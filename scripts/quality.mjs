// Handover quality tests with proof screenshots (public, free tools), run against the LIVE site.
// Usage: node scripts/quality.mjs <label>   e.g. before | after
// Writes evidence/quality/<label>/*.png and results.json. Read-only: only tools and pages are visited.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js'));
const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const label = process.argv[2] || 'before';
const only = process.argv.slice(3);
const OUT = path.join(ROOT, 'evidence', 'quality', label);
fs.mkdirSync(OUT, { recursive: true });
const HOST = 'dental.mouzzambuild.com';
const SITE = `https://${HOST}`;
const PAGES = { home: '/', book: '/book-appointment/', doctors: '/doctors/', services: '/services/', patients: '/patients/', contact: '/contact/' };
const resFile = path.join(OUT, 'results.json');
const results = fs.existsSync(resFile) ? JSON.parse(fs.readFileSync(resFile, 'utf8')) : { label, site: SITE };
const save = () => fs.writeFileSync(resFile, JSON.stringify(results, null, 2));
const want = (k) => !only.length || only.includes(k);
const stamp = () => new Date().toISOString().replace('T', ' ').slice(0, 16) + ' UTC';

const browser = await pw.chromium.launch({ args: ['--disable-quic'] });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1.5, locale: 'en-US' });
// Small date stamp on every tool screenshot, so the proof shows when it was taken.
await ctx.addInitScript(`(() => { const add = () => { if (document.getElementById('qa-stamp') || !document.body) return; const d = document.createElement('div'); d.id = 'qa-stamp'; d.textContent = 'Captured ${stamp()}'; d.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:2147483647;background:#111;color:#fff;font:600 13px/1.2 Arial;padding:6px 10px;border-radius:6px;opacity:.85'; document.body.appendChild(d); }; document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', add) : add(); })();`);
async function shot(name, url, waitFn, opts = {}) {
	const p = await ctx.newPage();
	try {
		await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
		if (waitFn) { await waitFn(p); }
		await p.waitForTimeout(opts.settle ?? 1500);
		await p.screenshot({ path: path.join(OUT, name + '.png'), fullPage: !!opts.full });
		console.log('shot', name);
		return p;
	} catch (e) { console.log('FAILED', name, e.message.split('\n')[0]); await p.screenshot({ path: path.join(OUT, name + '-FAILED.png') }).catch(() => {}); return p; }
}

// 1. Google PageSpeed Insights: numbers from the public API, screenshots from the web UI.
if (want('psi')) {
	results.psi = {};
	for (const [k, pth] of Object.entries({ home: '/', book: '/book-appointment/' })) {
		for (const strategy of ['mobile', 'desktop']) {
			const api = `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=${encodeURIComponent(SITE + pth)}&strategy=${strategy}&category=performance&category=accessibility&category=best-practices&category=seo`;
			try {
				const j = await (await fetch(api)).json();
				const c = j.lighthouseResult.categories, a = j.lighthouseResult.audits;
				results.psi[`${k}-${strategy}`] = { performance: Math.round(c.performance.score * 100), accessibility: Math.round(c.accessibility.score * 100), bestPractices: Math.round(c['best-practices'].score * 100), seo: Math.round(c.seo.score * 100), lcp: a['largest-contentful-paint'].displayValue, cls: a['cumulative-layout-shift'].displayValue, tbt: a['total-blocking-time'].displayValue, fcp: a['first-contentful-paint'].displayValue, fetched: j.lighthouseResult.fetchTime };
				console.log('psi', k, strategy, JSON.stringify(results.psi[`${k}-${strategy}`]));
			} catch (e) { results.psi[`${k}-${strategy}`] = { error: e.message }; console.log('psi api failed', k, strategy, e.message); }
			save();
		}
	}
	for (const [k, pth] of Object.entries({ home: '/', book: '/book-appointment/' })) {
		for (const ff of ['mobile', 'desktop']) {
			const p = await shot(`psi-${k}-${ff}`, `https://pagespeed.web.dev/analysis?url=${encodeURIComponent(SITE + pth)}&form_factor=${ff}`,
				async (pg) => { await pg.waitForFunction(() => /Diagnose performance issues|Performance/i.test(document.body.innerText) && document.querySelectorAll('.lh-exp-gauge__percentage, .lh-gauge__percentage').length >= 4, null, { timeout: 180000 }); }, { settle: 3000 });
			await p.close();
		}
	}
}

// 2. Security: securityheaders.com, Mozilla Observatory, SSL Labs (grade via API, screenshot via site).
if (want('security')) {
	results.security = results.security || {};
	let p = await shot('security-headers', `https://securityheaders.com/?q=${HOST}&followRedirects=on&hide=on`, async (pg) => { await pg.waitForSelector('.score', { timeout: 60000 }); });
	results.security.securityHeadersGrade = await p.$eval('.score span', (e) => e.textContent.trim()).catch(() => null);
	await p.close();
	p = await shot('security-observatory', `https://developer.mozilla.org/en-US/observatory/analyze?host=${HOST}`, async (pg) => { await pg.waitForFunction(() => /Scan summary|Grade/i.test(document.body.innerText), null, { timeout: 120000 }); }, { settle: 3000 });
	results.security.observatory = await p.evaluate(() => { const t = document.body.innerText; const g = t.match(/\n([A-F][+-]?)\n/); const s = t.match(/Score\s*\n?\s*(\d+)\s*\/\s*100/); return { grade: g ? g[1] : null, score: s ? s[1] : null }; }).catch(() => null);
	await p.close();
	// SSL Labs takes 1–3 minutes; poll the public API politely, then screenshot the result page.
	try {
		let j; const t0 = Date.now();
		do { j = await (await fetch(`https://api.ssllabs.com/api/v3/analyze?host=${HOST}&fromCache=on&maxAge=24&all=done`)).json(); if (j.status !== 'READY' && j.status !== 'ERROR') { await new Promise((r) => setTimeout(r, 15000)); } } while (j.status !== 'READY' && j.status !== 'ERROR' && Date.now() - t0 < 360000);
		results.security.sslLabs = { status: j.status, grades: (j.endpoints || []).map((e) => e.grade) };
		console.log('ssllabs', JSON.stringify(results.security.sslLabs));
	} catch (e) { results.security.sslLabs = { error: e.message }; }
	p = await shot('security-ssllabs', `https://www.ssllabs.com/ssltest/analyze.html?d=${HOST}&hideResults=on`, async (pg) => { await pg.waitForSelector('#rating, .ratingTitle', { timeout: 120000 }); }, { settle: 2500 });
	await p.close();
	// WordPress hardening checks (our own, plain HTTP).
	const head = await fetch(SITE + '/');
	const h = Object.fromEntries(head.headers);
	const status = async (u, o = {}) => (await fetch(SITE + u, { redirect: 'manual', ...o })).status;
	const html = await head.text();
	results.security.headers = ['strict-transport-security', 'content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy'].reduce((o, k) => ({ ...o, [k]: h[k] || null }), {});
	results.security.wordpress = {
		wpVersionInHtml: /<meta name="generator" content="WordPress/i.test(html) || /ver=\d+\.\d+(\.\d+)?/.test(html.match(/wp-includes[^"']*ver=[^"']*/)?.[0] || ''),
		xmlrpc: await status('/xmlrpc.php'), usersApi: await status('/wp-json/wp/v2/users'), authorEnum: await status('/?author=1'),
		readme: await status('/readme.html'), uploadsListing: await status('/wp-content/uploads/'), debugLog: await status('/wp-content/debug.log'), wpConfigBackup: await status('/wp-config.php.bak'),
	};
	console.log('wp checks', JSON.stringify(results.security.wordpress), JSON.stringify(results.security.headers));
	save();
}

// 3. Tags & structured data: our own scan + Schema.org validator + W3C HTML validator.
if (want('tags')) {
	results.tags = {};
	for (const [k, pth] of Object.entries(PAGES)) {
		const html = await (await fetch(SITE + pth)).text();
		const meta = (n) => (html.match(new RegExp(`<meta[^>]+(?:name|property)="${n}"[^>]+content="([^"]*)"`, 'i')) || [])[1] || null;
		const ld = [...html.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)].map((m) => { try { const j = JSON.parse(m[1]); return [].concat(j['@graph'] || j).map((x) => x['@type']).flat(); } catch { return ['INVALID']; } }).flat();
		results.tags[k] = {
			title: (html.match(/<title>([^<]*)<\/title>/) || [])[1] || null, description: meta('description'), canonical: (html.match(/<link rel="canonical" href="([^"]+)"/) || [])[1] || null,
			robots: meta('robots'), ogTitle: meta('og:title'), ogImage: meta('og:image'), twitterCard: meta('twitter:card'),
			h1: (html.match(/<h1[\s>]/g) || []).length, imgNoAlt: (html.match(/<img(?![^>]*\balt=)[^>]*>/g) || []).length, schema: ld,
		};
	}
	console.log('tags', JSON.stringify(results.tags.home));
	save();
	let p = await shot('schema-validator-home', `https://validator.schema.org/#url=${encodeURIComponent(SITE + '/')}`, async (pg) => { await pg.waitForFunction(() => /Detected|No items detected|errors/i.test(document.body.innerText), null, { timeout: 90000 }); }, { settle: 3000 });
	await p.close();
	p = await shot('w3c-html-home', `https://validator.w3.org/nu/?doc=${encodeURIComponent(SITE + '/')}`, async (pg) => { await pg.waitForSelector('#results', { timeout: 90000 }); });
	results.tags.w3cHome = await p.evaluate(() => ({ errors: document.querySelectorAll('#results li.error').length, warnings: document.querySelectorAll('#results li.info.warning').length })).catch(() => null);
	await p.close();
	p = await shot('w3c-html-book', `https://validator.w3.org/nu/?doc=${encodeURIComponent(SITE + '/book-appointment/')}`, async (pg) => { await pg.waitForSelector('#results', { timeout: 90000 }); });
	results.tags.w3cBook = await p.evaluate(() => ({ errors: document.querySelectorAll('#results li.error').length, warnings: document.querySelectorAll('#results li.info.warning').length })).catch(() => null);
	await p.close();
	save();
}

// 4. Mobile layout on real device profiles (viewport screenshots + overflow + tap-target check).
if (want('mobile')) {
	results.mobile = {};
	const devs = { 'iphone-se': pw.devices['iPhone SE'], 'iphone-15': pw.devices['iPhone 15'] || pw.devices['iPhone 14'], 'pixel-7': pw.devices['Pixel 7'], 'ipad': pw.devices['iPad (gen 7)'], 'laptop-1440': { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 } };
	for (const [dn, d] of Object.entries(devs)) {
		const c = await browser.newContext({ ...d });
		for (const [k, pth] of Object.entries({ home: '/', book: '/book-appointment/', doctors: '/doctors/' })) {
			const p = await c.newPage();
			await p.goto(SITE + pth, { waitUntil: 'load', timeout: 90000 });
			await p.waitForTimeout(800);
			const r = await p.evaluate(() => ({ overflowPx: document.documentElement.scrollWidth - document.documentElement.clientWidth, smallTaps: [...document.querySelectorAll('a, button, input, select, textarea')].filter((e) => { const b = e.getBoundingClientRect(); return b.width > 0 && b.height > 0 && (b.height < 24 || b.width < 24); }).length }));
			if (k === 'book') { await p.evaluate(() => { const f = document.querySelector('#booking'); if (f) window.scrollTo(0, f.getBoundingClientRect().top + window.scrollY - 70); }); await p.waitForTimeout(400); }
			await p.screenshot({ path: path.join(OUT, `mobile-${dn}-${k}.png`) });
			results.mobile[`${dn}-${k}`] = r;
			await p.close();
		}
		await c.close();
		console.log('mobile', dn);
	}
	save();
}

await browser.close();
results.finished = stamp();
save();
console.log('done', OUT);
