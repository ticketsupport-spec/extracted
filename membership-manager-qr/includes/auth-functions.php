<?php
if (!defined('ABSPATH')) exit;

/**
 * Check if a member is logged in
 */
if (!function_exists('mmgr_is_member_logged_in')) {
    function mmgr_is_member_logged_in() {
        if (!isset($_COOKIE['mmgr_session'])) {
            return false;
        }
        
        $token = sanitize_text_field($_COOKIE['mmgr_session']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_token = %s AND session_expires > NOW()",
            $token
        ), ARRAY_A);
        
        return $member ? true : false;
    }
}

/**
 * Get current logged-in member
 */
if (!function_exists('mmgr_get_current_member')) {
    function mmgr_get_current_member($force_refresh = false) {
        static $cached_member = null;
        
        if ($cached_member !== null && !$force_refresh) {
            return $cached_member;
        }
        
        if (!isset($_COOKIE['mmgr_session'])) {
            $cached_member = false;
            return false;
        }
        
        $token = sanitize_text_field($_COOKIE['mmgr_session']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE session_token = %s AND session_expires > NOW()",
            $token
        ), ARRAY_A);
        
        $cached_member = $member ? $member : false;
        return $cached_member;
    }
}

/**
 * Create member session
 */
if (!function_exists('mmgr_create_member_session')) {
    function mmgr_create_member_session($member_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Update member record
        $wpdb->update(
            $table,
            array(
                'session_token' => $token,
                'session_expires' => $expires
            ),
            array('id' => $member_id)
        );
        
        // Set cookie
        setcookie('mmgr_session', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        
        return true;
    }
}

/**
 * Logout member
 */
if (!function_exists('mmgr_logout_member')) {
    function mmgr_logout_member() {
        if (isset($_COOKIE['mmgr_session'])) {
            $token = sanitize_text_field($_COOKIE['mmgr_session']);
            
            // Clear session from database
            global $wpdb;
            $table = $wpdb->prefix . 'memberships';
            
            $wpdb->update(
                $table,
                array(
                    'session_token' => null,
                    'session_expires' => null
                ),
                array('session_token' => $token)
            );
            
            // Clear cookie
            setcookie('mmgr_session', '', time() - 3600, '/', '', true, true);
            unset($_COOKIE['mmgr_session']);
        }
    }
}

/**
 * Verify member login credentials
 */
if (!function_exists('mmgr_verify_member_login')) {
    function mmgr_verify_member_login($email, $password) {
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ), ARRAY_A);
        
        if (!$member) {
            return false;
        }
        
        // Check if password is set
        if (empty($member['password_hash'])) {
            return array('error' => 'no_password');
        }
        
        // Verify password
        if (password_verify($password, $member['password_hash'])) {
            return $member;
        }
        
        return false;
    }
}

/**
 * Set member password
 */
if (!function_exists('mmgr_set_member_password')) {
    function mmgr_set_member_password($member_id, $password) {
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $wpdb->update(
            $table,
            array('password_hash' => $hash),
            array('id' => $member_id)
        );
    }
}

/**
 * Generate portal token for password setup
 */
if (!function_exists('mmgr_generate_portal_token')) {
    function mmgr_generate_portal_token($member_id) {
        $token = bin2hex(random_bytes(32));
        set_transient('mmgr_portal_token_' . $member_id, $token, 7 * DAY_IN_SECONDS);
        return $token;
    }
}

/**
 * Verify portal token
 */
if (!function_exists('mmgr_verify_portal_token')) {
    function mmgr_verify_portal_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'memberships';
        
        $members = $wpdb->get_results("SELECT id FROM $table", ARRAY_A);
        
        foreach ($members as $member) {
            $stored_token = get_transient('mmgr_portal_token_' . $member['id']);
            if ($stored_token === $token) {
                return $member['id'];
            }
        }
        
        return false;
    }
}