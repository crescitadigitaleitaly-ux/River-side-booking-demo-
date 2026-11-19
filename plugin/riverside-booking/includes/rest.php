<?php
/**
 * REST API (namespace rb/v1). Public routes never return other patients'
 * data. Staff routes need a logged-in user with the right capability and the
 * standard WordPress REST nonce. All responses are marked no-store so the
 * page cache never keeps availability or booking data.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Error helper.
 *
 * @param WP_Error $e Error.
 * @return WP_REST_Response
 */
function rb_rest_error( $e ) {
	$data = $e->get_error_data();
	return new WP_REST_Response(
		array(
			'ok'      => false,
			'code'    => $e->get_error_code(),
			'message' => $e->get_error_message(),
			'field'   => is_array( $data ) ? ( $data['field'] ?? '' ) : '',
		),
		is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400
	);
}

/**
 * Resolve service/doctor query params.
 *
 * @param WP_REST_Request $r Request.
 * @return array|WP_Error [service, doctor|null]
 */
function rb_rest_svc_doc( $r ) {
	$service = rb_service_by_slug( sanitize_title( (string) $r->get_param( 'service' ) ) );
	if ( ! $service ) {
		return new WP_Error( 'rb_invalid', 'Unknown service.', array( 'status' => 422 ) );
	}
	$ds = sanitize_title( (string) $r->get_param( 'doctor' ) );
	if ( '' === $ds || 'any' === $ds ) {
		return array( $service, null );
	}
	$doctor = rb_doctor_by_slug( $ds );
	if ( ! $doctor || ! in_array( (int) $service->id, $doctor->services, true ) ) {
		return new WP_Error( 'rb_mapping', 'This doctor does not offer the selected service.', array( 'status' => 422 ) );
	}
	return array( $service, $doctor );
}

/**
 * Slots as a JSON-friendly list.
 *
 * @param array $slots From rb_slots().
 * @return array
 */
function rb_slots_json( $slots ) {
	$out = array();
	foreach ( $slots as $hm => $s ) {
		$out[] = array(
			'time'  => $hm,
			'label' => $s['start']->format( 'g:i a' ),
			'part'  => (int) $s['start']->format( 'G' ) < 12 ? 'Morning' : ( (int) $s['start']->format( 'G' ) < 16 ? 'Afternoon' : 'Evening' ),
		);
	}
	return $out;
}

/**
 * Public appointment summary (no contact details).
 *
 * @param object $a Appointment.
 * @return array
 */
function rb_public_summary( $a ) {
	$s = rb_services( false )[ $a->service_id ] ?? null;
	$d = rb_doctors( false )[ $a->doctor_id ] ?? null;
	return array(
		'reference' => $a->reference,
		'status'    => $a->status,
		'status_label' => rb_statuses()[ $a->status ] ?? $a->status,
		'service'   => $s ? $s->name : '',
		'service_slug' => $s ? $s->slug : '',
		'doctor'    => $d ? $d->name : '',
		'doctor_slug' => $d ? $d->slug : '',
		'when'      => rb_when( $a->starts_at ),
		'first_name' => explode( ' ', $a->patient_name )[0],
	);
}

/**
 * Staff view of an appointment.
 *
 * @param object $a Appointment.
 * @return array
 */
function rb_staff_json( $a ) {
	$s     = rb_services( false )[ $a->service_id ] ?? null;
	$d     = rb_doctors( false )[ $a->doctor_id ] ?? null;
	$local = rb_local( $a->starts_at );
	return array(
		'id'          => (int) $a->id,
		'reference'   => $a->reference,
		'status'      => $a->status,
		'service'     => $s ? $s->name : '',
		'doctor'      => $d ? $d->name : '',
		'doctor_slug' => $d ? $d->slug : '',
		'date'        => $local->format( 'Y-m-d' ),
		'time'        => $local->format( 'H:i' ),
		'when'        => $local->format( 'D M j, g:i a' ),
		'patient'     => $a->patient_name,
		'email'       => $a->patient_email,
		'phone'       => $a->patient_phone,
		'verified'    => (bool) $a->verified,
		'created_at'  => $a->created_at,
	);
}

/**
 * Register routes.
 */
function rb_register_routes() {
	$ns  = 'rb/v1';
	$pub = '__return_true';

	register_rest_route(
		$ns,
		'/days',
		array(
			'methods'             => 'GET',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				if ( ! rb_rate_ok( 'get_' . rb_client_ip(), 240, MINUTE_IN_SECONDS ) ) {
					return rb_rest_error( new WP_Error( 'rb_rate', 'Too many requests.', array( 'status' => 429 ) ) );
				}
				$sd = rb_rest_svc_doc( $r );
				if ( is_wp_error( $sd ) ) {
					return rb_rest_error( $sd );
				}
				$from = (string) $r->get_param( 'from' );
				if ( ! rb_parse_date( $from ) ) {
					$from = rb_now()->format( 'Y-m-d' );
				}
				return array( 'ok' => true, 'days' => rb_days( $sd[0], $sd[1], $from, 14 ) );
			},
		)
	);

	register_rest_route(
		$ns,
		'/slots',
		array(
			'methods'             => 'GET',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				if ( ! rb_rate_ok( 'get_' . rb_client_ip(), 240, MINUTE_IN_SECONDS ) ) {
					return rb_rest_error( new WP_Error( 'rb_rate', 'Too many requests.', array( 'status' => 429 ) ) );
				}
				$sd = rb_rest_svc_doc( $r );
				if ( is_wp_error( $sd ) ) {
					return rb_rest_error( $sd );
				}
				$date = (string) $r->get_param( 'date' );
				if ( ! rb_parse_date( $date ) ) {
					return rb_rest_error( new WP_Error( 'rb_invalid', 'Invalid date.', array( 'status' => 422 ) ) );
				}
				return array( 'ok' => true, 'date' => $date, 'slots' => rb_slots_json( rb_slots( $sd[0], $sd[1], $date ) ) );
			},
		)
	);

	register_rest_route(
		$ns,
		'/requests',
		array(
			'methods'             => 'POST',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				$in = $r->get_params();
				if ( ! empty( $in['website'] ) ) {
					// Honeypot filled: pretend success, store nothing.
					return array( 'ok' => true, 'reference' => 'RFH-0000-00', 'status' => 'pending', 'spam' => true );
				}
				$in['human'] = isset( $in['rb_check'] ) && 'human' === $in['rb_check'];
				$res         = rb_create_request( $in );
				if ( is_wp_error( $res ) ) {
					return rb_rest_error( $res );
				}
				return new WP_REST_Response( array( 'ok' => true, 'duplicate' => $res['duplicate'] ) + rb_public_summary( $res['appt'] ), $res['duplicate'] ? 200 : 201 );
			},
		)
	);

	// Patient self-service via secure link.
	register_rest_route(
		$ns,
		'/manage',
		array(
			'methods'             => 'GET',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				$a = rb_token_appt( (string) $r->get_param( 't' ) );
				return is_wp_error( $a ) ? rb_rest_error( $a ) : array( 'ok' => true ) + rb_public_summary( $a );
			},
		)
	);
	register_rest_route(
		$ns,
		'/manage/(?P<what>days|slots)',
		array(
			'methods'             => 'GET',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				$a = rb_token_appt( (string) $r->get_param( 't' ) );
				if ( is_wp_error( $a ) ) {
					return rb_rest_error( $a );
				}
				$service = rb_services()[ $a->service_id ] ?? null;
				$doctor  = rb_doctors()[ $a->doctor_id ] ?? null;
				if ( ! $service || ! $doctor ) {
					return rb_rest_error( new WP_Error( 'rb_config', 'Please call the clinic to change this appointment.', array( 'status' => 409 ) ) );
				}
				if ( 'days' === $r['what'] ) {
					$from = rb_parse_date( (string) $r->get_param( 'from' ) ) ? (string) $r->get_param( 'from' ) : rb_now()->format( 'Y-m-d' );
					return array( 'ok' => true, 'days' => rb_days( $service, $doctor, $from, 14 ) );
				}
				$date = (string) $r->get_param( 'date' );
				if ( ! rb_parse_date( $date ) ) {
					return rb_rest_error( new WP_Error( 'rb_invalid', 'Invalid date.', array( 'status' => 422 ) ) );
				}
				return array( 'ok' => true, 'slots' => rb_slots_json( rb_slots( $service, $doctor, $date, array( 'exclude' => $a->id ) ) ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/manage/(?P<action>cancel|reschedule)',
		array(
			'methods'             => 'POST',
			'permission_callback' => $pub,
			'callback'            => function ( WP_REST_Request $r ) {
				if ( ! rb_rate_ok( 'manage_' . rb_client_ip(), 10, 10 * MINUTE_IN_SECONDS ) ) {
					return rb_rest_error( new WP_Error( 'rb_rate', 'Too many attempts. Please wait a few minutes.', array( 'status' => 429 ) ) );
				}
				$a = rb_token_appt( (string) $r->get_param( 't' ) );
				if ( is_wp_error( $a ) ) {
					return rb_rest_error( $a );
				}
				$args = array(
					'date'   => (string) $r->get_param( 'date' ),
					'time'   => (string) $r->get_param( 'time' ),
					'reason' => 'cancel' === $r['action'] ? 'Canceled by the patient using the secure link.' : 'Changed by the patient using the secure link.',
				);
				$res  = rb_transition( (int) $a->id, $r['action'], $args, 'patient' );
				if ( is_wp_error( $res ) ) {
					return rb_rest_error( $res );
				}
				rb_use_token( $a->token_id );
				return array( 'ok' => true ) + rb_public_summary( $res );
			},
		)
	);

	// Staff.
	$can_view   = fn() => current_user_can( 'rb_view_appointments' );
	$can_manage = fn() => current_user_can( 'rb_manage_appointments' );

	register_rest_route(
		$ns,
		'/appointments',
		array(
			'methods'             => 'GET',
			'permission_callback' => $can_view,
			'callback'            => function ( WP_REST_Request $r ) {
				$rows = rb_query_appointments(
					array(
						'status' => sanitize_key( (string) $r->get_param( 'status' ) ),
						'from'   => (string) $r->get_param( 'from' ),
						'to'     => (string) $r->get_param( 'to' ),
					)
				);
				return array( 'ok' => true, 'items' => array_map( 'rb_staff_json', $rows ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/appointments/(?P<id>\d+)/(?P<action>confirm|decline|cancel|complete|reschedule)',
		array(
			'methods'             => 'POST',
			'permission_callback' => $can_manage,
			'callback'            => function ( WP_REST_Request $r ) {
				$res = rb_transition(
					(int) $r['id'],
					$r['action'],
					array(
						'date'   => (string) $r->get_param( 'date' ),
						'time'   => (string) $r->get_param( 'time' ),
						'doctor' => (string) $r->get_param( 'doctor' ),
						'note'   => (string) $r->get_param( 'note' ),
						'reason' => (string) $r->get_param( 'reason' ),
					),
					'staff'
				);
				return is_wp_error( $res ) ? rb_rest_error( $res ) : array( 'ok' => true, 'item' => rb_staff_json( $res ) );
			},
		)
	);
	register_rest_route(
		$ns,
		'/staff/(?P<what>days|slots)',
		array(
			'methods'             => 'GET',
			'permission_callback' => $can_manage,
			'callback'            => function ( WP_REST_Request $r ) {
				$a = rb_get_appt( (int) $r->get_param( 'appointment' ) );
				if ( ! $a ) {
					return rb_rest_error( new WP_Error( 'rb_missing', 'Appointment not found.', array( 'status' => 404 ) ) );
				}
				$service = rb_services( false )[ $a->service_id ] ?? null;
				$ds      = sanitize_title( (string) $r->get_param( 'doctor' ) );
				$doctor  = $ds ? rb_doctor_by_slug( $ds ) : ( rb_doctors()[ $a->doctor_id ] ?? null );
				if ( ! $service || ! $doctor || ! in_array( (int) $service->id, $doctor->services, true ) ) {
					return rb_rest_error( new WP_Error( 'rb_mapping', 'This doctor does not offer the service.', array( 'status' => 422 ) ) );
				}
				if ( 'days' === $r['what'] ) {
					$from = rb_parse_date( (string) $r->get_param( 'from' ) ) ? (string) $r->get_param( 'from' ) : rb_now()->format( 'Y-m-d' );
					return array( 'ok' => true, 'days' => rb_days( $service, $doctor, $from, 14 ) );
				}
				$date = (string) $r->get_param( 'date' );
				if ( ! rb_parse_date( $date ) ) {
					return rb_rest_error( new WP_Error( 'rb_invalid', 'Invalid date.', array( 'status' => 422 ) ) );
				}
				return array( 'ok' => true, 'slots' => rb_slots_json( rb_slots( $service, $doctor, $date, array( 'exclude' => $a->id ) ) ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'rb_register_routes' );

/**
 * Never cache rb/v1 responses.
 *
 * @param WP_REST_Response $res     Response.
 * @param WP_REST_Server   $server  Server.
 * @param WP_REST_Request  $request Request.
 * @return WP_REST_Response
 */
function rb_rest_nocache( $res, $server, $request ) {
	if ( str_starts_with( $request->get_route(), '/rb/v1' ) && $res instanceof WP_REST_Response ) {
		$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$res->header( 'X-Robots-Tag', 'noindex' );
	}
	return $res;
}
add_filter( 'rest_post_dispatch', 'rb_rest_nocache', 10, 3 );

/**
 * Filtered appointment query used by REST and the dashboard.
 *
 * @param array $f status, doctor, service, from, to (Y-m-d clinic), q, limit.
 * @return object[]
 */
function rb_query_appointments( $f ) {
	global $wpdb;
	$where = array( '1=1' );
	$args  = array();
	if ( ! empty( $f['status'] ) && isset( rb_statuses()[ $f['status'] ] ) ) {
		$where[] = 'status = %s';
		$args[]  = $f['status'];
	}
	if ( ! empty( $f['doctor'] ) ) {
		$where[] = 'doctor_id = %d';
		$args[]  = (int) $f['doctor'];
	}
	if ( ! empty( $f['service'] ) ) {
		$where[] = 'service_id = %d';
		$args[]  = (int) $f['service'];
	}
	if ( ! empty( $f['from'] ) && rb_parse_date( $f['from'] ) ) {
		$where[] = 'starts_at >= %s';
		$args[]  = rb_utc( rb_parse_date( $f['from'] ) );
	}
	if ( ! empty( $f['to'] ) && rb_parse_date( $f['to'] ) ) {
		$where[] = 'starts_at < %s';
		$args[]  = rb_utc( rb_parse_date( $f['to'] )->modify( '+1 day' ) );
	}
	if ( ! empty( $f['q'] ) ) {
		$like    = '%' . $wpdb->esc_like( $f['q'] ) . '%';
		$where[] = '(reference LIKE %s OR patient_name LIKE %s OR patient_email LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	$limit = max( 1, min( 500, (int) ( $f['limit'] ?? 200 ) ) );
	$order = ! empty( $f['order'] ) && 'desc' === $f['order'] ? 'DESC' : 'ASC';
	$sql   = 'SELECT * FROM ' . rb_t( 'appointments' ) . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY starts_at $order LIMIT $limit";
	return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
