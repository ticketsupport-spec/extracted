<?php
/**
 * Plugin Name: Membership Manager with QR Codes
 * Description: Complete membership management system with QR code check-ins
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('MMGR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMGR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMGR_PLUGIN_FILE', __FILE__);

// Load core files immediately
require_once MMGR_PLUGIN_DIR . 'includes/database.php';
require_once MMGR_PLUGIN_DIR . 'includes/member-portal-functions.php';
require_once MMGR_PLUGIN_DIR . 'includes/messaging-functions.php';
require_once MMGR_PLUGIN_DIR . 'includes/email-functions.php';
require_once MMGR_PLUGIN_DIR . 'includes/admin-messages.php';  // MUST be before admin-menu.php

// Load UI/functionality files on plugins_loaded
add_action('plugins_loaded', function() {
    require_once MMGR_PLUGIN_DIR . 'includes/ajax-handlers.php';
    require_once MMGR_PLUGIN_DIR . 'includes/shortcodes.php';
    require_once MMGR_PLUGIN_DIR . 'includes/admin-menu.php';  // This needs the function to exist first
    require_once MMGR_PLUGIN_DIR . 'includes/styles.php';
    require_once MMGR_PLUGIN_DIR . 'includes/member-portal-shortcodes.php';
});

// Register activation hooks
register_activation_hook(__FILE__, 'mmgr_create_tables');
register_activation_hook(__FILE__, 'mmgr_create_plugin_pages');

/**
 * Check database version and upgrade if needed
 */
function mmgr_check_database_upgrade() {
    $current_version = get_option('mmgr_db_version', '0');
    $plugin_version = '1.0.0';
    
    if (version_compare($current_version, $plugin_version, '<')) {
        mmgr_create_tables();
    }
}
add_action('admin_init', 'mmgr_check_database_upgrade');

/**
 * Create plugin pages with shortcodes
 */
function mmgr_create_plugin_pages() {
    $pages = array(
        array(
            'title' => 'Member Registration',
            'slug' => 'member-registration',
            'content' => '[membership_registration]',
            'option' => 'mmgr_page_registration'
        ),
		array(
			'title' => 'Members Directory',
			'slug' => 'members-directory',
			'content' => '[mmgr_members_directory]',
			'option' => 'mmgr_page_directory'
		),		
        array(
            'title' => 'Member Check-In',
            'slug' => 'member-checkin',
            'content' => '[membership_checkin]',
            'option' => 'mmgr_page_checkin'
        ),
        array(
            'title' => 'Code of Conduct',
            'slug' => 'code-of-conduct',
            'content' => '[membership_code_of_conduct]',
            'option' => 'mmgr_page_coc'
        ),
        array(
            'title' => 'Member Setup',
            'slug' => 'member-setup',
            'content' => '[mmgr_password_setup]',
            'option' => 'mmgr_page_setup'
        ),
        array(
            'title' => 'Member Login',
            'slug' => 'member-login',
            'content' => '[mmgr_member_login]',
            'option' => 'mmgr_page_login'
        ),
        array(
            'title' => 'Member Dashboard',
            'slug' => 'member-dashboard',
            'content' => '[mmgr_member_dashboard]',
            'option' => 'mmgr_page_dashboard'
        ),
        array(
            'title' => 'Member Activity',
            'slug' => 'member-activity',
            'content' => '[mmgr_member_activity]',
            'option' => 'mmgr_page_activity'
        ),
        array(
            'title' => 'Member Profile',
            'slug' => 'member-profile',
            'content' => '[mmgr_member_profile]',
            'option' => 'mmgr_page_profile'
        ),
        array(
            'title' => 'Member Community',
            'slug' => 'member-community',
            'content' => '[mmgr_member_community]',
            'option' => 'mmgr_page_community'
        ),
        array(
            'title' => 'Members Directory',
            'slug' => 'members-directory',
            'content' => '[mmgr_members_directory]',
            'option' => 'mmgr_page_directory'
        ),
        array(
            'title' => 'Community Profile',
            'slug' => 'member-community-profile',
            'content' => '[mmgr_member_community_profile]',
            'option' => 'mmgr_page_community_profile'
        )
    );
    
    foreach ($pages as $page_data) {
        $existing_id = get_option($page_data['option']);
        
        if ($existing_id && get_post($existing_id) && get_post_status($existing_id) !== 'trash') {
            continue;
        }
        
        $page_id = wp_insert_post(array(
            'post_title' => $page_data['title'],
            'post_name' => $page_data['slug'],
            'post_content' => $page_data['content'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ));
        
        if ($page_id && !is_wp_error($page_id)) {
            update_option($page_data['option'], $page_id);
        }
    }
    
    update_option('mmgr_checkin_page_url', '/member-checkin');
    update_option('mmgr_registration_page_url', '/member-registration');
    update_option('mmgr_pages_created', '1');
}

// Helper Functions
function mmgr_generate_member_code($name) {
    return strtoupper(substr(md5($name . time() . rand()), 0, 12));
}

function mmgr_validate_age($dob) {
    $today = new DateTime();
    $birthdate = new DateTime($dob);
    $age = $today->diff($birthdate)->y;
    return $age >= 18;
}

function mmgr_get_daily_fee($level) {
    global $wpdb;
    $levels_tbl = $wpdb->prefix . "membership_levels";
    $fee = $wpdb->get_var($wpdb->prepare("SELECT daily_fee FROM $levels_tbl WHERE level_name = %s", $level));
    return $fee ? floatval($fee) : 5.00;
}

function mmgr_get_affiliated_accounts($current_id, $phone, $email) {
    global $wpdb;
    $tbl = $wpdb->prefix . "memberships";
    
    $affiliated = array();
    
    if (!empty($phone) && $phone !== 'PENDING') {
        $phone_matches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, member_code, name, level, phone, email, expire_date FROM $tbl WHERE phone = %s AND id != %d",
            $phone, $current_id
        ), ARRAY_A);
        
        foreach ($phone_matches as $match) {
            $match['match_type'] = 'phone';
            $affiliated[] = $match;
        }
    }
    
    if (!empty($email) && $email !== 'pending@example.com') {
        $email_matches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, member_code, name, level, phone, email, expire_date FROM $tbl WHERE email = %s AND id != %d",
            $email, $current_id
        ), ARRAY_A);
        
        foreach ($email_matches as $match) {
            $already_added = false;
            foreach ($affiliated as $aff) {
                if ($aff['id'] == $match['id']) {
                    $already_added = true;
                    break;
                }
            }
            
            if (!$already_added) {
                $match['match_type'] = 'email';
                $affiliated[] = $match;
            }
        }
    }
    
    return $affiliated;
}

function mmgr_get_absolute_url($url) {
    if (empty($url)) {
        return '';
    }
    
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    
    if (strpos($url, '/') === 0) {
        return home_url($url);
    }
    
    return home_url('/' . $url);
}



// Newsletter functionality
function mmgr_newsletter_page() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    
    $subscribers = $wpdb->get_results("SELECT id, first_name, last_name, email, phone, level, created_at FROM $tbl WHERE newsletter = 1 ORDER BY created_at DESC", ARRAY_A);
    $total = count($subscribers);
    
    echo '<div class="wrap">';
    echo '<h1>Newsletter Subscribers</h1>';
    echo '<p>Total subscribers: <strong>' . $total . '</strong></p>';
    echo '<p><a href="' . admin_url('admin.php?mmgr_export_newsletter=1') . '" class="button button-primary">📥 Export to CSV</a></p>';
    
    if ($total > 0) {
        echo '<table class="widefat"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Level</th><th>Subscribed</th></tr></thead><tbody>';
        foreach ($subscribers as $sub) {
            echo '<tr>';
            echo '<td>' . esc_html($sub['first_name'] . ' ' . $sub['last_name']) . '</td>';
            echo '<td>' . esc_html($sub['email']) . '</td>';
            echo '<td>' . esc_html($sub['phone']) . '</td>';
            echo '<td>' . esc_html($sub['level']) . '</td>';
            echo '<td>' . esc_html(date('M d, Y', strtotime($sub['created_at']))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No newsletter subscribers yet.</p>';
    }
    
    echo '</div>';
}

add_action('admin_init', function() {
    if (isset($_GET['mmgr_export_newsletter']) && current_user_can('manage_options')) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'memberships';
        $subscribers = $wpdb->get_results("SELECT email, first_name, last_name FROM $tbl WHERE newsletter = 1 ORDER BY email", ARRAY_A);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="newsletter-subscribers-'.date('Y-m-d').'.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Email', 'First Name', 'Last Name'));
        
        foreach ($subscribers as $sub) {
            fputcsv($output, array($sub['email'], $sub['first_name'], $sub['last_name']));
        }
        
        fclose($output);
        exit;
    }
});