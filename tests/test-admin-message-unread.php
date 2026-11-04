<?php
// Run with: php tests/test-admin-message-unread.php
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
function current_user_can($capability) {
    global $can_manage;
    return $can_manage && $capability === 'manage_options';
}
function check_admin_referer($action) {
    global $valid_nonce;
    expect($action === 'mmgr_mark_all_admin_messages_read', 'Bulk read uses an action-specific nonce');
    if (!$valid_nonce) throw new RuntimeException('Invalid nonce');
}
function current_time($format) { return '2026-11-04 18:00:00'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_create_nonce($action) { return $action . '-nonce'; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="' . esc_attr($action . '-nonce') . '">'; }
function mmgr_is_conversation_archived($viewer, $member) { return false; }

class UnreadDatabase {
    public $prefix = 'site_2_';
    public $queries = array();
    public $writes = array();
    public $fail_read = false;
    public $fail_write = false;
    public $messages;
    public function __construct() {
        $base = array('from_member_id' => 7, 'to_member_id' => 0, 'read_at' => null,
            'message' => 'Archived unread <script>alert(1)</script>', 'image_url' => null,
            'image_deleted' => 0, 'sent_at' => '2025-01-01 10:00:00', 'member_name' => 'Archived member');
        $this->messages = array(
            array_merge($base, array('id' => 1)),
            array_merge($base, array('id' => 2, 'from_member_id' => 99, 'member_name' => null,
                'message' => 'Deleted sender unread', 'image_url' => 'https://example.test/image.jpg')),
            array_merge($base, array('id' => 3, 'read_at' => '2025-01-02 10:00:00', 'message' => 'Already read')),
            array_merge($base, array('id' => 4, 'from_member_id' => 0, 'to_member_id' => 7, 'message' => 'Outgoing unread')),
            array_merge($base, array('id' => 5, 'to_member_id' => 8, 'message' => 'Private member message')),
        );
        for ($id = 6; $id <= 17; $id++) {
            $this->messages[] = array_merge($base, array('id' => $id, 'message' => 'Older unread ' . $id));
        }
    }
    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace_callback('/%[ds]/', function() use ($arg) {
                return is_int($arg) ? (string) $arg : "'" . addslashes($arg) . "'";
            }, $query, 1);
        }
        return $query;
    }
    public function unread() {
        return array_values(array_filter($this->messages, function($message) {
            return $message['to_member_id'] === 0 && $message['read_at'] === null;
        }));
    }
    public function get_results($query, $format) {
        $this->queries[] = $query;
        if (str_contains($query, 'SELECT msg.*')) {
            expect(str_contains($query, 'LEFT JOIN site_2_memberships') &&
                str_contains($query, 'WHERE msg.to_member_id = 0 AND msg.read_at IS NULL'),
                'Unread list uses badge scope and retains deleted senders');
            expect(!str_contains($query, 'LIMIT') && !str_contains($query, 'membership_conversation_archive'),
                'Unread list has no recent-message limit or archive exclusion');
            return $this->fail_read ? null : $this->unread();
        }
        return array();
    }
    public function get_var($query) {
        $this->queries[] = $query;
        return str_contains($query, 'COUNT(*)') ? count($this->unread()) : null;
    }
    public function query($query) {
        $this->writes[] = $query;
        expect(str_contains($query, 'UPDATE site_2_membership_messages SET read_at =') &&
            str_contains($query, 'WHERE to_member_id = 0 AND read_at IS NULL'),
            'Bulk read updates only unread incoming admin messages using the site prefix');
        if ($this->fail_write) return false;
        $count = count($this->unread());
        foreach ($this->messages as &$message) {
            if ($message['to_member_id'] === 0 && $message['read_at'] === null) {
                $message['read_at'] = current_time('mysql');
            }
        }
        return $count;
    }
}

require __DIR__ . '/../membership-manager-qr/includes/admin-messages.php';
function render_unread($get = array('unread' => '1'), $post = array()) {
    $_GET = $get;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    ob_start();
    try {
        mmgr_admin_messages_page();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$wpdb = new UnreadDatabase();
$html = render_unread();
expect(str_contains($html, 'SHOW ALL UNREAD') && str_contains($html, 'MARK ALL READ'), 'Unread view offers both requested controls');
expect(str_contains($html, 'mmgr_mark_all_admin_messages_read-nonce') && str_contains($html, "return confirm("),
    'Mark all read includes nonce and confirmation');
expect(str_contains($html, 'Archived member') && str_contains($html, 'Deleted Member #99') &&
    str_contains($html, 'Deleted sender unread'), 'Unread view exposes archived and deleted-member messages');
expect(substr_count($html, '<div class="card"') === 14 && str_contains($html, 'Older unread 17'),
    'Every unread message is shown, including those outside the latest ten');
expect(!str_contains($html, 'Already read') && !str_contains($html, 'Outgoing unread') &&
    !str_contains($html, 'Private member message'), 'Read, outgoing and private messages are excluded');
expect(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>alert(1)</script>'),
    'Unread message content is escaped');
expect(str_contains($html, 'View attached image') && str_contains($html, 'member=7') &&
    !str_contains($html, 'member=99'), 'Images are accessible and only existing senders have conversation links');
expect(!$wpdb->writes, 'Showing unread messages does not mark them read');
render_unread(array('unread' => '1', 'member' => 7));
expect(!$wpdb->writes, 'Unread list does not implicitly mark a conversation read even with a member parameter');

$html = render_unread(array());
expect(str_contains($html, 'SHOW ALL UNREAD') && str_contains($html, 'MARK ALL READ'),
    'Normal messages page offers both controls even with an empty active list');

$before = $wpdb->messages;
$html = render_unread(array('unread' => '1'), array('mmgr_mark_all_admin_messages_read' => 1));
expect(str_contains($html, 'All incoming admin messages marked as read') && str_contains($html, 'No unread messages.'),
    'Bulk action reports success and immediately refreshes the unread list');
expect(count($wpdb->writes) === 1 && !$wpdb->unread(), 'One operation clears all incoming unread messages');
expect($wpdb->messages[0]['read_at'] === '2026-11-04 18:00:00' &&
    $wpdb->messages[1]['read_at'] === '2026-11-04 18:00:00', 'Archived and deleted-member messages receive site-local read time');
expect(array_slice($wpdb->messages, 2, 3) === array_slice($before, 2, 3),
    'Existing read timestamps, outgoing receipts and private messages are unchanged');
ob_start();
$actions['admin_footer']();
$footer = ob_get_clean();
expect(str_contains($footer, ".text('')"), 'Menu badge clears immediately when count reaches zero');
$html = render_unread(array('unread' => '1'), array('mmgr_mark_all_admin_messages_read' => 1));
expect(str_contains($html, 'All incoming admin messages marked as read'), 'Repeated bulk read is a successful no-op');

$wpdb = new UnreadDatabase();
$wpdb->fail_write = true;
$html = render_unread(array('unread' => '1'), array('mmgr_mark_all_admin_messages_read' => 1));
expect(str_contains($html, 'could not be marked as read') && count($wpdb->unread()) === 14,
    'Database failure is explicit and leaves unread messages intact');
$wpdb->fail_read = true;
$html = render_unread();
expect(str_contains($html, 'Unread messages could not be loaded') && !str_contains($html, 'No unread messages.'),
    'Read failure is not presented as an empty inbox');

$wpdb = new UnreadDatabase();
$valid_nonce = false;
try {
    render_unread(array('unread' => '1'), array('mmgr_mark_all_admin_messages_read' => 1));
    throw new RuntimeException('Expected nonce rejection');
} catch (RuntimeException $error) {
    expect($error->getMessage() === 'Invalid nonce' && !$wpdb->writes, 'Invalid nonce cannot change read status');
}
$valid_nonce = true;
$can_manage = false;
$html = render_unread(array('unread' => '1'), array('mmgr_mark_all_admin_messages_read' => 1));
expect(str_contains($html, 'not allowed') && !$wpdb->writes && !$wpdb->queries,
    'Users without management permission cannot view or modify admin messages');
echo "Results: $passed passed\n";
