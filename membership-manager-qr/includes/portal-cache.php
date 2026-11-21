<?php
if (!defined('ABSPATH')) exit;

if (!empty($_COOKIE['mmgr_session']) && !defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

function mmgr_is_portal_cache_request() {
    if (!empty($_COOKIE['mmgr_session'])) {
        return true;
    }

    $slugs = array(
        'member-registration', 'member-setup', 'member-login', 'member-dashboard',
        'member-activity', 'member-profile', 'member-community', 'members-directory',
        'member-community-profile', 'member-messages', 'member-events',
        'member-code-of-conduct', 'member-help',
    );
    if (is_page($slugs)) {
        return true;
    }

    $page = get_queried_object();
    if (empty($page->post_content)) {
        return false;
    }
    foreach (array(
        'membership_registration', 'mmgr_password_setup', 'mmgr_member_login',
        'mmgr_upcoming_events', 'mmgr_portal_footer',
        'mmgr_member_dashboard', 'mmgr_member_activity', 'mmgr_member_profile',
        'mmgr_member_community', 'mmgr_members_directory', 'mmgr_member_community_profile',
        'mmgr_member_messages', 'mmgr_member_events', 'mmgr_member_help',
        'membership_code_of_conduct',
    ) as $shortcode) {
        if (has_shortcode($page->post_content, $shortcode)) {
            return true;
        }
    }
    return false;
}

function mmgr_portal_no_cache_headers($headers) {
    if (empty($GLOBALS['mmgr_no_cache_request'])) {
        return $headers;
    }
    $headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
    $headers['Pragma'] = 'no-cache';
    $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
    $headers['X-Accel-Expires'] = '0';
    unset($headers['Last-Modified'], $headers['ETag']);
    return $headers;
}

add_filter('nocache_headers', 'mmgr_portal_no_cache_headers');
add_filter('wp_headers', function($headers) {
    if (!is_admin() && mmgr_is_portal_cache_request()) {
        $GLOBALS['mmgr_no_cache_request'] = true;
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        return mmgr_portal_no_cache_headers($headers);
    }
    return $headers;
});
