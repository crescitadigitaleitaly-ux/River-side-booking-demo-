<?php
/**
 * Title: Contact page
 * Slug: riverside-health/page-contact
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Contact', 'Contact Riverside Family Health', 'Questions about appointments, billing or your records? Send us a message or call during opening hours.' ) . '<section class="rh-section"><div class="rh-container rh-book-layout"><div>' . rh_form( 'contact' ) . '</div><aside class="rh-aside"><div class="rh-aside__card">' . rh_contact_list() . '</div>' . rh_urgent_card() . '</aside></div></section>' );
