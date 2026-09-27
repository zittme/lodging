<?php

namespace Zittme\Modules\Lodging\Controllers;

/**
 * 설치와 업데이트.
 *
 * 테이블은 schemas/*.xml 을 코어가 만들어 준다.
 */
class Install extends Base
{
	/**
	 * 최초 스키마 이후에 추가된 칼럼들. [테이블, 칼럼, 타입, 길이]
	 *
	 * 코어는 이미 존재하는 테이블에 스키마 XML 의 새 칼럼을 자동으로 붙여 주지 않는다.
	 * 스키마에 칼럼을 추가할 때는 반드시 이 표에도 같이 적을 것.
	 */
	public const ADDED_COLUMNS = [
		['lodging_booking', 'point_used', 'bigint', null, 0],
		['lodging_booking', 'hold_expires', 'char', 14, null],
		['lodging_room_type', 'min_nights', 'bigint', null, 1],
		['lodging_room_type', 'max_nights', 'bigint', null, 30],
		['lodging_room_type', 'extra_person_price', 'bigint', null, 0],
		['lodging_room_type', 'long_stay_nights', 'bigint', null, 0],
		['lodging_room_type', 'long_stay_rate', 'bigint', null, 0],
	];

	/**
	 * 최초 설치 이후 추가된 테이블들. 스키마 파일명과 같다.
	 */
	public const ADDED_TABLES = ['lodging_coupon_wallet', 'lodging_point'];

	public function moduleInstall()
	{
		return new \BaseObject();
	}

	public function checkUpdate()
	{
		$oDB = \DB::getInstance();

		foreach (self::ADDED_COLUMNS as [$table, $column])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				return true;
			}
		}

		foreach (self::ADDED_TABLES as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				return true;
			}
		}

		return false;
	}

	public function moduleUpdate()
	{
		$oDB = \DB::getInstance();

		foreach (self::ADDED_COLUMNS as [$table, $column, $type, $size, $default])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				// 기본값이 있는 숫자 칼럼은 NOT NULL 로 붙인다. 기존 행이 NULL 이면 계산이 어긋난다
				$oDB->addColumn($table, $column, $type, $size, $default, $default !== null);
			}
		}
		if ($oDB->isColumnExists('lodging_booking', 'hold_expires') && !$oDB->isIndexExists('lodging_booking', 'idx_hold_expires'))
		{
			$oDB->addIndex('lodging_booking', 'idx_hold_expires', ['hold_expires']);
		}

		foreach (self::ADDED_TABLES as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				$oDB->createTable($this->module_path . 'schemas/' . $table . '.xml');
			}
		}

		return new \BaseObject();
	}

	public function recompileCache()
	{
	}
}
