<?php
if (!defined('ABSPATH')) exit;

function mmgr_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // ===========================
    // MAIN MEMBERSHIPS TABLE
    // ===========================
    $memberships_table = $wpdb->prefix . 'memberships';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$memberships_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_code VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        partner_first_name VARCHAR(100),
        partner_last_name VARCHAR(100),
        name VARCHAR(255) NOT NULL,
        partner_name VARCHAR(255),
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        sex VARCHAR(20),
        partner_sex VARCHAR(20),
        age DATE,
        partner_age DATE,
        level VARCHAR(100) NOT NULL,
        photo_url VARCHAR(500),
        newsletter TINYINT(1) DEFAULT 0,
        agreed_terms TINYINT(1) DEFAULT 0,
        start_date DATE NOT NULL,
        expire_date DATE,
        paid TINYINT(1) DEFAULT 0,
        payment_date DATETIME,
        payment_method VARCHAR(50),
        payment_amount DECIMAL(10,2),
        last_visited DATETIME,
        banned TINYINT(1) DEFAULT 0,
        banned_reason TEXT,
        banned_on DATETIME,
        password_hash VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_member_code (member_code),
        INDEX idx_email (email),
        INDEX idx_level (level),
        INDEX idx_paid (paid),
        INDEX idx_expire_date (expire_date),
        INDEX idx_banned (banned)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // MEMBERSHIP LEVELS TABLE
    // ===========================
    $levels_table = $wpdb->prefix . 'membership_levels';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$levels_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_name VARCHAR(100) UNIQUE NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        daily_fee DECIMAL(10,2) DEFAULT 0,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_level_name (level_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insert default levels if none exist
    $level_count = $wpdb->get_var("SELECT COUNT(*) FROM $levels_table");
    if ($level_count == 0) {
        $wpdb->insert($levels_table, array(
            'level_name' => 'Single',
            'price' => 150.00,
            'daily_fee' => 5.00,
            'description' => 'Individual membership'
        ));
        $wpdb->insert($levels_table, array(
            'level_name' => 'Couple',
            'price' => 250.00,
            'daily_fee' => 8.00,
            'description' => 'Couple/Family membership'
        ));
        $wpdb->insert($levels_table, array(
            'level_name' => 'Youth',
            'price' => 75.00,
            'daily_fee' => 3.00,
            'description' => 'Youth membership (under 18)'
        ));
    }
    
    // ===========================
    // SPECIAL EVENT FEES TABLE
    // ===========================
    $special_fees_table = $wpdb->prefix . 'membership_special_fees';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$special_fees_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        fee_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_date (event_date),
        INDEX idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // VISIT LOGS TABLE
    // ===========================
    $visits_table = $wpdb->prefix . 'membership_visits';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$visits_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        visit_time DATETIME NOT NULL,
        daily_fee DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        is_first_visit TINYINT(1) NOT NULL DEFAULT 0,
        orientation_done TINYINT(1) NOT NULL DEFAULT 0,
        id_verified TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_member_id (member_id),
        INDEX idx_visit_time (visit_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // EMAIL LOG TABLE
    // ===========================
    $email_log_table = $wpdb->prefix . 'membership_email_log';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$email_log_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        sent_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL,
        INDEX idx_member_id (member_id),
        INDEX idx_sent_at (sent_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // CARD REQUESTS TABLE
    // ===========================
    $card_requests_table = $wpdb->prefix . 'mmgr_card_requests';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$card_requests_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        request_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        completed_date DATETIME,
        notes TEXT,
        INDEX idx_member_id (member_id),
        INDEX idx_status (status),
        INDEX idx_request_date (request_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // FORUM TOPICS TABLE
    // ===========================
    $forum_topics_table = $wpdb->prefix . 'membership_forum_topics';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$forum_topics_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_name VARCHAR(255) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT '💬',
        active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        moderator_id INT NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Insert default forum topics if none exist
    $topic_count = $wpdb->get_var("SELECT COUNT(*) FROM $forum_topics_table");
    if ($topic_count == 0) {
        $wpdb->insert($forum_topics_table, array(
            'topic_name' => 'General Discussion',
            'description' => 'General community discussion and announcements',
            'icon' => '💬',
            'sort_order' => 1
        ));
        $wpdb->insert($forum_topics_table, array(
            'topic_name' => 'Events & Tournaments',
            'description' => 'Upcoming events and tournament discussions',
            'icon' => '🏆',
            'sort_order' => 2
        ));
        $wpdb->insert($forum_topics_table, array(
            'topic_name' => 'Tips & Tricks',
            'description' => 'Share your best pickleball tips',
            'icon' => '🎾',
            'sort_order' => 3
        ));
    }
    
    // Special Events/Advertising Table
    $events_table = $wpdb->prefix . 'membership_events';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$events_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        start_time TIME,
        end_time TIME,
        location VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_event_date (event_date),
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // FORUM POSTS TABLE
    // ===========================
    $forum_posts_table = $wpdb->prefix . 'membership_forum_posts';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$forum_posts_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        topic_id INT NOT NULL,
        message TEXT NOT NULL,
        photo_url VARCHAR(500),
        posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        edited_at DATETIME NULL DEFAULT NULL,
        INDEX idx_member (member_id),
        INDEX idx_topic (topic_id),
        INDEX idx_posted (posted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // MESSAGING TABLES
    // ===========================
    
    // Messages table
    $messages_table = $wpdb->prefix . 'membership_messages';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$messages_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_member_id INT NOT NULL,
        to_member_id INT NOT NULL,
        message TEXT NOT NULL,
        image_url VARCHAR(500),
        image_deleted TINYINT(1) DEFAULT 0,
        sent_at DATETIME NOT NULL,
        read_at DATETIME,
        deleted_by_sender TINYINT(1) DEFAULT 0,
        deleted_by_receiver TINYINT(1) DEFAULT 0,
        INDEX idx_from_member (from_member_id),
        INDEX idx_to_member (to_member_id),
        INDEX idx_sent_at (sent_at),
        INDEX idx_conversation (from_member_id, to_member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Contacts table
    $contacts_table = $wpdb->prefix . 'membership_contacts';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$contacts_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        contact_member_id INT NOT NULL,
        added_at DATETIME NOT NULL,
        UNIQUE KEY unique_contact (member_id, contact_member_id),
        INDEX idx_member_id (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Blocks table
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$blocks_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        blocked_member_id INT NOT NULL,
        blocked_at DATETIME NOT NULL,
        UNIQUE KEY unique_block (member_id, blocked_member_id),
        INDEX idx_member_id (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Message reports table
    $reports_table = $wpdb->prefix . 'membership_message_reports';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$reports_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        reported_by INT NOT NULL,
        reason TEXT NOT NULL,
        reported_at DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        INDEX idx_message_id (message_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ===========================
    // PWA PUSH SUBSCRIPTIONS TABLE
    // ===========================
    $push_table = $wpdb->prefix . 'mmgr_push_subscriptions';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$push_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        endpoint VARCHAR(700) NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY unique_endpoint (endpoint(700)),
        INDEX idx_member_id (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ===========================
    // COMMUNITY AWARDS TABLE
    // ===========================
    $awards_table = $wpdb->prefix . 'membership_community_awards';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$awards_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        award_name VARCHAR(100) NOT NULL,
        award_icon VARCHAR(255) NOT NULL DEFAULT '🏅',
        criteria_type VARCHAR(20) NOT NULL DEFAULT 'visits',
        min_threshold INT NOT NULL DEFAULT 0,
        max_threshold INT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_criteria_type (criteria_type),
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert default awards if none exist
    $award_count = $wpdb->get_var("SELECT COUNT(*) FROM $awards_table");
    if ($award_count == 0) {
        $wpdb->insert($awards_table, array(
            'award_name'    => 'Newbie',
            'award_icon'    => '🌱',
            'criteria_type' => 'visits',
            'min_threshold' => 1,
            'max_threshold' => 2,
            'sort_order'    => 1,
        ));
        $wpdb->insert($awards_table, array(
            'award_name'    => 'Regular',
            'award_icon'    => '⭐',
            'criteria_type' => 'visits',
            'min_threshold' => 3,
            'max_threshold' => 9,
            'sort_order'    => 2,
        ));
        $wpdb->insert($awards_table, array(
            'award_name'    => 'Veteran',
            'award_icon'    => '🏆',
            'criteria_type' => 'visits',
            'min_threshold' => 10,
            'max_threshold' => null,
            'sort_order'    => 3,
        ));
    }

    // ===========================
    // HELP TOPICS TABLE
    // ===========================
    $help_topics_table = $wpdb->prefix . 'membership_help_topics';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$help_topics_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        sort_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (active),
        INDEX idx_category (category),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ===========================
    // ORIENTATION ITEMS TABLE
    // ===========================
    $orientation_items_table = $wpdb->prefix . 'membership_orientation_items';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$orientation_items_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(500) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default orientation items if none exist
    $orientation_count = $wpdb->get_var("SELECT COUNT(*) FROM `$orientation_items_table`");
    if ($orientation_count == 0) {
        $defaults = array(
            array('Gone over the club\'s rules with the member',                          10),
            array('Shown the member the location of towels',                               20),
            array('Discussed activities that take place at club events with the member',   30),
        );
        foreach ($defaults as $d) {
            $wpdb->insert($orientation_items_table, array(
                'title'      => $d[0],
                'sort_order' => $d[1],
                'active'     => 1,
            ));
        }
    }

    // ===========================
    // ORIENTATION COMPLETIONS TABLE
    // ===========================
    $orientation_comp_table = $wpdb->prefix . 'membership_orientation_completions';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$orientation_comp_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        item_id INT NOT NULL,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_member_item (member_id, item_id),
        INDEX idx_member_id (member_id),
        INDEX idx_item_id (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Update plugin version
    update_option('mmgr_db_version', '1.0.0');
    
    // Log successful table creation
    error_log('MMGR: All database tables created/verified successfully');
}

/**
 * Add columns that were introduced after the initial schema.
 * Uses ALTER TABLE … ADD COLUMN so existing tables get patched without
 * data loss. Safe to call repeatedly – each check is guarded by SHOW COLUMNS.
 */
function mmgr_migrate_columns() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';

    // Bail if the memberships table doesn't exist yet.
    if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") !== $tbl) {
        return;
    }

    // newsletter column (required for the Newsletter Subscribers admin page)
    if (!$wpdb->get_row("SHOW COLUMNS FROM `$tbl` LIKE 'newsletter'")) {
        $wpdb->query("ALTER TABLE `$tbl` ADD COLUMN `newsletter` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // created_at column (displayed in the newsletter admin page subscriber list)
    if (!$wpdb->get_row("SHOW COLUMNS FROM `$tbl` LIKE 'created_at'")) {
        $wpdb->query("ALTER TABLE `$tbl` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    // updated_at column
    if (!$wpdb->get_row("SHOW COLUMNS FROM `$tbl` LIKE 'updated_at'")) {
        $wpdb->query("ALTER TABLE `$tbl` ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    update_option('mmgr_db_version', '1.1.0');
}

/**
 * Create the community awards table if it doesn't exist yet.
 * Called as part of the 1.2.0 migration.
 */
function mmgr_migrate_community_awards() {
    global $wpdb;
    $awards_table = $wpdb->prefix . 'membership_community_awards';
    if ($wpdb->get_var("SHOW TABLES LIKE '$awards_table'") === $awards_table) {
        return; // Already exists
    }
    $charset_collate = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$awards_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        award_name VARCHAR(100) NOT NULL,
        award_icon VARCHAR(255) NOT NULL DEFAULT '🏅',
        criteria_type VARCHAR(20) NOT NULL DEFAULT 'visits',
        min_threshold INT NOT NULL DEFAULT 0,
        max_threshold INT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_criteria_type (criteria_type),
        INDEX idx_active (active),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB $charset_collate");

    $wpdb->insert($awards_table, array(
        'award_name'    => 'Newbie',
        'award_icon'    => '🌱',
        'criteria_type' => 'visits',
        'min_threshold' => 1,
        'max_threshold' => 2,
        'sort_order'    => 1,
    ));
    $wpdb->insert($awards_table, array(
        'award_name'    => 'Regular',
        'award_icon'    => '⭐',
        'criteria_type' => 'visits',
        'min_threshold' => 3,
        'max_threshold' => 9,
        'sort_order'    => 2,
    ));
    $wpdb->insert($awards_table, array(
        'award_name'    => 'Veteran',
        'award_icon'    => '🏆',
        'criteria_type' => 'visits',
        'min_threshold' => 10,
        'max_threshold' => null,
        'sort_order'    => 3,
    ));

    update_option('mmgr_db_version', '1.2.0');
}

/**
 * Create the help topics table if it doesn't exist yet.
 * Called as part of the 1.3.0 migration.
 */
function mmgr_migrate_help_topics() {
    global $wpdb;
    $help_topics_table = $wpdb->prefix . 'membership_help_topics';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$help_topics_table'" ) === $help_topics_table;

    if ( $table_exists ) {
        // If the table already exists but has no topics, seed the defaults so new
        // installs (where mmgr_create_tables created the table without seeding) get them.
        $has_topics = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$help_topics_table`" );
        if ( $has_topics > 0 ) {
            return; // Topics already present — nothing to do.
        }
        // Fall through to seed default topics below.
    } else {
        $charset_collate = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `$help_topics_table` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'General',
        sort_order INT DEFAULT 0,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (active),
        INDEX idx_category (category),
        INDEX idx_sort (sort_order)
    ) ENGINE=InnoDB $charset_collate" );
    }

    // Seed a handful of starter help topics
    $defaults = array(
        array( 'How do I log in?', 'Navigate to the member login page and enter the email address you registered with, along with your password. If you have forgotten your password, use the "Forgot Password" link on the login page.', 'Account' ),
        array( 'How do I update my profile?', 'After logging in, click <strong>Profile</strong> in the navigation bar. From there you can update your photo, bio, and any custom fields.', 'Account' ),
        array( 'How do I send a message to another member?', 'Go to the <strong>Messages</strong> page and click <em>New Message</em>, or visit a member\'s profile and click the <em>Send Message</em> button.', 'Messages' ),
        array( 'How do I find other members?', 'Use the <strong>Directory</strong> page to search and browse all members. You can filter by name or membership level.', 'Community' ),
        array( 'How do I RSVP to an event?', 'Visit the <strong>Events</strong> page and click the <em>RSVP</em> button on any upcoming event you would like to attend.', 'Events' ),
        array( 'What are community awards?', 'Community awards are badges that are automatically earned based on your activity — such as visit count, likes received, or forum posts. They appear next to your name in the directory.', 'Community' ),
        array( 'How do I add friends?', 'Visit another member\'s profile and click <em>Add Friend</em>. Once they accept, you will be connected as friends.', 'Community' ),
        array( 'How do I report an issue?', 'If you encounter inappropriate content or behaviour, use the <em>Report</em> link on the relevant message or forum post. Our admin team will review it promptly.', 'General' ),
    );
    $sort = 0;
    foreach ( $defaults as $d ) {
        $sort += 10;
        $wpdb->insert( $help_topics_table, array(
            'title'      => $d[0],
            'content'    => $d[1],
            'category'   => $d[2],
            'sort_order' => $sort,
            'active'     => 1,
        ) );
    }
}

/**
 * Migrate the help_topics content column from TEXT to LONGTEXT so that
 * rich-content answers (including embedded images uploaded via the
 * WordPress media library) are not silently truncated.
 * Safe to call repeatedly – guarded by a column-type check.
 */
function mmgr_migrate_help_topics_content_longtext() {
    global $wpdb;
    $help_topics_table = $wpdb->prefix . 'membership_help_topics';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$help_topics_table'" ) !== $help_topics_table ) {
        return; // Table doesn't exist yet; mmgr_migrate_help_topics will create it as LONGTEXT.
    }

    $col = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = %s
               AND COLUMN_NAME  = 'content'",
            $help_topics_table
        ),
        ARRAY_A
    );

    if ( ! $col || strtolower( $col['DATA_TYPE'] ) === 'longtext' ) {
        return; // Already LONGTEXT or column not found.
    }

    $wpdb->query( "ALTER TABLE `" . esc_sql( $help_topics_table ) . "` MODIFY COLUMN `content` LONGTEXT NOT NULL" );
}

/**
 * Add first-visit staff-action columns to the memberships table,
 * and matching log columns to the visits table.
 * Safe to call repeatedly – each check is guarded by SHOW COLUMNS.
 */
function mmgr_migrate_first_visit_columns() {
    global $wpdb;
    $tbl        = $wpdb->prefix . 'memberships';
    $visits_tbl = $wpdb->prefix . 'membership_visits';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$tbl'" ) === $tbl ) {
        if ( ! $wpdb->get_row( "SHOW COLUMNS FROM `$tbl` LIKE 'orientation_done'" ) ) {
            $wpdb->query( "ALTER TABLE `$tbl` ADD COLUMN `orientation_done` TINYINT(1) NOT NULL DEFAULT 0" );
        }
        if ( ! $wpdb->get_row( "SHOW COLUMNS FROM `$tbl` LIKE 'id_verified'" ) ) {
            $wpdb->query( "ALTER TABLE `$tbl` ADD COLUMN `id_verified` TINYINT(1) NOT NULL DEFAULT 0" );
        }
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$visits_tbl'" ) === $visits_tbl ) {
        if ( ! $wpdb->get_row( "SHOW COLUMNS FROM `$visits_tbl` LIKE 'is_first_visit'" ) ) {
            $wpdb->query( "ALTER TABLE `$visits_tbl` ADD COLUMN `is_first_visit` TINYINT(1) NOT NULL DEFAULT 0" );
        }
        if ( ! $wpdb->get_row( "SHOW COLUMNS FROM `$visits_tbl` LIKE 'orientation_done'" ) ) {
            $wpdb->query( "ALTER TABLE `$visits_tbl` ADD COLUMN `orientation_done` TINYINT(1) NOT NULL DEFAULT 0" );
        }
        if ( ! $wpdb->get_row( "SHOW COLUMNS FROM `$visits_tbl` LIKE 'id_verified'" ) ) {
            $wpdb->query( "ALTER TABLE `$visits_tbl` ADD COLUMN `id_verified` TINYINT(1) NOT NULL DEFAULT 0" );
        }
    }
}

/**
 * Create orientation tables for existing installs that already ran mmgr_create_tables()
 * before these tables were added. Safe to call repeatedly.
 */
function mmgr_migrate_orientation_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $items_tbl = $wpdb->prefix . 'membership_orientation_items';
    $comp_tbl  = $wpdb->prefix . 'membership_orientation_completions';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$items_tbl'" ) !== $items_tbl ) {
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `$items_tbl` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB $charset_collate" );

        // Seed defaults on fresh migration
        $defaults = array(
            array( 'Gone over the club\'s rules with the member',                          10 ),
            array( 'Shown the member the location of towels',                               20 ),
            array( 'Discussed activities that take place at club events with the member',   30 ),
        );
        foreach ( $defaults as $d ) {
            $wpdb->insert( $items_tbl, array( 'title' => $d[0], 'sort_order' => $d[1], 'active' => 1 ) );
        }
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$comp_tbl'" ) !== $comp_tbl ) {
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `$comp_tbl` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            item_id INT NOT NULL,
            completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_member_item (member_id, item_id),
            INDEX idx_member_id (member_id),
            INDEX idx_item_id (item_id)
        ) ENGINE=InnoDB $charset_collate" );
    }
}

/**
 * Check and update database schema on plugin load
 */
function mmgr_check_database() {
    $current_version = get_option('mmgr_db_version', '0.0.0');
    $required_version = '1.6.0';
    
    if (version_compare($current_version, $required_version, '<')) {
        mmgr_create_tables();
        mmgr_migrate_columns();
        mmgr_migrate_community_awards();
        mmgr_migrate_help_topics();
        mmgr_migrate_help_topics_content_longtext();
        mmgr_migrate_first_visit_columns();
        mmgr_migrate_orientation_tables();
        update_option( 'mmgr_db_version', '1.6.0' );
    }
}

// Hook to check database on admin init
add_action('admin_init', 'mmgr_check_database');