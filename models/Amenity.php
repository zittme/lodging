<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 편의시설 사전.
 *
 * 숙소·객실의 amenities 열은 이 사전의 키를 쉼표로 이어 저장한다 (예: "wifi,parking,breakfast").
 * 사전에 없는 키는 읽을 때 걸러낸다. 표시 이름은 언어 파일의 lodging_amenity_<키> 다.
 */
class Amenity
{
	/**
	 * 사전 순서가 곧 화면 표시 순서다.
	 */
	public const KEYS = [
		'wifi', 'parking', 'breakfast', 'pool', 'spa', 'sauna', 'fitness', 'restaurant',
		'bbq', 'kitchen', 'pet', 'smoking_free', 'ocean_view', 'mountain_view', 'terrace',
		'netflix', 'pc', 'bathtub', 'elevator', 'wheelchair', 'laundry', 'lounge', 'shuttle',
	];

	/**
	 * 저장 문자열을 키 배열로. 사전에 없는 값은 버린다.
	 *
	 * @param string|null $stored
	 * @return array
	 */
	public static function parse(?string $stored): array
	{
		$keys = [];
		foreach (explode(',', (string)$stored) as $raw)
		{
			$key = trim($raw);
			if ($key !== '' && in_array($key, self::KEYS, true) && !in_array($key, $keys, true))
			{
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * 키 배열을 저장 문자열로. 사전 순서로 정렬한다.
	 *
	 * @param array $keys
	 * @return string
	 */
	public static function format(array $keys): string
	{
		$clean = [];
		foreach (self::KEYS as $key)
		{
			if (in_array($key, $keys, true))
			{
				$clean[] = $key;
			}
		}
		return implode(',', $clean);
	}

	/**
	 * 화면용 목록: [['key' => 'wifi', 'label' => '무료 와이파이'], ...]
	 *
	 * @param string|null $stored
	 * @return array
	 */
	public static function labels(?string $stored): array
	{
		$list = [];
		foreach (self::parse($stored) as $key)
		{
			$list[] = ['key' => $key, 'label' => self::labelOf($key)];
		}
		return $list;
	}

	/**
	 * 키 하나의 표시 이름. 언어 파일에 없으면 키를 그대로 돌려준다.
	 *
	 * @param string $key
	 * @return string
	 */
	public static function labelOf(string $key): string
	{
		$label = lang('lodging.lodging_amenity_' . $key);
		return (is_string($label) && $label !== '' && strpos($label, 'lodging_amenity_') === false) ? $label : $key;
	}
}
