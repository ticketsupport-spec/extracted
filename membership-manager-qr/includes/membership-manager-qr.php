<?php
/**
 * Plugin Name: Membership Manager QR
 * Description: Complete membership management with QR codes, check-in tracking, and revenue management
 * Version: 2.4
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: membership-manager-qr
 */

if (!defined('ABSPATH')) exit;

define('MMGR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMGR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMGR_VERSION', '2.4');

// Load CSS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('mmgr-styles', MMGR_PLUGIN_URL . 'assets/styles.css', array(), MMGR_VERSION);
    wp_enqueue_script('mmgr-scripts', MMGR_PLUGIN_URL . 'assets/scripts.js', array('jquery'), MMGR_VERSION, true);
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('mmgr-admin-styles', MMGR_PLUGIN_URL . 'assets/styles.css', array(), MMGR_VERSION);
    wp_enqueue_script('mmgr-admin-scripts', MMGR_PLUGIN_URL . 'assets/admin-scripts.js', array('jquery'), MMGR_VERSION, true);
});

// Database Setup
function mmgr_ensure_tables_exist() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Memberships Table
    $memberships = $wpdb->prefix . "memberships";
    if ($wpdb->get_var("SHOW TABLES LIKE '$memberships'") != $memberships) {
        $sql = "CREATE TABLE $memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_code VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            partner_first_name VARCHAR(100),
            partner_last_name VARCHAR(100),
            partner_name VARCHAR(200),
            name VARCHAR(200) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            level VARCHAR(50) NOT NULL,
            sex VARCHAR(20),
            partner_sex VARCHAR(20),
            age DATE NOT NULL,
            partner_age DATE,
            newsletter TINYINT DEFAULT 0,
            agreed_terms TINYINT DEFAULT 1,
            photo_url LONGTEXT,
            notes LONGTEXT,
            paid TINYINT DEFAULT 0,
            amount_paid DECIMAL(10,2) DEFAULT 0,
            start_date DATE,
            expire_date DATE,
            last_visited DATETIME,
            banned TINYINT DEFAULT 0,
            banned_reason TEXT,
            banned_on DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_code (member_code),
            INDEX idx_banned (banned),
            INDEX idx_level (level)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    } else {
        // Add sex column if missing
        if ($wpdb->get_var("SHOW COLUMNS FROM $memberships LIKE 'sex'") == null) {
            $wpdb->query("ALTER TABLE $memberships ADD COLUMN sex VARCHAR(20) AFTER level");
        }
        if ($wpdb->get_var("SHOW COLUMNS FROM $memberships LIKE 'partner_sex'") == null) {
            $wpdb->query("ALTER TABLE $memberships ADD COLUMN partner_sex VARCHAR(20) AFTER partner_name");
        }
    }
    
    // Visits Table
    $visits = $wpdb->prefix . "membership_visits";
    if ($wpdb->get_var("SHOW TABLES LIKE '$visits'") != $visits) {
        $sql = "CREATE TABLE $visits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            member_id BIGINT UNSIGNED NOT NULL,
            visit_time DATETIME NOT NULL,
            daily_fee DECIMAL(10,2) DEFAULT 0,
            notes VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES $memberships(id) ON DELETE CASCADE,
            INDEX idx_member (member_id),
            INDEX idx_visit_time (visit_time)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    } else {
        // Migrate existing visits table to add missing columns
        if ($wpdb->get_var("SHOW COLUMNS FROM $visits LIKE 'daily_fee'") == null) {
            $wpdb->query("ALTER TABLE $visits ADD COLUMN daily_fee DECIMAL(10,2) DEFAULT 0 AFTER visit_time");
        }
        if ($wpdb->get_var("SHOW COLUMNS FROM $visits LIKE 'notes'") == null) {
            $wpdb->query("ALTER TABLE $visits ADD COLUMN notes VARCHAR(255) AFTER daily_fee");
        }
    }
    
    // Membership Levels Table
    $levels = $wpdb->prefix . "membership_levels";
    if ($wpdb->get_var("SHOW TABLES LIKE '$levels'") != $levels) {
        $sql = "CREATE TABLE $levels (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level_name VARCHAR(100) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL,
            daily_fee DECIMAL(10,2) DEFAULT 5.00,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (level_name)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Insert default levels with different fees
        $wpdb->insert($levels, array('level_name' => 'Single', 'price' => 50, 'daily_fee' => 5.00, 'description' => 'Single membership'));
        $wpdb->insert($levels, array('level_name' => 'Couple', 'price' => 80, 'daily_fee' => 8.00, 'description' => 'Couple membership'));
    } else {
        // CRITICAL: Add missing columns to membership_levels table
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $levels");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        // Add daily_fee if missing
        if (!in_array('daily_fee', $column_names)) {
            $wpdb->query("ALTER TABLE $levels ADD COLUMN daily_fee DECIMAL(10,2) DEFAULT 5.00 AFTER price");
        }
        
        // Add description if missing
        if (!in_array('description', $column_names)) {
            $wpdb->query("ALTER TABLE $levels ADD COLUMN description TEXT AFTER daily_fee");
        }
        
        // Add created_at if missing
        if (!in_array('created_at', $column_names)) {
            $wpdb->query("ALTER TABLE $levels ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Add updated_at if missing
        if (!in_array('updated_at', $column_names)) {
            $wpdb->query("ALTER TABLE $levels ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }
    
    // Special Fees Table
    $fees = $wpdb->prefix . "membership_fees";
    if ($wpdb->get_var("SHOW TABLES LIKE '$fees'") != $fees) {
        $sql = "CREATE TABLE $fees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            fee_date DATE UNIQUE NOT NULL,
            fee_amount DECIMAL(10,2) NOT NULL,
            event_name VARCHAR(255),
            description TEXT,
            apply_to_levels LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_date (fee_date)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    } else {
        // Add missing columns to membership_fees table
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $fees");
        $column_names = array();
        foreach ($columns as $column) {
            $column_names[] = $column->Field;
        }
        
        // Add apply_to_levels if missing
        if (!in_array('apply_to_levels', $column_names)) {
            $wpdb->query("ALTER TABLE $fees ADD COLUMN apply_to_levels LONGTEXT AFTER event_name");
        }
        
        // Add description if missing
        if (!in_array('description', $column_names)) {
            $wpdb->query("ALTER TABLE $fees ADD COLUMN description TEXT AFTER event_name");
        }
    }
    
    // Initialize options if they don't exist
    if (!get_option('mmgr_registration_title')) {
        update_option('mmgr_registration_title', 'Membership Signup');
    }
    if (!get_option('mmgr_checkin_title')) {
        update_option('mmgr_checkin_title', 'QR Code Scanner');
    }
}

// Generate Member Code
function mmgr_generate_member_code($name) {
    global $wpdb;
    $tbl = $wpdb->prefix."memberships";
    $clean_name = strtoupper(substr(str_replace(' ','',preg_replace('/[^a-zA-Z0-9]/','',$name)),0,4));
    $code = $clean_name . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    $count = 0;
    while($wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE member_code=%s", $code)) && $count < 10) {
        $code = $clean_name . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $count++;
    }
    return $code;
}

// Get Daily Fee for Member Level
function mmgr_get_daily_fee($member_level = null, $date = null) {
    global $wpdb;
    $date = $date ? $date : date('Y-m-d');
    $fees_tbl = $wpdb->prefix."membership_fees";
    $levels_tbl = $wpdb->prefix."membership_levels";
    
    // Check for special event fee on this date
    $special = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fees_tbl WHERE fee_date=%s", $date), ARRAY_A);
    if ($special) {
        // Check if this fee applies to the member's level
        if (!$special['apply_to_levels'] || empty($special['apply_to_levels'])) {
            // No level restriction, applies to all
            return (float)$special['fee_amount'];
        }
        $levels = json_decode($special['apply_to_levels'], true);
        if (is_array($levels) && in_array($member_level, $levels)) {
            return (float)$special['fee_amount'];
        }
    }
    
    // Get the default fee for this membership level
    if ($member_level) {
        $level = $wpdb->get_row($wpdb->prepare("SELECT daily_fee FROM $levels_tbl WHERE level_name=%s", $member_level), ARRAY_A);
        if ($level) {
            return (float)$level['daily_fee'];
        }
    }
    
    // Fallback to global default
    return 5.00;
}

// Calculate Member Age from DOB
function mmgr_calculate_age($dob) {
    if (empty($dob)) return 0;
    $birth = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($birth)->y;
}

// Validate Member is 18+
function mmgr_validate_age($dob) {
    return mmgr_calculate_age($dob) >= 18;
}

// Include Files
require_once MMGR_PLUGIN_DIR . 'includes/admin-menu.php';
require_once MMGR_PLUGIN_DIR . 'includes/shortcodes.php';
require_once MMGR_PLUGIN_DIR . 'includes/ajax-handlers.php';

// Activation Hook
register_activation_hook(__FILE__, 'mmgr_ensure_tables_exist');
?>