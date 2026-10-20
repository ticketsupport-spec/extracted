<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', 'mmgr_newsletter_editor_assets');
add_action('admin_post_mmgr_send_newsletter', 'mmgr_handle_send_newsletter');

function mmgr_newsletter_editor_assets() {
    if (isset($_GET['page']) && $_GET['page'] === 'membership_newsletter' && current_user_can('manage_options')) {
        wp_enqueue_media();
    }
}

function mmgr_newsletter_composer() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $key = 'mmgr_newsletter_result_' . get_current_user_id();
    $result = get_transient($key);
    delete_transient($key);
    $subject = '';
    $body = '';
    $event_id = 0;
    $audience = 'all';
    $override = false;

    if (is_array($result)) {
        if (!empty($result['error'])) {
            $subject = $result['subject'] ?? '';
            $body = $result['body'] ?? '';
            $event_id = $result['event_id'] ?? 0;
            $audience = in_array($result['audience'] ?? '', array('all', 'with_ticket', 'without_ticket'), true) ? $result['audience'] : 'all';
            $override = !empty($result['override']);
            echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $class = !empty($result['failed']) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html(sprintf(
                'Newsletter accepted for sending: %d. Failed: %d. Skipped invalid or duplicate addresses: %d. Acceptance does not guarantee delivery.',
                $result['sent'], $result['failed'], $result['skipped']
            )) . '</p></div>';
        }
    }
    global $wpdb;
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT id, event_name, event_date FROM {$wpdb->prefix}membership_events
         WHERE active = 1 AND event_date >= %s ORDER BY event_date ASC, start_time ASC, id ASC",
        current_time('Y-m-d')
    ), ARRAY_A);
    if ($wpdb->last_error) {
        echo '<div class="notice notice-error"><p>Unable to load upcoming events. Reload before sending an event message.</p></div>';
    }
    ?>
    <h2>Send a newsletter</h2>
    <p>By default, email only newsletter subscribers. Optionally target members with or without a paid ticket for an upcoming event. Each unique valid email address receives a separate message; other recipients are never shown.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm(this.elements.mmgr_newsletter_override.checked ? 'Send to the selected audience INCLUDING members who have not subscribed to the newsletter?' : 'Send to newsletter subscribers in the selected audience?');">
        <input type="hidden" name="action" value="mmgr_send_newsletter">
        <?php wp_nonce_field('mmgr_send_newsletter', 'mmgr_newsletter_nonce'); ?>
        <p>
            <label for="mmgr_newsletter_event"><strong>Upcoming event</strong></label><br>
            <select id="mmgr_newsletter_event" name="mmgr_newsletter_event">
                <option value="0">No event (newsletter)</option>
                <?php foreach ((array) $events as $event): ?>
                    <option value="<?php echo esc_attr($event['id']); ?>" <?php selected($event_id, $event['id']); ?>><?php echo esc_html($event['event_name'] . ' — ' . $event['event_date']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="mmgr_newsletter_audience"><strong>Audience</strong></label><br>
            <select id="mmgr_newsletter_audience" name="mmgr_newsletter_audience">
                <option value="all" <?php selected($audience, 'all'); ?>>Everyone</option>
                <option value="with_ticket" <?php selected($audience, 'with_ticket'); ?>>Members with a paid event ticket</option>
                <option value="without_ticket" <?php selected($audience, 'without_ticket'); ?>>Members without a paid event ticket</option>
            </select>
            <span class="description">Ticket filters require an upcoming event. Everyone includes members regardless of ticket status.</span>
        </p>
        <p>
            <label><input type="checkbox" name="mmgr_newsletter_override" value="1" <?php checked($override); ?>> Override newsletter preference for this send: include unsubscribed members in the selected audience.</label><br>
            <span class="description">Leave unchecked for actual newsletters. This does not change anyone's subscription preference.</span>
        </p>
        <p>
            <label for="mmgr_newsletter_subject"><strong>Subject</strong></label><br>
            <input type="text" id="mmgr_newsletter_subject" name="mmgr_newsletter_subject" value="<?php echo esc_attr($subject); ?>" class="large-text" required>
        </p>
        <p><strong>Message</strong></p>
        <p>Use the Visual editor to format your email and Add Media to upload or insert an image. Images load from your website; some email clients may block them.</p>
        <?php
        wp_editor($body, 'mmgr_newsletter_body', array(
            'textarea_name' => 'mmgr_newsletter_body',
            'textarea_rows' => 15,
            'media_buttons' => true,
            'tinymce' => true,
            'quicktags' => true,
        ));
        submit_button('Send to selected audience');
        ?>
    </form>
    <?php
}

function mmgr_handle_send_newsletter() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to send newsletters.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Invalid request method.');
    }
    check_admin_referer('mmgr_send_newsletter', 'mmgr_newsletter_nonce');

    $subject = isset($_POST['mmgr_newsletter_subject']) && is_string($_POST['mmgr_newsletter_subject'])
        ? sanitize_text_field(wp_unslash($_POST['mmgr_newsletter_subject'])) : '';
    $body = isset($_POST['mmgr_newsletter_body']) && is_string($_POST['mmgr_newsletter_body'])
        ? wp_kses_post(wp_unslash($_POST['mmgr_newsletter_body'])) : '';
    $event_id = filter_var($_POST['mmgr_newsletter_event'] ?? 0, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
    $audience = $_POST['mmgr_newsletter_audience'] ?? 'all';
    $override = isset($_POST['mmgr_newsletter_override']) && $_POST['mmgr_newsletter_override'] === '1';
    $result = array('subject' => $subject, 'body' => $body, 'event_id' => $event_id, 'audience' => $audience, 'override' => $override);

    if ($subject === '' || (trim(wp_strip_all_tags($body)) === '' && !preg_match('/<img\b[^>]*\bsrc\s*=/i', $body))) {
        $result['error'] = 'Enter a subject and a message before sending.';
    } else {
        $result = array_merge($result, mmgr_send_newsletter($subject, $body, $event_id, $audience, $override));
    }

    set_transient('mmgr_newsletter_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=membership_newsletter'));
    exit;
}

function mmgr_send_newsletter($subject, $body, $event_id = 0, $audience = 'all', $override = false) {
    global $wpdb;
    if (!is_int($event_id) || $event_id < 0 || !in_array($audience, array('all', 'with_ticket', 'without_ticket'), true)
        || ($audience !== 'all' && !$event_id)) {
        return array('error' => 'Select a valid audience and an upcoming event for ticket filters.');
    }
    if ($event_id) {
        $event = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}membership_events WHERE id = %d AND active = 1 AND event_date >= %s",
            $event_id, current_time('Y-m-d')
        ));
        if ($wpdb->last_error || !$event) {
            return array('error' => 'The selected event is unavailable or no longer upcoming. No emails were sent.');
        }
    }
    $tbl = $wpdb->prefix . 'memberships';
    $where = $override === true ? '1 = 1' : 'newsletter = 1';
    if ($audience !== 'all') {
        $exists = $audience === 'with_ticket' ? 'EXISTS' : 'NOT EXISTS';
        $where .= $wpdb->prepare(
            " AND $exists (SELECT 1 FROM {$wpdb->prefix}membership_member_event_tickets t
             WHERE t.member_id = `$tbl`.id AND t.event_id = %d)",
            $event_id
        );
    }
    $subscribers = $wpdb->get_results("SELECT email FROM `$tbl` WHERE $where", ARRAY_A);
    if ($wpdb->last_error) {
        return array('error' => 'Unable to load email recipients. No emails were sent.');
    }
    if (empty($subscribers)) {
        return array('error' => 'There are no matching recipients to email.');
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');
    $from_email = sanitize_email(get_option('mmgr_email_from_email', get_option('admin_email')));
    $from_name = sanitize_text_field(get_option('mmgr_email_from_name', get_bloginfo('name')));
    if (is_email($from_email)) {
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    }
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
        . wpautop(wp_kses_post($body)) . '</body></html>';
    $result = array('sent' => 0, 'failed' => 0, 'skipped' => 0);
    $seen = array();
    foreach ($subscribers as $subscriber) {
        $email = trim($subscriber['email'] ?? '');
        $key = strtolower($email);
        if (!is_email($email) || isset($seen[$key])) {
            $result['skipped']++;
            continue;
        }
        $seen[$key] = true;
        if (wp_mail($email, $subject, $html, $headers)) {
            $result['sent']++;
        } else {
            $result['failed']++;
        }
    }
    return $result;
}
