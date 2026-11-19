<?php
/**
 * Title: Privacy page
 * Slug: riverside-health/page-privacy
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_page_hero( 'Privacy', 'Privacy policy', 'How this demonstration website handles the details you send through its forms.' ) . rh_privacy_content() );
