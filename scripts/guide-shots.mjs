// Screenshots of the everyday editing screens for the client guide (staging, clinic manager account).
// Contact details are masked server-side (recording mode cookie), so the guide is platform-safe.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import fs from 'node:fs'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const OUT = path.join(ROOT, 'guide', 'img'); fs.mkdirSync(OUT, { recursive: true });
const STG = 'https://mouzzambuild.com/riverside-staging';
const [U, P] = process.env.STG_AUTH.split(':'); const KEY = fs.readFileSync(process.env.STG_KEY_FILE, 'utf8').trim();
const b = await pw.chromium.launch({ args: ['--disable-quic'] });
const c = await b.newContext({ viewport: { width: 1366, height: 860 }, deviceScaleFactor: 1.5, httpCredentials: { username: U, password: P } });
await c.addCookies([{ name: 'rb_rec', value: '1', domain: 'mouzzambuild.com', path: '/riverside-staging', secure: true }]);
await c.request.fetch(`${STG}/?rbstg=login&as=manager.demo`, { headers: { 'X-RB-STG': KEY } });
const p = await c.newPage();
const shot = async (url, name, opts = {}) => { await p.goto(STG + url, { waitUntil: 'load' }); if (opts.before) { await opts.before(); } await p.waitForTimeout(500); await p.screenshot({ path: path.join(OUT, name + '.png'), fullPage: !!opts.full, clip: opts.clip }); console.log(name); };
await shot('/wp-admin/admin.php?page=rb-clinic-info', '1-clinic-details', { full: true, before: async () => { await p.addStyleTag({ content: '#rb-maps{color:transparent!important;text-shadow:0 0 8px rgba(0,0,0,.6)}' }); } });
await shot('/wp-admin/edit.php?post_type=rb_doctor', '2-doctors-list');
const docId = await p.$eval('#the-list tr a.row-title', (a) => new URL(a.href).searchParams.get('post'));
await shot(`/wp-admin/post.php?post=${docId}&action=edit`, '3-doctor-hours', { before: async () => { await p.evaluate(() => document.querySelector('#rb_doctor_hours')?.scrollIntoView()); } });
await shot('/wp-admin/admin.php?page=rb-closures', '4-closures');
await shot('/wp-admin/admin.php?page=rb-reception', '5-reception');
await b.close();
