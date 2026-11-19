# AEOTester report

Generated 2026-11-19 by the [AEOTester](https://aeotester.com/?utm_source=plugin&utm_medium=report) plugin, rubric v1.2.0.

| | |
|---|---|
| Live site | https://dental.mouzzambuild.com |
| Audited | 7 page(s): a sample, one page per template |

> Sampled 7 page(s), one per template (homepage, top-level pages, and a listing plus an item from each section), from 7 URLs found. Use --pages N to change the sample size or --all to audit every page.

> Fetched live from https://dental.mouzzambuild.com with the user agent "Mozilla/5.0 (compatible; AEOTester-Audit/0.2; +https://github.com/shapito27/aeotester-site-audit)".

<details><summary>Pages in the sample</summary>

| Page | Stands in for |
|---|---|
| https://dental.mouzzambuild.com/ | homepage |
| https://dental.mouzzambuild.com/services/ | static page |
| https://dental.mouzzambuild.com/doctors/ | static page |
| https://dental.mouzzambuild.com/patients/ | static page |
| https://dental.mouzzambuild.com/contact/ | static page |
| https://dental.mouzzambuild.com/book-appointment/ | static page |
| https://dental.mouzzambuild.com/privacy-policy/ | static page |

</details>

## Summary

**Score: 113 / 133 (85%) - Nearly Optimized**

Not scored: 6 page(s) kept out of search on purpose (noindex, and the site has no sitemap that lists pages): `https://dental.mouzzambuild.com/services/`, `https://dental.mouzzambuild.com/doctors/`, `https://dental.mouzzambuild.com/patients/`, `https://dental.mouzzambuild.com/contact/`, `https://dental.mouzzambuild.com/book-appointment/`, `https://dental.mouzzambuild.com/privacy-policy/`. They do not need to rank, so page checks leave them out.

| Category | Score |
|---|---:|
| AI crawler access | 18 / 18 |
| Crawlability and indexing | 5 / 18 |
| Structured data | 19 / 20 |
| Meta and social tags | 21 / 22 |
| Content structure and quality | 34 / 36 |
| Agent readiness | 16 / 19 |

## Issues by points lost

### FAIL Indexability - 0 / 8 (-8)

`crawlability.indexability` | auto-fix

Critical indexability issues: Homepage has a noindex directive

- <meta name="robots"> blocks indexing (noindex) - `https://dental.mouzzambuild.com/:6`

Fix: Remove noindex / none from the robots meta tag in production.

### FAIL Sitemap Detection - 0 / 5 (-5)

`crawlability.sitemap` | auto-fix

No sitemap.xml found

- No sitemap at /sitemap.xml, /sitemap_index.xml or any robots.txt Sitemap: URL

Fix: Add a sitemap at /sitemap.xml listing every page and reference it in robots.txt with "Sitemap: https://<domain>/sitemap.xml".

### WARN Agent Protocol Discovery - 3 / 6 (-3)

`agent-readiness.protocols` | assisted

Agent discovery artifacts found: API catalog (RFC 9727), OpenAPI document


Fix: Add more of the artifacts agents look for: an MCP server card at /.well-known/mcp/server-card.json, an API catalog at /.well-known/api-catalog, an OpenAPI document, and OAuth metadata if authentication is required.

### WARN Internal Linking - 3 / 4 (-1)

`content.internal-links` | assisted

45 internal links (19 in content), 82% descriptive

- Generic anchor text "Learn more" on an in-content link - `https://dental.mouzzambuild.com/:82`, `https://dental.mouzzambuild.com/:82`, `https://dental.mouzzambuild.com/:82`, `https://dental.mouzzambuild.com/:82`, `https://dental.mouzzambuild.com/:82` and 3 more

Fix: Add 3+ in-content links to related pages with descriptive anchor text, and replace generic anchors such as "read more" or "click here" with the target page topic.

## Advice (no points)

Worth knowing, but not scored: these can be deliberate choices.

### Markdown for Agents

`agent-readiness.markdown`

- Optional: also answer "Accept: text/markdown" requests with the Markdown copy (Cloudflare "Markdown for Agents", or edge middleware serving the .md file). Markdown copy https://dental.mouzzambuild.com/?format=md works; a request with "Accept: text/markdown" got text/html; charset=utf-8 (HTTP 200) - `https://dental.mouzzambuild.com/:30`

## All checks

| Check | Status | Score | Fix |
|---|---|---:|---|
| AI Bot Crawlability | PASS | 12 / 12 | auto-fix |
| llms.txt Detection | PASS | 6 / 6 | auto-fix |
| Content Signals | PASS | not scored | auto-fix |
| Indexability | FAIL | 0 / 8 | auto-fix |
| Sitemap Detection | FAIL | 0 / 5 | auto-fix |
| SSL / HTTPS | PASS | 2 / 2 | assisted |
| HTTP Behavior | PASS | 3 / 3 | assisted |
| Structured Data | PASS | 14 / 15 | auto-fix |
| Schema Validation | PASS | 5 / 5 | auto-fix |
| Meta Title | PASS | 4 / 5 | auto-fix |
| Meta Description | PASS | 5 / 5 | auto-fix |
| Open Graph | PASS | 3 / 3 | auto-fix |
| Twitter Cards | PASS | 3 / 3 | auto-fix |
| Language Tags | PASS | 3 / 3 | auto-fix |
| Mobile Friendly | PASS | 3 / 3 | auto-fix |
| Semantic Headings | PASS | 8 / 8 | auto-fix |
| Content Quality | PASS | 5 / 5 | assisted |
| Content Freshness | PASS | 5 / 5 | assisted |
| Author Signals | PASS | 4 / 5 | assisted |
| Image Alt Text | PASS | 5 / 5 | assisted |
| Internal Linking | WARN | 3 / 4 | assisted |
| Accessibility | PASS | 4 / 4 | auto-fix |
| Server-Rendered Content | PASS | 6 / 6 | assisted |
| Markdown for Agents | PASS | 3 / 3 | assisted |
| Agent-Usable Controls | PASS | 4 / 4 | assisted |
| Agent Protocol Discovery | WARN | 3 / 6 | assisted |

## Notes

- Scores come from the live site as a crawler sees it: the HTML the server sends, before any JavaScript runs. Checks marked "inconclusive" could not be read (blocked, rate limited or timed out) and were not scored down.
- To fix issues, open the site's code in your coding agent (Claude Code, Codex) and run the AEOTester fix skill (`/aeotester:fix` in Claude Code) there. It shows a diff and asks before writing anything.

---

Check a live URL: https://aeotester.com/?utm_source=plugin&utm_medium=report
