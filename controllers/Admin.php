<?php

namespace Zittme\Modules\Lodging\Controllers;

use Zittme\Modules\Lodging\Models\Booking as BookingModel;
use Zittme\Modules\Lodging\Models\CancelRule;
use Zittme\Modules\Lodging\Models\Inventory;
use Zittme\Modules\Lodging\Models\Rate;
use Zittme\Modules\Lodging\Models\Property as PropertyModel;
use Zittme\Modules\Lodging\Models\RoomType as RoomTypeModel;

/**
 * 관리 화면 — 업소·객실·예약 운영.
 */
class Admin extends Base
{
	public function init()
	{
		parent::init();

		$this->setTemplatePath($this->module_path . 'views/admin/');
	}

	/**
	 * 코어 관리자 쪽 안내 화면. 운영은 전부 전용 콘솔에서 한다.
	 */
	public function dispLodgingAdminIntro()
	{
		\Context::set('console_url', getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole'));
		$this->setTemplateFile('intro');
	}

	/**
	 * 콘솔 화면 주소.
	 *
	 * @param string $page
	 * @param array $params 추가 파라미터 (property_srl 등)
	 * @return string
	 */
	public static function consoleUrl(string $page, array $params = []): string
	{
		$args = ['', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', $page];
		foreach ($params as $key => $value)
		{
			$args[] = $key;
			$args[] = $value;
		}

		return call_user_func_array('getNotEncodedUrl', $args);
	}

	/**
	 * 대시보드. 오늘 입실·퇴실·대실과 최근 예약.
	 */
	public function dispLodgingAdminDashboard()
	{
		$today = date('Ymd');

		$checkin = new \stdClass;
		$checkin->status = BookingModel::STATUS_CONFIRMED;
		$checkin->checkin_ymd = $today;
		$checkin->list_count = 50;
		$in = BookingModel::getList($checkin);

		$recent = new \stdClass;
		$recent->list_count = 10;
		$out = BookingModel::getList($recent);

		$today_checkins = $in->toBool() && is_array($in->data) ? $in->data : [];
		$properties = $this->getAllProperties();
		$prop_titles = [];
		foreach ($properties as $p) { $prop_titles[(int)$p->property_srl] = (string)$p->title; }

		// 요약 숫자. 예약 표를 직접 세는 편이 빠르고 단순하다. 표 접두사는 DB 가 붙인다
		$db = \Zittme\Framework\DB::getInstance();
		$month = date('Ym');
		$week_end = date('Ymd', strtotime('+6 days'));
		$confirmed = BookingModel::STATUS_CONFIRMED;

		$stat = new \stdClass;
		$stat->today_stay = 0;
		$stat->today_dayuse = 0;
		foreach ($today_checkins as $b) { if ($b->stay_type === 'dayuse') { $stat->today_dayuse++; } else { $stat->today_stay++; } }
		$stat->today_out = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND stay_type = ? AND checkout_ymd = ?', $confirmed, 'stay', $today)->fetch()->cnt;
		$stat->in_house = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND stay_type = ? AND checkin_ymd <= ? AND checkout_ymd > ?', $confirmed, 'stay', $today, $today)->fetch()->cnt;
		$stat->total_rooms = (int)$db->query('SELECT COALESCE(SUM(total_rooms), 0) AS cnt FROM lodging_room_type WHERE status = ?', 'open')->fetch()->cnt;
		$stat->occupancy = $stat->total_rooms > 0 ? (int)round($stat->in_house / $stat->total_rooms * 100) : 0;

		$row = $db->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM lodging_booking WHERE regdate LIKE ? AND status IN (?, ?)', $month . '%', $confirmed, 'completed')->fetch();
		$stat->month_count = (int)$row->cnt;
		$stat->month_amount = (int)$row->amount;
		$stat->month_avg = $stat->month_count > 0 ? (int)round($stat->month_amount / $stat->month_count) : 0;
		$stat->month_canceled = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE regdate LIKE ? AND status = ?', $month . '%', 'canceled')->fetch()->cnt;

		$stat->pending_onsite = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND pay_method = ? AND pay_status = ? AND checkin_ymd >= ?', $confirmed, 'onsite', 'pending', $today)->fetch()->cnt;
		$stat->pending_prepay = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND pay_method = ? AND pay_status = ?', $confirmed, 'prepay', 'pending')->fetch()->cnt;
		$stat->unanswered_reviews = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_review WHERE (reply IS NULL OR reply = ?)', '')->fetch()->cnt;
		$stat->stale_checkins = (int)$db->query('SELECT COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND checkout_ymd < ?', $confirmed, $today)->fetch()->cnt;

		// 다음 7일 입실 전망 (오늘 포함)
		$week = [];
		for ($i = 0; $i < 7; $i++) { $week[date('Ymd', strtotime("+{$i} days"))] = ['stay' => 0, 'dayuse' => 0]; }
		foreach ($db->query('SELECT checkin_ymd, stay_type, COUNT(*) AS cnt FROM lodging_booking WHERE status = ? AND checkin_ymd BETWEEN ? AND ? GROUP BY checkin_ymd, stay_type', $confirmed, $today, $week_end)->fetchAll() as $r)
		{
			if (isset($week[$r->checkin_ymd])) { $week[$r->checkin_ymd][$r->stay_type === 'dayuse' ? 'dayuse' : 'stay'] = (int)$r->cnt; }
		}

		// 숙소별 이번 달 예약·매출 상위
		$top_props = [];
		foreach ($db->query('SELECT property_srl, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM lodging_booking WHERE regdate LIKE ? AND status IN (?, ?) GROUP BY property_srl ORDER BY amount DESC LIMIT 6', $month . '%', $confirmed, 'completed')->fetchAll() as $r)
		{
			$r->title = $prop_titles[(int)$r->property_srl] ?? ('#' . $r->property_srl);
			$top_props[] = $r;
		}

		// 최근 후기 5건과 쿠폰 사용 상위
		$recent_reviews = [];
		foreach ($db->query('SELECT * FROM lodging_review ORDER BY regdate DESC LIMIT 5')->fetchAll() as $r)
		{
			$r->property_title = $prop_titles[(int)$r->property_srl] ?? '';
			$recent_reviews[] = $r;
		}
		$top_coupons = $db->query('SELECT title, code, used_count, use_limit, end_ymd, status FROM lodging_coupon ORDER BY used_count DESC, regdate DESC LIMIT 5')->fetchAll();

		\Context::set('today', $today);
		\Context::set('today_checkins', $today_checkins);
		\Context::set('recent_bookings', $out->toBool() && is_array($out->data) ? $out->data : []);
		\Context::set('property_list', $properties);
		\Context::set('prop_titles', $prop_titles);
		\Context::set('stat', $stat);
		\Context::set('week', $week);
		\Context::set('top_props', $top_props);
		\Context::set('recent_reviews', $recent_reviews);
		\Context::set('top_coupons', $top_coupons);

		$this->setTemplateFile('dashboard');
	}

	/**
	 * 업소 목록.
	 */
	public function dispLodgingAdminPropertyList()
	{
		$list = $this->getAllProperties();
		foreach ($list as $property)
		{
			$property->room_type_count = count(RoomTypeModel::getList((int)$property->property_srl));
		}

		\Context::set('property_list', $list);
		\Context::set('instance_list', $this->getInstanceList());
		$this->setTemplateFile('property_list');
	}

	/**
	 * 업소 만들기·고치기.
	 */
	public function dispLodgingAdminPropertyInfo()
	{
		$property = PropertyModel::get((int)\Context::get('property_srl'));

		$rules = [];
		foreach ($property ? CancelRule::getList((int)$property->property_srl) : [] as $rule)
		{
			$rules[] = ['days_before' => (int)$rule->days_before, 'refund_rate' => (int)$rule->refund_rate];
		}

		\Context::set('property', $property);
		\Context::set('cancel_rules_json', json_encode($rules));
		\Context::set('instance_list', $this->getInstanceList());
		\Context::set('property_images', $property ? \Zittme\Modules\Lodging\Models\Image::getList((int)$property->property_srl) : []);

		$this->setTemplateFile('property_info');
	}

	/**
	 * 페이지(인스턴스) 생성. 예약 화면이 붙을 mid 를 만든다.
	 */
	public function procLodgingAdminInsertInstance()
	{
		$vars = \Context::getRequestVars();

		$mid = trim((string)$vars->instance_mid);
		if (!preg_match('/^[a-zA-Z]([a-zA-Z0-9_]*)$/', $mid))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_invalid_instance_name');
		}

		$args = new \stdClass;
		$args->module = 'lodging';
		$args->mid = $mid;
		$args->browser_title = trim(utf8_normalize_spaces((string)$vars->browser_title)) ?: $mid;
		$args->layout_srl = (int)$vars->layout_srl;
		$args->mlayout_srl = (int)$vars->mlayout_srl;
		$args->skin = '/USE_DEFAULT/';
		$args->mskin = '/USE_DEFAULT/';
		$args->is_skin_fix = 'N';
		$args->is_mskin_fix = 'N';

		$output = \ModuleController::getInstance()->insertModule($args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(self::consoleUrl('properties'));
	}

	/**
	 * 업소 저장. 취소 규정도 같은 폼에서 함께 저장한다.
	 */
	public function procLodgingAdminInsertProperty()
	{
		$vars = \Context::getRequestVars();

		$module_srl = (int)$vars->target_module_srl;
		$property_srl = (int)$vars->property_srl;
		if ($property_srl > 0)
		{
			$existing = PropertyModel::get($property_srl);
			if (!$existing)
			{
				throw new \Zittme\Framework\Exceptions\TargetNotFound;
			}
			$module_srl = (int)$existing->module_srl;
		}
		if ($module_srl === 0)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_select_instance');
		}
		if (trim((string)$vars->title) === '')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_title_required');
		}

		$args = new \stdClass;
		$args->property_srl = $property_srl;
		$args->module_srl = $module_srl;
		$args->title = trim((string)$vars->title);
		$args->summary = trim((string)$vars->summary);
		$args->description = (string)$vars->description;
		$args->property_type = in_array($vars->property_type, ['hotel', 'motel', 'pension', 'guesthouse', 'resort'], true) ? $vars->property_type : 'motel';
		// 편의시설은 사전(Amenity::KEYS)에 있는 키만 쉼표로 이어 저장한다
		$args->amenities = \Zittme\Modules\Lodging\Models\Amenity::format(is_array($vars->amenities ?? null) ? $vars->amenities : []);
		$args->address = trim((string)$vars->address);
		$args->address_detail = trim((string)$vars->address_detail);
		$args->phone = trim((string)$vars->phone);
		$args->notify_email = trim((string)$vars->notify_email);
		$args->checkin_time = preg_replace('/[^0-9]/', '', (string)$vars->checkin_time) ?: '1500';
		$args->checkout_time = preg_replace('/[^0-9]/', '', (string)$vars->checkout_time) ?: '1100';
		$args->allow_onsite_pay = $vars->allow_onsite_pay === 'Y' ? 'Y' : 'N';
		$args->allow_prepay = $vars->allow_prepay === 'Y' ? 'Y' : 'N';
		$args->status = $vars->status === 'closed' ? 'closed' : 'open';
		$args->list_order = (int)$vars->list_order;

		if ($args->allow_onsite_pay === 'N' && $args->allow_prepay === 'N')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_no_pay_method');
		}

		$output = PropertyModel::save($args);
		if (!$output->toBool())
		{
			return $output;
		}

		$property_srl = (int)$output->get('property_srl');

		\Zittme\Modules\Lodging\Models\Image::saveUploaded('images', $module_srl, $property_srl);

		// 취소 규정. 화면이 JSON 배열 [{days_before, refund_rate}] 로 보낸다.
		$rules = json_decode((string)$vars->cancel_rules_json, true);
		if (is_array($rules))
		{
			$rule_output = CancelRule::saveAll($property_srl, $rules);
			if (!$rule_output->toBool())
			{
				return $rule_output;
			}
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'property_edit', 'property_srl', $property_srl));
	}

	/**
	 * 업소 삭제. 확정 예약이 남아 있으면 지우지 않는다.
	 */
	public function procLodgingAdminDeleteProperty()
	{
		$property = PropertyModel::get((int)\Context::get('property_srl'));
		if (!$property)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$check = new \stdClass;
		$check->property_srl = (int)$property->property_srl;
		$check->status = BookingModel::STATUS_CONFIRMED;
		$check->list_count = 1;
		$output = BookingModel::getList($check);
		if ($output->toBool() && is_array($output->data) && count($output->data))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_has_active_bookings');
		}

		$args = new \stdClass;
		$args->property_srl = (int)$property->property_srl;
		executeQuery('lodging.deleteCancelRules', $args);

		foreach (RoomTypeModel::getList((int)$property->property_srl) as $room_type)
		{
			$del = new \stdClass;
			$del->room_type_srl = (int)$room_type->room_type_srl;
			executeQuery('lodging.deleteRoomType', $del);
		}

		$result = executeQuery('lodging.deleteProperty', $args);
		if (!$result->toBool())
		{
			return $result;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'properties'));
	}


	/**
	 * 사진 삭제. 업소·객실 공용.
	 */
	public function procLodgingAdminDeleteImage()
	{
		$file_srl = (int)\Context::get('file_srl');
		$target_srl = (int)\Context::get('target_srl');
		$back = (string)\Context::get('back') === 'room_types' ? 'room_types' : 'property_edit';
		$property_srl = (int)\Context::get('property_srl');

		if (!\Zittme\Modules\Lodging\Models\Image::delete($file_srl, $target_srl))
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(self::consoleUrl($back, ['property_srl' => $property_srl]));
	}

	/**
	 * 객실 타입 목록·편집. 한 화면에서 행 단위로 다룬다.
	 */
	public function dispLodgingAdminRoomTypes()
	{
		$property = $this->getPropertyOr404();

		$room_types = RoomTypeModel::getList((int)$property->property_srl);
		$grid_counts = \Zittme\Modules\Lodging\Models\Room::countByType((int)$property->property_srl);
		foreach ($room_types as $room_type)
		{
			$room_type->grid_count = $grid_counts[(int)$room_type->room_type_srl] ?? 0;
			$room_type->images = \Zittme\Modules\Lodging\Models\Image::getList((int)$room_type->room_type_srl);
		}

		\Context::set('property', $property);
		\Context::set('room_types', $room_types);

		$this->setTemplateFile('room_types');
	}

	/**
	 * 객실 타입 저장.
	 */
	public function procLodgingAdminInsertRoomType()
	{
		$property = $this->getPropertyOr404();
		$vars = \Context::getRequestVars();

		if (trim((string)$vars->title) === '')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_title_required');
		}

		$args = new \stdClass;
		$args->room_type_srl = (int)$vars->room_type_srl;
		$args->property_srl = (int)$property->property_srl;
		$args->module_srl = (int)$property->module_srl;
		$args->title = trim((string)$vars->title);
		$args->description = trim((string)$vars->description);
		$args->total_rooms = max(1, (int)$vars->total_rooms);
		$args->std_person = max(1, (int)$vars->std_person);
		$args->max_person = max($args->std_person, (int)$vars->max_person);
		$args->stay_price_weekday = max(0, (int)$vars->stay_price_weekday);
		$args->stay_price_fri = max(0, (int)$vars->stay_price_fri);
		$args->stay_price_sat = max(0, (int)$vars->stay_price_sat);
		$args->use_dayuse = $vars->use_dayuse === 'Y' ? 'Y' : 'N';
		$args->dayuse_hours = max(1, (int)$vars->dayuse_hours);
		$args->dayuse_open = preg_replace('/[^0-9]/', '', (string)$vars->dayuse_open) ?: '1200';
		$args->dayuse_close = preg_replace('/[^0-9]/', '', (string)$vars->dayuse_close) ?: '2200';
		$args->dayuse_price_weekday = max(0, (int)$vars->dayuse_price_weekday);
		$args->dayuse_price_fri = max(0, (int)$vars->dayuse_price_fri);
		$args->dayuse_price_sat = max(0, (int)$vars->dayuse_price_sat);
		$args->min_nights = max(1, (int)($vars->min_nights ?? 1));
		$args->max_nights = max($args->min_nights, (int)($vars->max_nights ?? 30) ?: 30);
		$args->extra_person_price = max(0, (int)($vars->extra_person_price ?? 0));
		$args->long_stay_nights = max(0, (int)($vars->long_stay_nights ?? 0));
		$args->long_stay_rate = min(100, max(0, (int)($vars->long_stay_rate ?? 0)));
		$args->status = $vars->status === 'closed' ? 'closed' : 'open';
		$args->list_order = (int)$vars->list_order;

		$output = RoomTypeModel::save($args);
		if (!$output->toBool())
		{
			return $output;
		}

		\Zittme\Modules\Lodging\Models\Image::saveUploaded('images', (int)$property->module_srl, (int)$output->get('room_type_srl'));

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'room_types', 'property_srl', $property->property_srl));
	}

	/**
	 * 객실 타입 삭제. 확정 예약이 남아 있으면 거부.
	 */
	public function procLodgingAdminDeleteRoomType()
	{
		$room_type = RoomTypeModel::get((int)\Context::get('room_type_srl'));
		if (!$room_type)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$check = new \stdClass;
		$check->room_type_srl = (int)$room_type->room_type_srl;
		$check->status = BookingModel::STATUS_CONFIRMED;
		$check->list_count = 1;
		$output = BookingModel::getList($check);
		if ($output->toBool() && is_array($output->data) && count($output->data))
		{
			throw new \Zittme\Framework\Exception('lodging.msg_has_active_bookings');
		}

		$args = new \stdClass;
		$args->room_type_srl = (int)$room_type->room_type_srl;
		$result = executeQuery('lodging.deleteRoomType', $args);
		if (!$result->toBool())
		{
			return $result;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'room_types', 'property_srl', $room_type->property_srl));
	}

	/**
	 * 요금·재고 달력. 한 달을 객실 타입별로 보여 준다.
	 */
	public function dispLodgingAdminCalendar()
	{
		$property = $this->getPropertyOr404();

		$month = preg_replace('/[^0-9]/', '', (string)\Context::get('month'));
		if (strlen($month) !== 6)
		{
			$month = date('Ym');
		}
		$start = $month . '01';
		$end = date('Ymt', strtotime($start));

		$room_types = RoomTypeModel::getList((int)$property->property_srl);
		$calendar = [];
		foreach ($room_types as $room_type)
		{
			$srl = (int)$room_type->room_type_srl;
			$rows = Inventory::getRange($srl, $start, $end);
			$days = [];
			for ($t = strtotime($start); $t <= strtotime($end); $t += 86400)
			{
				$ymd = date('Ymd', $t);
				$row = $rows[$ymd] ?? null;
				$total = $row && (int)$row->total_override > 0 ? (int)$row->total_override : (int)$room_type->total_rooms;
				$days[$ymd] = [
					'price' => Rate::priceOf($room_type, 'stay', $ymd),
					'is_override' => Rate::getOverride($srl, 'stay', $ymd) !== null,
					'total' => $total,
					'booked' => $row ? (int)$row->booked_stay : 0,
					'closed' => $row ? $row->closed : 'N',
				];
			}
			$calendar[$srl] = $days;
		}

		\Context::set('property', $property);
		\Context::set('room_types', $room_types);
		\Context::set('calendar', $calendar);
		\Context::set('month', $month);
		\Context::set('prev_month', date('Ym', strtotime($start . ' -1 month')));
		\Context::set('next_month', date('Ym', strtotime($start . ' +1 month')));

		$this->setTemplateFile('calendar');
	}

	/**
	 * 달력에서 하루 설정 저장 — 지정가·판매 중지·객실 수 조정.
	 */
	public function procLodgingAdminSaveDay()
	{
		$room_type = RoomTypeModel::get((int)\Context::get('room_type_srl'));
		if (!$room_type)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$ymd = preg_replace('/[^0-9]/', '', (string)\Context::get('ymd'));
		if (strlen($ymd) !== 8)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_invalid_dates');
		}

		// 지정가. 0 이면 지운다 (기본가 복귀).
		Rate::setOverride((int)$room_type->room_type_srl, 'stay', $ymd, (int)\Context::get('price_stay'));
		if ($room_type->use_dayuse === 'Y')
		{
			Rate::setOverride((int)$room_type->room_type_srl, 'dayuse', $ymd, (int)\Context::get('price_dayuse'));
		}

		Inventory::setDay((int)$room_type->room_type_srl, $ymd, (string)\Context::get('closed'), (int)\Context::get('total_override'));

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'calendar', 'property_srl', $room_type->property_srl, 'month', substr($ymd, 0, 6)));
	}

	/**
	 * 예약 목록.
	 */
	public function dispLodgingAdminBookings()
	{
		$property = $this->getPropertyOr404();

		$args = new \stdClass;
		$args->property_srl = (int)$property->property_srl;
		$args->page = (int)\Context::get('page') ?: 1;
		$args->list_count = 20;

		$status = (string)\Context::get('status');
		BookingModel::expireStaleHolds();
		if (in_array($status, [BookingModel::STATUS_HOLD, BookingModel::STATUS_CONFIRMED, BookingModel::STATUS_CANCELED, BookingModel::STATUS_EXPIRED, BookingModel::STATUS_COMPLETED, BookingModel::STATUS_NOSHOW], true))
		{
			$args->status = $status;
		}
		$keyword = trim((string)\Context::get('search_keyword'));
		if ($keyword !== '')
		{
			$args->s_name = $keyword;
			$args->s_phone = $keyword;
			$args->s_code = $keyword;
		}

		// 체크인일 구간 필터
		$start_ymd = preg_replace('/[^0-9]/', '', (string)\Context::get('start_ymd'));
		$end_ymd = preg_replace('/[^0-9]/', '', (string)\Context::get('end_ymd'));
		if (strlen($start_ymd) === 8)
		{
			$args->start_ymd = $start_ymd;
		}
		if (strlen($end_ymd) === 8)
		{
			$args->end_ymd = $end_ymd;
		}

		$output = BookingModel::getList($args);

		// 배정 화면용: 호실 목록과 객실 타입 이름표
		$type_names = [];
		foreach (RoomTypeModel::getList((int)$property->property_srl) as $room_type)
		{
			$type_names[(int)$room_type->room_type_srl] = $room_type->title;
		}
		$rooms_flat = [];
		foreach (\Zittme\Modules\Lodging\Models\Room::getGrid((int)$property->property_srl) as $rooms)
		{
			foreach ($rooms as $room)
			{
				$rooms_flat[] = $room;
			}
		}

		\Context::set('property', $property);
		\Context::set('booking_list', $output->toBool() && is_array($output->data) ? $output->data : []);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('page', $args->page);
		\Context::set('status', $status);
		\Context::set('search_keyword', $keyword);
		\Context::set('type_names', $type_names);
		\Context::set('rooms_flat', $rooms_flat);

		$this->setTemplateFile('bookings');
	}

	/**
	 * 예약 처리 — 이용완료·노쇼·취소(전액 환불 선택)·호실 배정.
	 */
	public function procLodgingAdminBooking()
	{
		$booking = BookingModel::get((int)\Context::get('booking_srl'));
		if (!$booking)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$action = (string)\Context::get('booking_action');

		if ($action === 'cancel')
		{
			$force = \Context::get('full_refund') === 'Y';
			$output = BookingModel::cancel($booking, trim((string)\Context::get('reason')), $force);
			if (!$output->toBool())
			{
				return $output;
			}

			$refund = (int)$output->get('refund_amount');
			if ($refund > 0 && $booking->pay_method === 'prepay' && $booking->pay_status === 'paid' && self::isPayAvailable())
			{
				$order = \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('lodging', (int)$booking->booking_srl);
				if ($order)
				{
					\Zittme\Modules\Zittme_pay\PayService::cancel((int)$order->order_srl, lang('lodging.msg_canceled_by_admin'), $refund);
				}
			}
		}
		elseif (in_array($action, ['complete', 'noshow'], true) && $booking->status !== BookingModel::STATUS_CONFIRMED)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_not_confirmed');
		}
		elseif ($action === 'complete')
		{
			$output = BookingModel::update((int)$booking->booking_srl, ['status' => BookingModel::STATUS_COMPLETED]);
			// 이용 완료 시 회원에게 포인트를 적립한다
			if ($output->toBool())
			{
				\Zittme\Modules\Lodging\Models\Point::earnFor($booking);
			}
		}
		elseif ($action === 'noshow')
		{
			$output = BookingModel::update((int)$booking->booking_srl, ['status' => BookingModel::STATUS_NOSHOW]);
		}
		elseif ($action === 'paid')
		{
			// 현장결제 수납 처리. 선결제는 결제 쪽 승인으로만 바뀐다
			if ($booking->pay_method !== 'onsite' || $booking->status !== BookingModel::STATUS_CONFIRMED)
			{
				throw new \Zittme\Framework\Exception('msg_invalid_request');
			}
			$output = BookingModel::updatePayStatus((int)$booking->booking_srl, 'paid');
		}
		elseif ($action === 'assign')
		{
			$output = BookingModel::update((int)$booking->booking_srl, ['assigned_room_srl' => (int)\Context::get('assigned_room_srl')]);
		}
		else
		{
			throw new \Zittme\Framework\Exception('msg_invalid_request');
		}

		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'bookings', 'property_srl', $booking->property_srl));
	}

	/**
	 * 후기 관리 — 답글과 노출 상태.
	 */
	public function dispLodgingAdminReviews()
	{
		$property = $this->getPropertyOr404();

		$page = (int)\Context::get('page') ?: 1;
		$args = new \stdClass;
		$args->property_srl = (int)$property->property_srl;
		$args->list_count = 20;
		$args->page = $page;
		$output = executeQueryArray('lodging.getReviewList', $args);

		\Context::set('property', $property);
		\Context::set('review_list', $output->toBool() && is_array($output->data) ? $output->data : []);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('page', $page);

		$this->setTemplateFile('reviews');
	}

	/**
	 * 후기 답글·노출 상태 저장.
	 */
	public function procLodgingAdminReviewReply()
	{
		$args = new \stdClass;
		$args->review_srl = (int)\Context::get('review_srl');
		$args->reply = trim((string)\Context::get('reply'));
		$args->reply_date = $args->reply !== '' ? date('YmdHis') : '';
		$args->status = \Context::get('status') === 'hidden' ? 'hidden' : 'public';

		$output = executeQuery('lodging.updateReview', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'reviews', 'property_srl', \Context::get('property_srl')));
	}

	/**
	 * 층×호실 그리드.
	 */
	public function dispLodgingAdminRooms()
	{
		$property = $this->getPropertyOr404();

		$grid = [];
		foreach (\Zittme\Modules\Lodging\Models\Room::getGrid((int)$property->property_srl) as $floor => $rooms)
		{
			foreach ($rooms as $room)
			{
				$grid[] = [
					'floor' => (int)$room->floor,
					'name' => $room->name,
					'room_type_srl' => (int)$room->room_type_srl,
					'status' => $room->status,
					'memo' => (string)$room->memo,
				];
			}
		}

		$room_types = RoomTypeModel::getList((int)$property->property_srl);
		$types = [];
		foreach ($room_types as $room_type)
		{
			$types[] = ['srl' => (int)$room_type->room_type_srl, 'title' => $room_type->title, 'total' => (int)$room_type->total_rooms];
		}

		\Context::set('property', $property);
		\Context::set('grid_json', json_encode($grid, \JSON_UNESCAPED_UNICODE));
		\Context::set('types_json', json_encode($types, \JSON_UNESCAPED_UNICODE));

		$this->setTemplateFile('rooms');
	}

	/**
	 * 그리드 저장. 화면이 JSON 한 덩어리로 보낸다.
	 */
	public function procLodgingAdminSaveRooms()
	{
		$property = $this->getPropertyOr404();

		$rooms = json_decode((string)\Context::get('rooms_json'), true);
		if (!is_array($rooms))
		{
			throw new \Zittme\Framework\Exception('msg_invalid_request');
		}

		$output = \Zittme\Modules\Lodging\Models\Room::saveGrid((int)$property->property_srl, $rooms);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'rooms', 'property_srl', $property->property_srl));
	}

	/**
	 * 쿠폰 목록·등록.
	 */
	public function dispLodgingAdminCoupons()
	{
		$args = new \stdClass;
		$output = executeQueryArray('lodging.getCouponList', $args);

		\Context::set('coupon_list', $output->toBool() && is_array($output->data) ? $output->data : []);
		\Context::set('instance_list', $this->getInstanceList());
		\Context::set('property_list', $this->getAllProperties());

		$this->setTemplateFile('coupons');
	}

	/**
	 * 쿠폰 저장.
	 */
	public function procLodgingAdminInsertCoupon()
	{
		$vars = \Context::getRequestVars();

		if (trim((string)$vars->title) === '')
		{
			throw new \Zittme\Framework\Exception('lodging.msg_title_required');
		}

		$module_srl = (int)$vars->target_module_srl;
		if ($module_srl === 0)
		{
			throw new \Zittme\Framework\Exception('lodging.msg_select_instance');
		}

		$args = new \stdClass;
		$args->coupon_srl = (int)$vars->coupon_srl;
		$args->module_srl = $module_srl;
		$args->property_srl = (int)$vars->property_srl;
		$args->title = trim((string)$vars->title);
		$args->code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$vars->code));
		$args->discount_type = $vars->discount_type === 'rate' ? 'rate' : 'amount';
		$args->discount_value = max(0, (int)$vars->discount_value);
		$args->max_discount = max(0, (int)$vars->max_discount);
		$args->min_amount = max(0, (int)$vars->min_amount);
		$args->apply_to = in_array($vars->apply_to, ['stay', 'dayuse'], true) ? $vars->apply_to : 'all';
		$args->start_ymd = preg_replace('/[^0-9]/', '', (string)$vars->start_ymd);
		$args->end_ymd = preg_replace('/[^0-9]/', '', (string)$vars->end_ymd);
		$args->use_limit = max(0, (int)$vars->use_limit);
		$args->per_member_limit = max(0, (int)$vars->per_member_limit);
		$args->status = $vars->status === 'closed' ? 'closed' : 'open';

		if ($args->coupon_srl > 0 && CouponModel::get($args->coupon_srl))
		{
			$output = executeQuery('lodging.updateCoupon', $args);
		}
		else
		{
			$args->coupon_srl = getNextSequence();
			$args->regdate = date('YmdHis');
			$output = executeQuery('lodging.insertCoupon', $args);
		}

		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'coupons'));
	}

	/**
	 * 쿠폰 삭제.
	 */
	public function procLodgingAdminDeleteCoupon()
	{
		$args = new \stdClass;
		$args->coupon_srl = (int)\Context::get('coupon_srl');

		$output = executeQuery('lodging.deleteCoupon', $args);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'coupons'));
	}

	/**
	 * 요청된 업소. 없으면 404.
	 *
	 * @return object
	 */
	protected function getPropertyOr404(): object
	{
		$property = PropertyModel::get((int)\Context::get('property_srl'));
		if (!$property)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		return $property;
	}
	/**
	 * 이 모듈로 만든 페이지 목록.
	 *
	 * @return array
	 */
	protected function getInstanceList(): array
	{
		$list = \ModuleModel::getMidList((object)['module' => 'lodging']);
		return is_array($list) ? $list : [];
	}

	/**
	 * 모든 인스턴스의 업소 목록.
	 *
	 * @return array
	 */
	protected function getAllProperties(): array
	{
		$list = [];
		foreach ($this->getInstanceList() as $instance)
		{
			foreach (PropertyModel::getList((int)$instance->module_srl) as $property)
			{
				$property->mid = $instance->mid;
				$list[] = $property;
			}
		}

		return $list;
	}
}
