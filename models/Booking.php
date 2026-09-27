<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 예약 원장.
 *
 * 확정 순서: 재고 차감(조건부) → 예약 행 삽입. 삽입이 실패하면 차감을 되돌린다.
 * 금액과 취소 규정은 예약 시점 스냅샷으로 저장되어 이후 요금표·규정 변경의
 * 영향을 받지 않는다. 환불 계산은 저장된 스냅샷만 본다.
 */
class Booking
{
	public const STATUS_HOLD = 'hold';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELED = 'canceled';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_NOSHOW = 'noshow';

	/**
	 * 선결제 예약이 결제 없이 재고를 붙잡아 둘 수 있는 시간(분).
	 */
	public const HOLD_MINUTES = 10;

	/**
	 * 예약을 확정한다.
	 *
	 * $args 필수: module_srl, property, room_type, stay_type, checkin_ymd, checkout_ymd,
	 *   guest_name, guest_phone. 선택: member_srl, guest_email, guest_password(평문),
	 *   person_count, dayuse_start, pay_method, coupon_srl, discount_amount, request_memo.
	 *
	 * @param object $args
	 * @return object 성공 시 booking_srl / booking_code 를 담는다
	 */
	public static function create(object $args): object
	{
		$property = $args->property;
		$room_type = $args->room_type;
		$stay_type = $args->stay_type === 'dayuse' ? 'dayuse' : 'stay';

		if ($stay_type === 'dayuse')
		{
			// 대실은 이용일 하루. checkout 은 이용일과 같다.
			$ymds = [$args->checkin_ymd];
			$args->checkout_ymd = $args->checkin_ymd;
		}
		else
		{
			$ymds = Inventory::nights($args->checkin_ymd, $args->checkout_ymd);
		}

		if (!count($ymds))
		{
			return new \BaseObject(-1, 'lodging.msg_invalid_dates');
		}

		$quote = Rate::quote($room_type, $stay_type, $ymds, (int)($args->person_count ?? 2));
		$room_amount = (int)$quote['total'];
		if ((int)$quote['base'] <= 0)
		{
			return new \BaseObject(-1, 'lodging.msg_no_price');
		}

		$discount = max(0, (int)($args->discount_amount ?? 0));
		// 포인트는 쿠폰 할인 뒤 남는 금액까지만 쓴다
		$point_used = min(max(0, (int)($args->point_used ?? 0)), max(0, $room_amount - $discount));
		$total = max(0, $room_amount - $discount - $point_used);

		// 재고를 먼저 잡는다. 여기서 실패하면 그 날짜 중 하나가 만실이다.
		if (!Inventory::reserve($room_type, $stay_type, $ymds))
		{
			return new \BaseObject(-1, 'lodging.msg_sold_out');
		}

		$row = new \stdClass;
		$row->booking_srl = getNextSequence();
		$row->booking_code = self::makeCode();
		$row->module_srl = (int)$args->module_srl;
		$row->property_srl = (int)$property->property_srl;
		$row->room_type_srl = (int)$room_type->room_type_srl;
		$row->vendor_srl = (int)($property->vendor_srl ?? 0);
		$row->stay_type = $stay_type;
		$row->checkin_ymd = $args->checkin_ymd;
		$row->checkout_ymd = $args->checkout_ymd;
		$row->dayuse_start = (string)($args->dayuse_start ?? '');
		// 인원은 1명 이상, 객실 최대 인원 이하로만 받는다. 넘겨 오면 최대 인원으로 맞춘다
		$max_person = max(1, (int)($room_type->max_person ?? 0));
		$row->person_count = min($max_person, max(1, (int)($args->person_count ?? 2)));
		$row->member_srl = (int)($args->member_srl ?? 0);
		$row->guest_name = mb_substr((string)$args->guest_name, 0, 80);
		$row->guest_phone = mb_substr((string)$args->guest_phone, 0, 60);
		$row->guest_email = mb_substr((string)($args->guest_email ?? ''), 0, 120);
		$row->guest_password = ($args->guest_password ?? '') !== ''
			? \Zittme\Framework\Password::hashPassword((string)$args->guest_password)
			: '';
		$row->room_amount = $room_amount;
		$row->discount_amount = $discount;
		$row->total_amount = $total;
		$row->coupon_srl = (int)($args->coupon_srl ?? 0);
		$row->point_used = $point_used;
		$row->pay_method = ($args->pay_method ?? '') === 'prepay' ? 'prepay' : 'onsite';
		// 현장결제는 결제 절차가 없으므로 바로 paid 취급하지 않고 pending 으로 남긴다.
		// 선결제는 결제가 끝나야 확정된다. 그 전까지는 정해진 시간만 재고를 붙잡는다.
		$row->pay_status = 'pending';
		if ($row->pay_method === 'prepay' && $total > 0)
		{
			$hold_minutes = max(3, (int)($args->hold_minutes ?? self::HOLD_MINUTES));
			$row->status = self::STATUS_HOLD;
			$row->hold_expires = date('YmdHis', time() + 60 * $hold_minutes);
		}
		else
		{
			$row->status = self::STATUS_CONFIRMED;
			$row->hold_expires = null;
		}
		$row->cancel_policy = json_encode(CancelRule::snapshot((int)$property->property_srl));
		$row->request_memo = mb_substr((string)($args->request_memo ?? ''), 0, 250);
		$row->ipaddress = \RX_CLIENT_IP;
		$row->regdate = date('YmdHis');
		$row->last_update = $row->regdate;

		$output = executeQuery('lodging.insertBooking', $row);
		if (!$output->toBool())
		{
			Inventory::release((int)$room_type->room_type_srl, $stay_type, $ymds);
			return $output;
		}

		// 포인트 차감 이력
		if ($point_used > 0 && $row->member_srl > 0)
		{
			Point::add((int)$row->member_srl, -$point_used, 'use', (int)$row->booking_srl, $row->booking_code);
		}

		$output->add('booking_srl', $row->booking_srl);
		$output->add('booking_code', $row->booking_code);
		$output->add('total_amount', $total);
		$output->add('status', $row->status);
		return $output;
	}

	/**
	 * 결제 승인 → 결제 대기 예약을 확정한다. 이긴 요청만 true.
	 *
	 * @param int $booking_srl
	 * @return bool
	 */
	public static function confirmPaid(int $booking_srl): bool
	{
		return self::transition($booking_srl, [self::STATUS_HOLD], self::STATUS_CONFIRMED, ['pay_status' => 'paid']);
	}

	/**
	 * 지금 상태가 $from 중 하나일 때만 바꾼다.
	 *
	 * @param int $booking_srl
	 * @param array $from
	 * @param string $to
	 * @param array $extra
	 * @return bool
	 */
	protected static function transition(int $booking_srl, array $from, string $to, array $extra = []): bool
	{
		$args = (object)array_merge($extra, [
			'booking_srl' => $booking_srl,
			'status' => $to,
			'from_status_list' => $from,
			'last_update' => date('YmdHis'),
		]);
		$output = executeQuery('lodging.updateBookingStatusIf', $args);
		return $output->toBool() && (int)\DB::getInstance()->getAffectedRows() > 0;
	}

	/**
	 * 재고·쿠폰·포인트를 되돌린다. 취소와 만료가 함께 쓴다.
	 *
	 * @param object $booking
	 * @return void
	 */
	protected static function releaseHeld(object $booking): void
	{
		$ymds = $booking->stay_type === 'dayuse'
			? [$booking->checkin_ymd]
			: Inventory::nights($booking->checkin_ymd, $booking->checkout_ymd);
		Inventory::release((int)$booking->room_type_srl, $booking->stay_type, $ymds);

		if ((int)$booking->coupon_srl > 0)
		{
			Coupon::rollback((int)$booking->booking_srl, (int)$booking->coupon_srl);
		}
		// 쓴 포인트는 전액 돌려준다 (환불율과 무관)
		if ((int)($booking->point_used ?? 0) > 0 && (int)$booking->member_srl > 0)
		{
			Point::add((int)$booking->member_srl, (int)$booking->point_used, 'refund', (int)$booking->booking_srl, (string)$booking->booking_code);
		}
	}

	/**
	 * 결제 시간이 지난 결제 대기 예약을 정리한다.
	 *
	 * 크론 없이도 재고가 맞도록 달력·예약 경로에서 부른다. 한 번에 조금씩만 한다.
	 *
	 * @return int 정리한 건수
	 */
	public static function expireStaleHolds(): int
	{
		$output = executeQueryArray('lodging.getExpiredHolds', (object)[
			'status' => self::STATUS_HOLD,
			'now' => date('YmdHis'),
			'list_count' => 20,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}

		$count = 0;
		foreach ($output->data as $booking)
		{
			// 결제 쪽 상태를 먼저 본다. 통지를 놓친 결제 완료 건은 확정하고,
			// 입금 대기(무통장·가상계좌)는 입금 기한까지 재고를 지켜 준다
			$order = class_exists('\\Zittme\\Modules\\Zittme_pay\\PayService')
				? \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('lodging', (int)$booking->booking_srl)
				: null;
			if ($order && (string)$order->status === 'paid' && (int)$order->amount === (int)$booking->total_amount)
			{
				self::confirmPaid((int)$booking->booking_srl);
				continue;
			}
			if ($order && (string)$order->status === 'pending')
			{
				$due = (string)($order->due_date ?? '');
				if (strlen($due) === 14 && $due > date('YmdHis'))
				{
					self::transition((int)$booking->booking_srl, [self::STATUS_HOLD], self::STATUS_HOLD, ['hold_expires' => $due]);
					continue;
				}
			}

			if (self::transition((int)$booking->booking_srl, [self::STATUS_HOLD], self::STATUS_EXPIRED, [
				'canceled_date' => date('YmdHis'),
				'cancel_reason' => 'payment timeout',
			]))
			{
				self::releaseHeld($booking);
				$count++;
			}
		}
		return $count;
	}

	/**
	 * 예약을 취소한다. 환불액은 예약에 저장된 규정 스냅샷으로 계산한다.
	 *
	 * @param object $booking
	 * @param string $reason
	 * @param bool $force_full_refund 관리자가 전액 환불로 처리할 때
	 * @return object 성공 시 refund_amount 를 담는다
	 */
	public static function cancel(object $booking, string $reason = '', bool $force_full_refund = false): object
	{
		if (!in_array($booking->status, [self::STATUS_HOLD, self::STATUS_CONFIRMED], true))
		{
			return new \BaseObject(-1, 'lodging.msg_not_cancelable');
		}

		// 아직 받은 돈이 없으면(결제 대기, 현장결제 미수납) 돌려줄 금액도 없다
		if ($booking->status === self::STATUS_HOLD || $booking->pay_status !== 'paid')
		{
			$refund = 0;
		}
		else
		{
			$refund = $force_full_refund ? (int)$booking->total_amount : self::refundAmountOf($booking);
		}

		$won = self::transition((int)$booking->booking_srl, [self::STATUS_HOLD, self::STATUS_CONFIRMED], self::STATUS_CANCELED, [
			'cancel_reason' => mb_substr($reason, 0, 250),
			'canceled_date' => date('YmdHis'),
			'refund_amount' => $refund,
		]);
		if (!$won)
		{
			return new \BaseObject(-1, 'lodging.msg_not_cancelable');
		}

		self::releaseHeld($booking);

		$output = new \BaseObject();
		$output->add('refund_amount', $refund);
		return $output;
	}

	/**
	 * 지금 취소하면 얼마를 돌려받는가.
	 *
	 * 규정 스냅샷은 [{days_before, refund_rate}, ...] 이고 days_before 내림차순으로
	 * 훑어 "체크인까지 남은 일수 >= days_before" 인 첫 구간의 환불율을 쓴다.
	 * 스냅샷이 비어 있으면 전액 환불이다.
	 *
	 * @param object $booking
	 * @return int
	 */
	public static function refundAmountOf(object $booking): int
	{
		$total = (int)$booking->total_amount;
		$rules = json_decode((string)$booking->cancel_policy, true);
		if (!is_array($rules) || !count($rules))
		{
			return $total;
		}

		$days_left = (int)floor((strtotime($booking->checkin_ymd) - strtotime(date('Ymd'))) / 86400);

		usort($rules, function($a, $b) {
			return (int)$b['days_before'] - (int)$a['days_before'];
		});

		foreach ($rules as $rule)
		{
			if ($days_left >= (int)$rule['days_before'])
			{
				return (int)floor($total * (int)$rule['refund_rate'] / 100);
			}
		}

		// 남은 일수가 가장 작은 구간보다도 적다 (이미 지났거나 당일 규정 없음) — 환불 없음
		return 0;
	}

	/**
	 * 결제 상태를 바꾼다. zittme_pay 결과 처리에서 부른다.
	 *
	 * @param int $booking_srl
	 * @param string $pay_status
	 * @return object
	 */
	public static function updatePayStatus(int $booking_srl, string $pay_status): object
	{
		$args = new \stdClass;
		$args->booking_srl = $booking_srl;
		$args->pay_status = $pay_status;
		$args->last_update = date('YmdHis');

		return executeQuery('lodging.updateBookingStatus', $args);
	}

	/**
	 * 상태만 바꾼다 (이용완료·노쇼, 호실 배정).
	 *
	 * @param int $booking_srl
	 * @param array $fields status / assigned_room_srl 등
	 * @return object
	 */
	public static function update(int $booking_srl, array $fields): object
	{
		$args = new \stdClass;
		$args->booking_srl = $booking_srl;
		foreach ($fields as $key => $value)
		{
			$args->{$key} = $value;
		}
		$args->last_update = date('YmdHis');

		return executeQuery('lodging.updateBookingStatus', $args);
	}

	/**
	 * 단건.
	 *
	 * @param int $booking_srl
	 * @return ?object
	 */
	public static function get(int $booking_srl): ?object
	{
		$args = new \stdClass;
		$args->booking_srl = $booking_srl;

		$output = executeQuery('lodging.getBooking', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return (is_array($row) ? (count($row) ? reset($row) : null) : $row);
	}

	/**
	 * 예약번호로 찾는다. 비회원 조회와 zittme_pay source_code 역추적에 쓴다.
	 *
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(string $code): ?object
	{
		$args = new \stdClass;
		$args->booking_code = $code;

		$output = executeQuery('lodging.getBookingByCode', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return (is_array($row) ? (count($row) ? reset($row) : null) : $row);
	}

	/**
	 * 목록. 관리 콘솔·마이페이지 공용.
	 *
	 * @param object $args
	 * @return object executeQueryArray 결과 그대로
	 */
	public static function getList(object $args): object
	{
		$args->list_count = $args->list_count ?? 20;
		$args->page_count = $args->page_count ?? 10;
		$args->page = $args->page ?? 1;

		return executeQueryArray('lodging.getBookingList', $args);
	}

	/**
	 * 예약번호. LG + 날짜 + 난수 6자리.
	 *
	 * @return string
	 */
	protected static function makeCode(): string
	{
		return 'LG' . date('ymd') . strtoupper(\Zittme\Framework\Security::getRandom(6, 'alnum'));
	}
}
