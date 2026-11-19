<?php
/**
 * Title: Header
 * Slug: riverside-health/header
 * Categories: riverside-health
 * Inserter: no
 *
 * @package riverside-health
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in inc/sections.php.
echo rh_block( rh_header() );
