{{-- 숙박 전용 콘솔 셸. 다크 사이드바와 웜 그레이 본문에 강조색은 인디고를 쓴다. --}}
<style>
@font-face {
	font-family: 'Pretendard';
	src: url('{{ \RX_BASEURL }}common/fonts/PretendardVariable.woff2') format('woff2-variations');
	font-weight: 45 920;
	font-display: swap;
}

:root {
	--ld-accent: #5158cf;
	--ld-accent-soft: #eceefb;
	--ld-ink: #262c33;
	--ld-sub: #6d7681;
	--ld-line: #e6e8ea;
	--ld-bg: #f6f7f7;
	--ld-side: #23262f;
	--ld-side-ink: #aeb6bf;
	--ld-font: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
}

html, body { margin: 0; padding: 0; background: var(--ld-bg); }
body, body * { font-family: var(--ld-font); font-style: normal; }
body { -webkit-font-smoothing: antialiased; color: var(--ld-ink); font-size: 14.5px; line-height: 1.6; }

.ldc-side { position: fixed; top: 0; left: 0; bottom: 0; width: 240px; box-sizing: border-box; padding: 26px 14px 16px; background: var(--ld-side); z-index: 100; overflow-y: auto; display: flex; flex-direction: column; scrollbar-width: thin; scrollbar-color: #3d4650 transparent; }
.ldc-side::-webkit-scrollbar { width: 6px; }
.ldc-side::-webkit-scrollbar-thumb { border-radius: 999px; background: #3d4650; }
html { scrollbar-width: thin; scrollbar-color: #ccd2d6 transparent; }

.ldc-logo { display: flex; align-items: baseline; gap: 7px; padding: 0 12px 22px; font-size: 17px; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
.ldc-logo span { font-size: 12.5px; font-weight: 600; color: #7d8894; }

.ldc-nav { flex: 1; }
.ldc-nav a { position: relative; display: flex; align-items: center; gap: 10px; padding: 8px 14px; margin-bottom: 1px; border-radius: 9px; font-size: 14.5px; font-weight: 500; color: var(--ld-side-ink) !important; text-decoration: none !important; transition: background .13s, color .13s; }
.ldc-nav a:hover { background: rgba(255,255,255,.06); color: #fff !important; }
.ldc-nav a.is-active { background: rgba(255,255,255,.1); color: #fff !important; font-weight: 700; }
.ldc-nav a.is-active::before { content: ''; position: absolute; left: -14px; top: 7px; bottom: 7px; width: 3px; border-radius: 0 3px 3px 0; background: var(--ld-accent); }
.ldc-nav a.is-disabled { opacity: .4; pointer-events: none; }
.ldc-nav-sep { margin: 16px 14px 5px; font-size: 11.5px; font-weight: 700; color: #5d6672; letter-spacing: .04em; }
.ldc-side-foot { padding-top: 14px; border-top: 1px solid rgba(255,255,255,.08); }
.ldc-side-foot a { display: block; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: #737d89 !important; text-decoration: none !important; }
.ldc-side-foot a:hover { color: #fff !important; background: rgba(255,255,255,.06); }

.ldc-main { margin-left: 240px; padding: 26px 40px 100px; box-sizing: border-box; min-height: 100vh; }

/* 기존 화면들이 쓰는 x_* 클래스를 콘솔 결로 입힌다 */
.ldc-main section.section { padding: 24px 26px; border: 1px solid var(--ld-line); border-radius: 14px; background: #fff; margin-bottom: 18px; }
.ldc-main h2 { margin: 0 0 18px; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
.ldc-main h3 { margin: 20px 0 12px; font-size: 15px; font-weight: 800; }
.ldc-main .x_help-block, .ldc-main .x_help-inline { font-size: 12.5px; color: var(--ld-sub); }
.ldc-main .x_help-block { margin: 6px 0 12px; }

.ldc-main .x_table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--ld-line); border-radius: 12px; overflow: hidden; }
.ldc-main .x_table th { padding: 12px 14px; background: #fafbfb; font-size: 12.5px; font-weight: 700; color: var(--ld-sub); text-align: left; border-bottom: 1px solid var(--ld-line); white-space: nowrap; }
.ldc-main .x_table td { padding: 12px 14px; font-size: 13.5px; border-bottom: 1px solid #f2f3f4; vertical-align: middle; color: var(--ld-ink); }
.ldc-main .x_table tbody tr:last-child td { border-bottom: 0; }
.ldc-main .x_table tbody tr:hover { background: #fbfcfc; }
.ldc-main .x_text-center { text-align: center; color: var(--ld-sub); }
.ldc-main .nowr { white-space: nowrap; }

.ldc-main .x_btn, .ldc-main a.x_btn { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 7px 14px; border: 1px solid var(--ld-line); border-radius: 9px; background: #fff !important; font-size: 13px; font-weight: 600; font-family: inherit; cursor: pointer; color: var(--ld-ink) !important; text-decoration: none !important; transition: border-color .12s, color .12s; }
.ldc-main .x_btn:hover { border-color: var(--ld-accent); color: var(--ld-accent) !important; }
.ldc-main .x_btn-primary, .ldc-main a.x_btn-primary { background: var(--ld-accent) !important; border-color: var(--ld-accent); color: #fff !important; }
.ldc-main .x_btn-primary:hover, .ldc-main a.x_btn-primary:hover, .ldc-main .x_btn-primary:focus-visible { background: #454bb8 !important; border-color: #454bb8; color: #fff !important; }
.ldc-main .x_btn-danger:hover { border-color: #cd2b31; color: #cd2b31 !important; }

.ldc-main .x_label { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: #f1f3f4; color: var(--ld-sub); }
.ldc-main .x_label-info { background: var(--ld-accent-soft); color: var(--ld-accent); }
.ldc-main .x_label-warning { background: #fdf4e3; color: #a9781b; }
.ldc-main .x_label-important { background: #f6eded; color: #b4544e; }

.ldc-main input[type="text"], .ldc-main input[type="number"], .ldc-main input[type="password"],
.ldc-main select, .ldc-main textarea { box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--ld-line); border-radius: 8px; font-size: 13px; font-family: inherit; background: #fff; color: var(--ld-ink); }
.ldc-main input:focus, .ldc-main select:focus, .ldc-main textarea:focus { outline: none; border-color: var(--ld-accent); box-shadow: 0 0 0 3px rgba(81, 88, 207, .12); }
.ldc-main .x_full-width { width: 100%; }
.ldc-main .x_input-append { display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.ldc-main .x_inline { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; font-size: 13.5px; }

.ldc-main .x_form-horizontal .x_control-group { display: flex; gap: 18px; margin-bottom: 14px; }
.ldc-main .x_control-label { flex: 0 0 170px; padding-top: 8px; font-size: 13px; font-weight: 700; color: #4a525b; margin: 0; }
.ldc-main .x_controls { flex: 1 1 auto; min-width: 0; }
.ldc-main .x_clearfix { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.ldc-main .x_pull-left { margin: 0; }
.ldc-main .x_pull-right { margin-left: auto; }
.ldc-main .x_alert { padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-bottom: 14px; }
.ldc-main .x_alert-warning { background: #fdf4e3; color: #8a6516; border: 1px solid #f3e3bd; }
.ldc-main .x_page-navigation { display: flex; gap: 5px; margin-top: 16px; }
.ldc-main .x_page-navigation a { min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--ld-sub); text-decoration: none; }
.ldc-main .x_page-navigation a:hover { background: #eef0f2; }
.ldc-main .x_page-navigation a.x_active { background: var(--ld-accent); color: #fff; }
.ldc-main .x_page-header { display: none; }

@media (max-width: 900px) {
	.ldc-side { display: none; }
	.ldc-main { margin-left: 0; padding: 18px 14px 80px; }
	.ldc-main .x_form-horizontal .x_control-group { flex-direction: column; gap: 6px; }
	.ldc-main .x_control-label { flex: none; padding-top: 0; }
	.ldc-main .x_table { display: block; overflow-x: auto; }
}
</style>

@php
$ldc_p = Context::get('zmc_current') ?: 'dashboard';
$ldc_prop = (int)Context::get('zmc_property_srl');
if (isset($property) && is_object($property)) { $ldc_prop = (int)$property->property_srl; }
@endphp

<aside class="ldc-side">
	<div class="ldc-logo">{{ $lang->lodging }} <span>Console</span></div>

	<nav class="ldc-nav">
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'dashboard') }}" class="{{ $ldc_p === 'dashboard' ? 'is-active' : '' }}">{{ $lang->lodging_admin_dashboard }}</a>
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'properties') }}" class="{{ in_array($ldc_p, ['properties', 'property_edit'], true) ? 'is-active' : '' }}">{{ $lang->lodging_admin_properties }}</a>
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'coupons') }}" class="{{ $ldc_p === 'coupons' ? 'is-active' : '' }}">{{ $lang->lodging_coupon }}</a>

		<div class="ldc-nav-sep">{{ $lang->lodging_console_property_ops }}</div>
		@foreach(['bookings' => $lang->lodging_admin_bookings, 'calendar' => $lang->lodging_admin_calendar, 'room_types' => $lang->lodging_room_type, 'rooms' => $lang->lodging_admin_rooms, 'reviews' => $lang->lodging_review] as $ldc_key => $ldc_label)
		@if($ldc_prop > 0)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', $ldc_key, 'property_srl', $ldc_prop) }}" class="{{ $ldc_p === $ldc_key ? 'is-active' : '' }}">{{ $ldc_label }}</a>
		@else
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispLodgingConsole', 'p', 'properties') }}" class="is-disabled">{{ $ldc_label }}</a>
		@endif
		@endforeach
		@if($ldc_prop <= 0)
		<div class="ldc-nav-sep">{{ $lang->lodging_console_pick_property }}</div>
		@endif
	</nav>

	<div class="ldc-side-foot">
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', '') }}" target="_blank">{{ $lang->lodging_console_view_site }}</a>
		<a href="{{ getUrl('', 'mid', '', 'module', 'admin', 'act', '') }}" target="_blank">{{ $lang->lodging_console_admin }}</a>
	</div>
</aside>

<div class="ldc-main">
