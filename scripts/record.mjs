// Records the video scenes (1600x900) with Playwright.
// Emails, phone numbers and web addresses are blurred live (Upwork + Fiverr content rules).
// The LIVE site is only browsed (no form submission). Everything else runs on STAGING.
// Usage: STG_AUTH='user:pass' STG_KEY_FILE=... node scripts/record.mjs [scene ...]
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js'));
const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const OUT = path.join(ROOT, 'video', 'rec');
fs.mkdirSync(OUT, { recursive: true });
const STG = 'https://mouzzambuild.com/riverside-staging';
const LIVE = 'https://dental.mouzzambuild.com';
const [U, P] = process.env.STG_AUTH.split(':');
const KEY = fs.readFileSync(process.env.STG_KEY_FILE, 'utf8').trim();
const only = process.argv.slice(2);
const W = 1600, H = 900; // Upscaled to 1080p with lanczos in the build (CSS zoom caused half-drawn frames).

// Runs in every page and frame before page scripts.
const PREP = `
(() => {
  // Masking is painted with the text itself (transparent text + blurred shadow); a CSS filter alone can lag in screen recordings.
  const M = 'color:transparent!important;text-shadow:0 0 9px rgba(20,30,40,.75)!important;-webkit-text-fill-color:transparent!important;';
  const css = 'input[type=email],input[type=tel],input[name=email],input[name=phone],.rb-blur,.rb-blur *{' + M + '}' +
    'html:not(.rb-ready) body{visibility:hidden!important}';
  const RX = /@|\\(555\\)|555-|mouzzambuild|https?:|www\\.|\\.example|wa\\.me|whatsapp/i;
  const mark = (root) => {
    const tw = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let n; while ((n = tw.nextNode())) { if (RX.test(n.nodeValue) && n.parentElement && !n.parentElement.closest('script,style')) n.parentElement.classList.add('rb-blur'); }
    root.querySelectorAll && root.querySelectorAll('a[href^="tel:"],a[href^="mailto:"]').forEach((a) => a.classList.add('rb-blur'));
  };
  // Add the style at once (before the first paint), not at DOMContentLoaded: Chrome paints partially parsed pages.
  const addCss = () => { if (!document.getElementById('rb-rec-css') && document.documentElement) { const s = document.createElement('style'); s.id = 'rb-rec-css'; s.textContent = css; document.documentElement.appendChild(s); } };
  addCss();
  const go = () => {
    addCss();
    if (document.body) { mark(document.body); document.documentElement.classList.add('rb-ready'); new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach((x) => x.nodeType === 1 ? mark(x) : (x.nodeType === 3 && x.parentElement && mark(x.parentElement))))).observe(document.body, { childList: true, subtree: true, characterData: true }); }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();`;

const browser = await pw.chromium.launch({ args: ['--disable-quic'] });
async function scene(name, fn, { staff = '' } = {}) {
	if (only.length && !only.includes(name)) { return; }
	const ctx = await browser.newContext({ viewport: { width: W, height: H }, httpCredentials: { username: U, password: P }, recordVideo: { dir: OUT, size: { width: W, height: H } } });
	await ctx.addInitScript(PREP);
	// Staging masks contact details server-side in recording mode (see staging/001-staging-helper.php).
	await ctx.addCookies([{ name: 'rb_rec', value: '1', domain: 'mouzzambuild.com', path: '/riverside-staging', secure: true }]);
	if (staff) { await ctx.request.fetch(`${STG}/?rbstg=login&as=${staff}`, { headers: { 'X-RB-STG': KEY } }); }
	const page = await ctx.newPage();
	const t0 = Date.now();
	try { await fn(page); } catch (e) { console.error(name, 'ERROR', e.message.split('\n')[0]); }
	const vid = page.video();
	await ctx.close();
	const src = await vid.path();
	const dst = path.join(OUT, name + '.webm');
	fs.renameSync(src, dst);
	console.log(name, ((Date.now() - t0) / 1000).toFixed(1) + 's', dst);
}
const pause = (p, ms) => p.waitForTimeout(ms);
const smooth = (p, y) => p.evaluate((yy) => window.scrollTo({ top: yy, behavior: 'instant' }), y);
const toEl = (p, sel, off = 140) => p.evaluate(([s, o]) => { const el = document.querySelector(s); window.scrollTo({ top: (el.getBoundingClientRect().top + window.scrollY) - o, behavior: 'instant' }); }, [sel, off]);

// Reset staging demo data so the scenes start clean.
const ctx0 = await browser.newContext({ httpCredentials: { username: U, password: P } });
if (!only.length || only.includes('reception')) { console.log('seed', (await (await ctx0.request.fetch(`${STG}/?rbstg=seed`, { headers: { 'X-RB-STG': KEY } })).json())); await ctx0.request.fetch(`${STG}/?rbstg=clear_rl`, { headers: { 'X-RB-STG': KEY } }); }
await ctx0.close();

// 1. Site tour (staging = same design as live, with the new booking page).
await scene('tour', async (p) => {
	await p.goto(STG + '/', { waitUntil: 'load' }); await pause(p, 1800);
	await smooth(p, 700); await pause(p, 1500);
	await p.goto(STG + '/services/', { waitUntil: 'load' }); await pause(p, 1200); await smooth(p, 600); await pause(p, 1300);
	await p.goto(STG + '/doctors/', { waitUntil: 'load' }).catch(() => {}); await pause(p, 1200); await smooth(p, 500); await pause(p, 1300);
	await p.goto(STG + '/patients/', { waitUntil: 'load' }).catch(() => {}); await pause(p, 1500);
});

// 2. The problem, on the LIVE site (read only: options are selected, nothing is submitted).
await scene('problem', async (p) => {
	await p.goto(LIVE + '/book-appointment/', { waitUntil: 'load' }); await pause(p, 1000);
	await toEl(p, '#booking', 120); await pause(p, 1200);
	await p.selectOption('select[name=service]', 'dentistry'); await pause(p, 900);
	await p.evaluate(() => { const d = document.querySelector('select[name=doctor]'); d.size = d.options.length; d.style.height = 'auto'; d.style.outline = '4px solid #dc2626'; d.style.outlineOffset = '3px'; });
	await pause(p, 3500);
	// Next Sunday at "Evening" is accepted by the old form (the clinic is closed on Sundays).
	const sun = await p.evaluate(() => { const d = new Date(); d.setDate(d.getDate() + ((7 - d.getDay()) % 7 || 7)); return d.toISOString().slice(0, 10); });
	await p.fill('input[name=date]', sun); await p.selectOption('select[name=time]', 'evening');
	await p.evaluate(() => { ['input[name=date]', 'select[name=time]'].forEach((s) => { const e = document.querySelector(s); e.style.outline = '4px solid #dc2626'; e.style.outlineOffset = '3px'; }); });
	await toEl(p, 'input[name=date]', 260); await pause(p, 4000);
});

// 3. The solution: new booking form on staging, real request (fictional data).
await scene('solution', async (p) => {
	await p.goto(STG + '/book-appointment/', { waitUntil: 'load' }); await pause(p, 800);
	await toEl(p, '#booking', 110); await pause(p, 900);
	await p.selectOption('#rb-service', 'dentistry'); await pause(p, 500);
	await p.evaluate(() => { const d = document.querySelector('#rb-doctor'); d.size = 3; d.style.height = 'auto'; d.style.outline = '4px solid #0f766e'; d.style.outlineOffset = '3px'; });
	await p.selectOption('#rb-doctor', 'romano'); await pause(p, 1800);
	await p.evaluate(() => { const d = document.querySelector('#rb-doctor'); d.size = 0; d.style.outline = ''; });
	await p.waitForSelector('.rb-slot input'); await toEl(p, '[data-rb-when]', 130); await pause(p, 2200);
	await p.locator('.rb-slot').nth(2).click(); await pause(p, 900);
	await toEl(p, '#rb-name', 160); await pause(p, 500);
	await p.type('#rb-name', 'Grace Demo-Patient', { delay: 25 });
	await p.fill('#rb-email', 'grace.demo@patient.example'); await p.fill('#rb-phone', '(555) 014-7788');
	await p.selectOption('#rb-visit', 'cleaning'); await p.check('input[name=consent]'); await pause(p, 400);
	await toEl(p, '[data-rb-summary]', 220); await pause(p, 1600);
	await p.click('.rb-submit');
	await p.waitForSelector('[data-rb-success]:not([hidden])'); await pause(p, 400);
	await toEl(p, '#booking', 110); await pause(p, 3500);
});

// 4. Reception dashboard + confirm.
await scene('reception', async (p) => {
	await p.goto(STG + '/wp-admin/admin.php?page=rb-reception', { waitUntil: 'load' }); await pause(p, 2200);
	const btn = p.locator('tr:has-text("Grace Demo-Patient") [data-rb-act="confirm"]');
	await btn.scrollIntoViewIfNeeded(); await pause(p, 800);
	await btn.hover(); await pause(p, 400); await btn.click();
	await p.waitForSelector('[data-rb-notice] .notice'); await pause(p, 1200);
	await p.waitForEvent('load').catch(() => {}); await pause(p, 1800);
}, { staff: 'reception.demo' });

// 5. Conflict: two requests for the same time; the second confirmation is refused.
await scene('conflict', async (p) => {
	await p.goto(STG + '/wp-admin/admin.php?page=rb-reception', { waitUntil: 'load' });
	const ids = await p.$$eval('tr:has(.rb-flag--warn)', (rows) => rows.map((r) => r.getAttribute('data-row')));
	await p.evaluate(async (id) => fetch(RB.api + 'appointments/' + id + '/confirm', { method: 'POST', headers: { 'X-WP-Nonce': RB.nonce, 'Content-Type': 'application/json' }, body: '{}' }), ids[0]);
	await p.goto(STG + '/wp-admin/admin.php?page=rb-reception&view=' + ids[1], { waitUntil: 'load' }); await pause(p, 1800);
	await p.click('[data-rb-act="confirm"]');
	await p.waitForSelector('[data-rb-notice] .notice'); await pause(p, 3800);
}, { staff: 'reception.demo' });

// 6. Test outbox: the confirmation message.
await scene('outbox', async (p) => {
	await p.goto(STG + '/wp-admin/admin.php?page=rb-outbox', { waitUntil: 'load' }); await pause(p, 1800);
	await p.locator('a:has-text("Your appointment is confirmed")').first().click();
	await p.waitForLoadState('load'); await pause(p, 1200);
	await smooth(p, 260); await pause(p, 3500);
}, { staff: 'reception.demo' });

// 7. Patient secure link: cancel, the time is released.
await scene('patient', async (p) => {
	const tmp = await p.context().newPage();
	await tmp.goto(STG + '/wp-admin/admin.php?page=rb-outbox', { waitUntil: 'load' });
	await tmp.locator('a:has-text("Your appointment is confirmed")').first().click(); await tmp.waitForLoadState('load');
	const src = await tmp.getAttribute('.rb-mail__frame', 'srcdoc');
	const link = (src.match(/href="([^"]*manage-appointment[^"]*)"/) || [])[1].replace(/&amp;/g, '&');
	await tmp.close();
	p.on('dialog', (d) => d.accept());
	await p.goto(link, { waitUntil: 'load' }); await p.waitForSelector('[data-act="cancel"]'); await pause(p, 2600);
	await p.click('[data-act="cancel"]'); await p.waitForSelector('.rb-success'); await pause(p, 3500);
}, { staff: 'reception.demo' });

await browser.close();
