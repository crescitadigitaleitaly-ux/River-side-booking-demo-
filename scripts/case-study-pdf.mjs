// Renders guide/case-study.html to guide/Riverside-clinic-website-case-study.pdf (A4, 5 pages).
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const b = await pw.chromium.launch(); const p = await b.newPage();
await p.goto('file:///' + path.join(ROOT, 'guide/case-study.html').split(path.sep).join('/'), { waitUntil: 'load' });
await p.pdf({ path: path.join(ROOT, 'guide/Riverside-clinic-website-case-study.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
await b.close(); console.log('pdf ok');
