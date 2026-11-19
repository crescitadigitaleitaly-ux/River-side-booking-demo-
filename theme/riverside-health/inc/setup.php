<?php
/**
 * One-time setup, re-run whenever RH_SETUP_VERSION increases.
 *
 * Creates the pages (each page body is a single pattern reference, so all
 * copy lives in the theme), and moves pages left over from the previous
 * theme to Draft. Nothing is deleted; WordPress keeps revisions of every
 * page whose content is replaced.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

define( 'RH_SETUP_VERSION', 1 );

/**
 * Pages managed by the theme: slug => [ title, pattern slug, meta description ].
 *
 * @return array
 */
function rh_pages() {
	return array(
		'services'         => array( 'Services', 'riverside-health/page-services', 'Primary care, pediatrics, women\'s health, family dentistry, heart health, lab tests, imaging and vaccinations at Riverside Family Health.' ),
		'doctors'          => array( 'Our Doctors', 'riverside-health/page-doctors', 'Meet the physicians and dentists of Riverside Family Health and book an appointment with the doctor of your choice.' ),
		'book-appointment' => array( 'Book an Appointment', 'riverside-health/page-book', 'Request an appointment online at Riverside Family Health. Choose a service, a doctor and a time that suits you.' ),
		'patients'         => array( 'Patient Information', 'riverside-health/page-patients', 'Your first visit, insurance and billing, telehealth and frequently asked questions at Riverside Family Health.' ),
		'contact'          => array( 'Contact Us', 'riverside-health/page-contact', 'Address, phone, opening hours and contact form for Riverside Family Health.' ),
		'privacy-policy'   => array( 'Privacy Policy', 'riverside-health/page-privacy', 'How the Riverside Family Health demo website handles the details you send through its forms.' ),
	);
}

/**
 * Run setup if this version has not run yet.
 */
function rh_maybe_setup() {
	if ( (int) get_option( 'rh_setup_version' ) >= RH_SETUP_VERSION || get_transient( 'rh_setup_lock' ) ) {
		return;
	}
	set_transient( 'rh_setup_lock', 1, MINUTE_IN_SECONDS );

	$keep = array();
	foreach ( rh_pages() as $slug => $p ) {
		$content = '<!-- wp:pattern {"slug":"' . $p[1] . '"} /-->';
		$page    = get_page_by_path( $slug, OBJECT, 'page' );
		$args    = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $p[0],
			'post_name'    => $slug,
			'post_content' => $content,
			'post_excerpt' => $p[2],
			'meta_input'   => array( '_yoast_wpseo_metadesc' => $p[2] ),
		);
		if ( $page ) {
			$args['ID'] = $page->ID;
			$id         = wp_update_post( wp_slash( $args ), true );
		} else {
			$id = wp_insert_post( wp_slash( $args ), true );
		}
		if ( ! is_wp_error( $id ) ) {
			$keep[] = (int) $id;
		}
	}

	// Home page: the theme's front-page template renders it.
	$home = get_page_by_path( 'home', OBJECT, 'page' );
	if ( ! $home ) {
		$home_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Home',
				'post_name'   => 'home',
			)
		);
	} else {
		$home_id = $home->ID;
		if ( 'publish' !== $home->post_status ) {
			wp_update_post(
				array(
					'ID'          => $home_id,
					'post_status' => 'publish',
				)
			);
		}
	}
	if ( $home_id && ! is_wp_error( $home_id ) ) {
		$keep[] = (int) $home_id;
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $home_id );
	}

	$privacy = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
	if ( $privacy ) {
		update_option( 'wp_page_for_privacy_policy', $privacy->ID );
	}

	// Pages from the previous theme: move to Draft (recoverable in Pages > Drafts).
	$old = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => $keep,
		)
	);
	foreach ( $old as $id ) {
		update_post_meta( $id, '_rh_moved_to_draft', gmdate( 'c' ) );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
	}

	update_option( 'blogname', 'Riverside Family Health' );
	update_option( 'blogdescription', 'Family medicine, pediatrics, women\'s health and dentistry under one roof' );
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	update_option( 'rh_setup_version', RH_SETUP_VERSION );
	flush_rewrite_rules( false );
	delete_transient( 'rh_setup_lock' );
}
add_action( 'init', 'rh_maybe_setup', 20 );
