<?php
if (!defined('ABSPATH')) exit;

/**
 * Generate QR code using PHP (no external API needed)
 */
function mmgr_generate_qr_code($member_code) {
    if (empty($member_code)) {
        return false;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $qr_dir = $upload_dir['basedir'] . '/membership-qr-codes';
    
    if (!file_exists($qr_dir)) {
        wp_mkdir_p($qr_dir);
    }
    
    $qr_file = $qr_dir . '/qr-' . $member_code . '.png';
    $qr_url = $upload_dir['baseurl'] . '/membership-qr-codes/qr-' . $member_code . '.png';
    
    // If file already exists, return the URL
    if (file_exists($qr_file)) {
        return $qr_url;
    }
    
    // Use alternative QR API: qrcode.tec-it.com (free, no key needed)
    $qr_data = urlencode($member_code);
    $qr_image_url = "https://qrcode.tec-it.com/API/QRCode?data={$qr_data}&size=medium&errorcorrection=M";
    
    // Download and save the QR code using WordPress HTTP API
    $response = wp_remote_get($qr_image_url, array(
        'timeout' => 15,
        'sslverify' => true
    ));
    
    if (is_wp_error($response)) {
        error_log('QR Code generation failed for ' . $member_code . ': ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log('QR Code generation failed for ' . $member_code . ': HTTP ' . $response_code);
        return false;
    }
    
    $qr_image_data = wp_remote_retrieve_body($response);
    
    if (empty($qr_image_data)) {
        error_log('QR Code generation failed for ' . $member_code . ': Empty response body');
        return false;
    }
    
    // Save the image
    $result = file_put_contents($qr_file, $qr_image_data);
    
    if ($result === false) {
        error_log('QR Code save failed for ' . $member_code . ': Cannot write to ' . $qr_file);
        return false;
    }
    
    return $qr_url;
}

/**
 * Regenerate QR code for a member (force regeneration)
 */
function mmgr_regenerate_qr_code($member_code) {
    if (empty($member_code)) {
        return false;
    }
    
    // Delete existing QR code
    $upload_dir = wp_upload_dir();
    $qr_file = $upload_dir['basedir'] . '/membership-qr-codes/qr-' . $member_code . '.png';
    
    if (file_exists($qr_file)) {
        @unlink($qr_file);
    }
    
    // Generate new one
    return mmgr_generate_qr_code($member_code);
}