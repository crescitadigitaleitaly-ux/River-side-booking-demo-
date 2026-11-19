<?php
/**
 * Plugin Name: Riverside Hardening
 * Description: Security headers, a clean answer to user-name probing (?author=N) and no WordPress version in the page source. Small, no settings.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Mouzzam Channa (Crescita Digitale Italia)
 * License: GPL-2.0-or-later
 *
 * @package riverside-hardening
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security headers on every front-end and admin response.
 * The Content-Security-Policy is applied to the public site only (wp-admin needs more freedom).
 */
function rhd_headers() {
	if ( headers_sent() ) {
		return;
	}
	// No includeSubDomains: staging runs on the main domain, and this must not affect other subdomains.
	header( 'Strict-Transport-Security: max-age=31536000' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin' );
	if ( ! is_admin() && ! in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php' ), true ) ) {
		// The theme uses a few inline styles/scripts (fast, no extra requests), so 'unsafe-inline' stays for now.
		header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; upgrade-insecure-requests" );
	}
}
add_action( 'send_headers', 'rhd_headers' );
add_action( 'admin_init', 'rhd_headers' );
add_action( 'login_init', 'rhd_headers' );

/**
 * ?author=1 style probing: redirect home with 301 instead of a 500 error page.
 * Runs before other security plugins answer it.
 */
function rhd_author_probe() {
	if ( is_admin() || ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}
add_action( 'init', 'rhd_author_probe', 0 );

// Author archives are not used on this site: send them home too.
add_action(
	'template_redirect',
	function () {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

// WordPress emoji detection: not needed (all modern browsers show emoji natively); its blob worker
// is blocked by the Content-Security-Policy and it adds extra inline script to every page.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );

// No WordPress version in the page source or feeds.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
add_filter(
	'style_loader_src',
	'rhd_strip_wp_ver',
	99
);
add_filter( 'script_loader_src', 'rhd_strip_wp_ver', 99 );

/**
 * Remove ?ver=<WordPress version> from core assets (theme/plugin versions stay for cache busting).
 *
 * @param string $src URL.
 * @return string
 */
function rhd_strip_wp_ver( $src ) {
	if ( $src && str_contains( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
