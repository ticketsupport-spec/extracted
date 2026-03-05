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
define('MMGR_VERSION', get_file_data(__FILE__, ['Version' => 'Version'])['Version']);

// Load core files immediately
require_once MMGR_PLUGIN_DIR . 'includes/database.php';
require_once MMGR_PLUGIN_DIR . 'includes/qr-generator.php';
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
    require_once MMGR_PLUGIN_DIR . 'includes/pwa-functions.php';
});

// Register activation hooks
register_activation_hook(__FILE__, 'mmgr_create_tables');
register_activation_hook(__FILE__, 'mmgr_migrate_columns');
register_activation_hook(__FILE__, function() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/settings-page.php';
    mmgr_create_plugin_pages();
});

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

    // Ensure the newsletter column exists (in case migration hasn't run yet)
    if (!$wpdb->get_row("SHOW COLUMNS FROM `$tbl` LIKE 'newsletter'")) {
        $wpdb->query("ALTER TABLE `$tbl` ADD COLUMN `newsletter` TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!$wpdb->get_row("SHOW COLUMNS FROM `$tbl` LIKE 'created_at'")) {
        $wpdb->query("ALTER TABLE `$tbl` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    $subscribers = $wpdb->get_results(
        "SELECT id, first_name, last_name, email, phone, level, created_at
         FROM $tbl
         WHERE newsletter = 1
         ORDER BY created_at DESC",
        ARRAY_A
    );

    echo '<div class="wrap">';
    echo '<h1>Newsletter Subscribers</h1>';

    // Surface any database error to the admin
    if ($wpdb->last_error) {
        echo '<div class="notice notice-error"><p><strong>Database error:</strong> ' . esc_html($wpdb->last_error) . '</p></div>';
    }

    $total = is_array($subscribers) ? count($subscribers) : 0;

    echo '<p>Total subscribers: <strong>' . $total . '</strong></p>';
    echo '<p><a href="' . esc_url(wp_nonce_url(admin_url('admin.php?mmgr_export_newsletter=1'), 'mmgr_export_newsletter')) . '" class="button button-primary">📥 Export to CSV</a></p>';

    if ($total > 0) {
        echo '<table class="widefat"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Level</th><th>Subscribed</th></tr></thead><tbody>';
        foreach ($subscribers as $sub) {
            $subscribed = !empty($sub['created_at']) ? date_i18n('M d, Y', strtotime($sub['created_at'])) : '—';
            echo '<tr>';
            echo '<td>' . esc_html($sub['first_name'] . ' ' . $sub['last_name']) . '</td>';
            echo '<td>' . esc_html($sub['email']) . '</td>';
            echo '<td>' . esc_html($sub['phone']) . '</td>';
            echo '<td>' . esc_html($sub['level']) . '</td>';
            echo '<td>' . esc_html($subscribed) . '</td>';
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
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mmgr_export_newsletter')) {
            wp_die('Security check failed.');
        }

        global $wpdb;
        $tbl = $wpdb->prefix . 'memberships';
        $subscribers = $wpdb->get_results(
            "SELECT email, first_name, last_name, phone, level FROM $tbl WHERE newsletter = 1 ORDER BY email",
            ARRAY_A
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="newsletter-subscribers-' . current_time('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Email', 'First Name', 'Last Name', 'Phone', 'Level'));

        foreach ((array) $subscribers as $sub) {
            fputcsv($output, array($sub['email'], $sub['first_name'], $sub['last_name'], $sub['phone'], $sub['level']));
        }

        fclose($output);
        exit;
    }
});