<?php
/**
 * Tables, roles and the manage-appointment page.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Table name helper.
 *
 * @param string $name Short name.
 * @return string
 */
function rb_t( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'rb_' . $name;
}

/**
 * Run install when the stored DB version is older than the code.
 */
function rb_maybe_upgrade() {
	if ( (int) get_option( 'rb_db_version' ) < RB_DB_VERSION ) {
		rb_install();
	}
}

/**
 * Create or update tables, roles and pages. Safe to run more than once.
 */
function rb_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . rb_t( 'appointments' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		reference varchar(20) NOT NULL,
		status varchar(12) NOT NULL DEFAULT 'pending',
		service_id bigint(20) unsigned NOT NULL,
		doctor_id bigint(20) unsigned NOT NULL,
		doctor_pref varchar(10) NOT NULL DEFAULT 'chosen',
		starts_at datetime NOT NULL,
		ends_at datetime NOT NULL,
		patient_name varchar(120) NOT NULL,
		patient_email varchar(190) NOT NULL,
		patient_phone varchar(30) NOT NULL,
		patient_type varchar(12) NOT NULL,
		visit_type varchar(20) NOT NULL,
		note varchar(300) NOT NULL DEFAULT '',
		staff_note text NULL,
		idem_key varchar(64) NULL,
		ip_hash char(64) NOT NULL DEFAULT '',
		verified tinyint(1) NOT NULL DEFAULT 1,
		reminder_sent tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		confirmed_at datetime NULL,
		canceled_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY reference (reference),
		UNIQUE KEY idem_key (idem_key),
		KEY status_start (status,starts_at),
		KEY doctor_start (doctor_id,starts_at)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . rb_t( 'slot_locks' ) . " (
		doctor_id bigint(20) unsigned NOT NULL,
		block_start datetime NOT NULL,
		appointment_id bigint(20) unsigned NOT NULL,
		PRIMARY KEY  (doctor_id,block_start),
		KEY appointment_id (appointment_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . rb_t( 'closures' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		starts_at datetime NOT NULL,
		ends_at datetime NOT NULL,
		reason varchar(120) NOT NULL DEFAULT '',
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY range_idx (starts_at,ends_at)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . rb_t( 'events' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		appointment_id bigint(20) unsigned NOT NULL,
		action varchar(30) NOT NULL,
		from_status varchar(12) NOT NULL DEFAULT '',
		to_status varchar(12) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		actor varchar(20) NOT NULL DEFAULT 'staff',
		details text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY appointment_id (appointment_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . rb_t( 'outbox' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		appointment_id bigint(20) unsigned NOT NULL,
		type varchar(20) NOT NULL,
		recipient varchar(190) NOT NULL,
		subject varchar(255) NOT NULL,
		body longtext NOT NULL,
		delivered tinyint(1) NOT NULL DEFAULT 0,
		delivery_note varchar(190) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY appointment_id (appointment_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . rb_t( 'tokens' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		appointment_id bigint(20) unsigned NOT NULL,
		token_hash char(64) NOT NULL,
		expires_at datetime NOT NULL,
		used_at datetime NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY appointment_id (appointment_id)
		) $c;"
	);

	rb_install_roles();
	rb_install_pages();
	update_option( 'rb_db_version', RB_DB_VERSION, false );
	if ( ! get_option( 'rb_settings' ) ) {
		update_option(
			'rb_settings',
			array(
				'send_real'   => 0,
				'allowlist'   => '',
				'min_notice'  => 120,
				'max_days'    => 60,
			),
			false
		);
	}
	if ( ! wp_next_scheduled( 'rb_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'rb_hourly' );
	}
}

/**
 * Capabilities for editing services and doctors (custom post types).
 *
 * @return string[]
 */
function rb_item_caps() {
	return array( 'edit_rb_items', 'edit_others_rb_items', 'publish_rb_items', 'read_private_rb_items', 'delete_rb_items', 'delete_others_rb_items', 'delete_published_rb_items', 'delete_private_rb_items', 'edit_published_rb_items', 'edit_private_rb_items' );
}

/**
 * Roles: reception (appointments only) and clinic manager (plus services,
 * doctors, closures). Administrators get everything.
 */
function rb_install_roles() {
	$reception = array(
		'read'                   => true,
		'rb_view_appointments'   => true,
		'rb_manage_appointments' => true,
	);
	remove_role( 'rb_reception' );
	add_role( 'rb_reception', 'Reception (demo)', $reception );

	$manager = $reception + array( 'rb_manage_clinic' => true, 'upload_files' => true );
	foreach ( rb_item_caps() as $cap ) {
		$manager[ $cap ] = true;
	}
	remove_role( 'rb_clinic_manager' );
	add_role( 'rb_clinic_manager', 'Clinic manager (demo)', $manager );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array_merge( array( 'rb_view_appointments', 'rb_manage_appointments', 'rb_manage_clinic' ), rb_item_caps() ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}

/**
 * The page patients land on from their secure link.
 */
function rb_install_pages() {
	$existing = get_page_by_path( 'manage-appointment' );
	if ( $existing ) {
		update_option( 'rb_manage_page', $existing->ID, false );
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Manage your appointment',
			'post_name'    => 'manage-appointment',
			'post_content' => '<!-- wp:shortcode -->[rb_manage]<!-- /wp:shortcode -->',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( 'rb_manage_page', $id, false );
	}
}

/**
 * Settings with defaults.
 *
 * @return array
 */
function rb_settings() {
	return wp_parse_args(
		(array) get_option( 'rb_settings', array() ),
		array(
			'send_real'  => 0,
			'allowlist'  => '',
			'min_notice' => 120,
			'max_days'   => 60,
		)
	);
}
