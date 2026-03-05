<?php
if (!defined('ABSPATH')) exit;

/**
 * Create database tables for member portal
 */
function mmgr_create_portal_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Add columns to memberships table
    $memberships_tbl = $wpdb->prefix . 'memberships';
    
    // Check if password_hash column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'password_hash'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL");
    }
    
    // Check if profile_photo_url column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'profile_photo_url'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN profile_photo_url VARCHAR(500) DEFAULT NULL");
    }

    // Check if community_alias column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'community_alias'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN community_alias VARCHAR(100) DEFAULT NULL");
    }

    // Check if community_photo_url column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'community_photo_url'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN community_photo_url VARCHAR(500) DEFAULT NULL");
    }

    // Check if community_bio column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'community_bio'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN community_bio TEXT DEFAULT NULL");
    }

    // Check if active column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = 'active'");
    if(empty($row)) {
        $wpdb->query("ALTER TABLE $memberships_tbl ADD COLUMN active TINYINT(1) DEFAULT 1");
    }

    // Add moderator_id to forum topics table
    $forum_topics_tbl = $wpdb->prefix . 'membership_forum_topics';
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($forum_topics_tbl) . "' AND column_name = 'moderator_id'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE `$forum_topics_tbl` ADD COLUMN `moderator_id` INT NULL DEFAULT NULL");
    }

    // Add edited_at to forum posts table
    $forum_posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($forum_posts_tbl) . "' AND column_name = 'edited_at'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE `$forum_posts_tbl` ADD COLUMN `edited_at` DATETIME NULL DEFAULT NULL");
    }

    // Add forum suspension/ban columns to memberships
    $memberships_tbl = $wpdb->prefix . 'memberships';
    $forum_cols = array(
        'forum_suspended'        => "ADD COLUMN `forum_suspended` TINYINT(1) DEFAULT 0",
        'forum_suspended_until'  => "ADD COLUMN `forum_suspended_until` DATETIME NULL DEFAULT NULL",
        'forum_suspended_reason' => "ADD COLUMN `forum_suspended_reason` TEXT NULL DEFAULT NULL",
        'forum_banned'           => "ADD COLUMN `forum_banned` TINYINT(1) DEFAULT 0",
        'forum_banned_reason'    => "ADD COLUMN `forum_banned_reason` TEXT NULL DEFAULT NULL",
    );
    foreach ($forum_cols as $col => $alter) {
        $exists = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($memberships_tbl) . "' AND column_name = '" . esc_sql($col) . "'");
        if (empty($exists)) {
            $wpdb->query("ALTER TABLE `$memberships_tbl` $alter");
        }
    }

    // Forum topic moderators table (multiple moderators per topic)
    $topic_mods_tbl = $wpdb->prefix . 'membership_forum_topic_mods';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$topic_mods_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        member_id INT NOT NULL,
        added_at DATETIME NOT NULL,
        UNIQUE KEY unique_mod (topic_id, member_id),
        INDEX idx_topic_id (topic_id),
        INDEX idx_member_id (member_id)
    ) $charset_collate");

    // Forum post edit history table (previous versions, visible to moderators only)
    $post_history_tbl = $wpdb->prefix . 'membership_forum_post_history';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$post_history_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        old_message TEXT NOT NULL,
        saved_at DATETIME NOT NULL,
        INDEX idx_post_id (post_id)
    ) $charset_collate");

    // Migration: add 'hidden' column to forum posts table if missing
    $forum_posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $hidden_col = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND table_name = '" . esc_sql($forum_posts_tbl) . "' AND column_name = 'hidden'");
    if (empty($hidden_col)) {
        $wpdb->query("ALTER TABLE `$forum_posts_tbl` ADD COLUMN `hidden` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Forum post comments table
    $post_comments_tbl = $wpdb->prefix . 'membership_forum_post_comments';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$post_comments_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        member_id INT NOT NULL,
        comment TEXT NOT NULL,
        posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post_id (post_id),
        INDEX idx_member_id (member_id),
        INDEX idx_posted_at (posted_at)
    ) $charset_collate");

    // Member likes table
    $likes_tbl = $wpdb->prefix . 'membership_likes';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$likes_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        liked_member_id INT NOT NULL,
        liked_at DATETIME NOT NULL,
        UNIQUE KEY unique_like (member_id, liked_member_id),
        INDEX idx_member_id (member_id),
        INDEX idx_liked_member_id (liked_member_id)
    ) $charset_collate");

    // Forum post likes table
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$post_likes_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        post_id INT NOT NULL,
        liked_at DATETIME NOT NULL,
        UNIQUE KEY unique_post_like (member_id, post_id),
        INDEX idx_member_id (member_id),
        INDEX idx_post_id (post_id)
    ) $charset_collate");

    // Forum comment likes table
    $comment_likes_tbl = $wpdb->prefix . 'membership_forum_comment_likes';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$comment_likes_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        comment_id INT NOT NULL,
        liked_at DATETIME NOT NULL,
        UNIQUE KEY unique_comment_like (member_id, comment_id),
        INDEX idx_member_id (member_id),
        INDEX idx_comment_id (comment_id)
    ) $charset_collate");

    // Private member notes table (notes a viewer leaves on another member's profile, only visible to them)
    $member_notes_tbl = $wpdb->prefix . 'membership_member_notes';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$member_notes_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        viewer_member_id INT NOT NULL,
        profile_member_id INT NOT NULL,
        note TEXT NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY unique_note (viewer_member_id, profile_member_id),
        INDEX idx_viewer_member_id (viewer_member_id)
    ) $charset_collate");

    // Member bio photos table (up to 50 photos per member)
    $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$bio_photos_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        photo_url VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_member_id (member_id),
        INDEX idx_sort (member_id, sort_order)
    ) $charset_collate");

    // Bio photo likes table (members liking individual bio photos)
    $bio_photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$bio_photo_likes_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        photo_id INT NOT NULL,
        liked_at DATETIME NOT NULL,
        UNIQUE KEY unique_photo_like (member_id, photo_id),
        INDEX idx_member_id (member_id),
        INDEX idx_photo_id (photo_id)
    ) $charset_collate");

    // Card requests table
    $card_requests_tbl = $wpdb->prefix . 'mmgr_card_requests';
    $sql_card = "CREATE TABLE IF NOT EXISTS $card_requests_tbl (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        request_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        completed_date DATETIME DEFAULT NULL,
        notes TEXT,
        INDEX idx_member_id (member_id),
        INDEX idx_status (status)
    ) $charset_collate;";
    
    // Forum posts table
    $forum_tbl = $wpdb->prefix . 'mmgr_forum_posts';
    $sql_forum = "CREATE TABLE IF NOT EXISTS $forum_tbl (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        message TEXT NOT NULL,
        posted_at DATETIME NOT NULL,
        edited_at DATETIME DEFAULT NULL,
        INDEX idx_member_id (member_id),
        INDEX idx_posted_at (posted_at)
    ) $charset_collate;";
    
    // Member sessions table
    $sessions_tbl = $wpdb->prefix . 'mmgr_member_sessions';
    $sql_sessions = "CREATE TABLE IF NOT EXISTS $sessions_tbl (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        session_token VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        INDEX idx_member_id (member_id),
        INDEX idx_session_token (session_token),
        INDEX idx_expires_at (expires_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_card);
    dbDelta($sql_forum);
    dbDelta($sql_sessions);
}

// Run on plugin activation
add_action('init', 'mmgr_create_portal_tables');

/**
 * Verify member login
 * WRAPPED to prevent redeclaration - also in auth-functions.php
 */
if (!function_exists('mmgr_verify_member_login')) {
    function mmgr_verify_member_login($email, $password) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'memberships';
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tbl WHERE email = %s",
            $email
        ), ARRAY_A);
        
        if (!$member) {
            return false;
        }
        
        if (empty($member['password_hash'])) {
            return array('error' => 'no_password', 'member_id' => $member['id']);
        }
        
        if (password_verify($password, $member['password_hash'])) {
            return $member;
        }
        
        return false;
    }
}

/**
 * Create member session
 * WRAPPED to prevent redeclaration - also in auth-functions.php
 */
if (!function_exists('mmgr_create_member_session')) {
    function mmgr_create_member_session($member_id) {
        global $wpdb;
        $sessions_tbl = $wpdb->prefix . 'mmgr_member_sessions';
        
        // Invalidate any existing session in the current browser before creating a new one.
        // This prevents a stale session belonging to a different member from remaining
        // active in the database when a new user logs in on the same device/browser.
        if (!empty($_COOKIE['mmgr_session'])) {
            $old_token = sanitize_text_field($_COOKIE['mmgr_session']);
            $wpdb->delete($sessions_tbl, array('session_token' => $old_token));
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        
        // Clean old sessions for this member
        $wpdb->delete($sessions_tbl, array('member_id' => $member_id));
        
        // Create new session
        $wpdb->insert($sessions_tbl, array(
            'member_id' => $member_id,
            'session_token' => $token,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
        
        // Set cookie with Secure, HttpOnly, and SameSite=Strict to prevent CSRF and
        // to ensure the cookie is only sent over HTTPS and not accessible via JavaScript.
        if (PHP_VERSION_ID >= 70300) {
            setcookie('mmgr_session', $token, array(
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ));
        } else {
            // PHP < 7.3: use header() directly to set SameSite reliably.
            $max_age = 30 * 24 * 60 * 60;
            header('Set-Cookie: mmgr_session=' . rawurlencode($token) . '; Max-Age=' . $max_age . '; Path=/; Secure; HttpOnly; SameSite=Strict', false);
        }
        
        return $token;
    }
}

/**
 * Get current logged-in member
 * WRAPPED to prevent redeclaration - also in auth-functions.php
 */
if (!function_exists('mmgr_get_current_member')) {
    function mmgr_get_current_member() {
        if (empty($_COOKIE['mmgr_session'])) {
            return null;
        }
        
        global $wpdb;
        $sessions_tbl = $wpdb->prefix . 'mmgr_member_sessions';
        $members_tbl = $wpdb->prefix . 'memberships';
        
        $token = sanitize_text_field($_COOKIE['mmgr_session']);
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_tbl WHERE session_token = %s AND expires_at > NOW()",
            $token
        ), ARRAY_A);
        
        if (!$session) {
            return null;
        }
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_tbl WHERE id = %d",
            $session['member_id']
        ), ARRAY_A);
        
        return $member;
    }
}

/**
 * Logout member
 * WRAPPED to prevent redeclaration - also in auth-functions.php
 */
if (!function_exists('mmgr_logout_member')) {
    function mmgr_logout_member() {
        if (!empty($_COOKIE['mmgr_session'])) {
            global $wpdb;
            $sessions_tbl = $wpdb->prefix . 'mmgr_member_sessions';
            $token = sanitize_text_field($_COOKIE['mmgr_session']);
            
            $wpdb->delete($sessions_tbl, array('session_token' => $token));
            // Clear the cookie using the same flags (Secure, HttpOnly, SameSite=Strict)
            // that were used when setting it, so browsers properly remove it.
            if (PHP_VERSION_ID >= 70300) {
                setcookie('mmgr_session', '', array(
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ));
            } else {
                // PHP < 7.3: use header() directly to set SameSite reliably.
                header('Set-Cookie: mmgr_session=deleted; Expires=' . gmdate('D, d M Y H:i:s T', time() - 3600) . '; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Strict', false);
            }
            // Unset from the current request's superglobal so that subsequent calls
            // to mmgr_get_current_member() within this same PHP execution do not
            // mistakenly read the now-invalidated token.
            unset($_COOKIE['mmgr_session']);
        }
    }
}

/**
 * Check if member is logged in (for use in templates)
 * WRAPPED to prevent redeclaration - also in auth-functions.php
 */
if (!function_exists('mmgr_is_member_logged_in')) {
    function mmgr_is_member_logged_in() {
        return mmgr_get_current_member() !== null;
    }
}

/**
 * Get member's visit history
 */
function mmgr_get_member_visits($member_id, $limit = 20) {
    global $wpdb;
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $visits_tbl WHERE member_id = %d ORDER BY visit_time DESC LIMIT %d",
        $member_id,
        $limit
    ), ARRAY_A);
}

/**
 * Get member's special events attended
 */
function mmgr_get_member_special_events($member_id) {
    global $wpdb;
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    $fees_tbl = $wpdb->prefix . 'membership_fees';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT v.visit_time, v.daily_fee, f.event_name, f.description, f.fee_date
         FROM $visits_tbl v
         INNER JOIN $fees_tbl f ON DATE(v.visit_time) = f.fee_date
         WHERE v.member_id = %d
         ORDER BY v.visit_time DESC",
        $member_id
    ), ARRAY_A);
}

/**
 * Get upcoming special events
 */
function mmgr_get_upcoming_special_events() {
    global $wpdb;
    $fees_tbl = $wpdb->prefix . 'membership_fees';
    
    return $wpdb->get_results(
        "SELECT * FROM $fees_tbl WHERE fee_date >= CURDATE() ORDER BY fee_date ASC",
        ARRAY_A
    );
}

/**
 * Request physical card
 */
function mmgr_request_card($member_id) {
    global $wpdb;
    $card_tbl = $wpdb->prefix . 'mmgr_card_requests';
    
    // Check if already has pending request
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $card_tbl WHERE member_id = %d AND status = 'pending'",
        $member_id
    ));
    
    if ($existing) {
        return array('success' => false, 'message' => 'You already have a pending card request.');
    }
    
    $result = $wpdb->insert($card_tbl, array(
        'member_id' => $member_id,
        'request_date' => current_time('mysql'),
        'status' => 'pending'
    ));
    
    if ($result) {
        // TODO: Send notification to admin
        return array('success' => true, 'message' => 'Card request submitted successfully!');
    }
    
    return array('success' => false, 'message' => 'Failed to submit request. Please try again.');
}

/**
 * Get member's card request status
 */
function mmgr_get_card_request_status($member_id) {
    global $wpdb;
    $card_tbl = $wpdb->prefix . 'mmgr_card_requests';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $card_tbl WHERE member_id = %d ORDER BY request_date DESC LIMIT 1",
        $member_id
    ), ARRAY_A);
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

/**
 * Fetch a page of likes received by a member from all sources.
 *
 * Each row contains: liked_at, like_type ('profile'|'photo'|'post'),
 * from_id, from_alias, context_id (topic_id for posts, photo_id for photos),
 * context_label (topic_name for posts).
 *
 * @param int $member_id  The member whose received likes to query.
 * @param int $offset     OFFSET for pagination.
 * @param int $per_page   LIMIT for pagination.
 * @return array
 */
function mmgr_get_received_likes( $member_id, $offset = 0, $per_page = 10 ) {
    global $wpdb;

    $likes_tbl      = $wpdb->prefix . 'membership_likes';
    $photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
    $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    $posts_tbl      = $wpdb->prefix . 'membership_forum_posts';
    $topics_tbl     = $wpdb->prefix . 'membership_forum_topics';
    $members_tbl    = $wpdb->prefix . 'memberships';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT lr.liked_at, lr.from_member_id, lr.like_type, lr.context_id, lr.context_label,
                    m.community_alias AS from_alias, m.id AS from_id
             FROM (
                 SELECT liked_at, member_id AS from_member_id,
                        'profile' AS like_type, NULL AS context_id, NULL AS context_label
                 FROM $likes_tbl
                 WHERE liked_member_id = %d

                 UNION ALL

                 SELECT bpl.liked_at, bpl.member_id AS from_member_id,
                        'photo' AS like_type, bpl.photo_id AS context_id, NULL AS context_label
                 FROM $photo_likes_tbl bpl
                 JOIN $bio_photos_tbl bp ON bpl.photo_id = bp.id
                 WHERE bp.member_id = %d

                 UNION ALL

                 SELECT fpl.liked_at, fpl.member_id AS from_member_id,
                        'post' AS like_type, fp.topic_id AS context_id, t.topic_name AS context_label
                 FROM $post_likes_tbl fpl
                 JOIN $posts_tbl fp ON fpl.post_id = fp.id
                 LEFT JOIN $topics_tbl t ON fp.topic_id = t.id
                 WHERE fp.member_id = %d
             ) lr
             LEFT JOIN $members_tbl m ON lr.from_member_id = m.id
             ORDER BY lr.liked_at DESC
             LIMIT %d OFFSET %d",
            $member_id,
            $member_id,
            $member_id,
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    return $rows ?: array();
}

/**
 * Count total likes received by a member from all sources.
 *
 * @param int $member_id
 * @return int
 */
function mmgr_count_received_likes( $member_id ) {
    global $wpdb;

    $likes_tbl      = $wpdb->prefix . 'membership_likes';
    $photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
    $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    $posts_tbl      = $wpdb->prefix . 'membership_forum_posts';

    return
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $likes_tbl WHERE liked_member_id = %d",
            $member_id
        ) ) +
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $photo_likes_tbl bpl
             JOIN $bio_photos_tbl bp ON bpl.photo_id = bp.id
             WHERE bp.member_id = %d",
            $member_id
        ) ) +
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $post_likes_tbl fpl
             JOIN $posts_tbl fp ON fpl.post_id = fp.id
             WHERE fp.member_id = %d",
            $member_id
        ) );
}

/**
 * Render a single "like received" list item as an HTML string.
 *
 * @param array $like  Row from mmgr_get_received_likes().
 * @return string
 */
function mmgr_render_received_like_item( $like ) {
    $alias      = esc_html( $like['from_alias'] ?: 'Member' );
    $from_id    = (int) $like['from_id'];
    $time_ago   = human_time_diff( strtotime( $like['liked_at'] ), current_time( 'timestamp' ) ) . ' ago';
    $profile_url = esc_url( home_url( '/member-community-profile/' ) . '?id=' . $from_id );

    switch ( $like['like_type'] ) {
        case 'photo':
            $icon  = '📸';
            $label = $alias . ' liked your photo';
            $link  = $profile_url;
            break;
        case 'post':
            $icon       = '💬';
            $topic_name = $like['context_label'] ? esc_html( mb_substr( $like['context_label'], 0, 30 ) ) : 'your post';
            $label      = $alias . ' liked your post in ' . $topic_name;
            $link       = esc_url( home_url( '/member-community/' ) . '?topic=' . (int) $like['context_id'] );
            break;
        default: // 'profile'
            $icon  = '❤️';
            $label = $alias . ' liked your profile';
            $link  = $profile_url;
            break;
    }

    return '<div style="padding:10px;background:#f9f9f9;border-radius:6px;border-left:3px solid #FF2197;cursor:pointer;transition:all 0.3s;" onclick="window.location.href=\'' . $link . '\'">'
        . '<div style="font-weight:bold;color:#9b51e0;font-size:14px;">' . $icon . ' ' . $label . '</div>'
        . '<div style="font-size:12px;color:#666;">' . esc_html( $time_ago ) . '</div>'
        . '</div>';
}

/**
 * AJAX: Load more received likes (pagination for the activity page).
 */
add_action( 'wp_ajax_nopriv_mmgr_load_received_likes', function() { do_action( 'wp_ajax_mmgr_load_received_likes' ); } );
add_action( 'wp_ajax_mmgr_load_received_likes', function() {
    check_ajax_referer( 'mmgr_load_received_likes', 'nonce' );

    $member = mmgr_get_current_member();
    if ( ! $member ) {
        wp_send_json_error( 'Not logged in' );
    }

    $per_page = 10;
    $offset   = absint( $_POST['offset'] );

    $likes = mmgr_get_received_likes( $member['id'], $offset, $per_page + 1 );
    $has_more = count( $likes ) > $per_page;
    if ( $has_more ) {
        array_pop( $likes );
    }

    ob_start();
    foreach ( $likes as $like ) {
        echo mmgr_render_received_like_item( $like );
    }
    $html = ob_get_clean();

    wp_send_json_success( array(
        'html'        => $html,
        'has_more'    => $has_more,
        'next_offset' => $offset + $per_page,
    ) );
} );

/**
 * Get a paginated list of items that a member has liked (sent likes).
 *
 * Returns rows with: liked_at, like_type, context_id, context_label, target_member_id, target_alias, photo_url
 *
 * @param int $member_id  The member whose likes we are fetching.
 * @param int $offset     Pagination offset.
 * @param int $per_page   LIMIT for pagination.
 * @return array
 */
function mmgr_get_sent_likes( $member_id, $offset = 0, $per_page = 10 ) {
    global $wpdb;

    $likes_tbl           = $wpdb->prefix . 'membership_likes';
    $photo_likes_tbl     = $wpdb->prefix . 'membership_bio_photo_likes';
    $bio_photos_tbl      = $wpdb->prefix . 'membership_bio_photos';
    $post_likes_tbl      = $wpdb->prefix . 'membership_forum_post_likes';
    $posts_tbl           = $wpdb->prefix . 'membership_forum_posts';
    $topics_tbl          = $wpdb->prefix . 'membership_forum_topics';
    $members_tbl         = $wpdb->prefix . 'memberships';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT sl.liked_at, sl.like_type, sl.context_id, sl.context_label,
                    sl.target_member_id, m.community_alias AS target_alias, m.community_photo_url AS photo_url
             FROM (
                 SELECT l.liked_at,
                        'profile' AS like_type,
                        l.liked_member_id AS context_id,
                        NULL AS context_label,
                        l.liked_member_id AS target_member_id
                 FROM $likes_tbl l
                 WHERE l.member_id = %d

                 UNION ALL

                 SELECT bpl.liked_at,
                        'photo' AS like_type,
                        bpl.photo_id AS context_id,
                        NULL AS context_label,
                        bp.member_id AS target_member_id
                 FROM $photo_likes_tbl bpl
                 JOIN $bio_photos_tbl bp ON bpl.photo_id = bp.id
                 WHERE bpl.member_id = %d

                 UNION ALL

                 SELECT fpl.liked_at,
                        'post' AS like_type,
                        fp.topic_id AS context_id,
                        t.topic_name AS context_label,
                        fp.member_id AS target_member_id
                 FROM $post_likes_tbl fpl
                 JOIN $posts_tbl fp ON fpl.post_id = fp.id
                 LEFT JOIN $topics_tbl t ON fp.topic_id = t.id
                 WHERE fpl.member_id = %d
             ) sl
             LEFT JOIN $members_tbl m ON sl.target_member_id = m.id
             ORDER BY sl.liked_at DESC
             LIMIT %d OFFSET %d",
            $member_id,
            $member_id,
            $member_id,
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    return $rows ?: array();
}

/**
 * Count total items a member has liked across all sources.
 *
 * @param int $member_id
 * @return int
 */
function mmgr_count_sent_likes( $member_id ) {
    global $wpdb;

    $likes_tbl       = $wpdb->prefix . 'membership_likes';
    $photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
    $post_likes_tbl  = $wpdb->prefix . 'membership_forum_post_likes';

    return
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $likes_tbl WHERE member_id = %d",
            $member_id
        ) ) +
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $photo_likes_tbl WHERE member_id = %d",
            $member_id
        ) ) +
        (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $post_likes_tbl WHERE member_id = %d",
            $member_id
        ) );
}

/**
 * Render a single "things I liked" list item as an HTML string.
 *
 * @param array $like  Row from mmgr_get_sent_likes().
 * @return string
 */
function mmgr_render_sent_like_item( $like ) {
    $alias    = esc_html( $like['target_alias'] ?: 'Member' );
    $time_ago = human_time_diff( strtotime( $like['liked_at'] ), current_time( 'timestamp' ) ) . ' ago';

    switch ( $like['like_type'] ) {
        case 'photo':
            $icon    = '📸';
            $label   = $alias . "'s photo";
            $link    = esc_url( home_url( '/member-community-profile/' ) . '?id=' . (int) $like['target_member_id'] );
            $border  = '#FF2197';
            break;
        case 'post':
            $icon       = '💬';
            $topic_name = $like['context_label'] ? esc_html( mb_substr( $like['context_label'], 0, 30 ) ) : 'a post';
            $label      = 'Post in ' . $topic_name . ' by ' . $alias;
            $link       = esc_url( home_url( '/member-community/' ) . '?topic=' . (int) $like['context_id'] );
            $border     = '#9b51e0';
            break;
        default: // 'profile'
            $icon   = '❤️';
            $label  = $alias . "'s profile";
            $link   = esc_url( home_url( '/member-community-profile/' ) . '?id=' . (int) $like['context_id'] );
            $border = '#0073aa';
            break;
    }

    return '<div style="padding:10px;background:#f9f9f9;border-radius:6px;border-left:3px solid ' . $border . ';cursor:pointer;transition:all 0.3s;" onclick="window.location.href=\'' . $link . '\'">'
        . '<div style="font-weight:bold;color:#333;font-size:14px;">' . $icon . ' ' . esc_html( $label ) . '</div>'
        . '<div style="font-size:12px;color:#666;">' . esc_html( $time_ago ) . '</div>'
        . '</div>';
}

/**
 * AJAX: Load more sent likes (pagination for the activity page "Things I Liked" section).
 */
add_action( 'wp_ajax_nopriv_mmgr_load_sent_likes', function() { do_action( 'wp_ajax_mmgr_load_sent_likes' ); } );
add_action( 'wp_ajax_mmgr_load_sent_likes', function() {
    check_ajax_referer( 'mmgr_load_sent_likes', 'nonce' );

    $member = mmgr_get_current_member();
    if ( ! $member ) {
        wp_send_json_error( 'Not logged in' );
    }

    $per_page = 10;
    $offset   = absint( $_POST['offset'] );

    $likes    = mmgr_get_sent_likes( $member['id'], $offset, $per_page + 1 );
    $has_more = count( $likes ) > $per_page;
    if ( $has_more ) {
        array_pop( $likes );
    }

    ob_start();
    foreach ( $likes as $like ) {
        echo mmgr_render_sent_like_item( $like );
    }
    $html = ob_get_clean();

    wp_send_json_success( array(
        'html'        => $html,
        'has_more'    => $has_more,
        'next_offset' => $offset + $per_page,
    ) );
} );