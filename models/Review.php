<?php

namespace Zittme\Modules\Lodging\Models;

/**
 * 숙박 검증 후기.
 *
 * 이용완료(completed) 예약만 후기를 남길 수 있고, 예약당 1건이다
 * (booking_srl 유니크 인덱스가 지킨다).
 */
class Review
{
	/**
	 * 업소의 공개 후기 목록.
	 *
	 * @param int $property_srl
	 * @param int $count
	 * @param int $page
	 * @return array
	 */
	public static function getList(int $property_srl, int $count = 10, int $page = 1): array
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;
		$args->status = 'public';
		$args->list_count = $count;
		$args->page = $page;

		$output = executeQueryArray('lodging.getReviewList', $args);
		return $output->toBool() && is_array($output->data) ? $output->data : [];
	}

	/**
	 * 평균 점수와 건수.
	 *
	 * @param int $property_srl
	 * @return object {count, average}
	 */
	public static function summary(int $property_srl): object
	{
		$args = new \stdClass;
		$args->property_srl = $property_srl;
		$args->status = 'public';

		$output = executeQuery('lodging.getReviewSummary', $args);
		$row = ($output->toBool() && $output->data) ? $output->data : null;
		$row = is_array($row) ? (count($row) ? reset($row) : null) : $row;

		return (object)[
			'count' => is_object($row) ? (int)$row->count : 0,
			'average' => is_object($row) && (int)$row->count > 0 ? round((float)$row->average, 1) : 0.0,
		];
	}

	/**
	 * 후기를 쓴다.
	 *
	 * @param object $booking
	 * @param int $score 1~5
	 * @param string $content
	 * @return object
	 */
	public static function write(object $booking, int $score, string $content): object
	{
		if ($booking->status !== 'completed')
		{
			return new \BaseObject(-1, 'lodging.msg_review_not_completed');
		}
		if ($content === '')
		{
			return new \BaseObject(-1, 'lodging.msg_review_content_required');
		}

		$args = new \stdClass;
		$args->review_srl = getNextSequence();
		$args->property_srl = (int)$booking->property_srl;
		$args->room_type_srl = (int)$booking->room_type_srl;
		$args->booking_srl = (int)$booking->booking_srl;
		$args->member_srl = (int)$booking->member_srl;
		$args->writer_name = self::maskName((string)$booking->guest_name);
		$args->score = min(5, max(1, $score));
		$args->content = utf8_clean($content);
		$args->regdate = date('YmdHis');

		// 예약당 1건은 booking_srl 유니크 인덱스가 지킨다. 중복이면 여기서 실패한다.
		$output = executeQuery('lodging.insertReview', $args);
		if (!$output->toBool())
		{
			return new \BaseObject(-1, 'lodging.msg_review_exists');
		}

		return $output;
	}

	/**
	 * 이름 마스킹. 홍길동 → 홍*동.
	 *
	 * @param string $name
	 * @return string
	 */
	protected static function maskName(string $name): string
	{
		$len = mb_strlen($name);
		if ($len <= 1)
		{
			return $name;
		}
		if ($len === 2)
		{
			return mb_substr($name, 0, 1) . '*';
		}

		return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
	}
}
