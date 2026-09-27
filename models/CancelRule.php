<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 취소 규정.
 *
 * 업소별 (체크인 N일 전, 환불율 %) 구간 목록이다. 예약 시점에 snapshot() 결과가
 * 예약에 JSON 으로 저장되고, 환불 계산은 그 스냅샷만 본다 — 이후 규정을 바꿔도
 * 이미 받은 예약에는 적용되지 않는다.
 */
class CancelRule
{
	/**
	 * 업소의 규정 목록. days_before 내림차순.
	 *
	 * @param int $property_srl
	 * @return array
	 */
	public static function getList(int $property_srl): array
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;

		$output = executeQueryArray('lodging.getCancelRules', $args);
		return $output->toBool() && is_array($output->data) ? $output->data : [];
	}

	/**
	 * 예약에 저장할 스냅샷.
	 *
	 * @param int $property_srl
	 * @return array [{days_before, refund_rate}, ...]
	 */
	public static function snapshot(int $property_srl): array
	{
		$list = [];
		foreach (self::getList($property_srl) as $rule)
		{
			$list[] = [
				'days_before' => (int)$rule->days_before,
				'refund_rate' => (int)$rule->refund_rate,
			];
		}

		return $list;
	}

	/**
	 * 규정을 통째로 다시 쓴다.
	 *
	 * @param int $property_srl
	 * @param array $rules [{days_before, refund_rate}, ...]
	 * @return object
	 */
	public static function saveAll(int $property_srl, array $rules): object
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;
		$output = executeQuery('lodging.deleteCancelRules', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$seen = [];
		foreach ($rules as $rule)
		{
			$rule = (object)$rule;
			$days = max(0, (int)($rule->days_before ?? 0));
			$rate = min(100, max(0, (int)($rule->refund_rate ?? 0)));
			if (isset($seen[$days]))
			{
				continue;
			}
			$seen[$days] = true;

			$row = new \stdClass;
			$row->rule_srl = getNextSequence();
			$row->property_srl = $property_srl;
			$row->days_before = $days;
			$row->refund_rate = $rate;

			$output = executeQuery('lodging.insertCancelRule', $row);
			if (!$output->toBool())
			{
				return $output;
			}
		}

		return new \BaseObject();
	}
}
