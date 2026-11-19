<?php
/**
 * Riverside Health theme.
 *
 * A fast, light block theme for the Riverside Family Health demo website.
 * All copy lives in inc/data.php and inc/sections.php; pages are created by
 * inc/setup.php when the theme is activated.
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

define( 'RH_VERSION', '1.1.0' );

require_once __DIR__ . '/inc/data.php';
require_once __DIR__ . '/inc/sections.php';
require_once __DIR__ . '/inc/forms.php';
require_once __DIR__ . '/inc/setup.php';

/**
 * Theme supports.
 */
function rh_theme_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'rh_theme_setup' );

/**
 * Stylesheet (about 6 KB gzipped). No other CSS or JS files are loaded.
 */
function rh_enqueue() {
	wp_enqueue_style( 'riverside-health', get_stylesheet_uri(), array(), RH_VERSION );
}
add_action( 'wp_enqueue_scripts', 'rh_enqueue' );

/**
 * Early head: font preload, icon, theme colour and a demo robots rule.
 */
function rh_head() {
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_theme_file_uri( 'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2' ) ) );
	if ( ! has_site_icon() ) {
		printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( get_theme_file_uri( 'assets/icon.svg' ) ) );
	}
	echo '<meta name="theme-color" content="#0f766e">' . "\n";
	if ( ! defined( 'WPSEO_VERSION' ) && is_singular() && has_excerpt() ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( get_the_excerpt() ) );
	} elseif ( ! defined( 'WPSEO_VERSION' ) && is_front_page() ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( 'Riverside Family Health: primary care, pediatrics, women\'s health, family dentistry and heart health under one roof. Book an appointment online.' ) );
	}
}
add_action( 'wp_head', 'rh_head', 1 );

/**
 * Small inline script: sets today's date as the earliest booking date and
 * marks real browsers for the spam check. Printed inline so no extra request
 * is needed.
 */
function rh_footer_script() {
	echo "<script>(function(){var d=new Date(),t=d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);document.querySelectorAll('[data-rh-min-today]').forEach(function(i){i.min=t;});document.querySelectorAll('[data-rh-form]').forEach(function(f){f.addEventListener('submit',function(){var c=f.querySelector('[name=rh_check]');if(c){c.value='human';}});});})();</script>\n";
}
add_action( 'wp_footer', 'rh_footer_script', 20 );

/**
 * Pattern category.
 */
function rh_pattern_category() {
	register_block_pattern_category( 'riverside-health', array( 'label' => 'Riverside Health' ) );
}
add_action( 'init', 'rh_pattern_category', 9 );

/**
 * Document titles: "Page – Riverside Family Health"; the home page leads with
 * what the clinic offers.
 *
 * @param array $parts Title parts.
 * @return array
 */
function rh_title_parts( $parts ) {
	if ( is_front_page() ) {
		$parts['title']   = 'Riverside Family Health';
		$parts['tagline'] = 'Family Doctors, Pediatrics & Dentistry';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'rh_title_parts' );

/**
 * Demo site: keep it out of search results so it never competes with real
 * clinics, while the portfolio links to it.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function rh_robots( $robots ) {
	$robots['noindex'] = true;
	$robots['follow']  = true;
	return $robots;
}
add_filter( 'wp_robots', 'rh_robots' );
