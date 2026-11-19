# Riverside Family Health: appointment requests + reception desk (PLAN)

Status: **APPROVED by Mouzzam on 18 Nov 2026 (incl. GitHub repo + Drive folder). Defaults used for open choices: in-app Test outbox only; staging password set by Claude; mapping as in §11.** (Updated 18 Nov: added §16, which covers GitHub for code and Google Drive for files.)
Prepared 18 Nov 2026 for Mouzzam Channa (independent developer, Crescita Digitale Italia).
Demo site: https://dental.mouzzambuild.com (fictional clinic, custom theme `riverside-health`).

---

## 1. What I checked (read-only)

| Check | How | Result |
|---|---|---|
| Theme code | Local backup in `projects/live-websites/hostinger/riverside-health/theme` | Same files as the server (MD5 of `forms.php`, `data.php`, `functions.php`, `setup.php`, `style.css` all match) |
| Plugins on the dental site | DB `active_plugins` | Only Hostinger AI, Hostinger Hub, Hostinger Tools, Really Simple Security. No booking, form, SMTP or cache plugin |
| Database | `SELECT` queries only, via Novamira on the main site | 1.45 MB, prefix `wp_`, **0 saved requests** (`rh_request` posts), 1 user (administrator), timezone set to UTC |
| Email | `wp-config.php` and plugin folder | Plain PHP `mail()`, no SMTP. `mouzzambuild.com` has no SPF/DMARC (known from the HVAC study), so mail delivery is unreliable |
| Caching | Response headers | Hostinger CDN (`hcdn`), page reported as `DYNAMIC` |
| Audit bug | Live booking page in a browser, choose "Family Dentistry" | **Reproduced.** The doctor list still shows all 6 doctors plus "No preference". There is no code linking service to doctor, in the browser or on the server |

## 2. What works now / needs repair / needs building

**Works now**
- Booking and contact forms post to `admin-post.php` with server-side validation, honeypot, JS check and a rate limit (5 per 10 min per IP).
- A submission is meant to be saved as a private "Request" post and emailed to the admin address (a Gmail account). Note: 0 requests are in the database, so either nobody has submitted, or test rows were deleted. Email delivery is not proven.
- Demo banner, "do not enter real medical information" note, noindex.

**Needs repair (bugs / weak spots found)**
1. Service → doctor: any doctor can be picked for any service (e.g. Dentistry + cardiologist). The server also accepts it.
2. Closed days are bookable: Sunday is "Closed" but the date field allows it; Saturday closes at 2 pm but "Evening (4–7 pm)" is accepted.
3. Time is only a vague band (morning/afternoon/evening), not a real slot. No durations, no doctor hours, no holidays.
4. The request is stored as one block of text (title + body). No reference number, no status, no structured fields, so it cannot be filtered or confirmed.
5. The confirmation message says "we will contact you" but nothing tracks pending vs confirmed.
6. `wp_insert_post()` and `wp_mail()` results are not checked; the visitor sees "sent" even if saving fails.
7. The email goes to the real admin inbox with `Reply-To` set to whatever the visitor typed.
8. The rate limit uses `REMOTE_ADDR`; behind the Hostinger CDN this may be the CDN's IP (to verify on staging).
9. Free-text "Reason for your visit" invites medical details. Should become a non-clinical choice.
10. Site timezone is UTC, but the clinic is (fictionally) in California.

**Needs building**
Editable services/doctors with explicit mappings, schedules, holidays and blocked periods, real slot calculation, appointment records with reference numbers, a reception dashboard, confirm/decline/reschedule/cancel/complete, conflict-safe confirmation, test-only notifications, secure patient links, staff roles, reset tools, tests.

## 3. Chosen approach

**A small custom plugin, `riverside-booking`**, plus small theme changes so the booking page uses it.

Why not a ready-made booking plugin (Amelia Lite, Bookly, Simply Schedule Appointments, Easy Appointments)?
- Free versions limit staff/services or hide reminders, roles and custom statuses behind paid tiers.
- They bring their own styling and scripts, which would undo the theme's speed work (1 CSS file, ~20 lines of JS).
- Their notification systems send to real addresses; locking them to a test outbox is harder than writing our own.
- For a case study, a clean custom plugin shows real skill and is easy to explain.

Trade-off (honest): custom code means we own the bugs. Mitigation: small scope, clear data model, automated tests.

How it fits the current theme:
- The theme stays the presentation layer. `rh_form('booking')` is replaced by the plugin's form block/shortcode, styled with the theme's existing classes.
- Services and doctors move from PHP arrays (`inc/data.php`) to editable records. The theme reads them from the plugin, with the old arrays kept as a fallback so the site never breaks.
- Contact form stays as it is (only fixes 6, 7 and 8 apply).

**Pages are cached, so all live data comes from small REST endpoints that are never cached:**
- `GET /wp-json/rb/v1/doctors?service=dentistry` → only mapped doctors
- `GET /wp-json/rb/v1/slots?service=…&doctor=…&date=…` → free start times
- `POST /wp-json/rb/v1/requests` → create a pending request
- Patient link endpoints (cancel / reschedule) using a token
- Staff endpoints (logged-in, capability checked, nonce checked)

Front-end: about 3–4 KB of plain JS (no jQuery, no framework). Without JS the form still works: the server checks everything and shows a clear error if the doctor/time is not valid.

## 4. Staging setup (same method as the HVAC study)

1. Copy `public_html/dental` → `public_html/riverside-staging` (files only).
2. Clone every `wp_` table to `stg_` tables in the same database. Point the copy's `wp-config.php` to `stg_`, set `WP_ENVIRONMENT_TYPE=staging`, set `siteurl/home` to `https://mouzzambuild.com/riverside-staging`.
3. Protect it: noindex (already in the theme) **plus** a password on the folder (Hostinger hPanel "Password protect directories", or `.htaccess` basic auth). Mouzzam chooses the password.
4. A tiny must-use plugin on staging that **hard-blocks all real email** (`pre_wp_mail` returns false and logs it). Even a bug cannot email anyone.
5. Set timezone to `America/Los_Angeles` on staging.
6. Production (`dental`, `wp_` tables) is never touched until Mouzzam says "deploy".

Note: Novamira runs on the production main site. I will only use it to copy files/tables and run checks inside the staging folder and `stg_` tables. An HVAC staging copy (`hvac-staging`) is still on the server, waiting for Mouzzam's go-ahead to delete.

## 5. Data model

Times are stored in UTC, shown in clinic time (America/Los_Angeles).

**Editable in wp-admin (WordPress-native custom post types + meta):**
- `rb_service`: name, slug, short text, duration (min), buffer after (min), active, sort order.
- `rb_doctor`: name, title, specialty, bio, photo, active, **services (explicit list)**, weekly hours (per weekday: start/end, can be several ranges), slot step (15 min).

**Custom tables (needed for reliable, fast and conflict-safe booking):**

`rb_closures`: id, doctor_id (NULL = whole clinic), starts_at, ends_at, reason (e.g. "Public holiday", "Training"), created_by.

`rb_appointments`
| column | notes |
|---|---|
| id | |
| reference | unique, e.g. `RFH-7K3Q-92` (random, not guessable from id) |
| status | `pending`, `confirmed`, `declined`, `canceled`, `completed` |
| service_id, doctor_id | doctor may be NULL for "No preference" until reception assigns one |
| starts_at, ends_at | UTC |
| patient_name, patient_email, patient_phone, patient_type | fictional data only |
| visit_type | non-clinical choice: check-up / follow-up / new concern / cleaning |
| note | optional, max 300 chars, labelled "no medical details" |
| idempotency_key | unique, from the form, blocks double submits |
| source_ip_hash | for rate limit/audit, hashed |
| created_at, updated_at, confirmed_at, canceled_at | |
| staff_note | internal only |

`rb_slot_locks` (the conflict guard): appointment_id, doctor_id, block_start. **UNIQUE (doctor_id, block_start).**
When staff confirm, the plugin inserts one row per 15-minute block the appointment covers, inside a transaction. If another confirmed booking already holds any block, MySQL rejects the insert, the transaction rolls back, and staff see "This time was just taken". Cancel/decline deletes the rows, releasing capacity. This is safe even if two receptionists click Confirm at the same second.

`rb_events` (audit log): appointment_id, action, from_status, to_status, user_id, at, details.

`rb_outbox` (test notifications): id, appointment_id, type, to_label, subject, body_html, created_at, delivered (yes/no), delivery_target.

`rb_tokens`: appointment_id, purpose (cancel/reschedule), token_hash (SHA-256), expires_at, used_at.

Pending requests do **not** hold capacity (they are requests, not bookings). The slot finder hides times blocked by confirmed bookings and closures; reception sees a warning if two pending requests want the same slot.

## 6. Roles

| Role | Can do |
|---|---|
| Administrator | Everything, plus demo reset |
| `rb_clinic_manager` (new) | Edit services, doctors, hours, closures; everything reception can do |
| `rb_reception` (new) | View/filter appointments, confirm, decline, reschedule, cancel, complete, add staff notes, view test outbox. Cannot edit services/settings, users, plugins or other content |
| Visitor | Submit a request; cancel/reschedule only their own via a valid, unexpired link |

Every staff action checks a capability and a nonce. Demo staff accounts exist on staging only; passwords are generated, kept out of the repo, and handed to Mouzzam privately.

## 7. Notifications (test only)

- Types: **receipt** (request received, pending), **confirmation**, **reschedule**, **cancellation**, **decline**, **reminder** (24 h before, via WP-Cron plus a "Run reminders now" button for the demo).
- Every message is first written to `rb_outbox` and shown in a **Test outbox** screen in the reception area. Subject starts with `[TEST – Riverside demo]`, and the body says it is a demonstration.
- Real sending is **off by default**. If Mouzzam wants real-inbox proof, sending is allowed **only to addresses on an allowlist** that Mouzzam enters in settings (one test inbox). Any other recipient is replaced by the outbox only. The address the visitor types is never emailed.
- Optional free delivery target: a **Mailtrap Email Testing** free sandbox (catches mail, never delivers to real people) or a test Gmail Mouzzam owns, sent through SMTP set in `wp-config.php` constants (secrets server-side only).
- No SMS or WhatsApp.

## 8. Patient links (cancel / reschedule)

- 32 random bytes, sent only inside the test notification; the database stores only the SHA-256 hash.
- Expire at the earlier of 7 days or 2 hours before the appointment. Cancel links are single use.
- Reschedule from the patient creates a new pending time on the same record (status back to pending, old slot lock released).
- Wrong/expired link shows a friendly page with no booking details.

## 9. Security and validation checklist

- Server-side validation of every field; service/doctor mapping, opening hours, closures, duration and "not in the past / max 90 days ahead" all checked again on submit.
- Spam: honeypot + JS check (kept), rate limit per IP (fixed to read the real visitor IP if the CDN adds a header; verified on staging) and per email (3 per hour).
- Duplicate submits: idempotency key (unique index) + button disabled while sending.
- Safe errors: visitor sees a plain message and the reference number only on success; details go to a server log, never to the page.
- Output escaped, `$wpdb->prepare()` everywhere, capability + nonce on staff routes, REST routes return 401/403 for anyone else.
- Secrets (SMTP, if any) only in `wp-config.php`, never in the database export, repo or front-end.
- Data: fictional only. No diagnoses, uploads, prescriptions or clinical records. Demo warning kept on the page and in every notification. Old demo rows auto-deleted after 30 days.

## 10. Test plan

**Automated (Playwright, already installed; Node scripts like the solar and HVAC projects), run against staging:**
1. Dentistry shows only Dr. Romano (and "No preference"); every service shows only its mapped doctors.
2. Sunday and a configured holiday show no slots; a forged request for Sunday / 6 pm Saturday / a closure is rejected by the server (422).
3. Two pending requests for the same doctor + time; two parallel Confirm calls → exactly one succeeds, the other gets 409 "time taken".
4. A submitted request appears in the reception list with status Pending and its reference.
5. Staff confirm → status Confirmed, event logged, one "confirmation" item in the test outbox.
6. Cancel (staff or patient link) → status Canceled, slot lock gone, time shows as free again.
7. Logged-out visitor and a Subscriber get 401/403 on every staff route and cannot open the reception page; an expired/used token is refused.
8. Double submit with the same key creates one record; 6th request in 10 min is rate limited.
9. Reschedule → old slot released, new slot checked, "reschedule" notification created.
10. No real email leaves staging (mail log check).

**Manual / quality:** mobile 375 px and desktop, keyboard-only form, screen-reader labels, no console errors, Lighthouse before/after on the booking page (lab numbers with date and settings, only what we actually measure).

Output: `TEST-REPORT.md` with pass/fail, date, and screenshots, in the same style as the solar case study.

## 11. Demo data and reset

- Seed: the 8 current services and 6 current doctors (same fictional names), with mappings: Primary Care → Carter, Hayes; Pediatrics → Bennett; Women's Health → Raman; Family Dentistry → Romano; Heart Health → Okafor; Lab & Diagnostics, Imaging, Vaccinations → Carter, Hayes (to confirm with Mouzzam).
- Hours: Mon–Fri 8:00–19:00, Sat 9:00–14:00, Sun closed (from the site), per-doctor variations (e.g. Dr. Romano no Wednesdays) so the demo shows real rules. One sample clinic holiday and one doctor training block.
- Sample appointments: ~12 fictional requests over the next 2 weeks in mixed statuses, `.example` emails, 555-01xx phones.
- **Reset**: admin-only "Reset demo data" button (and the same function callable from a script): empties appointments, locks, events, outbox, tokens; reseeds the above. Optional daily automatic reset on staging.

## 12. Requirement checklist

| Requirement | Where in plan |
|---|---|
| Editable services and doctor profiles | §5 CPTs |
| Explicit service → doctor mappings | §5, §10.1 |
| Doctor hours, durations, holidays, blocked periods | §5 |
| Times calculated from rules | §3 slots endpoint |
| Requests labelled pending until confirmed | §5 status, §7 receipt |
| Saved in real DB, unique reference, timestamps | §5 `rb_appointments` |
| Reception view, filters, 4+ statuses | §6, dashboard filters: date, doctor, service, status, reference/name search |
| Confirm / decline / reschedule | §6 |
| Recheck at confirmation, no conflicting confirmations | §5 slot locks, §10.3 |
| Separate TEST notifications to approved inbox/preview only | §7 |
| Secure expiring cancel/reschedule links | §8 |
| Role-limited staff access | §6, §10.7 |
| Validation, spam, rate limits, duplicates, safe errors | §9 |
| Secrets server-side only | §9 |
| Fictional data, no clinical data, demo warning kept | §9, §11 |
| Staging only, no production change without approval | §4 |

## 13. Video outline (60–90 s)

Style rules: no zoom, face bubble, quiet music, no platform names, blur emails/phones/URLs, CTA "send me a message". Say clearly it is a demo clinic with fictional data.

| Time | Scene | Voice-over idea |
|---|---|---|
| 0–8 s | Title card + booking page | "A clinic's booking form that only looked like it worked." |
| 8–18 s | Before: Dentistry + all doctors; Sunday accepted | "Pick dentistry and you could still book the heart doctor, even on Sunday." |
| 18–32 s | After: Dentistry → only Dr. Romano, real free times | "Now each service shows only its doctors, and only real free times." |
| 32–42 s | Submit → "Request RFH-… received, pending" | "Every request gets a reference and stays pending until reception confirms." |
| 42–55 s | Reception dashboard, filter, Confirm | "Reception sees it straight away and confirms in one click." |
| 55–62 s | Second receptionist tries same slot → "time just taken" | "Two people can't confirm the same time. The system checks again." |
| 62–72 s | Test outbox: confirmation email | "The patient message is created. In this demo it goes to a test inbox only." |
| 72–84 s | Patient link → cancel; slot free again / reschedule | "Patients can cancel or move with a secure link, and the time opens up again." |
| 84–90 s | Closing card | "Need booking that really works? Send me a message." |

## 14. Screenshot shot list

1. Before: Dentistry selected, all doctors listed.
2. Before: Sunday date accepted.
3. After: Dentistry → Dr. Romano only.
4. After: slot picker with closed day/holiday greyed out.
5. Success message with reference + "Pending".
6. Reception dashboard (filters + status badges).
7. Appointment detail with event history.
8. Conflict message on second confirm.
9. Test outbox list + one confirmation email.
10. Patient cancel page, then slot free again.
11. Reception user blocked from Settings/Plugins (role proof).
12. Test report summary (all tests passed, with date).
13. Mobile 375 px booking form.
All with emails/phones/URLs blurred in the platform-safe copies.

## 15. What Mouzzam must supply or decide

Nothing paid is needed. Hosting, WordPress, Playwright, Node and FFmpeg are already there.

1. **Approve this plan** (or tell me what to change).
2. **Staging password**: choose one for the folder (or say "you set it").
3. **Test inbox**: either (a) in-app Test outbox only (recommended, no setup), or (b) also one real test inbox you own, or (c) a free Mailtrap Email Testing account (you create it; you put the SMTP user/password in `wp-config.php` yourself or let me, kept out of the repo).
4. **Confirm the service → doctor mapping** in §11, especially Lab/Imaging/Vaccinations.
5. **Optional**: Cloudflare Turnstile (free) for stronger spam protection. My advice: skip it for the demo; honeypot + rate limit are enough.
6. Later, separately: whether to deploy to the live demo, and whether to delete the old `hvac-staging` copy.
7. **OK to create the private GitHub repo `riverside-booking-demo`** and the **Drive folder "Riverside Family Health – Appointment Demo"** (see §16).

## 15b. Free booking plugins: what is possible and what is not (checked 18 Nov 2026)

Prices and limits change; figures come from the vendors' own pages and WordPress.org where possible.

| Plugin (free version) | What the free version can do | What needs paying | Fit for this demo |
|---|---|---|---|
| **Bookly (free)** | Booking form, calendar, email notifications | Vendor support says free = **1 staff member and 5 services**; more staff/services, add-ons, payments, Google Calendar need Bookly Pro (paid) | No: we have 6 doctors and 8 services |
| **Amelia Lite** | Booking for **1 employee**, basic email notifications | More employees, SMS, custom notifications, payments, calendar sync need a paid licence | No: only 1 doctor |
| **Simply Schedule Appointments (Basic, free)** | Appointment types, assign/round-robin to staff, shared resources, email confirmations | SMS reminders (Professional), team booking/more team features (Business) | Partly; heavy React UI, reception workflow is generic, styling would clash with the theme |
| **Easy Appointments (free)** | Locations + services + workers with connections between them, email notifications, calendar, CSV export | Google Calendar, SMS, payments are paid extensions | Closest free option, but dated UI, no "pending → confirmed" reception dashboard like we need, no conflict guard at confirmation, no test-only mail lock |

What **no** free plugin gives us out of the box: a reception desk with confirm/decline/reschedule and audit log, a database-level guard so two staff can't confirm the same slot, notifications locked to a test outbox, and the theme's own fast styling. All of those are central to the case study.

**Decision (approved by Mouzzam 18 Nov):** build the small custom plugin. Cost: $0, no paid plugins.
What is **not** possible for free in this plan (and is left out): SMS/WhatsApp messages, online payments, Google Calendar sync, video calls. These can be mentioned to clients as "paid add-ons if needed".

Sources: [Bookly support: free version limits](https://support.booking-wp-plugin.com/hc/en-us/community/posts/9372594837138-How-many-services-are-allowed-in-free-version), [Amelia licences](https://help.wpamelia.com/pricing/which-licenses-are-available), [SSA editions](https://simplyscheduleappointments.com/guides/which-simply-schedule-appointments-edition-is-right-for-you/), [Easy Appointments on WordPress.org](https://wordpress.org/plugins/easy-appointments/).

## 16. Where code and files are kept (GitHub + Google Drive)

Mouzzam's rule: all code goes to GitHub; all files, screenshots, videos and other material go to Google Drive, not his PC (low disk space and RAM).

**Code: GitHub**
- No GitHub repo for this exists yet (I checked `riverside-booking`, `riverside-health` and `riverside-booking-demo` under `crescitadigitaleitaly-ux`, and none of them exist). The theme is only backed up inside the private hub repo `-private-repo-live-websites`.
- Proposal: **create a new private repo `crescitadigitaleitaly-ux/riverside-booking-demo`**, but only after Mouzzam says OK. It holds the `riverside-booking` plugin, the updated `riverside-health` theme, the staging mu-plugin, seed/reset scripts, Playwright tests, `PLAN.md`, `TEST-REPORT.md` and this project's `CLAUDE.md`.
- Commit after each build step in §17, on a working branch, and push. Never commit secrets: SMTP passwords, staging passwords and demo-staff passwords stay in `wp-config.php` or Mouzzam's password manager. `.gitignore` covers `node_modules`, `.env`, test output and video files.
- The hub repo keeps its role: after any site change, update `hostinger/riverside-health/CLAUDE.md` there and push. Its theme backup is refreshed only when the change goes to production.
- Note: the `gh` command isn't installed on this laptop. I'll create the repo either with Mouzzam on github.com (a 1-minute click) or through the GitHub connector if he prefers. Pushing works with the git login he already uses.

**Files: Google Drive**
- New folder: **"Riverside Family Health – Appointment Demo"**, with subfolders:
  - `01 Evidence – screenshots (originals)`
  - `02 Evidence – screenshots (platform-safe, blurred)`
  - `03 Test reports`
  - `04 Video – recordings and audio`
  - `05 Video – final`
  - `06 Portfolio images and text`
- Upload with the Google Drive connector in this app, or Mouzzam drags the files in if the connector can't handle large videos.
- Big files (screen recordings, renders) are made in the scratchpad or a temp folder, uploaded, then checked on Drive (file name, size and that it opens).
- **Local copies are deleted only after the upload is checked and Mouzzam agrees**, file group by file group. Nothing is deleted on his word alone without that check.
- No contact details or real personal data go into Drive files that will be shared publicly. Only the blurred copies are used for the portfolio.

## 17. Build order (after approval)

0. With Mouzzam's OK: create the private GitHub repo and the Drive folder.
1. Staging copy + mail block + timezone (and verify the copy works).
2. Plugin skeleton, tables, CPTs, seed data, roles.
3. Slot engine + REST endpoints + new booking form (fixes the audit bug).
4. Reception dashboard + status actions + slot locks + audit log.
5. Test outbox + notifications + patient links + reminders.
6. Security pass, Playwright tests, test report.
7. Screenshots, then video (separate approval for voice-over credits).

Each step ends with a short check-in so Mouzzam can see progress.
