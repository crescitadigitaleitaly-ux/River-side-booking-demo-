// Acceptance tests for the Riverside booking demo, run against STAGING only.
// Usage (Git Bash):
//   export MSYS_NO_PATHCONV=1
//   STG_AUTH='user:pass' STG_KEY_FILE=/path/stg-helper.key USERS_FILE=/path/staff-users.json node tests/acceptance.mjs
// Writes evidence/test-results.json and screenshots to evidence/screenshots/.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js'));
const pw = require('playwright');

const BASE = process.env.BASE || 'https://mouzzambuild.com/riverside-staging';
const [AUTH_USER, AUTH_PASS] = (process.env.STG_AUTH || ':').split(':');
const KEY = fs.readFileSync(process.env.STG_KEY_FILE, 'utf8').trim();
const USERS = JSON.parse(fs.readFileSync(process.env.USERS_FILE, 'utf8'));
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const SHOTS = path.join(ROOT, 'evidence', 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const results = [];
const t0 = Date.now();
function record(id, name, pass, detail) {
	results.push({ id, name, pass: !!pass, detail });
	console.log(`${pass ? 'PASS' : 'FAIL'}  ${id} ${name}${detail ? ' — ' + (typeof detail === 'string' ? detail : JSON.stringify(detail)) : ''}`);
}
async function step(id, name, fn) {
	try { await fn(); } catch (e) { record(id, name, false, 'Error: ' + e.message.split('\n')[0]); }
}

const browser = await pw.chromium.launch({ args: ['--disable-quic'] });
const ctxOpts = { httpCredentials: { username: AUTH_USER, password: AUTH_PASS }, viewport: { width: 1366, height: 900 }, deviceScaleFactor: 1.5 };
const anon = await browser.newContext(ctxOpts);
const api = anon.request;

const helper = (action, opts = {}) => api.fetch(`${BASE}/?rbstg=${action}${opts.qs || ''}`, { method: opts.body ? 'POST' : 'GET', headers: { 'X-RB-STG': KEY, 'Content-Type': 'application/json' }, data: opts.body });
const getJ = async (url) => { const r = await api.get(BASE + url, { headers: { 'Cache-Control': 'no-cache' } }); return { status: r.status(), body: await r.json().catch(() => ({})) }; };
const postJ = async (url, data) => { const r = await api.post(BASE + url, { data }); return { status: r.status(), body: await r.json().catch(() => ({})) }; };

// Find a bookable date and time for a service/doctor.
async function freeSlot(service, doctor, skip = 0) {
	const d = await getJ(`/wp-json/rb/v1/days?service=${service}&doctor=${doctor}`);
	const days = d.body.days.filter((x) => x.free > 0);
	for (const day of days.slice(skip)) {
		const s = await getJ(`/wp-json/rb/v1/slots?service=${service}&doctor=${doctor}&date=${day.date}`);
		if (s.body.slots && s.body.slots.length) { return { date: day.date, time: s.body.slots[0].time, all: s.body.slots.map((x) => x.time) }; }
	}
	throw new Error('no free slot');
}
let seq = 0;
const person = (extra = {}) => ({ name: 'Test Patient ' + (++seq), email: `test.patient${seq}.${Date.now()}@patient.example`, phone: '(555) 014-' + String(1000 + seq), patient_type: 'new', visit_type: 'checkup', consent: '1', rb_check: 'human', idem: 'test-' + Date.now() + '-' + seq + '-abcdefgh', ...extra });

async function staffContext(login) {
	const ctx = await browser.newContext(ctxOpts);
	const r = await ctx.request.fetch(`${BASE}/?rbstg=login&as=${login}`, { headers: { 'X-RB-STG': KEY } });
	if (!r.ok()) { throw new Error('login helper ' + r.status()); }
	return ctx;
}

// ---------------------------------------------------------------- setup
const seed = await (await helper('seed')).json();
await helper('clear_rl');
record('0', 'Demo data reset on staging', seed.ok && seed.services === 8 && seed.doctors === 6, seed);

// ---------------------------------------------------------------- 1 mapping
await step('1', 'Each service shows only its mapped doctors (Family Dentistry → Dr. Romano only)', async () => {
	const page = await anon.newPage();
	await page.goto(BASE + '/book-appointment/', { waitUntil: 'domcontentloaded' });
	const svcs = await page.$$eval('#rb-service option[value]:not([value=""])', (os) => os.map((o) => ({ v: o.value, t: o.textContent, docs: o.dataset.doctors.split(',') })));
	const bad = [];
	for (const s of svcs) {
		await page.selectOption('#rb-service', s.v);
		const shown = await page.$$eval('#rb-doctor option', (os) => os.filter((o) => !o.disabled && !o.hidden && o.value !== 'any').map((o) => o.value));
		if (shown.sort().join() !== s.docs.sort().join()) { bad.push({ service: s.v, shown, expected: s.docs }); }
	}
	await page.selectOption('#rb-service', 'dentistry');
	const dent = await page.$$eval('#rb-doctor option', (os) => os.filter((o) => !o.disabled && !o.hidden).map((o) => o.textContent));
	await page.waitForSelector('.rb-day:not([disabled])', { timeout: 20000 });
	await page.locator('#booking').screenshot({ path: path.join(SHOTS, '03-after-dentistry-only-romano.png') });
	// Server must reject a forged combination too.
	const slot = await freeSlot('cardiology', 'okafor');
	const forged = await postJ('/wp-json/rb/v1/requests', person({ service: 'dentistry', doctor: 'okafor', date: slot.date, time: slot.time }));
	record('1', 'Each service shows only its mapped doctors (Family Dentistry → Dr. Romano only)', !bad.length && dent.length === 2 && dent[1].includes('Romano') && forged.status === 422,
		{ servicesChecked: svcs.length, mismatches: bad, dentistryOptions: dent, forgedDentistryWithCardiologist: forged.status });
	await page.close();
});

// ---------------------------------------------------------------- 2 closed days
await step('2', 'Closed days and unavailable times cannot be booked', async () => {
	const d = await getJ('/wp-json/rb/v1/days?service=primary-care&doctor=any');
	const sunday = d.body.days.find((x) => x.dow === 'Sun');
	const closure = d.body.days.find((x) => /closed/i.test(x.reason) && x.dow !== 'Sun');
	const checks = {};
	checks.sundayShownClosed = sunday && sunday.free === 0;
	checks.closureShown = closure ? closure.reason : 'none in range';
	checks.forgedSunday = (await postJ('/wp-json/rb/v1/requests', person({ service: 'primary-care', doctor: 'carter', date: sunday.date, time: '10:00' }))).status;
	const sat = d.body.days.find((x) => x.dow === 'Sat');
	checks.forgedSaturdayEvening = (await postJ('/wp-json/rb/v1/requests', person({ service: 'primary-care', doctor: 'carter', date: sat.date, time: '18:00' }))).status;
	if (closure) { checks.forgedClosureDay = (await postJ('/wp-json/rb/v1/requests', person({ service: 'primary-care', doctor: 'carter', date: closure.date, time: '10:00' }))).status; }
	checks.forgedOffGrid = (await postJ('/wp-json/rb/v1/requests', person({ service: 'primary-care', doctor: 'carter', date: (await freeSlot('primary-care', 'carter')).date, time: '10:07' }))).status;
	const pass = checks.sundayShownClosed && checks.forgedSunday === 422 && checks.forgedSaturdayEvening === 422 && (!closure || checks.forgedClosureDay === 422) && checks.forgedOffGrid === 422;
	record('2', 'Closed days and unavailable times cannot be booked', pass, checks);
	const page = await anon.newPage();
	await page.goto(BASE + '/book-appointment/?service=dentistry&doctor=romano', { waitUntil: 'domcontentloaded' });
	await page.waitForSelector('.rb-day:not([disabled])', { timeout: 20000 });
	await page.locator('[data-rb-when]').screenshot({ path: path.join(SHOTS, '04-day-strip-closed-days.png') });
	await page.close();
});

// ---------------------------------------------------------------- 3 + 4 submit through the UI and see it in reception
let uiRef = '';
await step('3', 'Visitor submits a request through the form and gets a reference, status Pending', async () => {
	await helper('clear_rl');
	const page = await anon.newPage();
	await page.goto(BASE + '/book-appointment/', { waitUntil: 'domcontentloaded' });
	await page.selectOption('#rb-service', 'dentistry');
	await page.selectOption('#rb-doctor', 'romano');
	await page.waitForSelector('.rb-day.is-active', { timeout: 20000 });
	await page.waitForSelector('.rb-slot input', { timeout: 20000 });
	await page.locator('.rb-slot').first().click();
	await page.fill('#rb-name', 'Grace Demo-Patient');
	await page.fill('#rb-email', 'grace.demo@patient.example');
	await page.fill('#rb-phone', '(555) 014-7788');
	await page.selectOption('#rb-visit', 'cleaning');
	await page.check('input[name=consent]');
	await page.locator('[data-rb-summary]').waitFor({ state: 'visible' });
	await page.locator('#booking').screenshot({ path: path.join(SHOTS, '05-form-filled.png') });
	await page.click('.rb-submit');
	await page.waitForSelector('[data-rb-success]:not([hidden])', { timeout: 20000 });
	uiRef = (await page.textContent('.rb-ref strong')).trim();
	const pill = await page.textContent('[data-rb-success] .rb-pill');
	await page.locator('#booking').screenshot({ path: path.join(SHOTS, '06-request-received-pending.png') });
	record('3', 'Visitor submits a request through the form and gets a reference, status Pending', /^RFH-[A-Z0-9]{4}-\d{2}$/.test(uiRef) && /Pending/.test(pill), { reference: uiRef, status: pill });
	await page.close();
});

const reception = await staffContext('reception.demo');
const rPage = await reception.newPage();
await step('4', 'The submitted request appears in the reception dashboard', async () => {
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&from=&q=${encodeURIComponent(uiRef)}`, { waitUntil: 'domcontentloaded' });
	const row = await rPage.textContent('.rb-table tbody');
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception`, { waitUntil: 'domcontentloaded' });
	await rPage.screenshot({ path: path.join(SHOTS, '07-reception-dashboard.png'), fullPage: true });
	record('4', 'The submitted request appears in the reception dashboard', row.includes(uiRef) && /Pending/.test(row), { found: row.includes(uiRef) });
});

// ---------------------------------------------------------------- 5 + 6 competing confirmations
let winnerId = 0, slotA = null;
await step('5', 'Two competing requests: only one can be confirmed for the same time', async () => {
	await helper('clear_rl');
	slotA = await freeSlot('cardiology', 'okafor', 1);
	const a = await postJ('/wp-json/rb/v1/requests', person({ service: 'cardiology', doctor: 'okafor', date: slotA.date, time: slotA.time }));
	const b = await postJ('/wp-json/rb/v1/requests', person({ service: 'cardiology', doctor: 'okafor', date: slotA.date, time: slotA.time }));
	if (a.status !== 201 || b.status !== 201) { throw new Error('could not create both pending requests: ' + a.status + '/' + b.status + ' ' + (a.body.message || b.body.message)); }
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&from=`, { waitUntil: 'domcontentloaded' });
	const ids = await rPage.evaluate(async ([ra, rb]) => {
		const r = await fetch(RB.api + 'appointments?status=pending', { headers: { 'X-WP-Nonce': RB.nonce } }).then((x) => x.json());
		return [ra, rb].map((ref) => (r.items.find((i) => i.reference === ref) || {}).id);
	}, [a.body.reference, b.body.reference]);
	// Fire both confirmations at the same moment.
	const out = await rPage.evaluate(async (ids2) => Promise.all(ids2.map((id) => fetch(RB.api + 'appointments/' + id + '/confirm', { method: 'POST', headers: { 'X-WP-Nonce': RB.nonce, 'Content-Type': 'application/json' }, body: '{}' }).then(async (r) => ({ id, status: r.status, body: await r.json() })))), ids);
	const ok = out.filter((o) => o.status === 200), taken = out.filter((o) => o.status === 409);
	winnerId = ok[0] ? ok[0].id : 0;
	const loser = taken[0] ? taken[0].id : 0;
	if (loser) {
		await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&view=${loser}`, { waitUntil: 'domcontentloaded' });
		await rPage.click('[data-rb-act="confirm"]');
		await rPage.waitForSelector('[data-rb-notice] .notice', { timeout: 15000 });
		await rPage.screenshot({ path: path.join(SHOTS, '08-conflict-time-just-taken.png') });
	}
	record('5', 'Two competing requests: only one can be confirmed for the same time', ok.length === 1 && taken.length === 1, out.map((o) => ({ status: o.status, msg: o.body.message || o.body.item?.status })));
});

await step('6', 'Staff confirmation updates the record and creates the confirmation test notification', async () => {
	const rec = await rPage.evaluate(async () => (await fetch(RB.api + 'appointments?status=confirmed', { headers: { 'X-WP-Nonce': RB.nonce } }).then((x) => x.json())).items.map((i) => i.id));
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&view=${winnerId}`, { waitUntil: 'domcontentloaded' });
	const hist = await rPage.textContent('.rb-history');
	const mails = await rPage.$$eval('.rb-card a[href*="rb-outbox&msg="]', (as) => as.map((a) => ({ t: a.textContent, href: a.href })));
	await rPage.screenshot({ path: path.join(SHOTS, '09-appointment-detail-history.png'), fullPage: true });
	const conf = mails.find((m) => m.t === 'Confirmation');
	if (conf) {
		await rPage.goto(conf.href, { waitUntil: 'domcontentloaded' });
		await rPage.screenshot({ path: path.join(SHOTS, '10-test-outbox-confirmation.png'), fullPage: true });
	}
	record('6', 'Staff confirmation updates the record and creates the confirmation test notification', rec.includes(winnerId) && /Confirm/.test(hist) && !!conf, { confirmedInDb: rec.includes(winnerId), notifications: mails.map((m) => m.t) });
});

// ---------------------------------------------------------------- 7 cancellation releases capacity (patient link)
await step('7', 'Cancellation (patient secure link) releases the time for others', async () => {
	const before = await getJ(`/wp-json/rb/v1/slots?service=cardiology&doctor=okafor&date=${slotA.date}`);
	const takenBefore = !before.body.slots.some((s) => s.time === slotA.time);
	// Read the newest manage link from the confirmation message (test outbox).
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&view=${winnerId}`, { waitUntil: 'domcontentloaded' });
	const href = await rPage.$$eval('.rb-card a[href*="rb-outbox&msg="]', (as) => as.filter((a) => a.textContent === 'Confirmation').pop().href);
	await rPage.goto(href, { waitUntil: 'domcontentloaded' });
	const src = await rPage.getAttribute('.rb-mail__frame', 'srcdoc');
	const link = (src.match(/href="([^"]*manage-appointment[^"]*)"/) || [])[1].replace(/&amp;/g, '&');
	const patient = await anon.newPage();
	patient.on('dialog', (dlg) => dlg.accept());
	await patient.goto(link, { waitUntil: 'domcontentloaded' });
	await patient.waitForSelector('[data-act="cancel"]', { timeout: 20000 });
	await patient.locator('.rb-manage').screenshot({ path: path.join(SHOTS, '11-patient-manage-page.png') });
	await patient.click('[data-act="cancel"]');
	await patient.waitForSelector('.rb-success', { timeout: 20000 });
	await patient.locator('.rb-manage').screenshot({ path: path.join(SHOTS, '12-patient-canceled.png') });
	const after = await getJ(`/wp-json/rb/v1/slots?service=cardiology&doctor=okafor&date=${slotA.date}`);
	const freeAfter = after.body.slots.some((s) => s.time === slotA.time);
	// The same link must not work twice.
	const tok = new URL(link).searchParams.get('t');
	const reuse = await getJ('/wp-json/rb/v1/manage?t=' + tok);
	record('7', 'Cancellation (patient secure link) releases the time for others', takenBefore && freeAfter && reuse.status === 410, { takenBefore, freeAfter, reusedLink: reuse.status });
	await patient.close();
});

// ---------------------------------------------------------------- 8 access control
await step('8', 'Unauthorised users cannot view or change bookings', async () => {
	const c = {};
	c.anonList = (await getJ('/wp-json/rb/v1/appointments')).status;
	c.anonConfirm = (await postJ('/wp-json/rb/v1/appointments/1/confirm', {})).status;
	c.anonStaffSlots = (await getJ('/wp-json/rb/v1/staff/slots?appointment=1&date=2026-12-01')).status;
	c.badToken = (await getJ('/wp-json/rb/v1/manage?t=' + 'a'.repeat(64))).status;
	const sub = await staffContext('subscriber.demo');
	const sp = await sub.newPage();
	const resp = await sp.goto(`${BASE}/wp-admin/admin.php?page=rb-reception`, { waitUntil: 'domcontentloaded' });
	c.subscriberAdminPage = resp.status();
	const nonce = await sp.evaluate(async (b) => (await fetch(b + '/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text(), BASE);
	c.subscriberRest = (await sub.request.get(BASE + '/wp-json/rb/v1/appointments', { headers: { 'X-WP-Nonce': nonce } })).status();
	await sp.screenshot({ path: path.join(SHOTS, '13-subscriber-blocked.png') });
	// Reception cannot open settings (admin-only) and cannot edit plugins.
	const rs = await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-settings`, { waitUntil: 'domcontentloaded' });
	c.receptionSettings = rs.status();
	const rp = await rPage.goto(`${BASE}/wp-admin/plugins.php`, { waitUntil: 'domcontentloaded' });
	c.receptionPlugins = rp.status();
	await sub.close();
	const pass = c.anonList === 401 && c.anonConfirm === 401 && c.anonStaffSlots === 401 && c.badToken === 404 && c.subscriberAdminPage === 403 && c.subscriberRest === 403 && c.receptionSettings === 403 && c.receptionPlugins === 403;
	record('8', 'Unauthorised users cannot view or change bookings', pass, c);
});

// ---------------------------------------------------------------- 9 duplicates + rate limit + honeypot
await step('9', 'Duplicate submissions, spam honeypot and rate limit are handled', async () => {
	await helper('clear_rl');
	const s = await freeSlot('primary-care', 'carter', 2);
	const p = person({ service: 'primary-care', doctor: 'carter', date: s.date, time: s.time });
	const first = await postJ('/wp-json/rb/v1/requests', p);
	const again = await postJ('/wp-json/rb/v1/requests', p);
	const hp = await postJ('/wp-json/rb/v1/requests', { ...person({ service: 'primary-care', doctor: 'carter', date: s.date, time: s.all[1] }), website: 'http://spam.example' });
	const codes = [];
	for (let i = 0; i < 6; i++) { codes.push((await postJ('/wp-json/rb/v1/requests', person({ service: 'primary-care', doctor: 'carter', date: s.date, time: s.all[2 + i] || s.all[2] }))).status); }
	await helper('clear_rl');
	record('9', 'Duplicate submissions, spam honeypot and rate limit are handled', first.status === 201 && again.status === 200 && again.body.duplicate === true && again.body.reference === first.body.reference && hp.body.spam === true && codes.includes(429),
		{ first: first.status, duplicateSameReference: again.body.reference === first.body.reference, honeypotStoredNothing: hp.body.spam === true, burstStatuses: codes });
});

// ---------------------------------------------------------------- 10 staff reschedule
await step('10', 'Staff reschedule keeps the booking confirmed and creates a reschedule notification', async () => {
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&status=confirmed&from=`, { waitUntil: 'domcontentloaded' });
	const target = await rPage.evaluate(async () => { const r = await fetch(RB.api + 'appointments?status=confirmed', { headers: { 'X-WP-Nonce': RB.nonce } }).then((x) => x.json()); const now = new Date(); return r.items.find((i) => new Date(i.date + 'T' + i.time) > now); });
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&view=${target.id}#reschedule`, { waitUntil: 'domcontentloaded' });
	await rPage.waitForSelector('[data-rb-rdays] .rb-day:not([disabled])', { timeout: 20000 });
	await rPage.locator('[data-rb-rdays] .rb-day:not([disabled])').nth(1).click();
	await rPage.waitForSelector('[data-rb-rslots] input', { timeout: 20000 });
	await rPage.locator('[data-rb-rslots] input').first().check();
	await rPage.screenshot({ path: path.join(SHOTS, '14-staff-reschedule.png'), fullPage: true });
	await rPage.click('[data-rb-rsave]');
	await rPage.waitForSelector('[data-rb-notice] .notice-success', { timeout: 20000 });
	await rPage.waitForTimeout(1500);
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-reception&view=${target.id}`, { waitUntil: 'domcontentloaded' });
	const head = await rPage.textContent('h1');
	const notes = await rPage.$$eval('.rb-card a[href*="rb-outbox&msg="]', (as) => as.map((a) => a.textContent));
	record('10', 'Staff reschedule keeps the booking confirmed and creates a reschedule notification', /Confirmed/.test(head) && notes.includes('Reschedule'), { status: head.trim(), notifications: notes });
});

// ---------------------------------------------------------------- 11 outbox only
await step('11', 'Notifications stay in the test outbox (no real recipient)', async () => {
	await rPage.goto(`${BASE}/wp-admin/admin.php?page=rb-outbox`, { waitUntil: 'domcontentloaded' });
	const rows = await rPage.$$eval('tbody tr', (trs) => trs.map((tr) => tr.lastElementChild.textContent.trim()));
	await rPage.screenshot({ path: path.join(SHOTS, '15-test-outbox-list.png'), fullPage: true });
	record('11', 'Notifications stay in the test outbox (no real recipient)', rows.length > 0 && rows.every((r) => r === 'Test outbox only'), { messages: rows.length, deliveryNotes: [...new Set(rows)] });
});

// ---------------------------------------------------------------- 12 mobile + console
await step('12', 'Booking form works on a 375 px phone with no console errors and no sideways scroll', async () => {
	const m = await browser.newContext({ ...ctxOpts, viewport: { width: 375, height: 812 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
	const page = await m.newPage();
	const errors = [];
	page.on('console', (msg) => { if (msg.type() === 'error') { errors.push(msg.text()); } });
	await page.goto(BASE + '/book-appointment/?service=pediatrics', { waitUntil: 'domcontentloaded' });
	await page.waitForSelector('.rb-slot', { timeout: 20000 });
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
	await page.locator('#booking').screenshot({ path: path.join(SHOTS, '16-mobile-375-form.png') });
	await m.close();
	record('12', 'Booking form works on a 375 px phone with no console errors and no sideways scroll', overflow <= 0 && !errors.length, { overflowPx: overflow, consoleErrors: errors });
});

// ---------------------------------------------------------------- finish
await helper('seed'); // Leave staging in a clean demo state.
await browser.close();
const summary = { when: new Date().toISOString(), base: BASE, seconds: Math.round((Date.now() - t0) / 1000), passed: results.filter((r) => r.pass).length, total: results.length, results };
fs.writeFileSync(path.join(ROOT, 'evidence', 'test-results.json'), JSON.stringify(summary, null, 2));
console.log(`\n${summary.passed}/${summary.total} passed in ${summary.seconds}s`);
process.exit(summary.passed === summary.total ? 0 : 1);
