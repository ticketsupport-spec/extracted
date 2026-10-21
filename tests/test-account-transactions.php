<?php
// Run with: php tests/test-account-transactions.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$can_manage = true;
$nonce_valid = true;
$now = '2028-01-31 23:45:00';
$passed = 0;
function add_action($hook, $callback) {}
function current_user_can($capability) { global $can_manage; return $can_manage; }
function wp_verify_nonce($nonce, $action) { global $nonce_valid; return $nonce_valid && $nonce === 'valid'; }
function current_time($format) { global $now; return $now; }
function get_current_user_id() { return 42; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function sanitize_email($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=valid'; }
function wp_nonce_field($action, $name) {}
function wp_create_nonce($action) { return 'valid'; }
function checked($value, $expected = true) {}
function selected($value, $expected) {}
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function get_userdata($id) { return false; }
function mmgr_generate_member_code($name) { return 'NEWCODE'; }
function mmgr_generate_qr_file($code) {}
function mmgr_send_welcome_email($id) {}
function mmgr_send_welcome_pm($id) {}

class AccountDatabase {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $members = array();
    public $tickets = array();
    public $rsvps = array();
    public $ledger = array();
    public $events = array();
    public $queries = array();
    public $fail = '';
    public $engines = array();
    private $snapshot;
    public function __construct($level = 'Single') {
        $this->members[7] = array('id' => 7, 'name' => 'Test Account', 'member_code' => 'CODE7',
            'first_name' => 'Test', 'last_name' => 'Account', 'email' => 'test@example.test',
            'level' => $level, 'start_date' => '2027-01-31', 'expire_date' => '2028-01-31',
            'paid' => 0, 'payment_amount' => 0, 'payment_method' => null);
        $base = array('event_name' => 'Dance <Night>', 'event_date' => '2028-02-15',
            'active' => 1, 'tickets_enabled' => 1, 'ticket_prices' => '{"Single":20,"Couple":35}');
        $this->events[5] = array_merge($base, array('id' => 5));
        $this->events[6] = array_merge($base, array('id' => 6, 'event_name' => 'Second party'));
        $this->events[8] = array_merge($base, array('id' => 8, 'event_date' => '2027-01-01'));
        $this->events[9] = array_merge($base, array('id' => 9, 'active' => 0));
    }
    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        foreach ($args as $value) {
            $query = preg_replace_callback('/%[ds]/', function() use ($value) {
                return is_int($value) ? (string) $value : "'" . addslashes($value) . "'";
            }, $query, 1);
        }
        return $query;
    }
    public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
    public function esc_like($value) { return $value; }
    public function query($query) {
        $this->queries[] = $query;
        if (str_starts_with($query, 'CREATE TABLE')) {
            if ($this->snapshot !== null) { throw new RuntimeException('DDL inside transaction'); }
            return $this->fail === 'schema' ? false : 1;
        }
        if (preg_match('/^ALTER TABLE `([^`]+)` ENGINE=InnoDB$/', $query, $matches)) {
            if ($this->snapshot !== null) { throw new RuntimeException('Engine migration inside transaction'); }
            if ($this->fail === 'engine') { return false; }
            $this->engines[$matches[1]] = 'InnoDB';
            return 1;
        }
        if ($query === 'START TRANSACTION') {
            $this->snapshot = array($this->members, $this->tickets, $this->rsvps, $this->ledger);
        } elseif ($query === 'ROLLBACK') {
            if ($this->snapshot !== null) {
                list($this->members, $this->tickets, $this->rsvps, $this->ledger) = $this->snapshot;
            }
            $this->snapshot = null;
        } elseif ($query === 'COMMIT') {
            if ($this->fail === 'commit') { return false; }
            $this->snapshot = null;
        } else {
            throw new RuntimeException('Unexpected query: ' . $query);
        }
        return 1;
    }
    public function get_row($query, $format) {
        $this->queries[] = $query;
        if (preg_match("/^SHOW TABLE STATUS WHERE Name = '([^']+)'/", $query, $matches)) {
            return array('Engine' => $this->engines[$matches[1]] ?? 'InnoDB');
        }
        preg_match('/id = (\d+)/', $query, $matches);
        return $this->members[(int) ($matches[1] ?? 0)] ?? null;
    }
    public function get_var($query) {
        $this->queries[] = $query;
        if (str_contains($query, 'operation_key =')) {
            preg_match("/operation_key = '([^']+)'/", $query, $matches);
            foreach ($this->ledger as $row) {
                if ($row['operation_key'] === $matches[1]) { return $row['id']; }
            }
        } elseif (str_contains($query, 'SUM(amount)')) {
            preg_match("/created_at >= '([^']+)' AND created_at < '([^']+)'/", $query, $dates);
            return array_sum(array_map(function($row) use ($dates) {
                return $row['created_at'] >= $dates[1] && $row['created_at'] < $dates[2] &&
                    in_array($row['activity'], array('membership_sale', 'ticket_sale'), true) ? $row['amount'] : 0;
            }, $this->ledger));
        } elseif (str_contains($query, 'COUNT(*)') && str_contains($query, 'membership_account_transactions')) {
            return count($this->ledger);
        } elseif (str_contains($query, 'membership_event_rsvps')) {
            preg_match('/event_id = (\d+) AND member_id = (\d+)/', $query, $matches);
            return isset($this->rsvps[$matches[2]][$matches[1]]) ? 1 : null;
        } elseif (str_contains($query, 'membership_member_event_tickets')) {
            preg_match('/member_id = (\d+) AND event_id = (\d+)/', $query, $matches);
            return isset($this->tickets[$matches[1]][$matches[2]]) ? 1 : null;
        }
        return null;
    }
    public function get_results($query, $format) {
        $this->queries[] = $query;
        if (str_contains($query, 'FROM wp_membership_events e')) {
            preg_match('/t.member_id = (\d+)/', $query, $matches);
            $id = (int) $matches[1];
            $rows = array();
            foreach ($this->events as $event) {
                if ($event['active'] && $event['event_date'] >= substr(current_time('mysql'), 0, 10) &&
                    ($event['tickets_enabled'] || isset($this->tickets[$id][$event['id']]))) {
                    $rows[] = array_merge($event, array('paid_ticket_id' => $this->tickets[$id][$event['id']]['id'] ?? null));
                }
            }
            return $rows;
        }
        if (str_contains($query, 'membership_account_transactions')) {
            $rows = array_reverse($this->ledger);
            if (preg_match('/(?:a\.)?member_id = (\d+)/', $query, $matches)) {
                $rows = array_values(array_filter($rows, function($row) use ($matches) { return $row['member_id'] == $matches[1]; }));
            }
            foreach ($rows as &$row) {
                $row['name'] = $this->members[$row['member_id']]['name'] ?? '';
                $row['member_code'] = $this->members[$row['member_id']]['member_code'] ?? '';
            }
            unset($row);
            if (preg_match('/LIMIT (\d+) OFFSET (\d+)/', $query, $matches)) {
                $rows = array_slice($rows, (int) $matches[2], (int) $matches[1]);
            }
            return $rows;
        }
        return array();
    }
    public function update($table, $data, $where) {
        if ($this->fail === 'member') { return false; }
        $this->members[$where['id']] = array_merge($this->members[$where['id']], $data);
        return 1;
    }
    public function insert($table, $data, $formats = null) {
        if ($table === 'wp_memberships') {
            if ($this->fail === 'member') { return false; }
            $this->insert_id = 10;
            $this->members[10] = array_merge($data, array('id' => 10));
        } elseif ($table === 'wp_membership_account_transactions') {
            if ($this->fail === 'ledger' || ($this->fail === 'second_log' && count($this->ledger) === 1)) { return false; }
            foreach ($this->ledger as $row) {
                if ($row['operation_key'] === $data['operation_key']) { return false; }
            }
            $this->insert_id = count($this->ledger) + 1;
            $this->ledger[] = array_merge($data, array('id' => $this->insert_id));
        } elseif ($table === 'wp_membership_member_event_tickets') {
            if ($this->fail === 'ticket') { return false; }
            $this->tickets[$data['member_id']][$data['event_id']] = array_merge($data, array('id' => 99));
        } elseif ($table === 'wp_membership_event_rsvps') {
            if ($this->fail === 'rsvp') { return false; }
            $this->rsvps[$data['member_id']][$data['event_id']] = $data;
        } else {
            throw new RuntimeException('Unexpected insert: ' . $table);
        }
        return 1;
    }
    public function delete($table, $where, $formats = null) {
        unset($this->tickets[$where['member_id']][$where['event_id']]);
        return 1;
    }
}

require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
require __DIR__ . '/../membership-manager-qr/includes/account-transactions.php';

function account_form($overrides = array(), $editing = true) {
    global $wpdb;
    $_GET = $editing ? array('id' => 7) : array();
    $member = $wpdb->members[7];
    $_POST = array_merge(array_fill_keys(array('partner_first_name', 'partner_last_name', 'phone',
        'sex', 'partner_sex', 'age', 'partner_age'), ''), array(
        'first_name' => 'Test', 'last_name' => 'Account', 'email' => 'test@example.test',
        'level' => $member['level'], 'start_date' => $member['start_date'], 'expire_date' => $member['expire_date'],
        'amount_paid' => $member['payment_amount'], 'member_nonce' => 'valid',
        'mmgr_save_member' => 1, 'account_operation_key' => bin2hex(random_bytes(16)),
    ), !empty($member['paid']) ? array('paid' => 1) : array(), $overrides);
    if (isset($_POST['paid']) && $_POST['paid'] === null) { unset($_POST['paid']); }
    ob_start();
    include __DIR__ . '/../membership-manager-qr/includes/admin/add-edit-member.php';
    return ob_get_clean();
}
function account_logs($args = array()) {
    $_POST = array();
    $_GET = $args;
    ob_start();
    include __DIR__ . '/../membership-manager-qr/includes/admin/visit-logs.php';
    return ob_get_clean();
}
function expect($condition, $description) {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $description); }
    $passed++;
    echo "PASS: $description\n";
}

// A subprocess exercises the real CSV endpoint, which intentionally exits.
if (isset($argv[1]) && $argv[1] === '--export') {
    $wpdb = new AccountDatabase();
    $fields = array();
    if (($argv[2] ?? '') === 'malicious') {
        $wpdb->members[7]['member_code'] = '@SUM(1,2)';
        $wpdb->events[5]['event_name'] = '=HYPERLINK("https://example.test","title")';
        $fields = array('first_name' => '=SUM(1,2)', 'last_name' => '');
    }
    account_form(array_merge(array('membership_operation' => 'comp_1', 'mmgr_paid_tickets_present' => 1,
        'paid_event_ids' => array('5'), 'ticket_methods' => array(5 => 'comp')), $fields));
    $_GET = array('export_account_csv' => 1, '_wpnonce' => $argv[2] ?? 'valid');
    if (($argv[2] ?? '') === 'malicious') { $_GET['_wpnonce'] = 'valid'; }
    if (($argv[2] ?? '') === 'denied') { $can_manage = false; }
    include __DIR__ . '/../membership-manager-qr/includes/admin/visit-logs.php';
    exit;
}

foreach (array('Single' => 20, 'Couple' => 35) as $level => $price) {
    foreach (array('paid', 'comp') as $method) {
        $wpdb = new AccountDatabase($level);
        $html = account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5'),
            'ticket_methods' => array(5 => $method)));
        expect(str_contains($html, 'Member updated successfully!'), "$level $method ticket saves through actual form");
        expect(count($wpdb->ledger) === 1 && $wpdb->ledger[0]['amount'] == ($method === 'comp' ? 0 : $price),
            "$level $method logs correct price once per account");
        expect(mmgr_member_has_paid_event_ticket(7, 5) && isset($wpdb->rsvps[7][5]), "$level $method grants ticket admission and Going RSVP");
        expect($wpdb->ledger[0]['created_by'] === 42 && $wpdb->ledger[0]['created_at'] === $now,
            'Ledger records administrator and WordPress local time');
        if ($method === 'comp') { expect(str_contains($html, 'COMP&#039;D TICKET'), 'Account shows persisted complimentary ticket status'); }
        $html = account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5')));
        expect(count($wpdb->ledger) === 1 && count($wpdb->rsvps[7]) === 1, 'Ordinary ticket resave does not duplicate sale or RSVP');
        expect(str_contains(implode("\n", $wpdb->queries), 'FOR UPDATE'), 'Financial changes lock account row before reading grants');
    }
}

foreach (array(true, false) as $editing) {
    $wpdb = new AccountDatabase();
    account_form(array('paid' => 1, 'amount_paid' => '150.50'), $editing);
    $id = $editing ? 7 : 10;
    expect(count($wpdb->ledger) === 1 && $wpdb->ledger[0]['member_id'] === $id &&
        $wpdb->ledger[0]['amount'] === 150.5, 'Marking membership paid records sale for ' . ($editing ? 'existing' : 'new') . ' account');
    if ($editing) {
        account_form();
        expect(count($wpdb->ledger) === 1, 'Paid membership ordinary resave does not duplicate revenue');
        account_form(array('amount_paid' => 160));
        expect(count($wpdb->ledger) === 1, 'Amount correction does not invent historical payment');
        $wpdb->members[7]['payment_method'] = 'Cash';
        $payment_date = $wpdb->members[7]['payment_date'];
        account_form(array('start_date' => '2028-01-01', 'expire_date' => '2029-01-31'));
        expect(count($wpdb->ledger) === 1 && $wpdb->members[7]['payment_method'] === 'Cash' &&
            $wpdb->members[7]['payment_date'] === $payment_date, 'Paid date corrections preserve payment method and date without duplicate revenue');
        expect($wpdb->members[7]['start_date'] === '2028-01-01' && $wpdb->members[7]['expire_date'] === '2029-01-31',
            'Normal form saves still persist corrected membership dates');
        account_form(array('membership_operation' => 'paid'));
        expect(count($wpdb->ledger) === 2 && $wpdb->ledger[1]['amount'] === 160.0, 'Explicit paid renewal records current payment exactly once');
        account_form();
        expect(count($wpdb->ledger) === 2, 'Renewal normal resave is idempotent');
    }
}

foreach (array('comp_1' => '2028-02-29', 'comp_12' => '2029-01-31') as $operation => $expiry) {
    $wpdb = new AccountDatabase('Couple');
    $key = str_repeat('c', 32);
    $html = account_form(array('membership_operation' => $operation, 'account_operation_key' => $key, 'amount_paid' => 250));
    expect($wpdb->members[7]['paid'] === 1 && $wpdb->members[7]['payment_amount'] === 0 &&
        $wpdb->members[7]['payment_method'] === 'COMP', "$operation grants free membership access");
    expect($wpdb->members[7]['start_date'] === '2028-01-31' && $wpdb->members[7]['expire_date'] === $expiry, "$operation uses local date and calendar expiry");
    expect(count($wpdb->ledger) === 1 && str_contains($wpdb->ledger[0]['description'], "COMP'D MEMBERSHIP"), 'Membership COMP audit is explicit');
    account_form(array('membership_operation' => $operation, 'account_operation_key' => $key));
    expect(count($wpdb->ledger) === 1, 'Replaying explicit COMP request does not extend term or duplicate log');
    account_form(array('amount_paid' => 999));
    expect(count($wpdb->ledger) === 1 && $wpdb->members[7]['payment_amount'] === 0 &&
        $wpdb->members[7]['expire_date'] === $expiry, 'Normal edit retains COMP status, zero amount and dates');
    $key = str_repeat('d', 32);
    account_form(array('membership_operation' => 'paid', 'amount_paid' => 250, 'account_operation_key' => $key));
    account_form(array('membership_operation' => 'paid', 'amount_paid' => 250, 'account_operation_key' => $key));
    expect(count($wpdb->ledger) === 2 && $wpdb->ledger[1]['amount'] === 250.0 &&
        $wpdb->members[7]['payment_method'] === 'Admin', 'Explicit paid conversion records one sale even on replay');
}
expect(mmgr_comp_expiry('2028-02-29', 12) === '2029-02-28', 'Leap-year annual COMP clamps to February end');
expect(mmgr_comp_expiry('2027-01-31', 1) === '2027-02-28', 'Non-leap monthly COMP clamps to February end');
expect(mmgr_comp_expiry('2028-12-31', 1) === '2029-01-31', 'Monthly COMP crosses calendar year correctly');

foreach (array(false, true) as $failure) {
    $wpdb = new AccountDatabase();
    $wpdb->engines['wp_membership_member_event_tickets'] = 'MyISAM';
    $wpdb->engines['wp_membership_event_rsvps'] = 'MyISAM';
    $wpdb->fail = $failure ? 'engine' : '';
    $html = account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5')));
    if ($failure) {
        expect(!$wpdb->tickets && !$wpdb->ledger && str_contains($html, 'InnoDB storage support'),
            'Failed legacy engine conversion prevents unlogged grants');
    } else {
        expect($wpdb->engines['wp_membership_member_event_tickets'] === 'InnoDB' &&
            $wpdb->engines['wp_membership_event_rsvps'] === 'InnoDB' && count($wpdb->ledger) === 1,
            'Legacy nontransactional ticket and RSVP tables convert before grant');
    }
}
$wpdb = new AccountDatabase();
$wpdb->engines['wp_memberships'] = 'MyISAM';
account_form(array('membership_operation' => 'comp_1'));
expect(!$wpdb->ledger && !$wpdb->members[7]['paid'], 'Unexpected nontransactional account storage fails closed');

foreach (array('schema', 'member', 'ticket', 'rsvp', 'ledger', 'second_log', 'commit') as $failure) {
    $wpdb = new AccountDatabase();
    $before = $wpdb->members;
    $wpdb->fail = $failure;
    $html = account_form(array('membership_operation' => 'comp_12', 'mmgr_paid_tickets_present' => 1,
        'paid_event_ids' => array('5'), 'ticket_methods' => array(5 => 'comp')));
    expect($wpdb->members === $before && !$wpdb->ledger && !$wpdb->tickets && !$wpdb->rsvps,
        "$failure failure rolls back account, grants, RSVPs and audit records");
    expect(str_contains($html, 'No membership or ticket changes were committed') &&
        !str_contains($html, 'Member updated successfully!'), "$failure failure reports clear form error");
}

$wpdb = new AccountDatabase();
$can_manage = false;
account_form(array('membership_operation' => 'comp_1'));
expect(!$wpdb->ledger && !$wpdb->queries, 'Non-admin form denied before database access');
expect(str_contains(account_logs(), 'not allowed') && !$wpdb->queries, 'Non-admin logs denied');
$can_manage = true;
$nonce_valid = false;
account_form(array('membership_operation' => 'comp_1'));
expect(!$wpdb->ledger && !$wpdb->members[7]['paid'], 'Invalid nonce denies complimentary grants');
$nonce_valid = true;
foreach (array(
    array('account_operation_key' => 'bad'),
    array('membership_operation' => 'comp_6'),
    array('amount_paid' => -1),
    array('amount_paid' => 'INF'),
    array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('999')),
    array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array(array('5'))),
    array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5'), 'ticket_methods' => array(5 => 'invalid')),
) as $invalid) {
    $wpdb = new AccountDatabase();
    $html = account_form($invalid);
    expect(!$wpdb->tickets && !$wpdb->ledger && str_contains($html, 'Member could not be saved'), 'Malformed financial request fails closed');
}

$wpdb = new AccountDatabase();
$wpdb->events[5]['ticket_prices'] = '{}';
account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5')));
expect(!$wpdb->tickets && !$wpdb->ledger, 'Missing paid ticket price rolls back grant instead of guessing');
account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('05')));
expect(!$wpdb->tickets && !$wpdb->ledger, 'Leading-zero ID cannot bypass ticket price validation or audit');
account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5'), 'ticket_methods' => array(5 => 'comp')));
expect(isset($wpdb->tickets[7][5]) && $wpdb->ledger[0]['amount'] === 0, 'Explicit COMP works without configured paid price');
foreach (array('paid', 'comp') as $method) {
    $wpdb = new AccountDatabase();
    account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('05', '005', '5'),
        'ticket_methods' => array(5 => $method)));
    expect(isset($wpdb->tickets[7][5]) && count($wpdb->ledger) === 1 &&
        $wpdb->ledger[0]['event_id'] === 5 && $wpdb->ledger[0]['amount'] == ($method === 'paid' ? 20 : 0),
        "Leading-zero and duplicate IDs produce exactly one $method ticket and ledger record");
}

$wpdb = new AccountDatabase();
$wpdb->tickets[7] = array(8 => array('id' => 8), 9 => array('id' => 9));
$wpdb->rsvps[7] = array(5 => array('created_at' => '2020-01-01'), 8 => array(), 9 => array());
account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array('5')));
expect(isset($wpdb->tickets[7][8], $wpdb->tickets[7][9]) &&
    $wpdb->rsvps[7][5]['created_at'] === '2020-01-01', 'Historical tickets and existing RSVPs are preserved');
$wpdb->events[5]['tickets_enabled'] = 0;
account_form(array('mmgr_paid_tickets_present' => 1, 'paid_event_ids' => array()));
expect(!isset($wpdb->tickets[7][5]) && isset($wpdb->rsvps[7][5]) && $wpdb->ledger[1]['activity'] === 'ticket_removed',
    'Closed-sale ticket can be removed without erasing RSVP or audit');
expect(mmgr_account_revenue('2028-01-31') === 20.0, 'Revocation is not falsely treated as a refund');

$wpdb = new AccountDatabase();
account_form(array('paid' => 1, 'amount_paid' => 150, 'mmgr_paid_tickets_present' => 1,
    'paid_event_ids' => array('5', '6'), 'ticket_methods' => array(5 => 'paid', 6 => 'comp')));
expect(mmgr_account_revenue('2028-01-31') === 170.0 && mmgr_account_revenue('2028-01', true) === 170.0,
    'Daily and monthly revenue include paid membership/ticket once, exclude COMPs');
expect(mmgr_account_revenue('2028-02', true) === 0.0, 'Revenue is restricted to WordPress-local date interval');
$html = account_logs();
expect(str_contains($html, '$170.00') && str_contains($html, 'COMP&#039;D TICKET') &&
    str_contains($html, 'PAID MEMBERSHIP'), 'Existing logs route displays ledger and combined revenue');
expect(str_contains($html, '>Accounting</h1>'), 'Logs heading matches Accounting admin menu');
expect(strpos($html, "This Month's Revenue") < strpos($html, 'Account transactions — membership'),
    'Account ledger follows all four dashboard cards');
expect(preg_match('/This Month\'s Revenue<\/p>\s*<\/div>\s*<\/div>\s*<h2>Account transactions/', $html) === 1,
    'Account ledger starts outside the stats dashboard grid');
expect(str_contains($html, "Today's Visits") && str_contains($html, 'No visit logs yet.'),
    'Financial activity does not create fake visits');
expect(!str_contains(implode("\n", $wpdb->queries), 'INSERT INTO wp_membership_visits'), 'No attendance records written by grants');
$row = mmgr_account_csv_row($wpdb->ledger[2]);
expect($row[5] === 'ticket_comp' && $row[6] === '0.00' && $row[7] === 'COMP' &&
    str_contains($row[10], "COMP'D TICKET"), 'CSV preserves explicit COMP type and zero charge');
expect(count(mmgr_get_account_transactions(7, 1, 1)) === 1, 'Account history supports pagination');
foreach (array('=SUM(1,2)', '+SUM(1,2)', '-SUM(1,2)', '@SUM(1,2)', "\tformula", "\rformula", "\nformula", '  =SUM(1,2)') as $payload) {
    $unsafe = array_merge($wpdb->ledger[2], array('name' => $payload, 'member_code' => $payload, 'description' => $payload));
    $safe = mmgr_account_csv_row($unsafe);
    expect($safe[3] === "'" . $payload && $safe[4] === "'" . $payload && $safe[10] === "'" . $payload,
        'CSV neutralizes formula/control-leading names, codes and descriptions: ' . json_encode($payload));
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --export ';
$csv = shell_exec($command . 'valid');
$lines = array_map('str_getcsv', explode("\n", trim($csv)));
expect(count($lines) === 3 && $lines[0][5] === 'Activity' &&
    str_contains($csv, "COMP'D MEMBERSHIP") && str_contains($csv, "COMP'D TICKET"),
    'Real logs CSV endpoint exports membership and ticket COMPs');
expect(str_contains(shell_exec($command . 'invalid'), 'security check failed'), 'CSV endpoint requires valid export nonce');
expect(str_contains(shell_exec($command . 'denied'), 'not allowed'), 'CSV endpoint requires administrator capability');
$malicious = shell_exec($command . 'malicious');
$malicious_rows = array_map('str_getcsv', explode("\n", trim($malicious)));
expect(str_starts_with($malicious_rows[1][3], "'=") && str_starts_with($malicious_rows[1][4], "'@"),
    'Real CSV endpoint safely exports malicious account names and codes');
expect(str_starts_with($malicious_rows[1][10], "COMP'D TICKET") &&
    str_contains($malicious_rows[1][10], '=HYPERLINK('),
    'Malicious event title remains literal inside safe audit description');
echo "\nResults: $passed passed, 0 failed\n";
