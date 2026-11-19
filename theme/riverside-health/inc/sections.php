<?php
/**
 * Front-end sections. Each function returns escaped HTML used by the
 * patterns in /patterns, so every page is built from the same pieces.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon (Lucide-style strokes, MIT licence).
 *
 * @param string $name Icon name.
 * @return string
 */
function rh_icon( $name ) {
	$paths = array(
		'stethoscope' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>',
		'child'       => '<circle cx="12" cy="5" r="3"/><path d="M12 8v5m-5-3 5 3 5-3M9 22l3-9 3 9"/>',
		'heart-pulse' => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/><path d="M3.2 12H9l.5-1 2 4.5 2-7 1.5 3.5h5.3"/>',
		'heart'       => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/>',
		'tooth'       => '<path d="M7 3c-2.5 0-4 2-4 4.5 0 3 1.5 4.5 2 7 .5 2.5 1 6.5 2.5 6.5 2 0 1.5-5 4.5-5s2.5 5 4.5 5c1.5 0 2-4 2.5-6.5.5-2.5 2-4 2-7C21 5 19.5 3 17 3c-2 0-3 1-5 1S9 3 7 3Z"/>',
		'flask'       => '<path d="M9 2v7.5L4 18a2.5 2.5 0 0 0 2.2 4h11.6a2.5 2.5 0 0 0 2.2-4l-5-8.5V2"/><path d="M8 2h8M6.5 15h11"/>',
		'scan'        => '<path d="M3 7V5a2 2 0 0 1 2-2h2m10 0h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
		'syringe'     => '<path d="m18 2 4 4m-5 1 3-3m-1 5-8.7 8.7a2.4 2.4 0 0 1-3.4 0l-.6-.6a2.4 2.4 0 0 1 0-3.4L15 5m-6 6 4 4m-8 4-3 3m12-18 6 6"/>',
		'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'phone'       => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
		'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18m-10 4h1v4"/>',
		'pin'         => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
		'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
		'check'       => '<path d="M20 6 9 17l-5-5"/>',
		'shield'      => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1Z"/><path d="m9 12 2 2 4-4"/>',
		'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
		'video'       => '<path d="m16 13 5.2 3.5a.5.5 0 0 0 .8-.4V7.9a.5.5 0 0 0-.8-.4L16 11"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
		'arrow'       => '<path d="M5 12h14m-7-7 7 7-7 7"/>',
		'alert'       => '<path d="m21.7 18-8-14a2 2 0 0 0-3.5 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z"/><path d="M12 9v4m0 4h.01"/>',
		'menu'        => '<path d="M4 6h16M4 12h16M4 18h16"/>',
	);
	return '<svg class="rh-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Responsive theme image from assets/images/<name>-<width>.webp.
 *
 * @param string $name   Image base name.
 * @param array  $widths Available widths, smallest first.
 * @param string $alt    Alt text.
 * @param string $sizes  Sizes attribute.
 * @param float  $ratio  Width / height, for layout stability.
 * @param bool   $eager  Load immediately (above the fold).
 * @return string
 */
function rh_img( $name, $widths, $alt, $sizes, $ratio, $eager = false ) {
	$srcset = array();
	foreach ( $widths as $w ) {
		$srcset[] = esc_url( get_theme_file_uri( "assets/images/{$name}-{$w}.webp" ) ) . " {$w}w";
	}
	$w = end( $widths );
	return sprintf(
		'<img src="%1$s" srcset="%2$s" sizes="%3$s" width="%4$d" height="%5$d" alt="%6$s" loading="%7$s" decoding="async"%8$s>',
		esc_url( get_theme_file_uri( "assets/images/{$name}-{$w}.webp" ) ),
		esc_attr( implode( ', ', $srcset ) ),
		esc_attr( $sizes ),
		$w,
		round( $w / $ratio ),
		esc_attr( $alt ),
		$eager ? 'eager' : 'lazy',
		$eager ? ' fetchpriority="high"' : ''
	);
}

/**
 * Doctor photo.
 *
 * @param string $key Doctor key.
 * @param string $sizes Sizes attribute.
 * @return string
 */
function rh_doctor_img( $key, $sizes = '(max-width: 600px) 100vw, 360px' ) {
	$d = rh_doctors()[ $key ];
	return rh_img( 'dr-' . $key, array( 360, 600 ), $d[0] . ', ' . $d[2], $sizes, 0.8 );
}

/**
 * Link to a page by slug.
 *
 * @param string $slug Page slug.
 * @param string $hash Optional anchor.
 * @return string
 */
function rh_url( $slug, $hash = '' ) {
	return esc_url( home_url( '/' . ( $slug ? $slug . '/' : '' ) . ( $hash ? '#' . $hash : '' ) ) );
}

/**
 * Section heading block.
 *
 * @param string $eyebrow Small label.
 * @param string $title   Heading (h2).
 * @param string $lead    Optional intro paragraph.
 * @param bool   $center  Centre aligned.
 * @return string
 */
function rh_heading( $eyebrow, $title, $lead = '', $center = true ) {
	return '<div class="rh-heading' . ( $center ? ' rh-heading--center' : '' ) . '">'
		. '<p class="rh-eyebrow">' . esc_html( $eyebrow ) . '</p>'
		. '<h2>' . esc_html( $title ) . '</h2>'
		. ( $lead ? '<p class="rh-lead">' . esc_html( $lead ) . '</p>' : '' )
		. '</div>';
}

/**
 * Page header for inner pages.
 *
 * @param string $eyebrow Small label.
 * @param string $title   H1.
 * @param string $lead    Intro.
 * @return string
 */
function rh_page_hero( $eyebrow, $title, $lead ) {
	return '<section class="rh-page-hero"><div class="rh-container">'
		. '<p class="rh-eyebrow">' . esc_html( $eyebrow ) . '</p>'
		. '<h1>' . esc_html( $title ) . '</h1>'
		. '<p class="rh-lead">' . esc_html( $lead ) . '</p>'
		. '</div></section>';
}

/**
 * Home hero.
 *
 * @return string
 */
function rh_home_hero() {
	$c = rh_clinic();
	return '<section class="rh-hero"><div class="rh-container rh-hero__grid">'
		. '<div class="rh-hero__text">'
		. '<p class="rh-pill">' . rh_icon( 'shield' ) . ' Accepting new patients of all ages</p>'
		. '<h1>Compassionate care for every generation of your family</h1>'
		. '<p class="rh-lead">Primary care, pediatrics, women\'s health, dentistry and heart health under one roof, with same-week appointments and doctors who take the time to listen.</p>'
		. '<div class="rh-actions">'
		. '<a class="rh-btn rh-btn--primary" href="' . rh_url( 'book-appointment' ) . '">' . rh_icon( 'calendar' ) . ' Book an appointment</a>'
		. '<a class="rh-btn rh-btn--ghost" href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . rh_icon( 'phone' ) . ' ' . esc_html( $c['phone'] ) . '</a>'
		. '</div>'
		. '<ul class="rh-hero__facts">'
		. '<li>' . rh_icon( 'check' ) . ' Same-week appointments</li>'
		. '<li>' . rh_icon( 'check' ) . ' Most insurance accepted</li>'
		. '<li>' . rh_icon( 'check' ) . ' Telehealth available</li>'
		. '</ul></div>'
		. '<div class="rh-hero__media">'
		. rh_img( 'hero', array( 800, 1400 ), 'A doctor in a white coat talking with a smiling patient in a bright consultation room', '(max-width: 900px) 100vw, 600px', 1600 / 1100, true )
		. '<div class="rh-hero__card">' . rh_icon( 'clock' ) . '<div><strong>Opening hours</strong><span>Mon–Fri 8 am – 7 pm · Sat 9 am – 2 pm</span></div></div>'
		. '</div></div></section>';
}

/**
 * Three quick-info cards under the hero.
 *
 * @return string
 */
function rh_quick_info() {
	$c    = rh_clinic();
	$rows = '';
	foreach ( $c['hours'] as $h ) {
		$rows .= '<li><span>' . esc_html( $h[0] ) . '</span><span>' . esc_html( $h[1] ) . '</span></li>';
	}
	return '<section class="rh-quick"><div class="rh-container rh-quick__grid">'
		. '<div class="rh-quick__card rh-quick__card--accent">' . rh_icon( 'calendar' ) . '<h2>Book online</h2><p>Choose a service, a doctor and a time that suits you. We confirm every request within one working day.</p><a class="rh-link-arrow" href="' . rh_url( 'book-appointment' ) . '">Request an appointment ' . rh_icon( 'arrow' ) . '</a></div>'
		. '<div class="rh-quick__card">' . rh_icon( 'clock' ) . '<h2>Opening hours</h2><ul class="rh-hours">' . $rows . '</ul></div>'
		. '<div class="rh-quick__card">' . rh_icon( 'alert' ) . '<h2>Urgent care</h2><p>Same-day slots are held every morning for urgent problems. Call <a href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . esc_html( $c['phone'] ) . '</a>. In a medical emergency, call 911.</p></div>'
		. '</div></section>';
}

/**
 * Services grid.
 *
 * @param bool $link_each Link each card to the Services page anchor.
 * @return string
 */
function rh_services_grid( $link_each = true ) {
	$cards = '';
	foreach ( rh_services() as $key => $s ) {
		$cards .= '<article class="rh-service-card">'
			. '<span class="rh-icon-badge">' . rh_icon( $s[1] ) . '</span>'
			. '<h3>' . esc_html( $s[0] ) . '</h3>'
			. '<p>' . esc_html( $s[2] ) . '</p>'
			. ( $link_each ? '<a class="rh-link-arrow" href="' . rh_url( 'services', $key ) . '" aria-label="' . esc_attr( 'Learn more about ' . $s[0] ) . '">Learn more ' . rh_icon( 'arrow' ) . '</a>' : '' )
			. '</article>';
	}
	return '<div class="rh-services-grid">' . $cards . '</div>';
}

/**
 * Home: services section.
 *
 * @return string
 */
function rh_home_services() {
	return '<section class="rh-section rh-section--alt" id="services"><div class="rh-container">'
		. rh_heading( 'Our services', 'Complete care for your whole family', 'From your child\'s first check-up to managing a long-term condition, our doctors, dentists and nurses work together in one place.' )
		. rh_services_grid()
		. '<p class="rh-center"><a class="rh-btn rh-btn--outline" href="' . rh_url( 'services' ) . '">View all services</a></p>'
		. '</div></section>';
}

/**
 * Home: about / why choose us.
 *
 * @return string
 */
function rh_home_about() {
	$points = array(
		array( 'users', 'One team, one record', 'Your doctor, dentist and specialists share one patient record, so you never have to repeat your story.' ),
		array( 'calendar', 'Appointments that fit your week', 'Early, evening and Saturday appointments, with same-day slots for urgent problems.' ),
		array( 'video', 'Telehealth when it suits you', 'Follow-ups, prescription renewals and results by secure video call.' ),
		array( 'shield', 'Clear, honest advice', 'We explain your options and costs before any treatment, in plain language.' ),
	);
	$list = '';
	foreach ( $points as $p ) {
		$list .= '<li><span class="rh-icon-badge rh-icon-badge--sm">' . rh_icon( $p[0] ) . '</span><div><h3>' . esc_html( $p[1] ) . '</h3><p>' . esc_html( $p[2] ) . '</p></div></li>';
	}
	return '<section class="rh-section" id="about"><div class="rh-container rh-split">'
		. '<div class="rh-split__media">' . rh_img( 'clinic', array( 700, 1200 ), 'The bright, modern waiting area of Riverside Family Health with plants and natural light', '(max-width: 900px) 100vw, 560px', 1.4 ) . '</div>'
		. '<div class="rh-split__text">'
		. rh_heading( 'Why Riverside', 'A modern family practice that still feels personal', 'We built Riverside Family Health around a simple idea: good medicine starts with time, attention and a team that knows you.', false )
		. '<ul class="rh-points">' . $list . '</ul>'
		. '</div></div></section>';
}

/**
 * Doctor cards.
 *
 * @param bool $full Show biography and details (Doctors page).
 * @return string
 */
function rh_doctor_cards( $full = false ) {
	$cards = '';
	foreach ( rh_doctors() as $key => $d ) {
		$cards .= '<article class="rh-doctor' . ( $full ? ' rh-doctor--full' : '' ) . '" id="' . esc_attr( 'dr-' . $key ) . '">'
			. '<div class="rh-doctor__photo">' . rh_doctor_img( $key, $full ? '(max-width: 700px) 100vw, 300px' : '(max-width: 600px) 100vw, 360px' ) . '</div>'
			. '<div class="rh-doctor__body">'
			. ( $full ? '<h2>' : '<h3>' ) . esc_html( $d[0] ) . ', <span>' . esc_html( $d[1] ) . '</span>' . ( $full ? '</h2>' : '</h3>' )
			. '<p class="rh-doctor__role">' . esc_html( $d[2] ) . '</p>'
			. ( $full
				? '<p>' . esc_html( $d[4] ) . '</p><dl class="rh-doctor__meta"><dt>Education</dt><dd>' . esc_html( $d[5] ) . '</dd><dt>Languages</dt><dd>' . esc_html( $d[6] ) . '</dd></dl>'
					. '<a class="rh-btn rh-btn--primary rh-btn--sm" href="' . esc_url( add_query_arg( 'doctor', $key, home_url( '/book-appointment/' ) ) ) . '#booking">Book with ' . esc_html( preg_replace( '/^Dr\. .* (\S+)$/', 'Dr. $1', $d[0] ) ) . '</a>'
				: '<a class="rh-link-arrow" href="' . rh_url( 'doctors', 'dr-' . $key ) . '" aria-label="' . esc_attr( 'View profile of ' . $d[0] ) . '">View profile ' . rh_icon( 'arrow' ) . '</a>' )
			. '</div></article>';
	}
	return '<div class="rh-doctors' . ( $full ? ' rh-doctors--full' : '' ) . '">' . $cards . '</div>';
}

/**
 * Home: doctors section.
 *
 * @return string
 */
function rh_home_doctors() {
	return '<section class="rh-section rh-section--alt" id="doctors"><div class="rh-container">'
		. rh_heading( 'Meet our doctors', 'Experienced specialists who know you by name', 'Board-certified physicians and dentists covering family medicine, internal medicine, women\'s health, pediatrics, dentistry and cardiology.' )
		. rh_doctor_cards()
		. '</div></section>';
}

/**
 * How booking works.
 *
 * @return string
 */
function rh_steps() {
	$steps = array(
		array( 'Choose your care', 'Select a service and, if you like, a preferred doctor.' ),
		array( 'Pick a time', 'Tell us the day and time of day that works best for you.' ),
		array( 'Get confirmation', 'Our team confirms your appointment by phone or email within one working day.' ),
	);
	$out = '';
	foreach ( $steps as $i => $s ) {
		$out .= '<li><span class="rh-step-num">' . ( $i + 1 ) . '</span><h3>' . esc_html( $s[0] ) . '</h3><p>' . esc_html( $s[1] ) . '</p></li>';
	}
	return '<section class="rh-section"><div class="rh-container">'
		. rh_heading( 'Booking made simple', 'Your appointment in three steps' )
		. '<ol class="rh-steps">' . $out . '</ol>'
		. '</div></section>';
}

/**
 * FAQ accordion (native details/summary, no JavaScript).
 *
 * @param int $limit Number of questions.
 * @return string
 */
function rh_faq( $limit = 0 ) {
	$items = $limit ? array_slice( rh_faqs(), 0, $limit ) : rh_faqs();
	$out   = '';
	foreach ( $items as $f ) {
		$out .= '<details class="rh-faq__item"><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[1] ) . '</p></details>';
	}
	return '<div class="rh-faq">' . $out . '</div>';
}

/**
 * Home: FAQ section.
 *
 * @return string
 */
function rh_home_faq() {
	return '<section class="rh-section rh-section--alt" id="faq"><div class="rh-container rh-narrow">'
		. rh_heading( 'Questions', 'Frequently asked questions' )
		. rh_faq( 5 )
		. '<p class="rh-center"><a class="rh-link-arrow" href="' . rh_url( 'patients' ) . '">More patient information ' . rh_icon( 'arrow' ) . '</a></p>'
		. '</div></section>';
}

/**
 * Call-to-action band.
 *
 * @return string
 */
function rh_cta() {
	$c = rh_clinic();
	return '<section class="rh-cta"><div class="rh-container rh-cta__inner">'
		. '<div><h2>Ready to book your visit?</h2><p>New and returning patients welcome. Most appointments are available within the same week.</p></div>'
		. '<div class="rh-actions">'
		. '<a class="rh-btn rh-btn--light" href="' . rh_url( 'book-appointment' ) . '">' . rh_icon( 'calendar' ) . ' Book an appointment</a>'
		. '<a class="rh-btn rh-btn--on-dark" href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . rh_icon( 'phone' ) . ' Call ' . esc_html( $c['phone'] ) . '</a>'
		. '</div></div></section>';
}

/**
 * Contact details list.
 *
 * @return string
 */
function rh_contact_list() {
	$c     = rh_clinic();
	$hours = '';
	foreach ( $c['hours'] as $h ) {
		$hours .= esc_html( $h[0] . ': ' . $h[1] ) . '<br>';
	}
	return '<ul class="rh-contact-list">'
		. '<li>' . rh_icon( 'pin' ) . '<div><strong>Address</strong><span>' . esc_html( $c['address'] ) . '<br>' . esc_html( $c['city'] ) . '</span><a href="' . esc_url( $c['maps'] ) . '" rel="noopener" target="_blank">Get directions</a></div></li>'
		. '<li>' . rh_icon( 'phone' ) . '<div><strong>Appointments</strong><a href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . esc_html( $c['phone'] ) . '</a></div></li>'
		. '<li>' . rh_icon( 'mail' ) . '<div><strong>Email</strong><a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a></div></li>'
		. '<li>' . rh_icon( 'clock' ) . '<div><strong>Opening hours</strong><span>' . $hours . '</span></div></li>'
		. '</ul>';
}

/**
 * Home: contact preview.
 *
 * @return string
 */
function rh_home_contact() {
	return '<section class="rh-section" id="contact"><div class="rh-container rh-split rh-split--top">'
		. '<div class="rh-split__text">' . rh_heading( 'Visit us', 'Find Riverside Family Health', 'Free patient parking, step-free access and a pharmacy next door.', false ) . rh_contact_list() . '</div>'
		. '<div class="rh-split__media">' . rh_img( 'reception', array( 700, 1200 ), 'The reception desk of a modern clinic with soft lighting', '(max-width: 900px) 100vw, 560px', 1.4 ) . '</div>'
		. '</div></section>';
}

/**
 * Logo mark (inline SVG, matches the site icon).
 *
 * @return string
 */
function rh_logo() {
	return '<a class="rh-logo" href="' . esc_url( home_url( '/' ) ) . '"><svg class="rh-logo__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><rect width="32" height="32" rx="8" fill="#0F766E"/><path d="M13 8h6v5h5v6h-5v5h-6v-5H8v-6h5z" fill="#fff"/></svg><span>Riverside<small>Family Health</small></span></a>';
}

/**
 * Main navigation items: slug => label.
 *
 * @return array
 */
function rh_nav_items() {
	return array(
		''         => 'Home',
		'services' => 'Services',
		'doctors'  => 'Doctors',
		'patients' => 'Patients',
		'contact'  => 'Contact',
	);
}

/**
 * Navigation links, marking the current page.
 *
 * @return string
 */
function rh_nav_links() {
	$here = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH ), '/' );
	$out  = '';
	foreach ( rh_nav_items() as $slug => $label ) {
		$out .= '<li><a href="' . rh_url( $slug ) . '"' . ( $here === $slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	return $out;
}

/**
 * Site header: demo notice, top bar, logo and navigation.
 *
 * @return string
 */
function rh_header() {
	$c     = rh_clinic();
	$links = rh_nav_links();
	return '<a class="rh-skip" href="#main">Skip to content</a>'
		. '<div class="rh-demo-bar">Demo website by <a href="https://mouzzambuild.com/">Mouzzam Builds</a> — the clinic, people and data are fictional.</div>'
		. '<div class="rh-topbar"><div class="rh-container"><ul>'
		. '<li>' . rh_icon( 'phone' ) . ' <a href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . esc_html( $c['phone'] ) . '</a></li>'
		. '<li class="rh-hide-sm">' . rh_icon( 'clock' ) . ' Mon–Fri 8 am – 7 pm · Sat 9 am – 2 pm</li>'
		. '</ul><ul><li class="rh-hide-sm">' . rh_icon( 'pin' ) . ' ' . esc_html( $c['address'] . ', ' . $c['city'] ) . '</li>'
		. '<li>' . rh_icon( 'alert' ) . ' Emergency? Call 911</li></ul></div></div>'
		. '<header class="rh-header"><div class="rh-container">' . rh_logo()
		. '<nav class="rh-nav" aria-label="Main"><ul>' . $links . '</ul>'
		. '<details class="rh-menu"><summary>' . rh_icon( 'menu' ) . ' Menu</summary><ul>' . $links . '<li><a href="' . rh_url( 'book-appointment' ) . '">Book an appointment</a></li></ul></details>'
		. '<a class="rh-btn rh-btn--primary rh-btn--sm" href="' . rh_url( 'book-appointment' ) . '">' . rh_icon( 'calendar' ) . ' Book appointment</a>'
		. '</nav></div></header>';
}

/**
 * Site footer.
 *
 * @return string
 */
function rh_footer() {
	$c        = rh_clinic();
	$services = '';
	foreach ( array_slice( rh_services(), 0, 6, true ) as $k => $s ) {
		$services .= '<li><a href="' . rh_url( 'services', $k ) . '">' . esc_html( $s[0] ) . '</a></li>';
	}
	$hours = '';
	foreach ( $c['hours'] as $h ) {
		$hours .= '<li>' . esc_html( $h[0] . ': ' . $h[1] ) . '</li>';
	}
	return '<footer class="rh-footer"><div class="rh-container"><div class="rh-footer__grid">'
		. '<div>' . rh_logo() . '<p>Family medicine, pediatrics, women\'s health, dentistry and heart health under one roof in Riverside.</p></div>'
		. '<div><h2>Services</h2><ul>' . $services . '</ul></div>'
		. '<div><h2>Patients</h2><ul><li><a href="' . rh_url( 'book-appointment' ) . '">Book an appointment</a></li><li><a href="' . rh_url( 'doctors' ) . '">Our doctors</a></li><li><a href="' . rh_url( 'patients' ) . '">Patient information</a></li><li><a href="' . rh_url( 'patients', 'insurance' ) . '">Insurance &amp; billing</a></li><li><a href="' . rh_url( 'contact' ) . '">Contact us</a></li></ul></div>'
		. '<div><h2>Contact</h2><ul><li><address>' . esc_html( $c['address'] ) . '<br>' . esc_html( $c['city'] ) . '</address></li><li><a href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . esc_html( $c['phone'] ) . '</a></li><li><a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a></li>' . $hours . '</ul></div>'
		. '</div><div class="rh-footer__bottom"><span>© ' . esc_html( gmdate( 'Y' ) ) . ' Riverside Family Health · <a href="' . rh_url( 'privacy-policy' ) . '">Privacy policy</a></span>'
		. '<span>Demo website designed and built by <a href="https://mouzzambuild.com/">Mouzzam Builds</a>. Clinic, people and data are fictional.</span></div>'
		. '</div></footer>';
}

/**
 * Wrap section HTML in a Custom HTML block for patterns.
 *
 * @param string $html Markup.
 * @return string
 */
function rh_block( $html ) {
	return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->\n";
}

/**
 * Services page rows.
 *
 * @return string
 */
function rh_service_rows() {
	$out = '';
	foreach ( rh_services() as $key => $s ) {
		$items = '';
		foreach ( $s[4] as $b ) {
			$items .= '<li>' . rh_icon( 'check' ) . esc_html( $b ) . '</li>';
		}
		$media = $s[5]
			? rh_img( $s[5], array( 480, 800 ), $s[0] . ' at Riverside Family Health', '(max-width: 900px) 100vw, 560px', 900 / 640 )
			: '<div class="rh-service-row__panel" aria-hidden="true">' . rh_icon( $s[1] ) . '</div>';
		$out .= '<article class="rh-service-row" id="' . esc_attr( $key ) . '">'
			. '<div><span class="rh-icon-badge">' . rh_icon( $s[1] ) . '</span><h2>' . esc_html( $s[0] ) . '</h2><p>' . esc_html( $s[3] ) . '</p><ul class="rh-checks">' . $items . '</ul>'
			. '<a class="rh-btn rh-btn--primary rh-btn--sm" href="' . esc_url( add_query_arg( 'service', $key, home_url( '/book-appointment/' ) ) ) . '#booking">Book ' . esc_html( $s[0] ) . '</a></div>'
			. '<div class="rh-service-row__media">' . $media . '</div>'
			. '</article>';
	}
	return '<section class="rh-section rh-section--tight"><div class="rh-container">' . $out . '</div></section>';
}

/**
 * Urgent-care card.
 *
 * @return string
 */
function rh_urgent_card() {
	$c = rh_clinic();
	return '<div class="rh-aside__card rh-aside__card--urgent"><h2>' . rh_icon( 'alert' ) . ' Need help today?</h2><p>For urgent problems, call <a href="tel:' . esc_attr( $c['phone_raw'] ) . '">' . esc_html( $c['phone'] ) . '</a> for a same-day slot. In a medical emergency, call <strong>911</strong> or go to the nearest emergency department.</p></div>';
}

/**
 * Sidebar on the booking page.
 *
 * @return string
 */
function rh_book_aside() {
	$c     = rh_clinic();
	$hours = '';
	foreach ( $c['hours'] as $h ) {
		$hours .= '<li><span>' . esc_html( $h[0] ) . '</span><span>' . esc_html( $h[1] ) . '</span></li>';
	}
	return '<aside class="rh-aside">'
		. '<div class="rh-aside__card"><h2>What happens next?</h2><ul class="rh-checks">'
		. '<li>' . rh_icon( 'check' ) . 'We check availability with your chosen doctor.</li>'
		. '<li>' . rh_icon( 'check' ) . 'We call or email to confirm within one working day.</li>'
		. '<li>' . rh_icon( 'check' ) . 'You receive a reminder the day before your visit.</li>'
		. '</ul></div>'
		. rh_urgent_card()
		. '<div class="rh-aside__card"><h2>Opening hours</h2><ul class="rh-hours">' . $hours . '</ul></div>'
		. '</aside>';
}

/**
 * Patients page content.
 *
 * @return string
 */
function rh_patients_content() {
	$cards = array(
		array( 'users', 'Your first visit', 'Arrive 15 minutes early so we can complete your registration.', array( 'Photo ID and insurance card', 'List of current medications', 'Recent test results or letters', 'Your previous doctor\'s details' ), 'first-visit' ),
		array( 'shield', 'Insurance & billing', 'We accept Medicare, Medicaid and most major private plans. We will confirm your coverage before your visit.', array( 'Co-pays are collected at check-in', 'Clear estimates before any treatment', 'Card, cash and contactless payments', 'Payment plans for larger treatments' ), 'insurance' ),
		array( 'video', 'Telehealth', 'Many appointments can be done by secure video call from home.', array( 'Follow-up visits', 'Prescription renewals', 'Test results discussions', 'Minor illness advice' ), 'telehealth' ),
	);
	$out = '';
	foreach ( $cards as $c ) {
		$items = '';
		foreach ( $c[3] as $i ) {
			$items .= '<li>' . esc_html( $i ) . '</li>';
		}
		$out .= '<div class="rh-info-card" id="' . esc_attr( $c[4] ) . '"><span class="rh-icon-badge">' . rh_icon( $c[0] ) . '</span><h2>' . esc_html( $c[1] ) . '</h2><p>' . esc_html( $c[2] ) . '</p><ul>' . $items . '</ul></div>';
	}
	return '<section class="rh-section"><div class="rh-container"><div class="rh-info-grid">' . $out . '</div>'
		. '<div class="rh-narrow" style="margin:0 auto">' . rh_heading( 'Questions', 'Frequently asked questions' ) . rh_faq() . '</div>'
		. '</div></section>';
}

/**
 * Privacy policy text for the demo site.
 *
 * @return string
 */
function rh_privacy_content() {
	$p = array(
		array( 'This is a demonstration website', 'Riverside Family Health is a fictional practice created by Mouzzam Builds to demonstrate a website for a medical clinic. The doctors, address, phone numbers and other details are invented. Please do not send real medical or personal information through this website.' ),
		array( 'What we collect', 'If you use the booking or contact form, we receive the details you type: your name, email, phone number, the service or topic you choose, your preferred date and time, and your message. We do not use analytics, advertising or tracking cookies.' ),
		array( 'How we use it', 'Form submissions are stored in the website\'s administration area and sent by email to the site owner, only so that a demonstration enquiry can be seen and answered. They are never sold or shared.' ),
		array( 'How long we keep it', 'Demonstration submissions are deleted regularly and kept for no longer than 90 days.' ),
		array( 'Your rights', 'You can ask for a copy of your data or for it to be deleted at any time by contacting the website owner through mouzzambuild.com.' ),
	);
	$out = '';
	foreach ( $p as $s ) {
		$out .= '<h2>' . esc_html( $s[0] ) . '</h2><p>' . esc_html( $s[1] ) . '</p>';
	}
	return '<section class="rh-section"><div class="rh-container"><div class="rh-prose">' . $out . '</div></div></section>';
}
