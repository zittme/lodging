<?php

namespace Zittme\Modules\Lodging\Controllers;

/**
 * 숙박 — 호텔·모텔·펜션 예약.
 *
 * 업소(property) → 객실 타입(room_type) → 날짜별 재고·요금 구조다.
 * 개별 호실은 관리하지 않는다. 숙박과 대실은 카운터를 따로 두되(booked_stay / booked_dayuse),
 * 대실 마감이 숙박 입실보다 늦은 객실은 두 카운터의 합으로 재고를 나눠 쓴다.
 *
 * 커머스 없이 동작해야 한다. 쿠폰·후기는 자체 테이블이다.
 * 선결제는 zittme_pay 가 설치된 경우에만 열리고, 현장결제만으로도 운영된다.
 */
class Base extends \ModuleObject
{
	/**
	 * 숙박 유형.
	 */
	public const STAY_TYPE_STAY = 'stay';
	public const STAY_TYPE_DAYUSE = 'dayuse';

	/**
	 * 예약 상태.
	 */
	public const STATUS_HOLD = 'hold';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELED = 'canceled';
	public const STATUS_EXPIRED = 'expired';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_NOSHOW = 'noshow';

	/**
	 * ModuleObject 에는 init() 이 없다. 하위 컨트롤러가 parent::init() 을
	 * 부를 수 있도록 계단을 둔다.
	 */
	public function init()
	{
	}

	/**
	 * 로그인 정보. 비로그인일 때 false 가 오므로 한곳에서 거른다.
	 *
	 * @return ?object
	 */
	public static function getLoggedInfo(): ?object
	{
		$logged_info = \Context::get('logged_info');
		return is_object($logged_info) ? $logged_info : null;
	}

	/**
	 * 짓미페이가 설치되어 있는가. 선결제 노출 여부가 여기 달려 있다.
	 *
	 * @return bool
	 */
	public static function isPayAvailable(): bool
	{
		return class_exists('\\Zittme\\Modules\\Zittme_pay\\PayService')
			&& (!method_exists('\\Zittme\\Modules\\Zittme_pay\\PayService', 'isAvailable') || \Zittme\Modules\Zittme_pay\PayService::isAvailable());
	}

	/**
	 * 지금 시각 (라이믹스 표준 14자리).
	 *
	 * @return string
	 */
	public static function now(): string
	{
		return date('YmdHis');
	}
}
