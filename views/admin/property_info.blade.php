@include('_tabs')

<section class="section">
	<h2>{{ $property ? $property->title : $lang->lodging_admin_property_add }}</h2>

	@if(!$property && !count($instance_list))
	<div class="x_alert x_alert-warning">{{ $lang->msg_select_instance }}</div>
	@endif

	<form action="./" method="post" enctype="multipart/form-data" class="x_form-horizontal" id="ldg-property-form">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminInsertProperty" />
		<input type="hidden" name="property_srl" value="{{ $property ? $property->property_srl : 0 }}" />
		<input type="hidden" name="cancel_rules_json" id="ldg-rules-json" value="" />

		@if(!$property)
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_instance">{{ $lang->lodging_property }} mid</label>
			<div class="x_controls">
				<select id="ldg_instance" name="target_module_srl">
					@foreach($instance_list as $instance)
					<option value="{{ $instance->module_srl }}">{{ $instance->browser_title ?: $instance->mid }} ({{ $instance->mid }})</option>
					@endforeach
				</select>
			</div>
		</div>
		@endif

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_title">{{ $lang->lodging_property }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_title" name="title" value="{{ $property ? $property->title : '' }}" class="x_full-width" required />
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_type">{{ $lang->lodging_property_type }}</label>
			<div class="x_controls">
				<select id="ldg_type" name="property_type">
					@foreach(['motel', 'hotel', 'pension', 'guesthouse', 'resort'] as $ldg_t)
					<option value="{{ $ldg_t }}" @if($property && $property->property_type === $ldg_t) selected @endif>{{ $lang->{'lodging_type_' . $ldg_t} }}</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_amenities }}</label>
			<div class="x_controls">
				@php
				$ldg_has = $property ? \Zittme\Modules\Lodging\Models\Amenity::parse($property->amenities ?? '') : [];
				@endphp
				@foreach(\Zittme\Modules\Lodging\Models\Amenity::KEYS as $ldg_a)
				<label class="x_checkbox x_inline" style="margin:0 12px 6px 0">
					<input type="checkbox" name="amenities[]" value="{{ $ldg_a }}" @if(in_array($ldg_a, $ldg_has, true)) checked @endif />
					{{ $lang->{'lodging_amenity_' . $ldg_a} ?? $ldg_a }}
				</label>
				@endforeach
				<p class="x_help-block">{{ $lang->lodging_amenities_help }}</p>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_summary">{{ $lang->lodging_summary }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_summary" name="summary" value="{{ $property ? $property->summary : '' }}" class="x_full-width" maxlength="250" />
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_description">{{ $lang->lodging_intro }}</label>
			<div class="x_controls">
				<textarea id="ldg_description" name="description" rows="5" class="x_full-width">{{ $property ? $property->description : '' }}</textarea>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_address">{{ $lang->lodging_address }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_address" name="address" value="{{ $property ? $property->address : '' }}" class="x_full-width" />
				<input type="text" name="address_detail" value="{{ $property ? $property->address_detail : '' }}" class="x_full-width" style="margin-top:6px" />
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_notify_email">{{ $lang->lodging_notify_email }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_notify_email" name="notify_email" value="{{ $property ? $property->notify_email : '' }}" class="x_full-width" />
				<p class="x_help-block">{{ $lang->lodging_notify_email_help }}</p>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_phone">{{ $lang->lodging_phone }}</label>
			<div class="x_controls">
				<input type="text" id="ldg_phone" name="phone" value="{{ $property ? $property->phone : '' }}" />
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_checkin_time">{{ $lang->lodging_checkin_time }} / {{ $lang->lodging_checkout_time }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="text" id="ldg_checkin_time" name="checkin_time" value="{{ $property ? $property->checkin_time : '1500' }}" size="5" maxlength="4" />
					<input type="text" name="checkout_time" value="{{ $property ? $property->checkout_time : '1100' }}" size="5" maxlength="4" />
				</span>
				<p class="x_help-block">HHMM</p>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_pay_method }}</label>
			<div class="x_controls">
				<label class="x_inline"><input type="checkbox" name="allow_onsite_pay" value="Y" @if(!$property || $property->allow_onsite_pay === 'Y') checked @endif /> {{ $lang->lodging_allow_onsite }}</label>
				<label class="x_inline"><input type="checkbox" name="allow_prepay" value="Y" @if(!$property || $property->allow_prepay === 'Y') checked @endif /> {{ $lang->lodging_allow_prepay }}</label>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_sale_status }}</label>
			<div class="x_controls">
				<label class="x_inline"><input type="radio" name="status" value="open" @if(!$property || $property->status === 'open') checked @endif /> {{ $lang->lodging_status_open }}</label>
				<label class="x_inline"><input type="radio" name="status" value="closed" @if($property && $property->status === 'closed') checked @endif /> {{ $lang->lodging_status_closed }}</label>
			</div>
		</div>

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_images">{{ $lang->lodging_photo_add }}</label>
			<div class="x_controls">
				<input type="file" id="ldg_images" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" />
				<p class="x_help-block">{{ $lang->lodging_photo_help }}</p>
			</div>
		</div>

		<h3>{{ $lang->lodging_cancel_policy }}</h3>
		<p class="x_help-block">{{ $lang->lodging_no_cancel_rule }}</p>

		<div id="ldg-rule-rows"></div>
		<p><button type="button" class="x_btn" id="ldg-rule-add">+</button></p>

		<div class="x_controls">
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
			<a class="x_btn" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'properties') }}">{{ $lang->cmd_list }}</a>
		</div>
	</form>

	@if($property && count($property_images))
	<h3>{{ $lang->lodging_photos }}</h3>
	<div style="display:flex;gap:10px;flex-wrap:wrap">
		@foreach($property_images as $ldg_image)
		<div style="width:120px">
			<img src="{{ $ldg_image->url }}" alt="" style="width:120px;height:84px;object-fit:cover;border-radius:8px;border:1px solid var(--ld-line)" />
			<form action="./" method="post" onsubmit="return confirm('{{ $lang->lodging_photo_delete_confirm }}')">
				<input type="hidden" name="module" value="lodging" />
				<input type="hidden" name="act" value="procLodgingAdminDeleteImage" />
				<input type="hidden" name="file_srl" value="{{ $ldg_image->file_srl }}" />
				<input type="hidden" name="target_srl" value="{{ $property->property_srl }}" />
				<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
				<input type="hidden" name="back" value="property_edit" />
				<button type="submit" class="x_btn" style="width:100%;margin-top:4px;box-sizing:border-box">{{ $lang->cmd_delete }}</button>
			</form>
		</div>
		@endforeach
	</div>
	@endif
</section>

<script>
(function() {
	var rows = document.getElementById('ldg-rule-rows');
	var jsonInput = document.getElementById('ldg-rules-json');
	var initial = {!! $cancel_rules_json !!};

	function buildRow(rule) {
		var box = document.createElement('div');
		box.className = 'ldg-rule-row';
		box.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px';
		box.innerHTML = '{{ $lang->lodging_rule_checkin }} <input type="number" class="ldg-r-days" min="0" style="width:70px" value="' + (parseInt(rule.days_before, 10) || 0) + '" /> {{ $lang->lodging_rule_days_before }} '
			+ '<input type="number" class="ldg-r-rate" min="0" max="100" style="width:70px" value="' + (parseInt(rule.refund_rate, 10) || 0) + '" /> {{ $lang->lodging_rule_refund }} '
			+ '<button type="button" class="x_btn" data-action="del">{{ $lang->cmd_delete }}</button>';
		return box;
	}

	function render(list) {
		rows.innerHTML = '';
		list.forEach(function(rule) { rows.appendChild(buildRow(rule)); });
	}

	rows.addEventListener('click', function(event) {
		var button = event.target.closest('button[data-action="del"]');
		if (button) { button.closest('.ldg-rule-row').remove(); }
	});

	document.getElementById('ldg-rule-add').addEventListener('click', function() {
		rows.appendChild(buildRow({ days_before: 0, refund_rate: 0 }));
	});

	document.getElementById('ldg-property-form').addEventListener('submit', function() {
		var result = [];
		rows.querySelectorAll('.ldg-rule-row').forEach(function(box) {
			result.push({
				days_before: parseInt(box.querySelector('.ldg-r-days').value, 10) || 0,
				refund_rate: parseInt(box.querySelector('.ldg-r-rate').value, 10) || 0
			});
		});
		jsonInput.value = JSON.stringify(result);
	});

	render(initial);
})();
</script>
