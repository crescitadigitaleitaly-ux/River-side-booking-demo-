<?php
/**
 * Plugin Name: Riverside staging helper
 * Description: STAGING ONLY. Lets the test scripts seed demo data, create demo staff users and log in, when the request carries the secret X-RB-STG header (only its SHA-256 hash is stored on the server). Never deploy to production.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

if ( 'staging' !== wp_get_environment_type() ) {
	return;
}

/*
 * Recording mode (cookie rb_rec=1, staging only): mask emails, phone numbers and the site domain in the HTML
 * before it is sent, so screen recordings can never show contact details (Upwork/Fiverr rules). Links keep working.
 */
if ( isset( $_COOKIE['rb_rec'] ) && '1' === $_COOKIE['rb_rec'] ) {
	add_action(
		'init',
		function () {
			ob_start(
				function ( $html ) {
					$html = preg_replace( '/[A-Za-z0-9._%+-]+@(?!media|import|font-face|keyframes|supports|charset)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+/', '•••••@•••••', $html );
					$html = preg_replace( '/\(555\)\s?\d{3}-\d{4}|\+1555\d{7}|555-01\d\d/', '(•••) •••-••••', $html );
					return preg_replace( '#(?<![/.\w-])(dental\.)?mouzzambuild\.com(?!/)#', '••••••.com', $html );
				}
			);
		},
		0
	);
}

add_action(
	'init',
	function () {
		if ( empty( $_GET['rbstg'] ) || empty( $_SERVER['HTTP_X_RB_STG'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$hash_file = dirname( ABSPATH, 2 ) . '/.rs-staging-helper.sha';
		$expected  = is_readable( $hash_file ) ? trim( (string) file_get_contents( $hash_file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$given     = hash( 'sha256', (string) wp_unslash( $_SERVER['HTTP_X_RB_STG'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! $expected || ! hash_equals( $expected, $given ) ) {
			status_header( 403 );
			exit;
		}
		nocache_headers();
		$do = sanitize_key( wp_unslash( $_GET['rbstg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'seed' === $do && function_exists( 'rb_seed_demo' ) ) {
			wp_send_json( array( 'ok' => true ) + rb_seed_demo() );
		}
		if ( 'clear_rl' === $do ) {
			global $wpdb;
			$n = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_rb\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_rb\\_rl\\_%'" ); // phpcs:ignore
			wp_send_json( array( 'ok' => true, 'deleted' => (int) $n ) );
		}
		if ( 'status' === $do ) {
			wp_send_json( array( 'ok' => true, 'plugin' => defined( 'RB_VERSION' ) ? RB_VERSION : null, 'tz' => wp_timezone_string(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'xff' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', 'real' => $_SERVER['HTTP_X_REAL_IP'] ?? '' ) ); // phpcs:ignore
		}
		if ( 'users' === $do ) {
			// Body: {"reception.demo":"password","manager.demo":"password","subscriber.demo":"password"}.
			$in  = json_decode( (string) file_get_contents( 'php://input' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$map = array( 'reception.demo' => 'rb_reception', 'manager.demo' => 'rb_clinic_manager', 'subscriber.demo' => 'subscriber' );
			$out = array();
			foreach ( $map as $login => $role ) {
				if ( empty( $in[ $login ] ) ) {
					continue;
				}
				$user = get_user_by( 'login', $login );
				$id   = $user ? $user->ID : wp_insert_user( array( 'user_login' => $login, 'user_email' => $login . '@staff.example', 'user_pass' => $in[ $login ], 'display_name' => ucwords( str_replace( '.demo', ' (demo)', $login ) ), 'role' => $role ) );
				if ( $user ) {
					wp_set_password( $in[ $login ], $id );
					$user->set_role( $role );
				}
				$out[ $login ] = is_wp_error( $id ) ? $id->get_error_message() : (int) $id;
			}
			wp_send_json( array( 'ok' => true, 'users' => $out ) );
		}
		if ( 'login' === $do ) {
			$login = isset( $_GET['as'] ) ? sanitize_user( wp_unslash( $_GET['as'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user  = $login ? get_user_by( 'login', $login ) : get_user_by( 'id', 1 );
			if ( ! $user ) {
				status_header( 404 );
				exit;
			}
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, false, true );
			wp_send_json( array( 'ok' => true, 'user' => $user->user_login ) );
		}
		status_header( 400 );
		exit;
	},
	1
);
