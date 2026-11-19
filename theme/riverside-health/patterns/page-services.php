<?php
/**
 * Title: Services page
 * Slug: riverside-health/page-services
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Our services', 'Care for every stage of life', 'Family medicine, specialist clinics and dentistry in one modern building, with on-site lab tests and imaging so you get answers faster.' ) . rh_service_rows() . rh_cta() );
