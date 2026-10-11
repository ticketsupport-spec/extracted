<?php
// Run with: php tests/test-event-tickets.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
$current_member = null;

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
function get_option($key, $default = false) { return $default; }
function wp_create_nonce($action) { return 'test-nonce'; }

class TestDatabase {
    public $prefix = 'wp_';
    public $events = array();
    public $level_prices = array('Single' => 150.00, 'Couple' => 250.00);

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
        if (str_contains($query, 'SELECT * FROM wp_membership_events')) {
            return $this->events;
        }
        return array();
    }
    public function get_col($query) { return array(); }
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

echo "\nResults: $passed passed, 0 failed\n";
