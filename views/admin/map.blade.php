@include('_tabs')

<section class="section">
	<h2>{{ $lang->lodging_map_links }}</h2>

	<form action="./" method="post" class="x_form-horizontal">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminInsertMapConfig" />

		<div class="x_control-group">
			<label class="x_control-label" for="ldc_map_mode">{{ $lang->lodging_map_mode }}</label>
			<div class="x_controls">
				<select id="ldc_map_mode" name="map_mode">
					@foreach(['auto', 'kakao', 'naver', 'google', 'osm', 'multi'] as $ldc_mode)
					<option value="{{ $ldc_mode }}" @selected($map_config->map_mode === $ldc_mode)>{{ $lang->{'lodging_map_' . $ldc_mode} }}</option>
					@endforeach
				</select>
				<p class="x_help-block">{{ $lang->about_lodging_map_mode }}</p>
			</div>
		</div>

		<div class="x_control-group" id="ldc_map_multi" @if($map_config->map_mode !== 'multi') hidden @endif>
			<span class="x_control-label">{{ $lang->lodging_map_services }}</span>
			<div class="x_controls">
				@foreach(['kakao', 'naver', 'google', 'osm'] as $ldc_service)
				<label class="x_inline"><input type="checkbox" name="map_services[]" value="{{ $ldc_service }}" @checked(in_array($ldc_service, $map_config->map_services, true)) /> {{ $lang->{'lodging_map_' . $ldc_service} }}</label>
				@endforeach
			</div>
		</div>

		<div class="x_control-group">
			<span class="x_control-label"></span>
			<div class="x_controls">
				<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
			</div>
		</div>
	</form>
</section>

<script>
document.getElementById('ldc_map_mode').addEventListener('change', function () {
	document.getElementById('ldc_map_multi').hidden = this.value !== 'multi';
});
</script>
