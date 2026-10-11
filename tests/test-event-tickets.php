<?php
// Run with: php tests/test-event-tickets.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$current_member = null;
$can_manage = true;
$options = array();

function add_action($hook, $callback) {}
function add_shortcode($tag, $callback) {
    global $shortcodes;
    $shortcodes[$tag] = $callback;
}
function mmgr_get_current_member() {
    global $current_member;
    $_GET['usercod'] = $current_member['member_code'] ?? '';
    return $current_member;
}
function nocache_headers() {}
function wp_unslash($value) { return $value; }
function home_url($path) { return 'https://example.com' . $path; }
function admin_url($path) { return home_url('/wp-admin/' . $path); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_js($value) { return addslashes($value); }
function esc_sql($value) { return addslashes($value); }
function wp_kses_post($value) { return $value; }
function wpautop($value) { return '<p>' . $value . '</p>'; }
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value) { global $options; $options[$key] = $value; }
function wp_create_nonce($action) { return 'test-nonce'; }
function current_time($format) { return '2026-10-11 12:00:00'; }
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function current_user_can($capability) { global $can_manage; return $can_manage; }
function get_current_user_id() { return 3; }

class TestDatabase {
    public $prefix = 'wp_';
    public $events = array();
    public $level_prices = array('Single' => 150.00, 'Couple' => 250.00);
    public $tickets = array();
    public $rsvps = array();
    public $rsvp_write_fails = false;
    public $backfill_runs = 0;
    public $write_fails = false;
    public $writes = 0;

    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $query = preg_replace('/%[ds]/', is_numeric($arg) ? (string) $arg : "'" . $arg . "'", $query, 1);
        }
        return $query;
    }
    public function get_var($query) {
        if (str_contains($query, 'FROM wp_membership_member_event_tickets')) {
            preg_match('/member_id = (\d+) AND event_id = (\d+)/', $query, $m);
            return isset($this->tickets[$m[1]][$m[2]]) ? 1 : null;
        }
        if (str_contains($query, 'FROM wp_membership_event_rsvps')) {
            preg_match('/event_id = (\d+) AND member_id = (\d+)/', $query, $m);
            return isset($this->rsvps[$m[2]][$m[1]]) ? 1 : null;
        }
        if (str_contains($query, 'FROM wp_membership_levels')) {
            preg_match("/level_name = '([^']*)'/", $query, $m);
            return $this->level_prices[$m[1]] ?? null;
        }
        return 0;
    }
    public function get_row($query, $format) {
        preg_match('/id = (\d+)/', $query, $m);
        foreach ($this->events as $event) {
            if ((int) $event['id'] === (int) $m[1] && $event['active']) {
                return $event;
            }
        }
        return null;
    }
    public function get_results($query, $format = null) {
        if (str_contains($query, 'FROM wp_membership_event_rsvps')) {
            $rows = array();
            foreach ($this->rsvps as $member_id => $events) {
                foreach ($events as $event_id => $rsvp) {
                    $rows[] = array(
                        'event_id' => $event_id, 'member_id' => $member_id,
                        'community_alias' => 'Member ' . $member_id,
                        'level' => 'Couple', 'sex' => '', 'community_photo_url' => '',
                    );
                }
            }
            return $rows;
        }
        if (str_contains($query, 'JOIN wp_membership_member_event_tickets')) {
            preg_match('/t.member_id = (\d+)/', $query, $match);
            $member_id = (int) $match[1];
            expect(str_contains($query, "e.event_date >= '2026-10-11'"), 'Upcoming tickets use the WordPress local date');
            $results = array();
            foreach ($this->events as $event) {
                $ticket = $this->tickets[$member_id][$event['id']] ?? null;
                if (!$event['active'] || $event['event_date'] < '2026-10-11') {
                    continue;
                }
                if (str_contains($query, 'LEFT JOIN') ? ($event['tickets_enabled'] || $ticket) : (bool) $ticket) {
                    $results[] = array_merge($event, array('paid_ticket_id' => $ticket ? 1 : null));
                }
            }
            usort($results, function($a, $b) { return strcmp($a['event_date'], $b['event_date']); });
            return $results;
        }
        if (str_contains($query, 'SELECT * FROM wp_membership_events')) {
            return array_filter($this->events, function($event) {
                return $event['active'] && $event['event_date'] >= '2026-10-11';
            });
        }
        return array();
    }
    public function get_col($query) {
        if (str_contains($query, 'FROM wp_membership_event_rsvps')) {
            preg_match('/member_id = (\d+)/', $query, $m);
            return array_keys($this->rsvps[$m[1]] ?? array());
        }
        return array();
    }
    public function query($query) {
        $this->backfill_runs++;
        if ($this->write_fails) { return false; }
        expect(str_contains($query, 'ON DUPLICATE KEY UPDATE'), 'Backfill tolerates concurrent RSVP creation');
        foreach ($this->events as $event) {
            if (!$event['active'] || $event['event_date'] < '2026-10-11') {
                continue;
            }
            foreach ($this->tickets as $member_id => $tickets) {
                if (isset($tickets[$event['id']]) && !isset($this->rsvps[$member_id][$event['id']])) {
                    $this->rsvps[$member_id][$event['id']] = array('created_at' => $tickets[$event['id']]['created_at']);
                }
            }
        }
        return 1;
    }
    public function insert($table, $data, $formats = null) {
        if ($table === 'wp_membership_event_rsvps') {
            if ($this->rsvp_write_fails) { return false; }
            expect(!isset($this->rsvps[$data['member_id']][$data['event_id']]), 'RSVP insertion never duplicates an attendee');
            $this->writes++;
            $this->rsvps[$data['member_id']][$data['event_id']] = $data;
            return 1;
        }
        expect($table === 'wp_membership_member_event_tickets', 'Ticket saves do not change membership payments');
        if ($this->write_fails) { return false; }
        $this->writes++;
        $this->tickets[$data['member_id']][$data['event_id']] = $data;
        return 1;
    }
    public function delete($table, $where, $formats = null) {
        if ($this->write_fails) { return false; }
        $this->writes++;
        unset($this->tickets[$where['member_id']][$where['event_id']]);
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

require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
require __DIR__ . '/../membership-manager-qr/includes/member-portal-shortcodes.php';

$wpdb = new TestDatabase();
$event = array(
    'id' => 5, 'event_name' => 'Halloween Party', 'event_date' => '2099-10-31',
    'start_time' => '20:00:00', 'end_time' => '', 'location' => 'Main Hall',
    'description' => 'Costumes!', 'image_url' => '', 'active' => 1,
    'tickets_enabled' => 1,
    'ticket_prices' => json_encode(array('Single' => '20', 'Couple' => 35.5, 'Youth' => '')),
    'ticket_pay_cash' => 1, 'ticket_pay_etransfer' => 1,
    'ticket_etransfer_email' => 'pay@example.com', 'ticket_instructions' => "Tickets are limited\n<b>No refunds</b>",
);
$plain_event = array_merge($event, array('id' => 6, 'event_name' => 'Regular Night', 'tickets_enabled' => 0));
$wpdb->events = array($event, $plain_event);

$unpaid  = array('id' => 7, 'member_code' => 'CODE7', 'level' => 'Single', 'paid' => 0, 'expire_date' => null);
$paid    = array('id' => 8, 'member_code' => 'CODE8', 'level' => 'Couple', 'paid' => 1, 'expire_date' => '2099-01-01');
$expired = array('id' => 9, 'member_code' => 'CODE9', 'level' => 'Single', 'paid' => 1, 'expire_date' => '2000-01-01');
$youth   = array('id' => 10, 'member_code' => 'CODE10', 'level' => 'Youth', 'paid' => 1, 'expire_date' => null);

// Price decoding
$prices = mmgr_get_event_ticket_prices($event);
expect($prices === array('Single' => 20.0, 'Couple' => 35.5), 'Blank level prices are omitted');
expect(mmgr_get_event_ticket_prices(array('ticket_prices' => null)) === array(), 'Missing prices decode to empty list');

// Paid membership detection
expect(!mmgr_member_has_paid_membership($unpaid), 'Unpaid member has no paid membership');
expect(mmgr_member_has_paid_membership($paid), 'Paid current member has paid membership');
expect(!mmgr_member_has_paid_membership($expired), 'Expired member has no paid membership');
expect(mmgr_member_has_paid_membership($youth), 'Paid member with no expiry has paid membership');

// Totals
$calc = mmgr_calculate_event_ticket_total($event, $unpaid);
expect($calc['membership_due'] && $calc['membership_fee'] === 150.0, 'Unpaid member is charged membership fee');
expect($calc['total'] === 170.0, 'Unpaid total = membership fee + ticket price');
$calc = mmgr_calculate_event_ticket_total($event, $paid);
expect(!$calc['membership_due'] && $calc['membership_fee'] === 0.0, 'Paid member is not charged membership fee');
expect($calc['total'] === 35.5, 'Paid total = ticket price for member level');
$calc = mmgr_calculate_event_ticket_total($event, $expired);
expect($calc['total'] === 170.0, 'Expired member pays membership fee + ticket price');
expect(!mmgr_calculate_event_ticket_total($event, $youth)['available'], 'Level without price is unavailable');

// URL
expect(mmgr_event_tickets_url(5, $paid) === 'https://example.com/member-events/?usercod=CODE8&tickets=5', 'Tickets URL is member specific');

// Buy Tickets button on Upcoming Events
$current_member = $paid;
$html = $shortcodes['mmgr_upcoming_events'](array());
expect(substr_count($html, 'BUY TICKETS') === 1, 'Widget shows BUY TICKETS only for ticketed events');
expect(str_contains($html, '/member-events/?usercod=CODE8&amp;tickets=5'), 'Widget button links to ticket page');
$current_member = null;
$html = $shortcodes['mmgr_upcoming_events'](array());
expect(!str_contains($html, 'BUY TICKETS'), 'Visitors do not see BUY TICKETS');

$current_member = $paid;
unset($_GET['tickets']);
$html = $shortcodes['mmgr_member_events']();
expect(substr_count($html, 'BUY TICKETS') === 1, 'Events page shows BUY TICKETS next to Going button');

// Buy Tickets page
$_GET['tickets'] = '5';
$current_member = $unpaid;
$html = $shortcodes['mmgr_member_events']();
expect(str_contains($html, 'Halloween Party') && str_contains($html, 'Main Hall'), 'Ticket page shows event info');
expect(str_contains($html, 'Membership fee (Single)') && str_contains($html, '$170.00'), 'Ticket page shows membership + ticket total for unpaid member');
expect(str_contains($html, 'Cash in Person') && str_contains($html, 'pay@example.com'), 'Enabled payment options shown');
expect(str_contains($html, '&lt;b&gt;No refunds&lt;/b&gt;'), 'Ticket instructions are escaped');

$current_member = $paid;
$html = $shortcodes['mmgr_member_events']();
expect(!str_contains($html, 'Membership fee (') && str_contains($html, '$35.50'), 'Paid member sees ticket price only');

$wpdb->events[0]['ticket_pay_cash'] = 0;
$html = $shortcodes['mmgr_member_events']();
expect(!str_contains($html, 'Cash in Person') && str_contains($html, 'Email Money Transfer'), 'Disabled payment option hidden');
$wpdb->events[0]['ticket_pay_etransfer'] = 0;
$html = $shortcodes['mmgr_member_events']();
expect(str_contains($html, 'contact us to arrange payment'), 'Fallback shown when no payment option enabled');

$current_member = $youth;
$html = $shortcodes['mmgr_member_events']();
expect(str_contains($html, 'not available for your membership level'), 'Unpriced level cannot buy');

$_GET['tickets'] = '6';
$html = $shortcodes['mmgr_member_events']();
expect(str_contains($html, 'Tickets are not available for this event'), 'Non-ticketed event has no ticket page');

// Admin-assigned paid tickets and digital card display.
$wpdb->events = array(
    $event,
    array_merge($event, array('id' => 11, 'event_name' => '<script>alert(1)</script>', 'event_date' => '2026-10-11')),
    array_merge($event, array('id' => 12, 'event_date' => '2000-01-01')),
    array_merge($event, array('id' => 13, 'active' => 0)),
    $plain_event,
);
expect(mmgr_save_member_paid_tickets(8, array('5', '11', '5')), 'Admin can assign multiple paid tickets');
expect(count($wpdb->tickets[8]) === 2, 'Duplicate IDs create one ticket per event');
expect(count($wpdb->rsvps[8]) === 2, 'Paid ticket assignments automatically add members to Going');
expect($wpdb->tickets[8][5]['created_by'] === 3, 'Ticket assignment records the administrator');
$current_member = $paid;
unset($_GET['tickets']);
$html = $shortcodes['mmgr_upcoming_events'](array());
expect(substr_count($html, 'YOU ARE GOING') === 2 && !str_contains($html, 'BUY TICKETS'), 'Widget replaces buy buttons for paid ticket holders');
$html = $shortcodes['mmgr_member_events']();
expect(substr_count($html, 'YOU ARE GOING') === 2 && !str_contains($html, 'BUY TICKETS'), 'Events page replaces buy buttons for paid ticket holders');
expect(preg_match_all('/>\s*✅ Going – Remove Me\s*<\/button>/', $html) === 2, 'Paid ticket holders also have Going RSVP state');
expect(substr_count($html, '👥 Going (1)') === 2 && str_contains($html, 'Member 8'), 'Paid ticket holders appear in event Going lists');
expect(str_contains(mmgr_render_buy_tickets_button($event, $unpaid), 'BUY TICKETS'), 'Another member still sees BUY TICKETS');
expect(!mmgr_member_has_paid_event_ticket(0, 5), 'Missing member cannot have a paid ticket');
$writes = $wpdb->writes;
expect(mmgr_save_member_paid_tickets(8, array('5', '11')), 'Resaving existing tickets succeeds');
expect($wpdb->writes === $writes, 'Resaving paid selections does not create duplicates');
foreach (array(array('12'), array('13'), array('6'), array('999'), array('5 OR 1=1'), array(array('5')), '5') as $invalid) {
    expect(!mmgr_save_member_paid_tickets(8, $invalid), 'Invalid or unavailable selection is rejected');
}
expect($wpdb->writes === $writes, 'Rejected selections do not modify paid tickets');
$can_manage = false;
expect(!mmgr_save_member_paid_tickets(8, array('5')), 'Members cannot grant themselves paid tickets');
$can_manage = true;
$html = mmgr_render_member_paid_events($paid);
expect(str_contains($html, 'Your upcoming events') && substr_count($html, '>PAID</span>') === 2, 'Paid ticket cards have heading and PAID badges');
expect(str_contains($html, 'October 11, 2026') && str_contains($html, '8:00 PM'), 'Cards include event date and start time');
expect(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'Paid event titles are escaped');
expect(strpos($html, 'October 11, 2026') < strpos($html, 'October 31, 2099'), 'Paid tickets appear in chronological order');
expect(str_contains(mmgr_render_member_paid_events($unpaid), 'No prepaid event tickets yet.'), 'Another member cannot see assigned tickets');
expect(mmgr_render_member_paid_events(null) === '', 'Visitors cannot see paid ticket cards');
$wpdb->events[0]['tickets_enabled'] = 0;
expect(str_contains(mmgr_render_member_paid_events($paid), 'Halloween Party'), 'Paid tickets remain visible when sales close');
expect(str_contains(mmgr_render_buy_tickets_button($wpdb->events[0], $paid), 'YOU ARE GOING'), 'Paid ticket status remains visible after sales close');
$wpdb->tickets[8][12] = array('event_id' => 12);
$wpdb->tickets[8][13] = array('event_id' => 13);
expect(count(mmgr_get_member_paid_events(8)) === 2, 'Past and inactive paid events are hidden');
expect(mmgr_save_member_paid_tickets(8, array('11')), 'Admin can remove a paid ticket after sales close');
expect(!isset($wpdb->tickets[8][5]) && isset($wpdb->tickets[8][12], $wpdb->tickets[8][13]), 'Removing upcoming tickets retains historical and inactive assignments');
expect(isset($wpdb->rsvps[8][5]), 'Removing payment does not remove Going RSVP');
$wpdb->write_fails = true;
expect(!mmgr_save_member_paid_tickets(8, array()), 'Failed ticket removal reports failure');
$wpdb->write_fails = false;
expect(mmgr_save_member_paid_tickets(8, array()), 'Empty selection removes remaining editable tickets');
$wpdb->write_fails = true;
expect(!mmgr_save_member_paid_tickets(8, array('11')), 'Failed ticket insertion reports failure');
$wpdb->write_fails = false;
$wpdb->rsvp_write_fails = true;
unset($wpdb->rsvps[8][11]);
expect(!mmgr_save_member_paid_tickets(8, array('11')), 'Failed automatic RSVP reports failure');
$wpdb->rsvp_write_fails = false;
expect(mmgr_save_member_paid_tickets(8, array('11')), 'Resaving repairs a missing RSVP for an existing paid ticket');
expect(isset($wpdb->rsvps[8][11]), 'Repaired paid ticket holder is Going');

// Upgrade existing paid tickets, preserving manual RSVPs and historical records.
$wpdb->events[0]['tickets_enabled'] = 1;
$wpdb->tickets[8][5] = array('created_at' => '2026-10-01 12:00:00');
$wpdb->rsvps[8][11] = array('created_at' => '2026-09-01 12:00:00');
unset($wpdb->rsvps[8][5]);
$wpdb->write_fails = true;
mmgr_backfill_paid_ticket_rsvps();
expect(empty($options['mmgr_paid_ticket_rsvps_backfilled']), 'Failed backfill can retry');
$wpdb->write_fails = false;
mmgr_backfill_paid_ticket_rsvps();
expect($wpdb->rsvps[8][5]['created_at'] === '2026-10-01 12:00:00', 'Existing paid tickets are added to Going on upgrade');
expect($wpdb->rsvps[8][11]['created_at'] === '2026-09-01 12:00:00', 'Backfill preserves existing RSVP timestamps');
expect(!isset($wpdb->rsvps[8][12]) && !isset($wpdb->rsvps[8][13]), 'Backfill does not add past or inactive events');
$runs = $wpdb->backfill_runs;
mmgr_backfill_paid_ticket_rsvps();
expect($wpdb->backfill_runs === $runs, 'Successful backfill runs only once');

echo "\nResults: $passed passed, 0 failed\n";
