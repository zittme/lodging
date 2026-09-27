<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 객실 타입 — 판매 단위.
 */
class RoomType
{
	/**
	 * 업소의 객실 타입 목록.
	 *
	 * @param int $property_srl
	 * @param string $status 빈 문자열이면 전체
	 * @return array
	 */
	public static function getList(int $property_srl, string $status = ''): array
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;
		if ($status !== '')
		{
			$args->status = $status;
		}

		$output = executeQueryArray('lodging.getRoomTypeList', $args);
		return $output->toBool() && is_array($output->data) ? $output->data : [];
	}

	/**
	 * 단건.
	 *
	 * @param int $room_type_srl
	 * @return ?object
	 */
	public static function get(int $room_type_srl): ?object
	{
		if ($room_type_srl <= 0)
		{
			return null;
		}

		$args = new \stdClass;
		$args->room_type_srl = $room_type_srl;

		$output = executeQuery('lodging.getRoomType', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return (is_array($row) ? (count($row) ? reset($row) : null) : $row);
	}

	/**
	 * 업소의 최저 숙박 기본가. 목록 카드의 "N원~" 표기에 쓴다.
	 *
	 * @param int $property_srl
	 * @return int 판매 중인 타입이 없으면 0
	 */
	public static function minPrice(int $property_srl): int
	{
		$min = 0;
		foreach (self::getList($property_srl, 'open') as $room_type)
		{
			$price = (int)$room_type->stay_price_weekday;
			if ($price > 0 && ($min === 0 || $price < $min))
			{
				$min = $price;
			}
		}

		return $min;
	}

	/**
	 * 저장. room_type_srl 이 있으면 수정.
	 *
	 * @param object $args
	 * @return object 성공 시 room_type_srl 을 담는다
	 */
	public static function save(object $args): object
	{
		$args->room_type_srl = (int)($args->room_type_srl ?? 0);
		$args->last_update = date('YmdHis');

		if ($args->room_type_srl > 0 && self::get($args->room_type_srl))
		{
			$output = executeQuery('lodging.updateRoomType', $args);
		}
		else
		{
			$args->room_type_srl = getNextSequence();
			$args->regdate = $args->last_update;
			$output = executeQuery('lodging.insertRoomType', $args);
		}

		if ($output->toBool())
		{
			$output->add('room_type_srl', $args->room_type_srl);
		}

		return $output;
	}
}
