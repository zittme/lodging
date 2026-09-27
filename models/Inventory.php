<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 날짜별 객실 재고.
 *
 * 재고 행은 미리 만들어 두지 않는다. 예약이 처음 닿는 날짜에 행을 만들고,
 * 차감은 "잔여가 있을 때만" 조건부 UPDATE 로 처리해 동시 예약의 초과 판매를 막는다.
 * 숙박은 체크인일~체크아웃 전날까지 각 날짜를, 대실은 이용일 하루를 차감한다.
 */
class Inventory
{
	/**
	 * 숙박 유형별 카운터 칼럼.
	 */
	protected const COUNTER = [
		'stay' => 'booked_stay',
		'dayuse' => 'booked_dayuse',
	];

	/**
	 * 대실과 숙박이 같은 객실 재고를 나눠 쓰는가.
	 *
	 * 대실 마감 시각이 숙박 입실 시각보다 늦으면 같은 날 같은 객실에서 두 손님이
	 * 겹친다. 그때는 두 카운터의 합이 객실 수를 넘지 못하게 한다. 대실이 입실 전에
	 * 끝나는 업소(낮 대실 뒤 밤 숙박)는 따로 센다.
	 *
	 * @param object $room_type
	 * @return bool
	 */
	public static function sharesPool(object $room_type): bool
	{
		static $checkin_times = [];
		if (($room_type->use_dayuse ?? 'N') !== 'Y')
		{
			return false;
		}

		$property_srl = (int)($room_type->property_srl ?? 0);
		if (!isset($checkin_times[$property_srl]))
		{
			$property = Property::get($property_srl);
			$checkin_times[$property_srl] = $property ? (string)$property->checkin_time : '1500';
		}

		$close = (string)($room_type->dayuse_close ?? '2200');
		// 자정을 넘기는 마감(0200 등)은 입실 시각보다 늦은 것으로 본다
		if ($close !== '' && $close < '0600')
		{
			return true;
		}
		return $close > $checkin_times[$property_srl];
	}

	/**
	 * 구간의 날짜 목록. 숙박은 체크아웃일을 포함하지 않는다.
	 *
	 * @param string $checkin_ymd
	 * @param string $checkout_ymd
	 * @return array YYYYMMDD 문자열 목록
	 */
	public static function nights(string $checkin_ymd, string $checkout_ymd): array
	{
		$start = strtotime($checkin_ymd);
		$end = strtotime($checkout_ymd);
		if ($start === false || $end === false || $start >= $end)
		{
			return [];
		}

		$list = [];
		for ($t = $start; $t < $end; $t += 86400)
		{
			$list[] = date('Ymd', $t);
		}

		return $list;
	}

	/**
	 * 구간 전체에 잔여가 있는가.
	 *
	 * 확정이 아니라 노출·사전 확인용이다. 확정은 reserve() 의 조건부 차감이 한다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param array $ymds
	 * @return bool
	 */
	public static function isAvailable(object $room_type, string $stay_type, array $ymds): bool
	{
		if (!count($ymds))
		{
			return false;
		}

		foreach ($ymds as $ymd)
		{
			if (self::remaining($room_type, $stay_type, $ymd) < 1)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * 특정 날짜의 잔여 수.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param string $ymd
	 * @return int
	 */
	public static function remaining(object $room_type, string $stay_type, string $ymd): int
	{
		$row = self::getRow((int)$room_type->room_type_srl, $ymd);
		$total = (int)$room_type->total_rooms;

		if ($row)
		{
			if ($row->closed === 'Y')
			{
				return 0;
			}
			if ((int)$row->total_override > 0)
			{
				$total = (int)$row->total_override;
			}
			if (self::sharesPool($room_type))
			{
				return max(0, $total - (int)$row->booked_stay - (int)$row->booked_dayuse);
			}
			$counter = self::COUNTER[$stay_type] ?? 'booked_stay';
			return max(0, $total - (int)$row->{$counter});
		}

		return max(0, $total);
	}

	/**
	 * 구간 전체를 차감한다. 하루라도 실패하면 성공한 날짜를 되돌리고 false.
	 *
	 * 각 날짜의 차감은 "카운터 < 상한" 일 때만 통과하는 조건부 UPDATE 라,
	 * 동시에 들어온 두 예약 중 마지막 한 자리는 한쪽만 가져간다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param array $ymds
	 * @return bool
	 */
	public static function reserve(object $room_type, string $stay_type, array $ymds): bool
	{
		if (!count($ymds) || !isset(self::COUNTER[$stay_type]))
		{
			return false;
		}

		$done = [];
		foreach ($ymds as $ymd)
		{
			if (!self::increment($room_type, $stay_type, $ymd))
			{
				foreach ($done as $ok_ymd)
				{
					self::decrement((int)$room_type->room_type_srl, $stay_type, $ok_ymd);
				}
				return false;
			}
			$done[] = $ymd;
		}

		return true;
	}

	/**
	 * 구간 전체를 복원한다 (취소).
	 *
	 * @param int $room_type_srl
	 * @param string $stay_type
	 * @param array $ymds
	 * @return void
	 */
	public static function release(int $room_type_srl, string $stay_type, array $ymds): void
	{
		foreach ($ymds as $ymd)
		{
			self::decrement($room_type_srl, $stay_type, $ymd);
		}
	}

	/**
	 * 하루 차감. 행이 없으면 만들고 나서 조건부로 올린다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param string $ymd
	 * @return bool
	 */
	protected static function increment(object $room_type, string $stay_type, string $ymd): bool
	{
		$room_type_srl = (int)$room_type->room_type_srl;
		$row = self::getRow($room_type_srl, $ymd);

		if (!$row)
		{
			// 동시 생성은 (room_type_srl, ymd) 유니크 인덱스가 한쪽을 떨어뜨린다.
			// 실패해도 행은 생겼다는 뜻이므로 그대로 조건부 UPDATE 로 넘어간다.
			$args = new \stdClass;
			$args->inventory_srl = getNextSequence();
			$args->room_type_srl = $room_type_srl;
			$args->ymd = $ymd;
			executeQuery('lodging.insertInventory', $args);
			$row = self::getRow($room_type_srl, $ymd);
			if (!$row)
			{
				return false;
			}
		}

		if ($row->closed === 'Y')
		{
			return false;
		}

		$cap = (int)$row->total_override > 0 ? (int)$row->total_override : (int)$room_type->total_rooms;
		$counter = self::COUNTER[$stay_type];

		// 같은 객실을 대실과 숙박이 함께 쓰는 날은 두 카운터의 합으로 상한을 건다
		if (self::sharesPool($room_type))
		{
			$stmt = \DB::getInstance()->query(
				'UPDATE lodging_inventory SET ' . $counter . ' = ' . $counter . ' + 1 WHERE room_type_srl = ? AND ymd = ? AND booked_stay + booked_dayuse < ?',
				[$room_type_srl, $ymd, $cap]
			);
			return $stmt && $stmt->rowCount() > 0;
		}

		// WHERE counter < cap 조건이 실패하면 갱신 행 수가 0 — 그날은 만실이다.
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->ymd = $ymd;
		$args->cap = $cap;
		$output = executeQuery('lodging.increment' . ucfirst($stay_type), $args);
		if (!$output->toBool())
		{
			return false;
		}

		return (int)\DB::getInstance()->getAffectedRows() > 0;
	}

	/**
	 * 하루 복원. 0 아래로는 내려가지 않는다.
	 *
	 * @param int $room_type_srl
	 * @param string $stay_type
	 * @param string $ymd
	 * @return void
	 */
	protected static function decrement(int $room_type_srl, string $stay_type, string $ymd): void
	{
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->ymd = $ymd;
		executeQuery('lodging.decrement' . ucfirst($stay_type), $args);
	}

	/**
	 * 특정 날짜의 판매 설정(판매 중지·객실 수 조정)을 바꾼다. 행이 없으면 만든다.
	 *
	 * @param int $room_type_srl
	 * @param string $ymd
	 * @param string $closed Y/N
	 * @param int $total_override 0 = 기본값 사용
	 * @return object
	 */
	public static function setDay(int $room_type_srl, string $ymd, string $closed, int $total_override): object
	{
		if (!self::getRow($room_type_srl, $ymd))
		{
			$args = new \stdClass;
			$args->inventory_srl = getNextSequence();
			$args->room_type_srl = $room_type_srl;
			$args->ymd = $ymd;
			executeQuery('lodging.insertInventory', $args);
		}

		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->ymd = $ymd;
		$args->closed = $closed === 'Y' ? 'Y' : 'N';
		$args->total_override = max(0, $total_override);

		return executeQuery('lodging.updateInventoryDay', $args);
	}

	/**
	 * 재고 행 하나.
	 *
	 * @param int $room_type_srl
	 * @param string $ymd
	 * @return ?object
	 */
	public static function getRow(int $room_type_srl, string $ymd): ?object
	{
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->ymd = $ymd;

		$output = executeQuery('lodging.getInventory', $args);
		return ($output->toBool() && $output->data && !is_array($output->data)) ? $output->data
			: (($output->toBool() && is_array($output->data) && count($output->data)) ? reset($output->data) : null);
	}

	/**
	 * 구간의 재고 행들. 달력 화면용.
	 *
	 * @param int $room_type_srl
	 * @param string $start_ymd
	 * @param string $end_ymd
	 * @return array ymd => row
	 */
	public static function getRange(int $room_type_srl, string $start_ymd, string $end_ymd): array
	{
		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;
		$args->start_ymd = $start_ymd;
		$args->end_ymd = $end_ymd;

		$output = executeQueryArray('lodging.getInventoryRange', $args);
		$map = [];
		foreach (($output->toBool() && is_array($output->data)) ? $output->data : [] as $row)
		{
			$map[$row->ymd] = $row;
		}

		return $map;
	}
}
