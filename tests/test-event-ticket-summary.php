<?php
// Run with: php tests/test-event-ticket-summary.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

$passed = 0;
function expect($condition, $description) {
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $passed++;
    echo "PASS: $description\n";
}
function add_action($hook, $callback) {}
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function wp_nonce_url($url, $action) { return $url; }
function wp_nonce_field($action, $name) {}
function wp_editor($content, $id, $settings) {}
function checked($value, $expected) {}

class SummaryDatabase {
    public $prefix = 'wp_';
    public $groups = array();
    public $summary_queries = 0;
    public $events = array();
    public function get_var($query) { return 'wp_membership_events'; }
    public function get_results($query, $format = null) {
        if (str_contains($query, 'membership_member_event_tickets')) {
            $this->summary_queries++;
            expect(str_contains($query, 'COUNT(*) AS ticket_count'), 'Counts paid ticket records');
            expect(str_contains($query, 'LEFT JOIN wp_memberships m ON m.id = t.member_id'), 'Preserves tickets with missing member details');
            expect(str_contains($query, 'GROUP BY t.event_id, m.level, m.sex'), 'Groups all event summaries in one query');
            expect(!str_contains($query, 'membership_event_rsvps') && !str_contains($query, 'm.paid'), 'Does not count RSVPs or filter by membership payment');
            return $this->groups;
        }
        if (str_contains($query, 'FROM wp_membership_events')) {
            return $this->events;
        }
        return array();
    }
}

require __DIR__ . '/../membership-manager-qr/includes/member-portal-functions.php';
$wpdb = new SummaryDatabase();
expect(mmgr_get_event_ticket_sales_summaries() === array(), 'No paid tickets gives an empty summary');
$wpdb->groups = array(
    array('event_id' => 5, 'level' => 'Single', 'sex' => 'Male', 'ticket_count' => '3'),
    array('event_id' => 5, 'level' => 'Single', 'sex' => 'Female', 'ticket_count' => '2'),
    array('event_id' => 5, 'level' => 'Couple', 'sex' => 'Male', 'ticket_count' => '4'),
    array('event_id' => 5, 'level' => ' COUPLES ', 'sex' => 'Female', 'ticket_count' => '1'),
    array('event_id' => 5, 'level' => 'Single Male', 'sex' => '', 'ticket_count' => '1'),
    array('event_id' => 5, 'level' => 'Female', 'sex' => null, 'ticket_count' => '1'),
    array('event_id' => 5, 'level' => null, 'sex' => null, 'ticket_count' => '1'),
    array('event_id' => 5, 'level' => 'Single', 'sex' => 'Other', 'ticket_count' => '1'),
    array('event_id' => 6, 'level' => 'Couple', 'sex' => '', 'ticket_count' => '1'),
);
$summaries = mmgr_get_event_ticket_sales_summaries();
expect($summaries[5] === array('total' => 19, 'single_men' => 4, 'single_ladies' => 3, 'couples' => 5, 'other' => 2), 'Couples count twice in the total and once in their own category');
expect($summaries[6]['total'] === 2 && $summaries[6]['couples'] === 1, 'Events have independent summaries');
expect(!isset($summaries[7]), 'Events without paid tickets have no sales');

$wpdb->events = array(
    array('id' => 5, 'event_name' => 'Party <test>', 'event_date' => '2099-10-31', 'active' => 1, 'tickets_enabled' => 1),
    array('id' => 7, 'event_name' => 'No sales', 'event_date' => '2099-11-01', 'active' => 0, 'tickets_enabled' => 0),
);
$queries_before = $wpdb->summary_queries;
ob_start();
require __DIR__ . '/../membership-manager-qr/includes/admin/events-page.php';
$html = ob_get_clean();
expect($wpdb->summary_queries === $queries_before + 1, 'Admin listing loads summaries once, not once per event');
expect(str_contains($html, '<th>Tickets Sold</th>'), 'Admin events list has a ticket sales column');
expect(str_contains($html, 'Total: 19') && str_contains($html, 'Single men: 4') && str_contains($html, 'Single ladies: 3') && str_contains($html, 'Couples: 5'), 'Admin shows total and all requested categories');
expect(str_contains($html, 'Other / unspecified: 2'), 'Unclassified tickets remain visible in the breakdown');
expect(str_contains($html, 'Total: 0') && str_contains($html, 'Single men: 0') && str_contains($html, 'Single ladies: 0') && str_contains($html, 'Couples: 0'), 'Events without sales show zeros even when ticket sales are disabled');
expect(str_contains($html, 'Couples count as 2 tickets in the total.'), 'Explains how couples contribute to the total');
expect(str_contains($html, 'Party &lt;test&gt;') && !str_contains($html, 'Party <test>'), 'Event names remain escaped');

echo "\nResults: $passed passed, 0 failed\n";
