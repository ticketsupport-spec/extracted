<?php
if (!defined('ABSPATH')) exit;

// QR Code Generation
add_action('wp_ajax_mmgr_qrcode', 'mmgr_qrcode_handler');
function mmgr_qrcode_handler() {
    global $wpdb;
    $tbl = $wpdb->prefix.'memberships';
    if (!isset($_GET['code'])) wp_die('No member');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE member_code=%s", $_GET['code']), ARRAY_A);
    if (!$row) wp_die('Not found');
    require_once MMGR_PLUGIN_DIR . 'phpqrcode.php';
    header('Content-type: image/png');
    QRcode::png($row['member_code'], null, QR_ECLEVEL_L, 5, 1);
    exit;
}

// Photo Upload
add_action('wp_ajax_mmgr_upload_photo', 'mmgr_upload_photo_handler');
add_action('wp_ajax_nopriv_mmgr_upload_photo', 'mmgr_upload_photo_handler');
function mmgr_upload_photo_handler() {
    if (!isset($_FILES['photo'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $upload = wp_handle_upload($_FILES['photo'], array('test_form' => false));
    
    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
    }
    
    if (!isset($upload['file'])) {
        wp_send_json_error('Upload failed');
    }
    
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name($_FILES['photo']['name']),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    
    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    
    if (!is_wp_error($attach_id)) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        $image_url = wp_get_attachment_url($attach_id);
        wp_send_json_success(array('url' => $image_url, 'id' => $attach_id));
    } else {
        wp_send_json_error('Failed to save image');
    }
}

// Member Lookup for Check-in
add_action('wp_ajax_mmgr_lookup', 'mmgr_lookup_handler');
add_action('wp_ajax_nopriv_mmgr_lookup', 'mmgr_lookup_handler');
function mmgr_lookup_handler() {
    global $wpdb;
    $tbl = $wpdb->prefix.'memberships';
    $visits_tbl = $wpdb->prefix.'membership_visits';
    $code = isset($_GET['code']) ? $_GET['code'] : '';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE member_code=%s", $code), ARRAY_A);
    
    if (!$row) {
        wp_send_json(array('status'=>'fail'));
    }
    
    if ($row['banned']) {
        wp_send_json(array('status'=>'banned','member'=>array(
            'banned_reason'=> $row['banned_reason'],
            'banned_on'=> $row['banned_on']
        )));
    }
    
    $member_id = $row['id'];
    $now = current_time('mysql');
    $wpdb->update($tbl, array('last_visited'=>$now), array('id'=>$member_id));
    $wpdb->insert($visits_tbl, array('member_id'=>$member_id, 'visit_time'=>$now));
    $row['last_visited'] = $now;
    
    wp_send_json(array('status'=>'ok','member'=>$row));
}
?>