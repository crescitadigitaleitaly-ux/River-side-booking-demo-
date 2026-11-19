# Quality report: handover tests (live site, 19 Nov 2026)

Site: Riverside Family Health demo (fictional clinic), live WordPress site on Hostinger.
**Before** = live site on 19 Nov ~14:15 (Lisbon time), before the upgrade. **After** = same site ~14:50 after deploying the booking system + security/SEO/AEO fixes.
All tools are free and public. Screenshots: `evidence/quality/before/`, `evidence/quality/after/` (originals) and `evidence/quality/safe/` (website address, IPs and account avatar blurred for Upwork/Fiverr). Machine data: `results.json` in each folder.

## Results

| Test (tool) | Before | After |
|---|---|---|
| Speed – home, mobile (Google PageSpeed Insights) | 100 performance · 100 accessibility · 100 best practices · 61 SEO | 100 · 100 · 100 · 61 |
| Speed – home, desktop | 100 · 100 · 100 · 61 | 100 · 100 · 100 · 61 |
| Speed – booking page, mobile | 100 · 100 · 100 · 66 | **100 · 100 · 100 · 66 with the full booking system** |
| Speed – booking page, desktop | 100 · 100 · 100 · 66 | 100 · 100 · 100 · 66 |
| Security headers (securityheaders.com) | **D** (5 headers missing) | **A** (all 6 headers present) |
| Mozilla HTTP Observatory | **D – 35/100** (8/12 tests) | **B+ – 80/100** (11/12 tests) |
| SSL/TLS (Qualys SSL Labs) | A (all servers) | **A+** (all servers) |
| Structured data (Schema.org validator, home) | No items detected | **8 items, 0 errors, 0 warnings** (clinic, page, 6 doctors, FAQ) |
| Google Rich Results Test (/patients/) | – | Breadcrumbs, Local business, Organization: **valid** |
| HTML code (W3C validator) | home 0 errors · booking **1 error** | home 0 · booking **0 errors** |
| AI-search readiness (AEOTester audit) | **59% – 78/133** "Not AEO Ready" | **85% – 113/133** "Nearly Optimized" |
| Booking system (our Playwright tests, staging) | – | **13/13 passed** |
| Mobile layout (iPhone SE, iPhone 15, Pixel 7, iPad, laptop 1440 px; home, booking, doctors) | 0 px sideways scroll | 0 px sideways scroll; booking form usable on every size |
| WordPress hardening (own checks) | `?author=1` → **500 error** · `readme.html` public (shows WP version) | `?author=1` → 301 redirect · `readme.html` 403 · xmlrpc 405 · user API 403 · no WP version in source |

Public report links (PageSpeed, after): home https://pagespeed.web.dev/analysis/https-dental-mouzzambuild-com/ouu1lwqf9g · booking https://pagespeed.web.dev/analysis/https-dental-mouzzambuild-com-book-appointment/2oh94lz3fi

## Why the SEO score stays at 61–66 (honest note)

The clinic is fictional, so the site sends `noindex` on purpose: it must never appear in Google next to real clinics. Lighthouse SEO gives a heavy penalty for "page is blocked from indexing", and the AEO audit loses 13 points for the same reason (noindex + no sitemap). Everything else that SEO needs is in place: unique titles (50–60 characters) and descriptions (120–160), canonical tags, Open Graph/Twitter tags with a share image, structured data with 0 errors, valid HTML, fast mobile pages. **On a real client site, removing `noindex` and turning the sitemap back on is a one-line change.** Google rankings cannot be measured for a noindex demo, so none are claimed.

## What was changed

- **Security** (`plugin/riverside-hardening`): HSTS, Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, COOP; `?author=` probing redirected (was a 500 page); WordPress version hidden; emoji script removed (blocked by CSP, saves bytes); `readme.html`/`license.txt` blocked in `.htaccess`.
- **SEO/tags/AEO** (`theme/riverside-health/inc/seo.php`): page titles and descriptions; Open Graph + Twitter Card with a 1200×630 share image; JSON-LD (MedicalClinic, WebSite, WebPage with dates, BreadcrumbList, Person ×6, Service ×8, FAQPage); Markdown copy of each page (`?format=md`) for AI tools; accurate `llms.txt` (the auto-generated one had a broken character); `Content-Signal` line in robots.txt; sitemap off (demo is noindex); footer address in an `<address>` tag.
- **Booking system**: API catalog + OpenAPI description of the public availability API at `/.well-known/` for AI agents.
- **Cleanup**: leftover "Hello world" posts set to draft.

## Method and limits

- PageSpeed, securityheaders, Observatory, SSL Labs, Schema validator, Rich Results and W3C were run in a normal Chrome browser (these services block automated browsers). Mobile screenshots: Playwright with real device profiles.
- Scores are lab measurements on the date above and can vary slightly between runs.
- "Capped at A" (securityheaders) and the last Observatory point: the CSP still allows inline scripts/styles, which the theme uses for speed. Next step for an A+: move the ~20 lines of inline JS to a file and use nonces.
- Not tested: load testing with many users at once; real email delivery (all messages go to the test outbox by design).
- Backup taken before go-live: theme files + full database dump of the live site (stored outside the public folder on the server).
