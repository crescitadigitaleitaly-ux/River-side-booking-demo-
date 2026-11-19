<?php
/**
 * Title: Patients page
 * Slug: riverside-health/page-patients
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Patients', 'Patient information', 'Everything you need for your visit: what to bring, insurance and billing, telehealth and answers to common questions.' ) . rh_patients_content() . rh_cta() );
