<?php
/**
 * Services and doctors: editable custom post types, meta boxes, accessors
 * and the bridge that lets the theme show the edited data.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the two post types. They live under the Reception menu.
 */
function rb_register_types() {
	$common = array(
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'rb-reception',
		'show_in_rest'    => false,
		'capability_type' => array( 'rb_item', 'rb_items' ),
		'map_meta_cap'    => true,
		'hierarchical'    => false,
	);
	register_post_type(
		'rb_service',
		$common + array(
			'labels'   => array(
				'name'          => 'Services',
				'singular_name' => 'Service',
				'add_new_item'  => 'Add service',
				'edit_item'     => 'Edit service',
				'all_items'     => 'Services',
			),
			'supports' => array( 'title', 'page-attributes' ),
		)
	);
	register_post_type(
		'rb_doctor',
		$common + array(
			'labels'   => array(
				'name'          => 'Doctors',
				'singular_name' => 'Doctor',
				'add_new_item'  => 'Add doctor',
				'edit_item'     => 'Edit doctor',
				'all_items'     => 'Doctors',
			),
			'supports' => array( 'title', 'page-attributes' ),
		)
	);
}
add_action( 'init', 'rb_register_types' );

/**
 * Weekday names, Sunday = 0 (PHP "w").
 *
 * @return string[]
 */
function rb_weekdays() {
	return array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday' );
}

/**
 * Parse "08:00-12:00, 13:00-17:00" into ranges. Times must be on the
 * 15-minute grid and in order. Returns null if the text is invalid.
 *
 * @param string $text Input.
 * @return array|null
 */
function rb_parse_ranges( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return array();
	}
	$out  = array();
	$last = -1;
	foreach ( preg_split( '/\s*,\s*/', $text ) as $part ) {
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)$/', $part, $m ) ) {
			return null;
		}
		$a = (int) $m[1] * 60 + (int) $m[2];
		$b = (int) $m[3] * 60 + (int) $m[4];
		if ( $a % RB_BLOCK || $b % RB_BLOCK || $b <= $a || $a < $last ) {
			return null;
		}
		$last  = $b;
		$out[] = array( sprintf( '%02d:%02d', $m[1], $m[2] ), sprintf( '%02d:%02d', $m[3], $m[4] ) );
	}
	return $out;
}

/**
 * Ranges back to text.
 *
 * @param array $ranges Ranges.
 * @return string
 */
function rb_ranges_text( $ranges ) {
	return implode( ', ', array_map( fn( $r ) => $r[0] . '-' . $r[1], (array) $ranges ) );
}

/**
 * Meta fields per type: key => [label, input type, help].
 *
 * @param string $type Post type.
 * @return array
 */
function rb_meta_fields( $type ) {
	if ( 'rb_service' === $type ) {
		return array(
			'rb_duration' => array( 'Appointment length (minutes)', 'number', 'Multiple of 15 is easiest for the schedule.' ),
			'rb_buffer'   => array( 'Buffer after (minutes)', 'number', 'Cleaning or preparation time after each visit.' ),
			'rb_active'   => array( 'Bookable online', 'checkbox', '' ),
			'rb_short'    => array( 'Short description', 'textarea', 'Shown on cards.' ),
			'rb_long'     => array( 'Long description', 'textarea', 'Shown on the Services page.' ),
			'rb_bullets'  => array( 'Bullet points', 'textarea', 'One per line.' ),
			'rb_icon'     => array( 'Icon name (theme)', 'text', 'e.g. tooth, heart, child.' ),
			'rb_image'    => array( 'Image key (theme)', 'text', 'e.g. svc-dental.' ),
		);
	}
	return array(
		'rb_creds'     => array( 'Credentials', 'text', 'e.g. MD, DDS.' ),
		'rb_specialty' => array( 'Specialty / role', 'text', '' ),
		'rb_active'    => array( 'Bookable online', 'checkbox', '' ),
		'rb_bio'       => array( 'Short biography', 'textarea', '' ),
		'rb_education' => array( 'Education', 'text', '' ),
		'rb_languages' => array( 'Languages', 'text', '' ),
	);
}

/**
 * Meta boxes.
 */
function rb_add_meta_boxes() {
	add_meta_box( 'rb_service_box', 'Booking details', 'rb_render_meta_box', 'rb_service', 'normal', 'high' );
	add_meta_box( 'rb_doctor_box', 'Profile', 'rb_render_meta_box', 'rb_doctor', 'normal', 'high' );
	add_meta_box( 'rb_doctor_services', 'Services this doctor offers', 'rb_render_doctor_services', 'rb_doctor', 'normal', 'high' );
	add_meta_box( 'rb_doctor_hours', 'Weekly working hours', 'rb_render_doctor_hours', 'rb_doctor', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'rb_add_meta_boxes' );

/**
 * Render generic fields.
 *
 * @param WP_Post $post Post.
 */
function rb_render_meta_box( $post ) {
	wp_nonce_field( 'rb_save_meta', 'rb_meta_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( rb_meta_fields( $post->post_type ) as $key => $f ) {
		$val = get_post_meta( $post->ID, $key, true );
		if ( 'rb_bullets' === $key && is_array( $val ) ) {
			$val = implode( "\n", $val );
		}
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
		if ( 'textarea' === $f[1] ) {
			echo '<textarea class="large-text" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( (string) $val ) . '</textarea>';
		} elseif ( 'checkbox' === $f[1] ) {
			echo '<label><input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1"' . checked( '0' !== (string) $val, true, false ) . '> Yes</label>';
		} else {
			echo '<input class="regular-text" type="' . esc_attr( $f[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $val ) . '"' . ( 'number' === $f[1] ? ' min="5" max="240" step="5"' : '' ) . '>';
		}
		if ( $f[2] ) {
			echo '<p class="description">' . esc_html( $f[2] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	if ( 'rb_doctor' === $post->post_type ) {
		echo '<p class="description">The page address (slug) <code>' . esc_html( $post->post_name ) . '</code> links this profile to the theme photo.</p>';
	}
}

/**
 * Service mapping checkboxes.
 *
 * @param WP_Post $post Doctor.
 */
function rb_render_doctor_services( $post ) {
	$chosen = array_map( 'intval', (array) get_post_meta( $post->ID, 'rb_services', true ) );
	echo '<p>Patients choosing one of these services can only pick doctors who offer it.</p><fieldset>';
	foreach ( rb_services( false ) as $s ) {
		echo '<label style="display:block;margin:.3rem 0"><input type="checkbox" name="rb_services[]" value="' . (int) $s->id . '"' . checked( in_array( $s->id, $chosen, true ), true, false ) . '> ' . esc_html( $s->name ) . ( $s->active ? '' : ' (not bookable)' ) . '</label>';
	}
	echo '</fieldset>';
}

/**
 * Weekly hours inputs.
 *
 * @param WP_Post $post Doctor.
 */
function rb_render_doctor_hours( $post ) {
	$hours = (array) get_post_meta( $post->ID, 'rb_hours', true );
	echo '<p>Times the doctor can see patients (clinic time). Use the second range for a split day, e.g. 08:00–12:00 and 13:00–17:00. Holidays and one-off absences go under Reception → Closures.</p>';
	echo '<table class="rb-hours-table"><thead><tr><th>Day</th><th>Day off</th><th>From</th><th>To</th><th>From</th><th>To</th></tr></thead><tbody>';
	foreach ( rb_weekdays() as $n => $label ) {
		$r   = $hours[ $n ] ?? array();
		$off = empty( $r );
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input type="checkbox" name="rb_off[' . (int) $n . ']" value="1"' . checked( $off, true, false ) . ' aria-label="' . esc_attr( $label . ' day off' ) . '"></td>';
		for ( $k = 0; $k < 2; $k++ ) {
			foreach ( array( 0 => 'from', 1 => 'to' ) as $x => $what ) {
				echo '<td><input type="time" step="900" name="rb_hours_ui[' . (int) $n . '][' . $k . '][' . $what . ']" value="' . esc_attr( $r[ $k ][ $x ] ?? '' ) . '" aria-label="' . esc_attr( $label . ' range ' . ( $k + 1 ) . ' ' . $what ) . '"></td>';
			}
		}
		echo '</tr>';
	}
	echo '</tbody></table><p class="description">Times use 15-minute steps (e.g. 08:00, 08:15, 08:30).</p>';
}

/**
 * Save meta with validation.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function rb_save_meta( $post_id, $post ) {
	if ( ! in_array( $post->post_type, array( 'rb_service', 'rb_doctor' ), true ) ) {
		return;
	}
	if ( ! isset( $_POST['rb_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['rb_meta_nonce'] ) ), 'rb_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( rb_meta_fields( $post->post_type ) as $key => $f ) {
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per type below.
		if ( 'checkbox' === $f[1] ) {
			$val = $raw ? '1' : '0';
		} elseif ( 'number' === $f[1] ) {
			$val = max( 0, min( 240, (int) $raw ) );
			if ( 'rb_duration' === $key ) {
				$val = max( 5, $val );
			}
		} elseif ( 'rb_bullets' === $key ) {
			$val = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r?\n/', (string) $raw ) ) ) );
		} elseif ( 'textarea' === $f[1] ) {
			$val = sanitize_textarea_field( (string) $raw );
		} else {
			$val = sanitize_text_field( (string) $raw );
		}
		update_post_meta( $post_id, $key, $val );
	}
	if ( 'rb_doctor' === $post->post_type ) {
		$valid    = wp_list_pluck( rb_services( false ), 'id' );
		$services = array_values( array_intersect( array_map( 'intval', (array) ( $_POST['rb_services'] ?? array() ) ), $valid ) );
		update_post_meta( $post_id, 'rb_services', $services );

		$hours  = array();
		$errors = array();
		$ui     = (array) ( $_POST['rb_hours_ui'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed strictly below.
		$off    = (array) ( $_POST['rb_off'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( rb_weekdays() as $n => $label ) {
			$text = '';
			if ( empty( $off[ $n ] ) ) {
				$parts = array();
				foreach ( (array) ( $ui[ $n ] ?? array() ) as $rng ) {
					$a = sanitize_text_field( wp_unslash( $rng['from'] ?? '' ) );
					$b = sanitize_text_field( wp_unslash( $rng['to'] ?? '' ) );
					if ( '' !== $a || '' !== $b ) {
						$parts[] = $a . '-' . $b;
					}
				}
				$text = implode( ', ', $parts );
			}
			$parsed = rb_parse_ranges( $text );
			if ( null === $parsed ) {
				$errors[] = $label;
				$parsed   = ( (array) get_post_meta( $post_id, 'rb_hours', true ) )[ $n ] ?? array();
			}
			$hours[ $n ] = $parsed;
		}
		update_post_meta( $post_id, 'rb_hours', $hours );
		if ( $errors ) {
			set_transient( 'rb_hours_error_' . get_current_user_id(), $errors, 60 );
		}
	}
	rb_flush_cache();
}
add_action( 'save_post', 'rb_save_meta', 10, 2 );

/**
 * Show hour-format errors after saving.
 */
function rb_hours_notice() {
	$errors = get_transient( 'rb_hours_error_' . get_current_user_id() );
	if ( $errors ) {
		delete_transient( 'rb_hours_error_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>Working hours not saved for: ' . esc_html( implode( ', ', $errors ) ) . '. Each range needs a start and a later end time in 15-minute steps, and the second range must start after the first ends.</p></div>';
	}
}
add_action( 'admin_notices', 'rb_hours_notice' );

/**
 * In-request cache reset.
 */
function rb_flush_cache() {
	$GLOBALS['rb_cache'] = array();
}

/**
 * Services as objects, ordered.
 *
 * @param bool $active_only Only bookable services.
 * @return object[] keyed by ID
 */
function rb_services( $active_only = true ) {
	$ck = 'services_' . (int) $active_only;
	if ( isset( $GLOBALS['rb_cache'][ $ck ] ) ) {
		return $GLOBALS['rb_cache'][ $ck ];
	}
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'rb_service', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) ) ) as $p ) {
		$active = '0' !== (string) get_post_meta( $p->ID, 'rb_active', true );
		if ( $active_only && ! $active ) {
			continue;
		}
		$out[ $p->ID ] = (object) array(
			'id'       => $p->ID,
			'slug'     => $p->post_name,
			'name'     => $p->post_title,
			'duration' => max( 5, (int) get_post_meta( $p->ID, 'rb_duration', true ) ),
			'buffer'   => max( 0, (int) get_post_meta( $p->ID, 'rb_buffer', true ) ),
			'active'   => $active,
			'short'    => (string) get_post_meta( $p->ID, 'rb_short', true ),
			'long'     => (string) get_post_meta( $p->ID, 'rb_long', true ),
			'bullets'  => (array) get_post_meta( $p->ID, 'rb_bullets', true ),
			'icon'     => (string) get_post_meta( $p->ID, 'rb_icon', true ),
			'image'    => (string) get_post_meta( $p->ID, 'rb_image', true ),
		);
	}
	$GLOBALS['rb_cache'][ $ck ] = $out;
	return $out;
}

/**
 * Doctors as objects, ordered.
 *
 * @param bool $active_only Only bookable doctors.
 * @return object[] keyed by ID
 */
function rb_doctors( $active_only = true ) {
	$ck = 'doctors_' . (int) $active_only;
	if ( isset( $GLOBALS['rb_cache'][ $ck ] ) ) {
		return $GLOBALS['rb_cache'][ $ck ];
	}
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'rb_doctor', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) ) ) as $p ) {
		$active = '0' !== (string) get_post_meta( $p->ID, 'rb_active', true );
		if ( $active_only && ! $active ) {
			continue;
		}
		$out[ $p->ID ] = (object) array(
			'id'        => $p->ID,
			'slug'      => $p->post_name,
			'name'      => $p->post_title,
			'creds'     => (string) get_post_meta( $p->ID, 'rb_creds', true ),
			'specialty' => (string) get_post_meta( $p->ID, 'rb_specialty', true ),
			'bio'       => (string) get_post_meta( $p->ID, 'rb_bio', true ),
			'education' => (string) get_post_meta( $p->ID, 'rb_education', true ),
			'languages' => (string) get_post_meta( $p->ID, 'rb_languages', true ),
			'services'  => array_map( 'intval', (array) get_post_meta( $p->ID, 'rb_services', true ) ),
			'hours'     => (array) get_post_meta( $p->ID, 'rb_hours', true ),
			'active'    => $active,
		);
	}
	$GLOBALS['rb_cache'][ $ck ] = $out;
	return $out;
}

/**
 * Find by slug.
 *
 * @param string $slug Slug.
 * @return object|null
 */
function rb_service_by_slug( $slug ) {
	foreach ( rb_services() as $s ) {
		if ( $s->slug === $slug ) {
			return $s;
		}
	}
	return null;
}

/**
 * Find by slug.
 *
 * @param string $slug Slug.
 * @return object|null
 */
function rb_doctor_by_slug( $slug ) {
	foreach ( rb_doctors() as $d ) {
		if ( $d->slug === $slug ) {
			return $d;
		}
	}
	return null;
}

/**
 * Bookable doctors mapped to a service.
 *
 * @param int $service_id Service.
 * @return object[]
 */
function rb_doctors_for_service( $service_id ) {
	return array_filter( rb_doctors(), fn( $d ) => in_array( (int) $service_id, $d->services, true ) );
}

/**
 * Short doctor label, e.g. "Dr. Romano".
 *
 * @param object $d Doctor.
 * @return string
 */
function rb_doctor_short( $d ) {
	return preg_replace( '/^Dr\. .* (\S+)$/', 'Dr. $1', $d->name );
}

/*
 * Theme bridge: when the theme asks for its services/doctors, give it the
 * edited records (same array shape as theme/inc/data.php). If nothing is
 * seeded yet, the theme's own defaults stay.
 */
add_filter(
	'rh_services',
	function ( $defaults ) {
		$svc = empty( $GLOBALS['rb_seeding'] ) ? rb_services() : array();
		if ( ! $svc ) {
			return $defaults;
		}
		$out = array();
		foreach ( $svc as $s ) {
			$d                = $defaults[ $s->slug ] ?? array();
			$out[ $s->slug ] = array(
				$s->name,
				$s->icon ? $s->icon : ( $d[1] ?? 'stethoscope' ),
				$s->short ? $s->short : ( $d[2] ?? '' ),
				$s->long ? $s->long : ( $d[3] ?? '' ),
				$s->bullets ? $s->bullets : ( $d[4] ?? array() ),
				$s->image ? $s->image : ( $d[5] ?? null ),
			);
		}
		return $out;
	}
);
add_filter(
	'rh_doctors',
	function ( $defaults ) {
		$docs = empty( $GLOBALS['rb_seeding'] ) ? rb_doctors() : array();
		if ( ! $docs ) {
			return $defaults;
		}
		$services = rb_services( false );
		$out      = array();
		foreach ( $docs as $doc ) {
			if ( ! isset( $defaults[ $doc->slug ] ) ) {
				continue; // The theme has photos only for known keys.
			}
			$first             = $doc->services ? ( $services[ $doc->services[0] ]->slug ?? '' ) : '';
			$out[ $doc->slug ] = array( $doc->name, $doc->creds, $doc->specialty, $first, $doc->bio, $doc->education, $doc->languages );
		}
		return $out ? $out : $defaults;
	}
);
