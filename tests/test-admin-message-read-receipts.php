<?php
// Run with: php tests/test-admin-message-read-receipts.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$actions = array();
$can_manage = true;
$valid_nonce = true;
function expect($condition, $description) {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $passed++;
    echo "PASS: $description\n";
}
function add_action($hook, $callback) { global $actions; $actions[$hook] = $callback; }
function current_user_can($capability) { global $can_manage; return $can_manage; }
function check_ajax_referer($action, $field) {
    global $valid_nonce;
    expect($action === 'mmgr_admin_load_more_messages' && $field === 'nonce', 'Checks admin pagination nonce');
    if (!$valid_nonce) throw new RuntimeException('Invalid nonce');
}
function wp_send_json_error($data) { throw new ReceiptResponse(false, $data); }
function wp_send_json_success($data) { throw new ReceiptResponse(true, $data); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function wp_create_nonce($action) { return $action . '-nonce'; }
function wp_nonce_field($action) {}
function get_option($key) { return $key === 'date_format' ? 'd/m/Y' : 'H:i'; }
function mysql2date($format, $date) {
    return (new DateTimeImmutable($date, new DateTimeZone('Europe/London')))->format($format);
}
function current_time($format) { return '2026-10-20 19:00:00'; }
function mmgr_is_conversation_archived($member_id, $other_id) { return false; }
function mmgr_get_conversation_count($member_id, $other_id) { return 12; }
function mmgr_get_conversation($member_id, $other_id, $limit, $offset) {
    global $wpdb;
    expect($member_id === 0 && $other_id === 7 && $limit === 10 && $offset === 10, 'Pagination retains admin conversation scope');
    return $wpdb->messages;
}
class ReceiptResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
class ReceiptDatabase {
    public $prefix = 'wp_';
    public $messages = array();
    public $updates = array();
    public function prepare($query, ...$args) { return $query; }
    public function get_results($query, $format) {
        return strpos($query, 'SELECT *') !== false ? array_reverse($this->messages) : array();
    }
    public function get_var($query) { return null; }
    public function get_row($query, $format) {
        return array('id' => 7, 'name' => 'Recipient', 'photo_url' => '', 'email' => 'member@example.com',
            'member_code' => 'ABC', 'level' => 'Member', 'phone' => '', 'paid' => 1);
    }
    public function update($table, $data, $where) { $this->updates[] = $where; }
}
$wpdb = new ReceiptDatabase();
require __DIR__ . '/../membership-manager-qr/includes/admin-messages.php';
require __DIR__ . '/../membership-manager-qr/includes/ajax-handlers.php';
$base = array('id' => 1, 'from_member_id' => 0, 'message' => 'Admin message',
    'image_url' => null, 'image_deleted' => 0, 'sent_at' => '2026-10-19 08:00:00', 'read_at' => null);
$read = array_replace($base, array('id' => 2, 'from_member_id' => '0', 'read_at' => '2026-10-20 18:45:00'));
$incoming = array_replace($read, array('id' => 3, 'from_member_id' => 7));
$image = array_replace($read, array('id' => 4, 'message' => '', 'image_url' => 'https://example.com/photo.jpg'));
expect(mmgr_admin_message_read_status($base) === 'Unread', 'Unopened messages show Unread without a fabricated timestamp');
expect(mmgr_admin_message_read_status($read) === 'Read on 20/10/2026 18:45', 'Read receipt includes year, date and time in site formats');
expect(mmgr_admin_message_read_status($incoming) === '', 'Incoming messages do not show recipient receipts');
expect(mmgr_admin_message_read_status($image) === mmgr_admin_message_read_status($read), 'Image-only messages have read receipts');
$wpdb->messages = array($base, $read, $incoming, $image);
$_GET = array('member' => 7);
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
mmgr_admin_messages_page();
$html = ob_get_clean();
preg_match_all('/<div class="mmgr-admin-message-time mmgr-admin-message-read-status">(.*?)<\/div>/', $html, $matches);
expect($matches[1] === array('Unread', 'Read on 20/10/2026 18:45', 'Read on 20/10/2026 18:45'), 'Initial view shows receipts only under outgoing messages');
expect($wpdb->updates === array(array('from_member_id' => 7, 'to_member_id' => 0)), 'Viewing as admin does not mark outgoing messages read');
expect($wpdb->messages[1]['read_at'] === '2026-10-20 18:45:00', 'Rendering preserves the recorded first-read time');

function load_earlier_receipts() {
    global $actions;
    try {
        $actions['wp_ajax_mmgr_admin_load_more_messages']();
    } catch (ReceiptResponse $response) {
        return $response;
    }
    throw new RuntimeException('Missing AJAX response');
}
$_POST = array('member_id' => 7, 'offset' => 10);
$response = load_earlier_receipts();
expect($response->success, 'Admin can load earlier messages');
expect(array_column($response->data['messages'], 'read_status') === array('Unread', 'Read on 20/10/2026 18:45', '', 'Read on 20/10/2026 18:45'),
    'Earlier messages use the same status formatting and exclude incoming receipts');
$can_manage = false;
expect(!load_earlier_receipts()->success, 'Non-admin users cannot retrieve read receipts');
$can_manage = true;
$valid_nonce = false;
try {
    load_earlier_receipts();
    throw new RuntimeException('Expected nonce rejection');
} catch (RuntimeException $error) {
    expect($error->getMessage() === 'Invalid nonce', 'Invalid nonce cannot retrieve read receipts');
}
try {
    $actions['wp_ajax_nopriv_mmgr_admin_load_more_messages']();
    throw new RuntimeException('Expected anonymous rejection');
} catch (ReceiptResponse $response) {
    expect(!$response->success, 'Anonymous requests cannot retrieve read receipts');
}
echo "Results: $passed passed\n";
