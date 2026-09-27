<?php

namespace Zittme\Modules\Lodging\Controllers;

use Zittme\Modules\Lodging\Models\Booking as BookingModel;

/**
 * 코어 확장점 처리.
 */
class Trigger extends Base
{
	/**
	 * 사이트맵의 "모듈 연결" 목록에 자기 자신을 올린다.
	 *
	 * 코어는 인스턴스가 하나 이상 있는 모듈만 목록에 올린다.
	 *
	 * @param array $moduleList
	 * @return object
	 */
	public function addToSitemap(&$moduleList)
	{
		if (is_array($moduleList) && !in_array('lodging', $moduleList, true))
		{
			$moduleList[] = 'lodging';
		}

		return new \BaseObject();
	}

	/**
	 * 결제 승인 → 결제 대기 예약을 확정한다.
	 *
	 * 트리거는 중복 도착할 수 있다. 확정 전이에 이긴 한 번만 안내 메일을 보낸다.
	 * 결제 시간이 지나 이미 풀린 예약에 결제가 들어오면 그 결제를 돌려준다.
	 *
	 * @param object $order zittme_pay 주문 객체
	 * @return void
	 */
	public function triggerPayApproved($order)
	{
		if (!is_object($order) || ($order->source_module ?? '') !== 'lodging')
		{
			return;
		}

		$booking_srl = (int)($order->source_srl ?? 0);
		$booking = $booking_srl > 0 ? BookingModel::get($booking_srl) : null;
		if (!$booking)
		{
			return;
		}

		// 결제 금액이 예약 금액과 다르면 확정하지 않는다
		if ((int)($order->amount ?? 0) !== (int)$booking->total_amount)
		{
			return;
		}

		if (BookingModel::confirmPaid($booking_srl))
		{
			$fresh = BookingModel::get($booking_srl);
			$property = \Zittme\Modules\Lodging\Models\Property::get((int)$booking->property_srl);
			$room_type = \Zittme\Modules\Lodging\Models\RoomType::get((int)$booking->room_type_srl);
			if ($fresh && $property && $room_type)
			{
				\Zittme\Modules\Lodging\Models\Mailer::onBooked($fresh, $property, $room_type);
			}
			return;
		}

		$fresh = BookingModel::get($booking_srl);
		if (!$fresh)
		{
			return;
		}
		if ($fresh->status === BookingModel::STATUS_CONFIRMED)
		{
			BookingModel::updatePayStatus($booking_srl, 'paid');
			return;
		}
		if (in_array($fresh->status, [BookingModel::STATUS_EXPIRED, BookingModel::STATUS_CANCELED], true)
			&& (int)($order->order_srl ?? 0) > 0 && self::isPayAvailable())
		{
			$refund = \Zittme\Modules\Zittme_pay\PayService::cancel((int)$order->order_srl, lang('lodging.msg_late_payment_refund'));
			BookingModel::updatePayStatus($booking_srl, !empty($refund->success) ? 'refunded' : 'paid');
		}
	}

	/**
	 * 결제 취소/환불 통지.
	 *
	 * 손님·관리자가 예약을 취소하면 예약 쪽이 결제 취소를 먼저 부르므로,
	 * 여기 도착했을 때 예약이 아직 살아 있다면 결제 쪽에서 시작된 취소다 —
	 * 그때만 예약을 전액 환불로 되돌린다. 부분 환불 통지는 예약을 건드리지 않는다.
	 *
	 * @param object $order
	 * @return void
	 */
	public function triggerPayCancelled($order)
	{
		if (!is_object($order) || ($order->source_module ?? '') !== 'lodging')
		{
			return;
		}

		$booking_srl = (int)($order->source_srl ?? 0);
		$booking = $booking_srl > 0 ? BookingModel::get($booking_srl) : null;
		if (!$booking)
		{
			return;
		}

		$fully = ($order->status ?? '') === 'cancelled';
		if ($fully && in_array($booking->status, [BookingModel::STATUS_HOLD, BookingModel::STATUS_CONFIRMED], true))
		{
			BookingModel::cancel($booking, 'pay cancelled', true);
		}

		BookingModel::updatePayStatus($booking_srl, $fully ? 'refunded' : 'partial_refunded');
	}
}
