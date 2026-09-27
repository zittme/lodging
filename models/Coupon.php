<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 자체 쿠폰 원장.
 *
 * 커머스 쿠폰과 무관하다 — 이 모듈은 커머스 없이 동작해야 한다.
 * 할인액 계산과 사용 기록이 전부이며, 사용 한도는 used_count 와 사용 이력으로 센다.
 */
class Coupon
{
	/**
	 * 단건.
	 *
	 * @param int $coupon_srl
	 * @return ?object
	 */
	public static function get(int $coupon_srl): ?object
	{
		$args = new \stdClass;
		$args->coupon_srl = $coupon_srl;

		$output = executeQuery('lodging.getCoupon', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return Lang::localize(is_array($row) ? (count($row) ? reset($row) : null) : $row, Lang::COUPON_FIELDS);
	}

	/**
	 * 코드로 찾는다.
	 *
	 * @param int $module_srl
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(int $module_srl, string $code): ?object
	{
		$args = new \stdClass;
		$args->module_srl = $module_srl;
		$args->code = $code;

		$output = executeQuery('lodging.getCouponByCode', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return Lang::localize(is_array($row) ? (count($row) ? reset($row) : null) : $row, Lang::COUPON_FIELDS);
	}

	/**
	 * 이 예약 조건에 쿠폰을 쓸 수 있는가. 되면 할인액, 안 되면 오류 메시지 키.
	 *
	 * @param object $coupon
	 * @param int $property_srl
	 * @param string $stay_type
	 * @param int $room_amount
	 * @param int $member_srl
	 * @return array [할인액, 오류 키('' = 사용 가능)]
	 */
	public static function evaluate(object $coupon, int $property_srl, string $stay_type, int $room_amount, int $member_srl): array
	{
		$today = date('Ymd');

		if ($coupon->status !== 'open')
		{
			return [0, 'lodging.msg_coupon_closed'];
		}
		if ((int)$coupon->property_srl > 0 && (int)$coupon->property_srl !== $property_srl)
		{
			return [0, 'lodging.msg_coupon_not_applicable'];
		}
		if ($coupon->apply_to !== 'all' && $coupon->apply_to !== $stay_type)
		{
			return [0, 'lodging.msg_coupon_not_applicable'];
		}
		if (($coupon->start_ymd ?? '') !== '' && $today < $coupon->start_ymd)
		{
			return [0, 'lodging.msg_coupon_not_started'];
		}
		if (($coupon->end_ymd ?? '') !== '' && $today > $coupon->end_ymd)
		{
			return [0, 'lodging.msg_coupon_expired'];
		}
		if ($room_amount < (int)$coupon->min_amount)
		{
			return [0, 'lodging.msg_coupon_min_amount'];
		}
		if ((int)$coupon->use_limit > 0 && (int)$coupon->used_count >= (int)$coupon->use_limit)
		{
			return [0, 'lodging.msg_coupon_exhausted'];
		}
		if ($member_srl > 0 && (int)$coupon->per_member_limit > 0
			&& self::countUsesByMember((int)$coupon->coupon_srl, $member_srl) >= (int)$coupon->per_member_limit)
		{
			return [0, 'lodging.msg_coupon_member_limit'];
		}

		if ($coupon->discount_type === 'rate')
		{
			$discount = (int)floor($room_amount * (int)$coupon->discount_value / 100);
			if ((int)$coupon->max_discount > 0)
			{
				$discount = min($discount, (int)$coupon->max_discount);
			}
		}
		else
		{
			$discount = (int)$coupon->discount_value;
		}

		return [min($discount, $room_amount), ''];
	}

	/**
	 * 방문자에게 보여 줄 쿠폰. 열려 있고, 기간 안이고, 수량이 남았고, 이 숙소에 쓸 수 있는 것만.
	 *
	 * @param int $module_srl
	 * @param int $property_srl
	 * @return array
	 */
	public static function publicList(int $module_srl, int $property_srl): array
	{
		$args = new \stdClass;
		$args->module_srl = $module_srl;
		$output = executeQueryArray('lodging.getCouponList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$today = date('Ymd');
		$list = [];
		foreach ($output->data as $coupon)
		{
			if ($coupon->status !== 'open') { continue; }
			if ((int)$coupon->property_srl > 0 && (int)$coupon->property_srl !== $property_srl) { continue; }
			if (($coupon->start_ymd ?? '') !== '' && $today < $coupon->start_ymd) { continue; }
			if (($coupon->end_ymd ?? '') !== '' && $today > $coupon->end_ymd) { continue; }
			if ((int)$coupon->use_limit > 0 && (int)$coupon->used_count >= (int)$coupon->use_limit) { continue; }
			$list[] = Lang::localize($coupon, Lang::COUPON_FIELDS);
		}
		return $list;
	}

	/**
	 * 회원이 공개 쿠폰을 내 쿠폰함에 담는다. 같은 쿠폰은 한 장만 담긴다.
	 *
	 * @param object $coupon
	 * @param int $member_srl
	 * @return object
	 */
	public static function claim(object $coupon, int $member_srl): object
	{
		if ($member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_not_logged');
		}
		if (self::getWalletByCoupon((int)$coupon->coupon_srl, $member_srl))
		{
			return new \BaseObject(-1, 'lodging.msg_coupon_already_claimed');
		}

		$args = new \stdClass;
		$args->wallet_srl = getNextSequence();
		$args->coupon_srl = (int)$coupon->coupon_srl;
		$args->member_srl = $member_srl;
		$args->status = 'available';
		$args->booking_srl = 0;
		$args->regdate = date('YmdHis');
		return executeQuery('lodging.insertCouponWallet', $args);
	}

	/**
	 * 회원의 쿠폰함. 쿠폰 정보와 상태(available / used / expired / closed)를 붙여 돌려준다.
	 *
	 * @param int $member_srl
	 * @return array
	 */
	public static function walletList(int $member_srl): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}
		$args = new \stdClass;
		$args->member_srl = $member_srl;
		$output = executeQueryArray('lodging.getCouponWalletList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$today = date('Ymd');
		$list = [];
		foreach ($output->data as $row)
		{
			$coupon = self::get((int)$row->coupon_srl);
			if (!$coupon)
			{
				continue;
			}
			$row->coupon = $coupon;
			$row->state = (string)$row->status;
			if ($row->state === 'available')
			{
				if ($coupon->status !== 'open') { $row->state = 'closed'; }
				elseif (($coupon->end_ymd ?? '') !== '' && $today > $coupon->end_ymd) { $row->state = 'expired'; }
				elseif ((int)$coupon->use_limit > 0 && (int)$coupon->used_count >= (int)$coupon->use_limit) { $row->state = 'closed'; }
			}
			$list[] = $row;
		}
		return $list;
	}

	/**
	 * 쿠폰함 한 장. 회원 본인 것만 돌려준다.
	 *
	 * @param int $wallet_srl
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function getWallet(int $wallet_srl, int $member_srl): ?object
	{
		$args = new \stdClass;
		$args->wallet_srl = $wallet_srl;
		$args->member_srl = $member_srl;
		$output = executeQuery('lodging.getCouponWallet', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;
		return $row ?: null;
	}

	/**
	 * 회원이 이 쿠폰을 담아 두었는지. 사용 여부와 무관하게 한 장 돌려준다.
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function getWalletByCoupon(int $coupon_srl, int $member_srl): ?object
	{
		$args = new \stdClass;
		$args->coupon_srl = $coupon_srl;
		$args->member_srl = $member_srl;
		$output = executeQuery('lodging.getCouponWalletByCoupon', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;
		return $row ?: null;
	}

	/**
	 * 사용을 기록한다. 예약 확정 직후에 부른다.
	 *
	 * @param int $coupon_srl
	 * @param int $booking_srl
	 * @param int $member_srl
	 * @param int $discount_amount
	 * @return void
	 */
	public static function record(int $coupon_srl, int $booking_srl, int $member_srl, int $discount_amount): void
	{
		$args = new \stdClass;
		$args->use_srl = getNextSequence();
		$args->coupon_srl = $coupon_srl;
		$args->booking_srl = $booking_srl;
		$args->member_srl = $member_srl;
		$args->discount_amount = $discount_amount;
		$args->regdate = date('YmdHis');
		executeQuery('lodging.insertCouponUse', $args);

		$count = new \stdClass;
		$count->coupon_srl = $coupon_srl;
		executeQuery('lodging.incrementCouponUsed', $count);

		// 쿠폰함에 담아 둔 장이 있으면 그 장을 사용 처리한다
		if ($member_srl > 0)
		{
			$find = new \stdClass;
			$find->coupon_srl = $coupon_srl;
			$find->member_srl = $member_srl;
			$find->status = 'available';
			$output = executeQuery('lodging.getCouponWalletByCoupon', $find);
			$row = ($output->toBool() && $output->data) ? $output->data : null;
			$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;
			if ($row)
			{
				$use = new \stdClass;
				$use->wallet_srl = (int)$row->wallet_srl;
				$use->status = 'used';
				$use->booking_srl = $booking_srl;
				$use->used_date = date('YmdHis');
				executeQuery('lodging.updateCouponWalletUsed', $use);
			}
		}
	}

	/**
	 * 예약 취소 시 사용 이력을 되돌린다.
	 *
	 * @param int $booking_srl
	 * @param int $coupon_srl
	 * @return void
	 */
	public static function rollback(int $booking_srl, int $coupon_srl): void
	{
		$args = new \stdClass;
		$args->booking_srl = $booking_srl;
		executeQuery('lodging.deleteCouponUseByBooking', $args);

		$count = new \stdClass;
		$count->coupon_srl = $coupon_srl;
		executeQuery('lodging.decrementCouponUsed', $count);

		// 쿠폰함 장도 다시 쓸 수 있게 되돌린다
		$back = new \stdClass;
		$back->booking_srl = $booking_srl;
		$back->status = 'available';
		$back->reset_booking_srl = 0;
		$back->used_date = '';
		executeQuery('lodging.updateCouponWalletRollback', $back);
	}

	/**
	 * 회원의 이 쿠폰 사용 횟수.
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @return int
	 */
	protected static function countUsesByMember(int $coupon_srl, int $member_srl): int
	{
		$args = new \stdClass;
		$args->coupon_srl = $coupon_srl;
		$args->member_srl = $member_srl;

		$output = executeQuery('lodging.getCouponUseCount', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;
		return is_object($row) ? (int)$row->count : 0;
	}
}
