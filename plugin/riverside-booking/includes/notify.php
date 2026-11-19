<?php
/**
 * Test-only notifications and secure patient links.
 *
 * Every message is written to the Test outbox (rb_outbox) and shown in
 * wp-admin. Nothing is ever sent to the address a visitor typed. A copy can
 * go to approved test inboxes only, when that is switched on in Settings.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notification types.
 *
 * @return array type => label
 */
function rb_notify_types() {
	return array(
		'receipt'      => 'Request received',
		'confirmation' => 'Confirmation',
		'reschedule'   => 'Reschedule',
		'cancellation' => 'Cancellation',
		'decline'      => 'Request declined',
		'reminder'     => 'Reminder',
	);
}

/**
 * Create a manage link token. Older unused tokens for the same appointment
 * stop working. Expires after 7 days or 2 hours before the visit.
 *
 * @param object $appt Appointment.
 * @return string Raw token (only ever put in the message, never stored).
 */
function rb_issue_token( $appt ) {
	global $wpdb;
	$now = gmdate( 'Y-m-d H:i:s' );
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . rb_t( 'tokens' ) . ' SET used_at = %s WHERE appointment_id = %d AND used_at IS NULL', $now, $appt->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$raw     = bin2hex( random_bytes( 32 ) );
	$expires = min( time() + 7 * DAY_IN_SECONDS, strtotime( $appt->starts_at . ' UTC' ) - 2 * HOUR_IN_SECONDS );
	$wpdb->insert(
		rb_t( 'tokens' ),
		array(
			'appointment_id' => $appt->id,
			'token_hash'     => hash( 'sha256', $raw ),
			'expires_at'     => gmdate( 'Y-m-d H:i:s', max( $expires, time() ) ),
			'created_at'     => $now,
		)
	);
	return $raw;
}

/**
 * Look up a token.
 *
 * @param string $raw Raw token from the link.
 * @return object|WP_Error Appointment (with ->token_id).
 */
function rb_token_appt( $raw ) {
	global $wpdb;
	$raw = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $raw ) );
	if ( 64 !== strlen( $raw ) ) {
		return new WP_Error( 'rb_token', 'This link is not valid.', array( 'status' => 404 ) );
	}
	$tok = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'tokens' ) . ' WHERE token_hash = %s', hash( 'sha256', $raw ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $tok ) {
		return new WP_Error( 'rb_token', 'This link is not valid.', array( 'status' => 404 ) );
	}
	if ( $tok->used_at || $tok->expires_at <= gmdate( 'Y-m-d H:i:s' ) ) {
		return new WP_Error( 'rb_token', 'This link has expired or was already used. Please call the clinic to make changes.', array( 'status' => 410 ) );
	}
	$appt = rb_get_appt( $tok->appointment_id );
	if ( ! $appt || ! in_array( $appt->status, array( 'pending', 'confirmed' ), true ) ) {
		return new WP_Error( 'rb_token', 'This appointment can no longer be changed online.', array( 'status' => 410 ) );
	}
	$appt->token_id = (int) $tok->id;
	return $appt;
}

/**
 * Mark a token used.
 *
 * @param int $token_id Token row.
 */
function rb_use_token( $token_id ) {
	global $wpdb;
	$wpdb->update( rb_t( 'tokens' ), array( 'used_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $token_id ) );
}

/**
 * Patient manage-page URL.
 *
 * @param string $raw Token.
 * @return string
 */
function rb_manage_url( $raw ) {
	$page = (int) get_option( 'rb_manage_page' );
	$base = $page ? get_permalink( $page ) : home_url( '/manage-appointment/' );
	return add_query_arg( 't', $raw, $base );
}

/**
 * Human date/time in clinic time.
 *
 * @param string $utc UTC datetime.
 * @return string
 */
function rb_when( $utc ) {
	$d = rb_local( $utc );
	return $d->format( 'l, F j, Y' ) . ' at ' . $d->format( 'g:i a' ) . ' (' . $d->format( 'T' ) . ')';
}

/**
 * Build and store a test notification.
 *
 * @param object $appt Appointment.
 * @param string $type Type.
 * @param array  $ctx  reason, actor.
 * @return int Outbox row ID.
 */
function rb_notify( $appt, $type, $ctx = array() ) {
	global $wpdb;
	if ( ! $appt || ! isset( rb_notify_types()[ $type ] ) ) {
		return 0;
	}
	$service = rb_services( false )[ $appt->service_id ] ?? null;
	$doctor  = rb_doctors( false )[ $appt->doctor_id ] ?? null;
	$clinic  = function_exists( 'rh_clinic' ) ? rh_clinic() : array( 'name' => 'Riverside Family Health', 'phone' => '(555) 014-2200', 'address' => '', 'city' => '' );
	$first   = explode( ' ', $appt->patient_name )[0];
	$link    = in_array( $type, array( 'receipt', 'confirmation', 'reschedule', 'reminder' ), true ) ? rb_manage_url( rb_issue_token( $appt ) ) : '';
	$reason  = trim( (string) ( $ctx['reason'] ?? '' ) );

	$copy = array(
		'receipt'      => array( 'We received your appointment request', 'Thank you. Your request is <strong>pending</strong>: it is not confirmed yet. Our reception team will check the time with the doctor and confirm, usually within one working day.' ),
		'confirmation' => array( 'Your appointment is confirmed', 'Good news: your appointment is <strong>confirmed</strong>. Please arrive 10 minutes early and bring a photo ID and your insurance card.' ),
		'reschedule'   => array(
			'Your appointment time has changed',
			'patient' === ( $ctx['actor'] ?? '' ) || 'pending' === $appt->status
				? 'We received your new preferred time. It is <strong>pending</strong> until our reception team confirms it.'
				: 'Our reception team has moved your appointment. The new time below is <strong>confirmed</strong>.',
		),
		'cancellation' => array( 'Your appointment has been canceled', 'Your appointment has been <strong>canceled</strong> and the time has been released. If this was a mistake, please book again or call us.' ),
		'decline'      => array( 'We could not confirm your request', 'Sorry, we could not confirm this time. Please choose another time online or call us and we will help.' ),
		'reminder'     => array( 'Reminder: your appointment is tomorrow', 'This is a friendly reminder of your <strong>confirmed</strong> appointment.' ),
	);
	$subject = '[TEST – Riverside demo] ' . $copy[ $type ][0] . ' (' . $appt->reference . ')';
	$rows    = array(
		'Reference' => $appt->reference,
		'Status'    => rb_statuses()[ $appt->status ] ?? $appt->status,
		'Service'   => $service ? $service->name : '—',
		'Doctor'    => $doctor ? $doctor->name : '—',
		'When'      => rb_when( $appt->starts_at ),
		'Where'     => trim( $clinic['address'] . ', ' . $clinic['city'], ', ' ),
	);
	$table = '';
	foreach ( $rows as $k => $v ) {
		$table .= '<tr><td style="padding:6px 12px 6px 0;color:#5b6b75;white-space:nowrap">' . esc_html( $k ) . '</td><td style="padding:6px 0;color:#0f2530;font-weight:600">' . esc_html( $v ) . '</td></tr>';
	}
	$body  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#24343d;font-size:15px;line-height:1.55">';
	$body .= '<div style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:700;margin-bottom:16px">TEST MESSAGE from a demonstration website. Riverside Family Health is a fictional clinic. Not sent to a real patient.</div>';
	$body .= '<div style="background:#0f766e;color:#fff;border-radius:10px 10px 0 0;padding:16px 20px;font-size:18px;font-weight:700">' . esc_html( $clinic['name'] ) . '</div>';
	$body .= '<div style="border:1px solid #d9e3e8;border-top:0;border-radius:0 0 10px 10px;padding:20px">';
	$body .= '<p style="margin:0 0 12px">Hello ' . esc_html( $first ) . ',</p><p style="margin:0 0 16px">' . wp_kses( $copy[ $type ][1], array( 'strong' => array() ) ) . '</p>';
	if ( $reason ) {
		$body .= '<p style="margin:0 0 16px;padding:10px 12px;background:#f3f7f9;border-radius:8px">Message from reception: ' . esc_html( $reason ) . '</p>';
	}
	$body .= '<table style="border-collapse:collapse;margin:0 0 18px">' . $table . '</table>';
	if ( $link ) {
		$body .= '<p style="margin:0 0 8px"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700">Cancel or change appointment</a></p><p style="margin:0 0 16px;font-size:13px;color:#5b6b75">This secure link works for 7 days (until 2 hours before the visit) and only once.</p>';
	}
	$body .= '<p style="margin:0;font-size:13px;color:#5b6b75">Questions? Call ' . esc_html( $clinic['phone'] ) . '. For emergencies call 911.</p></div></div>';

	$set     = rb_settings();
	$targets = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', (string) $set['allowlist'] ) ), 'is_email' );
	$sent    = false;
	$note    = 'Test outbox only';
	if ( $set['send_real'] && $targets ) {
		$sent = wp_mail( $targets, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		$note = $sent ? 'Copy sent to approved test inbox' : 'Test inbox send failed (kept in outbox)';
	}
	$wpdb->insert(
		rb_t( 'outbox' ),
		array(
			'appointment_id' => $appt->id,
			'type'           => $type,
			'recipient'      => $appt->patient_name . ' <' . $appt->patient_email . '> (fictional, not sent)',
			'subject'        => $subject,
			'body'           => $body,
			'delivered'      => $sent ? 1 : 0,
			'delivery_note'  => $note,
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	return (int) $wpdb->insert_id;
}
