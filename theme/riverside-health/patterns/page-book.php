<?php
/**
 * Title: Book an appointment page
 * Slug: riverside-health/page-book
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Appointments', 'Book an appointment', 'Tell us what you need and when suits you. We confirm every request by phone or email within one working day.' ) . '<section class="rh-section"><div class="rh-container rh-book-layout"><div>' . ( function_exists( 'rb_booking_form' ) ? rb_booking_form() : rh_form( 'booking' ) ) . '</div>' . rh_book_aside() . '</div></section>' );
