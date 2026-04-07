<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handler for Member Check-In - Enhanced with Member Info
 */
add_action('wp_ajax_mmgr_checkin', 'mmgr_handle_checkin');
add_action('wp_ajax_nopriv_mmgr_checkin', 'mmgr_handle_checkin');

function mmgr_handle_checkin() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    
    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    
    if (empty($code)) {
        wp_send_json_error(array('message' => 'Please enter or scan a member code.'));
        return;
    }
    
    // Find member by code
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tbl WHERE member_code = %s",
        $code
    ), ARRAY_A);
    
    if (!$member) {
        wp_send_json_error(array('message' => '❌ Member not found. Code: ' . esc_html($code)));
        return;
    }
    
    // Check if banned
    if (!empty($member['banned']) && $member['banned'] == 1) {
        $ban_reason = !empty($member['banned_reason']) ? $member['banned_reason'] : 'No reason provided';
        wp_send_json_error(array('message' => '⛔ Access Denied: ' . esc_html($member['name']) . ' is banned. Reason: ' . esc_html($ban_reason)));
        return;
    }
    
    // Check if already checked in today
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    $existing_visit = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $visits_tbl WHERE member_id = %d AND visit_time BETWEEN %s AND %s",
        $member['id'],
        $today_start,
        $today_end
    ));
    
    if ($existing_visit > 0) {
        wp_send_json_error(array('message' => '⚠️ ' . esc_html($member['name']) . ' has already checked in today!'));
        return;
    }
    
    // Check expiration
    $today = date('Y-m-d');
    $is_expired = !empty($member['expire_date']) && $member['expire_date'] < $today;
    
    // Get daily fee
    $daily_fee = mmgr_get_daily_fee($member['level']);
    
    // Check for special event fee
    $special_fee = $wpdb->get_var($wpdb->prepare(
        "SELECT fee_amount FROM {$wpdb->prefix}membership_special_fees WHERE event_date = %s AND active = 1",
        $today
    ));
    
    if ($special_fee !== null) {
        $daily_fee = floatval($special_fee);
    }
    
    // Format dates safely
    $start_date = !empty($member['start_date']) ? date('M d, Y', strtotime($member['start_date'])) : 'N/A';
    $expire_date = !empty($member['expire_date']) ? date('M d, Y', strtotime($member['expire_date'])) : 'N/A';
    $last_visited = !empty($member['last_visited']) ? date('M d, Y g:i A', strtotime($member['last_visited'])) : 'Never';

    // Determine first visit (no previous visits recorded for this member)
    $visit_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $visits_tbl WHERE member_id = %d",
        $member['id']
    ) );
    $is_first_visit = ( $visit_count === 0 );

    // ── Pending orientation items for this member ─────────────────────────
    $orientation_items_tbl = $wpdb->prefix . 'membership_orientation_items';
    $orientation_comp_tbl  = $wpdb->prefix . 'membership_orientation_completions';
    $pending_orientation_items = array();
    $total_orientation_items   = 0;

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$orientation_items_tbl'" ) === $orientation_items_tbl ) {
        $total_orientation_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `$orientation_items_tbl` WHERE active = 1"
        );
        if ( $total_orientation_items > 0 ) {
            $pending = $wpdb->get_results( $wpdb->prepare(
                "SELECT oi.id, oi.title
                 FROM `$orientation_items_tbl` oi
                 WHERE oi.active = 1
                   AND NOT EXISTS (
                     SELECT 1 FROM `$orientation_comp_tbl` oc
                     WHERE oc.member_id = %d AND oc.item_id = oi.id
                   )
                 ORDER BY oi.sort_order ASC, oi.id ASC",
                $member['id']
            ), ARRAY_A );
            if ( $pending ) {
                $pending_orientation_items = $pending;
            }
        }
    }

    // ── Membership fee due check ──────────────────────────────────────────
    $levels_tbl            = $wpdb->prefix . 'membership_levels';
    $membership_fee_due    = false;
    $membership_fee_reason = '';
    $membership_fee_amount = 0.0;

    $level_info  = $wpdb->get_row( $wpdb->prepare(
        "SELECT price FROM `$levels_tbl` WHERE level_name = %s",
        $member['level']
    ), ARRAY_A );
    $level_price = $level_info ? floatval( $level_info['price'] ) : 0.0;

    if ( empty( $member['paid'] ) || intval( $member['paid'] ) === 0 ) {
        // Never paid
        $membership_fee_due    = true;
        $membership_fee_reason = 'first_time';
        $membership_fee_amount = $level_price;
    } elseif ( $is_expired ) {
        // Membership has expired — renewal due
        $membership_fee_due    = true;
        $membership_fee_reason = 'expired';
        $membership_fee_amount = $level_price;
    } elseif ( ! empty( $member['expire_date'] ) ) {
        // Expiring within 30 days
        $days_left = ( strtotime( $member['expire_date'] ) - strtotime( $today ) ) / DAY_IN_SECONDS;
        if ( $days_left <= 30 && $days_left >= 0 ) {
            $membership_fee_due    = true;
            $membership_fee_reason = 'expiring_soon';
            $membership_fee_amount = $level_price;
        }
    }

    // Return structured member data
    wp_send_json_success(array(
        'member' => array(
            'id' => intval($member['id']),
            'name' => !empty($member['name']) ? $member['name'] : 'Unknown',
            'first_name' => !empty($member['first_name']) ? $member['first_name'] : '',
            'last_name' => !empty($member['last_name']) ? $member['last_name'] : '',
            'partner_name' => !empty($member['partner_name']) ? $member['partner_name'] : '',
            'member_code' => $member['member_code'],
            'level' => !empty($member['level']) ? $member['level'] : 'N/A',
            'phone' => !empty($member['phone']) ? $member['phone'] : 'N/A',
            'email' => !empty($member['email']) ? $member['email'] : 'N/A',
            'photo_url' => !empty($member['photo_url']) ? $member['photo_url'] : '',
            'start_date' => $start_date,
            'expire_date' => $expire_date,
            'last_visited' => $last_visited,
            'is_expired' => $is_expired,
            'is_first_visit' => $is_first_visit,
            'orientation_done' => !empty($member['orientation_done']) ? (bool) $member['orientation_done'] : false,
            'id_verified' => !empty($member['id_verified']) ? (bool) $member['id_verified'] : false,
        ),
        'daily_fee'                 => floatval($daily_fee),
        'pending_orientation_items' => $pending_orientation_items,
        'total_orientation_items'   => $total_orientation_items,
        'membership_fee_due'        => $membership_fee_due,
        'membership_fee_reason'     => $membership_fee_reason,
        'membership_fee_amount'     => $membership_fee_amount,
    ));
}

/**
 * AJAX Handler to Confirm Payment and Log Visit
 */
add_action('wp_ajax_mmgr_confirm_payment', 'mmgr_confirm_payment');
add_action('wp_ajax_nopriv_mmgr_confirm_payment', 'mmgr_confirm_payment');

function mmgr_confirm_payment() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $daily_fee = isset($_POST['daily_fee']) ? floatval($_POST['daily_fee']) : 0;
    $paid = isset($_POST['paid']) ? intval($_POST['paid']) : 0;
    $notes = isset($_POST['notes']) ? sanitize_text_field($_POST['notes']) : '';
    
    if (!$member_id) {
        wp_send_json_error(array('message' => 'Invalid member ID'));
        return;
    }
    
    // Get member record (name + first-visit flags)
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT name, orientation_done, id_verified FROM $tbl WHERE id = %d",
        $member_id
    ), ARRAY_A);
    
    if (!$member) {
        wp_send_json_error(array('message' => 'Member not found'));
        return;
    }

    // Determine whether this is the member's very first visit
    $prior_visits = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $visits_tbl WHERE member_id = %d",
        $member_id
    ) );
    $is_first_visit = ( $prior_visits === 0 ) ? 1 : 0;

    // Capture first-visit staff-action flags from member record
    $orientation_done = isset( $member['orientation_done'] ) ? (int) $member['orientation_done'] : 0;
    $id_verified      = isset( $member['id_verified'] )      ? (int) $member['id_verified']      : 0;

    // Build notes — include first-visit markers so they're visible in the visit log
    $payment_note = $paid ? 'PAID' : 'UNPAID';
    $extra_notes  = array();
    if ( $is_first_visit )    $extra_notes[] = 'FIRST VISIT';
    if ( $orientation_done )  $extra_notes[] = 'Orientation ✓';
    if ( $id_verified )       $extra_notes[] = 'ID Verified ✓';
    if ( ! empty( $notes ) )  $extra_notes[] = $notes;
    $full_notes = $payment_note . ( ! empty( $extra_notes ) ? ' - ' . implode( ' | ', $extra_notes ) : '' );

    // Record visit with payment status and first-visit log data
    $visit_data = array(
        'member_id'       => $member_id,
        'visit_time'      => current_time('mysql'),
        'daily_fee'       => $daily_fee,
        'notes'           => $full_notes,
        'is_first_visit'  => $is_first_visit,
        'orientation_done'=> $orientation_done,
        'id_verified'     => $id_verified,
    );
    
    $wpdb->insert($visits_tbl, $visit_data);
    
    // Update last visited
    $wpdb->update($tbl, 
        array('last_visited' => current_time('mysql')),
        array('id' => $member_id)
    );
    
    $payment_status = $paid ? '💵 PAID' : '⚠️ UNPAID';
    
    wp_send_json_success(array(
        'message' => '✅ Check-in complete for ' . esc_html($member['name']) . '!<br>Status: ' . $payment_status . '<br>Fee: $' . number_format($daily_fee, 2)
    ));
}

/**
 * AJAX handler to generate and display QR code image
 */
add_action('wp_ajax_mmgr_qrcode', 'mmgr_ajax_generate_qrcode');
add_action('wp_ajax_nopriv_mmgr_qrcode', 'mmgr_ajax_generate_qrcode');

function mmgr_ajax_generate_qrcode() {
    $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
    
    if (!$code) {
        header('HTTP/1.1 400 Bad Request');
        die('No code provided');
    }
    
    // Check if QR library exists
    $qr_lib_path = MMGR_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
    
    if (!file_exists($qr_lib_path)) {
        // Fallback: Generate QR using online API
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($code);
        header('Location: ' . $qr_url);
        exit;
    }
    
    require_once $qr_lib_path;
    
    // Generate QR code directly to output
    header('Content-Type: image/png');
    QRcode::png($code, false, QR_ECLEVEL_L, 10, 2);
    exit;
}

/**
 * AJAX handler to regenerate QR code file for a member (admin only)
 */
add_action('wp_ajax_mmgr_regenerate_qr', 'mmgr_ajax_regenerate_qr');

function mmgr_ajax_regenerate_qr() {
    check_ajax_referer('mmgr_regenerate_qr', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if (!$member_id) {
        wp_send_json_error(array('message' => 'Invalid member ID'));
    }
    
    $member = $wpdb->get_row($wpdb->prepare("SELECT member_code FROM $tbl WHERE id = %d", $member_id), ARRAY_A);
    if (!$member || empty($member['member_code'])) {
        wp_send_json_error(array('message' => 'Member not found'));
    }
    
    $result = mmgr_regenerate_qr_code($member['member_code']);
    if ($result) {
        wp_send_json_success(array('message' => 'QR code regenerated successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to regenerate QR code. Check server logs.'));
    }
}

// AJAX: Load more messages for member portal
add_action('wp_ajax_mmgr_load_more_messages', function() {
    check_ajax_referer('mmgr_load_more_messages', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $other_member_id = intval($_POST['other_member_id']);
    $offset = intval($_POST['offset']);
    $limit = 10; // Load 10 at a time
    
    $messages = mmgr_get_conversation($member['id'], $other_member_id, $limit, $offset);
    
    if (empty($messages)) {
        wp_send_json_error(array('message' => 'No more messages'));
    }
    
    // Format messages for response
    $formatted_messages = array();
    foreach ($messages as $msg) {
        $formatted_messages[] = array(
            'id' => $msg['id'],
            'from_member_id' => $msg['from_member_id'],
            'message' => $msg['message'],
            'image_url' => $msg['image_url'],
            'image_deleted' => $msg['image_deleted'],
            'sent_at' => date('M j, g:i A', strtotime($msg['sent_at']))
        );
    }
    
    wp_send_json_success(array(
        'messages' => $formatted_messages,
        'count' => count($messages)
    ));
});

add_action('wp_ajax_nopriv_mmgr_load_more_messages', function() {
    wp_send_json_error(array('message' => 'Not logged in'));
});

// AJAX: Load more messages for admin
add_action('wp_ajax_mmgr_admin_load_more_messages', function() {
    check_ajax_referer('mmgr_admin_load_more_messages', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    $member_id = intval($_POST['member_id']);
    $offset = intval($_POST['offset']);
    $limit = 10; // Load 10 at a time
    
    $messages = mmgr_get_conversation(0, $member_id, $limit, $offset);
    
    if (empty($messages)) {
        wp_send_json_error(array('message' => 'No more messages'));
    }
    
    // Format messages for response
    $formatted_messages = array();
    foreach ($messages as $msg) {
        $formatted_messages[] = array(
            'id' => $msg['id'],
            'from_member_id' => $msg['from_member_id'],
            'message' => $msg['message'],
            'image_url' => $msg['image_url'],
            'image_deleted' => $msg['image_deleted'],
            'sent_at' => date('M j, g:i A', strtotime($msg['sent_at']))
        );
    }
    
    wp_send_json_success(array(
        'messages' => $formatted_messages,
        'count' => count($messages)
    ));
});

add_action('wp_ajax_nopriv_mmgr_admin_load_more_messages', function() {
    wp_send_json_error(array('message' => 'Permission denied'));
});

// AJAX: Get total unread private message count for the current portal member
add_action('wp_ajax_mmgr_get_unread_count', 'mmgr_ajax_get_unread_count');
add_action('wp_ajax_nopriv_mmgr_get_unread_count', 'mmgr_ajax_get_unread_count');

function mmgr_ajax_get_unread_count() {
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
        return;
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';

    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $messages_table
         WHERE to_member_id = %d AND read_at IS NULL AND deleted_by_receiver = 0",
        $member['id']
    ));

    wp_send_json_success(array('count' => $count));
}

// Combined nav stats: unread messages, received likes, upcoming events, award badges
add_action('wp_ajax_mmgr_get_nav_stats', 'mmgr_ajax_get_nav_stats');
add_action('wp_ajax_nopriv_mmgr_get_nav_stats', 'mmgr_ajax_get_nav_stats');

function mmgr_ajax_get_nav_stats() {
    $member = mmgr_get_current_member();
    if ( ! $member ) {
        wp_send_json_error( array( 'message' => 'Not logged in' ) );
        return;
    }

    global $wpdb;
    $mid = (int) $member['id'];

    // Unread messages
    $messages_table = $wpdb->prefix . 'membership_messages';
    $unread_messages = 0;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $messages_table ) . "'" ) === $messages_table ) {
        $unread_messages = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql( $messages_table ) . "`
             WHERE to_member_id = %d AND read_at IS NULL AND deleted_by_receiver = 0",
            $mid
        ) );
    }

    // Total likes received
    $likes_count = function_exists( 'mmgr_count_received_likes' )
        ? mmgr_count_received_likes( $mid )
        : 0;

    // Upcoming events count
    $events_table   = $wpdb->prefix . 'membership_events';
    $upcoming_count = 0;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $events_table ) . "'" ) === $events_table ) {
        $upcoming_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `" . esc_sql( $events_table ) . "` WHERE active = 1 AND event_date >= CURDATE()"
        );
    }

    // Award badges HTML
    $awards_html = function_exists( 'mmgr_render_member_award_badges' )
        ? mmgr_render_member_award_badges( $mid )
        : '';

    wp_send_json_success( array(
        'messages' => $unread_messages,
        'likes'    => $likes_count,
        'events'   => $upcoming_count,
        'awards'   => $awards_html,
    ) );
}

/**
 * AJAX: Staff marks orientation walkthrough complete for a first-time member.
 */
add_action('wp_ajax_mmgr_checkin_orientation', 'mmgr_ajax_checkin_orientation');
add_action('wp_ajax_nopriv_mmgr_checkin_orientation', 'mmgr_ajax_checkin_orientation');

function mmgr_ajax_checkin_orientation() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ( ! $member_id ) {
        wp_send_json_error( array( 'message' => 'Invalid member ID' ) );
        return;
    }

    $wpdb->update( $tbl, array( 'orientation_done' => 1 ), array( 'id' => $member_id ) );
    wp_send_json_success( array( 'message' => 'Orientation confirmed.' ) );
}

/**
 * AJAX: Staff marks ID verified for a first-time member.
 */
add_action('wp_ajax_mmgr_checkin_id_verified', 'mmgr_ajax_checkin_id_verified');
add_action('wp_ajax_nopriv_mmgr_checkin_id_verified', 'mmgr_ajax_checkin_id_verified');

function mmgr_ajax_checkin_id_verified() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    if ( ! $member_id ) {
        wp_send_json_error( array( 'message' => 'Invalid member ID' ) );
        return;
    }

    $wpdb->update( $tbl, array( 'id_verified' => 1 ), array( 'id' => $member_id ) );
    wp_send_json_success( array( 'message' => 'ID verified.' ) );
}

/**
 * AJAX: Staff captures/retakes a member profile photo from the check-in device camera.
 * Accepts a base64-encoded image data URI in $_POST['photo_data'].
 */
add_action('wp_ajax_mmgr_checkin_save_photo', 'mmgr_ajax_checkin_save_photo');
add_action('wp_ajax_nopriv_mmgr_checkin_save_photo', 'mmgr_ajax_checkin_save_photo');

function mmgr_ajax_checkin_save_photo() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';

    $member_id  = isset( $_POST['member_id'] )  ? intval( $_POST['member_id'] )           : 0;
    $photo_data = isset( $_POST['photo_data'] ) ? sanitize_text_field( $_POST['photo_data'] ) : '';

    if ( ! $member_id ) {
        wp_send_json_error( array( 'message' => 'Invalid member ID' ) );
        return;
    }

    // Validate data URI — must be a JPEG or PNG image
    if ( ! preg_match( '/^data:image\/(jpeg|png|webp);base64,/', $photo_data, $matches ) ) {
        wp_send_json_error( array( 'message' => 'Invalid image data.' ) );
        return;
    }

    $ext       = ( $matches[1] === 'png' ) ? 'png' : ( ( $matches[1] === 'webp' ) ? 'webp' : 'jpg' );
    $base64    = preg_replace( '/^data:image\/[a-z]+;base64,/', '', $photo_data );
    $image_bin = base64_decode( $base64 );

    if ( $image_bin === false || strlen( $image_bin ) < 100 ) {
        wp_send_json_error( array( 'message' => 'Could not decode image data.' ) );
        return;
    }

    // Write to a WordPress temp file first so we can validate the actual image content
    $tmp_path = wp_tempnam( 'mmgr-checkin-photo' );
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    if ( file_put_contents( $tmp_path, $image_bin ) === false ) {
        wp_send_json_error( array( 'message' => 'Could not write temporary image file.' ) );
        return;
    }

    // Validate that the decoded bytes are actually a real image (prevents disguised file uploads)
    $image_info = @getimagesize( $tmp_path );
    if ( $image_info === false ) {
        @unlink( $tmp_path );
        wp_send_json_error( array( 'message' => 'Uploaded data is not a valid image.' ) );
        return;
    }
    $allowed_mime_types = array( 'image/jpeg', 'image/png', 'image/webp' );
    if ( ! in_array( $image_info['mime'], $allowed_mime_types, true ) ) {
        @unlink( $tmp_path );
        wp_send_json_error( array( 'message' => 'Only JPEG, PNG and WebP images are allowed.' ) );
        return;
    }

    // Move the validated temp file into the WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $filename   = 'member-' . $member_id . '-checkin-' . time() . '.' . $ext;
    $filepath   = trailingslashit( $upload_dir['path'] ) . $filename;
    $file_url   = trailingslashit( $upload_dir['url'] ) . $filename;

    if ( ! rename( $tmp_path, $filepath ) ) {
        @unlink( $tmp_path );
        wp_send_json_error( array( 'message' => 'Could not save image file.' ) );
        return;
    }

    $wpdb->update( $tbl, array( 'photo_url' => $file_url ), array( 'id' => $member_id ) );
    wp_send_json_success( array( 'photo_url' => $file_url, 'message' => 'Photo saved.' ) );
}

/**
 * AJAX: Staff checks off one orientation item for a member.
 * Automatically marks orientation_done on the member record when all items are complete.
 */
add_action('wp_ajax_mmgr_checkin_complete_item', 'mmgr_ajax_checkin_complete_item');
add_action('wp_ajax_nopriv_mmgr_checkin_complete_item', 'mmgr_ajax_checkin_complete_item');

function mmgr_ajax_checkin_complete_item() {
    global $wpdb;
    $items_tbl   = $wpdb->prefix . 'membership_orientation_items';
    $comp_tbl    = $wpdb->prefix . 'membership_orientation_completions';
    $members_tbl = $wpdb->prefix . 'memberships';

    $member_id = isset( $_POST['member_id'] ) ? intval( $_POST['member_id'] ) : 0;
    $item_id   = isset( $_POST['item_id'] )   ? intval( $_POST['item_id'] )   : 0;

    if ( ! $member_id || ! $item_id ) {
        wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        return;
    }

    // Verify item is active
    $item = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `$items_tbl` WHERE id = %d AND active = 1",
        $item_id
    ) );
    if ( ! $item ) {
        wp_send_json_error( array( 'message' => 'Orientation item not found.' ) );
        return;
    }

    // Record completion (IGNORE duplicates in case of double-tap)
    $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO `$comp_tbl` (member_id, item_id, completed_at) VALUES (%d, %d, %s)",
        $member_id, $item_id, current_time('mysql')
    ) );

    // Count remaining active items not yet completed by this member
    $remaining = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM `$items_tbl` oi
         WHERE oi.active = 1
           AND NOT EXISTS (
             SELECT 1 FROM `$comp_tbl` oc
             WHERE oc.member_id = %d AND oc.item_id = oi.id
           )",
        $member_id
    ) );

    // When all items are done, set orientation_done flag on member record
    if ( $remaining === 0 ) {
        $wpdb->update( $members_tbl, array( 'orientation_done' => 1 ), array( 'id' => $member_id ) );
    }

    wp_send_json_success( array(
        'remaining' => $remaining,
        'all_done'  => ( $remaining === 0 ),
    ) );
}

/**
 * AJAX: Staff records membership fee payment at check-in.
 * Sets paid=1, records payment method + amount, sets expire_date to +1 year.
 */
add_action('wp_ajax_mmgr_checkin_collect_fee', 'mmgr_ajax_checkin_collect_fee');
add_action('wp_ajax_nopriv_mmgr_checkin_collect_fee', 'mmgr_ajax_checkin_collect_fee');

function mmgr_ajax_checkin_collect_fee() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';

    $member_id      = isset( $_POST['member_id'] )      ? intval( $_POST['member_id'] )              : 0;
    $payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : 'cash';
    $payment_amount = isset( $_POST['payment_amount'] ) ? floatval( $_POST['payment_amount'] )        : 0.0;

    if ( ! $member_id ) {
        wp_send_json_error( array( 'message' => 'Invalid member ID.' ) );
        return;
    }

    $new_expire = date( 'Y-m-d', strtotime( '+1 year' ) );

    $wpdb->update( $tbl, array(
        'paid'           => 1,
        'payment_date'   => current_time('mysql'),
        'payment_method' => $payment_method,
        'payment_amount' => $payment_amount,
        'expire_date'    => $new_expire,
    ), array( 'id' => $member_id ) );

    wp_send_json_success( array(
        'message'     => 'Membership fee recorded.',
        'expire_date' => date( 'M d, Y', strtotime( $new_expire ) ),
    ) );
}
// ============================================================
// STAFF SYSTEM AJAX HANDLERS
// ============================================================

/**
 * Helper: round minutes to nearest 15-minute interval.
 */
function mmgr_round_minutes_15($minutes) {
    return (int) (round($minutes / 15) * 15);
}

/**
 * Helper: format a decimal-minutes value as "Xh Ym".
 */
function mmgr_format_hours($minutes) {
    $minutes = (int) $minutes;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0 && $m > 0) return $h . 'h ' . $m . 'm';
    if ($h > 0)            return $h . 'h';
    return $m . 'm';
}

/**
 * Get total unpaid minutes for a staff member (rounded to 15 min per entry).
 */
function mmgr_staff_get_unpaid_minutes($staff_id) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'membership_staff_time_logs';
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT clock_in, clock_out FROM `$tbl` WHERE staff_id = %d AND paid = 0 AND clock_out IS NOT NULL",
        $staff_id
    ), ARRAY_A);

    $total = 0;
    foreach ($logs as $log) {
        $diff    = (strtotime($log['clock_out']) - strtotime($log['clock_in'])) / 60;
        $total  += mmgr_round_minutes_15($diff);
    }
    return $total;
}

// ── mmgr_staff_scan ──────────────────────────────────────────────────────────
add_action('wp_ajax_mmgr_staff_scan',        'mmgr_handle_staff_scan');
add_action('wp_ajax_nopriv_mmgr_staff_scan', 'mmgr_handle_staff_scan');

function mmgr_handle_staff_scan() {
    check_ajax_referer('mmgr_staff_scan', 'nonce');
    global $wpdb;

    $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    if (empty($code)) {
        wp_send_json_error(array('message' => 'No code provided.'));
    }

    $staff_tbl = $wpdb->prefix . 'membership_staff';
    $staff = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$staff_tbl` WHERE staff_code = %s AND active = 1",
        $code
    ), ARRAY_A);

    if (!$staff) {
        wp_send_json_error(array('message' => '❌ Staff member not found. Code: ' . esc_html($code)));
    }

    // Is the staff member currently clocked in (has an open log entry)?
    $time_tbl   = $wpdb->prefix . 'membership_staff_time_logs';
    $open_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM `$time_tbl` WHERE staff_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
        $staff['id']
    ), ARRAY_A);

    $is_clocked_in = !empty($open_entry);

    // Unpaid hours for current pay period
    $unpaid_minutes = mmgr_staff_get_unpaid_minutes($staff['id']);
    $hours_text     = mmgr_format_hours($unpaid_minutes);

    wp_send_json_success(array(
        'staff'           => array(
            'id'       => (int) $staff['id'],
            'name'     => $staff['name'],
            'position' => $staff['position'],
        ),
        'is_clocked_in'   => $is_clocked_in,
        'hours_this_period' => $hours_text,
    ));
}

// ── mmgr_staff_clock_in ──────────────────────────────────────────────────────
add_action('wp_ajax_mmgr_staff_clock_in',        'mmgr_handle_staff_clock_in');
add_action('wp_ajax_nopriv_mmgr_staff_clock_in', 'mmgr_handle_staff_clock_in');

function mmgr_handle_staff_clock_in() {
    check_ajax_referer('mmgr_staff_clock_in', 'nonce');
    global $wpdb;

    $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
    if (!$staff_id) {
        wp_send_json_error(array('message' => 'Invalid staff ID.'));
    }

    $staff_tbl = $wpdb->prefix . 'membership_staff';
    $staff = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM `$staff_tbl` WHERE id = %d AND active = 1", $staff_id), ARRAY_A);
    if (!$staff) {
        wp_send_json_error(array('message' => 'Staff member not found.'));
    }

    // Prevent double clock-in
    $time_tbl   = $wpdb->prefix . 'membership_staff_time_logs';
    $open_entry = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `$time_tbl` WHERE staff_id = %d AND clock_out IS NULL",
        $staff_id
    ));
    if ($open_entry) {
        wp_send_json_error(array('message' => esc_html($staff['name']) . ' is already clocked in.'));
    }

    $wpdb->insert($time_tbl, array(
        'staff_id' => $staff_id,
        'clock_in' => current_time('mysql'),
    ));

    wp_send_json_success(array('message' => esc_html($staff['name']) . ' clocked in at ' . date('g:i A', current_time('timestamp'))));
}

// ── mmgr_staff_clock_out ─────────────────────────────────────────────────────
add_action('wp_ajax_mmgr_staff_clock_out',        'mmgr_handle_staff_clock_out');
add_action('wp_ajax_nopriv_mmgr_staff_clock_out', 'mmgr_handle_staff_clock_out');

function mmgr_handle_staff_clock_out() {
    check_ajax_referer('mmgr_staff_clock_out', 'nonce');
    global $wpdb;

    $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
    if (!$staff_id) {
        wp_send_json_error(array('message' => 'Invalid staff ID.'));
    }

    $staff_tbl = $wpdb->prefix . 'membership_staff';
    $staff = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM `$staff_tbl` WHERE id = %d AND active = 1", $staff_id), ARRAY_A);
    if (!$staff) {
        wp_send_json_error(array('message' => 'Staff member not found.'));
    }

    $time_tbl   = $wpdb->prefix . 'membership_staff_time_logs';
    $open_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT id, clock_in FROM `$time_tbl` WHERE staff_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
        $staff_id
    ), ARRAY_A);

    if (!$open_entry) {
        wp_send_json_error(array('message' => esc_html($staff['name']) . ' is not currently clocked in.'));
    }

    $clock_out_time = current_time('mysql');
    $wpdb->update($time_tbl, array('clock_out' => $clock_out_time), array('id' => $open_entry['id']));

    // Calculate duration for this session (rounded)
    $diff    = (strtotime($clock_out_time) - strtotime($open_entry['clock_in'])) / 60;
    $rounded = mmgr_round_minutes_15($diff);

    wp_send_json_success(array(
        'message' => esc_html($staff['name']) . ' clocked out at ' . date('g:i A', current_time('timestamp')) . '. Session: ' . mmgr_format_hours($rounded),
    ));
}

// ── mmgr_staff_get_rooms ─────────────────────────────────────────────────────
add_action('wp_ajax_mmgr_staff_get_rooms',        'mmgr_handle_staff_get_rooms');
add_action('wp_ajax_nopriv_mmgr_staff_get_rooms', 'mmgr_handle_staff_get_rooms');

function mmgr_handle_staff_get_rooms() {    check_ajax_referer('mmgr_staff_get_rooms', 'nonce');
    global $wpdb;

    $rooms_tbl    = $wpdb->prefix . 'membership_rooms';
    $cleaning_tbl = $wpdb->prefix . 'membership_cleaning_log';
    $staff_tbl    = $wpdb->prefix . 'membership_staff';

    $rooms = $wpdb->get_results("
        SELECT r.id, r.room_name,
               cl.cleaned_at AS last_cleaned_at,
               s.name        AS last_cleaned_by
        FROM `$rooms_tbl` r
        LEFT JOIN (
            SELECT room_id, MAX(id) AS last_id
            FROM `$cleaning_tbl`
            GROUP BY room_id
        ) latest ON latest.room_id = r.id
        LEFT JOIN `$cleaning_tbl` cl ON cl.id = latest.last_id
        LEFT JOIN `$staff_tbl` s ON s.id = cl.staff_id
        WHERE r.active = 1
        ORDER BY r.sort_order ASC, r.id ASC
    ", ARRAY_A);

    wp_send_json_success(array('rooms' => $rooms ?: array()));
}

// ── mmgr_staff_log_cleaning ──────────────────────────────────────────────────
add_action('wp_ajax_mmgr_staff_log_cleaning',        'mmgr_handle_staff_log_cleaning');
add_action('wp_ajax_nopriv_mmgr_staff_log_cleaning', 'mmgr_handle_staff_log_cleaning');

function mmgr_handle_staff_log_cleaning() {
    check_ajax_referer('mmgr_staff_log_cleaning', 'nonce');
    global $wpdb;

    $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
    $room_id  = isset($_POST['room_id'])  ? intval($_POST['room_id'])  : 0;

    if (!$staff_id || !$room_id) {
        wp_send_json_error(array('message' => 'Invalid staff or room ID.'));
    }

    $cleaning_tbl = $wpdb->prefix . 'membership_cleaning_log';
    $wpdb->insert($cleaning_tbl, array(
        'staff_id'   => $staff_id,
        'room_id'    => $room_id,
        'cleaned_at' => current_time('mysql'),
    ));

    wp_send_json_success(array('message' => 'Room cleaning logged.'));
}

// ── Chemistry: Save member answers ──────────────────────────────────────────
add_action('wp_ajax_nopriv_mmgr_save_chemistry_answers', function() { do_action('wp_ajax_mmgr_save_chemistry_answers'); });
add_action('wp_ajax_mmgr_save_chemistry_answers', function() {
    check_ajax_referer('mmgr_chemistry', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in.'));
    }

    global $wpdb;
    $answers_tbl   = $wpdb->prefix . 'membership_chemistry_answers';
    $questions_tbl = $wpdb->prefix . 'membership_chemistry_questions';
    $members_tbl   = $wpdb->prefix . 'memberships';

    if ($wpdb->get_var("SHOW TABLES LIKE '$answers_tbl'") !== $answers_tbl) {
        wp_send_json_error(array('message' => 'Chemistry tables not found.'));
    }

    // Privacy
    $privacy = isset($_POST['privacy']) ? sanitize_text_field($_POST['privacy']) : 'everyone';
    if (!in_array($privacy, array('everyone', 'nobody'), true)) {
        $privacy = 'everyone';
    }

    // Answers JSON
    $answers_raw = isset($_POST['answers']) ? wp_unslash($_POST['answers']) : '{}';
    $answers = json_decode($answers_raw, true);
    if (!is_array($answers)) {
        wp_send_json_error(array('message' => 'Invalid answer data.'));
    }

    // Build a whitelist of active question IDs to prevent arbitrary inserts
    $active_ids = $wpdb->get_col("SELECT id FROM `$questions_tbl` WHERE active = 1");
    $active_ids = array_map('intval', $active_ids);

    foreach ($answers as $q_id_raw => $value_raw) {
        $q_id = intval($q_id_raw);
        $val  = max(0, min(100, intval($value_raw)));
        if (!$q_id || !in_array($q_id, $active_ids, true)) {
            continue;
        }
        $wpdb->replace($answers_tbl, array(
            'member_id'    => (int) $member['id'],
            'question_id'  => $q_id,
            'answer_value' => $val,
        ));
    }

    // Save privacy setting
    $wpdb->update($members_tbl, array('chemistry_privacy' => $privacy), array('id' => (int) $member['id']));

    wp_send_json_success(array('message' => 'Chemistry profile saved!'));
});

/**
 * AJAX: Save member sexual orientation selections.
 */
add_action('wp_ajax_mmgr_save_orientations', function() {
    global $wpdb;
    if (!check_ajax_referer('mmgr_save_orientations', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in.'));
    }

    $opts_tbl = $wpdb->prefix . 'membership_sexual_orientations';
    $sel_tbl  = $wpdb->prefix . 'membership_member_orientations';

    $raw_ids = isset($_POST['orientation_ids']) ? $_POST['orientation_ids'] : array();
    if (!is_array($raw_ids)) {
        $raw_ids = array();
    }
    $selected_ids = array_map('intval', $raw_ids);

    // Validate against active orientation IDs
    $active_ids = $wpdb->get_col("SELECT id FROM `$opts_tbl` WHERE active = 1");
    $active_ids = array_map('intval', $active_ids);
    $valid_ids  = array_intersect($selected_ids, $active_ids);

    // Replace selections: delete existing, insert valid
    $wpdb->delete($sel_tbl, array('member_id' => (int) $member['id']));
    foreach ($valid_ids as $oid) {
        $wpdb->replace($sel_tbl, array(
            'member_id'      => (int) $member['id'],
            'orientation_id' => $oid,
        ));
    }

    wp_send_json_success(array('message' => 'Orientation saved!'));
});

/**
 * AJAX: Dismiss a chemistry match.
 */
add_action('wp_ajax_mmgr_dismiss_chemistry_match', function() {
    global $wpdb;
    if (!check_ajax_referer('mmgr_dismiss_match', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in.'));
    }

    $dismiss_member_id = intval($_POST['dismissed_member_id'] ?? 0);
    if ($dismiss_member_id <= 0 || $dismiss_member_id === (int) $member['id']) {
        wp_send_json_error(array('message' => 'Invalid member ID.'));
    }

    // Verify the dismissed member actually exists and is active
    $members_tbl = $wpdb->prefix . 'memberships';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `$members_tbl` WHERE id = %d AND active = 1",
        $dismiss_member_id
    ));
    if (!$exists) {
        wp_send_json_error(array('message' => 'Member not found.'));
    }

    $dismiss_tbl = $wpdb->prefix . 'membership_dismissed_matches';
    $wpdb->replace($dismiss_tbl, array(
        'member_id'          => (int) $member['id'],
        'dismissed_member_id' => $dismiss_member_id,
    ));

    wp_send_json_success(array('message' => 'Match dismissed.'));
});
