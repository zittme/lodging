<?php

namespace Zittme\Modules\Lodging\Controllers;

/**
 * 숙박 전용 콘솔 — 코어 관리자에 귀속되지 않는 별도 풀스크린 운영 패널.
 *
 * standalone act + layout 'none' 으로 띄운다. 화면 데이터 로직은 Admin 을 그대로
 * 상속해 재사용하고, 콘솔 셸(사이드바)은 views/admin/_tabs 가 담당한다.
 * (학원·커머스 콘솔과 같은 규약)
 */
class Console extends Admin
{
	/**
	 * 콘솔 페이지 → Admin disp 메서드 매핑.
	 */
	public const PAGES = [
		'dashboard' => 'dispLodgingAdminDashboard',
		'properties' => 'dispLodgingAdminPropertyList',
		'property_edit' => 'dispLodgingAdminPropertyInfo',
		'room_types' => 'dispLodgingAdminRoomTypes',
		'calendar' => 'dispLodgingAdminCalendar',
		'bookings' => 'dispLodgingAdminBookings',
		'rooms' => 'dispLodgingAdminRooms',
		'reviews' => 'dispLodgingAdminReviews',
		'coupons' => 'dispLodgingAdminCoupons',
		'map' => 'dispLodgingAdminMap',
	];

	/**
	 * 업소가 정해져 있어야 열리는 페이지. property_srl 없이 들어오면 숙소 목록으로 보낸다.
	 */
	protected const NEED_PROPERTY = ['room_types', 'calendar', 'bookings', 'rooms', 'reviews'];

	/**
	 * 콘솔 진입점. ?act=dispLodgingConsole&p=<page>
	 *
	 * 예약자 신상과 결제 정보를 다루므로 최고관리자만 들여보낸다.
	 */
	public function dispLodgingConsole()
	{
		$logged_info = self::getLoggedInfo();
		if (!$logged_info || $logged_info->is_admin !== 'Y')
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		// act 이름에 Admin 이 없어 코어가 proc() 에서 모듈 스킨 경로를 덮어쓴다. 콘솔 화면은 views/admin 에 있으므로 되돌린다
		$this->setTemplatePath($this->module_path . 'views/admin/');

		$p = (string)\Context::get('p');
		if (!isset(self::PAGES[$p]))
		{
			$p = 'dashboard';
		}
		if (in_array($p, self::NEED_PROPERTY, true) && (int)\Context::get('property_srl') <= 0)
		{
			$p = 'properties';
		}

		\Context::set('zmc_console', true);
		\Context::set('zmc_current', $p);
		\Context::set('zmc_property_srl', (int)\Context::get('property_srl'));
		\Context::setBrowserTitle(lang('lodging.lodging') . ' ' . lang('lodging.lodging_console'));
		\Context::set('layout', 'none');

		return $this->{self::PAGES[$p]}();
	}
}
