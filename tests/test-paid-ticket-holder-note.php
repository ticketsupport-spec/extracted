<?php
// Run with: php tests/test-paid-ticket-holder-note.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$passed = 0;

function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return $value; }
function wp_kses_post($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return '/wp-admin/' . $path; }
function wp_nonce_field($action, $name) {}
function wp_editor($value, $id, $settings) {}
function checked($value, $expected) {}
function mmgr_get_event_ticket_prices($event) { return array(); }

class NoteDatabase {
    public $prefix = 'wp_';
    public $events = array();
    public $writes = 0;
    public function get_var($query) { return 'wp_membership_events'; }
    public function prepare($query, $id) { return str_replace('%d', (int) $id, $query); }
    public function get_row($query, $format) {
        preg_match('/id = (\d+)/', $query, $match);
        return $this->events[(int) $match[1]] ?? null;
    }
    public function get_results($query, $format) { return array(); }
    public function insert($table, $data) {
        $this->events[1] = $data + array('id' => 1);
        $this->writes++;
        return 1;
    }
    public function update($table, $data, $where) {
        $this->events[$where['id']] = $data + array('id' => $where['id']);
        $this->writes++;
        return 1;
    }
}

function expect($condition, $description) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $description); }
    $passed++;
    echo "PASS: $description\n";
}
function render_admin_event() {
    ob_start();
    include __DIR__ . '/../membership-manager-qr/includes/admin/events-page.php';
    return ob_get_clean();
}

$wpdb = new NoteDatabase();
$_GET = array();
$_POST = array(
    'save_event' => 1, 'event_nonce' => 'valid',
    'event_name' => 'Private party', 'event_date' => '2099-10-31',
    'description' => '', 'active' => 1,
    'ticket_paid_holder_note' => addslashes("Host's address\n<b>123 Secret Street</b>"),
);
render_admin_event();
expect($wpdb->events[1]['ticket_paid_holder_note'] === "Host's address\n123 Secret Street", 'New event saves an unslashed, plain-text multiline note');
$wpdb->events[2] = array('id' => 2, 'ticket_paid_holder_note' => 'Different party');
$_POST['event_id'] = 1;
$_POST['ticket_paid_holder_note'] = 'Updated address';
render_admin_event();
expect($wpdb->events[1]['ticket_paid_holder_note'] === 'Updated address', 'Event edit persists an updated note');
expect($wpdb->events[2]['ticket_paid_holder_note'] === 'Different party', 'Editing one event does not alter another note');
$_POST['ticket_paid_holder_note'] = '';
render_admin_event();
expect($wpdb->events[1]['ticket_paid_holder_note'] === '', 'Event edit can clear the note');
$writes = $wpdb->writes;
$_POST['event_nonce'] = 'invalid';
render_admin_event();
expect($wpdb->writes === $writes, 'Invalid nonce cannot save a note');

$_POST = array();
$_GET['edit'] = 1;
$wpdb->events[1]['ticket_paid_holder_note'] = '</textarea><script>alert(1)</script>';
$html = render_admin_event();
expect(str_contains($html, 'PAID TICKET HOLDER NOTE'), 'Event editor shows the requested field label');
expect(str_contains($html, '&lt;/textarea&gt;&lt;script&gt;'), 'Stored note is escaped in the editing textarea');
expect(!str_contains($html, '<script>alert(1)</script>'), 'Stored note cannot inject editor markup');

foreach (array('database.php', 'admin/events-page.php') as $file) {
    $source = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/' . $file);
    expect(str_contains($source, 'ticket_paid_holder_note TEXT NULL DEFAULT NULL'), 'New events schema includes note: ' . $file);
}
$source = file_get_contents(__DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php');
expect(str_contains($source, 'ADD COLUMN `ticket_paid_holder_note` TEXT NULL DEFAULT NULL'), 'Existing events have a note column migration');

echo "\nResults: $passed passed, 0 failed\n";
