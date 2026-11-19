<?php
/**
 * Title: Doctors page
 * Slug: riverside-health/page-doctors
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Our doctors', 'Meet the team behind your care', 'Physicians and dentists who take the time to listen, explain and plan your care with you. Choose a doctor and book directly.' ) . '<section class="rh-section"><div class="rh-container">' . rh_doctor_cards( true ) . '</div></section>' . rh_cta() );
