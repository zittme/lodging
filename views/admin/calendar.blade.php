@include('_tabs')

<section class="section">
	<div class="x_clearfix">
		<h2 class="x_pull-left">{{ $property->title }} — {{ $lang->lodging_admin_calendar }}</h2>
		<div class="x_pull-right">
			<a class="x_btn" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'calendar', 'property_srl', $property->property_srl, 'month', $prev_month) }}">&larr;</a>
			<strong style="margin:0 10px">{{ substr($month, 0, 4) }}.{{ substr($month, 4, 2) }}</strong>
			<a class="x_btn" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'calendar', 'property_srl', $property->property_srl, 'month', $next_month) }}">&rarr;</a>
		</div>
	</div>
	<p class="x_help-block">{{ $lang->lodging_calendar_help }}</p>

	@foreach($room_types as $room_type)
	<h3>{{ $room_type->title }}</h3>
	<div style="overflow-x:auto">
		<table class="x_table" style="min-width:900px">
			<thead>
				<tr>
					@foreach($calendar[$room_type->room_type_srl] as $ldg_ymd => $ldg_day)
					<th scope="col" class="nowr" style="text-align:center; @if(date('w', strtotime($ldg_ymd)) == 0) color:#e5484d; @elseif(date('w', strtotime($ldg_ymd)) == 6) color:#2677e3; @endif">{{ (int)substr($ldg_ymd, 6, 2) }}</th>
					@endforeach
				</tr>
			</thead>
			<tbody>
				<tr>
					@foreach($calendar[$room_type->room_type_srl] as $ldg_ymd => $ldg_day)
					<td style="text-align:center;padding:6px 4px; @if($ldg_day['closed'] === 'Y') background:#fdeaea; @endif">
						<button type="button" class="ldg-day" style="border:0;background:none;cursor:pointer;font-family:inherit;padding:0"
							data-room-type-srl="{{ $room_type->room_type_srl }}"
							data-room-type-title="{{ $room_type->title }}"
							data-ymd="{{ $ldg_ymd }}"
							data-price="{{ $ldg_day['price'] }}"
							data-override="{{ $ldg_day['is_override'] ? 'Y' : 'N' }}"
							data-total="{{ $ldg_day['total'] }}"
							data-closed="{{ $ldg_day['closed'] }}">
							<span style="display:block;font-size:11px; @if($ldg_day['is_override']) color:#7b3ff2;font-weight:700; @endif">{{ number_format($ldg_day['price'] / 10000, ($ldg_day['price'] % 10000) ? 1 : 0) }}{{ $lang->lodging_man_unit }}</span>
							<span style="display:block;font-size:11px;color: {{ $ldg_day['booked'] >= $ldg_day['total'] ? '#e5484d' : '#6b7684' }}">{{ $ldg_day['booked'] }}/{{ $ldg_day['total'] }}</span>
						</button>
					</td>
					@endforeach
				</tr>
			</tbody>
		</table>
	</div>
	@endforeach
	@if(!count($room_types))
	<p class="x_help-block">{{ $lang->lodging_no_property }}</p>
	@endif
</section>

<section class="section" id="ldg-day-editor" hidden>
	<h3 id="ldg-day-title"></h3>
	<form action="./" method="post" class="x_form-horizontal">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminSaveDay" />
		<input type="hidden" name="room_type_srl" value="" />
		<input type="hidden" name="ymd" value="" />

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_day_price">{{ $lang->lodging_override_price }}</label>
			<div class="x_controls">
				<input type="number" id="ldg_day_price" name="price_stay" min="0" step="1000" />
				<p class="x_help-block">{{ $lang->lodging_override_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_day_price_dayuse">{{ $lang->lodging_override_price_dayuse }}</label>
			<div class="x_controls">
				<input type="number" id="ldg_day_price_dayuse" name="price_dayuse" min="0" step="1000" value="0" />
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_day_total">{{ $lang->lodging_total_override }}</label>
			<div class="x_controls">
				<input type="number" id="ldg_day_total" name="total_override" min="0" />
				<p class="x_help-block">{{ $lang->lodging_total_override_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_closed }}</label>
			<div class="x_controls">
				<label class="x_inline"><input type="checkbox" name="closed" value="Y" id="ldg_day_closed" /> {{ $lang->lodging_closed_help }}</label>
			</div>
		</div>
		<div class="x_controls">
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
		</div>
	</form>
</section>

<script>
(function() {
	var editor = document.getElementById('ldg-day-editor');
	var form = editor.querySelector('form');
	var title = document.getElementById('ldg-day-title');

	document.querySelectorAll('.ldg-day').forEach(function(button) {
		button.addEventListener('click', function() {
			var d = button.dataset;
			form.elements.room_type_srl.value = d.roomTypeSrl;
			form.elements.ymd.value = d.ymd;
			form.elements.price_stay.value = d.override === 'Y' ? d.price : 0;
			form.elements.total_override.value = 0;
			form.elements.closed.checked = d.closed === 'Y';
			title.textContent = d.roomTypeTitle + ' · ' + d.ymd.slice(0, 4) + '.' + d.ymd.slice(4, 6) + '.' + d.ymd.slice(6, 8);
			editor.hidden = false;
			editor.scrollIntoView({ behavior: 'smooth' });
		});
	});
})();
</script>
