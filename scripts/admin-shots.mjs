// Full-HD stills of the staff admin panel for the "manage your own website" video (staging, masked).
// 1280x720 viewport at 1.5x = exactly 1920x1080, so the video shows them pixel-for-pixel (no scaling blur).
// The part being explained is spotlighted (orange ring, rest dimmed). Contact details are masked server-side
// (recording cookie rb_rec=1). Demo data is reset at the end.
// Usage: STG_AUTH=user:pass STG_KEY_FILE=<path> USERS_FILE=<path> node scripts/admin-shots.mjs
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const OUT = path.join(ROOT, 'video', 'admin', 'shots'); fs.mkdirSync(OUT, { recursive: true });
const STG = 'https://mouzzambuild.com/riverside-staging';
const [U, P] = process.env.STG_AUTH.split(':'); const KEY = fs.readFileSync(process.env.STG_KEY_FILE, 'utf8').trim();
const H = { headers: { 'X-RB-STG': KEY } };
const b = await pw.chromium.launch({ args: ['--disable-quic'] });
const ctx = async (user) => {
	const c = await b.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1.5, httpCredentials: { username: U, password: P } });
	await c.addCookies([{ name: 'rb_rec', value: '1', domain: 'mouzzambuild.com', path: '/riverside-staging', secure: true }]);
	if (user) await c.request.fetch(`${STG}/?rbstg=login&as=${user}`, H);
	return c;
};
const CSS = `.rb-hl{outline:5px solid #F59E0B!important;outline-offset:6px;border-radius:6px;position:relative;z-index:99999;box-shadow:0 0 0 6px rgba(245,158,11,.25),0 0 0 9999px rgba(15,23,42,.42)!important}
#rb-maps{color:transparent!important;text-shadow:0 0 8px rgba(0,0,0,.6)} #wpadminbar .ab-top-secondary{visibility:hidden}`;
let page;
// Recording mode masks contact values in the HTML too; put the real (fictional) values back before saving,
// otherwise the browser rejects the masked email and the masked text would be saved.
const REAL = { phone: '(555) 014-2200', urgent: '(555) 014-2299', email: 'care@riversidefamilyhealth.example', maps: 'https://www.google.com/maps/search/?api=1&query=1200+Riverbend+Avenue+Riverside+CA' };
const save = async () => { for (const [k, v] of Object.entries(REAL)) await page.fill('#rb-' + k, v); await page.click('.rb-wrap button.button-primary'); await page.waitForLoadState('load'); await page.addStyleTag({ content: CSS }); };
const go = async (url) => { await page.goto(STG + url, { waitUntil: 'load' }); await page.addStyleTag({ content: CSS }); };
const shot = async (name, sel, { center = true } = {}) => {
	await page.evaluate(([s, c]) => {
		document.querySelectorAll('.rb-hl').forEach((e) => e.classList.remove('rb-hl'));
		const el = s && document.querySelector(s);
		if (el) { el.classList.add('rb-hl'); if (c) el.scrollIntoView({ block: 'center' }); } else window.scrollTo(0, 0);
	}, [sel, center]);
	await page.waitForTimeout(400);
	await page.screenshot({ path: path.join(OUT, name + '.png') });
	console.log(name, sel || '(none)');
};

await (await ctx()).request.fetch(`${STG}/?rbstg=seed`, H); // clean demo data first
const m = await ctx('manager.demo'); page = await m.newPage();

// 1. Where everything lives
await go('/wp-admin/admin.php?page=rb-reception');
await shot('01-menu', '#toplevel_page_rb-reception', { center: false });
// 2-4. Clinic details & hours
await go('/wp-admin/admin.php?page=rb-clinic-info');
await shot('02-contact', '.rb-grid > .rb-card:nth-of-type(1)');
await page.check('input[aria-label="Saturday closed"]');
await shot('03-hours', '.rb-grid > .rb-card:nth-of-type(2)');
// 5. Safety: a closing time before the opening time is refused
await page.fill('input[aria-label="Monday closes"]', '07:00');
await save();
await shot('05-error', '.notice-error', { center: false });
// 4 + 6. Notice bar, then a valid save
const la = (d) => new Date(Date.now() + d * 864e5).toLocaleDateString('en-CA', { timeZone: 'America/Los_Angeles' });
await go('/wp-admin/admin.php?page=rb-clinic-info');
await page.check('input[aria-label="Saturday closed"]');
await page.check('input[name=notice_on]');
await page.fill('#rb-notice-text', 'Closed Thursday Nov 26 for Thanksgiving. Online booking stays open.');
await page.fill('input[name=notice_from]', la(0)); await page.fill('input[name=notice_to]', la(8));
await shot('04-notice', '.rb-card:has(#rb-notice-text)');
await save();
await shot('06-saved', '.notice-success', { center: false });
// 7. The website updates straight away
await go('/');
await shot('07-site', '.rh-notice-bar', { center: false });
await page.evaluate(() => document.querySelector('.rh-footer')?.scrollIntoView({ block: 'end' }));
await shot('08-footer', '.rh-footer address, .rh-footer', { center: false });
// 9-10. Doctors
await go('/wp-admin/edit.php?post_type=rb_doctor');
await shot('09-doctors', 'table.wp-list-table', { center: false });
const docId = await page.$eval('#the-list tr a.row-title', (a) => new URL(a.href).searchParams.get('post'));
await go(`/wp-admin/post.php?post=${docId}&action=edit`);
await shot('10-doctor-hours', '#rb_doctor_hours');
// 11. Holidays and leave
await go('/wp-admin/admin.php?page=rb-closures');
await page.selectOption('.rb-card:has(input[name=starts]) select', { index: 0 }).catch(() => {});
await page.fill('input[name=reason]', 'Public holiday');
await shot('11-closures', '.rb-card:has(input[name=starts])', { center: false });
// 12. Reception staff cannot change these settings
const r = await ctx('reception.demo'); page = await r.newPage();
await go('/wp-admin/admin.php?page=rb-clinic-info');
await page.addStyleTag({ content: 'body#error-page{margin-top:180px!important;max-width:900px!important;font-size:22px!important;padding:40px 50px!important}' }); await shot('12-reception-blocked', 'body#error-page', { center: false });

await (await ctx()).request.fetch(`${STG}/?rbstg=seed`, H); // put the demo data back
await b.close();
