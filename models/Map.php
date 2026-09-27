<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 지도 보기·길찾기 새 창 링크.
 */
class Map
{
	public const SERVICES = ['kakao', 'naver', 'google', 'osm'];
	public const MODES = ['auto', 'kakao', 'naver', 'google', 'osm', 'multi'];

	/**
	 * 지도 링크 설정.
	 *
	 * @return object map_mode, map_services
	 */
	public static function config(): object
	{
		$config = \ModuleModel::getModuleConfig('lodging');
		$config = is_object($config) ? clone $config : new \stdClass;

		$mode = (string)($config->map_mode ?? '');
		$config->map_mode = in_array($mode, self::MODES, true) ? $mode : 'auto';

		$services = $config->map_services ?? [];
		$services = is_array($services) ? $services : explode(',', (string)$services);
		$config->map_services = array_values(array_intersect(self::SERVICES, array_map('strval', $services)));

		return $config;
	}

	/**
	 * 이 방문자에게 보일 지도 서비스.
	 *
	 * @param ?string $lang_type
	 * @return array
	 */
	public static function services(?string $lang_type = null): array
	{
		$config = self::config();
		if ($config->map_mode === 'multi')
		{
			return $config->map_services ?: ['google'];
		}
		if ($config->map_mode !== 'auto')
		{
			return [$config->map_mode];
		}

		$lang_type = $lang_type ?? (string)\Context::getLangType();
		return $lang_type === 'ko' ? ['kakao', 'naver'] : ['google'];
	}

	/**
	 * 숙소의 지도 보기·길찾기 링크. 좌표가 없으면 주소(상세주소 제외) 검색으로 연다.
	 *
	 * @param object $property
	 * @return array [{service, name, view_url, route_url}]
	 */
	public static function links(object $property): array
	{
		$address = trim((string)$property->address);
		$links = [];
		foreach (self::services() as $service)
		{
			$link = self::build($service, (string)$property->title, $address, $property->latitude ?? '', $property->longitude ?? '');
			if ($link)
			{
				$links[] = $link;
			}
		}

		return $links;
	}

	/**
	 * 지도 서비스 하나의 링크.
	 *
	 * @param string $service
	 * @param string $name
	 * @param string $address
	 * @param mixed $lat
	 * @param mixed $lng
	 * @return ?array
	 */
	public static function build(string $service, string $name, string $address, $lat, $lng): ?array
	{
		if (!in_array($service, self::SERVICES, true))
		{
			return null;
		}

		$has_coords = is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 90 && abs((float)$lng) <= 180 && ((float)$lat != 0 || (float)$lng != 0);
		$name = trim(preg_replace('/\s*,\s*/u', ' ', $name));
		$query = $address !== '' ? $address : $name;
		if (!$has_coords && $query === '')
		{
			return null;
		}

		$y = $has_coords ? rtrim(rtrim(sprintf('%.7F', (float)$lat), '0'), '.') : '';
		$x = $has_coords ? rtrim(rtrim(sprintf('%.7F', (float)$lng), '0'), '.') : '';
		$label = $name !== '' ? $name : $query;

		switch ($service)
		{
			case 'kakao':
				$view = $has_coords ? 'https://map.kakao.com/link/map/' . rawurlencode($label) . ',' . $y . ',' . $x : 'https://map.kakao.com/link/search/' . rawurlencode($query);
				$route = $has_coords ? 'https://map.kakao.com/link/to/' . rawurlencode($label) . ',' . $y . ',' . $x : $view;
				break;
			case 'naver':
				$view = $has_coords ? 'https://map.naver.com/p/?' . http_build_query(['title' => $label, 'lng' => $x, 'lat' => $y, 'zoom' => 17, 'type' => 0], '', '&', PHP_QUERY_RFC3986) : 'https://map.naver.com/p/search/' . rawurlencode($query);
				$route = $has_coords ? 'https://map.naver.com/index.nhn?' . http_build_query(['elng' => $x, 'elat' => $y, 'etext' => $label, 'menu' => 'route', 'pathType' => 0], '', '&', PHP_QUERY_RFC3986) : $view;
				break;
			case 'google':
				$target = $has_coords ? $y . ',' . $x : $query;
				$view = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($target);
				$route = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($target);
				break;
			default:
				$view = $has_coords ? 'https://www.openstreetmap.org/?mlat=' . $y . '&mlon=' . $x . '#map=17/' . $y . '/' . $x : 'https://www.openstreetmap.org/search?query=' . rawurlencode($query);
				$route = $has_coords ? 'https://www.openstreetmap.org/directions?route=' . rawurlencode(';' . $y . ',' . $x) . '#map=17/' . $y . '/' . $x : $view;
		}

		return [
			'service' => $service,
			'name' => lang('lodging.lodging_map_' . $service),
			'view_url' => $view,
			'route_url' => $route,
		];
	}
}
