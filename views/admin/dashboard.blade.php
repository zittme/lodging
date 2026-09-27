@include('_tabs')

<style>
.ldc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.ldc-kpi { padding: 18px 20px; border: 1px solid var(--ld-line); border-radius: 14px; background: #fff; }
.ldc-kpi small { display: block; font-size: 12.5px; font-weight: 600; color: var(--ld-sub); }
.ldc-kpi strong { display: block; margin-top: 6px; font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
.ldc-kpi strong em { font-style: normal; font-size: 13px; font-weight: 600; color: var(--ld-sub); margin-left: 3px; }
.ldc-kpi span { display: block; margin-top: 4px; font-size: 12.5px; color: var(--ld-sub); }
.ldc-kpi.is-accent { background: var(--ld-accent); border-color: var(--ld-accent); color: #fff; }
.ldc-kpi.is-accent small, .ldc-kpi.is-accent span, .ldc-kpi.is-accent strong em { color: rgba(255,255,255,.75); }
.ldc-todo { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.ldc-todo a { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px; border: 1px solid var(--ld-line); border-radius: 12px; background: #fafbfb; color: var(--ld-ink) !important; text-decoration: none !important; font-size: 13.5px; font-weight: 600; }
.ldc-todo a:hover { border-color: var(--ld-accent); }
.ldc-todo b { font-size: 20px; font-weight: 800; color: var(--ld-accent); }
.ldc-todo a.is-zero b { color: var(--ld-sub); }
.ldc-grid-2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; }
.ldc-grid-2 > section.section { margin-bottom: 0; }
.ldc-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
.ldc-week div { padding: 12px 8px; border: 1px solid var(--ld-line); border-radius: 10px; text-align: center; }
.ldc-week div.is-today { border-color: var(--ld-accent); background: var(--ld-accent-soft); }
.ldc-week small { display: block; font-size: 12px; color: var(--ld-sub); }
.ldc-week strong { display: block; font-size: 20px; font-weight: 800; }
.ldc-week span { display: block; font-size: 11.5px; color: var(--ld-sub); }
.ldc-bar { position: relative; height: 8px; border-radius: 999px; background: #eef0f2; overflow: hidden; }
.ldc-bar i { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px; background: var(--ld-accent); }
.ldc-review { padding: 12px 0; border-bottom: 1px solid #f2f3f4; font-size: 13.5px; }
.ldc-review:last-child { border-bottom: 0; }
.ldc-review b { color: #d98d1c; margin-right: 6px; }
.ldc-review small { color: var(--ld-sub); margin-left: 6px; }
.ldc-review p { margin: 4px 0 0; color: var(--ld-ink); }
@media (max-width: 1100px) { .ldc-kpis, .ldc-todo { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ldc-grid-2 { grid-template-columns: 1fr; } }
</style>

@php
$month_label = zdate(date('Ym') . '01000000', 'Y.m');
$week_days = [$lang->lodging_wd_sun, $lang->lodging_wd_mon, $lang->lodging_wd_tue, $lang->lodging_wd_wed, $lang->lodging_wd_thu, $lang->lodging_wd_fri, $lang->lodging_wd_sat];
$week_max = 1;
foreach ($week as $w) { $week_max = max($week_max, $w['stay'] + $w['dayuse']); }
$top_max = 1;
foreach ($top_props as $tp) { $top_max = max($top_max, (int)$tp->amount); }
@endphp

<div class="ldc-kpis">
	<div class="ldc-kpi is-accent">
		<small>{{ $lang->lodging_admin_today_checkins }} ({{ zdate($today . '000000', 'm.d') }})</small>
		<strong>{{ $stat->today_stay }}<em>{{ $lang->lodging_stay }}</em> · {{ $stat->today_dayuse }}<em>{{ $lang->lodging_dayuse }}</em></strong>
		<span>{{ $lang->lodging_admin_today_checkouts }} {{ $stat->today_out }}</span>
	</div>
	<div class="ldc-kpi">
		<small>{{ $lang->lodging_admin_occupancy }}</small>
		<strong>{{ $stat->occupancy }}<em>%</em></strong>
		<span>{{ sprintf($lang->lodging_admin_in_house_of, $stat->in_house, $stat->total_rooms) }}</span>
	</div>
	<div class="ldc-kpi">
		<small>{{ $month_label }} {{ $lang->lodging_admin_month_revenue }}</small>
		<strong>{{ number_format($stat->month_amount) }}<em>{{ $lang->lodging_won }}</em></strong>
		<span>{{ sprintf($lang->lodging_admin_month_count_avg, $stat->month_count, number_format($stat->month_avg)) }}</span>
	</div>
	<div class="ldc-kpi">
		<small>{{ $month_label }} {{ $lang->lodging_admin_month_canceled }}</small>
		<strong>{{ $stat->month_canceled }}<em>{{ $lang->lodging_admin_unit_count }}</em></strong>
		<span>{{ $stat->month_count + $stat->month_canceled > 0 ? round($stat->month_canceled / ($stat->month_count + $stat->month_canceled) * 100) : 0 }}% {{ $lang->lodging_admin_cancel_rate }}</span>
	</div>
</div>

<section class="section">
	<h2>{{ $lang->lodging_admin_todo }}</h2>
	<div class="ldc-todo">
		<a href="{{ \Zittme\Modules\Lodging\Controllers\Admin::consoleUrl('bookings', ['property_srl' => count($property_list) ? $property_list[0]->property_srl : 0]) }}" class="@if (!$stat->pending_onsite) is-zero @endif"><span>{{ $lang->lodging_admin_pending_onsite }}</span><b>{{ $stat->pending_onsite }}</b></a>
		<a href="{{ \Zittme\Modules\Lodging\Controllers\Admin::consoleUrl('bookings', ['property_srl' => count($property_list) ? $property_list[0]->property_srl : 0]) }}" class="@if (!$stat->pending_prepay) is-zero @endif"><span>{{ $lang->lodging_admin_pending_prepay }}</span><b>{{ $stat->pending_prepay }}</b></a>
		<a href="{{ \Zittme\Modules\Lodging\Controllers\Admin::consoleUrl('reviews', ['property_srl' => count($property_list) ? $property_list[0]->property_srl : 0]) }}" class="@if (!$stat->unanswered_reviews) is-zero @endif"><span>{{ $lang->lodging_admin_unanswered_reviews }}</span><b>{{ $stat->unanswered_reviews }}</b></a>
		<a href="{{ \Zittme\Modules\Lodging\Controllers\Admin::consoleUrl('bookings', ['property_srl' => count($property_list) ? $property_list[0]->property_srl : 0]) }}" class="@if (!$stat->stale_checkins) is-zero @endif"><span>{{ $lang->lodging_admin_stale_checkins }}</span><b>{{ $stat->stale_checkins }}</b></a>
	</div>
</section>

<section class="section">
	<h2>{{ $lang->lodging_admin_week_outlook }}</h2>
	<div class="ldc-week">
		@foreach ($week as $ymd => $w)
		<div class="@if ($ymd === $today) is-today @endif">
			<small>{{ zdate($ymd . '000000', 'm.d') }} ({{ $week_days[(int)date('w', strtotime($ymd))] }})</small>
			<strong>{{ $w['stay'] + $w['dayuse'] }}</strong>
			<span>{{ $lang->lodging_stay }} {{ $w['stay'] }} · {{ $lang->lodging_dayuse }} {{ $w['dayuse'] }}</span>
		</div>
		@endforeach
	</div>
</section>

<div class="ldc-grid-2">
	<section class="section">
		<h2>{{ $month_label }} {{ $lang->lodging_admin_top_properties }}</h2>
		<table class="x_table">
			<thead><tr><th>{{ $lang->lodging_property }}</th><th class="nowr">{{ $lang->lodging_admin_bookings }}</th><th class="nowr">{{ $lang->lodging_admin_revenue }}</th><th style="width:30%"></th></tr></thead>
			<tbody>
				@foreach ($top_props as $tp)
				<tr>
					<td>{{ $tp->title }}</td>
					<td class="nowr">{{ $tp->cnt }}</td>
					<td class="nowr">{{ number_format($tp->amount) }}</td>
					<td><div class="ldc-bar"><i style="width:{{ round($tp->amount / $top_max * 100) }}%"></i></div></td>
				</tr>
				@endforeach
				@if (!count($top_props))
				<tr><td colspan="4" class="x_text-center">{{ $lang->lodging_no_booking }}</td></tr>
				@endif
			</tbody>
		</table>
	</section>

	<section class="section">
		<h2>{{ $lang->lodging_admin_recent_reviews }}</h2>
		@foreach ($recent_reviews as $rv)
		<div class="ldc-review">
			<b>&#9733; {{ $rv->score }}</b>{{ $rv->property_title }}<small>{{ $rv->writer_name }} · {{ zdate($rv->regdate, 'm.d') }}@if (($rv->reply ?? '') === '') · <span class="x_label x_label-warning">{{ $lang->lodging_admin_no_reply }}</span>@endif</small>
			<p>{{ mb_strimwidth((string)$rv->content, 0, 90, '…') }}</p>
		</div>
		@endforeach
		@if (!count($recent_reviews))
		<p class="x_text-center">{{ $lang->lodging_no_review }}</p>
		@endif
	</section>
</div>

<div class="ldc-grid-2">
	<section class="section">
		<h2>{{ $lang->lodging_admin_today_checkins }} ({{ zdate($today . '000000', 'Y.m.d') }})</h2>
		<table class="x_table x_table-striped">
			<thead>
				<tr>
					<th scope="col" class="nowr">{{ $lang->lodging_booking_code }}</th>
					<th scope="col">{{ $lang->lodging_booker_name }}</th>
					<th scope="col" class="nowr">{{ $lang->lodging_stay }}/{{ $lang->lodging_dayuse }}</th>
					<th scope="col" class="nowr">{{ $lang->lodging_total_amount }}</th>
					<th scope="col" class="nowr">{{ $lang->lodging_pay_method }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach($today_checkins as $booking)
				<tr>
					<td class="nowr">{{ $booking->booking_code }}</td>
					<td>{{ $booking->guest_name }} · {{ $booking->guest_phone }}<br /><small style="color:var(--ld-sub)">{{ $prop_titles[(int)$booking->property_srl] ?? '' }}</small></td>
					<td class="nowr">{{ $booking->stay_type === 'dayuse' ? $lang->lodging_dayuse : $lang->lodging_stay }}</td>
					<td class="nowr">{{ number_format($booking->total_amount) }}</td>
					<td class="nowr">{{ $booking->pay_method === 'prepay' ? $lang->lodging_pay_prepay : $lang->lodging_pay_onsite }} / {{ $lang->{'lodging_pay_' . $booking->pay_status} ?? $booking->pay_status }}</td>
				</tr>
				@endforeach
				@if(!count($today_checkins))
				<tr><td colspan="5" class="x_text-center">{{ $lang->lodging_no_booking }}</td></tr>
				@endif
			</tbody>
		</table>
	</section>

	<section class="section">
		<h2>{{ $lang->lodging_admin_coupon_usage }}</h2>
		<table class="x_table">
			<thead><tr><th>{{ $lang->lodging_coupon }}</th><th class="nowr">{{ $lang->lodging_coupon_code }}</th><th class="nowr">{{ $lang->lodging_coupon_used }}</th><th class="nowr">{{ $lang->lodging_coupon_period }}</th></tr></thead>
			<tbody>
				@foreach ($top_coupons as $cp)
				<tr>
					<td>{{ $cp->title }} @if ($cp->status !== 'open')<span class="x_label">{{ $lang->lodging_status_closed ?? 'closed' }}</span>@endif</td>
					<td class="nowr">{{ $cp->code }}</td>
					<td class="nowr">{{ $cp->used_count }}@if ((int)$cp->use_limit > 0) / {{ $cp->use_limit }}@endif</td>
					<td class="nowr">{{ ($cp->end_ymd ?? '') !== '' ? zdate($cp->end_ymd, 'Y.m.d') : '-' }}</td>
				</tr>
				@endforeach
				@if (!count($top_coupons))
				<tr><td colspan="4" class="x_text-center">{{ $lang->lodging_no_coupon }}</td></tr>
				@endif
			</tbody>
		</table>
	</section>
</div>

<section class="section">
	<h2>{{ $lang->lodging_admin_recent_bookings }}</h2>
	<table class="x_table x_table-striped">
		<thead>
			<tr>
				<th scope="col" class="nowr">{{ $lang->lodging_booking_code }}</th>
				<th scope="col">{{ $lang->lodging_property }}</th>
				<th scope="col">{{ $lang->lodging_booker_name }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_checkin }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_total_amount }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_sale_status }}</th>
			</tr>
		</thead>
		<tbody>
			@foreach($recent_bookings as $booking)
			<tr>
				<td class="nowr">{{ $booking->booking_code }}</td>
				<td>{{ $prop_titles[(int)$booking->property_srl] ?? '' }}</td>
				<td>{{ $booking->guest_name }}</td>
				<td class="nowr">{{ zdate($booking->checkin_ymd, 'Y.m.d') }}</td>
				<td class="nowr">{{ number_format($booking->total_amount) }}</td>
				<td class="nowr">{{ $lang->{'lodging_status_' . $booking->status} ?? $booking->status }}</td>
			</tr>
			@endforeach
			@if(!count($recent_bookings))
			<tr><td colspan="6" class="x_text-center">{{ $lang->lodging_no_booking }}</td></tr>
			@endif
		</tbody>
	</table>
</section>
