<?php
/**
 * Title: Home
 * Slug: riverside-health/home
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_home_hero() . rh_quick_info() . rh_home_services() . rh_home_about() . rh_home_doctors() . rh_steps() . rh_home_faq() . rh_cta() . rh_home_contact() );
