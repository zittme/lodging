<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 다국어 문구 — 코어의 사용자 정의 언어(lang 테이블)를 그대로 쓴다.
 *
 * 관리자가 입력한 문구는 '$user_lang->코드' 로 저장하고, 화면에 내보내기 전에
 * Context::replaceUserLang 으로 현재 언어 값으로 바꾼다. 원본은 <필드>_raw 에 남겨
 * 편집 화면이 연결된 코드를 알 수 있게 한다.
 */
class Lang
{
	public const PREFIX = '$user_lang->';

	/**
	 * 모델이 돌려주는 행에서 다국어로 바꿀 칸.
	 */
	public const PROPERTY_FIELDS = ['title', 'summary', 'description', 'address', 'address_detail'];
	public const ROOM_TYPE_FIELDS = ['title', 'description'];
	public const COUPON_FIELDS = ['title'];

	/**
	 * 사이트에 켜둔 언어 목록 (코드 => 이름).
	 *
	 * @return array<string, string>
	 */
	public static function languages(): array
	{
		$langs = \Context::loadLangSelected();
		return is_array($langs) ? $langs : [];
	}

	/**
	 * 값이 다국어 코드면 코드 이름만, 아니면 빈 문자열.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function codeOf($value): string
	{
		$value = trim((string)$value);
		if (strpos($value, self::PREFIX) !== 0)
		{
			return '';
		}
		$code = substr($value, strlen(self::PREFIX));
		return preg_match('/^[a-zA-Z0-9_]+$/', $code) ? $code : '';
	}

	/**
	 * 코드 이름을 저장값으로.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function toValue(string $code): string
	{
		$code = self::filterCode($code);
		return $code === '' ? '' : self::PREFIX . $code;
	}

	/**
	 * 코드 이름 정리 (영문·숫자·밑줄만).
	 *
	 * @param string $code
	 * @return string
	 */
	public static function filterCode(string $code): string
	{
		$code = preg_replace('/[^a-zA-Z0-9_]/', '', trim($code));
		return substr((string)$code, 0, 100);
	}

	/**
	 * 폼 값. <필드>_langcode 가 오면 코어 규약값을, 아니면 입력한 글자를 돌려준다.
	 *
	 * @param string $field
	 * @param string $fallback
	 * @return string
	 */
	public static function fromRequest(string $field, string $fallback): string
	{
		$code = self::filterCode((string)\Context::get($field . '_langcode'));
		return $code !== '' ? self::toValue($code) : $fallback;
	}

	/**
	 * 코드 하나의 언어별 값.
	 *
	 * @param string $code
	 * @return array<string, string>
	 */
	public static function values(string $code): array
	{
		$code = self::filterCode($code);
		if ($code === '')
		{
			return [];
		}
		$output = executeQueryArray('module.getLang', (object)['name' => $code]);
		$values = [];
		foreach (($output->toBool() ? ($output->data ?: []) : []) as $row)
		{
			$values[(string)$row->lang_code] = (string)$row->value;
		}
		return $values;
	}

	/**
	 * 현재 언어로 읽은 값. 없으면 다른 언어 값, 그것도 없으면 코드 이름.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function display(string $code): string
	{
		$values = self::values($code);
		$lang = \Context::getLangType();
		if (trim((string)($values[$lang] ?? '')) !== '')
		{
			return $values[$lang];
		}
		foreach ($values as $value)
		{
			if (trim($value) !== '')
			{
				return $value;
			}
		}
		return $code;
	}

	/**
	 * 현재 언어 값으로 바꾼다.
	 *
	 * 템플릿이 escape 로 출력하면 '->' 가 '&gt;' 가 되어 코어의 최종 치환에 걸리지 않으므로
	 * 값을 넘기기 전에 미리 바꾼다.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function text($value): string
	{
		$value = (string)$value;
		return strpos($value, self::PREFIX) === false ? $value : \Context::replaceUserLang($value);
	}

	/**
	 * 행 하나의 칸들을 바꾼다. 원본은 <필드>_raw 에 남긴다.
	 *
	 * @param ?object $row
	 * @param array<int, string> $fields
	 * @return ?object
	 */
	public static function localize(?object $row, array $fields): ?object
	{
		if (!$row)
		{
			return $row;
		}
		foreach ($fields as $field)
		{
			if (isset($row->{$field}) && !isset($row->{$field . '_raw'}))
			{
				$row->{$field . '_raw'} = $row->{$field};
				$row->{$field} = self::text($row->{$field});
			}
		}
		return $row;
	}

	/**
	 * 목록 전체를 바꾼다.
	 *
	 * @param array $rows
	 * @param array<int, string> $fields
	 * @return array
	 */
	public static function localizeAll(array $rows, array $fields): array
	{
		foreach ($rows as $row)
		{
			if (is_object($row))
			{
				self::localize($row, $fields);
			}
		}
		return $rows;
	}

	/**
	 * 날짜를 현재 언어 형식으로. YYYYMMDD 또는 YYYYMMDDHHIISS 를 받는다.
	 *
	 * @param string $ymd
	 * @param bool $with_time
	 * @return string
	 */
	public static function date(string $ymd, bool $with_time = false): string
	{
		$ymd = preg_replace('/[^0-9]/', '', $ymd);
		if (strlen($ymd) < 8)
		{
			return '';
		}
		$format = lang($with_time ? 'lodging.lodging_datetime_fmt' : 'lodging.lodging_date_fmt');
		return zdate($ymd, $format ?: ($with_time ? 'Y.m.d H:i' : 'Y.m.d'));
	}

	/**
	 * 금액을 현재 언어 형식으로.
	 *
	 * @param int|float $amount
	 * @return string
	 */
	public static function money($amount): string
	{
		return sprintf(lang('lodging.lodging_money'), number_format((float)$amount));
	}

	/**
	 * 등록된 코드 목록 (검색어로 좁힘). 코드마다 현재 언어 값을 함께 준다.
	 *
	 * @param string $keyword
	 * @param int $limit
	 * @return array<int, object> [{code, value}]
	 */
	public static function search(string $keyword = '', int $limit = 30): array
	{
		$output = executeQueryArray('module.getLang', new \stdClass);
		$rows = $output->toBool() ? ($output->data ?: []) : [];

		$lang = \Context::getLangType();
		$map = [];
		foreach ($rows as $row)
		{
			$code = (string)$row->name;
			if (!isset($map[$code]))
			{
				$map[$code] = ['code' => $code, 'value' => '', 'fallback' => ''];
			}
			$value = (string)$row->value;
			if ((string)$row->lang_code === $lang)
			{
				$map[$code]['value'] = $value;
			}
			elseif ($map[$code]['fallback'] === '')
			{
				$map[$code]['fallback'] = $value;
			}
		}

		$keyword = trim($keyword);
		$result = [];
		foreach ($map as $row)
		{
			$value = $row['value'] !== '' ? $row['value'] : $row['fallback'];
			if ($keyword !== '' && mb_stripos($row['code'], $keyword) === false && mb_stripos($value, $keyword) === false)
			{
				continue;
			}
			$result[] = (object)['code' => $row['code'], 'value' => $value];
			if (count($result) >= $limit)
			{
				break;
			}
		}
		return $result;
	}

	/**
	 * 코드를 만들거나 고친다. 코어의 lang 테이블에 그대로 쓴다.
	 *
	 * @param string $code 비우면 자동 생성
	 * @param array<string, string> $values 언어 코드 => 값
	 * @return string 저장된 코드 이름 ('' = 실패)
	 */
	public static function save(string $code, array $values): string
	{
		$allowed = self::languages();
		$clean = [];
		foreach ($values as $lang => $value)
		{
			$value = trim((string)$value);
			if (isset($allowed[$lang]) && $value !== '')
			{
				$clean[$lang] = $value;
			}
		}
		if (!count($clean))
		{
			return '';
		}

		$code = self::filterCode($code);
		if ($code === '')
		{
			$code = 'ldg_' . date('YmdHis') . sprintf('%03d', mt_rand(0, 999));
		}

		executeQuery('module.deleteLang', (object)['name' => $code]);
		foreach ($clean as $lang => $value)
		{
			executeQuery('module.insertLang', (object)[
				'name' => $code,
				'lang_code' => $lang,
				'value' => $value,
			]);
		}

		\ModuleAdminController::getInstance()->makeCacheDefinedLangCode(0);

		return $code;
	}
}
