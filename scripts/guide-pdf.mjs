// Renders guide/guide.html to guide/How-to-update-your-website.pdf (A4).
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const b = await pw.chromium.launch(); const p = await b.newPage();
await p.goto('file:///' + path.join(ROOT, 'guide/guide.html').split(path.sep).join('/'), { waitUntil: 'load' });
await p.pdf({ path: path.join(ROOT, 'guide/How-to-update-your-website.pdf'), format: 'A4', printBackground: true });
await b.close(); console.log('pdf ok');
