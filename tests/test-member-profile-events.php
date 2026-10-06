<?php
// Run with: php tests/test-member-profile-events.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$actions = array();
$current_member = null;
$valid_nonce = true;
$passed = 0;

function add_action($hook, $callback) {
    global $actions;
    $actions[$hook][] = $callback;
}
function add_shortcode($tag, $callback) {}
function do_action($hook) {
    global $actions;
    foreach ($actions[$hook] ?? array() as $callback) {
        $callback();
    }
}
function mmgr_get_current_member() {
    global $current_member;
    return $current_member;
}
function home_url($path) { return 'https://example.com' . $path; }
function admin_url($path) { return home_url('/wp-admin/' . $path); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_html($value); }
function esc_js($value) { return addslashes($value); }
function esc_sql($value) { return addslashes($value); }
function wp_kses_post($value) { return $value; }
function get_option($key, $default) { return $default; }
function wp_nonce_url($url, $action) { return $url; }
function wp_create_nonce($action) { return 'test-nonce'; }
function mmgr_get_pending_friend_request_count($id) { return 0; }
function current_time($format) { return '2026-10-06 12:00:00'; }
function check_ajax_referer($action, $key) {
    global $valid_nonce;
    if (!$valid_nonce) {
        throw new RuntimeException('Invalid nonce');
    }
}
function wp_send_json_error($data) {
    throw new RuntimeException($data);
}
function wp_send_json_success($data) {
    throw new RsvpResponse($data);
}

class RsvpResponse extends RuntimeException {
    public $data;
    public function __construct($data) {
        $this->data = $data;
        parent::__construct('success');
    }
}

class TestDatabase {
    public $prefix = 'wp_';
    public $members = array();
    public $bio_tables_exist = true;
    public $fetlife_rows = array();
    public $event_exists = true;
    public $rsvp = null;
    public $writes = 0;
    public $fetlife_queries = 0;

    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace('/%[ds]/', is_numeric($arg) ? (string) $arg : "'" . $arg . "'", $query, 1);
        }
        return $query;
    }
    public function get_var($query) {
        if (str_starts_with($query, 'SHOW TABLES LIKE')) {
            preg_match("/'([^']+)'/", $query, $match);
            return $this->bio_tables_exist && str_contains($match[1], 'membership_bio_') ? $match[1] : null;
        }
        if (str_contains($query, 'FROM wp_membership_event_rsvps')) {
            return $this->rsvp ? 1 : null;
        }
        if (str_contains($query, 'FROM wp_membership_events')) {
            return $this->event_exists ? 10 : null;
        }
        return 0;
    }
    public function get_results($query, $format) {
        if (str_contains($query, 'SELECT * FROM wp_memberships')) {
            return $this->members;
        }
        if (str_contains($query, 'INNER JOIN wp_membership_bio_fields')) {
            $this->fetlife_queries++;
            expect(str_contains($query, "LOWER(TRIM(f.field_name)) = 'fetlife name'"), 'Fetlife field matched by name');
            expect(str_contains($query, 'f.active = 1'), 'Only active bio fields used');
            return $this->fetlife_rows;
        }
        return array();
    }
    public function insert($table, $data) {
        $this->rsvp = $data;
        $this->writes++;
        return 1;
    }
    public function delete($table, $where) {
        $this->rsvp = null;
        $this->writes++;
        return 1;
    }
}

function expect($condition, $description) {
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $passed++;
    echo "PASS: $description\n";
}

require __DIR__ . '/../membership-manager-qr/includes/admin-menu.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-shortcodes.php';

$wpdb = new TestDatabase();
$member = array(
    'id' => 7, 'name' => 'Test Member', 'member_code' => 'TEST7',
    'level' => 'Single', 'paid' => 0, 'email' => 'member@example.com', 'phone' => '',
);
$wpdb->members = array($member);
$wpdb->fetlife_rows = array(
    array('member_id' => 7, 'field_value' => '   '),
    array('member_id' => 7, 'field_value' => '<script>alert("name")</script>'),
    array('member_id' => 7, 'field_value' => 'Duplicate'),
);
ob_start();
mmgr_members_page();
$html = ob_get_clean();
expect(str_contains($html, 'Fetlife Name: &lt;script&gt;'), 'Fetlife Name is displayed and escaped');
expect(!str_contains($html, '<script>alert("name")</script>'), 'Profile value cannot inject markup');
expect(!str_contains($html, 'Fetlife Name: Duplicate'), 'First nonempty value used for duplicate fields');
expect(strpos($html, 'Test Member') < strpos($html, 'Fetlife Name:'), 'Fetlife Name appears below member name');
expect($wpdb->fetlife_queries === 1, 'Names fetched with one bulk query');

$wpdb->fetlife_rows = array();
ob_start();
mmgr_members_page();
$html = ob_get_clean();
expect(!str_contains($html, 'Fetlife Name:'), 'No empty Fetlife Name line');
$wpdb->bio_tables_exist = false;
$queries_before = $wpdb->fetlife_queries;
ob_start();
mmgr_members_page();
ob_end_clean();
expect($wpdb->fetlife_queries === $queries_before, 'Missing bio tables handled without querying values');

$html = mmgr_get_portal_navigation('events', $member);
expect(str_contains($html, 'Please fill in your'), 'Incomplete profile prompts member on Events page');
expect(str_contains($html, '/member-profile/?usercod=TEST7'), 'Prompt links to member-specific PROFILE');
expect(!str_contains(mmgr_get_portal_navigation('profile', $member), 'Please fill in your'), 'No reminder on profile editor');
expect(!str_contains(mmgr_get_portal_navigation('events'), 'Please fill in your'), 'No prompt for visitors');
$complete = $member + array('community_alias' => 'Alias', 'community_bio' => 'Bio', 'community_photo_url' => 'photo.jpg');
expect(!str_contains(mmgr_get_portal_navigation('dashboard', $complete), 'Please fill in your'), 'Complete profiles not prompted');
foreach (array('community_alias', 'community_bio', 'community_photo_url') as $field) {
    $incomplete = $complete;
    $incomplete[$field] = '';
    expect(str_contains(mmgr_get_portal_navigation('dashboard', $incomplete), 'Please fill in your'), "Missing $field prompts completion");
}

function toggle_rsvp($expected_error = null) {
    try {
        do_action('wp_ajax_nopriv_mmgr_toggle_event_rsvp');
    } catch (RsvpResponse $response) {
        expect($expected_error === null, 'RSVP succeeds when permitted');
        return $response->data;
    } catch (RuntimeException $error) {
        expect($error->getMessage() === $expected_error, 'RSVP rejected: ' . $expected_error);
        return null;
    }
    throw new RuntimeException('RSVP did not return a response');
}

expect(isset($actions['wp_ajax_nopriv_mmgr_toggle_event_rsvp']), 'RSVP available to custom member sessions without WordPress login');
$_POST = array('event_id' => 10, 'nonce' => 'test-nonce', 'member_id' => 999);
$current_member = $member;
$response = toggle_rsvp();
expect($response['going'] === true, 'Unpaid member can mark an event as going');
expect($wpdb->rsvp['member_id'] === 7, 'RSVP belongs to session member, not submitted member ID');
$response = toggle_rsvp();
expect($response['going'] === false && $wpdb->rsvp === null, 'Unpaid member can remove RSVP');
$current_member['paid'] = 1;
expect(toggle_rsvp()['going'] === true, 'Paid member can still RSVP');

$writes_before = $wpdb->writes;
$current_member = null;
toggle_rsvp('Not logged in');
$current_member = $member;
$valid_nonce = false;
toggle_rsvp('Invalid nonce');
$valid_nonce = true;
$_POST['event_id'] = 0;
toggle_rsvp('Invalid event');
$_POST['event_id'] = 10;
$wpdb->event_exists = false;
toggle_rsvp('Event not found');
expect($wpdb->writes === $writes_before, 'Rejected requests do not modify RSVPs');

echo "\nResults: $passed passed, 0 failed\n";
