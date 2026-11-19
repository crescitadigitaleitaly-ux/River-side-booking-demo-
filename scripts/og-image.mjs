// Renders the 1200x630 social share image into the theme.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const b = await pw.chromium.launch(); const p = await b.newPage({ viewport: { width: 1200, height: 630 } });
await p.goto('file:///' + path.join(ROOT, 'video-src/og-image.html').split(path.sep).join('/')); await p.waitForTimeout(500);
await p.screenshot({ path: path.join(ROOT, 'theme/riverside-health/assets/images/og-image.jpg'), type: 'jpeg', quality: 86 }); await b.close(); console.log('ok');
