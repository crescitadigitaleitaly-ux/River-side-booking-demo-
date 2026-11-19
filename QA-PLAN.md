# QA plan: Riverside handover tests (draft for Mouzzam's approval, 19 Nov 2026)

Goal: test the Riverside site the way a web developer does before handing it to a client, and keep **proof screenshots with the scores** (date visible), plus blurred copies for Upwork/Fiverr. Only free tools.

## Quick pre-scan of the live site (read-only, 19 Nov 13:55)

- Security headers missing: HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy (only `upgrade-insecure-requests` is set).
- `/?author=1` returns **HTTP 500** (a server error; should redirect or 404).
- No Open Graph / Twitter tags (link previews on WhatsApp/Facebook look plain).
- No structured data (no MedicalClinic / Physician / FAQ schema), which hurts AI search (AEO) and rich results.
- Good: canonical tag, meta description, sitemap, robots.txt, xmlrpc blocked (405), user list API blocked (403).

## Tests (before → fix → after)

| Area | Tool (free) | What the screenshot shows |
|---|---|---|
| Speed | Google PageSpeed Insights (mobile + desktop), home + booking page | Performance / Accessibility / Best practices / SEO scores, Core Web Vitals |
| Mobile layout | Playwright on real device sizes: iPhone SE 375, iPhone 15 393, Pixel 412, iPad 768, laptop 1440 | Each page on each device, no sideways scroll, booking form usable on a phone |
| SEO | Lighthouse SEO + own checks (titles, descriptions, headings, canonical, sitemap, robots, image alt, broken links) | SEO score, checklist table |
| Tags | Open Graph / Twitter tags check, Schema.org validator, Google Rich Results Test | 0 errors, detected items (clinic, doctors, FAQ) |
| AEO (AI search) | AEO audit (same method as the solar case study) + `llms.txt` | Score before → after |
| Security | securityheaders.com, SSL Labs, Mozilla Observatory, WordPress checks (user enumeration, version leak, xmlrpc, directory listing), our 13 booking tests | Grades (e.g. F → A), SSL grade, checklist |
| Accessibility | Lighthouse + axe-core | Score, 0 serious issues |
| Code quality | W3C HTML validator | Errors before → after |
| Booking system | Our 13 acceptance tests | 13/13 |

## Honest limits

- **Google ranking cannot be proven** for this demo: the clinic is fictional and the site is set to `noindex` on purpose, so it never competes with real clinics. We show **SEO readiness** (scores, tags, schema, sitemap) instead. For a real client we would remove `noindex`. Lighthouse SEO will flag "page is blocked from indexing"; the report explains why.
- Public tools (PageSpeed, Rich Results, securityheaders) can only test the **live** site, not the password-protected staging. So: measure live now (before), fix on staging, deploy to live (needs Mouzzam's OK), measure live again (after).
- Scores are lab measurements on the test date; they can change a bit run to run. We report the median of 3 runs where possible.

## Fixes we expect to make (on staging first)

1. Security headers (.htaccess / small mu-plugin), fix the `?author=` 500, hide WordPress version.
2. Structured data: MedicalClinic + Physician + FAQPage (JSON-LD) in the theme.
3. Open Graph + Twitter tags with a share image.
4. `llms.txt` with the clinic facts (fictional, clearly a demo).
5. Anything the tests find (alt text, contrast, headings, speed).

## Output

- `QUALITY-REPORT.md` (before/after table, dates, tools, settings) + section in TEST-REPORT.md
- Screenshots: Drive `01 Evidence – originals` / `02 platform-safe (blurred)`; report in `03 Test reports`
- Optional: results card added to the video (no new voice credits)
- GitHub commit + CLAUDE.md / AGENTS.md / memory.md updated
