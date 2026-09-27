<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 호실 — 층×호실 그리드.
 *
 * 판매·재고 판정에는 쓰지 않는다. 프런트가 체크인 때 예약을 어느 호실에 넣는지
 * 배정하는 운영 계층이다. 그리드는 층·호수·칸 순서의 평면 구조라 층 복제가
 * 행 복사로 끝난다.
 */
class Room
{
	/**
	 * 업소의 호실 목록. 층 → 칸 순서로 정렬해 층별로 묶어 돌려준다.
	 *
	 * @param int $property_srl
	 * @return array floor => [room, ...]
	 */
	public static function getGrid(int $property_srl): array
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;

		$output = executeQueryArray('lodging.getRoomList', $args);
		$grid = [];
		foreach (($output->toBool() && is_array($output->data)) ? $output->data : [] as $room)
		{
			$grid[(int)$room->floor][] = $room;
		}

		krsort($grid);
		return $grid;
	}

	/**
	 * 그리드를 통째로 다시 쓴다.
	 *
	 * 화면이 [{floor, name, room_type_srl, status, memo}, ...] 를 보낸다.
	 * 칸 순서는 배열 순서다. 배정(assigned_room_srl)은 room_srl 을 새로 받으므로
	 * 저장 시 기존 배정이 끊긴다 — 그리드 구조 변경은 배정이 비는 시기에 할 일이다.
	 *
	 * @param int $property_srl
	 * @param array $rooms
	 * @return object
	 */
	public static function saveGrid(int $property_srl, array $rooms): object
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;
		$output = executeQuery('lodging.deleteRoomsByProperty', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$positions = [];
		foreach ($rooms as $room)
		{
			$room = (object)$room;
			$name = trim((string)($room->name ?? ''));
			if ($name === '')
			{
				continue;
			}
			$floor = (int)($room->floor ?? 1);
			$positions[$floor] = ($positions[$floor] ?? 0) + 1;

			$row = new \stdClass;
			$row->room_srl = getNextSequence();
			$row->property_srl = $property_srl;
			$row->room_type_srl = (int)($room->room_type_srl ?? 0);
			$row->floor = $floor;
			$row->name = mb_substr($name, 0, 40);
			$row->position = $positions[$floor];
			$row->status = ($room->status ?? 'open') === 'closed' ? 'closed' : 'open';
			$row->memo = mb_substr(trim((string)($room->memo ?? '')), 0, 120);

			$output = executeQuery('lodging.insertRoom', $row);
			if (!$output->toBool())
			{
				return $output;
			}
		}

		return new \BaseObject();
	}

	/**
	 * 객실 타입별 호실 수. total_rooms 와 어긋나면 관리 화면이 경고를 띄운다.
	 *
	 * @param int $property_srl
	 * @return array room_type_srl => count
	 */
	public static function countByType(int $property_srl): array
	{
		$counts = [];
		foreach (self::getGrid($property_srl) as $rooms)
		{
			foreach ($rooms as $room)
			{
				if ($room->status === 'open')
				{
					$counts[(int)$room->room_type_srl] = ($counts[(int)$room->room_type_srl] ?? 0) + 1;
				}
			}
		}

		return $counts;
	}
}
