<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 예약·취소 메일 알림.
 *
 * 발송은 코어 메일 기능(Zittme\Framework\Mail)에 맡긴다. 업소의 notify_email 로
 * 운영자에게, 예약자 이메일이 있으면 예약자에게도 보낸다.
 * 발송 실패가 예약 처리를 막지 않도록 예외를 삼키고 bool 만 돌려준다.
 */
class Mailer
{
	/**
	 * 예약 접수 알림. 운영자 + 예약자.
	 *
	 * @param object $booking
	 * @param object $property
	 * @param object $room_type
	 * @return void
	 */
	public static function onBooked(object $booking, object $property, object $room_type): void
	{
		$subject = sprintf('[%s] %s - %s', $property->title, lang('lodging.mail_booked'), $booking->booking_code);
		$body = self::renderBooking($booking, $property, $room_type);

		foreach (self::parseEmails((string)($property->notify_email ?? '')) as $email)
		{
			self::send($email, '', $subject, $body);
		}

		if (trim((string)$booking->guest_email) !== '')
		{
			self::send($booking->guest_email, $booking->guest_name, $subject, $body);
		}
	}

	/**
	 * 취소 알림. 운영자 + 예약자.
	 *
	 * @param object $booking 취소 반영 후 상태
	 * @param object $property
	 * @param object $room_type
	 * @param int $refund
	 * @return void
	 */
	public static function onCanceled(object $booking, object $property, object $room_type, int $refund): void
	{
		$subject = sprintf('[%s] %s - %s', $property->title, lang('lodging.mail_canceled'), $booking->booking_code);
		$body = self::renderBooking($booking, $property, $room_type);
		$body .= '<p style="margin:14px 0 0;font-size:14px;"><strong>' . htmlspecialchars(lang('lodging.lodging_refund_preview'), ENT_QUOTES, 'UTF-8') . ':</strong> '
			. number_format($refund) . lang('lodging.mail_won') . '</p>';

		foreach (self::parseEmails((string)($property->notify_email ?? '')) as $email)
		{
			self::send($email, '', $subject, $body);
		}

		if (trim((string)$booking->guest_email) !== '')
		{
			self::send($booking->guest_email, $booking->guest_name, $subject, $body);
		}
	}

	/**
	 * 예약 내용 표. 메일 클라이언트 호환을 위해 테이블 + 인라인 스타일만 쓴다.
	 *
	 * @param object $booking
	 * @param object $property
	 * @param object $room_type
	 * @return string
	 */
	protected static function renderBooking(object $booking, object $property, object $room_type): string
	{
		$is_stay = $booking->stay_type !== 'dayuse';
		$period = $is_stay
			? zdate($booking->checkin_ymd, 'Y.m.d') . ' ~ ' . zdate($booking->checkout_ymd, 'Y.m.d')
			: zdate($booking->checkin_ymd, 'Y.m.d')
				. (($booking->dayuse_start ?? '') !== '' ? ' ' . substr($booking->dayuse_start, 0, 2) . ':' . substr($booking->dayuse_start, 2, 2) : '');

		$rows = [
			[lang('lodging.lodging_booking_code'), $booking->booking_code],
			[lang('lodging.lodging_property'), $property->title],
			[lang('lodging.lodging_room_type'), $room_type->title . ' (' . ($is_stay ? lang('lodging.lodging_stay') : lang('lodging.lodging_dayuse')) . ')'],
			[lang('lodging.lodging_checkin'), $period],
			[lang('lodging.lodging_person'), (string)$booking->person_count],
			[lang('lodging.lodging_booker_name'), $booking->guest_name . ' / ' . $booking->guest_phone],
			[lang('lodging.lodging_total_amount'), number_format($booking->total_amount) . lang('lodging.mail_won')
				. ' (' . ($booking->pay_method === 'prepay' ? lang('lodging.lodging_pay_prepay') : lang('lodging.lodging_pay_onsite')) . ')'],
		];
		if (trim((string)$booking->request_memo) !== '')
		{
			$rows[] = [lang('lodging.lodging_request_memo'), $booking->request_memo];
		}

		$html = '';
		foreach ($rows as [$label, $value])
		{
			$html .= '<tr>'
				. '<th style="width:120px;padding:9px 12px;background:#f8fafc;border:1px solid #e5e7eb;text-align:left;font-weight:600;color:#374151;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
				. '<td style="padding:9px 12px;border:1px solid #e5e7eb;color:#111827;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
				. '</tr>';
		}

		return '<table cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;border-collapse:collapse;font-size:14px;font-family:-apple-system,BlinkMacSystemFont,Arial,sans-serif;">' . $html . '</table>';
	}

	/**
	 * 한 통 보낸다.
	 *
	 * @param string $email
	 * @param string $name
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 */
	protected static function send(string $email, string $name, string $subject, string $body): bool
	{
		if (!filter_var($email, \FILTER_VALIDATE_EMAIL))
		{
			return false;
		}

		try
		{
			$mail = new \Zittme\Framework\Mail;
			$mail->addTo($email, $name !== '' ? $name : null);
			$mail->setSubject($subject);
			$mail->setBody($body);

			return (bool)$mail->send();
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	/**
	 * 쉼표·줄바꿈 구분 주소 목록.
	 *
	 * @param string $raw
	 * @return array
	 */
	protected static function parseEmails(string $raw): array
	{
		$result = [];
		foreach ((array)preg_split('/[,;\r\n]+/', $raw) as $email)
		{
			$email = trim((string)$email);
			if ($email !== '' && filter_var($email, \FILTER_VALIDATE_EMAIL))
			{
				$result[] = $email;
			}
		}

		return array_values(array_unique($result));
	}
}
