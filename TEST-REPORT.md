# Test report: Riverside booking + reception demo

- **Environment:** staging copy https://mouzzambuild.com/riverside-staging/ (WordPress 7.1, PHP 8.3, clinic timezone America/Los_Angeles). Production was not touched.
- **Date:** 19 Nov 2026. **Tool:** Playwright 1.65 (Chromium, desktop 1366×900 and phone 375×812), script `tests/acceptance.mjs`.
- **Data:** fictional only (reserved `.example` emails, 555-01xx phones). The demo data is reset before and after the run.
- **Result:** **13 / 13 checks passed** (full machine output: `evidence/test-results.json`).

| # | Acceptance test | Result | Evidence (measured) |
|---|---|---|---|
| 0 | Demo data reset | PASS | 8 services, 6 doctors, 13 sample appointments created |
| 1 | Dentistry shows only the relevant dentist | PASS | All 8 services checked: listed doctors match the mapping exactly. Family Dentistry shows only Dr. Sofia Romano (plus "No preference"). A forged request "Dentistry + cardiologist" sent straight to the server was rejected (422) |
| 2 | Closed days / unavailable times can't be booked | PASS | Sunday shown as closed; Thanksgiving shown as "clinic closed". Forged requests for Sunday, Saturday 6 pm (closes 2 pm), a closure day and an off-grid time 10:07 were all rejected (422) |
| 3 | Visitor submits a request | PASS | Real form in the browser → reference `RFH-XXXX-00`, status Pending |
| 4 | Submitted request appears in reception | PASS | Found by reference in the reception list as Pending |
| 5 | Two competing requests can't both be confirmed | PASS | Two pending requests for the same doctor and time, both confirmed **at the same moment** by reception: one 200 (confirmed), one 409 ("time has just been booked") |
| 6 | Staff confirmation updates the record and creates the right test notification | PASS | Status confirmed in the database; history shows the action; "Confirmation" message created in the test outbox |
| 7 | Cancellation releases capacity | PASS | Patient opened the secure link from the confirmation and canceled: the time was taken before and free again after; reusing the same link was refused (410) |
| 8 | Unauthorised users can't view or change bookings | PASS | Logged-out: list 401, confirm 401, staff slots 401; fake link 404. Subscriber: reception page 403, API 403. Reception user: settings 403, plugins 403 |
| 9 | Duplicates, spam, rate limit | PASS | Same submission twice → same reference (no second record); honeypot → nothing stored; burst of requests → 429 after the limit |
| 10 | Staff reschedule | PASS | Confirmed appointment moved to another free time; still Confirmed; "Reschedule" notification created |
| 11 | Notifications only in the test outbox | PASS | Every message marked "Test outbox only"; real sending is off, and staging blocks all email at server level |
| 12 | Phone layout | PASS | 375 px: 0 px sideways scroll, 0 console errors |

## Bugs found and fixed during testing

1. **Phone layout:** the 14-day strip pushed the page 824 px wide on a 375 px phone (test 12). Fixed with `min-width:0` on the grid/flex children. Re-tested: 0 px.
2. **First activation:** the install routine created the "Manage appointment" page too early in WordPress's start-up, which caused a one-time critical error on the first page load. Moved to the `init` hook. Matters for the production deploy.
3. Network time-outs (`ETIMEDOUT`) on one run came from the test machine's connection, not the site. A re-run passed.

## Before vs after (live site checked 18 Nov 2026, read-only)

| Problem on the live demo | After (staging) |
|---|---|
| Family Dentistry still lists all 6 doctors; the server accepts any doctor for any service | Only mapped doctors are listed, and the server rejects a wrong pair |
| Sunday and Saturday-evening requests accepted | Only real free times inside each doctor's hours, closures respected |
| Vague time bands (morning/afternoon/evening) | Exact free start times from length + buffer |
| Request saved as one block of text, no reference or status (0 requests found in the DB) | Structured record, reference, timestamps, status workflow, history |
| Email to the admin with the visitor's typed address as Reply-To | Test outbox only; copies only to approved test inboxes |
| No reception tools | Reception desk with filters, actions, conflict-safe confirmation |

## Not covered / honest limits

- Real email delivery was not tested on purpose (all mail is blocked on staging; the domain has no SPF/DMARC).
- Without JavaScript the live times can't be shown; the page asks the visitor to call (the server still validates everything).
- Load testing (many users at once) was not done; only the two-confirmations race.
- Production deploy not done yet.
