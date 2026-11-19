<?php
/**
 * Reception desk in wp-admin: appointments list with filters, appointment
 * detail with actions and history, closures, test outbox, settings & demo.
 *
 * @package riverside-booking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu.
 */
function rb_admin_menu() {
	add_menu_page( 'Reception', 'Reception', 'rb_view_appointments', 'rb-reception', 'rb_page_reception', 'dashicons-calendar-alt', 3 );
	add_submenu_page( 'rb-reception', 'Appointments', 'Appointments', 'rb_view_appointments', 'rb-reception', 'rb_page_reception' );
	add_submenu_page( 'rb-reception', 'Closures and holidays', 'Closures', 'rb_manage_clinic', 'rb-closures', 'rb_page_closures' );
	add_submenu_page( 'rb-reception', 'Test outbox', 'Test outbox', 'rb_view_appointments', 'rb-outbox', 'rb_page_outbox' );
	add_submenu_page( 'rb-reception', 'Booking settings', 'Settings & demo', 'manage_options', 'rb-settings', 'rb_page_settings' );
}
add_action( 'admin_menu', 'rb_admin_menu' );

/**
 * Admin assets on our pages only.
 *
 * @param string $hook Page hook.
 */
function rb_admin_assets( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$type = get_current_screen() ? get_current_screen()->post_type : '';
	if ( ! str_starts_with( $page, 'rb-' ) && ! in_array( $type, array( 'rb_service', 'rb_doctor' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'rb-admin', RB_URL . 'assets/admin.css', array(), RB_VERSION );
	wp_enqueue_script( 'rb-admin', RB_URL . 'assets/admin.js', array(), RB_VERSION, true );
	wp_localize_script(
		'rb-admin',
		'RB',
		array(
			'api'   => esc_url_raw( rest_url( 'rb/v1/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rb_admin_assets' );

/**
 * Send reception staff straight to the desk after login.
 *
 * @param string  $to   Redirect.
 * @param string  $req  Requested.
 * @param WP_User $user User.
 * @return string
 */
function rb_login_redirect( $to, $req, $user ) {
	if ( $user instanceof WP_User && array_intersect( array( 'rb_reception', 'rb_clinic_manager' ), (array) $user->roles ) ) {
		return admin_url( 'admin.php?page=rb-reception' );
	}
	return $to;
}
add_filter( 'login_redirect', 'rb_login_redirect', 10, 3 );

/**
 * Reception staff see a clean admin bar (no site-editing links they cannot use).
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function rb_clean_admin_bar( $bar ) {
	$user = wp_get_current_user();
	if ( ! array_intersect( array( 'rb_reception', 'rb_clinic_manager' ), (array) $user->roles ) ) {
		return;
	}
	// Top-level items sit in the 'root-default' group (left side); keep only the site name and menu toggle.
	foreach ( $bar->get_nodes() as $node ) {
		if ( in_array( $node->parent, array( false, '', 'root-default' ), true ) && ! in_array( $node->id, array( 'site-name', 'menu-toggle', 'top-secondary', 'root-default' ), true ) || str_contains( $node->id, 'hostinger' ) ) {
			$bar->remove_node( $node->id );
		}
	}
	$bar->add_node( array( 'id' => 'rb-desk', 'title' => 'Reception desk', 'href' => admin_url( 'admin.php?page=rb-reception' ) ) );
}
// Runs after every plugin (e.g. Hostinger) has added its items.
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		if ( $wp_admin_bar instanceof WP_Admin_Bar ) {
			rb_clean_admin_bar( $wp_admin_bar );
		}
	}
);

/**
 * Status pill.
 *
 * @param string $status Status.
 * @return string
 */
function rb_pill( $status ) {
	return '<span class="rb-pill rb-pill--' . esc_attr( $status ) . '">' . esc_html( rb_statuses()[ $status ] ?? $status ) . '</span>';
}

/**
 * Buttons for allowed actions.
 *
 * @param object $a     Appointment.
 * @param bool   $small Compact.
 * @return string
 */
function rb_action_buttons( $a, $small = true ) {
	if ( ! current_user_can( 'rb_manage_appointments' ) ) {
		return '';
	}
	$cls = 'button' . ( $small ? ' button-small' : '' );
	$out = '';
	if ( 'pending' === $a->status ) {
		$out .= '<button type="button" class="' . $cls . ' button-primary" data-rb-act="confirm" data-id="' . (int) $a->id . '">Confirm</button> ';
		$out .= '<button type="button" class="' . $cls . '" data-rb-act="decline" data-id="' . (int) $a->id . '">Decline</button> ';
	}
	if ( 'confirmed' === $a->status && $a->starts_at <= current_time( 'mysql', true ) ) {
		$out .= '<button type="button" class="' . $cls . ' button-primary" data-rb-act="complete" data-id="' . (int) $a->id . '">Mark completed</button> ';
	}
	if ( in_array( $a->status, array( 'pending', 'confirmed' ), true ) ) {
		if ( $small ) {
			$out .= '<a class="' . $cls . '" href="' . esc_url( admin_url( 'admin.php?page=rb-reception&view=' . (int) $a->id . '#reschedule' ) ) . '">Reschedule</a> ';
		}
		$out .= '<button type="button" class="' . $cls . ' rb-danger" data-rb-act="cancel" data-id="' . (int) $a->id . '">Cancel</button>';
	}
	return $out;
}

/**
 * Appointments list / detail.
 */
function rb_page_reception() {
	if ( ! current_user_can( 'rb_view_appointments' ) ) {
		wp_die( 'Sorry, you are not allowed to view appointments.', 403 );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	if ( ! empty( $_GET['view'] ) ) {
		rb_page_detail( (int) $_GET['view'] );
		return;
	}
	global $wpdb;
	$f = array(
		'status'  => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
		'doctor'  => isset( $_GET['doctor'] ) ? (int) $_GET['doctor'] : 0,
		'service' => isset( $_GET['service'] ) ? (int) $_GET['service'] : 0,
		'from'    => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : rb_now()->format( 'Y-m-d' ),
		'to'      => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
		'q'       => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
	);
	// phpcs:enable
	$rows     = rb_query_appointments( $f );
	$services = rb_services( false );
	$doctors  = rb_doctors( false );
	$t        = rb_t( 'appointments' );
	$now      = gmdate( 'Y-m-d H:i:s' );
	$today    = rb_now()->setTime( 0, 0 );
	$kpi      = array(
		'Pending requests'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status='pending' AND starts_at > '$now'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'Confirmed today'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status='confirmed' AND starts_at >= %s AND starts_at < %s", rb_utc( $today ), rb_utc( $today->modify( '+1 day' ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'Confirmed, next 7 days' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status='confirmed' AND starts_at >= %s AND starts_at < %s", $now, gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'Canceled, last 7 days'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status='canceled' AND canceled_at >= %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	echo '<div class="wrap rb-wrap"><h1 class="wp-heading-inline">Reception</h1><p class="rb-sub">Riverside Family Health · demo clinic with fictional patients · times in clinic time (' . esc_html( rb_now()->format( 'T' ) ) . ')</p>';
	echo '<div class="rb-notice" data-rb-notice aria-live="polite"></div>';
	echo '<div class="rb-kpis">';
	foreach ( $kpi as $label => $n ) {
		echo '<div class="rb-kpi"><span class="rb-kpi__n">' . (int) $n . '</span><span class="rb-kpi__l">' . esc_html( $label ) . '</span></div>';
	}
	echo '</div>';

	echo '<form class="rb-filters" method="get"><input type="hidden" name="page" value="rb-reception">';
	echo '<label>Status<select name="status"><option value="">All</option>';
	foreach ( rb_statuses() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label><label>Doctor<select name="doctor"><option value="0">All</option>';
	foreach ( $doctors as $d ) {
		echo '<option value="' . (int) $d->id . '"' . selected( $f['doctor'], $d->id, false ) . '>' . esc_html( $d->name ) . '</option>';
	}
	echo '</select></label><label>Service<select name="service"><option value="0">All</option>';
	foreach ( $services as $s ) {
		echo '<option value="' . (int) $s->id . '"' . selected( $f['service'], $s->id, false ) . '>' . esc_html( $s->name ) . '</option>';
	}
	echo '</select></label><label>From<input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"></label><label>To<input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"></label>';
	echo '<label>Search<input type="search" name="q" value="' . esc_attr( $f['q'] ) . '" placeholder="Reference or name"></label>';
	echo '<button class="button">Filter</button> <a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=rb-reception&from=' ) ) . '">Show all dates</a></form>';

	echo '<table class="widefat striped rb-table"><thead><tr><th>Reference</th><th>When</th><th>Patient</th><th>Service</th><th>Doctor</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="7">No appointments match these filters.</td></tr>';
	}
	foreach ( $rows as $a ) {
		$flags = '';
		if ( ! $a->verified ) {
			$flags .= ' <span class="rb-flag" title="The browser check was missing: possible spam">unverified</span>';
		}
		if ( 'pending' === $a->status && rb_overlaps( $a ) ) {
			$flags .= ' <span class="rb-flag rb-flag--warn" title="Another active request overlaps this time with the same doctor">overlap</span>';
		}
		$local = rb_local( $a->starts_at );
		echo '<tr data-row="' . (int) $a->id . '"><td><a class="rb-ref" href="' . esc_url( admin_url( 'admin.php?page=rb-reception&view=' . (int) $a->id ) ) . '">' . esc_html( $a->reference ) . '</a>' . $flags . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		echo '<td><strong>' . esc_html( $local->format( 'D M j' ) ) . '</strong><br>' . esc_html( $local->format( 'g:i a' ) ) . '</td>';
		echo '<td>' . esc_html( $a->patient_name ) . '<br><span class="rb-dim">' . esc_html( 'new' === $a->patient_type ? 'New patient' : 'Returning' ) . '</span></td>';
		echo '<td>' . esc_html( $services[ $a->service_id ]->name ?? '—' ) . '</td>';
		echo '<td>' . esc_html( $doctors[ $a->doctor_id ]->name ?? '—' ) . ( 'any' === $a->doctor_pref ? '<br><span class="rb-dim">auto-assigned</span>' : '' ) . '</td>';
		echo '<td>' . rb_pill( $a->status ) . '</td><td class="rb-actions">' . rb_action_buttons( $a ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helpers.
	}
	echo '</tbody></table></div>';
}

/**
 * Appointment detail.
 *
 * @param int $id Appointment.
 */
function rb_page_detail( $id ) {
	global $wpdb;
	$a = rb_get_appt( $id );
	echo '<div class="wrap rb-wrap"><p><a href="' . esc_url( admin_url( 'admin.php?page=rb-reception' ) ) . '">← All appointments</a></p>';
	if ( ! $a ) {
		echo '<h1>Appointment not found</h1></div>';
		return;
	}
	$s      = rb_services( false )[ $a->service_id ] ?? null;
	$d      = rb_doctors( false )[ $a->doctor_id ] ?? null;
	$events = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'events' ) . ' WHERE appointment_id = %d ORDER BY id', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$mails  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, subject, created_at, delivery_note FROM ' . rb_t( 'outbox' ) . ' WHERE appointment_id = %d ORDER BY id', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	echo '<h1>' . esc_html( $a->reference ) . ' ' . rb_pill( $a->status ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="rb-notice" data-rb-notice aria-live="polite"></div>';
	$ov = 'pending' === $a->status ? rb_overlaps( $a ) : array();
	if ( $ov ) {
		echo '<div class="notice notice-warning inline"><p><strong>Heads up:</strong> ' . count( $ov ) . ' other active request(s) want an overlapping time with this doctor (' . esc_html( implode( ', ', wp_list_pluck( $ov, 'reference' ) ) ) . '). Only one can be confirmed.</p></div>';
	}
	echo '<div class="rb-grid"><div class="rb-card"><h2>Appointment</h2><dl class="rb-dl">';
	$rows = array(
		'When'          => rb_when( $a->starts_at ),
		'Service'       => $s ? $s->name . ' (' . $s->duration . ' min' . ( $s->buffer ? ' + ' . $s->buffer . ' min buffer' : '' ) . ')' : '—',
		'Doctor'        => ( $d ? $d->name : '—' ) . ( 'any' === $a->doctor_pref ? ' (patient had no preference)' : '' ),
		'Visit type'    => rb_visit_types()[ $a->visit_type ] ?? $a->visit_type,
		'Patient'       => $a->patient_name . ' · ' . ( 'new' === $a->patient_type ? 'New patient' : 'Returning patient' ),
		'Email'         => $a->patient_email,
		'Phone'         => $a->patient_phone,
		'Patient note'  => $a->note ? $a->note : '—',
		'Requested'     => rb_local( $a->created_at )->format( 'M j, Y g:i a' ),
		'Browser check' => $a->verified ? 'Passed' : 'Missing (possible spam)',
	);
	foreach ( $rows as $k => $v ) {
		echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
	}
	echo '</dl>';
	if ( $a->staff_note ) {
		echo '<h3>Staff notes</h3><p class="rb-pre">' . esc_html( $a->staff_note ) . '</p>';
	}
	if ( current_user_can( 'rb_manage_appointments' ) && in_array( $a->status, array( 'pending', 'confirmed' ), true ) ) {
		echo '<p><label for="rb-reason"><strong>Message to patient / internal note (optional)</strong></label><br><input id="rb-reason" class="large-text" type="text" maxlength="200" data-rb-reason placeholder="e.g. Dr. Romano is in surgery that morning"></p>';
	}
	echo '<p class="rb-actions">' . rb_action_buttons( $a, false ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	// Reschedule panel.
	echo '<div class="rb-card" id="reschedule">';
	if ( current_user_can( 'rb_manage_appointments' ) && in_array( $a->status, array( 'pending', 'confirmed' ), true ) && $s ) {
		echo '<h2>Reschedule</h2><p class="rb-dim">Shows only times that follow the doctor\'s hours, closures and confirmed bookings. ' . ( 'confirmed' === $a->status ? 'The appointment stays confirmed at the new time.' : 'The request stays pending.' ) . '</p>';
		echo '<div data-rb-resched data-id="' . (int) $a->id . '"><label>Doctor <select data-rb-rdoc>';
		foreach ( rb_doctors_for_service( $s->id ) as $doc ) {
			echo '<option value="' . esc_attr( $doc->slug ) . '"' . selected( $doc->id, (int) $a->doctor_id, false ) . '>' . esc_html( $doc->name ) . '</option>';
		}
		echo '</select></label><div class="rb-days" data-rb-rdays></div><div class="rb-slots" data-rb-rslots></div><p><button type="button" class="button button-primary" data-rb-rsave disabled>Move appointment</button></p></div>';
	} else {
		echo '<h2>Reschedule</h2><p class="rb-dim">Only pending or confirmed appointments can be moved.</p>';
	}
	echo '</div></div>';

	echo '<div class="rb-grid"><div class="rb-card"><h2>History</h2><ol class="rb-history">';
	foreach ( $events as $e ) {
		$who = 'patient' === $e->actor ? 'Patient' : ( 'system' === $e->actor ? 'System' : ( $e->user_id ? ( get_userdata( $e->user_id )->display_name ?? 'Staff' ) : 'Staff' ) );
		echo '<li><span class="rb-dim">' . esc_html( rb_local( $e->created_at )->format( 'M j, g:i a' ) ) . '</span> <strong>' . esc_html( ucfirst( $e->action ) ) . '</strong> by ' . esc_html( $who ) . ( $e->to_status && $e->to_status !== $e->from_status ? ' → ' . rb_pill( $e->to_status ) : '' ) . ( $e->details ? '<br>' . esc_html( $e->details ) : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</ol></div><div class="rb-card"><h2>Test notifications</h2>';
	if ( ! $mails ) {
		echo '<p class="rb-dim">None yet.</p>';
	}
	echo '<ul class="rb-history">';
	foreach ( $mails as $m ) {
		echo '<li><span class="rb-dim">' . esc_html( rb_local( $m->created_at )->format( 'M j, g:i a' ) ) . '</span> <a href="' . esc_url( admin_url( 'admin.php?page=rb-outbox&msg=' . (int) $m->id ) ) . '">' . esc_html( rb_notify_types()[ $m->type ] ?? $m->type ) . '</a> <span class="rb-dim">· ' . esc_html( $m->delivery_note ) . '</span></li>';
	}
	echo '</ul></div></div></div>';
}

/**
 * Closures and holidays.
 */
function rb_page_closures() {
	global $wpdb;
	if ( ! current_user_can( 'rb_manage_clinic' ) ) {
		wp_die( 'Sorry, you are not allowed to manage closures.', 403 );
	}
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'closures' ) . ' WHERE ends_at > %s ORDER BY starts_at', gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$docs = rb_doctors( false );
	echo '<div class="wrap rb-wrap"><h1>Closures and holidays</h1><p class="rb-sub">Whole-clinic holidays or one doctor\'s blocked time (training, leave). Online booking hides these times immediately.</p>';
	if ( isset( $_GET['rb_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['rb_msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	echo '<div class="rb-grid"><div class="rb-card"><h2>Upcoming</h2><table class="widefat striped"><thead><tr><th>Who</th><th>From</th><th>To</th><th>Reason</th><th></th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="5">No upcoming closures.</td></tr>';
	}
	foreach ( $rows as $c ) {
		$del = wp_nonce_url( admin_url( 'admin-post.php?action=rb_del_closure&id=' . (int) $c->id ), 'rb_del_closure_' . (int) $c->id );
		echo '<tr><td>' . esc_html( $c->doctor_id ? ( $docs[ $c->doctor_id ]->name ?? 'Doctor' ) : 'Whole clinic' ) . '</td><td>' . esc_html( rb_local( $c->starts_at )->format( 'D M j, g:i a' ) ) . '</td><td>' . esc_html( rb_local( $c->ends_at )->format( 'D M j, g:i a' ) ) . '</td><td>' . esc_html( $c->reason ) . '</td><td><a class="rb-danger-link" href="' . esc_url( $del ) . '">Remove</a></td></tr>';
	}
	echo '</tbody></table></div><div class="rb-card"><h2>Add closure</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'rb_add_closure' );
	echo '<input type="hidden" name="action" value="rb_add_closure"><p><label>Who<br><select name="doctor_id"><option value="0">Whole clinic</option>';
	foreach ( $docs as $d ) {
		echo '<option value="' . (int) $d->id . '">' . esc_html( $d->name ) . '</option>';
	}
	echo '</select></label></p><p><label>Start<br><input type="datetime-local" name="starts" required step="900"></label></p><p><label>End<br><input type="datetime-local" name="ends" required step="900"></label></p><p><label>Reason (shown to patients as "closed")<br><input type="text" name="reason" maxlength="120" class="regular-text" required placeholder="e.g. Public holiday"></label></p><p><button class="button button-primary">Add closure</button></p>';
	echo '<p class="rb-dim">Existing confirmed appointments are not moved automatically; reception will see them in the list to reschedule.</p></form></div></div></div>';
}

/**
 * Save closure.
 */
function rb_add_closure() {
	global $wpdb;
	if ( ! current_user_can( 'rb_manage_clinic' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_add_closure' );
	$tz   = wp_timezone();
	$s    = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', sanitize_text_field( wp_unslash( $_POST['starts'] ?? '' ) ), $tz );
	$e    = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', sanitize_text_field( wp_unslash( $_POST['ends'] ?? '' ) ), $tz );
	$back = admin_url( 'admin.php?page=rb-closures' );
	if ( ! $s || ! $e || $e <= $s ) {
		wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Please enter a valid start and end (end after start).' ), $back ) );
		exit;
	}
	$doc = (int) ( $_POST['doctor_id'] ?? 0 );
	if ( $doc && ! isset( rb_doctors( false )[ $doc ] ) ) {
		$doc = 0;
	}
	$wpdb->insert(
		rb_t( 'closures' ),
		array(
			'doctor_id'  => $doc,
			'starts_at'  => rb_utc( $s ),
			'ends_at'    => rb_utc( $e ),
			'reason'     => mb_substr( sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ), 0, 120 ),
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql', true ),
		)
	);
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Closure added.' ), $back ) );
	exit;
}
add_action( 'admin_post_rb_add_closure', 'rb_add_closure' );

/**
 * Delete closure.
 */
function rb_del_closure() {
	global $wpdb;
	$id = (int) ( $_GET['id'] ?? 0 );
	if ( ! current_user_can( 'rb_manage_clinic' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_del_closure_' . $id );
	$wpdb->delete( rb_t( 'closures' ), array( 'id' => $id ) );
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Closure removed.' ), admin_url( 'admin.php?page=rb-closures' ) ) );
	exit;
}
add_action( 'admin_post_rb_del_closure', 'rb_del_closure' );

/**
 * Test outbox.
 */
function rb_page_outbox() {
	global $wpdb;
	if ( ! current_user_can( 'rb_view_appointments' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	echo '<div class="wrap rb-wrap"><h1>Test outbox</h1><p class="rb-sub">Every patient message is created here for review. Nothing is sent to the address a visitor typed. ' . ( rb_settings()['send_real'] ? 'Copies also go to the approved test inbox.' : 'Real sending is off.' ) . '</p>';
	$mid = isset( $_GET['msg'] ) ? (int) $_GET['msg'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $mid ) {
		$m = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . rb_t( 'outbox' ) . ' WHERE id = %d', $mid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $m ) {
			$a = rb_get_appt( $m->appointment_id );
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=rb-outbox' ) ) . '">← All messages</a>' . ( $a ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=rb-reception&view=' . (int) $a->id ) ) . '">Open appointment ' . esc_html( $a->reference ) . '</a>' : '' ) . '</p>';
			echo '<div class="rb-card rb-mail"><dl class="rb-dl"><dt>Type</dt><dd>' . esc_html( rb_notify_types()[ $m->type ] ?? $m->type ) . '</dd><dt>To</dt><dd>' . esc_html( $m->recipient ) . '</dd><dt>Subject</dt><dd>' . esc_html( $m->subject ) . '</dd><dt>Created</dt><dd>' . esc_html( rb_local( $m->created_at )->format( 'M j, Y g:i a' ) ) . '</dd><dt>Delivery</dt><dd>' . esc_html( $m->delivery_note ) . '</dd></dl>';
			echo '<iframe class="rb-mail__frame" sandbox="allow-popups allow-popups-to-escape-sandbox" title="Message preview" srcdoc="' . esc_attr( '<base target="_blank">' . $m->body ) . '"></iframe></div></div>';
			return;
		}
	}
	$rows = $wpdb->get_results( 'SELECT o.id, o.type, o.subject, o.created_at, o.delivery_note, a.reference FROM ' . rb_t( 'outbox' ) . ' o LEFT JOIN ' . rb_t( 'appointments' ) . ' a ON a.id = o.appointment_id ORDER BY o.id DESC LIMIT 200' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	echo '<table class="widefat striped"><thead><tr><th>Created</th><th>Type</th><th>Subject</th><th>Appointment</th><th>Delivery</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="5">No messages yet.</td></tr>';
	}
	foreach ( $rows as $m ) {
		echo '<tr><td>' . esc_html( rb_local( $m->created_at )->format( 'M j, g:i a' ) ) . '</td><td><span class="rb-type rb-type--' . esc_attr( $m->type ) . '">' . esc_html( rb_notify_types()[ $m->type ] ?? $m->type ) . '</span></td><td><a href="' . esc_url( admin_url( 'admin.php?page=rb-outbox&msg=' . (int) $m->id ) ) . '">' . esc_html( $m->subject ) . '</a></td><td>' . esc_html( (string) $m->reference ) . '</td><td>' . esc_html( $m->delivery_note ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/**
 * Settings and demo tools.
 */
function rb_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$s = rb_settings();
	echo '<div class="wrap rb-wrap"><h1>Booking settings and demo tools</h1>';
	if ( isset( $_GET['rb_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['rb_msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	echo '<div class="rb-grid"><div class="rb-card"><h2>Booking rules and test notifications</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'rb_save_settings' );
	echo '<input type="hidden" name="action" value="rb_save_settings">';
	echo '<p><label>Minimum notice (minutes)<br><input type="number" name="min_notice" min="0" max="2880" step="15" value="' . (int) $s['min_notice'] . '"></label></p>';
	echo '<p><label>Book up to (days ahead)<br><input type="number" name="max_days" min="7" max="180" value="' . (int) $s['max_days'] . '"></label></p>';
	echo '<p><label><input type="checkbox" name="send_real" value="1"' . checked( (bool) $s['send_real'], true, false ) . '> Also send a copy of each test message to the approved test inbox(es) below</label></p>';
	echo '<p><label>Approved test inbox(es), one per line<br><textarea name="allowlist" rows="3" class="large-text" placeholder="your-test-inbox@example.com">' . esc_textarea( $s['allowlist'] ) . '</textarea></label><br><span class="rb-dim">Only these addresses can ever receive a message. Patient addresses are never used. On staging, all email is blocked by a server guard anyway.</span></p>';
	echo '<p><button class="button button-primary">Save settings</button></p></form></div>';
	echo '<div class="rb-card"><h2>Demo tools</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'rb_run_reminders' );
	echo '<input type="hidden" name="action" value="rb_run_reminders"><p>Reminders are created automatically every hour for confirmed visits in the next 24 hours.</p><p><button class="button">Create due reminders now</button></p></form><hr>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Reset all demo appointments, closures and messages? Services and doctors are re-seeded too.\');">';
	wp_nonce_field( 'rb_reset_demo' );
	echo '<input type="hidden" name="action" value="rb_reset_demo"><p>Delete every appointment, message, link and closure, re-create the fictional services, doctors, hours and sample requests.</p><p><button class="button rb-danger">Reset demo data</button></p></form></div></div></div>';
}

/**
 * Save settings.
 */
function rb_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_save_settings' );
	$list = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,]+/', (string) wp_unslash( $_POST['allowlist'] ?? '' ) ) ), 'is_email' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	update_option(
		'rb_settings',
		array(
			'send_real'  => empty( $_POST['send_real'] ) ? 0 : 1,
			'allowlist'  => implode( "\n", $list ),
			'min_notice' => max( 0, min( 2880, (int) ( $_POST['min_notice'] ?? 120 ) ) ),
			'max_days'   => max( 7, min( 180, (int) ( $_POST['max_days'] ?? 60 ) ) ),
		),
		false
	);
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Settings saved.' ), admin_url( 'admin.php?page=rb-settings' ) ) );
	exit;
}
add_action( 'admin_post_rb_save_settings', 'rb_save_settings' );

/**
 * Run reminders now.
 */
function rb_run_reminders_now() {
	if ( ! current_user_can( 'rb_manage_appointments' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_run_reminders' );
	$n = rb_run_reminders();
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( $n . ' reminder(s) created in the test outbox.' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=rb-settings' ) ) );
	exit;
}
add_action( 'admin_post_rb_run_reminders', 'rb_run_reminders_now' );

/**
 * Reset demo.
 */
function rb_reset_demo_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	check_admin_referer( 'rb_reset_demo' );
	$r = rb_seed_demo();
	wp_safe_redirect( add_query_arg( 'rb_msg', rawurlencode( 'Demo reset: ' . $r['services'] . ' services, ' . $r['doctors'] . ' doctors, ' . $r['appointments'] . ' sample appointments.' ), admin_url( 'admin.php?page=rb-settings' ) ) );
	exit;
}
add_action( 'admin_post_rb_reset_demo', 'rb_reset_demo_action' );
