<?php
// Run with: php tests/test-newsletter.php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);

$passed = 0;
$can_manage = true;
$valid_nonce = true;
$actions = array();
$mail = array();
$transients = array();
$media_enqueues = 0;
$editor = null;

function expect($condition, $description) {
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $passed++;
    echo "PASS: $description\n";
}
function add_action($hook, $callback) { global $actions; $actions[$hook] = $callback; }
function current_user_can($capability) {
    global $can_manage;
    if ($capability !== 'manage_options') {
        throw new RuntimeException('Unexpected capability: ' . $capability);
    }
    return $can_manage;
}
function wp_die($message) { throw new RuntimeException($message); }
function check_admin_referer($action, $field) {
    global $valid_nonce;
    expect($action === 'mmgr_send_newsletter' && $field === 'mmgr_newsletter_nonce', 'Send nonce checked');
    if (!$valid_nonce) {
        wp_die('Invalid nonce');
    }
}
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return trim(preg_replace('/[\r\n]+/', ' ', strip_tags($value))); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_kses_post($value) {
    // Test double only; WordPress supplies the full HTML and attribute allowlist.
    return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value);
}
function wp_strip_all_tags($value) { return strip_tags($value); }
function wpautop($value) { return '<p>' . str_replace("\n\n", '</p><p>', $value) . '</p>'; }
function get_option($key, $default = false) {
    $options = array('admin_email' => 'admin@example.com', 'mmgr_email_from_name' => 'Club Newsletter');
    return $options[$key] ?? $default;
}
function get_bloginfo($key) { return 'Club'; }
function wp_mail($to, $subject, $body, $headers) {
    global $mail;
    $mail[] = compact('to', 'subject', 'body', 'headers');
    return $to !== 'fail@example.com';
}
function get_current_user_id() { return 7; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function delete_transient($key) { global $transients; unset($transients[$key]); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
class NewsletterRedirect extends RuntimeException {}
function wp_safe_redirect($url) { throw new NewsletterRedirect($url); }
function wp_enqueue_media() { global $media_enqueues; $media_enqueues++; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_nonce_field($action, $name) { echo '<input name="' . esc_attr($name) . '">'; }
function wp_editor($body, $id, $settings) {
    global $editor;
    $editor = compact('body', 'id', 'settings');
    echo '<textarea>' . esc_html($body) . '</textarea>';
}
function submit_button($label) { echo '<button>' . esc_html($label) . '</button>'; }
function current_time($format) { return '2026-10-20'; }
function selected($actual, $expected) { if ((string) $actual === (string) $expected) echo 'selected="selected"'; }
function checked($actual) { if ($actual) echo 'checked="checked"'; }

class NewsletterDatabase {
    public $prefix = 'site_2_';
    public $last_error = '';
    public $subscribers = array();
    public $queries = array();
    public $events = array(
        array('id' => 12, 'event_name' => 'Upcoming <party>', 'event_date' => '2026-10-21', 'active' => 1),
        array('id' => 13, 'event_name' => 'Today', 'event_date' => '2026-10-20', 'active' => 1),
        array('id' => 14, 'event_name' => 'Past', 'event_date' => '2026-10-19', 'active' => 1),
        array('id' => 15, 'event_name' => 'Inactive', 'event_date' => '2026-10-21', 'active' => 0),
    );
    public $tickets = array();
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) {
            $sql = preg_replace_callback('/%[ds]/', function($match) use ($arg) {
                return $match[0] === '%d' ? (string) (int) $arg : "'" . addslashes($arg) . "'";
            }, $sql, 1);
        }
        return $sql;
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        expect(str_contains($sql, 'active = 1 AND event_date >= \'2026-10-20\''), 'Selected event validated against local date and active status');
        preg_match('/WHERE id = (\d+)/', $sql, $match);
        foreach ($this->events as $event) {
            if ($event['id'] === (int) $match[1] && $event['active'] && $event['event_date'] >= '2026-10-20') {
                return $event['id'];
            }
        }
        return null;
    }
    public function get_results($sql, $format) {
        $this->queries[] = $sql;
        expect($format === ARRAY_A, 'Subscriber results use associative arrays');
        if ($this->last_error) {
            return null;
        }
        if (str_contains($sql, 'FROM site_2_membership_events')) {
            expect(str_contains($sql, "active = 1 AND event_date >= '2026-10-20'"), 'Composer loads active upcoming events using local date');
            return array_filter($this->events, function($event) { return $event['active'] && $event['event_date'] >= '2026-10-20'; });
        }
        expect(str_contains($sql, 'FROM `site_2_memberships` WHERE '), 'Recipients queried using site prefix');
        $ticket_filter = str_contains($sql, 'EXISTS');
        $match = array();
        if ($ticket_filter) {
            expect(str_contains($sql, 'FROM site_2_membership_member_event_tickets t') && str_contains($sql, 't.member_id = `site_2_memberships`.id'), 'Ticket filter uses correlated paid-ticket records, not RSVPs');
            preg_match('/t.event_id = (\d+)/', $sql, $match);
        }
        return array_values(array_filter($this->subscribers, function($row) use ($sql, $ticket_filter, $match) {
            if (str_contains($sql, 'WHERE newsletter = 1') && $row['newsletter'] !== 1) return false;
            if (!$ticket_filter) return true;
            $has_ticket = in_array((int) $match[1], $this->tickets[$row['id']] ?? array(), true);
            return str_contains($sql, 'NOT EXISTS') ? !$has_ticket : $has_ticket;
        }));
    }
}
$wpdb = new NewsletterDatabase();
require __DIR__ . '/../membership-manager-qr/includes/newsletter-functions.php';

expect(($actions['admin_post_mmgr_send_newsletter'] ?? '') === 'mmgr_handle_send_newsletter', 'Authenticated send handler registered');
expect(!isset($actions['admin_post_nopriv_mmgr_send_newsletter']), 'No public send endpoint registered');

$_GET = array('page' => 'membership_newsletter');
mmgr_newsletter_editor_assets();
expect($media_enqueues === 1, 'Media library loaded on newsletter screen');
$_GET['page'] = 'membership_manager';
mmgr_newsletter_editor_assets();
expect($media_enqueues === 1, 'Media library not loaded on unrelated screens');
$can_manage = false;
$_GET['page'] = 'membership_newsletter';
mmgr_newsletter_editor_assets();
ob_start();
mmgr_newsletter_composer();
$output = ob_get_clean();
expect($output === '' && $media_enqueues === 1, 'Unauthorized users get no composer or media assets');
$can_manage = true;

ob_start();
mmgr_newsletter_composer();
$output = ob_get_clean();
expect(str_contains($output, 'admin-post.php') && str_contains($output, 'mmgr_newsletter_nonce'), 'Composer posts to protected endpoint');
expect($editor['id'] === 'mmgr_newsletter_body' && $editor['settings']['textarea_name'] === 'mmgr_newsletter_body', 'Editor submits message body');
expect($editor['settings']['tinymce'] && $editor['settings']['quicktags'] && $editor['settings']['media_buttons'], 'Visual, HTML, and image controls enabled');
expect(str_contains($output, 'Upcoming &lt;party&gt;') && !str_contains($output, '>Past') && !str_contains($output, '>Inactive'), 'Upcoming event names escaped and unavailable events excluded');
expect(str_contains($output, 'value="with_ticket"') && str_contains($output, 'value="without_ticket"'), 'Both ticket audiences available');
expect(str_contains($output, 'name="mmgr_newsletter_override"') && !str_contains($output, 'checked="checked"'), 'Override is explicit and off by default');

$wpdb->subscribers = array(
    array('email' => 'one@example.com', 'newsletter' => 1),
    array('email' => 'ONE@example.com', 'newsletter' => 1),
    array('email' => 'invalid', 'newsletter' => 1),
    array('email' => '', 'newsletter' => 1),
    array('email' => 'fail@example.com', 'newsletter' => 1),
    array('email' => 'opted-out@example.com', 'newsletter' => 0),
);
$result = mmgr_send_newsletter('Club news', '<h2>News</h2><img src="https://example.com/photo.jpg" alt="Club"><script>alert(1)</script>');
expect($result === array('sent' => 1, 'failed' => 1, 'skipped' => 3), 'Success, failure, duplicate and invalid addresses counted');
expect(count($mail) === 2, 'Duplicate, invalid and opted-out addresses receive no mail');
expect($mail[0]['to'] === 'one@example.com' && $mail[1]['to'] === 'fail@example.com', 'Each recipient gets an individual message');
expect(in_array('Content-Type: text/html; charset=UTF-8', $mail[0]['headers'], true), 'HTML content type set per message');
expect(in_array('From: Club Newsletter <admin@example.com>', $mail[0]['headers'], true), 'Configured sender reused');
expect(count($mail[0]['headers']) === 2, 'No shared recipient headers');
expect(str_contains($mail[0]['body'], '<h2>News</h2>') && str_contains($mail[0]['body'], '<img src='), 'Formatting and image preserved');
expect(!str_contains($mail[0]['body'], '<script>'), 'Body passed through HTML sanitization');

$wpdb->subscribers = array();
$result = mmgr_send_newsletter('News', 'Hello');
expect(isset($result['error']) && count($mail) === 2, 'Empty subscriber list sends nothing');
$wpdb->last_error = 'Database unavailable';
$result = mmgr_send_newsletter('News', 'Hello');
expect(isset($result['error']) && count($mail) === 2, 'Database failure sends nothing');
$wpdb->last_error = '';
$wpdb->subscribers = array(array('email' => 'one@example.com', 'newsletter' => 1));

function submit_newsletter($subject, $body, $options = array()) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array_merge(array('mmgr_newsletter_subject' => $subject, 'mmgr_newsletter_body' => $body), $options);
    try {
        mmgr_handle_send_newsletter();
        throw new RuntimeException('Missing redirect');
    } catch (NewsletterRedirect $redirect) {
        expect($redirect->getMessage() === admin_url('admin.php?page=membership_newsletter'), 'POST redirects to newsletter screen');
    }
    return get_transient('mmgr_newsletter_result_7');
}

foreach (array(array('', 'Hello'), array('Subject', ''), array('Subject', '<p><br></p>'), array(array('bad'), array('bad'))) as $input) {
    $result = submit_newsletter($input[0], $input[1]);
    expect(isset($result['error']), 'Missing or malformed subject/body rejected');
}
expect(count($mail) === 2, 'Invalid submissions never send mail');
$result = submit_newsletter('<b>Club</b> news', addslashes("<p>It's news</p><script>bad()</script>"));
expect($result['sent'] === 1, 'Valid submission sends mail');
expect($mail[2]['subject'] === 'Club news', 'Subject sanitized');
expect(str_contains($mail[2]['body'], "It's news") && !str_contains($mail[2]['body'], '<script>'), 'Submission unslashed and sanitized');
$result = submit_newsletter('Photo', '<img src="https://example.com/photo.jpg" alt="Club">');
expect($result['sent'] === 1, 'Image-only newsletters accepted');

foreach (array('permission', 'nonce', 'method') as $failure) {
    $can_manage = $failure !== 'permission';
    $valid_nonce = $failure !== 'nonce';
    $_SERVER['REQUEST_METHOD'] = $failure === 'method' ? 'GET' : 'POST';
    $queries_before = count($wpdb->queries);
    try {
        mmgr_handle_send_newsletter();
        throw new RuntimeException('Unexpected success');
    } catch (RuntimeException $error) {
        $expected = array('permission' => 'You do not have permission to send newsletters.', 'nonce' => 'Invalid nonce', 'method' => 'Invalid request method.');
        expect($error->getMessage() === $expected[$failure], 'Blocked invalid ' . $failure);
    }
    expect(count($wpdb->queries) === $queries_before && count($mail) === 4, 'Security rejection occurs before querying or sending');
}
$can_manage = true;
$valid_nonce = true;

set_transient('mmgr_newsletter_result_7', array('error' => 'Please fix <subject>', 'subject' => '" onfocus="bad()', 'body' => '<p>Draft</p>'), 600);
ob_start();
mmgr_newsletter_composer();
$output = ob_get_clean();
expect(str_contains($output, 'Please fix &lt;subject&gt;'), 'Validation feedback escaped');
expect(str_contains($output, '&quot; onfocus=&quot;bad()'), 'Restored subject escaped');
expect($editor['body'] === '<p>Draft</p>', 'Draft preserved on errors');
expect(get_transient('mmgr_newsletter_result_7') === false, 'Feedback consumed once after redirect');

set_transient('mmgr_newsletter_result_7', array('sent' => 1, 'failed' => 1, 'skipped' => 2), 600);
ob_start();
mmgr_newsletter_composer();
$output = ob_get_clean();
expect(str_contains($output, 'notice-warning') && str_contains($output, 'Failed: 1'), 'Partial failure visibly reported');
expect($editor['body'] === '', 'Successful send clears composer');

$wpdb->subscribers = array(
    array('id' => 1, 'email' => 'paid-in@example.com', 'newsletter' => 1),
    array('id' => 2, 'email' => 'paid-out@example.com', 'newsletter' => 0),
    array('id' => 3, 'email' => 'unpaid-in@example.com', 'newsletter' => 1),
    array('id' => 4, 'email' => 'unpaid-out@example.com', 'newsletter' => 0),
);
$wpdb->tickets = array(1 => array(12), 2 => array(12), 3 => array(13));
foreach (array(false, true) as $override) {
    foreach (array('all', 'with_ticket', 'without_ticket') as $audience) {
        $mail = array();
        $result = submit_newsletter('Event', 'Message', array(
            'mmgr_newsletter_event' => '12', 'mmgr_newsletter_audience' => $audience,
            'mmgr_newsletter_override' => $override ? '1' : '0',
        ));
        $expected = $audience === 'all' ? array('paid-in@example.com', 'paid-out@example.com', 'unpaid-in@example.com', 'unpaid-out@example.com')
            : ($audience === 'with_ticket' ? array('paid-in@example.com', 'paid-out@example.com') : array('unpaid-in@example.com', 'unpaid-out@example.com'));
        if (!$override) $expected = array_values(array_filter($expected, function($email) { return !str_contains($email, '-out@'); }));
        expect(array_column($mail, 'to') === $expected && $result['sent'] === count($expected), 'Correct ' . $audience . ' audience with override ' . ($override ? 'on' : 'off'));
    }
}
foreach (array(
    array('mmgr_newsletter_audience' => 'with_ticket'),
    array('mmgr_newsletter_audience' => 'without_ticket'),
    array('mmgr_newsletter_audience' => 'invalid'),
    array('mmgr_newsletter_audience' => array('all')),
    array('mmgr_newsletter_event' => array(12)),
    array('mmgr_newsletter_event' => '-12'),
    array('mmgr_newsletter_event' => '12 OR 1=1'),
    array('mmgr_newsletter_event' => '14'),
    array('mmgr_newsletter_event' => '15'),
    array('mmgr_newsletter_event' => '999'),
) as $options) {
    $mail = array();
    $result = submit_newsletter('Subject', 'Message', $options);
    expect(isset($result['error']) && !$mail, 'Invalid audience/event fails closed without email');
}
$mail = array();
$wpdb->last_error = 'Ticket query unavailable';
$result = mmgr_send_newsletter('Event', 'Message', 12, 'without_ticket', true);
expect(isset($result['error']) && !$mail, 'Event lookup failure cannot broaden audience');
$wpdb->last_error = '';
$result = submit_newsletter('Subject', '', array('mmgr_newsletter_event' => '12', 'mmgr_newsletter_audience' => 'with_ticket', 'mmgr_newsletter_override' => '1'));
ob_start();
mmgr_newsletter_composer();
$output = ob_get_clean();
expect(str_contains($output, 'value="12" selected="selected"') && str_contains($output, 'value="with_ticket" selected="selected"') && str_contains($output, 'checked="checked"'), 'Audience and override restored after validation errors');
$mail = array();
submit_newsletter('Regular newsletter', 'News');
expect(array_column($mail, 'to') === array('paid-in@example.com', 'unpaid-in@example.com'), 'Override never persists to later ordinary newsletters');
expect($wpdb->subscribers[1]['newsletter'] === 0, 'Override never changes stored subscription preferences');

echo "\nResults: $passed passed\n";
