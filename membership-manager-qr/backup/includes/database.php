<?php
if (!defined('ABSPATH')) exit;

function mmgr_ensure_tables_exist() {
    global $wpdb;
    $tbl = $wpdb->prefix . "memberships";
    $levels_tbl = $wpdb->prefix . "membership_levels";
    $visits_tbl = $wpdb->prefix . "membership_visits";
    $charset = $wpdb->get_charset_collate();

    $fields = array(
        "first_name" => "VARCHAR(64) DEFAULT ''",
        "last_name" => "VARCHAR(64) DEFAULT ''",
        "partner_first_name" => "VARCHAR(64) DEFAULT ''",
        "partner_last_name" => "VARCHAR(64) DEFAULT ''",
        "age" => "INT DEFAULT 0",
        "partner_age" => "INT DEFAULT 0",
        "newsletter" => "TINYINT(1) DEFAULT 0",
        "agreed_terms" => "TINYINT(1) DEFAULT 0",
        "photo_url" => "VARCHAR(255) DEFAULT ''",
        "last_visited" => "DATETIME NULL",
        "banned" => "TINYINT(1) DEFAULT 0",
        "banned_reason" => "VARCHAR(255) DEFAULT ''",
        "banned_on" => "DATETIME NULL"
    );

    $base_sql = "CREATE TABLE IF NOT EXISTS `$tbl` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        member_code VARCHAR(24) UNIQUE,
        name VARCHAR(128),
        email VARCHAR(128),
        phone VARCHAR(24),
        level VARCHAR(32) DEFAULT 'standard',
        partner_name VARCHAR(128) DEFAULT NULL,
        start_date DATE,
        expire_date DATE,
        notes TEXT,
        paid TINYINT(1) DEFAULT 0,
        amount_paid DECIMAL(10,2) DEFAULT 0.00
    ) $charset;";
    $wpdb->query($base_sql);

    $rowcols = $wpdb->get_results("SHOW COLUMNS FROM `$tbl`",ARRAY_A);
    $existing = array_column($rowcols,'Field');
    foreach($fields as $col=>$type) {
        if (!in_array($col,$existing)) {
            $wpdb->query("ALTER TABLE `$tbl` ADD `$col` $type");
        }
    }

    $wpdb->query("CREATE TABLE IF NOT EXISTS `$levels_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_name VARCHAR(32) UNIQUE,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00
    ) $charset;");
    
    $levels_exist = $wpdb->get_var("SELECT COUNT(*) FROM `$levels_tbl`");
    if (!$levels_exist) {
        $wpdb->query("INSERT INTO `$levels_tbl` (level_name,price) VALUES
            ('standard', 50.00),('couple',75.00),('vip',120.00)");
    }

    $wpdb->query("CREATE TABLE IF NOT EXISTS `$visits_tbl` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        member_id BIGINT UNSIGNED NOT NULL,
        visit_time DATETIME NOT NULL,
        FOREIGN KEY(member_id) REFERENCES $tbl(id) ON DELETE CASCADE
    ) $charset;");
}
?>