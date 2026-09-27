@include('_tabs')

<section class="section">
	<h2>{{ $lang->lodging_coupon }}</h2>

	<table class="x_table x_table-striped">
		<thead>
			<tr>
				<th scope="col">{{ $lang->lodging_coupon }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_coupon_code }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_discount }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_coupon_period }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_coupon_used }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_sale_status }}</th>
				<th scope="col" class="nowr"></th>
			</tr>
		</thead>
		<tbody>
			@foreach($coupon_list as $coupon)
			<tr>
				<td>{{ $coupon->title }}</td>
				<td class="nowr"><code>{{ $coupon->code ?: '-' }}</code></td>
				<td class="nowr">
					@if($coupon->discount_type === 'rate')
					{{ $coupon->discount_value }}%@if($coupon->max_discount > 0) ({{ $lang->lodging_max_discount_short }} {{ number_format($coupon->max_discount) }})@endif
					@else
					{{ number_format($coupon->discount_value) }}
					@endif
				</td>
				<td class="nowr">{{ $coupon->start_ymd ? zdate($coupon->start_ymd, 'y.m.d') : '' }}~{{ $coupon->end_ymd ? zdate($coupon->end_ymd, 'y.m.d') : '' }}</td>
				<td class="nowr">{{ $coupon->used_count }}@if($coupon->use_limit > 0)/{{ $coupon->use_limit }}@endif</td>
				<td class="nowr">{{ $coupon->status === 'open' ? $lang->lodging_status_open : $lang->lodging_status_closed }}</td>
				<td class="nowr">
					<form action="./" method="post" onsubmit="return confirm('{{ $lang->lodging_delete_coupon_confirm }}')">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminDeleteCoupon" />
						<input type="hidden" name="coupon_srl" value="{{ $coupon->coupon_srl }}" />
						<button type="submit" class="x_btn x_btn-danger">{{ $lang->cmd_delete }}</button>
					</form>
				</td>
			</tr>
			@endforeach
			@if(!count($coupon_list))
			<tr><td colspan="7" class="x_text-center">{{ $lang->lodging_no_coupon }}</td></tr>
			@endif
		</tbody>
	</table>
</section>

<section class="section">
	<h3>{{ $lang->lodging_coupon_add }}</h3>

	<form action="./" method="post" class="x_form-horizontal">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminInsertCoupon" />
		<input type="hidden" name="coupon_srl" value="0" />

		<div class="x_control-group">
			<label class="x_control-label" for="ldg_cp_module">{{ $lang->lodging_property }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<select id="ldg_cp_module" name="target_module_srl">
						@foreach($instance_list as $instance)
						<option value="{{ $instance->module_srl }}">{{ $instance->browser_title ?: $instance->mid }}</option>
						@endforeach
					</select>
					<select name="property_srl">
						<option value="0">{{ $lang->lodging_coupon_all_properties }}</option>
						@foreach($property_list as $property)
						<option value="{{ $property->property_srl }}">{{ $property->title }}</option>
						@endforeach
					</select>
				</span>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label" for="ldg_cp_title">{{ $lang->lodging_coupon }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="text" id="ldg_cp_title" name="title" required />
					<input type="text" name="code" placeholder="{{ $lang->lodging_coupon_code }}" />
				</span>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_discount }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<select name="discount_type">
						<option value="amount">{{ $lang->lodging_discount_amount }}</option>
						<option value="rate">{{ $lang->lodging_discount_rate }}</option>
					</select>
					<input type="number" name="discount_value" value="0" min="0" />
					<input type="number" name="max_discount" value="0" min="0" placeholder="{{ $lang->lodging_max_discount_short }}" />
					<input type="number" name="min_amount" value="0" min="0" placeholder="{{ $lang->lodging_min_amount_short }}" />
				</span>
			</div>
		</div>
		<div class="x_control-group">
			<label class="x_control-label">{{ $lang->lodging_coupon_period }}</label>
			<div class="x_controls">
				<span class="x_input-append">
					<input type="text" name="start_ymd" placeholder="20260901" size="9" maxlength="8" />
					<input type="text" name="end_ymd" placeholder="20261231" size="9" maxlength="8" />
					<select name="apply_to">
						<option value="all">{{ $lang->lodging_stay }}+{{ $lang->lodging_dayuse }}</option>
						<option value="stay">{{ $lang->lodging_stay }}</option>
						<option value="dayuse">{{ $lang->lodging_dayuse }}</option>
					</select>
					<input type="number" name="use_limit" value="0" min="0" placeholder="{{ $lang->lodging_use_limit_short }}" />
					<input type="number" name="per_member_limit" value="1" min="0" />
				</span>
				<p class="x_help-block">{{ $lang->lodging_coupon_help }}</p>
				<input type="hidden" name="status" value="open" />
			</div>
		</div>
		<div class="x_controls">
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
		</div>
	</form>
</section>
