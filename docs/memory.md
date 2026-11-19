# memory.md: Mouzzam's business, working rules and project index

A shared, living file. Any AI tool (Claude Code, Codex, others) should read this **first**, then the project's own `CLAUDE.md` / `AGENTS.md`.
Codex onboarding (connections, playbooks, skills map, how to split work with Claude): `Desktop\projects\codex-onboarding\` (backup in the hub repo, folder `codex-onboarding/`).
Mouzzam and the assistant improve it together. When something changes, edit it here and note the date in the change log at the bottom.
Last updated: 19 Nov 2026.

---

## 1. Who and what

- **Mouzzam Channa**: independent web developer. Business name: **Crescita Digitale Italia** (website crescitadigitaleitalia.com). Portfolio site: mouzzambuild.com.
- **Selling on:** Upwork and Fiverr (Fiverr seller name `mouzzam_channa`).
- **Goal right now:** win the first paid jobs. Everything should look professional, honest and measured, and work as portfolio proof.
- **Main services shown:** WordPress sites (custom block themes, Elementor), bug fixing and speed work, forms and booking systems, WooCommerce, custom-coded Next.js demos with Supabase, case-study videos.
- **To fill in together:** country and time zone for clients, prices and packages, target niches, preferred job types, business email to use on commits.

## 2. Working rules (always)

1. **Research and plan first.** A new task starts with a written plan, a check of what is already available, and only free tools that fit his laptop. Build only after his OK.
2. **Act like a senior developer, designer, QA engineer and video editor.** Guide him, and correct him with the reason when an idea is weak.
3. **Honesty.** Never invent results, clients, reviews or scores. Demo companies are clearly fictional, and test data is labelled as test data. Lab numbers always come with date, tool and settings.
4. **Staging first.** Never change a live/production site without his explicit instruction. Use a staging copy (e.g. `mouzzambuild.com/<name>-staging/` with `stg_` tables), a password, and a mail block.
5. **Every project has:**
   - `CLAUDE.md` **and** `AGENTS.md` (the same full context, so the work can continue in Codex): goal, plan, done, left, where files, servers and repos are, rules, how to run tests.
   - A copy of the Codex skill `.codex/skills/mouzzam-way/SKILL.md` (from the hub repo), so Codex Cloud knows these rules.
   - **Code on GitHub**, committed as work progresses.
   - **A Google Drive folder named after the project** for screenshots, videos, reports and documents, plus copies of `CLAUDE.md`, `AGENTS.md` and the plan.
   - Local copies of heavy files are deleted only after the Drive upload is checked **and** he agrees.
6. **Save tokens.** Use the cheapest model or agent that fits the job: smaller models or subagents for searching, uploads, bulk edits and test runs; the strongest model for design and tricky logic.
7. **Free tools only**, unless he decides otherwise. Mention free tools that would help.
8. **Language:** short, simple English. If he writes in Urdu, reply in **Roman Urdu** (never Hindi script).

## 3. Platform content rules (screenshots, videos, portfolio)

- Follow **both Upwork and Fiverr** rules. Fiverr is stricter: blur every email, phone number (even fake ones), WhatsApp and website/off-platform URL.
- No platform names in videos. Call to action: **"send me a message"**.
- Never show "Crescita Digitale Italia" in Fiverr material.
- Video style: no zoom or pan, round face bubble bottom-right, quiet music (about -21 dB, final -16 LUFS), ElevenLabs voice clone (state the credit cost first, 1 take first), 1920×1080, sharp captures (scale 1.5–2).

## 4. Machine and tools

- Laptop: Windows 11, 8 GB RAM, Intel UHD graphics, **no admin rights**, low free space. 5G internet, which can be slow (use `--disable-quic` in Chromium).
- Installed: Node 24, Python 3.12, FFmpeg, faster-whisper, Playwright + Chromium, OBS portable. **Not installed:** PHP, `gh` CLI.
- Not suitable: DaVinci Resolve. Use Clipchamp or Shotcut portable instead.

## 5. Hosting, sites and repos

- **Hostinger** account with WordPress sites under `domains/mouzzambuild.com/public_html/`: main portfolio, `hvac`, `dental` (Riverside), `skincare` (HEBE), plus staging folders.
- **Novamira MCP** is connected to the **production** main site: use it carefully, preferably only for staging work and read-only checks.
- **GitHub** organisation/user `crescitadigitaleitaly-ux`. Hub repo for live-site notes and backups: `-private-repo-live-websites` (local `Desktop\projects\live-websites`). After any site change, update that site's `CLAUDE.md` there and push.
- **Vercel** for the custom-coded demos (repo `Desktop\demo-websites-repo` / `bussiness-demo-website-repo`). **Supabase** for the solar quote system.
- Known gap: `mouzzambuild.com` has no SPF/DMARC/MX records, so mail from the sites is unreliable.

## 5b. Site register: every live website, its code and its content

Master inventory: `Desktop\projects\live-websites\README.md` (private GitHub repo `crescitadigitaleitaly-ux/-private-repo-live-websites`). Each site has its own `CLAUDE.md` there with full details. Checked 16 Nov 2026.

| Site | Live link | Code lives in | Content / backup lives in |
|---|---|---|---|
| Mouzzam Builds (portfolio) | https://mouzzambuild.com | Theme `mouzzam-builds` on Hostinger `public_html/wp-content/themes/` (backup in hub) | WordPress DB + hub `hostinger/mouzzambuild/` |
| Brightline Comfort HVAC (demo) | https://hvac.mouzzambuild.com | Astra + Elementor on Hostinger `public_html/hvac` (no own repo) | WordPress DB + hub `hostinger/hvac/`; case study `Desktop\projects\hvac-case-study` |
| Riverside Family Health (medical demo) | https://dental.mouzzambuild.com · staging https://mouzzambuild.com/riverside-staging/ (password) | Theme `riverside-health` (hub `hostinger/riverside-health/theme`); booking plugin in `Desktop\projects\riverside-booking-case-study` → repo `River-side-booking-demo-` (https://github.com/crescitadigitaleitaly-ux/River-side-booking-demo-) | Copy in theme `inc/data.php` / `sections.php`; Drive "Riverside Family Health – Appointment Demo" |
| HEBE skincare shop (demo) | https://skincare.mouzzambuild.com | Theme `hebe` + WooCommerce on Hostinger `public_html/skincare` | WooCommerce DB + hub `hostinger/hebe/` |
| Driftline Workshop (concept) | https://driftline.mouzzambuild.com | GitHub repo `demo-project-` | hub `hostinger/driftline/` |
| AERVEK (static) | https://aervek.mouzzambuild.com | Static HTML on Hostinger | hub `hostinger/static/aervek/` |
| The Relational Practice (static) | https://therapy.mouzzambuild.com | Static HTML on Hostinger | hub `hostinger/static/therapy/` |
| Club Giraffe (static, no subdomain) | files only in `public_html/club-giraffe` | Static HTML | hub `hostinger/static/club-giraffe/` |
| Crescita Digitale Italia (agency) | https://www.crescitadigitaleitalia.com | Repo `My-Portfolio-web` (Vite + React) on Vercel | hub `vercel/crescita-agency/` |
| 4 sector demos (Arvenis, Lumea, Aurevo solar, Lanterna) | `*-demo.crescitadigitaleitalia.com` | Repo `bussiness-demo-website-repo` (Next.js 16) on Vercel, local `Desktop\demo-websites-repo` | hub `vercel/demo-sites/`; solar case study `Desktop\projects\solar-quote-case-study` + Drive "Solar Quote Case Study - Aurevo Energia" |
| Restaurants (Acqua & Sale, Da Nicola, Zio Pesce) | `*.vercel.app` | Separate repos | hub `vercel/restaurants/` |
| MODA VIVA (Shopify) | https://djr21m-hk.myshopify.com | Shopify theme | hub `shopify/moda-viva/` |

How Claude reaches each place:
- **Hostinger WordPress:** Novamira MCP on the main site (it can read sibling installs). It touches **production**, so be careful and use staging.
- **GitHub:** local clones plus his git login (push works; creating repos he does on github.com).
- **Vercel:** Vercel connector, or a git push to `main` (auto-deploys).
- **Supabase:** Supabase connector (solar project).
- **Drive:** Google Drive connector (upload ≤ ~10 MB per file).
- **Shopify:** Shopify connector (when signed in).

Keep this table and the hub README in sync whenever a site is added, moved or deleted.

## 6. Project index (status in each project's CLAUDE.md)

| Project | Folder | Status (19 Nov 2026) |
|---|---|---|
| Riverside Family Health: booking + reception demo | `Desktop\projects\riverside-booking-case-study` | LIVE 19 Nov; quality-tested (security D to A, SSL A+, AEO 59 to 85%); final video with results; staff content editing (hours, phone, notice, doctor availability) live + client PDF guide; next: optional video scene |
| Solar quote system case study | `Desktop\projects\solar-quote-case-study` | Built, tested, video made; Drive upload in progress |
| HVAC WordPress bug-fix case study | `Desktop\projects\hvac-case-study` | Done; waiting for his review; `hvac-staging` to delete after OK |
| Portfolio repair (12 demo sites) | progress log `Desktop\portfolio-repair-progress.md` | One site at a time, approval after each |
| Fiverr gig video | `Downloads\New folder (3)\edited` | Done (72 s) |
| Lanterna restaurant CMS admin | demo repo `04-restaurant` | Built and pushed |

## 7. Lessons learned (add as we go)

- Public test tools (PageSpeed Insights, securityheaders.com, Mozilla Observatory, SSL Labs, W3C, Rich Results) refuse automated browsers: run them in his real Chrome via Claude in Chrome and keep the report link + screenshot.
- A strict Content-Security-Policy blocks the WordPress emoji script (blob worker): remove the emoji script instead of loosening the CSP.
- Demo sites stay `noindex` (fictional businesses must not rank); say so honestly when SEO scores are capped.
- Never write passwords into CLAUDE.md/AGENTS.md or anything pushed to GitHub.

- Copying a WordPress site to staging: include all uploads/CSS caches; rewrite the domain in cached CSS.
- Hostinger CDN (`hcdn`) can cache HTML and compress images; booking or availability data must come from no-store API calls.
- Creating GitHub repos through the API with his stored login is blocked by the safety check, so he creates empty repos on github.com and we push.
- Drive uploads through the connector: about 10 MB per file at most; bigger videos he drags in himself.

## Change log

- 2026-11-19 21:15: Codex works Local (not Cloud): skill + Hostinger + WordPress tested. Rule refined: plan + OK only for changes, read-only checks run directly. Added `06-github-and-drive.md`; repo skill `.codex/skills/mouzzam-way` in hub + Riverside for Codex Cloud; hub now has AGENTS.md.

- 2026-11-19 20:30: Codex onboarding written (`codex-onboarding/`): global `~/.codex/AGENTS.md`, skill `mouzzam-way` + 23 skills copied into `~/.codex/skills`; found Codex had no Hostinger/WordPress MCP configured; default split = Codex codes/tests, Claude deploys/tests in Chrome/Drive/videos.

- 2026-11-19 18:58: Riverside admin-panel walkthrough video (his voice, 1:21) built and on Drive; Mouzzam prefers a video over a PDF guide for showing clients how to edit.
- 2026-11-19 16:05: Riverside staff content editing (clinic details & hours, notice bar, doctor hours) live after backup; PSI mobile still 100/100/100; staff guide PDF on Drive.
- 2026-11-19 15:10: Riverside booking system + security/SEO/AEO fixes went live after Mouzzam's OK; handover tests with proof (QUALITY-REPORT.md).

- 2026-11-19 03:25: Riverside video done; next session = SEO/AEO/performance tests with proof screenshots (see project CLAUDE.md "NEXT SESSION").

- 2026-11-19: file created from the assistant's saved notes; business details in section 1 still to be completed together.
