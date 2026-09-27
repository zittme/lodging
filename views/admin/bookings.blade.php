@include('_tabs')

<section class="section">
	<h2>{{ $property->title }} — {{ $lang->lodging_admin_bookings }}</h2>

	<form action="./" method="get" style="margin-bottom:12px">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispLodgingAdminBookings" />
		<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
		<span class="x_input-append">
			<select name="status">
				<option value="">{{ $lang->lodging_sale_status }}</option>
				@foreach(['hold', 'confirmed', 'canceled', 'completed', 'noshow', 'expired'] as $ldg_st)
				<option value="{{ $ldg_st }}" @if($status === $ldg_st) selected @endif>{{ $lang->{'lodging_status_' . $ldg_st} }}</option>
				@endforeach
			</select>
			<input type="text" name="start_ymd" value="{{ Context::get('start_ymd') }}" placeholder="20260901" size="9" maxlength="8" />
			<input type="text" name="end_ymd" value="{{ Context::get('end_ymd') }}" placeholder="20260930" size="9" maxlength="8" />
			<input type="text" name="search_keyword" value="{{ $search_keyword }}" placeholder="{{ $lang->lodging_booker_name }} / {{ $lang->lodging_booking_code }}" />
			<button type="submit" class="x_btn">{{ $lang->lodging_search }}</button>
		</span>
	</form>

	<table class="x_table x_table-striped">
		<thead>
			<tr>
				<th scope="col" class="nowr">{{ $lang->lodging_booking_code }}</th>
				<th scope="col">{{ $lang->lodging_booker_name }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_room_type }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_checkin }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_total_amount }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_pay_method }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_assigned_room }}</th>
				<th scope="col" class="nowr"></th>
			</tr>
		</thead>
		<tbody>
			@foreach($booking_list as $booking)
			<tr>
				<td class="nowr">{{ $booking->booking_code }}<br /><span class="x_help-inline">{{ $lang->{'lodging_status_' . $booking->status} }}</span></td>
				<td>{{ $booking->guest_name }}<br /><span class="x_help-inline">{{ $booking->guest_phone }}</span></td>
				<td class="nowr">{{ $type_names[(int)$booking->room_type_srl] ?? '-' }}<br /><span class="x_help-inline">{{ $booking->stay_type === 'dayuse' ? $lang->lodging_dayuse : $lang->lodging_stay }}</span></td>
				<td class="nowr">{{ zdate($booking->checkin_ymd, 'm.d') }}@if($booking->stay_type === 'stay')~{{ zdate($booking->checkout_ymd, 'm.d') }}@endif</td>
				<td class="nowr">{{ number_format($booking->total_amount) }}</td>
				<td class="nowr">{{ $booking->pay_method === 'prepay' ? $lang->lodging_pay_prepay : $lang->lodging_pay_onsite }}<br /><span class="x_help-inline">{{ $lang->{'lodging_pay_' . $booking->pay_status} ?? $booking->pay_status }}</span></td>
				<td class="nowr">
					@if($booking->status === 'confirmed')
					<form action="./" method="post" class="x_inline">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminBooking" />
						<input type="hidden" name="booking_srl" value="{{ $booking->booking_srl }}" />
						<input type="hidden" name="booking_action" value="assign" />
						<span class="x_input-append">
							<select name="assigned_room_srl">
								<option value="0">-</option>
								@foreach($rooms_flat as $room)
								<option value="{{ $room->room_srl }}" @if((int)$booking->assigned_room_srl === (int)$room->room_srl) selected @endif @if((int)$room->room_type_srl !== (int)$booking->room_type_srl) disabled @endif>{{ $room->floor }}F {{ $room->name }}</option>
								@endforeach
							</select>
							<button type="submit" class="x_btn">{{ $lang->cmd_save }}</button>
						</span>
					</form>
					@else
					-
					@endif
				</td>
				<td class="nowr">
					@if(in_array($booking->status, ['confirmed', 'hold'], true))
					<form action="./" method="post" class="x_inline" onsubmit="return ldgBookingConfirm(this)">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminBooking" />
						<input type="hidden" name="booking_srl" value="{{ $booking->booking_srl }}" />
						<input type="hidden" name="full_refund" value="" />
						<input type="hidden" name="reason" value="" />
						<span class="x_input-append">
							<select name="booking_action">
								@if($booking->status === 'confirmed')<option value="complete">{{ $lang->lodging_action_complete }}</option>
								@if($booking->pay_method === 'onsite' && $booking->pay_status === 'pending')
								<option value="paid">{{ $lang->lodging_action_paid }}</option>
								@endif
								<option value="noshow">{{ $lang->lodging_action_noshow }}</option>@endif
								<option value="cancel">{{ $lang->lodging_action_cancel }}</option>
							</select>
							<button type="submit" class="x_btn">{{ $lang->lodging_action_run }}</button>
						</span>
					</form>
					@endif
				</td>
			</tr>
			@endforeach
			@if(!count($booking_list))
			<tr><td colspan="8" class="x_text-center">{{ $lang->lodging_no_booking }}</td></tr>
			@endif
		</tbody>
	</table>

	@if($page_navigation)
	<div class="x_page-navigation">
		@foreach($page_navigation as $page_no)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'bookings', 'property_srl', $property->property_srl, 'status', $status, 'search_keyword', $search_keyword, 'page', $page_no) }}" @if((int)$page === (int)$page_no) class="x_active" @endif>{{ $page_no }}</a>
		@endforeach
	</div>
	@endif
</section>

<script>
function ldgBookingConfirm(form) {
	if (form.elements.booking_action.value !== 'cancel') {
		return true;
	}
	if (!confirm('{{ $lang->lodging_admin_cancel_confirm }}')) {
		return false;
	}
	form.elements.full_refund.value = confirm('{{ $lang->lodging_admin_full_refund_ask }}') ? 'Y' : '';
	var reason = prompt('{{ $lang->lodging_cancel_reason_ask }}', '');
	if (reason === null) {
		return false;
	}
	form.elements.reason.value = reason;
	return true;
}
</script>
