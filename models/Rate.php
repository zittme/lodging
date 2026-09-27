<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 요금 계산.
 *
 * 기본가는 객실 타입의 주중/금/토 3단 칼럼이고, 특정일 지정가(lodging_rate_override)가
 * 있으면 그것이 이긴다. 숙박 총액은 각 숙박일 요금의 합이다.
 * 계산된 금액은 예약에 스냅샷으로 저장되어 이후 요금표 변경의 영향을 받지 않는다.
 */
class Rate
{
	/**
	 * 특정 날짜 하루의 요금.
	 *
	 * @param object $room_type
	 * @param string $stay_type stay / dayuse
	 * @param string $ymd
	 * @return int
	 */
	public static function priceOf(object $room_type, string $stay_type, string $ymd): int
	{
		$override = self::getOverride((int)$room_type->room_type_srl, $stay_type, $ymd);
		if ($override !== null)
		{
			return $override;
		}

		// 0=일 ... 5=금, 6=토
		$dow = (int)date('w', strtotime($ymd));
		$prefix = $stay_type === 'dayuse' ? 'dayuse_price_' : 'stay_price_';

		if ($dow === 6)
		{
			return (int)$room_type->{$prefix . 'sat'};
		}
		if ($dow === 5)
		{
			return (int)$room_type->{$prefix . 'fri'};
		}

		return (int)$room_type->{$prefix . 'weekday'};
	}

	/**
	 * 구간 총액. 숙박은 각 밤의 합, 대실은 이용일 하루치다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param array $ymds Inventory::nights() 결과 (대실은 이용일 하나)
	 * @return int
	 */
	public static function totalOf(object $room_type, string $stay_type, array $ymds): int
	{
		$total = 0;
		foreach ($ymds as $ymd)
		{
			$total += self::priceOf($room_type, $stay_type, $ymd);
		}

		return $total;
	}

	/**
	 * 인원·연박까지 반영한 객실 금액. 예약 금액은 반드시 이 값으로 정한다.
	 *
	 * 연박 할인은 숙박 요금에만 걸고, 인원 추가 요금에는 걸지 않는다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param array $ymds
	 * @param int $person
	 * @return array [base, extra_person, long_stay_discount, total]
	 */
	public static function quote(object $room_type, string $stay_type, array $ymds, int $person): array
	{
		$base = self::totalOf($room_type, $stay_type, $ymds);
		$units = count($ymds);

		$std = max(1, (int)($room_type->std_person ?? 2));
		$max = max($std, (int)($room_type->max_person ?? $std));
		$person = min($max, max(1, $person));
		$extra = max(0, $person - $std) * max(0, (int)($room_type->extra_person_price ?? 0)) * $units;

		$discount = 0;
		$long_nights = (int)($room_type->long_stay_nights ?? 0);
		$long_rate = min(100, max(0, (int)($room_type->long_stay_rate ?? 0)));
		if ($stay_type === 'stay' && $long_nights > 0 && $long_rate > 0 && $units >= $long_nights)
		{
			$discount = (int)floor($base * $long_rate / 100);
		}

		return [
			'base' => $base,
			'extra_person' => $extra,
			'long_stay_discount' => $discount,
			'total' => max(0, $base + $extra - $discount),
		];
	}

	/**
	 * 특정일 지정가. 없으면 null.
	 *
	 * @param int $room_type_srl
	 * @param string $stay_type
	 * @param string $ymd
	 * @return ?int
	 */
	public static function getOverride(int $room_type_srl, string $stay_type, string $ymd): ?int
	{
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->stay_type = $stay_type;
		$args->ymd = $ymd;

		$output = executeQuery('lodging.getRateOverride', $args);
		if (!$output->toBool() || !$output->data)
		{
			return null;
		}

		$row = is_array($output->data) ? reset($output->data) : $output->data;
		return is_object($row) ? (int)$row->price : null;
	}

	/**
	 * 지정가를 넣거나 바꾼다. price 가 0 이하이면 지정가를 지운다 (기본가로 복귀).
	 *
	 * @param int $room_type_srl
	 * @param string $stay_type
	 * @param string $ymd
	 * @param int $price
	 * @param string $memo
	 * @return object
	 */
	public static function setOverride(int $room_type_srl, string $stay_type, string $ymd, int $price, string $memo = ''): object
	{
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->stay_type = $stay_type;
		$args->ymd = $ymd;

		if ($price <= 0)
		{
			return executeQuery('lodging.deleteRateOverride', $args);
		}

		$existing = self::getOverride($room_type_srl, $stay_type, $ymd);
		$args->price = $price;
		$args->memo = $memo;

		if ($existing !== null)
		{
			return executeQuery('lodging.updateRateOverride', $args);
		}

		$args->override_srl = getNextSequence();
		return executeQuery('lodging.insertRateOverride', $args);
	}
}
