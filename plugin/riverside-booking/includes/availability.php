<?php
/**
 * Availability engine: free start times from doctor hours, service length,
 * buffer, closures, minimum notice and confirmed bookings (slot locks).
 * Everything is calculated in clinic time and stored in UTC.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current time in clinic timezone.
 *
 * @return DateTimeImmutable
 */
function rb_now() {
	return new DateTimeImmutable( 'now', wp_timezone() );
}

/**
 * Local DateTime → UTC MySQL string.
 *
 * @param DateTimeInterface $dt Time.
 * @return string
 */
function rb_utc( $dt ) {
	return DateTimeImmutable::createFromInterface( $dt )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
}

/**
 * UTC MySQL string → clinic-time DateTimeImmutable.
 *
 * @param string $utc UTC datetime.
 * @return DateTimeImmutable
 */
function rb_local( $utc ) {
	return ( new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
}

/**
 * Parse a clinic-time date (Y-m-d) strictly.
 *
 * @param string $date Date.
 * @return DateTimeImmutable|null
 */
function rb_parse_date( $date ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, wp_timezone() );
	return ( $d && $d->format( 'Y-m-d' ) === $date ) ? $d : null;
}

/**
 * Number of 15-minute blocks a service occupies (length + buffer).
 *
 * @param object $service Service.
 * @return int
 */
function rb_blocks( $service ) {
	return (int) ceil( ( $service->duration + $service->buffer ) / RB_BLOCK );
}

/**
 * Closures overlapping a range for a doctor (doctor-specific or whole clinic).
 *
 * @param int    $doctor_id Doctor (0 = clinic-wide only).
 * @param string $from      UTC.
 * @param string $to        UTC.
 * @return array
 */
function rb_closures_between( $doctor_id, $from, $to ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'closures' ) . ' WHERE doctor_id IN (0, %d) AND starts_at < %s AND ends_at > %s ORDER BY doctor_id, starts_at', $doctor_id, $to, $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Locked 15-minute blocks for a doctor in a range, as a set of UTC strings.
 *
 * @param int    $doctor_id Doctor.
 * @param string $from      UTC.
 * @param string $to        UTC.
 * @param int    $exclude   Appointment whose own locks are ignored.
 * @return array
 */
function rb_locked_blocks( $doctor_id, $from, $to, $exclude = 0 ) {
	global $wpdb;
	$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT block_start FROM ' . rb_t( 'slot_locks' ) . ' WHERE doctor_id = %d AND block_start >= %s AND block_start < %s AND appointment_id <> %d', $doctor_id, $from, $to, $exclude ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return array_fill_keys( $rows, true );
}

/**
 * Free start times for one doctor on one clinic-time day.
 *
 * @param object            $service Service.
 * @param object            $doctor  Doctor.
 * @param DateTimeImmutable $day     Midnight, clinic time.
 * @param array             $opts    exclude (appointment id), ignore_locks, ignore_notice.
 * @return array "H:i" => DateTimeImmutable start (clinic time)
 */
function rb_doctor_day_slots( $service, $doctor, $day, $opts = array() ) {
	$opts = wp_parse_args( $opts, array( 'exclude' => 0, 'ignore_locks' => false, 'ignore_notice' => false ) );
	if ( ! in_array( (int) $service->id, $doctor->services, true ) ) {
		return array();
	}
	$set      = rb_settings();
	$now      = rb_now();
	$earliest = $opts['ignore_notice'] ? $now : $now->modify( '+' . (int) $set['min_notice'] . ' minutes' );
	$latest   = $now->setTime( 0, 0 )->modify( '+' . ( (int) $set['max_days'] + 1 ) . ' days' );
	if ( $day >= $latest ) {
		return array();
	}
	$ranges = $doctor->hours[ (int) $day->format( 'w' ) ] ?? array();
	if ( ! $ranges ) {
		return array();
	}
	$from     = rb_utc( $day );
	$to       = rb_utc( $day->modify( '+1 day' ) );
	$closures = rb_closures_between( $doctor->id, $from, $to );
	$locks    = $opts['ignore_locks'] ? array() : rb_locked_blocks( $doctor->id, $from, $to, (int) $opts['exclude'] );
	$blocks   = rb_blocks( $service );
	$out      = array();

	foreach ( $ranges as $r ) {
		list( $sh, $sm ) = array_map( 'intval', explode( ':', $r[0] ) );
		list( $eh, $em ) = array_map( 'intval', explode( ':', $r[1] ) );
		$start           = $day->setTime( $sh, $sm );
		$end             = $day->setTime( $eh, $em );
		for ( $s = $start; $s->modify( '+' . ( $blocks * RB_BLOCK ) . ' minutes' ) <= $end; $s = $s->modify( '+' . RB_BLOCK . ' minutes' ) ) {
			if ( $s < $earliest ) {
				continue;
			}
			$occ_end = $s->modify( '+' . ( $blocks * RB_BLOCK ) . ' minutes' );
			$s_utc   = rb_utc( $s );
			$e_utc   = rb_utc( $occ_end );
			$closed  = false;
			foreach ( $closures as $c ) {
				if ( $c->starts_at < $e_utc && $c->ends_at > $s_utc ) {
					$closed = true;
					break;
				}
			}
			if ( $closed ) {
				continue;
			}
			$taken = false;
			for ( $k = 0; $k < $blocks; $k++ ) {
				if ( isset( $locks[ rb_utc( $s->modify( '+' . ( $k * RB_BLOCK ) . ' minutes' ) ) ] ) ) {
					$taken = true;
					break;
				}
			}
			if ( ! $taken ) {
				$out[ $s->format( 'H:i' ) ] = $s;
			}
		}
	}
	return $out;
}

/**
 * Free times for a service on a day, for one doctor or any mapped doctor.
 *
 * @param object      $service Service.
 * @param object|null $doctor  Doctor or null for "No preference".
 * @param string      $date    Y-m-d clinic time.
 * @param array       $opts    See rb_doctor_day_slots().
 * @return array "H:i" => [ 'start' => DateTimeImmutable, 'doctors' => int[] ]
 */
function rb_slots( $service, $doctor, $date, $opts = array() ) {
	$day = rb_parse_date( $date );
	if ( ! $day ) {
		return array();
	}
	$docs = $doctor ? array( $doctor ) : rb_doctors_for_service( $service->id );
	$out  = array();
	foreach ( $docs as $d ) {
		foreach ( rb_doctor_day_slots( $service, $d, $day, $opts ) as $hm => $start ) {
			if ( ! isset( $out[ $hm ] ) ) {
				$out[ $hm ] = array( 'start' => $start, 'doctors' => array() );
			}
			$out[ $hm ]['doctors'][] = (int) $d->id;
		}
	}
	ksort( $out );
	return $out;
}

/**
 * Overview of days: how many free times and, if none, why.
 *
 * @param object      $service Service.
 * @param object|null $doctor  Doctor or null.
 * @param string      $from    Y-m-d.
 * @param int         $count   Days.
 * @return array
 */
function rb_days( $service, $doctor, $from, $count = 14 ) {
	$day = rb_parse_date( $from );
	if ( ! $day ) {
		return array();
	}
	$today = rb_now()->setTime( 0, 0 );
	$set   = rb_settings();
	$last  = $today->modify( '+' . (int) $set['max_days'] . ' days' );
	$docs  = $doctor ? array( $doctor ) : rb_doctors_for_service( $service->id );
	$out   = array();
	for ( $i = 0; $i < $count; $i++, $day = $day->modify( '+1 day' ) ) {
		$row = array(
			'date'   => $day->format( 'Y-m-d' ),
			'dow'    => $day->format( 'D' ),
			'label'  => $day->format( 'M j' ),
			'free'   => 0,
			'reason' => '',
		);
		if ( $day < $today ) {
			$row['reason'] = 'Past';
		} elseif ( $day > $last ) {
			$row['reason'] = 'Not open for booking yet';
		} else {
			$works = false;
			foreach ( $docs as $d ) {
				if ( ! empty( $d->hours[ (int) $day->format( 'w' ) ] ) ) {
					$works = true;
				}
			}
			if ( ! $works ) {
				$row['reason'] = $doctor ? 'Not working' : 'Closed';
			} else {
				$row['free'] = count( rb_slots( $service, $doctor, $row['date'] ) );
				if ( ! $row['free'] ) {
					$clinic = rb_closures_between( $doctor ? $doctor->id : 0, rb_utc( $day ), rb_utc( $day->modify( '+1 day' ) ) );
					$row['reason'] = $clinic ? $clinic[0]->reason : 'Fully booked';
				}
			}
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * Check one exact start time against all rules.
 *
 * @param object            $service Service.
 * @param object            $doctor  Doctor.
 * @param DateTimeImmutable $start   Clinic-time start.
 * @param array             $opts    See rb_doctor_day_slots().
 * @return true|WP_Error
 */
function rb_check_slot( $service, $doctor, $start, $opts = array() ) {
	if ( ! in_array( (int) $service->id, $doctor->services, true ) ) {
		return new WP_Error( 'rb_mapping', 'This doctor does not offer the selected service.', array( 'status' => 422 ) );
	}
	$day = $start->setTime( 0, 0 );
	if ( isset( rb_doctor_day_slots( $service, $doctor, $day, $opts )[ $start->format( 'H:i' ) ] ) ) {
		return true;
	}
	// Distinguish "taken" (only confirmed bookings block it) from "not allowed".
	if ( empty( $opts['ignore_locks'] ) && isset( rb_doctor_day_slots( $service, $doctor, $day, array( 'ignore_locks' => true ) + $opts )[ $start->format( 'H:i' ) ] ) ) {
		return new WP_Error( 'rb_taken', 'Sorry, this time has just been booked. Please choose another time.', array( 'status' => 409 ) );
	}
	return new WP_Error( 'rb_unavailable', 'This time is outside the doctor\'s working hours, on a closed day, or too soon to book.', array( 'status' => 422 ) );
}
