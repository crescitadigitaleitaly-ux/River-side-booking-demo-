<?php
/**
 * Appointment records: create (pending), status changes, slot locks and the
 * audit log. Confirming and rescheduling run inside a database transaction;
 * the UNIQUE key on rb_slot_locks (doctor_id, block_start) makes it
 * impossible for two confirmed appointments to share a doctor's time.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Status labels.
 *
 * @return array
 */
function rb_statuses() {
	return array(
		'pending'   => 'Pending',
		'confirmed' => 'Confirmed',
		'declined'  => 'Declined',
		'canceled'  => 'Canceled',
		'completed' => 'Completed',
	);
}

/**
 * Non-clinical visit types (no medical details are collected).
 *
 * @return array
 */
function rb_visit_types() {
	return array(
		'checkup'  => 'Check-up / routine visit',
		'followup' => 'Follow-up visit',
		'new'      => 'New concern (details at the visit)',
		'cleaning' => 'Dental cleaning',
		'vaccine'  => 'Vaccination',
	);
}

/**
 * Load one appointment.
 *
 * @param int  $id         ID.
 * @param bool $for_update Lock the row (inside a transaction).
 * @return object|null
 */
function rb_get_appt( $id, $for_update = false ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'appointments' ) . ' WHERE id = %d' . ( $for_update ? ' FOR UPDATE' : '' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Visitor IP. Uses REMOTE_ADDR; a hosting CDN header can be trusted via the
 * rb_client_ip filter once verified on the server.
 *
 * @return string
 */
function rb_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'rb_client_ip', $ip );
}

/**
 * Simple fixed-window rate limit.
 *
 * @param string $bucket Key.
 * @param int    $limit  Max hits.
 * @param int    $window Seconds.
 * @return bool True if allowed.
 */
function rb_rate_ok( $bucket, $limit, $window ) {
	$key   = 'rb_rl_' . md5( $bucket );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Random, non-sequential reference like RFH-7K3Q-92.
 *
 * @return string
 */
function rb_new_reference() {
	global $wpdb;
	$alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$s = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$s .= $alpha[ random_int( 0, strlen( $alpha ) - 1 ) ];
		}
		$ref = 'RFH-' . $s . '-' . random_int( 10, 99 );
	} while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . ' WHERE reference = %s', $ref ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $ref;
}

/**
 * Audit log entry.
 *
 * @param int    $appt_id Appointment.
 * @param string $action  Action.
 * @param string $from    Old status.
 * @param string $to      New status.
 * @param string $actor   staff|patient|system.
 * @param string $details Text.
 */
function rb_log( $appt_id, $action, $from = '', $to = '', $actor = 'staff', $details = '' ) {
	global $wpdb;
	$wpdb->insert(
		rb_t( 'events' ),
		array(
			'appointment_id' => $appt_id,
			'action'         => $action,
			'from_status'    => $from,
			'to_status'      => $to,
			'user_id'        => 'staff' === $actor ? get_current_user_id() : 0,
			'actor'          => $actor,
			'details'        => $details,
			'created_at'     => current_time( 'mysql', true ),
		)
	);
}

/**
 * Validate input and create a pending request.
 *
 * @param array $in Raw input (unslashed).
 * @return array|WP_Error [ 'appt' => object, 'duplicate' => bool ]
 */
function rb_create_request( $in ) {
	global $wpdb;
	$err = fn( $msg, $field = '', $status = 422 ) => new WP_Error( 'rb_invalid', $msg, array( 'status' => $status, 'field' => $field ) );

	$name  = mb_substr( sanitize_text_field( $in['name'] ?? '' ), 0, 120 );
	$email = sanitize_email( $in['email'] ?? '' );
	$phone = sanitize_text_field( $in['phone'] ?? '' );
	$ptype = sanitize_key( $in['patient_type'] ?? '' );
	$vtype = sanitize_key( $in['visit_type'] ?? '' );
	$note  = mb_substr( sanitize_textarea_field( $in['note'] ?? '' ), 0, 300 );
	$idem  = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) ( $in['idem'] ?? '' ) );
	$idem  = strlen( $idem ) >= 16 ? substr( $idem, 0, 64 ) : null;

	if ( mb_strlen( $name ) < 2 ) {
		return $err( 'Please enter your full name.', 'name' );
	}
	if ( ! is_email( $email ) ) {
		return $err( 'Please enter a valid email address.', 'email' );
	}
	if ( ! preg_match( '/^[0-9 +().\-]{7,20}$/', $phone ) ) {
		return $err( 'Please enter a valid phone number.', 'phone' );
	}
	if ( ! in_array( $ptype, array( 'new', 'returning' ), true ) ) {
		return $err( 'Please choose new or returning patient.', 'patient_type' );
	}
	if ( ! isset( rb_visit_types()[ $vtype ] ) ) {
		return $err( 'Please choose the type of visit.', 'visit_type' );
	}
	if ( empty( $in['consent'] ) ) {
		return $err( 'Please accept the privacy note to continue.', 'consent' );
	}

	$service = rb_service_by_slug( sanitize_title( $in['service'] ?? '' ) );
	if ( ! $service ) {
		return $err( 'Please choose a service.', 'service' );
	}
	$doc_slug = sanitize_title( $in['doctor'] ?? 'any' );
	$doctor   = 'any' === $doc_slug || '' === $doc_slug ? null : rb_doctor_by_slug( $doc_slug );
	if ( 'any' !== $doc_slug && '' !== $doc_slug && ( ! $doctor || ! in_array( (int) $service->id, $doctor->services, true ) ) ) {
		return $err( 'The selected doctor does not offer ' . $service->name . '. Please choose one of the listed doctors.', 'doctor' );
	}
	$day = rb_parse_date( (string) ( $in['date'] ?? '' ) );
	$hm  = (string) ( $in['time'] ?? '' );
	if ( ! $day || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $hm ) ) {
		return $err( 'Please choose a date and time.', 'time' );
	}
	$start = $day->setTime( (int) substr( $hm, 0, 2 ), (int) substr( $hm, 3, 2 ) );

	// Same submission sent twice (double click, retry): return the first one.
	if ( $idem ) {
		$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . ' WHERE idem_key = %s', $idem ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $dup ) {
			return array( 'appt' => rb_get_appt( $dup ), 'duplicate' => true );
		}
	}
	$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . " WHERE patient_email = %s AND service_id = %d AND starts_at = %s AND status = 'pending' AND created_at > %s", $email, $service->id, rb_utc( $start ), gmdate( 'Y-m-d H:i:s', time() - 1800 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $dup ) {
		return array( 'appt' => rb_get_appt( $dup ), 'duplicate' => true );
	}

	$ip = rb_client_ip();
	if ( ! rb_rate_ok( 'ip_' . $ip, 5, 10 * MINUTE_IN_SECONDS ) || ! rb_rate_ok( 'em_' . strtolower( $email ), 3, HOUR_IN_SECONDS ) ) {
		return new WP_Error( 'rb_rate', 'Too many requests. Please wait a few minutes or call the clinic.', array( 'status' => 429 ) );
	}

	// Pick the doctor: the chosen one, or the first mapped doctor free at that time.
	if ( $doctor ) {
		$check = rb_check_slot( $service, $doctor, $start );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
	} else {
		$slots = rb_slots( $service, null, $day->format( 'Y-m-d' ) );
		if ( empty( $slots[ $hm ] ) ) {
			return new WP_Error( 'rb_unavailable', 'This time is no longer available. Please choose another time.', array( 'status' => 409 ) );
		}
		$doctor = rb_doctors()[ $slots[ $hm ]['doctors'][0] ];
	}

	$now = current_time( 'mysql', true );
	$row = array(
		'reference'     => rb_new_reference(),
		'status'        => 'pending',
		'service_id'    => $service->id,
		'doctor_id'     => $doctor->id,
		'doctor_pref'   => 'any' === $doc_slug || '' === $doc_slug ? 'any' : 'chosen',
		'starts_at'     => rb_utc( $start ),
		'ends_at'       => rb_utc( $start->modify( '+' . $service->duration . ' minutes' ) ),
		'patient_name'  => $name,
		'patient_email' => $email,
		'patient_phone' => $phone,
		'patient_type'  => $ptype,
		'visit_type'    => $vtype,
		'note'          => $note,
		'idem_key'      => $idem,
		'ip_hash'       => hash( 'sha256', wp_salt( 'nonce' ) . $ip ),
		'verified'      => ! empty( $in['human'] ) ? 1 : 0,
		'created_at'    => $now,
		'updated_at'    => $now,
	);
	if ( ! $wpdb->insert( rb_t( 'appointments' ), $row ) ) {
		// A parallel duplicate with the same key may have won the race.
		if ( $idem ) {
			$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . ' WHERE idem_key = %s', $idem ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $dup ) {
				return array( 'appt' => rb_get_appt( $dup ), 'duplicate' => true );
			}
		}
		error_log( '[riverside-booking] insert failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return new WP_Error( 'rb_server', 'Sorry, we could not save your request. Please try again or call the clinic.', array( 'status' => 500 ) );
	}
	$id = (int) $wpdb->insert_id;
	rb_log( $id, 'created', '', 'pending', 'patient', ( $row['verified'] ? '' : 'Browser check missing (possible spam). ' ) . ( 'any' === $row['doctor_pref'] ? 'No doctor preference; assigned ' . $doctor->name . '.' : '' ) );
	$appt = rb_get_appt( $id );
	rb_notify( $appt, 'receipt' );
	return array( 'appt' => $appt, 'duplicate' => false );
}

/**
 * Insert lock rows for an appointment. Must run inside a transaction.
 *
 * @param object $appt    Appointment (doctor_id, starts_at set).
 * @param object $service Service.
 * @return bool False if any block is already taken.
 */
function rb_lock_blocks( $appt, $service ) {
	global $wpdb;
	$start  = new DateTimeImmutable( $appt->starts_at, new DateTimeZone( 'UTC' ) );
	$blocks = rb_blocks( $service );
	$prev   = $wpdb->suppress_errors( true );
	$ok     = true;
	for ( $k = 0; $k < $blocks; $k++ ) {
		$r = $wpdb->insert(
			rb_t( 'slot_locks' ),
			array(
				'doctor_id'      => $appt->doctor_id,
				'block_start'    => $start->modify( '+' . ( $k * RB_BLOCK ) . ' minutes' )->format( 'Y-m-d H:i:s' ),
				'appointment_id' => $appt->id,
			)
		);
		if ( ! $r ) {
			$ok = false;
			break;
		}
	}
	$wpdb->suppress_errors( $prev );
	return $ok;
}

/**
 * Remove an appointment's locks (frees the time).
 *
 * @param int $appt_id Appointment.
 */
function rb_unlock( $appt_id ) {
	global $wpdb;
	$wpdb->delete( rb_t( 'slot_locks' ), array( 'appointment_id' => $appt_id ) );
}

/**
 * Change an appointment. Actions: confirm, decline, cancel, complete,
 * reschedule (args: date, time, optional doctor slug).
 *
 * @param int    $id     Appointment.
 * @param string $action Action.
 * @param array  $args   Arguments (note, date, time, doctor, reason).
 * @param string $actor  staff|patient.
 * @return object|WP_Error Updated appointment.
 */
function rb_transition( $id, $action, $args = array(), $actor = 'staff' ) {
	global $wpdb;
	$allowed = array(
		'confirm'    => array( 'pending' ),
		'decline'    => array( 'pending' ),
		'cancel'     => array( 'pending', 'confirmed' ),
		'complete'   => array( 'confirmed' ),
		'reschedule' => array( 'pending', 'confirmed' ),
	);
	if ( ! isset( $allowed[ $action ] ) ) {
		return new WP_Error( 'rb_action', 'Unknown action.', array( 'status' => 400 ) );
	}

	$wpdb->query( 'START TRANSACTION' );
	$appt = rb_get_appt( $id, true );
	if ( ! $appt ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'rb_missing', 'Appointment not found.', array( 'status' => 404 ) );
	}
	if ( ! in_array( $appt->status, $allowed[ $action ], true ) ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'rb_state', sprintf( 'This appointment is %s, so it cannot be changed that way.', strtolower( rb_statuses()[ $appt->status ] ?? $appt->status ) ), array( 'status' => 409 ) );
	}
	$service = rb_services( false )[ $appt->service_id ] ?? null;
	$doctor  = rb_doctors( false )[ $appt->doctor_id ] ?? null;
	if ( ! $service || ! $doctor ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'rb_config', 'The service or doctor for this appointment no longer exists.', array( 'status' => 409 ) );
	}
	$now    = current_time( 'mysql', true );
	$update = array( 'updated_at' => $now );
	$to     = $appt->status;
	$detail = '';

	switch ( $action ) {
		case 'confirm':
			// Re-check the rules at the moment of confirmation (hours, closures).
			$check = rb_check_slot( $service, $doctor, rb_local( $appt->starts_at ), array( 'exclude' => $appt->id, 'ignore_notice' => true ) );
			if ( is_wp_error( $check ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $check;
			}
			if ( ! rb_lock_blocks( $appt, $service ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'rb_taken', 'This time has just been confirmed for another patient with ' . rb_doctor_short( $doctor ) . '. Please reschedule this request.', array( 'status' => 409 ) );
			}
			$to                     = 'confirmed';
			$update['confirmed_at'] = $now;
			break;

		case 'decline':
			$to = 'declined';
			break;

		case 'cancel':
			rb_unlock( $appt->id );
			$to                    = 'canceled';
			$update['canceled_at'] = $now;
			break;

		case 'complete':
			if ( $appt->starts_at > $now ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'rb_future', 'An appointment can be marked completed only after its start time.', array( 'status' => 409 ) );
			}
			$to = 'completed';
			break;

		case 'reschedule':
			$new_doc = $doctor;
			if ( ! empty( $args['doctor'] ) && 'staff' === $actor ) {
				$new_doc = rb_doctor_by_slug( sanitize_title( $args['doctor'] ) );
				if ( ! $new_doc ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'rb_invalid', 'Unknown doctor.', array( 'status' => 422 ) );
				}
			}
			$day = rb_parse_date( (string) ( $args['date'] ?? '' ) );
			$hm  = (string) ( $args['time'] ?? '' );
			if ( ! $day || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $hm ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'rb_invalid', 'Please choose a new date and time.', array( 'status' => 422 ) );
			}
			$start = $day->setTime( (int) substr( $hm, 0, 2 ), (int) substr( $hm, 3, 2 ) );
			$check = rb_check_slot( $service, $new_doc, $start, array( 'exclude' => $appt->id ) );
			if ( is_wp_error( $check ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $check;
			}
			$old_when = rb_local( $appt->starts_at )->format( 'D M j, g:i a' ) . ' with ' . $doctor->name;
			rb_unlock( $appt->id );
			$appt->doctor_id     = $new_doc->id;
			$appt->starts_at     = rb_utc( $start );
			$update['doctor_id'] = $new_doc->id;
			$update['starts_at'] = $appt->starts_at;
			$update['ends_at']   = rb_utc( $start->modify( '+' . $service->duration . ' minutes' ) );
			$update['reminder_sent'] = 0;
			if ( 'confirmed' === $appt->status && 'staff' === $actor ) {
				if ( ! rb_lock_blocks( $appt, $service ) ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'rb_taken', 'That new time was just booked. Please choose another.', array( 'status' => 409 ) );
				}
			} else {
				// A patient change (or a pending request) goes back to reception.
				$to = 'pending';
			}
			$detail = 'From ' . $old_when . ' to ' . $start->format( 'D M j, g:i a' ) . ' with ' . $new_doc->name . '.';
			break;
	}

	$update['status'] = $to;
	if ( isset( $args['note'] ) && 'staff' === $actor ) {
		$note = sanitize_textarea_field( $args['note'] );
		if ( '' !== $note ) {
			$update['staff_note'] = trim( ( $appt->staff_note ? $appt->staff_note . "\n" : '' ) . gmdate( 'M j' ) . ': ' . mb_substr( $note, 0, 500 ) );
		}
	}
	if ( false === $wpdb->update( rb_t( 'appointments' ), $update, array( 'id' => $appt->id ) ) ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'rb_server', 'Could not save the change. Please try again.', array( 'status' => 500 ) );
	}
	$wpdb->query( 'COMMIT' );

	$reason = isset( $args['reason'] ) ? sanitize_text_field( $args['reason'] ) : '';
	rb_log( $appt->id, $action, $appt->status, $to, $actor, trim( $detail . ' ' . $reason ) );
	$fresh = rb_get_appt( $appt->id );
	$type  = array(
		'confirm'    => 'confirmation',
		'decline'    => 'decline',
		'cancel'     => 'cancellation',
		'reschedule' => 'reschedule',
	);
	if ( isset( $type[ $action ] ) ) {
		rb_notify( $fresh, $type[ $action ], array( 'reason' => $reason, 'actor' => $actor ) );
	}
	return $fresh;
}

/**
 * Other active requests for the same doctor that overlap this one.
 *
 * @param object $appt Appointment.
 * @return object[]
 */
function rb_overlaps( $appt ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT id, reference, status FROM ' . rb_t( 'appointments' ) . " WHERE id <> %d AND doctor_id = %d AND status IN ('pending','confirmed') AND starts_at < %s AND ends_at > %s", $appt->id, $appt->doctor_id, $appt->ends_at, $appt->starts_at ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Hourly job: reminders for confirmed visits in the next 24 hours, and
 * clean-up of demo rows older than 30 days.
 *
 * @return int Reminders created.
 */
function rb_run_reminders() {
	global $wpdb;
	$now  = gmdate( 'Y-m-d H:i:s' );
	$soon = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
	$ids  = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . " WHERE status = 'confirmed' AND reminder_sent = 0 AND starts_at > %s AND starts_at <= %s", $now, $soon ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	foreach ( $ids as $id ) {
		$wpdb->update( rb_t( 'appointments' ), array( 'reminder_sent' => 1 ), array( 'id' => $id ) );
		rb_notify( rb_get_appt( $id ), 'reminder' );
		rb_log( $id, 'reminder', '', '', 'system', 'Reminder created (test outbox).' );
	}
	$old = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	$del = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . rb_t( 'appointments' ) . ' WHERE starts_at < %s', $old ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	foreach ( $del as $id ) {
		foreach ( array( 'appointments' => 'id', 'events' => 'appointment_id', 'outbox' => 'appointment_id', 'tokens' => 'appointment_id', 'slot_locks' => 'appointment_id' ) as $t => $col ) {
			$wpdb->delete( rb_t( $t ), array( $col => $id ) );
		}
	}
	return count( $ids );
}
add_action( 'rb_hourly', 'rb_run_reminders' );
