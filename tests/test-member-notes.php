<?php
// Run with: php tests/test-member-notes.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$valid_nonce = true;
function expect($condition, $description) {
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $passed++;
    echo "PASS: $description\n";
}
function add_action($hook, $callback) {}
function get_option($key, $default) { return '1.10.0'; }
function wp_verify_nonce($nonce, $action) { global $valid_nonce; return $valid_nonce; }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return $value; }
function get_current_user_id() { return 3; }
function current_time($format) { return '2026-10-06 12:00:00'; }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function selected($value, $expected) {}
function checked($value, $expected) {}
function wp_nonce_field($action, $name) {}
function wp_create_nonce($action) { return 'test-nonce'; }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_userdata($id) { return false; }
function mmgr_generate_member_code($name) { return 'TEST42'; }
function mmgr_generate_qr_file($code) {}
function mmgr_send_welcome_email($id) {}
function mmgr_send_welcome_pm($id) {}

class NotesDatabase {
    public $prefix = 'site_2_';
    public $insert_id = 0;
    public $table_exists = false;
    public $creation_fails = false;
    public $note_fails = false;
    public $member_fails = false;
    public $update_result = 1;
    public $creates = 0;
    public $checks = array();
    public $notes = array();
    public $member_writes = 0;
    public $member = array(
        'id' => 7, 'member_code' => 'TEST7', 'email' => 'member@example.com',
    );
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, ...$args) {
        foreach ($args as $arg) {
            $query = preg_replace_callback('/%[ds]/', function () use ($arg) {
                return is_int($arg) ? (string) $arg : "'" . addslashes($arg) . "'";
            }, $query, 1);
        }
        return $query;
    }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function get_var($query) {
        $this->checks[] = $query;
        return $this->table_exists ? $this->prefix . 'membership_member_notes' : null;
    }
    public function query($query) {
        expect(str_contains($query, 'CREATE TABLE IF NOT EXISTS `' . $this->prefix . 'membership_member_notes`'), 'Creates the active site notes table only');
        expect(str_contains($query, $this->get_charset_collate()), 'Uses the WordPress database charset');
        $this->creates++;
        $this->table_exists = !$this->creation_fails;
        return $this->creation_fails ? false : 1;
    }
    public function get_row($query, $format) { return $this->member; }
    public function get_results($query, $format) {
        return str_contains($query, 'membership_member_notes') ? $this->notes : array();
    }
    public function update($table, $data, $where) {
        $this->member_writes++;
        return $this->member_fails ? false : $this->update_result;
    }
    public function insert($table, $data) {
        if ($table === $this->prefix . 'memberships') {
            $this->member_writes++;
            if ($this->member_fails) {
                return false;
            }
            $this->insert_id = 42;
            $this->member = array_merge($this->member, $data, array('id' => 42));
            return 1;
        }
        expect($table === $this->prefix . 'membership_member_notes', 'Note insert uses the checked table');
        if (!$this->table_exists || $this->note_fails) {
            return false;
        }
        $this->notes[] = $data;
        $this->insert_id = 99;
        return 1;
    }
}

require __DIR__ . '/../membership-manager-qr/includes/database.php';

$wpdb = new NotesDatabase();
mmgr_check_database();
expect($wpdb->table_exists, 'Current-version installations repair missing notes tables');
expect(str_contains($wpdb->checks[0], 'site\\\\_2\\\\_membership\\\\_member\\\\_notes'), 'Existence check escapes LIKE wildcards in the exact site table name');
expect(mmgr_ensure_member_notes_table(), 'Existing table passes verification');
expect($wpdb->creates === 1, 'Existing notes table is not recreated');
$wpdb->table_exists = false;
$wpdb->creation_fails = true;
expect(!mmgr_ensure_member_notes_table(), 'Failed table creation returns failure');
$wpdb->creation_fails = false;
expect(mmgr_ensure_member_notes_table(), 'Failed creation can be retried');

function render_member_form($editing, $note) {
    global $wpdb;
    $_GET = $editing ? array('id' => 7) : array();
    $_POST = array_fill_keys(array(
        'first_name', 'last_name', 'partner_first_name', 'partner_last_name',
        'email', 'phone', 'sex', 'partner_sex', 'age', 'partner_age',
        'level', 'start_date', 'expire_date',
    ), '');
    $_POST['mmgr_save_member'] = '1';
    $_POST['member_nonce'] = 'test-nonce';
    $_POST['admin_note'] = addslashes($note);
    ob_start();
    include __DIR__ . '/../membership-manager-qr/includes/admin/add-edit-member.php';
    return ob_get_clean();
}

$wpdb = new NotesDatabase();
$wpdb->update_result = 0;
$text = "Member's note\nSecond line";
$html = render_member_form(true, $text);
expect(count($wpdb->notes) === 1 && $wpdb->notes[0]['note'] === $text, 'Unchanged member still saves a multiline note without added slashes');
expect($wpdb->notes[0]['member_id'] === 7 && $wpdb->notes[0]['created_by'] === 3, 'Note retains member and author IDs');
expect(str_contains($html, 'Member updated successfully!'), 'Successful note save reports success');
expect(str_contains($html, 'Second line'), 'Saved note appears in the note log');
expect(str_contains($html, 'rows="4"></textarea>'), 'Successful save clears the note input');

$wpdb->note_fails = true;
$html = render_member_form(true, 'Unsaved & private');
expect(str_contains($html, 'admin note could not be saved'), 'Insert failure is shown to the admin');
expect(!str_contains($html, 'Member updated successfully!'), 'Note failure does not show an overall success notice');
expect(str_contains($html, 'rows="4">Unsaved &amp; private</textarea>'), 'Failed note remains safely escaped for retry');

$wpdb = new NotesDatabase();
$wpdb->creation_fails = true;
$html = render_member_form(true, 'Keep this note');
expect(str_contains($html, 'admin note could not be saved') && !$wpdb->notes, 'Table creation failure cannot silently lose a note');

$wpdb = new NotesDatabase();
$html = render_member_form(false, 'New member note');
expect($wpdb->notes[0]['member_id'] === 42, 'New member note uses the member insert ID, not the note insert ID');
expect(str_contains($html, 'Edit member'), 'New member save still completes');

$wpdb = new NotesDatabase();
$wpdb->note_fails = true;
$html = render_member_form(false, 'Retry on created member');
expect(str_contains($html, 'action="https://example.com/wp-admin/admin.php?page=membership_add&amp;id=42"'), 'New member note failure retries on the created account');
expect(str_contains($html, 'rows="4">Retry on created member</textarea>'), 'New member failed note is preserved');
expect(!str_contains($html, 'Member added successfully!'), 'New member note failure suppresses overall success');
expect(!str_contains($html, 'name="mmgr_regenerate_qr"'), 'Separate QR forms cannot discard a pending note after creation');

foreach (array(true, false) as $editing) {
    $wpdb = new NotesDatabase();
    $wpdb->member_fails = true;
    $html = render_member_form($editing, 'Do not orphan this note');
    expect(!$wpdb->notes && $wpdb->creates === 0, 'Failed member write does not write an orphaned note');
    expect(str_contains($html, 'Member could not be saved'), 'Failed member write reports an error');
}

$wpdb = new NotesDatabase();
$html = render_member_form(true, '');
expect(!$wpdb->notes && $wpdb->creates === 0, 'Empty notes do not create entries');
$valid_nonce = false;
$html = render_member_form(true, 'Unauthorized note');
expect($wpdb->member_writes === 1 && !$wpdb->notes, 'Invalid nonce prevents member and note writes');

echo "\nResults: $passed passed, 0 failed\n";
