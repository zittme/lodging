<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 포인트(적립금). 숙박 모듈 자체 원장이며 코어 point 모듈과 연동하지 않는다.
 *
 * lodging_point 에 +/- 이력을 쌓고 잔액은 합계로 구한다.
 * reason: earn(이용 완료 적립) / use(예약 사용) / refund(취소 환급) / revoke(적립 회수) / admin(관리자 지급)
 */
class Point
{
	/**
	 * 이용 완료 시 결제 금액 대비 적립 비율(%). 모듈 설정 point_rate 로 바꿀 수 있다.
	 */
	public const DEFAULT_RATE = 1;

	/**
	 * 한 번에 쓸 수 있는 최소 포인트. 모듈 설정 point_min_use 로 바꿀 수 있다.
	 */
	public const DEFAULT_MIN_USE = 1000;

	public static function config(): object
	{
		$config = \ModuleModel::getModuleConfig('lodging');
		$config = is_object($config) ? $config : new \stdClass;
		$config->point_rate = isset($config->point_rate) && $config->point_rate !== '' ? (float)$config->point_rate : self::DEFAULT_RATE;
		$config->point_min_use = isset($config->point_min_use) && $config->point_min_use !== '' ? (int)$config->point_min_use : self::DEFAULT_MIN_USE;
		return $config;
	}

	/**
	 * 잔액.
	 *
	 * @param int $member_srl
	 * @return int
	 */
	public static function balance(int $member_srl): int
	{
		if ($member_srl <= 0)
		{
			return 0;
		}
		$args = new \stdClass;
		$args->member_srl = $member_srl;
		$output = executeQuery('lodging.getPointBalance', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;
		return $row ? (int)$row->balance : 0;
	}

	/**
	 * 이력 한 줄을 남긴다. 음수는 차감이다.
	 *
	 * @param int $member_srl
	 * @param int $amount
	 * @param string $reason
	 * @param int $booking_srl
	 * @param string $memo
	 * @return object
	 */
	public static function add(int $member_srl, int $amount, string $reason, int $booking_srl = 0, string $memo = ''): object
	{
		if ($member_srl <= 0 || $amount === 0)
		{
			return new \BaseObject();
		}
		$args = new \stdClass;
		$args->log_srl = getNextSequence();
		$args->member_srl = $member_srl;
		$args->amount = $amount;
		$args->reason = $reason;
		$args->booking_srl = $booking_srl;
		$args->memo = mb_substr($memo, 0, 200);
		$args->regdate = date('YmdHis');
		return executeQuery('lodging.insertPoint', $args);
	}

	/**
	 * 회원 이력.
	 *
	 * @param int $member_srl
	 * @param int $count
	 * @return array
	 */
	public static function history(int $member_srl, int $count = 50): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}
		$args = new \stdClass;
		$args->member_srl = $member_srl;
		$args->list_count = $count;
		$output = executeQueryArray('lodging.getPointList', $args);
		return ($output->toBool() && is_array($output->data)) ? $output->data : [];
	}

	/**
	 * 예약에서 쓸 수 있는 최대 포인트. 잔액과 결제 대상 금액 중 작은 쪽이며 최소 사용 단위를 지킨다.
	 *
	 * @param int $member_srl
	 * @param int $payable
	 * @param int $requested
	 * @return int
	 */
	public static function clampUse(int $member_srl, int $payable, int $requested): int
	{
		if ($member_srl <= 0 || $requested <= 0)
		{
			return 0;
		}
		$use = min($requested, self::balance($member_srl), max(0, $payable));
		if ($use < self::config()->point_min_use)
		{
			return 0;
		}
		return $use;
	}

	/**
	 * 이용 완료 적립. 같은 예약에 두 번 적립하지 않는다.
	 *
	 * @param object $booking
	 * @return int 적립액
	 */
	public static function earnFor(object $booking): int
	{
		$member_srl = (int)($booking->member_srl ?? 0);
		if ($member_srl <= 0)
		{
			return 0;
		}
		$args = new \stdClass;
		$args->member_srl = $member_srl;
		$args->booking_srl = (int)$booking->booking_srl;
		$args->reason = 'earn';
		$output = executeQueryArray('lodging.getPointList', $args);
		if ($output->toBool() && is_array($output->data) && count($output->data))
		{
			return 0;
		}
		$amount = (int)floor((int)$booking->total_amount * self::config()->point_rate / 100);
		if ($amount <= 0)
		{
			return 0;
		}
		self::add($member_srl, $amount, 'earn', (int)$booking->booking_srl, (string)$booking->booking_code);
		return $amount;
	}
}
