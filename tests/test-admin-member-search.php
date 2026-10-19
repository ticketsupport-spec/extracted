<?php
// Run with: php tests/test-admin-member-search.php
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
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_js($value) { return addslashes($value); }
function wp_create_nonce($action) { return 'test-nonce'; }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=test-nonce'; }

class MemberSearchDatabase {
    public $prefix = 'site_2_';
    public $members = array();
    public $query = '';
    public $prepared_query = '';
    public $args = array();

    public function esc_like($value) {
        return addcslashes($value, '_%\\');
    }
    public function prepare($query, $args) {
        $this->prepared_query = $query;
        $this->args = $args;
        foreach ($args as $arg) {
            $query = preg_replace_callback('/%s/', function() use ($arg) {
                return "'" . str_replace("'", "''", $arg) . "'";
            }, $query, 1);
        }
        return $query;
    }
    public function get_results($query, $format) {
        if (strpos($query, 'SELECT * FROM site_2_memberships') === 0) {
            $this->query = $query;
            return $this->members;
        }
        return array();
    }
    public function get_var($query) {
        if ($query === 'SELECT COUNT(*) FROM site_2_memberships') {
            return 12;
        }
        if (strpos($query, 'WHERE paid = 0') !== false) {
            return 3;
        }
        return null;
    }
}

$wpdb = new MemberSearchDatabase();
require __DIR__ . '/../membership-manager-qr/includes/admin-menu.php';

function render_members($search = null) {
    global $wpdb;
    $_GET = $search === null ? array() : array('member_search' => $search);
    $wpdb->prepared_query = '';
    $wpdb->args = array();
    ob_start();
    mmgr_members_page();
    return ob_get_clean();
}

$html = render_members();
expect($wpdb->query === 'SELECT * FROM site_2_memberships ORDER BY paid ASC, id DESC', 'Unfiltered list keeps existing ordering');
expect(strpos($html, 'name="page" value="membership_manager"') !== false, 'Search retains the admin page route');
expect(strpos($html, 'Add your first member') !== false, 'Empty unfiltered list offers member creation');
expect(strpos($html, 'Clear search') === false, 'Unfiltered list does not show reset control');
expect(strpos($html, 'name="delete"') === false, 'Search form does not replay member actions');

foreach (array('Jane', 'Smith', 'jane@example.com', '5551234') as $term) {
    $html = render_members($term);
    expect(strpos($wpdb->prepared_query, 'name LIKE %s OR partner_name LIKE %s OR email LIKE %s OR phone LIKE %s') !== false, "Search covers name and contact fields for $term");
    expect(array_slice($wpdb->args, 0, 4) === array_fill(0, 4, '%' . $term . '%'), "Search uses bound partial matches for $term");
}
expect($wpdb->args[4] === '%5551234%', 'Unformatted phone search includes normalized match');
expect(strpos($wpdb->prepared_query, "REPLACE(phone, ' ', '')") !== false, 'Stored phone formatting is stripped for matching');
render_members('+1 (555) 123-4567');
expect($wpdb->args[4] === '%15551234567%', 'Formatted phone search uses only digits for normalized match');
render_members('jane123@example.com');
expect(count($wpdb->args) === 4, 'Digits in an email do not trigger unrelated phone matches');
render_members('100%_match');
expect($wpdb->args[0] === '%100\\%\\_match%', 'SQL wildcard characters are treated literally');
render_members("O\\'Brien");
expect($wpdb->args[0] === "%O'Brien%", 'WordPress slashes are removed before binding search');
$html = render_members('"><img src=x onerror=alert(1)>');
expect(strpos($html, '<img src=x') === false, 'Search input cannot inject HTML');
expect(strpos($html, 'value="&quot;&gt;"') !== false, 'Retained search value is attribute escaped');
$html = render_members(array('Jane'));
expect(strpos($wpdb->query, ' WHERE ') === false, 'Malformed array search falls back to the full list');
$html = render_members('   ');
expect(strpos($wpdb->query, ' WHERE ') === false, 'Whitespace-only search falls back to the full list');
$html = render_members('Nobody');
expect(strpos($html, 'No members match your search') !== false, 'No-match search has an appropriate empty state');
expect(strpos($html, 'Add your first member') === false, 'No-match search does not imply an empty membership database');
expect(strpos($html, 'Clear search') !== false, 'Active search offers a reset control');
expect(strpos($html, '<h3 style="margin:0;font-size:32px;">12</h3>') !== false, 'Search preserves the overall member total');

$wpdb->members = array(array(
    'id' => 7, 'name' => 'Jane Smith', 'partner_name' => 'John Smith',
    'member_code' => 'ABC123', 'email' => 'long.email.address@example.com',
    'phone' => '(555) 123-4567', 'level' => 'Standard', 'paid' => 0,
    'banned' => 0, 'expire_date' => '', 'last_visited' => '', 'photo_url' => ''
));
$html = render_members('Jane');
expect(strpos($html, '1 matching member(s)') !== false, 'Search reports the matching member count');
expect(strpos($html, 'Jane Smith') !== false && strpos($html, 'John Smith') !== false, 'Member and partner remain visible');
foreach (array('Photo', 'QR Code', 'Member', 'Contact', 'Level', 'Payment', 'Status', 'Expires', 'Last Visit', 'Actions') as $label) {
    expect(strpos($html, 'class="mmgr-mobile-label" aria-hidden="true">' . $label) !== false, "Mobile cards label $label");
}
foreach (array('Edit', 'Mark Paid', 'Ban', 'Delete', 'Regen QR') as $action) {
    expect(strpos($html, '>' . $action . '<') !== false || strpos($html, $action) !== false, "$action remains available on member cards");
}
expect(strpos($html, '@media screen and (max-width: 782px)') !== false, 'Card styles only apply at the mobile breakpoint');
expect(strpos($html, 'min-height:44px') !== false, 'Mobile search and actions have touch-sized targets');
expect(strpos($html, 'overflow-wrap:anywhere') !== false, 'Long contact information can wrap');
$wpdb->members[0]['banned'] = 1;
$wpdb->members[0]['paid'] = 1;
$html = render_members();
expect(strpos($html, '>Unban</a>') !== false && strpos($html, '⛔ BANNED') !== false, 'Paid banned members keep their status and unban action');
expect(strpos($html, '💰 Mark Paid') === false, 'Paid members do not display the payment action');

echo "\nResults: $passed passed, 0 failed\n";
