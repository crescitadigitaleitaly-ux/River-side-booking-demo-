# Riverside Health theme

Light, fast WordPress block theme for the **Riverside Family Health** demo website at https://dental.mouzzambuild.com, built by Mouzzam Builds.

**Demo content:** the clinic, doctors, address, phone numbers (reserved 555-01xx range) and email (reserved `.example` domain) are fictional and AI-written. Every page shows a banner saying so, and the site asks search engines not to index it (`wp_robots` in `functions.php`), so it never competes with or impersonates a real practice.

## What it does

- Pages: Home, Services, Our Doctors, Book an Appointment, Patient Information, Contact Us, Privacy Policy, custom 404.
- Booking and contact forms (`inc/forms.php`): server-side validation, honeypot, JavaScript check and per-IP rate limit (5 per 10 minutes), no nonce because pages are fully cached. Submissions are stored privately under **Requests** in wp-admin and emailed to the site admin address.
- Activation runs `inc/setup.php` once (`RH_SETUP_VERSION`): creates or updates the pages (each page body is a pattern reference, so copy lives in `inc/data.php` and `inc/sections.php`), sets the static front page, site title and tagline, and moves other published pages to Draft (nothing is deleted; page revisions are kept).
- Performance: one stylesheet, one self-hosted variable font (Plus Jakarta Sans, OFL), inline SVG icons, responsive WebP images, ~20 lines of inline JavaScript, no jQuery or external requests.

## Install

1. Zip the `riverside-health` folder (or use the ZIP from the release).
2. WordPress > Appearance > Themes > Add New Theme > Upload Theme > Install Now > Activate (or "Replace active with uploaded" on later updates).
3. Load the home page once; setup runs automatically.

To roll back, activate the previous theme and restore pages from Pages > Drafts.

## Image credits

All photos are from [Unsplash](https://unsplash.com) and used under the [Unsplash License](https://unsplash.com/license) (free for commercial use, no attribution required). People shown are models in stock photos, not the fictional doctors' real identities. Unsplash photo IDs:

| File | Unsplash photo |
|---|---|
| hero | photo-1631217868264-e5b90bb7e133 |
| clinic | photo-1631248055158-edec7a3c072b |
| reception | photo-1519494026892-80bbd2d6fd0d |
| svc-primary | photo-1631815588090-d4bfec5b1ccb |
| svc-pediatrics | photo-1632053002928-1919605ee6f7 |
| svc-womens | photo-1638202993928-7267aad84c31 |
| svc-dental | photo-1629909613654-28e377c37b09 |
| svc-heart | photo-1530026405186-ed1f139313f8 |
| svc-lab | photo-1579154204601-01588f351e67 |
| svc-imaging | photo-1530497610245-94d3c16cda28 |
| svc-vaccines | photo-1612277795421-9bc7706a4a34 |
| dr-carter | photo-1559839734-2b71ea197ec2 |
| dr-hayes | photo-1612349317150-e413f6a5b16d |
| dr-raman | photo-1614608682850-e0d6ed316d47 |
| dr-bennett | photo-1622253692010-333f2da6031d |
| dr-romano | photo-1594824476967-48c8b964273f |
| dr-okafor | photo-1651008376811-b90baee60c1f |

Font: Plus Jakarta Sans (SIL Open Font License), via Fontsource. Icons: Lucide-style paths (ISC/MIT).
