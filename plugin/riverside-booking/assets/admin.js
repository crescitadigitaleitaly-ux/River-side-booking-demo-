/* Riverside Booking – reception actions (uses the WordPress REST nonce). */
(function () {
	'use strict';
	if (!window.RB) { return; }
	var labels = { confirm: 'Confirm', decline: 'Decline', cancel: 'Cancel', complete: 'Mark completed' };

	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function call(path, body, method) {
		return fetch(RB.api + path, {
			method: method || 'POST', credentials: 'same-origin', cache: 'no-store',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': RB.nonce },
			body: body ? JSON.stringify(body) : undefined
		}).then(function (r) { return r.json().then(function (j) { j.httpStatus = r.status; return j; }, function () { return { ok: false, message: 'Unexpected server response (' + r.status + ').' }; }); },
			function () { return { ok: false, message: 'Network problem. Please try again.' }; });
	}
	function notice(kind, msg) {
		var box = document.querySelector('[data-rb-notice]');
		if (box) { box.innerHTML = '<div class="notice notice-' + kind + ' is-dismissible"><p>' + esc(msg) + '</p></div>'; box.scrollIntoView({ block: 'nearest' }); }
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-rb-act]');
		if (!b) { return; }
		var act = b.getAttribute('data-rb-act'), id = b.getAttribute('data-id');
		if ((act === 'cancel' || act === 'decline') && !window.confirm(labels[act] + ' this appointment? The patient gets a test notification.')) { return; }
		var reasonEl = document.querySelector('[data-rb-reason]');
		var reason = reasonEl ? reasonEl.value : '';
		b.disabled = true; var old = b.textContent; b.textContent = '…';
		call('appointments/' + id + '/' + act, { reason: reason, note: reason }).then(function (j) {
			if (j.ok) {
				notice('success', j.item.reference + ': ' + (labels[act] || act) + ' done. Status is now ' + j.item.status + '. A test notification was created.');
				setTimeout(function () { location.reload(); }, 900);
			} else {
				b.disabled = false; b.textContent = old;
				notice(j.httpStatus === 409 ? 'warning' : 'error', j.message || 'Could not complete the action.');
			}
		});
	});

	// Reschedule panel on the detail page.
	var box = document.querySelector('[data-rb-resched]');
	if (!box) { return; }
	var id = box.getAttribute('data-id'), docSel = box.querySelector('[data-rb-rdoc]');
	var daysEl = box.querySelector('[data-rb-rdays]'), slotsEl = box.querySelector('[data-rb-rslots]'), save = box.querySelector('[data-rb-rsave]');
	var date = '';
	function get(path, params) {
		var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
		return fetch(RB.api + path + '?' + q, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': RB.nonce } }).then(function (r) { return r.json(); });
	}
	function loadDays() {
		daysEl.textContent = 'Loading…'; slotsEl.innerHTML = ''; save.disabled = true;
		get('staff/days', { appointment: id, doctor: docSel.value }).then(function (j) {
			if (!j.ok) { daysEl.textContent = j.message || 'Could not load.'; return; }
			daysEl.innerHTML = j.days.map(function (d) {
				return '<button type="button" class="rb-day" data-date="' + d.date + '"' + (d.free ? '' : ' disabled') + ' title="' + esc(d.free ? d.free + ' free' : d.reason) + '"><span>' + esc(d.dow) + '</span><b>' + esc(d.label) + '</b><span>' + esc(d.free ? d.free + ' free' : d.reason) + '</span></button>';
			}).join('');
		});
	}
	daysEl.addEventListener('click', function (e) {
		var b = e.target.closest('.rb-day'); if (!b || b.disabled) { return; }
		date = b.getAttribute('data-date'); save.disabled = true;
		Array.prototype.forEach.call(daysEl.children, function (c) { c.classList.toggle('is-active', c === b); });
		slotsEl.textContent = 'Loading…';
		get('staff/slots', { appointment: id, doctor: docSel.value, date: date }).then(function (j) {
			if (!j.ok) { slotsEl.textContent = j.message; return; }
			slotsEl.innerHTML = j.slots.length ? j.slots.map(function (s) { return '<label><input type="radio" name="rb_rtime" value="' + s.time + '">' + esc(s.label) + '</label>'; }).join('') : 'No free times.';
		});
	});
	slotsEl.addEventListener('change', function () { save.disabled = false; });
	docSel.addEventListener('change', loadDays);
	save.addEventListener('click', function () {
		var t = slotsEl.querySelector('input:checked'); if (!t) { return; }
		var reasonEl = document.querySelector('[data-rb-reason]');
		save.disabled = true;
		call('appointments/' + id + '/reschedule', { date: date, time: t.value, doctor: docSel.value, reason: reasonEl ? reasonEl.value : '' }).then(function (j) {
			if (j.ok) { notice('success', 'Moved to ' + j.item.when + ' with ' + j.item.doctor + '. A test notification was created.'); setTimeout(function () { location.reload(); }, 1000); }
			else { save.disabled = false; notice('warning', j.message); }
		});
	});
	loadDays();
})();
