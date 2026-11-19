// Renders video/cards/cards.html to 1920x1080 PNGs (one per card).
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const b = await pw.chromium.launch(); const p = await b.newPage({ viewport: { width: 1920, height: 1080 } });
for (const c of ['title', 'tech', 'value', 'tests', 'outro']) {
  await p.goto('file:///' + path.join(ROOT, 'video/cards/cards.html').split(path.sep).join('/') + '?card=' + c);
  await p.screenshot({ path: path.join(ROOT, 'video/cards', c + '.png') }); console.log(c);
}
await b.close();
