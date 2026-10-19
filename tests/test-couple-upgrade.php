<?php
// Run with: php tests/test-couple-upgrade.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$actions = array();
$passed = 0;
$valid_nonce = true;
$authenticated = true;
$notes_ready = true;
function add_action($hook, $callback) { global $actions; $actions[$hook] = $callback; }
function mmgr_get_current_member() { global $wpdb, $authenticated; return $authenticated ? $wpdb->member : null; }
function mmgr_ensure_member_notes_table() { global $notes_ready; return $notes_ready; }
function check_ajax_referer($action, $key) {
    global $valid_nonce;
    if (!$valid_nonce) { throw new RuntimeException('Invalid nonce'); }
}
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function current_time($format) { return $format === 'Y-m-d' ? '2026-10-19' : '2026-10-19 12:00:00'; }
function wp_send_json_error($data) { throw new UpgradeResponse(false, $data); }
function wp_send_json_success($data) { throw new UpgradeResponse(true, $data); }
function expect($condition, $description) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $description); }
    $passed++;
    echo "PASS: $description\n";
}
class UpgradeResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
class UpgradeDatabase {
    public $prefix = 'wp_';
    public $member = array(
        'id' => 7, 'level' => 'MALE', 'paid' => 1, 'member_code' => 'KEEP7',
        'start_date' => '2026-01-01', 'expire_date' => '2027-01-01', 'payment_amount' => '150.00',
    );
    public $levels = array('MALE', 'FEMALE', 'COUPLES');
    public $notes = array();
    public $queries = array();
    public $fail = '';
    private $snapshot;
    public function prepare($query, ...$args) { return str_replace('%d', (string) $args[0], $query); }
    public function get_col($query) { return $this->levels; }
    public function get_row($query, $format) {
        expect(str_contains($query, 'id = 7 FOR UPDATE'), 'Locks the authenticated account, not a posted member ID');
        return $this->member;
    }
    public function query($query) {
        $this->queries[] = $query;
        if ($query === $this->fail) { return false; }
        if ($query === 'START TRANSACTION') { $this->snapshot = array($this->member, $this->notes); }
        if ($query === 'ROLLBACK') { list($this->member, $this->notes) = $this->snapshot; }
        return 1;
    }
    public function insert($table, $data) {
        expect($table === 'wp_membership_admin_notes', 'Debt reminder uses admin notes, not private profile notes');
        if ($this->fail === 'note') { return false; }
        $this->notes[] = $data;
        return 1;
    }
    public function update($table, $data, $where) {
        expect($table === 'wp_memberships' && $where === array('id' => 7), 'Updates only the current member');
        if ($this->fail === 'update') { return false; }
        $this->member = array_merge($this->member, $data);
        return 1;
    }
}
require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
require __DIR__ . '/../membership-manager-qr/includes/ajax-handlers.php';

function reset_upgrade() {
    global $wpdb, $valid_nonce, $authenticated, $notes_ready;
    $wpdb = new UpgradeDatabase();
    $valid_nonce = $authenticated = $notes_ready = true;
    $_POST = array('nonce' => 'test', 'partner_first_name' => " Pat\\'s ", 'partner_last_name' => '<b>Smith</b>', 'partner_dob' => '1990-02-28', 'member_id' => 99);
}
function upgrade() {
    try { mmgr_ajax_upgrade_to_couple(); }
    catch (UpgradeResponse $response) { return $response; }
    throw new RuntimeException('No response');
}
reset_upgrade();
expect($actions['wp_ajax_nopriv_mmgr_upgrade_to_couple'] === 'mmgr_ajax_upgrade_to_couple', 'Portal sessions can use the logged-out WordPress AJAX route');
expect($actions['wp_ajax_mmgr_upgrade_to_couple'] === 'mmgr_ajax_upgrade_to_couple', 'WordPress logged-in route is also registered');
foreach (array('Single', 'MALE', 'FEMALE', 'Single Male', 'Single Female') as $level) {
    expect(mmgr_can_upgrade_to_couple(array('level' => $level)), "$level is eligible");
}
foreach (array('Couple', 'COUPLES', 'Youth', '') as $level) {
    expect(!mmgr_can_upgrade_to_couple(array('level' => $level)), "$level is not eligible");
}
$before = $wpdb->member;
expect(upgrade()->success, 'Paid single upgrades successfully');
expect($wpdb->member['level'] === 'COUPLES', 'Uses the configured couples level spelling');
expect($wpdb->member['partner_name'] === "Pat's Smith" && $wpdb->member['partner_age'] === '1990-02-28', 'Stores sanitized partner names and DOB');
foreach ($before as $key => $value) {
    if ($key !== 'level') { expect($wpdb->member[$key] === $value, "Preserves $key"); }
}
expect(count($wpdb->notes) === 1 && $wpdb->notes[0]['hidden'] === 0 &&
    str_contains($wpdb->notes[0]['note'], '$20.00') && str_contains($wpdb->notes[0]['note'], 'next visit'), 'Paid upgrade creates one visible next-visit debt reminder');
expect(!upgrade()->success && count($wpdb->notes) === 1, 'Repeat request cannot duplicate the reminder');

reset_upgrade();
$wpdb->member['paid'] = 0;
$wpdb->member['level'] = 'FEMALE';
$wpdb->levels = array('Single', 'Couple');
expect(upgrade()->success && $wpdb->member['level'] === 'Couple', 'Unpaid female upgrades with default Couple spelling');
expect(!$wpdb->notes && $wpdb->member['paid'] === 0, 'Unpaid account stays unpaid without an upgrade debt');
reset_upgrade();
$wpdb->member['expire_date'] = '2020-01-01';
expect(upgrade()->success && count($wpdb->notes) === 1, 'Paid flag determines debt even if the membership has expired');

foreach (array('', 'not-a-date', '2020-02-30', '2009-10-20', '2040-01-01', array('1990-01-01')) as $dob) {
    reset_upgrade();
    $_POST['partner_dob'] = $dob;
    expect(!upgrade()->success && !$wpdb->queries, 'Rejects missing, malformed, future or underage DOB before writes');
}
foreach (array('partner_first_name', 'partner_last_name', 'partner_dob') as $key) {
    reset_upgrade();
    unset($_POST[$key]);
    expect(!upgrade()->success && !$wpdb->notes, "Requires $key");
}
reset_upgrade();
$_POST['partner_dob'] = '2008-10-19';
expect(upgrade()->success, 'Accepts a partner on their eighteenth birthday');
foreach (array('note', 'update', 'COMMIT', 'START TRANSACTION') as $failure) {
    reset_upgrade();
    $wpdb->fail = $failure;
    $before = $wpdb->member;
    expect(!upgrade()->success && $wpdb->member === $before && !$wpdb->notes, "$failure failure leaves no partial upgrade or debt");
}
reset_upgrade();
$authenticated = false;
expect(!upgrade()->success && !$wpdb->queries, 'Unauthenticated requests cannot upgrade');
reset_upgrade();
$valid_nonce = false;
try { upgrade(); throw new RuntimeException('Nonce was not checked'); }
catch (RuntimeException $error) { expect($error->getMessage() === 'Invalid nonce' && !$wpdb->queries, 'Rejects invalid nonce before writes'); }
reset_upgrade();
$wpdb->levels = array('Single');
expect(!upgrade()->success && !$wpdb->queries, 'Missing couples configuration is handled without writes');
reset_upgrade();
$notes_ready = false;
expect(!upgrade()->success && !$wpdb->queries, 'Unavailable notes storage prevents losing a paid debt reminder');
echo "Results: $passed passed, 0 failed\n";
