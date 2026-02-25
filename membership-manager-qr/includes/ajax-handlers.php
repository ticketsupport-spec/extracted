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
        "SELECT fee_amount FROM {$wpdb->prefix}membership_fees WHERE fee_date = %s",
        $today
    ));
    
    if ($special_fee !== null) {
        $daily_fee = floatval($special_fee);
    }
    
    // Format dates safely
    $start_date = !empty($member['start_date']) ? date('M d, Y', strtotime($member['start_date'])) : 'N/A';
    $expire_date = !empty($member['expire_date']) ? date('M d, Y', strtotime($member['expire_date'])) : 'N/A';
    $last_visited = !empty($member['last_visited']) ? date('M d, Y g:i A', strtotime($member['last_visited'])) : 'Never';
    
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
            'is_expired' => $is_expired
        ),
        'daily_fee' => floatval($daily_fee)
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
    
    // Get member name
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT name FROM $tbl WHERE id = %d",
        $member_id
    ), ARRAY_A);
    
    if (!$member) {
        wp_send_json_error(array('message' => 'Member not found'));
        return;
    }
    
    // Add payment status to notes
    $payment_note = $paid ? 'PAID' : 'UNPAID';
    $full_notes = !empty($notes) ? $payment_note . ' - ' . $notes : $payment_note;
    
    // Record visit with payment status
    $visit_data = array(
        'member_id' => $member_id,
        'visit_time' => current_time('mysql'),
        'daily_fee' => $daily_fee,
        'notes' => $full_notes
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