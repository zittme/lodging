@include('_tabs')

<section class="section">
	<div class="x_clearfix">
		<h2 class="x_pull-left">{{ $lang->lodging_admin_properties }}</h2>
		<a class="x_btn x_btn-primary x_pull-right" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'property_edit') }}">{{ $lang->lodging_admin_property_add }}</a>
	</div>

	<table class="x_table x_table-striped x_table-hover">
		<thead>
			<tr>
				<th scope="col">{{ $lang->lodging_property }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_property_type }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_room_type }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_pay_method }}</th>
				<th scope="col" class="nowr">{{ $lang->lodging_sale_status }}</th>
				<th scope="col" class="nowr"></th>
			</tr>
		</thead>
		<tbody>
			@foreach($property_list as $property)
			<tr>
				<td><a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'property_edit', 'property_srl', $property->property_srl) }}">{{ $property->title }}</a> <span class="x_label">{{ $property->mid }}</span></td>
				<td class="nowr">{{ $lang->{'lodging_type_' . $property->property_type} ?? $property->property_type }}</td>
				<td class="nowr"><a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'room_types', 'property_srl', $property->property_srl) }}">{{ $property->room_type_count }}</a></td>
				<td class="nowr">
					@if($property->allow_prepay === 'Y')<span class="x_label x_label-info">{{ $lang->lodging_pay_prepay }}</span>@endif
					@if($property->allow_onsite_pay === 'Y')<span class="x_label">{{ $lang->lodging_pay_onsite }}</span>@endif
				</td>
				<td class="nowr">{{ $property->status === 'open' ? $lang->lodging_status_open : $lang->lodging_status_closed }}</td>
				<td class="nowr">
					<form action="./" method="post" onsubmit="return confirm('{{ $lang->lodging_delete_property_confirm }}')">
						<input type="hidden" name="module" value="lodging" />
						<input type="hidden" name="act" value="procLodgingAdminDeleteProperty" />
						<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
						<button type="submit" class="x_btn x_btn-danger">{{ $lang->cmd_delete }}</button>
					</form>
				</td>
			</tr>
			@endforeach
			@if(!count($property_list))
			<tr><td colspan="6" class="x_text-center">{{ $lang->lodging_no_property }}</td></tr>
			@endif
		</tbody>
	</table>
</section>

<section class="section">
	<h2>{{ $lang->lodging_instances }}</h2>
	<p class="x_help-block">{{ $lang->lodging_instance_help }}</p>

	<table class="x_table">
		<thead>
			<tr>
				<th scope="col">mid</th>
				<th scope="col">{{ $lang->browser_title }}</th>
				<th scope="col" class="nowr"></th>
			</tr>
		</thead>
		<tbody>
			@foreach($instance_list as $instance)
			<tr>
				<td>{{ $instance->mid }}</td>
				<td>{{ $instance->browser_title }}</td>
				<td class="nowr"><a class="x_btn" href="{{ getUrl('', 'mid', $instance->mid) }}" target="_blank" rel="noopener">{{ $lang->lodging_console_view_site }}</a></td>
			</tr>
			@endforeach
			@if(!count($instance_list))
			<tr><td colspan="3" class="x_text-center">{{ $lang->lodging_no_instance }}</td></tr>
			@endif
		</tbody>
	</table>

	<form action="./" method="post" style="margin-top:14px">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminInsertInstance" />
		<span class="x_input-append">
			<input type="text" name="instance_mid" placeholder="mid" pattern="[a-zA-Z][a-zA-Z0-9_]*" required />
			<input type="text" name="browser_title" placeholder="{{ $lang->browser_title }}" />
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->lodging_instance_add }}</button>
		</span>
	</form>
</section>
