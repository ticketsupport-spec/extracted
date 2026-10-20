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

    if (is_array($result)) {
        if (!empty($result['error'])) {
            $subject = $result['subject'] ?? '';
            $body = $result['body'] ?? '';
            echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $class = !empty($result['failed']) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html(sprintf(
                'Newsletter accepted for sending: %d. Failed: %d. Skipped invalid or duplicate addresses: %d. Acceptance does not guarantee delivery.',
                $result['sent'], $result['failed'], $result['skipped']
            )) . '</p></div>';
        }
    }
    ?>
    <h2>Send a newsletter</h2>
    <p>Email all opted-in newsletter subscribers. Each unique valid email address receives a separate message; other recipients are never shown.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Send this newsletter to all subscribers?');">
        <input type="hidden" name="action" value="mmgr_send_newsletter">
        <?php wp_nonce_field('mmgr_send_newsletter', 'mmgr_newsletter_nonce'); ?>
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
        submit_button('Send to all newsletter subscribers');
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
    $result = array('subject' => $subject, 'body' => $body);

    if ($subject === '' || (trim(wp_strip_all_tags($body)) === '' && !preg_match('/<img\b[^>]*\bsrc\s*=/i', $body))) {
        $result['error'] = 'Enter a subject and a message before sending.';
    } else {
        $result = array_merge($result, mmgr_send_newsletter($subject, $body));
    }

    set_transient('mmgr_newsletter_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=membership_newsletter'));
    exit;
}

function mmgr_send_newsletter($subject, $body) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    $subscribers = $wpdb->get_results("SELECT email FROM `$tbl` WHERE newsletter = 1", ARRAY_A);
    if ($wpdb->last_error) {
        return array('error' => 'Unable to load newsletter subscribers. No emails were sent.');
    }
    if (empty($subscribers)) {
        return array('error' => 'There are no newsletter subscribers to email.');
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
