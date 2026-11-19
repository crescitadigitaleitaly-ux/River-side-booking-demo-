# AEOTester report

Generated 2026-11-19 by the [AEOTester](https://aeotester.com/?utm_source=plugin&utm_medium=report) plugin, rubric v1.2.0.

| | |
|---|---|
| Live site | https://dental.mouzzambuild.com |
| Audited | 10 page(s): a sample, one page per template, from 11 URLs found |

> Sampled 10 page(s), one per template (homepage, top-level pages, and a listing plus an item from each section), from 11 URLs found. Use --pages N to change the sample size or --all to audit every page.

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
| https://dental.mouzzambuild.com/hello-world/ | static page |
| https://dental.mouzzambuild.com/hello-world-2/ | static page |
| https://dental.mouzzambuild.com/category/blog/ | tag, category or pagination page |

</details>

## Summary

**Score: 78 / 133 (59%) - Not AEO Ready**

| Category | Score |
|---|---:|
| AI crawler access | 18 / 18 |
| Crawlability and indexing | 10 / 18 |
| Structured data | 0 / 20 |
| Meta and social tags | 12.4 / 22 |
| Content structure and quality | 27.4 / 36 |
| Agent readiness | 10 / 19 |

## Issues by points lost

### FAIL Structured Data - 0 / 15 (-15)

`structured-data.present` | auto-fix

No structured data found

- No structured data (JSON-LD, Microdata or RDFa) - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Pages losing points: 10 of 10

Fix: Add JSON-LD structured data (Organization on every page, plus Article, FAQPage or Product where they fit).

### FAIL Indexability - 0 / 8 (-8)

`crawlability.indexability` | auto-fix

Critical indexability issues: Page has a noindex directive but is listed in the sitemap

- <meta name="robots"> blocks indexing (noindex) - `https://dental.mouzzambuild.com/:6`, `https://dental.mouzzambuild.com/services/:6`, `https://dental.mouzzambuild.com/doctors/:6`, `https://dental.mouzzambuild.com/patients/:6`, `https://dental.mouzzambuild.com/contact/:6` and 5 more
- noindex page is listed in the sitemap: the sitemap asks engines to index it, the page tells them not to - `https://dental.mouzzambuild.com/services/`, `https://dental.mouzzambuild.com/doctors/`, `https://dental.mouzzambuild.com/patients/`, `https://dental.mouzzambuild.com/contact/`, `https://dental.mouzzambuild.com/book-appointment/` and 4 more
- No canonical tag found - `https://dental.mouzzambuild.com/category/blog/:3`
- Pages losing points: 10 of 10

Fix: Remove noindex / none from the robots meta tag in production.

### FAIL Agent Protocol Discovery - 0 / 6 (-6)

`agent-readiness.protocols` | assisted

API surface detected but no agent discovery artifacts published

- No agent discovery files under /.well-known/ (MCP server card, API catalog, OpenAPI, OAuth metadata)
- API surface evidence: link rel="describedby" -> https://dental.mouzzambuild.com/llms.txt - `https://dental.mouzzambuild.com/:63`

Fix: Publish an MCP server card at /.well-known/mcp/server-card.json, an API catalog at /.well-known/api-catalog (RFC 9727) or an OpenAPI document, plus OAuth metadata at /.well-known/oauth-protected-resource if authentication is required.

### FAIL Schema Validation - 0 / 5 (-5)

`structured-data.valid` | auto-fix

No JSON-LD structured data to validate

- No JSON-LD structured data to validate - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Pages losing points: 10 of 10

Fix: Add JSON-LD structured data first, then validation can be checked.

### FAIL Content Freshness - 0 / 5 (-5)

`content.freshness` | assisted

No publish or modified dates found

- No publish or modified date (article:published_time, JSON-LD datePublished or <time datetime>) - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Pages losing points: 10 of 10

Fix: Add article:published_time and article:modified_time meta tags, or JSON-LD datePublished/dateModified.

### FAIL Open Graph - 0 / 3 (-3)

`meta.open-graph` | auto-fix

Missing 3 required OG tag(s)

- Missing og:title - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing og:description - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing og:image - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing og:url - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing og:type (use "website" or "article") - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Pages losing points: 10 of 10

Fix: Add og:title, og:description, og:image (absolute URL), og:type and og:url (equal to the canonical URL) as <meta property="og:..."> in the shared head.

### FAIL Twitter Cards - 0 / 3 (-3)

`meta.twitter-cards` | auto-fix

Missing 3 required Twitter tag(s)

- Missing twitter:card - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing twitter:title - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing twitter:description - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Missing twitter:image - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 5 more
- Pages losing points: 10 of 10

Fix: Add twitter:card (summary_large_image), twitter:title, twitter:description and twitter:image to the shared head, filled from the same values as the title, description and og:image.

### FAIL Markdown for Agents - 0 / 3 (-3)

`agent-readiness.markdown` | assisted

No Markdown representation for agents: "Accept: text/markdown" returns HTML

- A request with "Accept: text/markdown" got text/html; charset=utf-8 (HTTP 200) and no working markdown alternate is advertised - `https://dental.mouzzambuild.com/:3`, `https://dental.mouzzambuild.com/services/:3`, `https://dental.mouzzambuild.com/doctors/:3`, `https://dental.mouzzambuild.com/patients/:3`, `https://dental.mouzzambuild.com/contact/:3` and 3 more
- A request with "Accept: text/markdown" got text/html; charset=utf-8 (HTTP 500) and no working markdown alternate is advertised - `https://dental.mouzzambuild.com/hello-world-2/:3`, `https://dental.mouzzambuild.com/category/blog/:3`
- Pages losing points: 10 of 10

Fix: Answer "Accept: text/markdown" with a Markdown version and Content-Type: text/markdown (Cloudflare offers this as "Markdown for Agents", or use edge middleware), and advertise it with <link rel="alternate" type="text/markdown" href="..."> or a Link header.

### WARN Meta Description - 3 / 5 (-2)

`meta.description` | auto-fix

No meta description found

- Description could be longer (70-119 chars, optimal 120-160) - `https://dental.mouzzambuild.com/doctors/:10`, `https://dental.mouzzambuild.com/patients/:10`, `https://dental.mouzzambuild.com/contact/:10`, `https://dental.mouzzambuild.com/book-appointment/:10`, `https://dental.mouzzambuild.com/privacy-policy/:10`
- Missing <meta name="description"> - `https://dental.mouzzambuild.com/hello-world/:3`, `https://dental.mouzzambuild.com/hello-world-2/:3`, `https://dental.mouzzambuild.com/category/blog/:3`
- Pages losing points: 8 of 10

Fix: Write a unique, specific meta description of 120-160 characters that summarizes the page (not a copy of the title).

### WARN Author Signals - 3 / 5 (-2)

`content.author` | assisted

Author: "Mouzzam Builds" + author bio. Missing: expertise credentials

- No author bio section or link to an author/about page - `https://dental.mouzzambuild.com/:69`, `https://dental.mouzzambuild.com/services/:73`, `https://dental.mouzzambuild.com/doctors/:73`
- Fewer than 3 trust signals; missing: /about, /terms, <address> - `https://dental.mouzzambuild.com/:69`, `https://dental.mouzzambuild.com/services/:73`, `https://dental.mouzzambuild.com/doctors/:73`, `https://dental.mouzzambuild.com/patients/:73`, `https://dental.mouzzambuild.com/contact/:73` and 5 more
- No expertise signals (credentials, job title, years of experience) near the author or in the page text - `https://dental.mouzzambuild.com/patients/:75`, `https://dental.mouzzambuild.com/contact/:75`, `https://dental.mouzzambuild.com/book-appointment/:75`, `https://dental.mouzzambuild.com/privacy-policy/:76`, `https://dental.mouzzambuild.com/hello-world/:83` and 2 more
- Pages losing points: 10 of 10

Fix: Add a JSON-LD Person (name, jobTitle, sameAs) as the author. Add an author bio section with background and qualifications

### WARN Meta Title - 3.4 / 5 (-1.6)

`meta.title` | auto-fix

Title: 45 chars - 1 issue(s)

- Title slightly long (64 chars, optimal 50-60): "Riverside Family Health – Family Doctors, Pediatrics & Dentistry" - `https://dental.mouzzambuild.com/:11`
- Title too short (34 chars, aim for 50-60): "Services – Riverside Family Health" - `https://dental.mouzzambuild.com/services/:11`
- Title too short (37 chars, aim for 50-60): "Our Doctors – Riverside Family Health" - `https://dental.mouzzambuild.com/doctors/:11`
- Title could be longer (45 chars, optimal 50-60): "Patient Information – Riverside Family Health" - `https://dental.mouzzambuild.com/patients/:11`
- Title too short (36 chars, aim for 50-60): "Contact Us – Riverside Family Health" - `https://dental.mouzzambuild.com/contact/:11`
- Title could be longer (45 chars, optimal 50-60): "Book an Appointment – Riverside Family Health" - `https://dental.mouzzambuild.com/book-appointment/:11`
- Title could be longer (40 chars, optimal 50-60): "Privacy Policy – Riverside Family Health" - `https://dental.mouzzambuild.com/privacy-policy/:11`
- Title too short (38 chars, aim for 50-60): "Hello world! – Riverside Family Health" - `https://dental.mouzzambuild.com/hello-world/:10`
- ...and 2 more finding(s)
- Pages losing points: 10 of 10

Fix: Rewrite the title to 50-60 characters, specific to the page, as "Page Topic \| Brand".

### WARN Content Quality - 3.5 / 5 (-1.5)

`content.quality` | assisted

163 words (general page) - needs improvement

- Content could be more substantial (general page: under 200 words) - `https://dental.mouzzambuild.com/book-appointment/:82`
- Thin content (legal page: under 198 words, aim for 300+) - `https://dental.mouzzambuild.com/privacy-policy/:83`
- Very thin content (general page: under 66 words, aim for 200+) - `https://dental.mouzzambuild.com/hello-world/:90`, `https://dental.mouzzambuild.com/hello-world-2/:90`
- Page has minimal content (under 50 words) - may be an empty shell or loading state - `https://dental.mouzzambuild.com/hello-world/:90`, `https://dental.mouzzambuild.com/hello-world-2/:90`, `https://dental.mouzzambuild.com/category/blog/:95`
- Very thin content (blog page: under 132 words, aim for 400+) - `https://dental.mouzzambuild.com/category/blog/:95`
- Pages losing points: 5 of 10

Fix: Expand the main content to at least 200 words (general page), split it into 3+ real <p> paragraphs, and wrap it in <main> or <article>.

## Advice (no points)

Worth knowing, but not scored: these can be deliberate choices.

### Content Signals

`ai-access.content-signals`

- robots.txt has no Content-Signal line - `https://dental.mouzzambuild.com/robots.txt:1`

Suggestion: Add a line such as "Content-Signal: search=yes, ai-input=yes, ai-train=no" to robots.txt to state how AI may use your content (the yes/no values are your policy choice). See https://contentsignals.org/

## All checks

| Check | Status | Score | Fix |
|---|---|---:|---|
| AI Bot Crawlability | PASS | 12 / 12 | auto-fix |
| llms.txt Detection | PASS | 6 / 6 | auto-fix |
| Content Signals | ADVICE | not scored | auto-fix |
| Indexability | FAIL | 0 / 8 | auto-fix |
| Sitemap Detection | PASS | 5 / 5 | auto-fix |
| SSL / HTTPS | PASS | 2 / 2 | assisted |
| HTTP Behavior | PASS | 3 / 3 | assisted |
| Structured Data | FAIL | 0 / 15 | auto-fix |
| Schema Validation | FAIL | 0 / 5 | auto-fix |
| Meta Title | WARN | 3.4 / 5 | auto-fix |
| Meta Description | WARN | 3 / 5 | auto-fix |
| Open Graph | FAIL | 0 / 3 | auto-fix |
| Twitter Cards | FAIL | 0 / 3 | auto-fix |
| Language Tags | PASS | 3 / 3 | auto-fix |
| Mobile Friendly | PASS | 3 / 3 | auto-fix |
| Semantic Headings | PASS | 8 / 8 | auto-fix |
| Content Quality | WARN | 3.5 / 5 | assisted |
| Content Freshness | FAIL | 0 / 5 | assisted |
| Author Signals | WARN | 3 / 5 | assisted |
| Image Alt Text | PASS | 5 / 5 | assisted |
| Internal Linking | PASS | 3.9 / 4 | assisted |
| Accessibility | PASS | 4 / 4 | auto-fix |
| Server-Rendered Content | PASS | 6 / 6 | assisted |
| Markdown for Agents | FAIL | 0 / 3 | assisted |
| Agent-Usable Controls | PASS | 4 / 4 | assisted |
| Agent Protocol Discovery | FAIL | 0 / 6 | assisted |

## Notes

- Scores come from the live site as a crawler sees it: the HTML the server sends, before any JavaScript runs. Checks marked "inconclusive" could not be read (blocked, rate limited or timed out) and were not scored down.
- To fix issues, open the site's code in your coding agent (Claude Code, Codex) and run the AEOTester fix skill (`/aeotester:fix` in Claude Code) there. It shows a diff and asks before writing anything.

---

Check a live URL: https://aeotester.com/?utm_source=plugin&utm_medium=report
