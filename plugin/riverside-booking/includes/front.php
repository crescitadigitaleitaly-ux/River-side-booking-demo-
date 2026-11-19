<?php
/**
 * Front end: the booking form (used by the theme's booking page), the
 * patient "manage appointment" page and a no-JavaScript fallback handler.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load front assets (only on pages that show a form).
 */
function rb_front_assets() {
	wp_enqueue_style( 'riverside-booking', RB_URL . 'assets/booking.css', array(), RB_VERSION );
	wp_enqueue_script( 'riverside-booking', RB_URL . 'assets/booking.js', array(), RB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}

/**
 * Small inline icon (uses the theme's set when available).
 *
 * @param string $name Icon.
 * @return string
 */
function rb_icon( $name ) {
	return function_exists( 'rh_icon' ) ? rh_icon( $name ) : '';
}

/**
 * The booking form.
 *
 * @return string HTML.
 */
function rb_booking_form() {
	rb_front_assets();
	$services = rb_services();
	$doctors  = rb_doctors();
	$status   = isset( $_GET['rb'] ) ? sanitize_key( wp_unslash( $_GET['rb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$ref      = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$msg      = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.

	$out  = '<div class="rh-form-wrap rb-booking" id="booking" data-rb-booking data-rb-api="' . esc_url( rest_url( 'rb/v1/' ) ) . '">';
	$out .= '<div class="rb-live" aria-live="polite" data-rb-notice>';
	if ( 'sent' === $status && preg_match( '/^RFH-[A-Z0-9]{4}-\d{2}$/', $ref ) ) {
		$out .= '<div class="rh-notice rh-notice--ok" role="status"><strong>Request received: ' . esc_html( $ref ) . '.</strong> Status: pending. Our reception team will confirm the time with you.</div>';
	} elseif ( 'error' === $status ) {
		$out .= '<div class="rh-notice rh-notice--error" role="alert"><strong>Sorry, we could not send your request.</strong> ' . esc_html( $msg ) . '</div>';
	}
	$out .= '</div>';
	$out .= '<p class="rh-demo-note">' . rb_icon( 'alert' ) . ' Demonstration website with a fictional clinic. Please do not enter real medical information.</p>';

	$out .= '<form class="rh-form rb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-rb-form>';
	$out .= '<input type="hidden" name="action" value="rb_submit"><input type="hidden" name="idem" value=""><input type="hidden" name="rb_check" value=""><input type="hidden" name="date" value="">';
	$out .= '<div class="rh-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';

	// Step 1.
	$out .= '<fieldset class="rb-step"><legend class="rb-step__title"><span class="rb-step__n">1</span> What do you need?</legend><div class="rh-form__grid">';
	$out .= '<div class="rh-field"><label for="rb-service">Service <span class="rh-req" aria-hidden="true">*</span></label><select id="rb-service" name="service" required><option value="">Please choose…</option>';
	foreach ( $services as $s ) {
		$docs = array_map( fn( $d ) => $d->slug, rb_doctors_for_service( $s->id ) );
		$out .= '<option value="' . esc_attr( $s->slug ) . '" data-duration="' . (int) $s->duration . '" data-doctors="' . esc_attr( implode( ',', $docs ) ) . '">' . esc_html( $s->name ) . '</option>';
	}
	$out .= '</select></div>';
	$out .= '<div class="rh-field"><label for="rb-doctor">Doctor</label><select id="rb-doctor" name="doctor" aria-describedby="rb-doctor-help"><option value="any">No preference (first available)</option>';
	foreach ( $doctors as $d ) {
		$svc  = array_map( fn( $id ) => $services[ $id ]->slug ?? '', $d->services );
		$out .= '<option value="' . esc_attr( $d->slug ) . '" data-services="' . esc_attr( implode( ',', array_filter( $svc ) ) ) . '">' . esc_html( $d->name . ' – ' . $d->specialty ) . '</option>';
	}
	$out .= '</select><p class="rb-help" id="rb-doctor-help">Only doctors who offer the chosen service are listed.</p></div>';
	$out .= '</div></fieldset>';

	// Step 2.
	$out .= '<fieldset class="rb-step" data-rb-when><legend class="rb-step__title"><span class="rb-step__n">2</span> Choose a day and time</legend>';
	$out .= '<div class="rb-days-nav"><button type="button" class="rb-navbtn" data-rb-prev disabled aria-label="Previous two weeks">‹</button><div class="rb-days" data-rb-days role="group" aria-label="Days"><p class="rb-muted">Choose a service to see free times.</p></div><button type="button" class="rb-navbtn" data-rb-next disabled aria-label="Next two weeks">›</button></div>';
	$out .= '<div class="rb-slots" data-rb-slots role="radiogroup" aria-label="Free times"></div>';
	$out .= '<noscript><div class="rh-notice rh-notice--error">Live availability needs JavaScript. Please enable it, or call us on ' . esc_html( function_exists( 'rh_clinic' ) ? rh_clinic()['phone'] : '' ) . ' to book.</div></noscript>';
	$out .= '<p class="rb-help">Times are shown in clinic time (Pacific). Sundays and clinic holidays are closed.</p></fieldset>';

	// Step 3.
	$out .= '<fieldset class="rb-step"><legend class="rb-step__title"><span class="rb-step__n">3</span> Your details</legend><div class="rh-form__grid">';
	$out .= '<div class="rh-field"><label for="rb-name">Full name <span class="rh-req" aria-hidden="true">*</span></label><input id="rb-name" name="name" type="text" maxlength="120" autocomplete="name" required></div>';
	$out .= '<div class="rh-field"><label for="rb-email">Email <span class="rh-req" aria-hidden="true">*</span></label><input id="rb-email" name="email" type="email" maxlength="190" autocomplete="email" required></div>';
	$out .= '<div class="rh-field"><label for="rb-phone">Phone <span class="rh-req" aria-hidden="true">*</span></label><input id="rb-phone" name="phone" type="tel" maxlength="20" autocomplete="tel" pattern="[0-9 +().\-]{7,20}" required></div>';
	$out .= '<div class="rh-field"><label for="rb-visit">Type of visit <span class="rh-req" aria-hidden="true">*</span></label><select id="rb-visit" name="visit_type" required><option value="">Please choose…</option>';
	foreach ( rb_visit_types() as $k => $l ) {
		$out .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	$out .= '</select></div>';
	$out .= '<div class="rh-field rh-field--wide"><fieldset><legend>Are you a new or returning patient? <span class="rh-req" aria-hidden="true">*</span></legend><div class="rh-radios"><label class="rh-radio"><input type="radio" name="patient_type" value="new" checked required> New patient</label><label class="rh-radio"><input type="radio" name="patient_type" value="returning"> Returning patient</label></div></fieldset></div>';
	$out .= '<div class="rh-field rh-field--wide"><label for="rb-note">Anything reception should know? (optional)</label><textarea id="rb-note" name="note" rows="2" maxlength="300" aria-describedby="rb-note-help"></textarea><p class="rb-help" id="rb-note-help">For example "I need step-free access". Please do not include symptoms or medical details.</p></div>';
	$out .= '<div class="rh-field rh-field--wide"><label class="rh-check"><input type="checkbox" name="consent" value="1" required> <span>I agree that Riverside Family Health may use these details to respond to my request, as described in the <a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">privacy policy</a>. <span class="rh-req" aria-hidden="true">*</span></span></label></div>';
	$out .= '</div></fieldset>';

	$out .= '<div class="rb-summary" data-rb-summary hidden></div>';
	$out .= '<button type="submit" class="rh-btn rh-btn--primary rb-submit">' . rb_icon( 'calendar' ) . ' <span>Send appointment request</span></button>';
	$out .= '<p class="rh-form__small">Your request stays <strong>pending</strong> until our reception team confirms it. Fields marked * are required. For emergencies, call 911.</p>';
	$out .= '</form>';
	$out .= '<div class="rb-success" data-rb-success hidden tabindex="-1"></div>';
	$out .= '</div>';
	return $out;
}
add_shortcode( 'rb_booking_form', 'rb_booking_form' );

/**
 * Patient manage page (secure link target).
 *
 * @return string
 */
function rb_manage_shortcode() {
	rb_front_assets();
	return '<section class="rh-section"><div class="rh-container rb-manage-wrap"><div class="rh-form-wrap rb-manage" data-rb-manage data-rb-api="' . esc_url( rest_url( 'rb/v1/' ) ) . '">'
		. '<p class="rh-demo-note">' . rb_icon( 'alert' ) . ' Demonstration website with a fictional clinic.</p>'
		. '<div aria-live="polite" data-rb-notice></div><div data-rb-manage-body><p class="rb-muted">Loading your appointment…</p></div>'
		. '<noscript><div class="rh-notice rh-notice--error">This page needs JavaScript. Please call the clinic to change your appointment.</div></noscript>'
		. '</div></div></section>';
}
add_shortcode( 'rb_manage', 'rb_manage_shortcode' );

/**
 * The manage page carries a secret token in its URL: no caching, no referrer.
 */
function rb_manage_headers() {
	$page = (int) get_option( 'rb_manage_page' );
	if ( $page && is_page( $page ) ) {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
}
add_action( 'template_redirect', 'rb_manage_headers' );

/**
 * Keep the manage page out of the theme navigation/sitemaps.
 *
 * @param array $robots Robots.
 * @return array
 */
add_filter(
	'wp_robots',
	function ( $robots ) {
		$page = (int) get_option( 'rb_manage_page' );
		if ( $page && is_page( $page ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);

/**
 * No-JavaScript fallback: a plain form post goes through the same checks.
 */
function rb_handle_post() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form behind a page cache; protected by honeypot, rate limit and idempotency.
	$back = home_url( '/book-appointment/' );
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'rb' => 'sent', 'ref' => 'RFH-0000-00' ), $back ) . '#booking' );
		exit;
	}
	$in          = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in rb_create_request().
	$in['human'] = isset( $in['rb_check'] ) && 'human' === $in['rb_check'];
	// phpcs:enable
	$res = rb_create_request( $in );
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( add_query_arg( array( 'rb' => 'error', 'msg' => rawurlencode( $res->get_error_message() ) ), $back ) . '#booking' );
		exit;
	}
	wp_safe_redirect( add_query_arg( array( 'rb' => 'sent', 'ref' => $res['appt']->reference ), $back ) . '#booking' );
	exit;
}
add_action( 'admin_post_nopriv_rb_submit', 'rb_handle_post' );
add_action( 'admin_post_rb_submit', 'rb_handle_post' );
