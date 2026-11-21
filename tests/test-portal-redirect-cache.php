<?php
// Run with: php tests/test-portal-redirect-cache.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$actions = $filters = $shortcodes = array();
$member = null;
$page_slug = '';
$page_content = '';
$is_admin_request = false;
$session_created = false;
$logged_out = false;
$passed = 0;

function expect($condition, $message) {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $passed++;
}
function add_action($name, $callback, ...$args) {}
function add_filter($name, $callback, ...$args) { global $filters; $filters[$name] = $callback; }
function add_shortcode($name, $callback) { global $shortcodes; $shortcodes[$name] = $callback; }
function home_url($path) { return 'https://example.test' . $path; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($value) { return $value; }
function wp_unslash($value) { return $value; }
function add_query_arg($key, $value, $url = null) {
    $url = $url ?? 'https://example.test/member-messages/?chat=3';
    return $url . (strpos($url, '?') === false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode($value);
}
function nocache_headers() {}
function current_user_can($capability) { return false; }
function mmgr_get_current_member() { global $member; return $member; }
function mmgr_create_member_session($id, $email = '') { global $session_created; $session_created = true; }
function mmgr_verify_member_login($email, $password) { return array('id' => 7, 'email' => $email, 'member_code' => 'ABC'); }
function mmgr_log_login_attempt(...$args) {}
function mmgr_logout_member() { global $logged_out; $logged_out = true; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function sanitize_email($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function get_option($key, $default = '') { return $default; }
function mmgr_validate_age($dob) { return true; }
function mmgr_generate_member_code($name) { return 'NEWCODE'; }
function mmgr_generate_qr_file($code) {}
function mmgr_send_welcome_email($id) {}
function mmgr_send_welcome_pm($id) {}
function is_admin() { global $is_admin_request; return $is_admin_request; }
function is_page($slugs) { global $page_slug; return in_array($page_slug, $slugs, true); }
function get_queried_object() { global $page_content; return (object) array('post_content' => $page_content); }
function has_shortcode($content, $shortcode) { return strpos($content, '[' . $shortcode . ']') !== false; }
class TestPortalDatabase {
    public $prefix = 'wp_';
    public $insert_id = 7;
    public $registration = false;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $format) { return $this->registration ? null : array('id' => 7, 'email' => 'member@example.test'); }
    public function insert($table, $data) { return 1; }
}
$wpdb = new TestPortalDatabase();
require __DIR__ . '/../membership-manager-qr/includes/portal-cache.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-shortcodes.php';
require __DIR__ . '/../membership-manager-qr/includes/shortcodes.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array();
$protected = array('mmgr_member_dashboard', 'mmgr_member_events', 'mmgr_member_activity',
    'mmgr_member_profile', 'mmgr_member_community', 'mmgr_member_messages',
    'mmgr_members_directory', 'mmgr_member_community_profile', 'mmgr_member_help');
foreach ($protected as $shortcode) {
    expect(strpos($shortcodes[$shortcode](), '/member-login/') !== false, "$shortcode offers login without redirect");
}

$member = array('id' => 7, 'email' => 'member@example.test', 'member_code' => 'ABC');
$login = $shortcodes['mmgr_member_login']();
expect(strpos($login, 'usercod=ABC') !== false, 'Existing session gets a dashboard link');
foreach ($protected as $shortcode) {
    $notice = $shortcodes[$shortcode]();
    expect(strpos($notice, 'usercod=ABC') !== false, "$shortcode requires the member-specific URL");
}
$_GET['usercod'] = 'WRONG';
$notice = mmgr_enforce_usercod($member);
expect(strpos($notice, 'chat=3&amp;usercod=ABC') !== false, 'Corrected link preserves other arguments');
$_GET['usercod'] = 'ABC';
expect(mmgr_enforce_usercod($member) === '', 'Matching member code proceeds without a redirect');
$_GET['action'] = 'logout';
expect(strpos($shortcodes['mmgr_member_dashboard'](), 'logged out') !== false && $logged_out, 'Logout completes without a redirect');

$member = null;
$_GET = array();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array('member_login' => '1', 'login_nonce' => 'valid', 'email' => 'member@example.test', 'password' => 'secret');
$login = $shortcodes['mmgr_member_login']();
expect($session_created && strpos($login, 'usercod=ABC') !== false, 'Successful login creates a session and offers a dashboard link');

$wpdb->registration = true;
$_POST = array('mmgr_register' => '1', 'reg_nonce' => 'valid', 'first_name' => 'Test',
    'last_name' => 'Member', 'email' => 'new@example.test', 'phone' => '123',
    'sex' => 'Female', 'dob' => '1990-01-01', 'level' => 'Single');
$registration = $shortcodes['membership_registration'](array());
expect(strpos($registration, 'Registration successful!') !== false && strpos($registration, '/member-login/') !== false, 'Registration offers a continuation link');
expect(strpos($registration, 'window.location') === false && strpos($registration, '<form') === false, 'Registration does not navigate automatically or offer the completed form again');

$headers = array('Cache-Control' => 'public, max-age=3600', 'ETag' => 'cached');
$result = $filters['wp_headers']($headers);
expect($result === $headers, 'Unrelated public pages retain their cache policy');
$page_slug = 'member-dashboard';
$result = $filters['wp_headers']($headers);
expect(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE, 'Portal requests tell WordPress caches not to cache');
expect(strpos($result['Cache-Control'], 'no-store') !== false && $result['X-Accel-Expires'] === '0' && !isset($result['ETag']), 'Portal response prohibits caching');
expect(strpos($filters['nocache_headers'](array())['Cache-Control'], 'private') !== false, 'Later no-cache headers remain private');
$page_slug = '';
$page_content = '[mmgr_member_messages]';
expect(mmgr_is_portal_cache_request(), 'Portal shortcodes on custom pages are not cached');
$page_content = '';
$_COOKIE['mmgr_session'] = 'test';
expect(mmgr_is_portal_cache_request(), 'Member-session requests are not cached');
$is_admin_request = true;
$result = $filters['wp_headers']($headers);
expect($result === $headers, 'Portal cache filter does not change admin responses');

echo "Results: $passed passed\n";
