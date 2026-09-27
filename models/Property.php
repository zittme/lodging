<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 업소.
 */
class Property
{
	/**
	 * 인스턴스의 업소 목록.
	 *
	 * @param int $module_srl
	 * @param string $status 빈 문자열이면 전체
	 * @return array
	 */
	public static function getList(int $module_srl, string $status = ''): array
	{
		$args = new \stdClass;
		$args->module_srl = $module_srl;
		if ($status !== '')
		{
			$args->status = $status;
		}

		$output = executeQueryArray('lodging.getPropertyList', $args);
		return $output->toBool() && is_array($output->data) ? Lang::localizeAll($output->data, Lang::PROPERTY_FIELDS) : [];
	}

	/**
	 * 단건.
	 *
	 * @param int $property_srl
	 * @return ?object
	 */
	public static function get(int $property_srl): ?object
	{
		if ($property_srl <= 0)
		{
			return null;
		}

		$args = new \stdClass;
		$args->property_srl = $property_srl;

		$output = executeQuery('lodging.getProperty', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		return Lang::localize(is_array($row) ? (count($row) ? reset($row) : null) : $row, Lang::PROPERTY_FIELDS);
	}

	/**
	 * 저장. property_srl 이 있으면 수정.
	 *
	 * @param object $args
	 * @return object 성공 시 property_srl 을 담는다
	 */
	public static function save(object $args): object
	{
		$args->property_srl = (int)($args->property_srl ?? 0);
		$args->last_update = date('YmdHis');

		if ($args->property_srl > 0 && self::get($args->property_srl))
		{
			$output = executeQuery('lodging.updateProperty', $args);
		}
		else
		{
			$args->property_srl = getNextSequence();
			$args->regdate = $args->last_update;
			$output = executeQuery('lodging.insertProperty', $args);
		}

		if ($output->toBool())
		{
			$output->add('property_srl', $args->property_srl);
		}

		return $output;
	}
}
