<?php
// Run with: php tests/test-admin-message-archive.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$actions = array();
$can_manage = true;
$valid_nonce = true;
$nonce_action = '';
function expect($condition, $description) {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $passed++;
    echo "PASS: $description\n";
}
function add_action($hook, $callback) { global $actions; $actions[$hook] = $callback; }
function current_user_can($capability) {
    global $can_manage;
    expect($capability === 'manage_options', 'Requires admin management capability');
    return $can_manage;
}
function check_ajax_referer($action, $field) {
    global $valid_nonce, $nonce_action;
    $nonce_action = $action;
    expect($field === 'nonce', 'Validates the submitted nonce field');
    if (!$valid_nonce) throw new RuntimeException('Invalid nonce');
}
function current_time($format) { return $format === 'timestamp' ? 1792490400 : '2026-10-20 10:00:00'; }
function wp_send_json_error($data) { throw new ArchiveResponse(false, $data); }
function wp_send_json_success($data) { throw new ArchiveResponse(true, $data); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_create_nonce($action) { return $action . '-nonce'; }
function human_time_diff($from, $to) { return '1 hour'; }

class ArchiveResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
class ArchiveDatabase {
    public $prefix = 'site_2_';
    public $queries = array();
    public $result = 2;
    public $conversations = array();
    public $archived = array();
    public function prepare($query, ...$args) {
        return str_replace('%s', "'" . $args[0] . "'", $query);
    }
    public function query($query) { $this->queries[] = $query; return $this->result; }
    public function get_results($query, $format) { return $this->conversations; }
    public function get_var($query) { return $this->prefix . 'membership_conversation_archive'; }
    public function get_col($query) { return $this->archived; }
}

$wpdb = new ArchiveDatabase();
require __DIR__ . '/../membership-manager-qr/includes/admin-messages.php';
$hook = 'wp_ajax_mmgr_admin_archive_all_conversations';
expect(isset($actions[$hook]), 'Registers authenticated bulk archive action');
expect(!isset($actions['wp_ajax_nopriv_mmgr_admin_archive_all_conversations']), 'No anonymous bulk archive action');

function archive_all() {
    global $actions;
    try {
        $actions['wp_ajax_mmgr_admin_archive_all_conversations']();
    } catch (ArchiveResponse $response) {
        return $response;
    }
    throw new RuntimeException('Missing AJAX response');
}

$response = archive_all();
expect($response->success, 'Reports successful bulk archive');
expect($nonce_action === 'mmgr_admin_archive_all_conversations', 'Uses action-specific nonce');
expect(count($wpdb->queries) === 1, 'Archives in a single database operation');
$query = $wpdb->queries[0];
expect(strpos($query, 'INSERT IGNORE INTO site_2_membership_conversation_archive') !== false, 'Uses prefixed archive table without deleting messages');
expect(strpos($query, "SELECT DISTINCT 0, m.id, '2026-10-20 10:00:00'") !== false, 'Archives each partner once for admin only');
expect(strpos($query, 'INNER JOIN site_2_memberships m') !== false, 'Matches sidebar member scope');
expect(strpos($query, 'CASE WHEN msg.from_member_id = 0 THEN msg.to_member_id') !== false, 'Includes outgoing admin conversations');
expect(strpos($query, 'ELSE msg.from_member_id END = m.id') !== false, 'Includes incoming admin conversations');
expect(strpos($query, 'WHERE (msg.from_member_id = 0 OR msg.to_member_id = 0)') !== false, 'Excludes member-to-member messages');
expect(strpos($query, 'UPDATE') === false && strpos($query, 'DELETE') === false, 'INSERT IGNORE preserves existing archives without changing member-side archives');
expect(substr_count($query, 'site_2_membership_conversation_archive') === 1, 'Does not read the insert target in a MySQL subquery');

$wpdb->result = 0;
expect(archive_all()->success, 'No active conversations is a successful no-op');
$wpdb->result = false;
$response = archive_all();
expect(!$response->success && strpos($response->data['message'], 'try again') !== false, 'Reports database failure rather than false success');

$wpdb->queries = array();
$can_manage = false;
expect(!archive_all()->success, 'Rejects users without permission');
expect(empty($wpdb->queries), 'Unauthorized request performs no writes');
$can_manage = true;
$valid_nonce = false;
try {
    archive_all();
    throw new RuntimeException('Nonce rejection expected');
} catch (RuntimeException $error) {
    expect($error->getMessage() === 'Invalid nonce', 'Rejects invalid nonce');
}
expect(empty($wpdb->queries), 'Invalid nonce performs no writes');

$_GET = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
function render_messages() {
    ob_start();
    mmgr_admin_messages_page();
    return ob_get_clean();
}
$html = render_messages();
expect(strpos($html, 'onclick="archiveAllAdminConversations(this)"') === false, 'Hides bulk button when no active conversations exist');
$wpdb->conversations = array(array(
    'member_id' => 7, 'name' => 'Member', 'photo_url' => '', 'email' => 'member@example.com',
    'last_message_time' => '2026-10-20 09:00:00', 'unread_count' => 0,
));
$html = render_messages();
expect(strpos($html, 'onclick="archiveAllAdminConversations(this)"') !== false, 'Shows Archive All in active conversation list');
expect(strpos($html, 'data-nonce="mmgr_admin_archive_all_conversations-nonce"') !== false, 'Renders bulk action nonce');
$wpdb->archived = array(7);
$html = render_messages();
expect(strpos($html, 'onclick="archiveAllAdminConversations(this)"') === false, 'Does not offer bulk archive for archived-only conversations');
expect(strpos($html, '&tab=archived') !== false, 'Retains archived conversation navigation');

echo "Results: $passed passed\n";
