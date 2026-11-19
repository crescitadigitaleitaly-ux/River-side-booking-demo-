<?php
/**
 * Demo data: fictional services, doctors, hours, closures and sample
 * requests. rb_seed_demo() wipes and re-creates everything (demo reset).
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Booking settings per service slug: [duration, buffer, sort].
 *
 * @return array
 */
function rb_seed_service_rules() {
	return array(
		'primary-care'  => array( 20, 0, 1 ),
		'pediatrics'    => array( 30, 0, 2 ),
		'womens-health' => array( 30, 0, 3 ),
		'dentistry'     => array( 45, 15, 4 ),
		'cardiology'    => array( 40, 5, 5 ),
		'diagnostics'   => array( 15, 0, 6 ),
		'imaging'       => array( 30, 0, 7 ),
		'vaccinations'  => array( 15, 0, 8 ),
	);
}

/**
 * Doctor → services and weekly hours (clinic time). 0 = Sunday.
 *
 * @return array
 */
function rb_seed_doctor_rules() {
	$wk = fn( $ranges, $days ) => array_fill_keys( $days, $ranges );
	return array(
		'carter'  => array( array( 'primary-care', 'diagnostics', 'imaging', 'vaccinations' ), $wk( array( array( '08:00', '12:00' ), array( '13:00', '17:00' ) ), array( 1, 2, 3, 4, 5 ) ) + array( 6 => array( array( '09:00', '14:00' ) ) ) ),
		'hayes'   => array( array( 'primary-care', 'diagnostics', 'imaging', 'vaccinations' ), $wk( array( array( '11:00', '14:00' ), array( '15:00', '19:00' ) ), array( 1, 2, 3, 4, 5 ) ) ),
		'raman'   => array( array( 'womens-health' ), $wk( array( array( '08:00', '12:00' ), array( '13:00', '16:00' ) ), array( 1, 2, 4 ) ) + array( 5 => array( array( '08:00', '12:00' ) ) ) ),
		'bennett' => array( array( 'pediatrics', 'vaccinations' ), $wk( array( array( '08:00', '12:00' ), array( '13:00', '16:00' ) ), array( 1, 2, 3, 4, 5 ) ) + array( 6 => array( array( '09:00', '14:00' ) ) ) ),
		'romano'  => array( array( 'dentistry' ), $wk( array( array( '09:00', '13:00' ), array( '14:00', '18:00' ) ), array( 1, 2, 4, 5 ) ) + array( 6 => array( array( '09:00', '13:00' ) ) ) ),
		'okafor'  => array( array( 'cardiology' ), $wk( array( array( '09:00', '13:00' ), array( '14:00', '17:00' ) ), array( 2, 3, 4 ) ) ),
	);
}

/**
 * Wipe and re-create all demo data.
 *
 * @return array Counts.
 */
function rb_seed_demo() {
	global $wpdb;
	$GLOBALS['rb_seeding'] = true;
	$svc_defaults          = function_exists( 'rh_services' ) ? rh_services() : array();
	$doc_defaults          = function_exists( 'rh_doctors' ) ? rh_doctors() : array();
	$GLOBALS['rb_seeding'] = false;

	delete_option( 'rb_clinic_info' ); // Clinic details, hours and notice back to the defaults.
	foreach ( get_posts( array( 'post_type' => array( 'rb_service', 'rb_doctor' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
		wp_delete_post( $pid, true );
	}
	foreach ( array( 'appointments', 'slot_locks', 'closures', 'events', 'outbox', 'tokens' ) as $t ) {
		$wpdb->query( 'TRUNCATE TABLE ' . rb_t( $t ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$svc_ids = array();
	foreach ( rb_seed_service_rules() as $slug => $r ) {
		$d  = $svc_defaults[ $slug ] ?? array( ucwords( str_replace( '-', ' ', $slug ) ), '', '', '', array(), '' );
		$id = wp_insert_post( array( 'post_type' => 'rb_service', 'post_status' => 'publish', 'post_title' => $d[0], 'post_name' => $slug, 'menu_order' => $r[2] ) );
		update_post_meta( $id, 'rb_duration', $r[0] );
		update_post_meta( $id, 'rb_buffer', $r[1] );
		update_post_meta( $id, 'rb_active', '1' );
		update_post_meta( $id, 'rb_icon', $d[1] );
		update_post_meta( $id, 'rb_short', $d[2] );
		update_post_meta( $id, 'rb_long', $d[3] );
		update_post_meta( $id, 'rb_bullets', $d[4] );
		update_post_meta( $id, 'rb_image', (string) $d[5] );
		$svc_ids[ $slug ] = $id;
	}
	$order = 0;
	foreach ( rb_seed_doctor_rules() as $slug => $r ) {
		$d  = $doc_defaults[ $slug ] ?? array( 'Dr. ' . ucfirst( $slug ), 'MD', '', '', '', '', '' );
		$id = wp_insert_post( array( 'post_type' => 'rb_doctor', 'post_status' => 'publish', 'post_title' => $d[0], 'post_name' => $slug, 'menu_order' => ++$order ) );
		update_post_meta( $id, 'rb_creds', $d[1] );
		update_post_meta( $id, 'rb_specialty', $d[2] );
		update_post_meta( $id, 'rb_bio', $d[4] );
		update_post_meta( $id, 'rb_education', $d[5] );
		update_post_meta( $id, 'rb_languages', $d[6] );
		update_post_meta( $id, 'rb_active', '1' );
		update_post_meta( $id, 'rb_services', array_values( array_map( fn( $s ) => $svc_ids[ $s ], $r[0] ) ) );
		$hours = array();
		foreach ( range( 0, 6 ) as $w ) {
			$hours[ $w ] = $r[1][ $w ] ?? array();
		}
		update_post_meta( $id, 'rb_hours', $hours );
	}
	rb_flush_cache();

	// Closures relative to today so the demo always looks current.
	$now   = rb_now();
	$today = $now->setTime( 0, 0 );
	$add   = function ( $doctor_slug, $start, $end, $reason ) use ( $wpdb ) {
		$doc = $doctor_slug ? rb_doctor_by_slug( $doctor_slug ) : null;
		$wpdb->insert( rb_t( 'closures' ), array( 'doctor_id' => $doc ? $doc->id : 0, 'starts_at' => rb_utc( $start ), 'ends_at' => rb_utc( $end ), 'reason' => $reason, 'created_at' => current_time( 'mysql', true ) ) );
	};
	$training = $today->modify( '+9 days' );
	while ( in_array( (int) $training->format( 'w' ), array( 0, 6 ), true ) ) {
		$training = $training->modify( '+1 day' );
	}
	$add( '', $training, $training->modify( '+1 day' ), 'Staff training day (clinic closed)' );
	$year = (int) $today->format( 'Y' );
	foreach ( array( array( 'fourth thursday of november ' . $year, 'Thanksgiving (clinic closed)' ), array( $year . '-12-25', 'Christmas Day (clinic closed)' ), array( ( $year + 1 ) . '-01-01', 'New Year\'s Day (clinic closed)' ) ) as $h ) {
		$day = new DateTimeImmutable( $h[0], wp_timezone() );
		$day = $day->setTime( 0, 0 );
		if ( $day >= $today && $day <= $today->modify( '+70 days' ) ) {
			$add( '', $day, $day->modify( '+1 day' ), $h[1] );
		}
	}
	$conf = $today->modify( '+3 days' );
	while ( 4 !== (int) $conf->format( 'w' ) ) {
		$conf = $conf->modify( '+1 day' );
	}
	$add( 'okafor', $conf->setTime( 13, 0 ), $conf->setTime( 17, 0 ), 'Cardiology conference' );

	// Sample patients (fictional; reserved .example emails and 555-01xx phones).
	$people = array(
		array( 'Olivia Martin', 'new' ), array( 'James Rivera', 'returning' ), array( 'Sophia Nguyen', 'returning' ), array( 'Liam Patel', 'new' ),
		array( 'Emma Johnson', 'returning' ), array( 'Noah Williams', 'new' ), array( 'Ava Thompson', 'returning' ), array( 'Lucas Garcia', 'new' ),
		array( 'Mia Robinson', 'returning' ), array( 'Ethan Clark', 'new' ), array( 'Isabella Lewis', 'returning' ), array( 'Mason Walker', 'new' ),
		array( 'Chloe Young', 'returning' ),
	);
	$n      = 0;
	$create = function ( $svc_slug, $doc_slug, $start, $status, $visit, $extra = array() ) use ( &$n, $people, $wpdb ) {
		$p       = $people[ $n % count( $people ) ];
		$service = rb_service_by_slug( $svc_slug );
		$doctor  = rb_doctor_by_slug( $doc_slug );
		++$n;
		$created = $extra['created'] ?? gmdate( 'Y-m-d H:i:s', time() - ( 30 - $n ) * HOUR_IN_SECONDS );
		$wpdb->insert(
			rb_t( 'appointments' ),
			array(
				'reference'     => rb_new_reference(),
				'status'        => 'pending',
				'service_id'    => $service->id,
				'doctor_id'     => $doctor->id,
				'doctor_pref'   => $extra['pref'] ?? 'chosen',
				'starts_at'     => rb_utc( $start ),
				'ends_at'       => rb_utc( $start->modify( '+' . $service->duration . ' minutes' ) ),
				'patient_name'  => $p[0],
				'patient_email' => strtolower( str_replace( ' ', '.', $p[0] ) ) . '@patient.example',
				'patient_phone' => sprintf( '(555) 01%d-%04d', $n % 10, 1000 + $n * 37 ),
				'patient_type'  => $p[1],
				'visit_type'    => $visit,
				'note'          => $extra['note'] ?? '',
				'verified'      => isset( $extra['unverified'] ) ? 0 : 1,
				'created_at'    => $created,
				'updated_at'    => $created,
			)
		);
		$id = (int) $wpdb->insert_id;
		rb_log( $id, 'created', '', 'pending', 'patient', 'Sample request (demo data).' );
		rb_notify( rb_get_appt( $id ), 'receipt' );
		if ( 'pending' !== $status ) {
			rb_transition( $id, array( 'confirmed' => 'confirm', 'declined' => 'decline', 'canceled' => 'cancel' )[ $status ] ?? 'confirm', array( 'reason' => $extra['reason'] ?? '' ), 'staff' );
		}
		return $id;
	};
	// First free slot on or after a day offset.
	$slot = function ( $svc_slug, $doc_slug, $offset, $pick = 0 ) use ( $today ) {
		$service = rb_service_by_slug( $svc_slug );
		$doctor  = rb_doctor_by_slug( $doc_slug );
		for ( $i = $offset; $i < $offset + 20; $i++ ) {
			$s = array_values( rb_slots( $service, $doctor, $today->modify( '+' . $i . ' days' )->format( 'Y-m-d' ) ) );
			if ( $s ) {
				return $s[ min( $pick, count( $s ) - 1 ) ]['start'];
			}
		}
		return null;
	};

	$plan = array(
		array( 'dentistry', 'romano', 1, 2, 'confirmed', 'cleaning' ),
		array( 'primary-care', 'carter', 1, 0, 'confirmed', 'checkup' ),
		array( 'pediatrics', 'bennett', 2, 3, 'pending', 'vaccine' ),
		array( 'cardiology', 'okafor', 2, 1, 'pending', 'followup' ),
		array( 'womens-health', 'raman', 3, 2, 'confirmed', 'checkup' ),
		array( 'primary-care', 'hayes', 3, 4, 'pending', 'new', array( 'pref' => 'any' ) ),
		array( 'diagnostics', 'carter', 4, 1, 'declined', 'checkup', array( 'reason' => 'Lab closed for maintenance that morning, please pick another day.' ) ),
		array( 'imaging', 'hayes', 5, 2, 'canceled', 'followup', array( 'reason' => 'Patient called to cancel.' ) ),
		array( 'vaccinations', 'bennett', 6, 0, 'pending', 'vaccine', array( 'unverified' => 1 ) ),
	);
	foreach ( $plan as $p ) {
		$start = $slot( $p[0], $p[1], $p[2], $p[3] );
		if ( $start ) {
			$create( $p[0], $p[1], $start, $p[4], $p[5], $p[6] ?? array() );
		}
	}
	// Conflict demo: two pending requests for the same dentist time.
	$start = $slot( 'dentistry', 'romano', 2, 4 );
	if ( $start ) {
		$create( 'dentistry', 'romano', $start, 'pending', 'checkup', array( 'note' => 'Prefers morning visits.' ) );
		$create( 'dentistry', 'romano', $start, 'pending', 'cleaning' );
	}
	// Past visits: one to mark completed, one already completed.
	foreach ( array( array( -1, 'confirmed' ), array( -2, 'completed' ) ) as $past ) {
		$day = $today->modify( $past[0] . ' days' );
		while ( 0 === (int) $day->format( 'w' ) ) {
			$day = $day->modify( '-1 day' );
		}
		$start   = $day->setTime( 10, 0 );
		$service = rb_service_by_slug( 'primary-care' );
		$doctor  = rb_doctor_by_slug( 'carter' );
		$p       = $people[ $n % count( $people ) ];
		++$n;
		$wpdb->insert(
			rb_t( 'appointments' ),
			array(
				'reference'     => rb_new_reference(),
				'status'        => $past[1],
				'service_id'    => $service->id,
				'doctor_id'     => $doctor->id,
				'starts_at'     => rb_utc( $start ),
				'ends_at'       => rb_utc( $start->modify( '+20 minutes' ) ),
				'patient_name'  => $p[0],
				'patient_email' => strtolower( str_replace( ' ', '.', $p[0] ) ) . '@patient.example',
				'patient_phone' => sprintf( '(555) 01%d-%04d', $n % 10, 1000 + $n * 37 ),
				'patient_type'  => $p[1],
				'visit_type'    => 'checkup',
				'created_at'    => rb_utc( $start->modify( '-3 days' ) ),
				'updated_at'    => rb_utc( $start ),
				'confirmed_at'  => rb_utc( $start->modify( '-2 days' ) ),
				'reminder_sent' => 1,
			)
		);
		$id = (int) $wpdb->insert_id;
		rb_log( $id, 'created', '', 'pending', 'patient', 'Sample request (demo data).' );
		rb_log( $id, 'confirm', 'pending', 'confirmed', 'system', 'Sample data.' );
		if ( 'completed' === $past[1] ) {
			rb_log( $id, 'complete', 'confirmed', 'completed', 'system', 'Sample data.' );
		}
	}

	return array(
		'services'     => count( $svc_ids ),
		'doctors'      => count( rb_seed_doctor_rules() ),
		'appointments' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . rb_t( 'appointments' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);
}
