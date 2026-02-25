<?php
if (!defined('ABSPATH')) exit;

/**
 * Send welcome email to new member with QR code
 */
function mmgr_send_welcome_email($member_id) {
    global $wpdb;
    $tbl = $wpdb->prefix . "memberships";
    
    // Check if welcome emails are enabled
    if (!get_option('mmgr_enable_welcome_email', 1)) {
        return false;
    }
    
    // Get member data
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d", $member_id), ARRAY_A);
    if (!$member) {
        return false;
    }
    
    // Email settings
    $from_name = get_option('mmgr_email_from_name', get_bloginfo('name'));
    $from_email = get_option('mmgr_email_from_email', get_option('admin_email'));
    $subject = get_option('mmgr_email_subject', 'Welcome to {site_name} - Your Membership is Active! 🎾');
    $template = get_option('mmgr_email_template', mmgr_get_default_email_template());
    $footer = get_option('mmgr_email_footer', mmgr_get_default_email_footer());
    $attach_qr = get_option('mmgr_attach_qr_code', 1);
    $enable_portal = get_option('mmgr_enable_member_portal', 1);
    
    // Generate password setup token if portal is enabled
    $portal_link = '';
    if ($enable_portal) {
        $token = mmgr_generate_portal_token($member_id);
        $portal_link = home_url('/member-setup/?token=' . $token);
    }
    
    // Prepare placeholders
    $placeholders = array(
        '{member_name}' => $member['name'],
        '{first_name}' => $member['first_name'],
        '{last_name}' => $member['last_name'],
        '{member_code}' => $member['member_code'],
        '{membership_type}' => $member['level'],
        '{expire_date}' => date('F j, Y', strtotime($member['expire_date'])),
        '{start_date}' => date('F j, Y', strtotime($member['start_date'])),
        '{site_name}' => get_bloginfo('name'),
        '{site_url}' => home_url(),
        '{code_of_conduct}' => home_url('/code-of-conduct'),
        '{portal_link}' => $portal_link ? '<a href="' . esc_url($portal_link) . '">Click here to set up your account password</a>' : '',
    );
    
    // Replace placeholders in subject, template, and footer
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    $footer_content = str_replace(array_keys($placeholders), array_values($placeholders), $footer);
    
    // Combine body and footer
    $full_body = $body . "\n\n" . $footer_content;
    
// Convert to HTML email - allow links
$html_body = wp_kses($full_body, array(
    'a' => array('href' => array(), 'target' => array(), 'style' => array()),
    'br' => array(),
    'strong' => array(),
    'b' => array(),
    'em' => array(),
    'i' => array(),
));
$html_body = nl2br($html_body);
$html_body = '
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;">
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:20px auto;padding:30px;background-color:#ffffff;color:#333333;line-height:1.6;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        ' . $html_body . '
    </div>
</body>
</html>';

    // Set headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    // Prepare attachments
    $attachments = array();
    if ($attach_qr) {
        $qr_file = mmgr_generate_qr_file($member['member_code']);
        if ($qr_file) {
            $attachments[] = $qr_file;
        }
    }
    
    // Send email
    $sent = wp_mail($member['email'], $subject, $html_body, $headers, $attachments);
    
    // Clean up QR file
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    // Log email
    mmgr_log_email($member_id, $member['email'], $subject, $sent);
    
    return $sent;
}

/**
 * Generate QR code as temporary file for email attachment
 */
function mmgr_generate_qr_file($code) {
    $upload_dir = wp_upload_dir();
    $qr_dir = $upload_dir['basedir'] . '/membership-qr-codes/';
    
    if (!file_exists($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    $qr_file = $qr_dir . 'qr-' . $code . '.png';
    
    // Check if QR library exists
    $qr_lib_path = MMGR_PLUGIN_DIR . 'vendor/phpqrcode/qrlib.php';
    
    if (file_exists($qr_lib_path)) {
        // Use local library if available
        require_once $qr_lib_path;
        QRcode::png($code, $qr_file, QR_ECLEVEL_L, 10, 2);
    } else {
        // Fallback: Download QR from API and save locally
        $api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($code);
        $qr_data = file_get_contents($api_url);
        
        if ($qr_data !== false) {
            file_put_contents($qr_file, $qr_data);
        } else {
            return false; // Failed to generate
        }
    }
    
    // Return the FILE PATH (not URL) for email attachment
    return file_exists($qr_file) ? $qr_file : false;
}





// TEMPORARY DEBUG FUNCTION
function mmgr_debug_token($token) {
    $decoded = base64_decode($token);
    $parts = explode('|', $decoded);
    
    if (count($parts) !== 2) {
        error_log('MMGR Token Debug: Invalid format - parts count: ' . count($parts));
        return;
    }
    
    list($member_id, $token_value) = $parts;
    $stored_token = get_transient('mmgr_portal_token_' . $member_id);
    
    error_log('MMGR Token Debug: Member ID: ' . $member_id);
    error_log('MMGR Token Debug: Token from URL: ' . substr($token_value, 0, 10) . '...');
    error_log('MMGR Token Debug: Stored token: ' . ($stored_token ? substr($stored_token, 0, 10) . '...' : 'NOT FOUND'));
    error_log('MMGR Token Debug: Match: ' . ($stored_token === $token_value ? 'YES' : 'NO'));
}

/**
 * Log sent emails
 */
function mmgr_log_email($member_id, $recipient, $subject, $success) {
    global $wpdb;
    $log_tbl = $wpdb->prefix . "membership_email_log";
    
    // Create log table if it doesn't exist
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$log_tbl` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        sent_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL,
        INDEX idx_member_id (member_id),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $wpdb->insert($log_tbl, array(
        'member_id' => $member_id,
        'recipient' => $recipient,
        'subject' => $subject,
        'sent_at' => current_time('mysql'),
        'status' => $success ? 'sent' : 'failed'
    ));
}

/**
 * Default email template
 */
function mmgr_get_default_email_template() {
    return "Hi {first_name},

Welcome to {site_name}! Your membership is now active.

Your Membership Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Member Code: {member_code}
Type: {membership_type}
Expires: {expire_date}

🎫 YOUR QR CODE
Your QR code is attached to this email. Show it on your phone when you check in, or print it for a physical card.

🔐 SET UP YOUR ACCOUNT
{portal_link}

From your portal you can:
✓ View your QR code anytime
✓ See your visit history
✓ Update your contact information

Need help? Reply to this email!

See you at the farm!";
}

/**
 * Default email footer
 */
function mmgr_get_default_email_footer() {
    return "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{site_name} Team
🌐 {site_url}";
}

/**
 * Send test email
 */
function mmgr_send_test_email($recipient_email) {
    $from_name = get_option('mmgr_email_from_name', get_bloginfo('name'));
    $from_email = get_option('mmgr_email_from_email', get_option('admin_email'));
    $subject = get_option('mmgr_email_subject', 'Welcome to {site_name} - Your Membership is Active! 🎾');
    $template = get_option('mmgr_email_template', mmgr_get_default_email_template());
    $footer = get_option('mmgr_email_footer', mmgr_get_default_email_footer());
    
    // Test placeholders
    $placeholders = array(
        '{member_name}' => 'John Doe',
        '{first_name}' => 'John',
        '{last_name}' => 'Doe',
        '{member_code}' => 'MB123456TEST',
        '{membership_type}' => 'Single',
        '{expire_date}' => date('F j, Y', strtotime('+1 year')),
        '{start_date}' => date('F j, Y'),
        '{site_name}' => get_bloginfo('name'),
        '{site_url}' => home_url(),
        '{code_of_conduct}' => home_url('/code-of-conduct'),
        '{portal_link}' => '<a href="' . home_url('/member-setup/?token=TEST') . '">Click here to set up your account password (TEST LINK)</a>',
    );
    
    // Replace placeholders
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $template);
    $footer_content = str_replace(array_keys($placeholders), array_values($placeholders), $footer);
    
    $full_body = $body . "\n\n" . $footer_content;
    
    // Convert to HTML email - allow links
    $html_body = wp_kses($full_body, array(
        'a' => array('href' => array(), 'target' => array()),
        'br' => array(),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
    ));
    $html_body = nl2br($html_body);
    $html_body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">' . $html_body . '</div>';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    // Add test QR code if enabled
    $attachments = array();
    if (get_option('mmgr_attach_qr_code', 1)) {
        $qr_file = mmgr_generate_qr_file('MB123456TEST');
        if ($qr_file) {
            $attachments[] = $qr_file;
        }
    }
    
    $sent = wp_mail($recipient_email, '[TEST] ' . $subject, $html_body, $headers, $attachments);
    
    // Clean up
    if (!empty($attachments)) {
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    return $sent;
}

/**
 * Get default welcome private message
 */
if (!function_exists('mmgr_get_default_welcome_pm')) {
    function mmgr_get_default_welcome_pm() {
        return "Hi {first_name}! 👋

Welcome to {site_name}! We're thrilled to have you as part of our community.

Here are a few things to get you started:

📱 **Your Digital Membership Card**
You can access your QR code anytime from your dashboard. Show it when checking in!

🎾 **Member Portal**
Explore your dashboard to:
- View your visit history
- Update your profile
- Check upcoming events
- Connect with other members

💬 **Stay Connected**
Feel free to reach out if you have any questions or need assistance. We're here to help!

If you need anything at all, just reply to this message and we'll get back to you as soon as possible.

See you on the courts! 🎾

— The {site_name} Team";
    }
}

// AJAX: Resend password setup link
add_action('wp_ajax_mmgr_resend_setup_link', function() {
    check_ajax_referer('mmgr_resend_setup', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    $member_id = intval($_POST['member_id']);
    
    global $wpdb;
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}memberships WHERE id = %d",
        $member_id
    ), ARRAY_A);
    
    if (!$member) {
        wp_send_json_error(array('message' => 'Member not found'));
    }
    
    // Generate new token
    $token = bin2hex(random_bytes(32));
    set_transient('mmgr_portal_token_' . $member_id, $token, 7 * DAY_IN_SECONDS);
    
    // Send email with setup link
    $setup_url = home_url('/member-setup/?token=' . $token);
    
    $subject = 'Set Up Your Member Portal Password - ' . get_bloginfo('name');
    $message = "Hi " . $member['first_name'] . ",\n\n";
    $message .= "Click the link below to set up your password for the member portal:\n\n";
    $message .= $setup_url . "\n\n";
    $message .= "This link will expire in 7 days.\n\n";
    $message .= "If you didn't request this, please ignore this email.\n\n";
    $message .= "— " . get_bloginfo('name');
    
    $sent = wp_mail($member['email'], $subject, $message);
    
    if ($sent) {
        wp_send_json_success(array('message' => 'Password setup email sent to ' . $member['email']));
    } else {
        wp_send_json_error(array('message' => 'Failed to send email. Please check your email settings.'));
    }
});