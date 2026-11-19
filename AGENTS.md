# AGENTS.md: Riverside Family Health booking + reception demo (for Codex and other coding agents)

> Same full context as CLAUDE.md (keep both in sync). Read `Desktop/projects/memory.md` (full path C:/Users/MouzzamChanna/Desktop/projects/memory.md) first for business + working rules: plan first and build after OK, staging only, honest fictional data, code to GitHub, media to the Drive folder, update CLAUDE.md + AGENTS.md after every session.

## STATUS (19 Nov 2026, ~15:10): LIVE + QUALITY-TESTED

- **Live on https://dental.mouzzambuild.com** (Mouzzam approved "Everything" on 19 Nov): booking plugin + reception desk + `riverside-hardening` plugin + theme 1.2.0 (SEO/tags/schema/AEO). One-time go-live setup ran (tables, roles, Manage page, timezone Los Angeles, demo seed: 8 services, 6 doctors, 13 sample appointments) and deleted itself. Leftover "Hello world" posts set to draft. `readme.html`/`license.txt` blocked in `.htaccess`. Hostinger's auto `llms.txt` renamed to `llms.txt.hostinger-bak` (theme serves an accurate one; rename again if Hostinger AI regenerates it).
- **Backup before go-live:** on the server outside public_html, folder `rs-live-backup-20261119-133811/` (theme files, `.htaccess`, old `llms.txt`, SQL dump of `wp_` tables). Roll back: restore theme + SQL, deactivate the two plugins.
- **Quality tests (before → after):** see `QUALITY-REPORT.md`. PageSpeed 100/100/100 (SEO 61–66 only because the demo is noindex on purpose); security headers D→A; Observatory D 35→B+ 80; SSL A→A+; schema none→8 items 0 errors; Rich Results valid; W3C booking 1→0 errors; AEO 59%→85%; 13/13 booking tests; 0 px sideways scroll on 5 devices.
- **Tool notes:** PageSpeed, securityheaders, Observatory, SSL Labs, Schema validator, Rich Results and W3C refuse automated browsers, so run them in Mouzzam's Chrome (Claude in Chrome). Mobile screenshots + own checks: `node scripts/quality.mjs <label> security tags mobile`. AEO: AEOTester audit on the live URL. Blurred copies: `python scripts/blur-quality.py`.
- **Final video:** `video/riverside-booking-case-study.mp4` (overview, problem, solution, booking tests, before/after quality results, CTA). Voice credits used in total: 1,285 + 507 + 533 = 2,325.
- **Demo staff accounts exist only on staging** (reception.demo / manager.demo; passwords are kept out of this repo, Mouzzam has them).

Next ideas (not started): A+ on securityheaders by moving inline JS to files + CSP nonces; swap the hero photo (its badge shows a real institution's name); delete staging copies when no longer needed (`riverside-staging`, `hvac-staging`); next project.

## Goal

Turn the fictional Riverside Family Health WordPress demo (https://dental.mouzzambuild.com, theme `riverside-health`) into a working **appointment-request + reception-management** demo for Mouzzam's Upwork/Fiverr portfolio, with no paid plugins. Fictional data only. Built and tested on a **staging copy**. Production changes only after Mouzzam explicitly says so.

## Status (19 Nov 2026)

**Built and all acceptance tests pass on staging (13/13, `evidence/test-results.json`, `TEST-REPORT.md`). Production is untouched.**

Waiting on Mouzzam:
1. ~~Create GitHub repo~~ Done: code, screenshots and docs pushed to `River-side-booking-demo-`.
2. Review staging and decide on **deploying to the live demo** (see "Deploy to production" below).
3. Review the case-study video (built 19 Nov, see "Video" below) and say what to change.
4. Fill the business details in `memory.md` §1.

## What was built

- **Plugin `riverside-booking`** (`plugin/riverside-booking/`): see `includes/*.php`.
  - Services and doctors are editable custom post types (Reception → Services / Doctors), with explicit service→doctor mapping, weekly hours (15-minute grid), length and buffer.
  - Closures and holidays (whole clinic or one doctor).
  - Availability engine (`availability.php`): doctor hours, service length + buffer, closures, minimum notice (120 min), booking horizon (60 days), confirmed bookings.
  - Requests saved in `wp_rb_appointments` with a random reference (`RFH-XXXX-00`), timestamps and status `pending` → `confirmed` / `declined` / `canceled` / `completed`.
  - Conflict-safe confirmation: a transaction plus `rb_slot_locks` UNIQUE(doctor_id, block_start). Two confirmations of the same time cannot both succeed (tested in parallel).
  - Reception desk (wp-admin → Reception): KPIs, filters (status, doctor, service, dates, search), detail page with history, confirm / decline / reschedule / cancel / complete, overlap warning, staff notes.
  - Test-only notifications (receipt, confirmation, reschedule, cancellation, decline, reminder) go to the **Test outbox** screen. Real sending is off. If switched on, mail goes only to allowlisted test inboxes, never to the visitor's address.
  - Secure patient links: 64-hex token, only its SHA-256 hash is stored, expires after 7 days or 2 hours before the visit, single use. Lets the patient cancel or change the time.
  - Roles: `rb_reception` (appointments only), `rb_clinic_manager` (+ services, doctors, closures). Admin has everything; Settings & demo reset are admin-only.
  - Security: server-side validation, honeypot, browser check, rate limit (5 per 10 min per IP, 3 per hour per email; REMOTE_ADDR is the real visitor IP on Hostinger, verified), idempotency key against double submits, no-store REST responses, nonces and capabilities on staff routes, no clinical fields (visit type is non-clinical, note limited to 300 chars with a warning).
  - Demo seed / reset (`seed.php`): 8 services, 6 doctors with hours, closures relative to today (training day, Thanksgiving/Christmas/New Year if in range, a doctor conference), 13 sample appointments including an overlap pair, an unverified one, past ones.
- **Theme `riverside-health` 1.1.0** (`theme/riverside-health/`, only 5 files changed):
  - `inc/data.php`: `rh_services()` / `rh_doctors()` wrapped in `apply_filters`, so the edited records show on the site.
  - `patterns/page-book.php`: uses `rb_booking_form()` when the plugin is active (falls back to the old form).
  - `inc/forms.php`: contact form now checks that saving worked, and no longer uses the typed visitor address as Reply-To.
  - `functions.php`, `style.css`: version 1.1.0.
- **Staging helpers** (`staging/001-staging-helper.php`, staging only; it refuses to run unless `WP_ENVIRONMENT_TYPE=staging`): seed, clear rate limits, create demo users, log in as a user for tests. Needs the secret `X-RB-STG` header (only its hash is on the server).

## Where things live

| What | Where |
|---|---|
| This project (code, docs, tests) | `C:\Users\MouzzamChanna\Desktop\projects\riverside-booking-case-study` (git repo, branch `main`) |
| GitHub | https://github.com/crescitadigitaleitaly-ux/River-side-booking-demo- (private, branch `main`, pushed 19 Nov 2026) |
| Google Drive | "Riverside Family Health – Appointment Demo" (id `1sp5i3hjy1LVk6dLo-DfIfYsb7geYcOPE`), subfolders 01 Evidence originals, 02 Evidence blurred, 03 Test reports, 04 Video recordings, 05 Video final, 06 Portfolio |
| Live site (untouched) | https://dental.mouzzambuild.com, Hostinger `domains/mouzzambuild.com/public_html/dental`, tables `wp_` |
| Staging | https://mouzzambuild.com/riverside-staging/ (HTTP password, user `review`), folder `public_html/riverside-staging`, tables `stg_` in the same DB, `WP_ENVIRONMENT_TYPE=staging`, timezone America/Los_Angeles, all email blocked by `mu-plugins/000-staging-guard.php`, debug log `domains/mouzzambuild.com/rs-staging-debug.log` |
| Staging secrets | **Not in the repo.** Session scratchpad files: `staging-auth.txt` (folder password), `stg-helper.key`, `staff-users.json` (reception.demo / manager.demo / subscriber.demo passwords). Mouzzam should copy them into his password manager; they are lost when the scratchpad is cleared (then regenerate via the helper). |
| Theme backup before changes | staging `wp-content/rb-theme-backup-20261119-002143/` |
| Hub notes for the live site | `Desktop\projects\live-websites\hostinger\riverside-health\CLAUDE.md` |

## How to run the tests

```bash
export MSYS_NO_PATHCONV=1
STG_AUTH='rfhstaging:<password>' STG_KEY_FILE=<path>/stg-helper.key USERS_FILE=<path>/staff-users.json node tests/acceptance.mjs
```
Uses the global Playwright install (Chromium, `--disable-quic`). About 2–3 minutes. Resets the demo data at the start and the end. Writes `evidence/test-results.json` and screenshots to `evidence/screenshots/` (those go to Drive, not git).

## How to deploy changes to staging

1. Build a zip: `python <scratchpad>/mkzip.py <scratchpad>/rb-deploy.zip` (puts `plugins/`, the 5 theme files and `mu-plugins/` helper in wp-content layout).
2. Novamira `create-upload-link` for `riverside-staging/wp-content/rb-deploy.zip`, then `curl -X PUT` the zip.
3. Novamira `execute-php`: open the zip, check every PHP file with `token_get_all(..., TOKEN_PARSE)`, then `extractTo(wp-content)` and delete the zip.

## Deploy to production (only after Mouzzam says so)

1. Back up the live theme folder and DB (Hostinger backup).
2. Upload `plugins/riverside-booking` and the 5 theme files. **Do NOT upload the staging helper.**
3. Activate the plugin in wp-admin (creates tables, roles and the Manage page), set the timezone to Los Angeles, then Reception → Settings & demo → Reset demo data.
4. Create the demo reception user for the case study (optional).
5. Run the acceptance tests against production with a staging-free variant, or check by hand: dentistry → Romano, submit, confirm, outbox.

## Video (19 Nov 2026)

- **Final:** `video/riverside-booking-case-study.mp4` (1:47, 1920×1080, −16.4 LUFS, master 10.9 MB, local only, gitignored). Drive: `05 Video – final/riverside-booking-case-study (Drive copy, 7 MB).mp4` (two-pass 420 kbit/s; the Drive connector accepts at most ~7 MB raw) + `.en.srt`. The master can be dragged into Drive by hand.
- Order (Mouzzam asked for an overview first): title → site tour → how it's built → why it matters → BEFORE (live site, read-only, nothing submitted) → AFTER booking form → reception confirm → conflict refused → test outbox → patient cancel link → 13/13 tests → "send me a message".
- Voice: ElevenLabs clone, two takes joined (overview 507 credits + main 1,285 credits = 1,792 credits). Flow `nTLx5ozlLQeOYMayRuyE`. Script: `video-src/voiceover-script.md`.
- Rebuild: `node scripts/record.mjs` (staging recordings; needs STG_AUTH + STG_KEY_FILE), `node scripts/cards.mjs`, `python scripts/transcribe.py video/audio/voice-full.mp3 video/audio/words.json` (+ fix the "reception" marker if it matches the overview), `python scripts/align_subs.py video/audio/words.json video/subtitle-text.txt`, `node scripts/build-video.mjs`.
- Privacy: on staging, recording mode (cookie `rb_rec=1`, staging helper) masks emails, phones and the domain **in the HTML before it is sent**; a client-side blur is a second layer. A CSS blur alone left the first frames after each page load readable, so don't rely on it.

## Decisions

- Custom plugin instead of Bookly / Amelia / SSA / Easy Appointments (free versions limit staff/services or lack the reception workflow, conflict guard and test-outbox lock; see PLAN.md §15b).
- Pending requests do not hold time; only confirmation locks it. Reception sees overlap warnings.
- "No preference" auto-assigns the first mapped doctor who is free at that time.
- A patient's own change sets the request back to pending. A staff reschedule keeps a confirmed booking confirmed.
- Without JavaScript the form shows a message to call instead of live times (honest limit; the server still validates everything).

## Left / ideas

- ~~GitHub repo + push~~ done 19 Nov.
- Blurred, platform-safe copies of screenshots for Upwork/Fiverr; video (PLAN.md §13, 60–90 s).
- Production deploy after review.
- Optional: calendar (week) view for reception; CSV export; .ics attachment in the confirmation.
- Delete `hvac-staging` (separate project) after Mouzzam's OK.

## Lessons

- In WordPress, creating pages from a plugin's upgrade routine must run on `init` or later (on `plugins_loaded` it caused a one-time critical error).
- Grid/flex children need `min-width:0`, or a horizontal day strip widens the page on phones (caught by test 12: 824 px overflow).
- On Mouzzam's 5G connection, long Playwright runs sometimes hit `ETIMEDOUT`; re-run before treating it as a bug.
- Novamira upload links accept zips anywhere under public_html (PHP files only in its sandbox), so zip + extract is the cheap way to deploy.
