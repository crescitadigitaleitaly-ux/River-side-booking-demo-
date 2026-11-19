# Voice-over script: Riverside booking case study (final voice 1:44)

Voice: Mouzzam's ElevenLabs clone (voice_id ceEarI8D5RvxxVecxOIJ), eleven_v4, 1 take first.
Rules: no platform names; say it is a demo clinic with fictional data; CTA "send me a message".
Section markers (first words) are used to time the pictures.

[intro + overview] (separate take, 507 credits, 32.6 s; Mouzzam asked for an overview first)
Hi, I'm Mouzzam. This is Riverside Family Health, a demo website for a family clinic. Patients can explore the services, meet the doctors, read patient information, and request an appointment online, on a phone or a computer. I built it on WordPress, with a custom, lightweight theme and my own booking plugin in PHP and JavaScript, with no paid plugins. For a clinic, that means fewer phone calls, no double bookings, and one place where reception sees every request. Here's what I fixed, and how it works.

(Main take, 1,285 credits, 79 s: its first sentence is cut; it is joined from "The problem" at 7.80 s.)

[problem]
The problem: the old form looked fine, but it checked nothing. Pick Family Dentistry, and you could still choose the heart doctor. Sundays, when the clinic is closed, were accepted too. And reception had no way to manage the requests.

[solution]
Now, each service only shows the doctors who offer it. The calendar only shows real free times, based on each doctor's hours, the visit length, holidays and closures. Every request gets a reference, and stays pending until reception confirms it.

[reception]
Reception sees every request in one dashboard. One click confirms, declines, or moves an appointment.

[conflict]
If two people try to confirm the same time, the system checks again, and only one booking goes through.

[email]
The patient gets a confirmation. In this demo, every message goes to a test outbox, never to a real inbox.

[patient]
With a secure, one-time link, patients can cancel or change their appointment, and the time opens up again for others.

[tests]
I tested everything with thirteen automated checks, from wrong doctors and closed days to access control and phones. All of them pass. No paid plugins, and all the data is fictional.

[outro]
If your clinic needs online booking that really works, send me a message.
