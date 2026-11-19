/* Riverside Booking – front end (no dependencies). */
(function () {
	'use strict';

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		var a = new Uint8Array(16); (window.crypto || {}).getRandomValues ? crypto.getRandomValues(a) : a.forEach(function (_, i) { a[i] = Math.random() * 256; });
		return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
	}
	function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
	function addDays(s, n) { var p = s.split('-'); var d = new Date(+p[0], +p[1] - 1, +p[2] + n); return ymd(d); }

	function api(base, path, params, body) {
		var url = base + path;
		if (params) {
			var q = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
			if (q) { url += (url.indexOf('?') > -1 ? '&' : '?') + q; }
		}
		var opt = { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' };
		if (body) { opt.method = 'POST'; opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
		return fetch(url, opt).then(function (r) {
			return r.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; }).then(function (j) { j.httpStatus = r.status; return j; });
		}, function () { return { ok: false, httpStatus: 0, message: 'Network problem. Please check your connection and try again.' }; });
	}

	function notice(box, kind, html) {
		box.innerHTML = html ? '<div class="rh-notice rh-notice--' + (kind === 'ok' ? 'ok' : 'error') + '" role="' + (kind === 'ok' ? 'status' : 'alert') + '">' + html + '</div>' : '';
	}

	/* Day strip + slot list, shared by the booking form and the manage page. */
	function Picker(root, fetchDays, fetchSlots, onPick) {
		var daysEl = $('[data-rb-days]', root), slotsEl = $('[data-rb-slots]', root);
		var prev = $('[data-rb-prev]', root), next = $('[data-rb-next]', root);
		var today = ymd(new Date()), from = today, date = '', time = '';
		function reset(msg) {
			date = ''; time = ''; onPick('', '');
			daysEl.innerHTML = '<p class="rb-muted">' + esc(msg) + '</p>'; slotsEl.innerHTML = '';
			prev.disabled = true; next.disabled = true;
		}
		function loadDays(autoPick) {
			daysEl.setAttribute('aria-busy', 'true');
			daysEl.innerHTML = '<p class="rb-muted">Checking free days…</p>'; slotsEl.innerHTML = '';
			return fetchDays(from).then(function (j) {
				daysEl.removeAttribute('aria-busy');
				if (!j.ok) { daysEl.innerHTML = '<p class="rb-muted">' + esc(j.message || 'Could not load days.') + '</p>'; return; }
				var first = '';
				daysEl.innerHTML = j.days.map(function (d) {
					var free = d.free > 0; if (free && !first) { first = d.date; }
					return '<button type="button" class="rb-day' + (free ? '' : ' is-off') + '" data-date="' + d.date + '"' + (free ? '' : ' disabled') + ' aria-pressed="false" title="' + esc(free ? d.free + ' free times' : d.reason) + '">' +
						'<span class="rb-day__dow">' + esc(d.dow) + '</span><span class="rb-day__date">' + esc(d.label) + '</span>' +
						'<span class="rb-day__info">' + esc(free ? d.free + ' free' : d.reason) + '</span></button>';
				}).join('');
				prev.disabled = from <= today; next.disabled = false;
				if (autoPick && first) { pickDay(first); }
				else if (!first) { slotsEl.innerHTML = '<p class="rb-muted">No free times in these two weeks. Try the next two weeks.</p>'; }
			});
		}
		function pickDay(d) {
			date = d; time = ''; onPick(date, '');
			$$('.rb-day', daysEl).forEach(function (b) { var on = b.getAttribute('data-date') === d; b.classList.toggle('is-active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
			slotsEl.innerHTML = '<p class="rb-muted">Loading free times…</p>';
			fetchSlots(d).then(function (j) {
				if (!j.ok) { slotsEl.innerHTML = '<p class="rb-muted">' + esc(j.message || 'Could not load times.') + '</p>'; return; }
				if (!j.slots.length) { slotsEl.innerHTML = '<p class="rb-muted">No free times left on this day.</p>'; return; }
				var groups = {};
				j.slots.forEach(function (s) { (groups[s.part] = groups[s.part] || []).push(s); });
				slotsEl.innerHTML = Object.keys(groups).map(function (g) {
					return '<div class="rb-slotgroup"><p class="rb-slotgroup__t">' + esc(g) + '</p><div class="rb-slotgroup__list">' + groups[g].map(function (s) {
						return '<label class="rb-slot"><input type="radio" name="rb_time" value="' + s.time + '"><span>' + esc(s.label) + '</span></label>';
					}).join('') + '</div></div>';
				}).join('');
			});
		}
		daysEl.addEventListener('click', function (e) { var b = e.target.closest('.rb-day'); if (b && !b.disabled) { pickDay(b.getAttribute('data-date')); } });
		slotsEl.addEventListener('change', function (e) { if (e.target.name === 'rb_time') { time = e.target.value; onPick(date, time); } });
		prev.addEventListener('click', function () { from = addDays(from, -14); if (from < today) { from = today; } loadDays(true); });
		next.addEventListener('click', function () { from = addDays(from, 14); loadDays(true); });
		return {
			start: function () { from = today; return loadDays(true); },
			reset: reset,
			reloadDay: function () { if (date) { pickDay(date); } else { loadDays(true); } }
		};
	}

	/* Booking form. */
	function initBooking(wrap) {
		var base = wrap.getAttribute('data-rb-api');
		var form = $('[data-rb-form]', wrap), noticeBox = $('[data-rb-notice]', wrap);
		var svc = $('#rb-service', form), doc = $('#rb-doctor', form), summary = $('[data-rb-summary]', form);
		var success = $('[data-rb-success]', wrap), btn = $('.rb-submit', form);
		var dateIn = form.elements.date, timeVal = '';
		form.elements.idem.value = uuid();

		var picker = Picker(form,
			function (from) { return api(base, 'days', { service: svc.value, doctor: doc.value, from: from }); },
			function (d) { return api(base, 'slots', { service: svc.value, doctor: doc.value, date: d }); },
			function (d, t) { dateIn.value = d; timeVal = t; updateSummary(); }
		);

		function filterDoctors() {
			var allowed = svc.value ? (svc.options[svc.selectedIndex].getAttribute('data-doctors') || '').split(',') : null;
			$$('option', doc).forEach(function (o) {
				if (o.value === 'any') { return; }
				var ok = !allowed || allowed.indexOf(o.value) > -1;
				o.hidden = !ok; o.disabled = !ok;
			});
			if (doc.selectedOptions[0] && doc.selectedOptions[0].disabled) { doc.value = 'any'; }
			var n = allowed ? allowed.filter(Boolean).length : 0;
			$('#rb-doctor-help').textContent = allowed ? (n === 1 ? 'One doctor offers this service.' : n + ' doctors offer this service.') : 'Only doctors who offer the chosen service are listed.';
		}
		function updateSummary() {
			if (!svc.value || !dateIn.value || !timeVal) { summary.hidden = true; return; }
			var so = svc.options[svc.selectedIndex], d = doc.value === 'any' ? 'first available doctor' : doc.options[doc.selectedIndex].text.split(' – ')[0];
			var p = dateIn.value.split('-'), dt = new Date(+p[0], +p[1] - 1, +p[2], +timeVal.slice(0, 2), +timeVal.slice(3));
			summary.innerHTML = '<strong>Your request:</strong> ' + esc(so.text) + ' with ' + esc(d) + ', ' +
				esc(dt.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })) + ' at ' +
				esc(dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })) + ' (' + esc(so.getAttribute('data-duration')) + ' min).';
			summary.hidden = false;
		}
		function refresh() {
			filterDoctors();
			if (!svc.value) { picker.reset('Choose a service to see free times.'); return; }
			picker.start();
		}
		svc.addEventListener('change', refresh);
		doc.addEventListener('change', function () { if (svc.value) { picker.start(); } });

		// Prefill from links like ?doctor=romano or ?service=dentistry.
		var qs = new URLSearchParams(location.search), pd = qs.get('doctor'), ps = qs.get('service');
		if (pd && !ps) { var o = $('option[value="' + CSS.escape(pd) + '"]', doc); if (o) { ps = (o.getAttribute('data-services') || '').split(',')[0]; } }
		if (ps && $('option[value="' + CSS.escape(ps) + '"]', svc)) { svc.value = ps; }
		filterDoctors();
		if (pd && $('option[value="' + CSS.escape(pd) + '"]:not([disabled])', doc)) { doc.value = pd; }
		if (svc.value) { picker.start(); }

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			notice(noticeBox, '', '');
			if (!form.reportValidity()) { return; }
			if (!dateIn.value || !timeVal) { notice(noticeBox, 'error', '<strong>Please choose a day and a free time.</strong>'); $('[data-rb-when]', form).scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
			form.elements.rb_check.value = 'human';
			var data = {};
			Array.prototype.forEach.call(form.elements, function (el) {
				if (!el.name || el.name === 'rb_time' || el.name === 'action') { return; }
				if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) { return; }
				data[el.name] = el.value;
			});
			data.time = timeVal;
			btn.disabled = true; btn.classList.add('is-busy'); $('span', btn).textContent = 'Sending…';
			api(base, 'requests', null, data).then(function (j) {
				btn.disabled = false; btn.classList.remove('is-busy'); $('span', btn).textContent = 'Send appointment request';
				if (j.ok) {
					form.hidden = true;
					success.innerHTML = '<div class="rb-success__icon" aria-hidden="true">✓</div><h2>Request received</h2>' +
						'<p class="rb-ref">Reference <strong>' + esc(j.reference) + '</strong></p>' +
						'<p><span class="rb-pill rb-pill--pending">Pending</span> Not confirmed yet. Our reception team will check the time with ' + esc(j.doctor) + ' and confirm.</p>' +
						'<dl class="rb-dl"><dt>Service</dt><dd>' + esc(j.service) + '</dd><dt>Doctor</dt><dd>' + esc(j.doctor) + '</dd><dt>Requested time</dt><dd>' + esc(j.when) + '</dd></dl>' +
						'<p class="rb-muted">In this demo, the confirmation email goes to a test outbox only, never to a real inbox.</p>' +
						'<button type="button" class="rh-btn rh-btn--outline" data-rb-again>Make another request</button>';
					success.hidden = false; success.focus();
					wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
					return;
				}
				var msg = esc(j.message || 'Something went wrong. Please try again.');
				notice(noticeBox, 'error', '<strong>' + msg + '</strong>');
				if (j.httpStatus === 409) { picker.reloadDay(); }
				if (j.field && form.elements[j.field] && form.elements[j.field].focus) { form.elements[j.field].focus(); }
				else { noticeBox.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
			});
		});
		success.addEventListener('click', function (e) {
			if (!e.target.closest('[data-rb-again]')) { return; }
			form.reset(); form.elements.idem.value = uuid(); success.hidden = true; form.hidden = false; summary.hidden = true; refresh();
		});
	}

	/* Patient manage page (secure link). */
	function initManage(wrap) {
		var base = wrap.getAttribute('data-rb-api'), body = $('[data-rb-manage-body]', wrap), noticeBox = $('[data-rb-notice]', wrap);
		var t = new URLSearchParams(location.search).get('t') || '';
		if (history.replaceState) { history.replaceState(null, '', location.pathname); } // Keep the secret out of history/bookmarks.
		function show(j) {
			body.innerHTML = '<h1 class="rb-manage__title">Your appointment</h1>' +
				'<dl class="rb-dl"><dt>Reference</dt><dd>' + esc(j.reference) + '</dd><dt>Status</dt><dd><span class="rb-pill rb-pill--' + esc(j.status) + '">' + esc(j.status_label) + '</span></dd>' +
				'<dt>Service</dt><dd>' + esc(j.service) + '</dd><dt>Doctor</dt><dd>' + esc(j.doctor) + '</dd><dt>When</dt><dd>' + esc(j.when) + '</dd></dl>' +
				'<div class="rb-manage__actions"><button type="button" class="rh-btn rh-btn--primary" data-act="move">Change time</button><button type="button" class="rh-btn rh-btn--outline" data-act="cancel">Cancel appointment</button></div>' +
				'<div class="rb-manage__move" hidden><h2>Choose a new time with ' + esc(j.doctor) + '</h2>' +
				'<div class="rb-days-nav"><button type="button" class="rb-navbtn" data-rb-prev disabled aria-label="Previous two weeks">‹</button><div class="rb-days" data-rb-days></div><button type="button" class="rb-navbtn" data-rb-next aria-label="Next two weeks">›</button></div>' +
				'<div class="rb-slots" data-rb-slots role="radiogroup" aria-label="Free times"></div>' +
				'<button type="button" class="rh-btn rh-btn--primary" data-act="save" disabled>Request this new time</button></div>';
		}
		function done(j, kind) {
			body.innerHTML = '<div class="rb-success"><div class="rb-success__icon" aria-hidden="true">✓</div><h1 class="rb-manage__title">' + (kind === 'cancel' ? 'Appointment canceled' : 'New time requested') + '</h1>' +
				'<p>Reference <strong>' + esc(j.reference) + '</strong> · <span class="rb-pill rb-pill--' + esc(j.status) + '">' + esc(j.status_label) + '</span></p>' +
				'<p>' + (kind === 'cancel' ? 'The time has been released for other patients.' : 'Your new time (' + esc(j.when) + ') is pending until reception confirms it.') + '</p>' +
				'<p class="rb-muted">This link has now been used. A new message (test outbox) was created.</p></div>';
		}
		api(base, 'manage', { t: t }).then(function (j) {
			if (!j.ok) { body.innerHTML = '<h1 class="rb-manage__title">Link not available</h1><p>' + esc(j.message) + '</p>'; return; }
			show(j);
			var date = '', time = '';
			var picker = Picker(body,
				function (from) { return api(base, 'manage/days', { t: t, from: from }); },
				function (d) { return api(base, 'manage/slots', { t: t, date: d }); },
				function (d, tm) { date = d; time = tm; $('[data-act="save"]', body).disabled = !(d && tm); }
			);
			body.addEventListener('click', function (e) {
				var b = e.target.closest('[data-act]'); if (!b) { return; }
				var act = b.getAttribute('data-act');
				if (act === 'move') { $('.rb-manage__move', body).hidden = false; picker.start(); return; }
				if (act === 'cancel') {
					if (!window.confirm('Cancel this appointment? The time will be released.')) { return; }
					b.disabled = true;
					api(base, 'manage/cancel', null, { t: t }).then(function (r) { if (r.ok) { done(r, 'cancel'); } else { b.disabled = false; notice(noticeBox, 'error', esc(r.message)); } });
				}
				if (act === 'save') {
					b.disabled = true;
					api(base, 'manage/reschedule', null, { t: t, date: date, time: time }).then(function (r) {
						if (r.ok) { done(r, 'move'); } else { b.disabled = false; notice(noticeBox, 'error', esc(r.message)); if (r.httpStatus === 409) { picker.reloadDay(); } }
					});
				}
			});
		});
	}

	$$('[data-rb-booking]').forEach(initBooking);
	$$('[data-rb-manage]').forEach(initManage);
})();
