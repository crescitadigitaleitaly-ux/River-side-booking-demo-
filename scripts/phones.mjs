// Phone-size screenshots of the live site with contact details masked (Upwork/Fiverr rules), for the video collage.
import { createRequire } from 'node:module'; import { execSync } from 'node:child_process'; import path from 'node:path';
const require = createRequire(path.join(execSync('npm root -g').toString().trim(), 'noop.js')); const pw = require('playwright');
const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1')), '..');
const SITE = 'https://dental.mouzzambuild.com';
const MASK = () => {
  const RX = /@|\(555\)|555-|mouzzambuild|https?:|www\.|\.example/i;
  const s = document.createElement('style'); s.textContent = '.qa-mask,.qa-mask *{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:0 0 9px rgba(20,30,40,.75)!important}'; document.head.appendChild(s);
  const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
  while ((n = tw.nextNode())) { if (RX.test(n.nodeValue) && n.parentElement) n.parentElement.classList.add('qa-mask'); }
  document.querySelectorAll('a[href^="tel:"],a[href^="mailto:"]').forEach((a) => a.classList.add('qa-mask'));
};
const shots = [['iPhone SE', '/book-appointment/', 'phone-1'], ['iPhone 15', '/', 'phone-2'], ['Pixel 7', '/doctors/', 'phone-3'], ['iPad (gen 7)', '/book-appointment/', 'phone-4']];
const b = await pw.chromium.launch({ args: ['--disable-quic'] });
for (const [dev, pth, name] of shots) {
  const c = await b.newContext({ ...(pw.devices[dev] || pw.devices['iPhone 14']) }); const p = await c.newPage();
  await p.goto(SITE + pth, { waitUntil: 'load' }); await p.waitForTimeout(800); await p.evaluate(MASK);
  if (pth.includes('book')) { await p.evaluate(() => { const f = document.querySelector('#booking'); if (f) window.scrollTo(0, f.getBoundingClientRect().top + window.scrollY - 70); }); await p.waitForTimeout(300); }
  await p.screenshot({ path: path.join(ROOT, 'video/cards', name + '.png') }); await c.close(); console.log(name);
}
await b.close();
