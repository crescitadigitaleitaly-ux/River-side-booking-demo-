# Plan: clinic staff can edit their own content (draft for Mouzzam's approval, 19 Nov 2026)

Why: clinics almost always ask "can we change the phone number, hours, prices, doctors or text ourselves?" The answer should be a simple, safe yes — without touching code and without breaking the design.

## What staff can already edit today (no code)

| Content | Where | Who |
|---|---|---|
| Services (name, length, buffer, short/long text, bullet points, bookable on/off) | Reception → Services | Clinic manager, admin |
| Doctors (name, title, specialty, bio, education, languages, which services, weekly hours, bookable on/off) | Reception → Doctors | Clinic manager, admin |
| Holidays and blocked time | Reception → Closures | Clinic manager, admin |
| Appointments, messages | Reception | Reception, manager, admin |

## What still needs a developer today (hard-coded in the theme)

- Clinic details: phone, emergency phone, email, address, map link
- Opening hours (shown in the header, footer, contact and booking pages)
- Home page texts: main headline, intro text, "accepting new patients" badge, about section
- FAQs (home + patients page)
- Patient information (first visit, insurance, telehealth) and a site-wide notice (e.g. "Closed on 24 December")
- Doctor photos (fixed theme images)

## Proposed solution: one "Clinic content" area in wp-admin

A small, safe editing area (part of the existing plugin, no paid plugin):

1. **Clinic details**: name, phone, emergency phone, email, address, map link. Changes appear everywhere at once (header, footer, contact, booking, schema for Google, llms.txt).
2. **Opening hours**: one row per day (or "closed"). Used on every page and in the Google data.
3. **Home page**: headline, intro text, badge text, about-section title and text, button labels. Character limits so the design never breaks.
4. **FAQs**: add, edit, reorder, delete questions (also feeds the FAQ data for Google/AI search).
5. **Notice bar**: optional message with start/end dates (e.g. holiday closing) shown above the header.
6. **Doctor photos**: upload a photo in the doctor screen (falls back to the current photo); automatic resizing by WordPress.
7. **Safety**: only the Clinic manager and admin roles can edit; reception cannot. Every field is validated and escaped. A "Preview" link. If a field is left empty, the current text is used, so the site never shows blanks. A short change log (who changed what, when).
8. **Simple guide for the client**: a one-page "How to edit your website" (with screenshots), as a page in wp-admin and as a PDF for the handover.

Why this approach (and not page builders): editing whole pages in the block editor or Elementor lets staff accidentally break layout, speed and accessibility. Fixed, labelled fields keep the 100/100/100 scores and the design safe, and this is what most small clinics actually need.

## Testing and proof

- Automated tests: manager edits phone + hours + a FAQ → visible on the live pages and in the Google data; reception user cannot open the editor; empty field falls back; HTML in a field is escaped.
- Re-run PageSpeed on the home page after the change (to show nothing got slower).
- Screenshots: the editor screens, before/after of the home page.
- Video: add a short scene (about 15–20 s): "Clinic staff can update their own content" → manager changes the opening hours and a FAQ → the site updates. Needs one short voice line (about 250–300 ElevenLabs credits; price checked first).

## Work order (after OK)

1. Build on staging → tests → screenshots.
2. Deploy to live (backup first), re-check speed.
3. Video scene + guide PDF, upload to Drive, push to GitHub, update CLAUDE.md/AGENTS.md.

Estimated effort: about half a day of work in this session.
