<?php

namespace Zittme\Modules\Lodging\Controllers;

use Zittme\Modules\Lodging\Models\Booking as BookingModel;
use Zittme\Modules\Lodging\Models\CancelRule;
use Zittme\Modules\Lodging\Models\Coupon as CouponModel;
use Zittme\Modules\Lodging\Models\Inventory;
use Zittme\Modules\Lodging\Models\Property as PropertyModel;
use Zittme\Modules\Lodging\Models\Rate;
use Zittme\Modules\Lodging\Models\Review as ReviewModel;
use Zittme\Modules\Lodging\Models\RoomType as RoomTypeModel;

/**
 * 방문자가 보는 화면 — 숙소 목록·상세·예약·본인 조회.
 */
class Front extends Base
{
	/**
	 * 비회원 본인 확인을 담아 두는 세션 키.
	 */
	public const LOOKUP_SESSION = 'lodging_lookup';

	/**
	 * 체크인일은 오늘부터 이 일수 안에서만 받는다.
	 */
	public const MAX_ADVANCE_DAYS = 365;

	public function init()
	{
		parent::init();

		$skin = $this->module_info->skin ?? 'default';
		if (!$skin || $skin === '/USE_DEFAULT/')
		{
			$skin = 'default';
		}
		$path = \Zittme\Framework\Theme::resolveSkinPath($this->module_path, $skin, 'skins');
		$this->setTemplatePath(rtrim($path ?: $this->module_path . 'skins/default', '/') . '/');

		\Context::set('module_info', $this->module_info);
		\Context::set('is_logged', self::getLoggedInfo() !== null);
	}

	/**
	 * 숙소 목록 겸 검색.
	 *
	 * 검색 조건(날짜·인원·유형·지역·검색어·정렬)이 하나라도 오면 그 조건으로 거른다.
	 * 날짜가 오면 숙소마다 그 날짜의 최저가와 잔여 객실을 붙인다.
	 * 조건 없이 왔는데 업소가 하나뿐이면 바로 상세로 보낸다.
	 */
	public function dispLodgingIndex()
	{
		$list = PropertyModel::getList((int)$this->module_srl, 'open');

		$stay_type = \Context::get('stay_type') === 'dayuse' ? 'dayuse' : 'stay';
		$checkin = self::cleanYmd((string)\Context::get('checkin'));
		$checkout = self::cleanYmd((string)\Context::get('checkout'));
		$person = max(0, (int)\Context::get('person'));
		$type = trim((string)\Context::get('type'));
		$region = trim((string)\Context::get('region'));
		$keyword = trim((string)\Context::get('q'));
		$sort = trim((string)\Context::get('sort'));
		$has_dates = $checkin !== '';

		$searching = $has_dates || $person > 0 || $type !== '' || $region !== '' || $keyword !== '' || $sort !== '';

		if (!$searching && count($list) === 1)
		{
			$only = reset($list);
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispLodgingView', 'property_srl', $only->property_srl));
			return;
		}

		// 날짜 구간. 대실은 하루, 숙박은 체크아웃이 체크인보다 뒤여야 한다.
		$ymds = [];
		if ($has_dates)
		{
			if ($stay_type === 'dayuse')
			{
				$checkout = $checkin;
				$ymds = [$checkin];
			}
			else
			{
				if ($checkout === '' || $checkout <= $checkin)
				{
					$checkout = date('Ymd', strtotime($checkin) + 86400);
				}
				$ymds = Inventory::nights($checkin, $checkout);
			}
		}

		$regions = [];
		$types = [];
		$result = [];
		foreach ($list as $property)
		{
			$property->region = self::regionOf((string)$property->address);
			$property->amenity_list = \Zittme\Modules\Lodging\Models\Amenity::labels($property->amenities ?? '');
			$property->review = ReviewModel::summary((int)$property->property_srl);
			$property->thumbnail_url = \Zittme\Modules\Lodging\Models\Image::firstUrl((int)$property->property_srl);

			// 거르기 전에 전체 지역·유형을 모아 둔다. 칩 목록은 검색 결과가 아니라 전체 기준이다.
			if ($property->region !== '' && !in_array($property->region, $regions, true)) { $regions[] = $property->region; }
			if (!in_array($property->property_type, $types, true)) { $types[] = $property->property_type; }

			if ($type !== '' && $property->property_type !== $type) { continue; }
			if ($region !== '' && $property->region !== $region) { continue; }
			if ($keyword !== '' && stripos($property->title . ' ' . $property->address . ' ' . $property->summary, $keyword) === false) { continue; }

			// 날짜·인원 기준 최저가와 잔여. 날짜가 없으면 요금표 최저가만 붙인다.
			$stat = self::propertyStat($property, $stay_type, $ymds, $person);
			if ($has_dates && !$stat['available']) { $property->sold_out = true; }
			if ($person > 0 && !$stat['fits']) { continue; }

			$property->min_price = $stat['min_price'];
			$property->available_rooms = $stat['available_rooms'];
			$property->available = $stat['available'];
			$property->has_dayuse = $stat['has_dayuse'];
			$result[] = $property;
		}

		// 정렬. 기본은 등록 순서, 마감된 숙소는 항상 뒤로 보낸다.
		usort($result, function ($a, $b) use ($sort) {
			$a_out = !empty($a->sold_out) ? 1 : 0;
			$b_out = !empty($b->sold_out) ? 1 : 0;
			if ($a_out !== $b_out) { return $a_out <=> $b_out; }
			if ($sort === 'price_asc') { return ($a->min_price ?: PHP_INT_MAX) <=> ($b->min_price ?: PHP_INT_MAX); }
			if ($sort === 'price_desc') { return $b->min_price <=> $a->min_price; }
			if ($sort === 'rating') { return [$b->review->average, $b->review->count] <=> [$a->review->average, $a->review->count]; }
			if ($sort === 'reviews') { return $b->review->count <=> $a->review->count; }
			return $a->list_order <=> $b->list_order;
		});

		\Context::set('property_list', $result);
		\Context::set('property_total', count($list));
		\Context::set('region_list', $regions);
		\Context::set('type_list', $types);
		\Context::set('searching', $searching);
		\Context::set('stay_type', $stay_type);
		\Context::set('checkin', $checkin);
		\Context::set('checkout', $checkout);
		\Context::set('nights', count($ymds));
		\Context::set('person', $person);
		\Context::set('type', $type);
		\Context::set('region', $region);
		\Context::set('q', $keyword);
		\Context::set('sort', $sort);
		$this->setTemplateFile('index');
	}

	/**
	 * 쿠폰·혜택. 모든 숙소에 쓰는 공개 쿠폰과, 지금 가장 싼 숙소·대실 되는 숙소를 모아 보여 준다.
	 */
	public function dispLodgingCoupons()
	{
		$coupons = CouponModel::publicList((int)$this->module_srl, 0);
		foreach ($coupons as $coupon)
		{
			$coupon->property_title = '';
			if ((int)$coupon->property_srl > 0)
			{
				$target = PropertyModel::get((int)$coupon->property_srl);
				$coupon->property_title = $target ? (string)$target->title : '';
			}
		}

		$list = [];
		foreach (PropertyModel::getList((int)$this->module_srl, 'open') as $property)
		{
			$property->region = self::regionOf((string)$property->address);
			$property->review = ReviewModel::summary((int)$property->property_srl);
			$property->thumbnail_url = \Zittme\Modules\Lodging\Models\Image::firstUrl((int)$property->property_srl);
			$stat = self::propertyStat($property, 'stay', [], 0);
			$property->min_price = $stat['min_price'];
			$property->has_dayuse = $stat['has_dayuse'];
			$property->dayuse_price = 0;
			if ($stat['has_dayuse'])
			{
				$dayuse = self::propertyStat($property, 'dayuse', [], 0);
				$property->dayuse_price = $dayuse['min_price'];
			}
			$list[] = $property;
		}

		$cheapest = array_filter($list, function ($p) { return (int)$p->min_price > 0; });
		usort($cheapest, function ($a, $b) { return $a->min_price <=> $b->min_price; });
		$dayuse_list = array_filter($list, function ($p) { return !empty($p->has_dayuse); });
		usort($dayuse_list, function ($a, $b) { return ($a->dayuse_price ?: PHP_INT_MAX) <=> ($b->dayuse_price ?: PHP_INT_MAX); });

		// 로그인 회원이면 이미 담은 쿠폰을 표시한다
		$logged_info = self::getLoggedInfo();
		$claimed = [];
		if ($logged_info)
		{
			foreach (CouponModel::walletList((int)$logged_info->member_srl) as $w) { $claimed[(int)$w->coupon_srl] = (string)$w->state; }
		}

		\Context::set('coupon_list', $coupons);
		\Context::set('claimed_map', $claimed);
		\Context::set('cheapest_list', array_slice(array_values($cheapest), 0, 6));
		\Context::set('dayuse_list', array_slice(array_values($dayuse_list), 0, 6));
		$this->setTemplateFile('coupons');
	}

	/**
	 * 내 쿠폰함. 회원 전용.
	 */
	public function dispLodgingMyCoupons()
	{
		$logged_info = self::getLoggedInfo();
		if (!$logged_info)
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispMemberLoginForm'));
			return;
		}

		$list = CouponModel::walletList((int)$logged_info->member_srl);
		foreach ($list as $row)
		{
			$row->property_title = '';
			if ((int)$row->coupon->property_srl > 0)
			{
				$target = PropertyModel::get((int)$row->coupon->property_srl);
				$row->property_title = $target ? (string)$target->title : '';
			}
		}

		\Context::set('wallet_list', $list);
		$this->setTemplateFile('mycoupons');
	}

	/**
	 * 내 포인트. 잔액과 이력. 회원 전용.
	 */
	public function dispLodgingMyPoints()
	{
		$logged_info = self::getLoggedInfo();
		if (!$logged_info)
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispMemberLoginForm'));
			return;
		}
		$member_srl = (int)$logged_info->member_srl;
		$config = \Zittme\Modules\Lodging\Models\Point::config();
		\Context::set('point_balance', \Zittme\Modules\Lodging\Models\Point::balance($member_srl));
		\Context::set('point_history', \Zittme\Modules\Lodging\Models\Point::history($member_srl, 100));
		\Context::set('point_rate', (float)$config->point_rate);
		\Context::set('point_min_use', (int)$config->point_min_use);
		$this->setTemplateFile('mypoints');
	}

	/**
	 * 쿠폰 받기. 공개 쿠폰을 내 쿠폰함에 담는다.
	 */
	public function procLodgingClaimCoupon()
	{
		$logged_info = self::getLoggedInfo();
		if (!$logged_info)
		{
			throw new \Zittme\Framework\Exceptions\MustLogin;
		}

		$coupon = CouponModel::get((int)\Context::get('coupon_srl'));
		if (!$coupon || (int)$coupon->module_srl !== (int)$this->module_srl)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_coupon_not_found');
		}
		$public = false;
		foreach (CouponModel::publicList((int)$this->module_srl, (int)$coupon->property_srl) as $open)
		{
			if ((int)$open->coupon_srl === (int)$coupon->coupon_srl) { $public = true; break; }
		}
		if (!$public)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_coupon_closed');
		}

		$output = CouponModel::claim($coupon, (int)$logged_info->member_srl);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('lodging.msg_coupon_claimed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispLodgingMyCoupons'));
	}

	/**
	 * 요금·재고 달력 (JSON).
	 *
	 * 객실 타입 하나의 한 달치를 돌려준다. 날짜마다 요금·잔여·판매중지 여부가 담긴다.
	 * 숙소 상세의 달력과 예약 상자가 쓴다.
	 */
	public function getLodgingCalendar()
	{
		BookingModel::expireStaleHolds();

		$room_type = RoomTypeModel::get((int)\Context::get('room_type_srl'));
		if (!$room_type || $room_type->status !== 'open')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$property = PropertyModel::get((int)$room_type->property_srl);
		if (!$property || (int)$property->module_srl !== (int)$this->module_srl || $property->status !== 'open')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$stay_type = \Context::get('stay_type') === 'dayuse' ? 'dayuse' : 'stay';
		$ym = preg_replace('/[^0-9]/', '', (string)\Context::get('ym'));
		if (strlen($ym) !== 6 || !checkdate((int)substr($ym, 4, 2), 1, (int)substr($ym, 0, 4)))
		{
			$ym = date('Ym');
		}

		$first = $ym . '01';
		$last = date('Ymt', strtotime($first));
		$today = date('Ymd');
		$rows = [];
		foreach (Inventory::getRange((int)$room_type->room_type_srl, $first, $last) as $row)
		{
			$rows[(string)$row->ymd] = $row;
		}

		$days = [];
		for ($ts = strtotime($first); $ts <= strtotime($last); $ts += 86400)
		{
			$ymd = date('Ymd', $ts);
			$remaining = $ymd < $today ? 0 : Inventory::remaining($room_type, $stay_type, $ymd);
			$days[] = [
				'ymd' => $ymd,
				'price' => ($stay_type === 'dayuse' && $room_type->use_dayuse !== 'Y') ? 0 : Rate::priceOf($room_type, $stay_type, $ymd),
				'remaining' => $remaining,
				'closed' => isset($rows[$ymd]) && $rows[$ymd]->closed === 'Y',
				'past' => $ymd < $today,
			];
		}

		$this->add('room_type_srl', (int)$room_type->room_type_srl);
		$this->add('stay_type', $stay_type);
		$this->add('ym', $ym);
		$this->add('days', $days);
	}

	/**
	 * 숙소 상세 + 날짜 검색 + 객실 목록.
	 */
	public function dispLodgingView()
	{
		BookingModel::expireStaleHolds();

		$property = $this->getOpenProperty();

		// 검색 조건. 기본값은 오늘 1박이다.
		$stay_type = \Context::get('stay_type') === 'dayuse' ? 'dayuse' : 'stay';
		$checkin = self::cleanYmd((string)\Context::get('checkin')) ?: date('Ymd');
		$checkout = self::cleanYmd((string)\Context::get('checkout')) ?: date('Ymd', strtotime('+1 day'));

		if ($stay_type === 'dayuse')
		{
			$checkout = $checkin;
			$ymds = [$checkin];
		}
		else
		{
			if ($checkout <= $checkin)
			{
				$checkout = date('Ymd', strtotime($checkin) + 86400);
			}
			$ymds = Inventory::nights($checkin, $checkout);
		}

		$room_types = RoomTypeModel::getList((int)$property->property_srl, 'open');
		foreach ($room_types as $room_type)
		{
			if ($stay_type === 'dayuse' && $room_type->use_dayuse !== 'Y')
			{
				$room_type->available = false;
				$room_type->unavailable_reason = 'no_dayuse';
				$room_type->price = 0;
				continue;
			}
			$room_type->available = Inventory::isAvailable($room_type, $stay_type, $ymds);
			$room_type->price = (int)Rate::quote($room_type, $stay_type, $ymds, (int)$room_type->std_person)['total'];
			$room_type->unavailable_reason = $room_type->available ? '' : 'sold_out';
		}
		$person = max(0, (int)\Context::get('person'));
		foreach ($room_types as $room_type)
		{
			$room_type->thumbnail_url = \Zittme\Modules\Lodging\Models\Image::firstUrl((int)$room_type->room_type_srl);
			$room_type->images = \Zittme\Modules\Lodging\Models\Image::getList((int)$room_type->room_type_srl);
			$room_type->amenity_list = \Zittme\Modules\Lodging\Models\Amenity::labels($room_type->amenities ?? '');
			// 인원을 골라 왔으면 못 받는 객실은 표시만 하고 예약을 막는다
			$room_type->fits_person = $person === 0 || (int)$room_type->max_person >= $person;
			$room_type->remaining = $room_type->available ? Inventory::remaining($room_type, $stay_type, $ymds[0]) : 0;
		}

		$property->region = self::regionOf((string)$property->address);
		$property->amenity_list = \Zittme\Modules\Lodging\Models\Amenity::labels($property->amenities ?? '');

		\Context::set('property', $property);
		\Context::set('map_links', \Zittme\Modules\Lodging\Models\Map::links($property));
		\Context::set('room_types', $room_types);
		\Context::set('stay_type', $stay_type);
		\Context::set('checkin', $checkin);
		\Context::set('checkout', $checkout);
		\Context::set('nights', count($ymds));
		\Context::set('person', $person);
		\Context::set('coupon_list', CouponModel::publicList((int)$this->module_srl, (int)$property->property_srl));
		\Context::set('can_prepay', $property->allow_prepay === 'Y' && self::isPayAvailable());
		\Context::set('can_onsite', $property->allow_onsite_pay === 'Y');
		\Context::set('property_images', \Zittme\Modules\Lodging\Models\Image::getList((int)$property->property_srl));
		\Context::set('cancel_rules', CancelRule::getList((int)$property->property_srl));
		\Context::set('review_summary', ReviewModel::summary((int)$property->property_srl));
		\Context::set('review_list', ReviewModel::getList((int)$property->property_srl, 5));
		\Context::setBrowserTitle($property->title);

		$this->setTemplateFile('view');
	}

	/**
	 * 예약 폼.
	 */
	public function dispLodgingBook()
	{
		if (empty($this->grant->book))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$property = $this->getOpenProperty();
		$room_type = $this->getOpenRoomType($property);

		$stay_type = \Context::get('stay_type') === 'dayuse' ? 'dayuse' : 'stay';
		$checkin = self::cleanYmd((string)\Context::get('checkin'));
		$checkout = $stay_type === 'dayuse' ? $checkin : self::cleanYmd((string)\Context::get('checkout'));

		BookingModel::expireStaleHolds();
		self::checkStayRange($room_type, $stay_type, $checkin, $checkout);
		$ymds = $stay_type === 'dayuse' ? array_filter([$checkin]) : Inventory::nights($checkin, $checkout);
		if (!Inventory::isAvailable($room_type, $stay_type, $ymds))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_sold_out');
		}

		$logged_info = self::getLoggedInfo();

		\Context::set('property', $property);
		\Context::set('room_type', $room_type);
		\Context::set('stay_type', $stay_type);
		\Context::set('checkin', $checkin);
		\Context::set('checkout', $checkout);
		\Context::set('nights', count($ymds));
		$quote = Rate::quote($room_type, $stay_type, $ymds, (int)$room_type->std_person);
		\Context::set('room_amount', (int)$quote['total']);
		\Context::set('room_quote', $quote);
		\Context::set('extra_person_total', max(0, (int)($room_type->extra_person_price ?? 0)) * count($ymds));
		\Context::set('cancel_rules', CancelRule::getList((int)$property->property_srl));
		\Context::set('logged_name', (string)($logged_info->nick_name ?? ''));
		\Context::set('logged_email', (string)($logged_info->email_address ?? ''));
		// 쿠폰함에서 쓸 수 있는 장만 고르게 한다 (기간·대상 검증은 예약 시 다시 한다)
		$wallet_list = [];
		foreach ($logged_info ? CouponModel::walletList((int)$logged_info->member_srl) : [] as $w)
		{
			if ($w->state !== 'available') { continue; }
			if ((int)$w->coupon->property_srl > 0 && (int)$w->coupon->property_srl !== (int)$property->property_srl) { continue; }
			if (($w->coupon->apply_to ?? 'all') !== 'all' && $w->coupon->apply_to !== $stay_type) { continue; }
			$wallet_list[] = $w;
		}
		\Context::set('wallet_list', $wallet_list);
		$point_config = \Zittme\Modules\Lodging\Models\Point::config();
		\Context::set('point_balance', $logged_info ? \Zittme\Modules\Lodging\Models\Point::balance((int)$logged_info->member_srl) : 0);
		\Context::set('point_min_use', (int)$point_config->point_min_use);
		\Context::set('point_rate', (float)$point_config->point_rate);
		\Context::set('logged_phone', (string)($logged_info->phone_number ?? ''));
		// 선결제는 업소 설정과 짓미페이 설치가 모두 맞아야 열린다.
		\Context::set('can_prepay', $property->allow_prepay === 'Y' && self::isPayAvailable());
		\Context::set('can_onsite', $property->allow_onsite_pay === 'Y');
		\Context::setBrowserTitle($property->title);

		$this->setTemplateFile('book');
	}

	/**
	 * 예약 접수.
	 */
	public function procLodgingBook()
	{
		if (empty($this->grant->book))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$property = $this->getOpenProperty();
		$room_type = $this->getOpenRoomType($property);

		$stay_type = \Context::get('stay_type') === 'dayuse' ? 'dayuse' : 'stay';
		$checkin = self::cleanYmd((string)\Context::get('checkin'));
		$checkout = $stay_type === 'dayuse' ? $checkin : self::cleanYmd((string)\Context::get('checkout'));

		BookingModel::expireStaleHolds();
		self::checkStayRange($room_type, $stay_type, $checkin, $checkout);
		if ($stay_type === 'dayuse' && $room_type->use_dayuse !== 'Y')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_no_dayuse');
		}
		$person_count = (int)\Context::get('person_count');
		if ($person_count > max(1, (int)$room_type->max_person))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_too_many_person');
		}

		$guest_name = trim((string)\Context::get('guest_name'));
		$guest_phone = trim((string)\Context::get('guest_phone'));
		if ($guest_name === '' || $guest_phone === '')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_guest_required');
		}

		$logged_info = self::getLoggedInfo();
		$member_srl = (int)($logged_info->member_srl ?? 0);

		$guest_password = trim((string)\Context::get('guest_password'));
		if ($member_srl === 0 && $guest_password === '')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_password_required');
		}

		// 결제 수단. 허용되지 않은 값이 오면 열려 있는 쪽으로 되돌린다.
		$pay_method = \Context::get('pay_method') === 'prepay' ? 'prepay' : 'onsite';
		if ($pay_method === 'prepay' && ($property->allow_prepay !== 'Y' || !self::isPayAvailable()))
		{
			$pay_method = 'onsite';
		}
		if ($pay_method === 'onsite' && $property->allow_onsite_pay !== 'Y')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_pay_method_closed');
		}

		// 쿠폰. 코드가 틀리면 예약을 막지 않고 무시하는 게 아니라 오류로 알린다.
		$ymds = $stay_type === 'dayuse' ? [$checkin] : Inventory::nights($checkin, $checkout);
		$room_amount = (int)Rate::quote($room_type, $stay_type, $ymds, $person_count ?: (int)$room_type->std_person)['total'];
		$coupon = null;
		$discount = 0;
		$coupon_code = trim((string)\Context::get('coupon_code'));
		$wallet_srl = (int)\Context::get('wallet_srl');
		// 쿠폰함에서 고른 장이 있으면 코드 입력보다 우선한다
		if ($wallet_srl > 0 && $member_srl > 0)
		{
			$wallet = CouponModel::getWallet($wallet_srl, $member_srl);
			if (!$wallet || $wallet->status !== 'available')
			{
				throw new \Zittme\Framework\Exception('lodging.msg_coupon_not_found');
			}
			$coupon_code = '';
			$coupon = CouponModel::get((int)$wallet->coupon_srl);
			if (!$coupon)
			{
				throw new \Zittme\Framework\Exception('lodging.msg_coupon_not_found');
			}
			[$discount, $error] = CouponModel::evaluate($coupon, (int)$property->property_srl, $stay_type, $room_amount, $member_srl);
			if ($error !== '')
			{
				throw new \Zittme\Framework\Exception($error);
			}
		}
		if ($coupon_code !== '')
		{
			$coupon = CouponModel::getByCode((int)$this->module_srl, $coupon_code);
			if (!$coupon)
			{
				throw new \Zittme\Framework\Exception('lodging.msg_coupon_not_found');
			}
			[$discount, $error] = CouponModel::evaluate($coupon, (int)$property->property_srl, $stay_type, $room_amount, $member_srl);
			if ($error !== '')
			{
				throw new \Zittme\Framework\Exception($error);
			}
		}

		$args = new \stdClass;
		$args->module_srl = (int)$this->module_srl;
		$args->property = $property;
		$args->room_type = $room_type;
		$args->stay_type = $stay_type;
		$args->checkin_ymd = $checkin;
		$args->checkout_ymd = $checkout;
		$args->dayuse_start = preg_replace('/[^0-9]/', '', (string)\Context::get('dayuse_start'));
		$args->person_count = $person_count ?: (int)$room_type->std_person;
		$args->hold_minutes = (int)($this->module_info->hold_minutes ?? 0) ?: BookingModel::HOLD_MINUTES;
		$args->member_srl = $member_srl;
		$args->guest_name = $guest_name;
		$args->guest_phone = $guest_phone;
		$args->guest_email = trim((string)\Context::get('guest_email'));
		$args->guest_password = $guest_password;
		$args->pay_method = $pay_method;
		$args->coupon_srl = $coupon ? (int)$coupon->coupon_srl : 0;
		$args->discount_amount = $discount;
		// 포인트는 회원만, 잔액과 결제 대상 금액 안에서
		$args->point_used = \Zittme\Modules\Lodging\Models\Point::clampUse($member_srl, $room_amount - $discount, (int)\Context::get('point_used'));
		$args->request_memo = trim((string)\Context::get('request_memo'));

		$output = BookingModel::create($args);
		if (!$output->toBool())
		{
			return $output;
		}

		$booking_srl = (int)$output->get('booking_srl');
		$booking_code = (string)$output->get('booking_code');
		$total = (int)$output->get('total_amount');

		if ($coupon && $discount > 0)
		{
			CouponModel::record((int)$coupon->coupon_srl, $booking_srl, $member_srl, $discount);
		}

		// 결제 대기 건은 결제가 끝나 확정될 때 안내한다 (결제 승인 트리거)
		$mail_booking = BookingModel::get($booking_srl);
		if ($mail_booking && $mail_booking->status === BookingModel::STATUS_CONFIRMED)
		{
			\Zittme\Modules\Lodging\Models\Mailer::onBooked($mail_booking, $property, $room_type);
		}

		// 방금 예약한 건은 비회원도 바로 볼 수 있게 세션에 남긴다.
		$known = $_SESSION[self::LOOKUP_SESSION] ?? [];
		$known = is_array($known) ? $known : [];
		$known[] = $booking_srl;
		$_SESSION[self::LOOKUP_SESSION] = array_values(array_unique(array_map('intval', $known)));

		$result_url = getNotEncodedFullUrl('', 'mid', $this->mid, 'act', 'dispLodgingResult', 'code', $booking_code);

		// 선결제: 결제 주문을 만들어 결제 페이지로 보낸다. 실패하면 예약을 되돌린다.
		if ($pay_method === 'prepay' && $total > 0)
		{
			$pay = \Zittme\Modules\Zittme_pay\PayService::createOrder([
				'source_module' => 'lodging',
				'source_srl' => $booking_srl,
				'source_code' => $booking_code,
				'member_srl' => $member_srl,
				'amount' => $total,
				'title' => sprintf('%s %s %s', $property->title, $room_type->title, \Zittme\Modules\Lodging\Models\Lang::date($checkin)),
				'payer' => ['name' => $guest_name, 'phone' => $guest_phone, 'email' => $args->guest_email],
				'return_url' => $result_url,
			]);
			if (empty($pay->success))
			{
				$booking = BookingModel::get($booking_srl);
				if ($booking)
				{
					BookingModel::cancel($booking, 'pay order failed', true);
				}
				return new \BaseObject(-1, $pay->message ?: 'lodging.msg_pay_failed');
			}

			$this->add('booking_code', $booking_code);
			$this->setRedirectUrl((string)$pay->pay_url ?: $result_url);
			return;
		}

		$this->add('booking_code', $booking_code);
		$this->setRedirectUrl($result_url);
	}

	/**
	 * 예약 결과·상세.
	 */
	public function dispLodgingResult()
	{
		$booking = BookingModel::getByCode(trim((string)\Context::get('code')));
		if (!$booking || (int)$booking->module_srl !== (int)$this->module_srl)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		if (!$this->canReadBooking($booking))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		\Context::set('booking', $booking);
		\Context::set('property', PropertyModel::get((int)$booking->property_srl));
		\Context::set('room_type', RoomTypeModel::get((int)$booking->room_type_srl));
		\Context::set('refund_preview', $booking->status === BookingModel::STATUS_CONFIRMED ? BookingModel::refundAmountOf($booking) : 0);

		// 결제 창을 닫고 돌아온 손님이 제한 시간 안에 다시 결제할 수 있게 한다
		$pay_url = '';
		if ($booking->status === BookingModel::STATUS_HOLD && self::isPayAvailable())
		{
			$order = \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('lodging', (int)$booking->booking_srl);
			if ($order && in_array((string)$order->status, ['ready', 'pending'], true))
			{
				$pay_url = \Zittme\Modules\Zittme_pay\PayService::getPayUrl((string)$order->order_code);
			}
		}
		\Context::set('pay_url', $pay_url);

		$this->setTemplateFile('result');
	}

	/**
	 * 내 예약 / 비회원 조회.
	 */
	public function dispLodgingMy()
	{
		$logged_info = self::getLoggedInfo();
		$member_srl = (int)($logged_info->member_srl ?? 0);
		\Context::set('point_balance', \Zittme\Modules\Lodging\Models\Point::balance($member_srl));

		if ($member_srl > 0)
		{
			$args = new \stdClass;
			$args->module_srl = (int)$this->module_srl;
			$args->member_srl = $member_srl;
			$output = BookingModel::getList($args);
			\Context::set('booking_list', $output->toBool() && is_array($output->data) ? $output->data : []);
			\Context::set('page_navigation', $output->page_navigation ?? null);
			\Context::set('need_auth', false);
		}
		else
		{
			\Context::set('booking_list', []);
			\Context::set('need_auth', true);
		}

		$this->setTemplateFile('my');
	}

	/**
	 * 비회원 예약 조회 (예약번호 + 비밀번호).
	 */
	public function procLodgingGuestLookup()
	{
		$code = trim((string)\Context::get('booking_code'));
		$password = trim((string)\Context::get('guest_password'));

		$booking = $code !== '' ? BookingModel::getByCode($code) : null;
		if (!$booking || $password === '' || $booking->guest_password === ''
			|| !\Zittme\Framework\Password::checkPassword($password, $booking->guest_password))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_lookup_failed');
		}

		$known = $_SESSION[self::LOOKUP_SESSION] ?? [];
		$known = is_array($known) ? $known : [];
		$known[] = (int)$booking->booking_srl;
		$_SESSION[self::LOOKUP_SESSION] = array_values(array_unique(array_map('intval', $known)));

		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispLodgingResult', 'code', $booking->booking_code));
	}

	/**
	 * 예약 취소 (예약자 본인).
	 */
	public function procLodgingCancel()
	{
		$booking = BookingModel::getByCode(trim((string)\Context::get('code')));
		if (!$booking || (int)$booking->module_srl !== (int)$this->module_srl)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		if (!$this->canReadBooking($booking))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$output = BookingModel::cancel($booking, trim((string)\Context::get('reason')));
		if (!$output->toBool())
		{
			return $output;
		}

		// 선결제 건은 환불액만큼 결제도 취소한다.
		// 금액 0 으로 부르면 결제 쪽은 남은 전액을 취소하므로, 돌려줄 돈이 있을 때만 부른다
		$refund = (int)$output->get('refund_amount');
		if ($refund > 0 && $booking->pay_method === 'prepay' && $booking->pay_status === 'paid' && self::isPayAvailable())
		{
			$order = \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('lodging', (int)$booking->booking_srl);
			if ($order)
			{
				\Zittme\Modules\Zittme_pay\PayService::cancel((int)$order->order_srl, lang('lodging.msg_canceled_by_guest'), $refund);
			}
		}

		$canceled = BookingModel::get((int)$booking->booking_srl);
		$cancel_property = PropertyModel::get((int)$booking->property_srl);
		$cancel_room_type = RoomTypeModel::get((int)$booking->room_type_srl);
		if ($canceled && $cancel_property && $cancel_room_type)
		{
			\Zittme\Modules\Lodging\Models\Mailer::onCanceled($canceled, $cancel_property, $cancel_room_type, $refund);
		}

		$this->setMessage('lodging.msg_booking_canceled');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispLodgingResult', 'code', $booking->booking_code));
	}

	/**
	 * 후기 작성. 이용완료 예약자만.
	 */
	public function procLodgingReview()
	{
		$booking = BookingModel::getByCode(trim((string)\Context::get('code')));
		if (!$booking || !$this->canReadBooking($booking))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$output = ReviewModel::write($booking, (int)\Context::get('score'), trim((string)\Context::get('content')));
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispLodgingResult', 'code', $booking->booking_code));
	}

	/**
	 * 주소에서 지역 이름을 뽑는다. "서울특별시 강남구 ..." 이면 "서울", "강원 강릉시" 이면 "강릉".
	 *
	 * 광역 단위는 앞 두 글자로 줄이고, 도 단위는 시·군 이름을 쓴다.
	 * 검색 칩과 카드의 지역 표기가 같은 규칙을 써야 하므로 여기 한 곳에서만 정한다.
	 *
	 * @param string $address
	 * @return string
	 */
	public static function regionOf(string $address): string
	{
		$address = trim($address);
		if ($address === '')
		{
			return '';
		}
		// 다국어로 번역된 주소는 한글 규칙이 맞지 않는다. 시·군 이름만 골라낸다
		if (!preg_match('/[\x{AC00}-\x{D7A3}]/u', $address))
		{
			if (preg_match('/\b(Seoul|Busan|Daegu|Incheon|Gwangju|Daejeon|Ulsan|Sejong|Jeju)\b/i', $address, $m))
			{
				return ucfirst(strtolower($m[1]));
			}
			if (preg_match('/([A-Za-z]+)-(?:si|gun)\b/', $address, $m))
			{
				return $m[1];
			}
			if (preg_match('/^\s*([^\s,]+?)(?:特別市|特别市|広域市|廣域市|广域市|特別自治市|特别自治市)/u', $address, $m))
			{
				return $m[1];
			}
			if (preg_match('/^\s*(済州|濟州|济州)/u', $address, $m))
			{
				return $m[1];
			}
			if (preg_match('/道\s*([^\s,道]{1,6}?)[市郡]/u', $address, $m))
			{
				return $m[1];
			}
			return trim((string)explode(',', $address)[0]);
		}
		$parts = preg_split('/\s+/', $address);
		$first = (string)($parts[0] ?? '');
		$second = (string)($parts[1] ?? '');

		// 특별시·광역시·특별자치시는 앞 두 글자가 곧 지역 이름이다
		if (preg_match('/^(서울|부산|대구|인천|광주|대전|울산|세종)/u', $first, $m))
		{
			return $m[1];
		}
		// 제주는 도 이름 자체가 지역이다
		if (strpos($first, '제주') === 0)
		{
			return '제주';
		}
		// 도 단위는 시·군까지 내려간다: "강원 강릉시" -> "강릉"
		if ($second !== '' && preg_match('/^(.+?)(시|군)$/u', $second, $m))
		{
			return $m[1];
		}
		return preg_replace('/(특별시|광역시|특별자치시|특별자치도|도)$/u', '', $first) ?: $first;
	}

	/**
	 * 숙소 한 곳의 검색용 요약: 최저가, 잔여 객실, 인원 수용 여부, 대실 운영 여부.
	 *
	 * 날짜가 있으면 그 구간의 요금과 재고를, 없으면 요금표 최저가만 본다.
	 *
	 * @param object $property
	 * @param string $stay_type
	 * @param array $ymds 날짜 구간. 비어 있으면 날짜 조건 없음
	 * @param int $person 0 이면 인원 조건 없음
	 * @return array {min_price, available_rooms, available, fits, has_dayuse}
	 */
	protected static function propertyStat(object $property, string $stay_type, array $ymds, int $person): array
	{
		$min_price = 0;
		$available_rooms = 0;
		$fits = $person === 0;
		$has_dayuse = false;

		foreach (RoomTypeModel::getList((int)$property->property_srl, 'open') as $room_type)
		{
			if ($room_type->use_dayuse === 'Y')
			{
				$has_dayuse = true;
			}
			if ($stay_type === 'dayuse' && $room_type->use_dayuse !== 'Y')
			{
				continue;
			}
			if ($person > 0 && (int)$room_type->max_person < $person)
			{
				continue;
			}
			$fits = true;

			if (count($ymds))
			{
				if (!Inventory::isAvailable($room_type, $stay_type, $ymds))
				{
					continue;
				}
				$price = Rate::totalOf($room_type, $stay_type, $ymds);
				$available_rooms += Inventory::remaining($room_type, $stay_type, $ymds[0]);
			}
			else
			{
				$prefix = $stay_type === 'dayuse' ? 'dayuse_price_' : 'stay_price_';
				$price = (int)$room_type->{$prefix . 'weekday'};
				$available_rooms += (int)$room_type->total_rooms;
			}

			if ($price > 0 && ($min_price === 0 || $price < $min_price))
			{
				$min_price = $price;
			}
		}

		return [
			'min_price' => $min_price,
			'available_rooms' => $available_rooms,
			'available' => $available_rooms > 0,
			'fits' => $fits,
			'has_dayuse' => $has_dayuse,
		];
	}

	/**
	 * 이 예약을 볼 수 있는 사람인가.
	 *
	 * @param object $booking
	 * @return bool
	 */
	protected function canReadBooking(object $booking): bool
	{
		if (!empty($this->grant->manager))
		{
			return true;
		}

		$member_srl = (int)($this->user->member_srl ?? 0);
		if ($member_srl > 0 && (int)$booking->member_srl === $member_srl)
		{
			return true;
		}

		$known = $_SESSION[self::LOOKUP_SESSION] ?? [];
		return is_array($known) && in_array((int)$booking->booking_srl, array_map('intval', $known), true);
	}

	/**
	 * 판매 중인 업소.
	 *
	 * @return object
	 */
	protected function getOpenProperty(): object
	{
		$property = PropertyModel::get((int)\Context::get('property_srl'));
		if (!$property || (int)$property->module_srl !== (int)$this->module_srl || $property->status !== 'open')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		return $property;
	}

	/**
	 * 판매 중인 객실 타입. 업소 소속인지도 대조한다.
	 *
	 * @param object $property
	 * @return object
	 */
	protected function getOpenRoomType(object $property): object
	{
		$room_type = RoomTypeModel::get((int)\Context::get('room_type_srl'));
		if (!$room_type || (int)$room_type->property_srl !== (int)$property->property_srl || $room_type->status !== 'open')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		return $room_type;
	}

	/**
	 * 예약할 수 있는 날짜 범위인가. 체크인은 오늘부터 MAX_ADVANCE_DAYS 안,
	 * 숙박일 수는 객실 타입의 최소·최대 숙박일 안이어야 한다.
	 *
	 * @param object $room_type
	 * @param string $stay_type
	 * @param string $checkin
	 * @param string $checkout
	 * @return void
	 */
	protected static function checkStayRange(object $room_type, string $stay_type, string $checkin, string $checkout): void
	{
		$today = date('Ymd');
		if ($checkin === '' || $checkin < $today || $checkin > date('Ymd', strtotime('+' . self::MAX_ADVANCE_DAYS . ' days')))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_invalid_dates');
		}
		if ($stay_type === 'dayuse')
		{
			return;
		}

		$nights = count(Inventory::nights($checkin, $checkout));
		$min = max(1, (int)($room_type->min_nights ?? 1));
		$max = max($min, (int)($room_type->max_nights ?? 30) ?: 30);
		if ($nights < 1)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_invalid_dates');
		}
		if ($nights < $min)
		{
			throw new \Zittme\Framework\Exception(sprintf(lang('lodging.msg_min_nights'), $min));
		}
		if ($nights > $max)
		{
			throw new \Zittme\Framework\Exception(sprintf(lang('lodging.msg_max_nights'), $max));
		}
	}

	/**
	 * YYYYMMDD 로 정리한다. 2026-08-21 표기도 받는다.
	 *
	 * @param string $raw
	 * @return string 형식이 아니면 빈 문자열
	 */
	protected static function cleanYmd(string $raw): string
	{
		$ymd = preg_replace('/[^0-9]/', '', $raw);
		return (strlen($ymd) === 8 && checkdate((int)substr($ymd, 4, 2), (int)substr($ymd, 6, 2), (int)substr($ymd, 0, 4))) ? $ymd : '';
	}
}
