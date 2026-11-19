<?php
/**
 * Everyday clinic content that staff edit themselves (no code):
 * contact details, opening hours and a dated notice bar.
 * The theme reads it through the rh_clinic filter, so the header, footer,
 * contact page, booking page, Google data (schema) and llms.txt all update at once.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Days in display order (PHP "w": 0 = Sunday).
 *
 * @return array
 */
function rb_info_days() {
	return array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday' );
}

/**
 * Saved clinic info with defaults.
 *
 * @return array
 */
function rb_info() {
	$def = array(
		'phone'   => '(555) 014-2200',
		'urgent'  => '(555) 014-2299',
		'email'   => 'care@riversidefamilyhealth.example',
		'address' => '1200 Riverbend Avenue, Suite 100',
		'city'    => 'Riverside, CA 92501',
		'maps'    => 'https://www.google.com/maps/search/?api=1&query=1200+Riverbend+Avenue+Riverside+CA',
		'hours'   => array(
			1 => array( 0, '08:00', '19:00' ), 2 => array( 0, '08:00', '19:00' ), 3 => array( 0, '08:00', '19:00' ), 4 => array( 0, '08:00', '19:00' ), 5 => array( 0, '08:00', '19:00' ),
			6 => array( 0, '09:00', '14:00' ), 0 => array( 1, '', '' ),
		),
		'notice'  => array( 'on' => 0, 'text' => '', 'from' => '', 'to' => '' ),
		'updated' => array( 'by' => '', 'at' => '' ),
	);
	$saved = get_option( 'rb_clinic_info', array() );
	return array_replace_recursive( $def, is_array( $saved ) ? $saved : array() );
}

/**
 * "08:00" → "8 am", "13:30" → "1:30 pm".
 *
 * @param string $hm Time.
 * @param bool   $long Use "8:00 am" style.
 * @return string
 */
function rb_info_time( $hm, $long = false ) {
	list( $h, $m ) = array_map( 'intval', explode( ':', $hm ) );
	$ap = $h >= 12 ? 'pm' : 'am';
	$h12 = $h % 12 ? $h % 12 : 12;
	return ( $long || $m ) ? sprintf( '%d:%02d %s', $h12, $m, $ap ) : $h12 . ' ' . $ap;
}

/**
 * Group consecutive days with the same hours.
 *
 * @param array $hours Saved hours.
 * @return array [ [ 'Monday – Friday', '8:00 am – 7:00 pm', 'Mon–Fri', '8 am – 7 pm', [days] , open, close ] ... ]
 */
function rb_info_groups( $hours ) {
	$groups = array();
	foreach ( rb_info_days() as $n => $day ) {
		$h   = $hours[ $n ];
		$key = $h[0] ? 'closed' : $h[1] . '-' . $h[2];
		$last = count( $groups ) - 1;
		if ( $last >= 0 && $groups[ $last ]['key'] === $key ) {
			$groups[ $last ]['days'][] = $n;
			$groups[ $last ]['names'][] = $day;
		} else {
			$groups[] = array( 'key' => $key, 'days' => array( $n ), 'names' => array( $day ), 'closed' => (bool) $h[0], 'open' => $h[1], 'close' => $h[2] );
		}
	}
	return $groups;
}

/**
 * Theme bridge: replace the clinic details and hours with the saved values.
 */
add_filter(
	'rh_clinic',
	function ( $c ) {
		$i = rb_info();
		foreach ( array( 'phone', 'urgent', 'email', 'address', 'city', 'maps' ) as $k ) {
			if ( '' !== trim( (string) $i[ $k ] ) ) {
				$c[ $k ] = $i[ $k ];
			}
		}
		$digits         = preg_replace( '/\D/', '', $c['phone'] );
		$c['phone_raw'] = '+' . ( 10 === strlen( $digits ) ? '1' . $digits : $digits );
		$rows  = array();
		$short = array();
		$spec  = array();
		foreach ( rb_info_groups( $i['hours'] ) as $g ) {
			$first = $g['names'][0];
			$last  = end( $g['names'] );
			$label = count( $g['names'] ) > 1 ? $first . ' – ' . $last : $first;
			$rows[] = array( $label, $g['closed'] ? 'Closed' : rb_info_time( $g['open'], true ) . ' – ' . rb_info_time( $g['close'], true ) );
			if ( ! $g['closed'] ) {
				$abbr    = count( $g['names'] ) > 1 ? substr( $first, 0, 3 ) . '–' . substr( $last, 0, 3 ) : substr( $first, 0, 3 );
				$short[] = $abbr . ' ' . rb_info_time( $g['open'] ) . ' – ' . rb_info_time( $g['close'] );
				$spec[]  = array( $g['names'], $g['open'], $g['close'] );
			}
		}
		$c['hours']       = $rows;
		$c['hours_short'] = implode( ' · ', $short );
		$c['hours_spec']  = $spec;
		return $c;
	}
);

/**
 * The notice text if it is switched on and today is inside its dates (clinic time).
 *
 * @return string
 */
function rb_site_notice() {
	$n = rb_info()['notice'];
	if ( empty( $n['on'] ) || '' === trim( $n['text'] ) ) {
		return '';
	}
	$today = rb_now()->format( 'Y-m-d' );
	if ( ( $n['from'] && $today < $n['from'] ) || ( $n['to'] && $today > $n['to'] ) ) {
		return '';
	}
	return $n['text'];
}

/**
 * Admin page: Reception → Clinic details & hours (clinic manager + admin).
 */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'rb-reception', 'Clinic details & hours', 'Clinic details & hours', 'rb_manage_clinic', 'rb-clinic-info', 'rb_page_clinic_info' );
	},
	11
);

/**
 * Render the editor.
 */
function rb_page_clinic_info() {
	if ( ! current_user_can( 'rb_manage_clinic' ) ) {
		wp_die( 'Sorry, you are not allowed to edit clinic details.', 403 );
	}
	$i   = rb_info();
	$msg = isset( $_GET['rb_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['rb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$err = isset( $_GET['rb_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rb_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$f   = fn( $k, $label, $type = 'text', $help = '', $max = 120 ) => '<p class="rb-field"><label for="rb-' . $k . '"><strong>' . esc_html( $label ) . '</strong></label><input id="rb-' . $k . '" class="regular-text" type="' . $type . '" name="' . $k . '" maxlength="' . $max . '" value="' . esc_attr( $i[ $k ] ) . '">' . ( $help ? '<span class="rb-dim">' . esc_html( $help ) . '</span>' : '' ) . '</p>';

	echo '<div class="wrap rb-wrap"><h1>Clinic details &amp; hours</h1><p class="rb-sub">Change your phone number, address, opening hours or post a short notice. Changes appear on every page of the website straight away.</p>';
	if ( $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . ' <a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">View website ↗</a></p></div>';
	}
	if ( $err ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	}
	if ( $i['updated']['at'] ) {
		echo '<p class="rb-dim">Last changed by ' . esc_html( $i['updated']['by'] ) . ' on ' . esc_html( rb_local( $i['updated']['at'] )->format( 'M j, Y \a\t g:i a' ) ) . '.</p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'rb_save_info' );
	echo '<input type="hidden" name="action" value="rb_save_info"><div class="rb-grid">';

	echo '<div class="rb-card"><h2>Contact details</h2>';
	echo $f( 'phone', 'Main phone', 'tel', 'Shown in the header, footer, contact page and "Call" buttons.', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $f.
	echo $f( 'urgent', 'Urgent / same-day line', 'tel', '', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $f( 'email', 'Email', 'email', '', 120 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $f( 'address', 'Street address', 'text', '', 120 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $f( 'city', 'City, state, ZIP', 'text', '', 80 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $f( 'maps', 'Google Maps link', 'url', 'Paste the "Share" link from Google Maps.', 300 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';

	echo '<div class="rb-card"><h2>Opening hours</h2><p class="rb-dim">Clinic opening hours shown to patients. Each doctor\'s own working hours are set under Reception → Doctors; holidays under Reception → Closures.</p><table class="rb-hours-table"><thead><tr><th>Day</th><th>Closed</th><th>Opens</th><th>Closes</th></tr></thead><tbody>';
	foreach ( rb_info_days() as $n => $day ) {
		$h = $i['hours'][ $n ];
		echo '<tr data-rb-day><th scope="row">' . esc_html( $day ) . '</th><td><input type="checkbox" name="hours[' . (int) $n . '][closed]" value="1"' . checked( (bool) $h[0], true, false ) . ' aria-label="' . esc_attr( $day . ' closed' ) . '"></td>'
			. '<td><input type="time" step="900" name="hours[' . (int) $n . '][open]" value="' . esc_attr( $h[1] ) . '" aria-label="' . esc_attr( $day . ' opens' ) . '"></td>'
			. '<td><input type="time" step="900" name="hours[' . (int) $n . '][close]" value="' . esc_attr( $h[2] ) . '" aria-label="' . esc_attr( $day . ' closes' ) . '"></td></tr>';
	}
	echo '</tbody></table></div>';

	$n = $i['notice'];
	echo '<div class="rb-card"><h2>Notice bar</h2><p class="rb-dim">A short message shown at the top of every page between two dates, e.g. "Closed on Thanksgiving, Thursday Nov 26". It disappears automatically after the end date.</p>';
	echo '<p><label><input type="checkbox" name="notice_on" value="1"' . checked( ! empty( $n['on'] ), true, false ) . '> Show the notice</label></p>';
	echo '<p class="rb-field"><label for="rb-notice-text"><strong>Message</strong></label><input id="rb-notice-text" class="large-text" type="text" name="notice_text" maxlength="160" value="' . esc_attr( $n['text'] ) . '"><span class="rb-dim">Max 160 characters, plain text.</span></p>';
	echo '<p class="rb-field rb-inline"><label>From <input type="date" name="notice_from" value="' . esc_attr( $n['from'] ) . '"></label> <label>Until <input type="date" name="notice_to" value="' . esc_attr( $n['to'] ) . '"></label></p></div>';

	echo '</div><p><button class="button button-primary button-hero">Save changes</button></p></form></div>';
}

/**
 * Save with validation. Invalid input is never stored; the page explains what to fix.
 */
function rb_save_info() {
	if ( ! current_user_can( 'rb_manage_clinic' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_save_info' );
	$back = admin_url( 'admin.php?page=rb-clinic-info' );
	$fail = function ( $m ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'rb_err', rawurlencode( $m ), $back ) );
		exit;
	};
	$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field below.
	$i    = rb_info();
	$tel  = '/^[0-9 +().\-]{7,20}$/';
	foreach ( array( 'phone' => 'Main phone', 'urgent' => 'Urgent line' ) as $k => $label ) {
		$v = sanitize_text_field( $in[ $k ] ?? '' );
		if ( '' !== $v && ! preg_match( $tel, $v ) ) {
			$fail( $label . ': please use digits, spaces, + ( ) - only.' );
		}
		$i[ $k ] = $v;
	}
	$email = sanitize_email( $in['email'] ?? '' );
	if ( '' !== $email && ! is_email( $email ) ) {
		$fail( 'Email: please enter a valid email address.' );
	}
	$i['email']   = $email;
	$i['address'] = mb_substr( sanitize_text_field( $in['address'] ?? '' ), 0, 120 );
	$i['city']    = mb_substr( sanitize_text_field( $in['city'] ?? '' ), 0, 80 );
	$maps         = esc_url_raw( trim( (string) ( $in['maps'] ?? '' ) ), array( 'https' ) );
	if ( '' !== trim( (string) ( $in['maps'] ?? '' ) ) && '' === $maps ) {
		$fail( 'Google Maps link: please paste a link that starts with https://' );
	}
	$i['maps'] = $maps;

	$hm = '/^([01]\d|2[0-3]):[0-5]\d$/';
	foreach ( rb_info_days() as $n => $day ) {
		$row    = (array) ( $in['hours'][ $n ] ?? array() );
		$closed = ! empty( $row['closed'] );
		$open   = sanitize_text_field( $row['open'] ?? '' );
		$close  = sanitize_text_field( $row['close'] ?? '' );
		if ( ! $closed && ( ! preg_match( $hm, $open ) || ! preg_match( $hm, $close ) || $close <= $open ) ) {
			$fail( $day . ': please enter an opening and a later closing time, or tick "Closed".' );
		}
		$i['hours'][ $n ] = array( $closed ? 1 : 0, $closed ? '' : $open, $closed ? '' : $close );
	}

	$from = sanitize_text_field( $in['notice_from'] ?? '' );
	$to   = sanitize_text_field( $in['notice_to'] ?? '' );
	foreach ( array( $from, $to ) as $d ) {
		if ( '' !== $d && ! rb_parse_date( $d ) ) {
			$fail( 'Notice dates: please use the date picker.' );
		}
	}
	if ( $from && $to && $to < $from ) {
		$fail( 'Notice: the end date must be on or after the start date.' );
	}
	$text = mb_substr( sanitize_text_field( $in['notice_text'] ?? '' ), 0, 160 );
	if ( ! empty( $in['notice_on'] ) && '' === $text ) {
		$fail( 'Notice: please write a message, or untick "Show the notice".' );
	}
	$i['notice']  = array( 'on' => empty( $in['notice_on'] ) ? 0 : 1, 'text' => $text, 'from' => $from, 'to' => $to );
	$user         = wp_get_current_user();
	$i['updated'] = array( 'by' => $user->display_name, 'at' => current_time( 'mysql', true ) );
	update_option( 'rb_clinic_info', $i, false );
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Saved. The website now shows the new details.' ), $back ) );
	exit;
}
add_action( 'admin_post_rb_save_info', 'rb_save_info' );

/*
 * Doctors list: availability at a glance (services, weekly hours, bookable).
 */
add_filter(
	'manage_rb_doctor_posts_columns',
	function ( $cols ) {
		return array( 'cb' => $cols['cb'], 'title' => 'Doctor', 'rb_services' => 'Services', 'rb_hours' => 'Weekly hours', 'rb_active' => 'Online booking' );
	}
);
add_action(
	'manage_rb_doctor_posts_custom_column',
	function ( $col, $id ) {
		if ( 'rb_services' === $col ) {
			$all = rb_services( false );
			echo esc_html( implode( ', ', array_filter( array_map( fn( $s ) => $all[ $s ]->name ?? '', (array) get_post_meta( $id, 'rb_services', true ) ) ) ) );
		} elseif ( 'rb_hours' === $col ) {
			$hours = (array) get_post_meta( $id, 'rb_hours', true );
			$out   = array();
			foreach ( rb_weekdays() as $n => $day ) {
				$out[] = '<span class="rb-day-chip' . ( empty( $hours[ $n ] ) ? ' is-off' : '' ) . '" title="' . esc_attr( $day . ': ' . ( empty( $hours[ $n ] ) ? 'day off' : rb_ranges_text( $hours[ $n ] ) ) ) . '">' . esc_html( substr( $day, 0, 2 ) ) . '</span>';
			}
			echo implode( '', $out ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		} elseif ( 'rb_active' === $col ) {
			echo '0' === (string) get_post_meta( $id, 'rb_active', true ) ? '<span class="rb-pill rb-pill--canceled">Off</span>' : '<span class="rb-pill rb-pill--confirmed">On</span>';
		}
	},
	10,
	2
);
