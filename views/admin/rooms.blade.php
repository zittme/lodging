@include('_tabs')

<section class="section">
	<h2>{{ $property->title }} — {{ $lang->lodging_admin_rooms }}</h2>
	<p class="x_help-block">{{ $lang->lodging_rooms_help }}</p>

	<form action="./" method="post" id="ldg-rooms-form">
		<input type="hidden" name="module" value="lodging" />
		<input type="hidden" name="act" value="procLodgingAdminSaveRooms" />
		<input type="hidden" name="property_srl" value="{{ $property->property_srl }}" />
		<input type="hidden" name="rooms_json" id="ldg-rooms-json" value="" />

		<div id="ldg-floors"></div>

		<p style="margin-top:12px">
			<button type="button" class="x_btn" id="ldg-floor-add">+ {{ $lang->lodging_floor_add }}</button>
			<button type="submit" class="x_btn x_btn-primary">{{ $lang->cmd_save }}</button>
		</p>
	</form>
</section>

<style>
.ldg-floor { margin-bottom: 14px; border: 1px solid #d7dce3; border-radius: 8px; background: #fff; }
.ldg-floor-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #eceff3; background: #f7f9fc; }
.ldg-floor-head strong { font-size: 14px; }
.ldg-floor-body { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 14px; }
.ldg-room-cell { display: flex; flex-direction: column; gap: 4px; width: 150px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafbfd; }
.ldg-room-cell input, .ldg-room-cell select { width: 100%; box-sizing: border-box; font-size: 12.5px; }
.ldg-room-cell.is-closed { opacity: .55; }
.ldg-room-cell-actions { display: flex; gap: 4px; }
.ldg-room-cell-actions button { flex: 1; padding: 2px 0; font-size: 11px; }
</style>

<script>
(function() {
	var floorsBox = document.getElementById('ldg-floors');
	var jsonInput = document.getElementById('ldg-rooms-json');
	var initial = {!! $grid_json !!};
	var TYPES = {!! $types_json !!};
	var LANG = {
		copy: '{{ $lang->lodging_floor_copy }}',
		del: '{{ $lang->cmd_delete }}',
		roomAdd: '+ {{ $lang->lodging_room_add }}',
		floorLabel: '{{ $lang->lodging_floor_label }}',
		closed: '{{ $lang->lodging_status_closed }}'
	};

	function typeSelect(value) {
		var select = document.createElement('select');
		select.className = 'ldg-c-type';
		TYPES.forEach(function(type) {
			var option = document.createElement('option');
			option.value = type.srl;
			option.textContent = type.title;
			if (type.srl === value) { option.selected = true; }
			select.appendChild(option);
		});
		return select;
	}

	function buildCell(room) {
		var cell = document.createElement('div');
		cell.className = 'ldg-room-cell' + (room.status === 'closed' ? ' is-closed' : '');

		var name = document.createElement('input');
		name.type = 'text';
		name.className = 'ldg-c-name';
		name.value = room.name || '';
		name.placeholder = '301';
		cell.appendChild(name);
		cell.appendChild(typeSelect(room.room_type_srl || (TYPES[0] ? TYPES[0].srl : 0)));

		var actions = document.createElement('div');
		actions.className = 'ldg-room-cell-actions';
		var closedBtn = document.createElement('button');
		closedBtn.type = 'button';
		closedBtn.className = 'x_btn ldg-c-closed';
		closedBtn.dataset.closed = room.status === 'closed' ? 'Y' : 'N';
		closedBtn.textContent = LANG.closed;
		closedBtn.addEventListener('click', function() {
			var on = closedBtn.dataset.closed === 'Y' ? 'N' : 'Y';
			closedBtn.dataset.closed = on;
			cell.classList.toggle('is-closed', on === 'Y');
		});
		var delBtn = document.createElement('button');
		delBtn.type = 'button';
		delBtn.className = 'x_btn';
		delBtn.textContent = LANG.del;
		delBtn.addEventListener('click', function() { cell.remove(); });
		actions.appendChild(closedBtn);
		actions.appendChild(delBtn);
		cell.appendChild(actions);

		return cell;
	}

	function buildFloor(floorNo, rooms) {
		var floor = document.createElement('div');
		floor.className = 'ldg-floor';

		var head = document.createElement('div');
		head.className = 'ldg-floor-head';
		var label = document.createElement('strong');
		var floorInput = document.createElement('input');
		floorInput.type = 'number';
		floorInput.className = 'ldg-f-no';
		floorInput.value = floorNo;
		floorInput.style.width = '60px';
		label.appendChild(floorInput);
		label.appendChild(document.createTextNode(' ' + LANG.floorLabel));
		head.appendChild(label);

		var addBtn = document.createElement('button');
		addBtn.type = 'button';
		addBtn.className = 'x_btn';
		addBtn.textContent = LANG.roomAdd;
		var copyBtn = document.createElement('button');
		copyBtn.type = 'button';
		copyBtn.className = 'x_btn';
		copyBtn.textContent = LANG.copy;
		var delBtn = document.createElement('button');
		delBtn.type = 'button';
		delBtn.className = 'x_btn x_btn-danger';
		delBtn.textContent = LANG.del;
		head.appendChild(addBtn);
		head.appendChild(copyBtn);
		head.appendChild(delBtn);
		floor.appendChild(head);

		var body = document.createElement('div');
		body.className = 'ldg-floor-body';
		rooms.forEach(function(room) { body.appendChild(buildCell(room)); });
		floor.appendChild(body);

		addBtn.addEventListener('click', function() {
			body.appendChild(buildCell({}));
		});
		delBtn.addEventListener('click', function() { floor.remove(); });
		copyBtn.addEventListener('click', function() {
			// 층 복제: 같은 구조로 한 층 위를 만들고, 호수 앞자리를 새 층으로 치환한다.
			var from = parseInt(floorInput.value, 10) || 1;
			var to = from + 1;
			var rooms = [];
			body.querySelectorAll('.ldg-room-cell').forEach(function(cell) {
				var name = cell.querySelector('.ldg-c-name').value;
				var renamed = name.replace(new RegExp('^' + from), String(to));
				if (renamed === name && name !== '') { renamed = String(to) + name.slice(String(from).length); }
				rooms.push({
					name: renamed,
					room_type_srl: parseInt(cell.querySelector('.ldg-c-type').value, 10) || 0,
					status: cell.querySelector('.ldg-c-closed').dataset.closed === 'Y' ? 'closed' : 'open'
				});
			});
			floorsBox.insertBefore(buildFloor(to, rooms), floor);
		});

		return floor;
	}

	function render() {
		var byFloor = {};
		initial.forEach(function(room) {
			(byFloor[room.floor] = byFloor[room.floor] || []).push(room);
		});
		var floors = Object.keys(byFloor).map(Number).sort(function(a, b) { return b - a; });
		if (!floors.length) { floors = [1]; byFloor[1] = []; }
		floors.forEach(function(floorNo) {
			floorsBox.appendChild(buildFloor(floorNo, byFloor[floorNo] || []));
		});
	}

	document.getElementById('ldg-floor-add').addEventListener('click', function() {
		floorsBox.insertBefore(buildFloor(1, []), floorsBox.firstChild);
	});

	document.getElementById('ldg-rooms-form').addEventListener('submit', function() {
		var result = [];
		floorsBox.querySelectorAll('.ldg-floor').forEach(function(floor) {
			var floorNo = parseInt(floor.querySelector('.ldg-f-no').value, 10) || 1;
			floor.querySelectorAll('.ldg-room-cell').forEach(function(cell) {
				result.push({
					floor: floorNo,
					name: cell.querySelector('.ldg-c-name').value,
					room_type_srl: parseInt(cell.querySelector('.ldg-c-type').value, 10) || 0,
					status: cell.querySelector('.ldg-c-closed').dataset.closed === 'Y' ? 'closed' : 'open'
				});
			});
		});
		jsonInput.value = JSON.stringify(result);
	});

	render();
})();
</script>
