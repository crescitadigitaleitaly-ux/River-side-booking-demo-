<?php
/**
 * Plugin Name: Riverside Booking
 * Description: Appointment requests and reception desk for the Riverside Family Health demo. Services and doctors with explicit mappings, working hours, closures, real free times, pending → confirmed workflow with conflict-safe confirmation, test-only notifications and secure patient links. Fictional data only.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Mouzzam Channa (Crescita Digitale Italia)
 * License: GPL-2.0-or-later
 * Text Domain: riverside-booking
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

define( 'RB_VERSION', '1.1.0' );
define( 'RB_DB_VERSION', 3 );
define( 'RB_DIR', __DIR__ );
define( 'RB_URL', plugin_dir_url( __FILE__ ) );
define( 'RB_BLOCK', 15 ); // Minutes per scheduling block.

require_once RB_DIR . '/includes/install.php';
require_once RB_DIR . '/includes/clinic.php';
require_once RB_DIR . '/includes/availability.php';
require_once RB_DIR . '/includes/appointments.php';
require_once RB_DIR . '/includes/notify.php';
require_once RB_DIR . '/includes/rest.php';
require_once RB_DIR . '/includes/front.php';
require_once RB_DIR . '/includes/admin.php';
require_once RB_DIR . '/includes/content.php';
require_once RB_DIR . '/includes/seed.php';

register_activation_hook( __FILE__, 'rb_install' );
add_action( 'init', 'rb_maybe_upgrade', 20 ); // After post types and rewrite are ready (creating the manage page needs them).
