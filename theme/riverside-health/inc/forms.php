<?php
/**
 * Booking and contact forms.
 *
 * Submissions go to admin-post.php, are validated and sanitised, stored as
 * private "Requests" in wp-admin and emailed to the site admin. There is no
 * nonce on purpose: pages are fully cached for speed and a cached nonce would
 * expire. Spam is filtered with a honeypot, a JavaScript check and a per-IP
 * rate limit instead.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the private Requests post type that stores submissions.
 */
function rh_register_requests() {
	register_post_type(
		'rh_request',
		array(
			'labels'          => array(
				'name'          => 'Requests',
				'singular_name' => 'Request',
				'menu_name'     => 'Requests',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-calendar-alt',
			'menu_position'   => 25,
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'rh_register_requests' );

/**
 * Field definitions per form.
 *
 * @param string $type booking|contact.
 * @return array name => [ label, type, required, options ]
 */
function rh_form_fields( $type ) {
	if ( 'booking' === $type ) {
		$services = array();
		foreach ( rh_services() as $k => $s ) {
			$services[ $k ] = $s[0];
		}
		$doctors = array( 'any' => 'No preference' );
		foreach ( rh_doctors() as $k => $d ) {
			$doctors[ $k ] = $d[0] . ' – ' . $d[2];
		}
		return array(
			'name'         => array( 'Full name', 'text', true ),
			'email'        => array( 'Email', 'email', true ),
			'phone'        => array( 'Phone', 'tel', true ),
			'patient_type' => array( 'Are you a new or returning patient?', 'radio', true, array( 'new' => 'New patient', 'returning' => 'Returning patient' ) ),
			'service'      => array( 'Service', 'select', true, $services ),
			'doctor'       => array( 'Preferred doctor', 'select', false, $doctors ),
			'date'         => array( 'Preferred date', 'date', true ),
			'time'         => array( 'Preferred time', 'select', true, array( 'morning' => 'Morning (8 am – 12 pm)', 'afternoon' => 'Afternoon (12 – 4 pm)', 'evening' => 'Evening (4 – 7 pm)' ) ),
			'message'      => array( 'Reason for your visit (optional)', 'textarea', false ),
		);
	}
	return array(
		'name'    => array( 'Full name', 'text', true ),
		'email'   => array( 'Email', 'email', true ),
		'phone'   => array( 'Phone (optional)', 'tel', false ),
		'topic'   => array( 'Topic', 'select', true, array( 'general' => 'General question', 'appointments' => 'Appointments', 'billing' => 'Billing & insurance', 'records' => 'Medical records', 'feedback' => 'Feedback' ) ),
		'message' => array( 'Message', 'textarea', true ),
	);
}

/**
 * Render a form.
 *
 * @param string $type booking|contact.
 * @return string
 */
function rh_form( $type ) {
	$type   = 'booking' === $type ? 'booking' : 'contact';
	$anchor = 'booking' === $type ? 'booking' : 'contact-form';
	$status = isset( $_GET['rh'] ) ? sanitize_key( wp_unslash( $_GET['rh'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$form   = isset( $_GET['rh_form'] ) ? sanitize_key( wp_unslash( $_GET['rh_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$pre    = array(
		'doctor'  => isset( $_GET['doctor'] ) ? sanitize_key( wp_unslash( $_GET['doctor'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill only.
		'service' => isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefill only.
	);
	if ( $pre['doctor'] && isset( rh_doctors()[ $pre['doctor'] ] ) && ! $pre['service'] ) {
		$pre['service'] = rh_doctors()[ $pre['doctor'] ][3];
	}

	$out = '<div class="rh-form-wrap" id="' . esc_attr( $anchor ) . '">';
	if ( $form === $type && 'sent' === $status ) {
		$out .= '<div class="rh-notice rh-notice--ok" role="status"><strong>Thank you, your request has been sent.</strong> '
			. ( 'booking' === $type ? 'Our team will contact you within one working day to confirm your appointment.' : 'We will reply within one working day.' )
			. '</div>';
	} elseif ( $form === $type && 'error' === $status ) {
		$out .= '<div class="rh-notice rh-notice--error" role="alert"><strong>Sorry, we could not send your request.</strong> Please check the required fields and try again, or call us.</div>';
	} elseif ( $form === $type && 'wait' === $status ) {
		$out .= '<div class="rh-notice rh-notice--error" role="alert"><strong>Too many requests.</strong> Please wait a few minutes before sending another one, or call us.</div>';
	}

	$out .= '<p class="rh-demo-note">' . rh_icon( 'alert' ) . ' This is a demonstration website. Please do not enter real medical information.</p>';
	$out .= '<form class="rh-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-rh-form>';
	$out .= '<input type="hidden" name="action" value="rh_submit"><input type="hidden" name="rh_form" value="' . esc_attr( $type ) . '"><input type="hidden" name="rh_check" value="">';
	$out .= '<div class="rh-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="rh_website" tabindex="-1" autocomplete="off"></label></div>';
	$out .= '<div class="rh-form__grid">';

	foreach ( rh_form_fields( $type ) as $name => $f ) {
		$id   = 'rh-' . $type . '-' . $name;
		$req  = $f[2] ? ' required' : '';
		$star = $f[2] ? ' <span class="rh-req" aria-hidden="true">*</span>' : '';
		$wide = in_array( $f[1], array( 'textarea', 'radio' ), true ) ? ' rh-field--wide' : '';
		$out .= '<div class="rh-field' . $wide . '">';
		if ( 'radio' === $f[1] ) {
			$out .= '<fieldset><legend>' . esc_html( $f[0] ) . $star . '</legend><div class="rh-radios">';
			$first = true;
			foreach ( $f[3] as $v => $l ) {
				$out  .= '<label class="rh-radio"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( $first ? ' checked' : '' ) . $req . '> ' . esc_html( $l ) . '</label>';
				$first = false;
			}
			$out .= '</div></fieldset>';
		} else {
			$out .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . $star . '</label>';
			if ( 'select' === $f[1] ) {
				$out .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . '>';
				if ( $f[2] ) {
					$out .= '<option value="">Please choose…</option>';
				}
				foreach ( $f[3] as $v => $l ) {
					$out .= '<option value="' . esc_attr( $v ) . '"' . selected( $pre[ $name ] ?? '', $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				$out .= '</select>';
			} elseif ( 'textarea' === $f[1] ) {
				$out .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" maxlength="1500"' . $req . '></textarea>';
			} else {
				$auto = array(
					'name'  => 'name',
					'email' => 'email',
					'phone' => 'tel',
				);
				$out .= '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $f[1] ) . '" name="' . esc_attr( $name ) . '" maxlength="120"'
					. ( isset( $auto[ $name ] ) ? ' autocomplete="' . esc_attr( $auto[ $name ] ) . '"' : '' )
					. ( 'tel' === $f[1] ? ' pattern="[0-9 +().\-]{7,20}"' : '' )
					. ( 'date' === $f[1] ? ' data-rh-min-today' : '' )
					. $req . '>';
			}
		}
		$out .= '</div>';
	}

	$out .= '<div class="rh-field rh-field--wide"><label class="rh-check"><input type="checkbox" name="consent" value="1" required> <span>I agree that Riverside Family Health may use these details to respond to my request, as described in the <a href="' . rh_url( 'privacy-policy' ) . '">privacy policy</a>. <span class="rh-req" aria-hidden="true">*</span></span></label></div>';
	$out .= '</div><button type="submit" class="rh-btn rh-btn--primary">' . ( 'booking' === $type ? rh_icon( 'calendar' ) . ' Request appointment' : rh_icon( 'mail' ) . ' Send message' ) . '</button>';
	$out .= '<p class="rh-form__small">Fields marked * are required. We reply within one working day. For emergencies, call 911.</p>';
	$out .= '</form></div>';
	return $out;
}

/**
 * Handle a form submission.
 */
function rh_handle_submit() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form behind full-page cache; see file header.
	$type = isset( $_POST['rh_form'] ) && 'booking' === $_POST['rh_form'] ? 'booking' : 'contact';
	$back = wp_get_referer();
	if ( ! $back || wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$back = home_url( 'booking' === $type ? '/book-appointment/' : '/contact/' );
	}
	$back   = remove_query_arg( array( 'rh', 'rh_form' ), $back );
	$anchor = 'booking' === $type ? '#booking' : '#contact-form';
	$go     = function ( $status ) use ( $back, $type, $anchor ) {
		wp_safe_redirect( add_query_arg( array( 'rh' => $status, 'rh_form' => $type ), $back ) . $anchor );
		exit;
	};

	// Honeypot: bots fill every field. Pretend success.
	if ( ! empty( $_POST['rh_website'] ) ) {
		$go( 'sent' );
	}

	// Rate limit: 5 requests per IP per 10 minutes.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key   = 'rh_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		$go( 'wait' );
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

	$data = array();
	foreach ( rh_form_fields( $type ) as $name => $f ) {
		$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised below per type.
		$val = 'textarea' === $f[1] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		$val = mb_substr( $val, 0, 'textarea' === $f[1] ? 1500 : 120 );
		$ok  = true;
		if ( '' === $val ) {
			$ok = ! $f[2];
		} elseif ( 'email' === $f[1] ) {
			$ok = (bool) is_email( $val );
		} elseif ( 'tel' === $f[1] ) {
			$ok = (bool) preg_match( '/^[0-9 +().\-]{7,20}$/', $val );
		} elseif ( isset( $f[3] ) ) {
			$ok = isset( $f[3][ $val ] );
		} elseif ( 'date' === $f[1] ) {
			$d  = DateTime::createFromFormat( '!Y-m-d', $val, wp_timezone() );
			$ok = $d && $d->format( 'Y-m-d' ) === $val
				&& $d >= new DateTime( 'today', wp_timezone() )
				&& $d <= new DateTime( '+1 year', wp_timezone() );
		}
		if ( ! $ok ) {
			$go( 'error' );
		}
		$data[ $name ] = isset( $f[3][ $val ] ) ? $f[3][ $val ] : $val;
	}
	if ( empty( $_POST['consent'] ) ) {
		$go( 'error' );
	}
	$human = isset( $_POST['rh_check'] ) && 'human' === $_POST['rh_check'];
	// phpcs:enable

	$labels = rh_form_fields( $type );
	$lines  = array();
	foreach ( $data as $name => $val ) {
		$lines[] = $labels[ $name ][0] . ': ' . ( '' === $val ? '—' : $val );
	}
	$body  = implode( "\n", $lines );
	$title = ( 'booking' === $type ? 'Appointment request' : 'Message' ) . ' – ' . $data['name'] . ( $human ? '' : ' (unverified, possible spam)' );

	// Values are already plain text (tags stripped), so skip KSES, which would turn "&" into "&amp;".
	kses_remove_filters();
	$saved = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'rh_request',
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => $body,
			)
		)
	);
	kses_init_filters();
	if ( ! $saved || is_wp_error( $saved ) ) {
		$go( 'error' );
	}

	wp_mail(
		get_option( 'admin_email' ),
		'[Riverside demo] ' . $title,
		$body . "\n\nSent from " . home_url( '/' ) . ' (demo website). Visitor email: ' . $data['email'] . ' (not used as Reply-To; check it before replying).'
	);

	$go( 'sent' );
}
add_action( 'admin_post_nopriv_rh_submit', 'rh_handle_submit' );
add_action( 'admin_post_rh_submit', 'rh_handle_submit' );
