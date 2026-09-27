@include('_tabs')

<section class="section">
	<h2>{{ $property->title }} — {{ $lang->lodging_room_type }}</h2>

	<table class="x_table x_table-striped">
		<thead>
			<tr>
				<th scope="col">{{ $lang->lodging_room_type }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_total_rooms }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_grid_count }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_stay }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_dayuse }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_sale_status }}</th>
				<th scope="col" class="nowr"></th>
			</tr>
		</thead>
		<tbody>
			@foreach($room_types as $room_type)
			<tr>
				<td>
					{{ $room_type->title }} <span class="x_help-inline">{{ sprintf($lang->lodging_std_person, $room_type->std_person) }} / {{ sprintf($lang->lodging_max_person, $room_type->max_person) }}</span>
					@if(count($room_type->images))
					<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
						@foreach($room_type->images as $ldg_image)
						<div style="position:relative;width:64px">
							<img src="{{ $ldg_image->url }}" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--ld-line)" />
							<form action="./" method="post" onsubmit="return confirm('{{ $lang->lodging_photo_delete_confirm }}')">
								<input type="hidden" name="module" value="lodging" />
								<input type="hidden" name="act" value="procLodgingAdminDeleteImage" />
								<input type="hidden" name="file_srl" value="{{ $ldg_image->file_srl }}" />
								<input type="hidden" name="target_srl" value="{{ $room_type->room_type_srl }}" />
								<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
								<input type="hidden" name="back" value="room_types" />
								<button type="submit" class="x_btn" style="width:100%;padding:2px 0;font-size:11px">{{ $lang->cmd_delete }}</button>
							</form>
						</div>
						@endforeach
					</div>
					@endif
				</td>
				<td class="nowr">
					{{ $room_type->total_rooms }}
					@if($room_type->grid_count > 0 && $room_type->grid_count != $room_type->total_rooms)
					<span class="x_label x_label-warning" title="{{ $lang->lodging_grid_mismatch }}">!</span>
					@endif
				</td>
				<td class="nowr">{{ $room_type->grid_count }}</td>
				<td class="nowr">{{ number_format($room_type->stay_price_weekday) }} / {{ number_format($room_type->stay_price_fri) }} / {{ number_format($room_type->stay_price_sat) }}</td>
				<td class="nowr">
					@if($room_type->use_dayuse === 'Y')
					{{ number_format($room_type->dayuse_price_weekday) }} / {{ number_format($room_type->dayuse_price_fri) }} / {{ number_format($room_type->dayuse_price_sat) }}
					@else
					-
					@endif
				</td>
				<td class="nowr">{{ $room_type->status === 'open' ? $lang->lodging_status_open : $lang->lodging_status_closed }}</td>
				<td class="nowr">
					<button type="button" class="x_btn ldg-edit-type" data-room-type='{{ json_encode(["room_type_srl" => (int)$room_type->room_type_srl, "title" => $room_type->title, "description" => $room_type->description, "total_rooms" => (int)$room_type->total_rooms, "std_person" => (int)$room_type->std_person, "max_person" => (int)$room_type->max_person, "stay_price_weekday" => (int)$room_type->stay_price_weekday, "stay_price_fri" => (int)$room_type->stay_price_fri, "stay_price_sat" => (int)$room_type->stay_price_sat, "use_dayuse" => $room_type->use_dayuse, "dayuse_hours" => (int)$room_type->dayuse_hours, "dayuse_open" => $room_type->dayuse_open, "dayuse_close" => $room_type->dayuse_close, "dayuse_price_weekday" => (int)$room_type->dayuse_price_weekday, "dayuse_price_fri" => (int)$room_type->dayuse_price_fri, "dayuse_price_sat" => (int)$room_type->dayuse_price_sat, "min_nights" => (int)($room_type->min_nights ?: 1), "max_nights" => (int)($room_type->max_nights ?: 30), "extra_person_price" => (int)$room_type->extra_person_price, "long_stay_nights" => (int)$room_type->long_stay_nights, "long_stay_rate" => (int)$room_type->long_stay_rate, "status" => $room_type->status, "list_order" => (int)$room_type->list_order], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) }}'>{{ $lang->cmd_modify }}</button>
					<form action="./" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->lodging_delete_type_confirm }}')">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminDeleteRoomType" />
						<input type="hidden" name="room_type_srl" value="{{ $room_type->room_type_srl }}" />
						<button type="submit" class="x_btn x_btn-danger">{{ $lang->cmd_delete }}</button>
					</form>
				</td>
			</tr>
			@endforeach
			@if(!count($room_types))
			<tr><td colspan="7" class="x_text-center">{{ $lang->lodging_no_property }}</td></tr>
			@endif
		</tbody>
	</table>
</section>

<section class="section">
	<h3 id="ldg-type-form-title">{{ $lang->lodging_room_type_add }}</h3>

	<form action="./" method="post" enctype="multipart/form-data" class="x_form-horizontal" id="ldg-type-form">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminInsertRoomType" />
		<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
		<input type="hidden" name="room_type_srl" value="0" />

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_title">{{ $lang->lodging_room_type }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_rt_title" name="title" class="x_full-width" required />
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_desc">{{ $lang->lodging_intro }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_rt_desc" name="description" class="x_full-width" />
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_total">{{ $lang->lodging_total_rooms }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="number" id="ldg_rt_total" name="total_rooms" value="1" min="1" />
					<input type="number" name="std_person" value="2" min="1" title="{{ $lang->lodging_person }}" />
					<input type="number" name="max_person" value="2" min="1" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_total_rooms }} / {{ $lang->lodging_std_person_label }} / {{ $lang->lodging_max_person_label }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_stay }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="number" name="stay_price_weekday" value="0" min="0" step="1000" />
					<input type="number" name="stay_price_fri" value="0" min="0" step="1000" />
					<input type="number" name="stay_price_sat" value="0" min="0" step="1000" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_price_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_min_nights">{{ $lang->lodging_nights_limit }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="number" id="ldg_rt_min_nights" name="min_nights" value="1" min="1" title="{{ $lang->lodging_min_nights_label }}" />
					<input type="number" name="max_nights" value="30" min="1" title="{{ $lang->lodging_max_nights_label }}" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_nights_limit_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_extra_person">{{ $lang->lodging_extra_person_price }}</label>
			<div class="x_controls">
				<input type="number" id="ldg_rt_extra_person" name="extra_person_price" value="0" min="0" step="1000" />
				<p class="x_help-block">{{ $lang->lodging_extra_person_price_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_long_nights">{{ $lang->lodging_long_stay }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="number" id="ldg_rt_long_nights" name="long_stay_nights" value="0" min="0" title="{{ $lang->lodging_long_stay_nights_label }}" />
					<input type="number" name="long_stay_rate" value="0" min="0" max="100" title="{{ $lang->lodging_long_stay_rate_label }}" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_long_stay_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_dayuse }}</label>
			<div class="x_controls">
				<label class="x_inline"><input type="checkbox" name="use_dayuse" value="Y" id="ldg_rt_dayuse" /> {{ $lang->lodging_use_dayuse }}</label>
				<span class="x_input-append" style="margin-left:8px">
					<input type="number" name="dayuse_hours" value="4" min="1" title="{{ $lang->lodging_dayuse_hours_label }}" />
					<input type="text" name="dayuse_open" value="1200" size="5" maxlength="4" />
					<input type="text" name="dayuse_close" value="2200" size="5" maxlength="4" />
				</span>
				<span class="x_input-append" style="margin-left:8px">
					<input type="number" name="dayuse_price_weekday" value="0" min="0" step="1000" />
					<input type="number" name="dayuse_price_fri" value="0" min="0" step="1000" />
					<input type="number" name="dayuse_price_sat" value="0" min="0" step="1000" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_dayuse_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_rt_images">{{ $lang->lodging_photo_add }}</label>
			<div class="x_controls">
				<input type="file" id="ldg_rt_images" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" />
				<p class="x_help-block">{{ $lang->lodging_photo_help }}</p>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_sale_status }}</label>
			<div class="x_controls">
				<label class="x_inline"><input type="radio" name="status" value="open" checked /> {{ $lang->lodging_status_open }}</label>
				<label class="x_inline"><input type="radio" name="status" value="closed" /> {{ $lang->lodging_status_closed }}</label>
				<input type="hidden" name="list_order" value="0" />
			</div>
		</div>
		<div class="x_controls">
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
			<button type="button" class="x_btn" id="ldg-type-reset" hidden>{{ $lang->cmd_cancel }}</button>
		</div>
	</form>
</section>

<script>
(function() {
	var form = document.getElementById('ldg-type-form');
	var title = document.getElementById('ldg-type-form-title');
	var resetBtn = document.getElementById('ldg-type-reset');
	var addLabel = title.textContent;

	function fill(data) {
		Object.keys(data).forEach(function(key) {
			var field = form.elements[key];
			if (!field) { return; }
			if (field.type === 'checkbox') { field.checked = data[key] === 'Y'; return; }
			if (field.length && field[0] && field[0].type === 'radio') {
				Array.prototype.forEach.call(field, function(radio) { radio.checked = radio.value === data[key]; });
				return;
			}
			field.value = data[key];
		});
	}

	document.querySelectorAll('.ldg-edit-type').forEach(function(button) {
		button.addEventListener('click', function() {
			fill(JSON.parse(button.dataset.roomType));
			title.textContent = form.elements.title.value;
			resetBtn.hidden = false;
			form.scrollIntoView({ behavior: 'smooth' });
		});
	});

	resetBtn.addEventListener('click', function() {
		form.reset();
		form.elements.room_type_srl.value = 0;
		title.textContent = addLabel;
		resetBtn.hidden = true;
	});
})();
</script>
