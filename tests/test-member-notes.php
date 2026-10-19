<?php
// Run with: php tests/test-member-notes.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$valid_nonce = true;
$can_manage_notes = true;
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
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return $value; }
function get_current_user_id() { return 3; }
function current_time($format) { return '2026-10-06 12:00:00'; }
function wp_send_json_error($data) { throw new JsonResponse($data); }
function wp_send_json_success($data) { throw new JsonResponse($data, true); }
function absint($value) { return abs((int) $value); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function selected($value, $expected) {}
function checked($value, $expected = true) {}
function current_user_can($capability) { global $can_manage_notes; return $can_manage_notes; }
function wp_nonce_field($action, $name) {}
function wp_create_nonce($action) { return 'test-nonce'; }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_userdata($id) { return false; }
function mmgr_generate_member_code($name) { return 'TEST42'; }
function mmgr_generate_qr_file($code) {}
function mmgr_send_welcome_email($id) {}
function mmgr_send_welcome_pm($id) {}

class JsonResponse extends RuntimeException {
    public $data;
    public $success;
    public function __construct($data, $success = false) { $this->data = $data; $this->success = $success; }
}

class NotesDatabase {
    public $prefix = 'site_2_';
    public $insert_id = 0;
    public $table_exists = false;
    public $legacy_schema = array();
    public $legacy_notes = array();
    public $admin_columns = array('id', 'member_id', 'note', 'created_by', 'created_at', 'hidden');
    public $rename_fails = false;
    public $renames = 0;
    public $creation_fails = false;
    public $hidden_column_add_fails = false;
    public $note_fails = false;
    public $member_fails = false;
    public $update_result = 1;
    public $creates = 0;
    public $checks = array();
    public $notes = array();
    public $admin_updates = array();
    public $admin_deletions = array();
    public $member_writes = 0;
    public $ticket_options = array();
    public $ticket_writes = array();
    public $ticket_fails = false;
    public $queries = array();
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
        if (str_contains($query, 'membership\\\\_admin\\\\_notes')) {
            return $this->table_exists ? $this->prefix . 'membership_admin_notes' : null;
        }
        return $this->legacy_schema ? $this->prefix . 'membership_member_notes' : null;
    }
    public function get_col($query) {
        return str_contains($query, $this->prefix . 'membership_admin_notes') ? $this->admin_columns : $this->legacy_schema;
    }
    public function query($query) {
        if (str_starts_with($query, 'RENAME TABLE')) {
            expect($query === "RENAME TABLE `{$this->prefix}membership_member_notes` TO `{$this->prefix}membership_admin_notes`", 'Migration renames only the current site legacy admin table');
            $this->renames++;
            if ($this->rename_fails) {
                return false;
            }
            $this->table_exists = true;
            $this->notes = $this->legacy_notes;
            $this->admin_columns = $this->legacy_schema;
            $this->legacy_schema = array();
            $this->legacy_notes = array();
            return 1;
        }
        if (str_starts_with($query, 'ALTER TABLE')) {
            expect(str_contains($query, 'ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0'), 'Adds the hidden flag to existing admin notes');
            if ($this->hidden_column_add_fails) {
                return false;
            }
            $this->admin_columns[] = 'hidden';
            return 1;
        }
        expect(str_contains($query, 'CREATE TABLE IF NOT EXISTS `' . $this->prefix . 'membership_admin_notes`'), 'Creates the active site admin notes table only');
        expect(str_contains($query, 'hidden TINYINT(1) NOT NULL DEFAULT 0'), 'New admin notes tables include the hidden flag');
        expect(str_contains($query, $this->get_charset_collate()), 'Uses the WordPress database charset');
        $this->creates++;
        $this->table_exists = !$this->creation_fails;
        $this->admin_columns = array('id', 'member_id', 'note', 'created_by', 'created_at', 'hidden');
        return $this->creation_fails ? false : 1;
    }
    public function get_row($query, $format) { return $this->member; }
    public function get_results($query, $format) {
        $this->queries[] = $query;
        if (str_contains($query, 'LEFT JOIN') && str_contains($query, 'membership_member_event_tickets')) {
            return $this->ticket_options;
        }
        if (str_contains($query, 'membership_admin_notes')) {
            $notes = $this->notes;
            if (str_contains($query, 'hidden = 0')) {
                $notes = array_filter($notes, function($note) { return empty($note['hidden']); });
            }
            return array_values($notes);
        }
        return array();
    }
    public function update($table, $data, $where, $format = null, $where_format = null) {
        if ($table === $this->prefix . 'membership_admin_notes') {
            $this->admin_updates[] = array('updated' => $data, 'where' => $where);
            return $this->note_fails ? false : $this->update_result;
        }
        $this->member_writes++;
        return $this->member_fails ? false : $this->update_result;
    }
    public function insert($table, $data, $formats = null) {
        if ($table === $this->prefix . 'membership_event_rsvps') {
            return 1;
        }
        if ($table === $this->prefix . 'membership_member_event_tickets') {
            if ($this->ticket_fails) { return false; }
            $this->ticket_writes[] = $data;
            return 1;
        }
        if ($table === $this->prefix . 'memberships') {
            $this->member_writes++;
            if ($this->member_fails) {
                return false;
            }
            $this->insert_id = 42;
            $this->member = array_merge($this->member, $data, array('id' => 42));
            return 1;
        }
        expect($table === $this->prefix . 'membership_admin_notes', 'Note insert uses the checked admin table');
        if (!$this->table_exists || $this->note_fails) {
            return false;
        }
        $data['id'] = 99;
        $this->notes[] = $data;
        $this->insert_id = 99;
        return 1;
    }
    public function delete($table, $where, $where_format = null) {
        expect($table === $this->prefix . 'membership_admin_notes', 'Note deletion uses the admin notes table');
        $this->admin_deletions[] = $where;
        return $this->note_fails ? false : 1;
    }
}

require __DIR__ . '/../membership-manager-qr/includes/database.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
require __DIR__ . '/../membership-manager-qr/includes/ajax-handlers.php';

$wpdb = new NotesDatabase();
mmgr_check_database();
expect($wpdb->table_exists, 'Current-version installations repair missing notes tables');
expect(str_contains($wpdb->checks[0], 'site\\\\_2\\\\_membership\\\\_admin\\\\_notes'), 'Existence check escapes LIKE wildcards in the exact site table name');
expect(mmgr_ensure_member_notes_table(), 'Existing table passes verification');
expect($wpdb->creates === 1, 'Existing notes table is not recreated');
$wpdb->table_exists = false;
$wpdb->creation_fails = true;
expect(!mmgr_ensure_member_notes_table(), 'Failed table creation returns failure');
$wpdb->creation_fails = false;
expect(mmgr_ensure_member_notes_table(), 'Failed creation can be retried');

$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->admin_columns = array('id', 'member_id', 'note', 'created_by', 'created_at');
expect(mmgr_ensure_member_notes_table() && in_array('hidden', $wpdb->admin_columns, true), 'Existing admin notes tables gain the hidden flag');
$wpdb->hidden_column_add_fails = true;
$wpdb->admin_columns = array('id', 'member_id', 'note', 'created_by', 'created_at');
expect(!mmgr_ensure_member_notes_table(), 'Failed hidden-flag migration is reported');

function render_member_form($editing, $note, $tickets = null, $note_action = null) {
    global $wpdb;
    $_GET = $editing ? array('id' => 7) : array();
    $_POST = array_fill_keys(array(
        'first_name', 'last_name', 'partner_first_name', 'partner_last_name',
        'email', 'phone', 'sex', 'partner_sex', 'age', 'partner_age',
        'level', 'start_date', 'expire_date',
    ), '');
    if ($note_action === null) {
        $_POST['mmgr_save_member'] = '1';
    } else {
        $_POST['mmgr_note_action'] = $note_action;
    }
    $_POST['member_nonce'] = 'test-nonce';
    $_POST['admin_note'] = addslashes($note);
    if ($tickets !== null) {
        $_POST['mmgr_paid_tickets_present'] = '1';
        $_POST['paid_event_ids'] = $tickets;
    }
    ob_start();
    include __DIR__ . '/../membership-manager-qr/includes/admin/add-edit-member.php';
    return ob_get_clean();
}

$wpdb = new NotesDatabase();
$wpdb->legacy_schema = array('id', 'viewer_member_id', 'profile_member_id', 'note', 'updated_at');
$private_note = array('id' => 1, 'viewer_member_id' => 2, 'profile_member_id' => 7, 'note' => 'Private profile note', 'updated_at' => current_time('mysql'));
$wpdb->legacy_notes = array($private_note);
$html = render_member_form(true, 'Admin only note');
expect(str_contains($html, 'Member updated successfully!'), 'Admin notes save when the legacy table has the private profile schema');
expect($wpdb->renames === 0 && $wpdb->legacy_notes === array($private_note), 'Private profile notes are neither moved nor changed');
expect(count($wpdb->notes) === 1 && $wpdb->notes[0]['note'] === 'Admin only note', 'Admin notes use separate storage');
expect(!str_contains($html, 'Private profile note'), 'Private profile notes do not appear in the admin note log');

$wpdb = new NotesDatabase();
$wpdb->legacy_schema = array('id', 'member_id', 'note', 'created_by', 'created_at');
$old_note = array('id' => 10, 'member_id' => 7, 'note' => 'Existing admin history', 'created_by' => 3, 'created_at' => current_time('mysql'));
$wpdb->legacy_notes = array($old_note);
$html = render_member_form(true, 'New admin note');
expect($wpdb->renames === 1 && $wpdb->creates === 0, 'Existing admin schema is moved without recreating or copying rows');
expect($wpdb->notes[0] === $old_note && count($wpdb->notes) === 2, 'Migration preserves existing admin note IDs, authors, timestamps and content');
expect(str_contains($html, 'Existing admin history'), 'Migrated admin history is still displayed');
expect(mmgr_ensure_member_notes_table() && $wpdb->renames === 1, 'Repeated schema checks do not repeat migration');
expect(!$wpdb->legacy_schema, 'Migration frees the old table name for private profile notes');

$wpdb = new NotesDatabase();
$wpdb->legacy_schema = array('id', 'member_id', 'note', 'created_by', 'created_at');
$wpdb->legacy_notes = array($old_note);
$wpdb->rename_fails = true;
$html = render_member_form(true, 'Pending migration');
expect(!$wpdb->table_exists && $wpdb->legacy_notes === array($old_note), 'Failed migration leaves existing history untouched');
expect(str_contains($html, 'rows="4">Pending migration</textarea>'), 'Failed migration preserves the submitted note');
$wpdb->rename_fails = false;
expect(mmgr_ensure_member_notes_table() && $wpdb->notes === array($old_note), 'Migration can safely retry after failure');

$wpdb = new NotesDatabase();
$wpdb->update_result = 0;
$text = "Member's note\nSecond line";
$html = render_member_form(true, $text);
expect(count($wpdb->notes) === 1 && $wpdb->notes[0]['note'] === $text, 'Unchanged member still saves a multiline note without added slashes');
expect($wpdb->notes[0]['member_id'] === 7 && $wpdb->notes[0]['created_by'] === 3, 'Note retains member and author IDs');
expect(str_contains($html, 'Member updated successfully!'), 'Successful note save reports success');
expect(str_contains($html, 'Second line'), 'Saved note appears in the note log');
expect(str_contains($html, 'rows="4"></textarea>'), 'Successful save clears the note input');

$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->notes = array(array('id' => 55, 'member_id' => 7, 'note' => 'Old note', 'created_by' => 3, 'created_at' => current_time('mysql'), 'hidden' => 0));
$html = render_member_form(true, '', null, 'hide:55');
expect($wpdb->admin_updates[0]['updated'] === array('hidden' => 1), 'Hide action marks only the selected note hidden');
expect($wpdb->admin_updates[0]['where'] === array('id' => 55, 'member_id' => 7), 'Hide action is scoped to the edited member');
expect(str_contains($html, 'Note hidden from check-in.'), 'Hide action reports success');
expect(!str_contains($html, 'Member updated successfully!'), 'Note action does not save the member form');

$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->notes = array(array('id' => 56, 'member_id' => 7, 'note' => 'Hidden note', 'created_by' => 3, 'created_at' => current_time('mysql'), 'hidden' => 1));
$html = render_member_form(true, '', null, 'show:56');
expect($wpdb->admin_updates[0]['updated'] === array('hidden' => 0), 'Show action restores the note to check-in');
expect(str_contains($html, 'Show at check-in'), 'Hidden notes can be shown again from the edit form');

$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->notes = array(array('id' => 57, 'member_id' => 7, 'note' => 'Delete this note', 'created_by' => 3, 'created_at' => current_time('mysql'), 'hidden' => 0));
$html = render_member_form(true, '', null, 'delete:57');
expect($wpdb->admin_deletions[0] === array('id' => 57, 'member_id' => 7), 'Delete action is scoped to the edited member');
expect(str_contains($html, 'Note deleted.'), 'Delete action reports success');

$valid_nonce = false;
$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->notes = array(array('id' => 58, 'member_id' => 7, 'note' => 'Hidden note', 'created_by' => 3, 'created_at' => current_time('mysql'), 'hidden' => 1));
$html = render_member_form(true, '', null, 'delete:58');
expect(!$wpdb->admin_deletions && str_contains($html, 'Security check failed.'), 'Invalid nonce prevents note actions');
$valid_nonce = true;

$wpdb = new NotesDatabase();
$wpdb->table_exists = true;
$wpdb->member = array('id' => 7, 'member_code' => 'TEST7', 'name' => 'Test Member', 'email' => 'member@example.com', 'banned' => 1);
$wpdb->notes = array(
    array('id' => 59, 'member_id' => 7, 'note' => 'Visible note', 'created_at' => current_time('mysql'), 'hidden' => 0),
    array('id' => 60, 'member_id' => 7, 'note' => 'Hidden note', 'created_at' => current_time('mysql'), 'hidden' => 1),
);
$_POST = array('code' => 'TEST7');
try {
    mmgr_handle_checkin();
} catch (JsonResponse $response) {
    expect(count($response->data['admin_notes']) === 1 && $response->data['admin_notes'][0]['note'] === 'Visible note', 'Check-in alerts include visible notes only');
}
expect(str_contains($wpdb->queries[0], 'member_id = 7 AND hidden = 0'), 'Check-in note query filters hidden notes');
expect(str_contains($wpdb->queries[0], 'SELECT id, member_id, note, created_at'), 'Check-in provides identifiers for individual note actions');

function checkin_note_action($operation, $note = '') {
    $_POST = array('nonce' => 'test-nonce', 'note_id' => 59, 'member_id' => 7, 'operation' => $operation, 'note' => $note);
    try {
        mmgr_ajax_checkin_note();
    } catch (JsonResponse $response) {
        return $response;
    }
    throw new RuntimeException('Missing note action response');
}

$wpdb = new NotesDatabase();
$response = checkin_note_action('edit', "Updated <b>note</b>\nSecond line");
expect($response->success, 'Check-in note edit succeeds');
expect($wpdb->admin_updates[0]['updated'] === array('note' => "Updated note\nSecond line"), 'Edits sanitize text while preserving multiple lines and note visibility');
expect($wpdb->admin_updates[0]['where'] === array('id' => 59, 'member_id' => 7), 'Check-in edit is scoped to the selected note and member');
$wpdb->update_result = 0;
expect(checkin_note_action('edit', 'Unchanged note')->success, 'Saving unchanged text is successful');
expect(checkin_note_action('delete')->success, 'Check-in delete succeeds');
expect($wpdb->admin_deletions[0] === array('id' => 59, 'member_id' => 7), 'Check-in delete is scoped to the selected note and member');
expect(!$wpdb->member_writes, 'Check-in note actions do not change member data');
$wpdb->note_fails = true;
expect(!checkin_note_action('edit', 'Retry me')->success, 'Check-in edit reports database failure');
expect(!checkin_note_action('delete')->success, 'Check-in delete reports database failure');

$wpdb = new NotesDatabase();
expect(!checkin_note_action('edit', '   ')->success && !$wpdb->admin_updates, 'Empty edits cannot erase a note');
expect(!checkin_note_action('hide')->success && !$wpdb->admin_updates, 'Unsupported check-in note actions are rejected');
$valid_nonce = false;
expect(!checkin_note_action('delete')->success && !$wpdb->admin_deletions, 'Invalid nonce blocks check-in deletion');
expect(!checkin_note_action('edit', 'Blocked')->success && !$wpdb->admin_updates, 'Invalid nonce blocks check-in editing');
$valid_nonce = true;
$can_manage_notes = false;
expect(!checkin_note_action('delete')->success && !$wpdb->admin_deletions, 'Non-admin cannot delete check-in notes');
expect(!checkin_note_action('edit', 'Blocked')->success && !$wpdb->admin_updates, 'Non-admin cannot edit check-in notes');
$_POST = array('code' => 'TEST7');
$wpdb->member = array('id' => 7, 'member_code' => 'TEST7', 'name' => 'Test Member', 'email' => 'member@example.com', 'banned' => 1);
try {
    mmgr_handle_checkin();
} catch (JsonResponse $response) {
    expect($response->data['admin_notes'] === array(), 'Non-admin check-in does not expose admin notes or identifiers');
}
$can_manage_notes = true;

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

$valid_nonce = true;
$wpdb = new NotesDatabase();
$wpdb->ticket_options = array(array(
    'id' => 5, 'event_name' => '<b>Party</b>', 'event_date' => '2099-10-31', 'paid_ticket_id' => null,
));
$html = render_member_form(true, '', array('5'));
expect(count($wpdb->ticket_writes) === 1 && $wpdb->ticket_writes[0]['member_id'] === 7, 'Editing a member saves the paid ticket on that account');
expect(str_contains($html, 'name="paid_event_ids[]"') && str_contains($html, '&lt;b&gt;Party&lt;/b&gt;'), 'Edit form presents escaped ticket event options');
$wpdb->ticket_fails = true;
$html = render_member_form(true, '', array('5'));
expect(str_contains($html, 'paid event tickets could not all be saved') && !str_contains($html, 'Member updated successfully!'), 'Ticket write failure is not reported as overall success');
$wpdb->ticket_fails = false;
$wpdb->member_fails = true;
$html = render_member_form(true, '', array('5'));
expect(count($wpdb->ticket_writes) === 1, 'Failed member update does not save tickets');
$wpdb->member_fails = false;
$valid_nonce = false;
$html = render_member_form(true, '', array('5'));
expect(count($wpdb->ticket_writes) === 1, 'Invalid member nonce prevents paid ticket writes');

echo "\nResults: $passed passed, 0 failed\n";
