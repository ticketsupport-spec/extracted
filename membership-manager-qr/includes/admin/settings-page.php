<?php
if (!defined('ABSPATH')) exit;

/**
 * Create plugin pages with shortcodes
 */
if (!function_exists('mmgr_create_plugin_pages')) {
    function mmgr_create_plugin_pages() {
        $pages = array(
            array(
                'title' => 'Member Registration',
                'slug' => 'member-registration',
                'content' => '[membership_registration]',
                'option' => 'mmgr_page_registration'
            ),
            array(
                'title' => 'Member Check-In',
                'slug' => 'member-checkin',
                'content' => '[membership_checkin]',
                'option' => 'mmgr_page_checkin'
            ),
            array(
                'title' => 'Code of Conduct',
                'slug' => 'code-of-conduct',
                'content' => '[membership_code_of_conduct]',
                'option' => 'mmgr_page_coc'
            ),
            array(
                'title' => 'Member Setup',
                'slug' => 'member-setup',
                'content' => '[mmgr_password_setup]',
                'option' => 'mmgr_page_setup'
            ),
            array(
                'title' => 'Member Login',
                'slug' => 'member-login',
                'content' => '[mmgr_member_login]',
                'option' => 'mmgr_page_login'
            ),
            array(
                'title' => 'Member Dashboard',
                'slug' => 'member-dashboard',
                'content' => '[mmgr_member_dashboard]',
                'option' => 'mmgr_page_dashboard'
            ),
            array(
                'title' => 'Member Activity',
                'slug' => 'member-activity',
                'content' => '[mmgr_member_activity]',
                'option' => 'mmgr_page_activity'
            ),
            array(
                'title' => 'Member Profile',
                'slug' => 'member-profile',
                'content' => '[mmgr_member_profile]',
                'option' => 'mmgr_page_profile'
            ),
            array(
                'title' => 'Member Community',
                'slug' => 'member-community',
                'content' => '[mmgr_member_community]',
                'option' => 'mmgr_page_community'
            ),
            array(
                'title' => 'Member Messages',
                'slug' => 'member-messages',
                'content' => '[mmgr_member_messages]',
                'option' => 'mmgr_page_messages'
            )
        );
        
        $created = array();
        $skipped = array();
        $errors = array();
        
        foreach ($pages as $page_data) {
            $existing_id = get_option($page_data['option']);
            
            if ($existing_id && get_post($existing_id) && get_post_status($existing_id) !== 'trash') {
                $skipped[] = $page_data['title'];
                continue;
            }
            
            $page_id = wp_insert_post(array(
                'post_title' => $page_data['title'],
                'post_name' => $page_data['slug'],
                'post_content' => $page_data['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                update_option($page_data['option'], $page_id);
                $created[] = $page_data['title'];
            } else {
                $error_msg = is_wp_error($page_id) ? $page_id->get_error_message() : 'Unknown error';
                $errors[] = $page_data['title'] . ': ' . $error_msg;
            }
        }
        
        return array(
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }
}

function mmgr_settings_admin() {
    // Enqueue media uploader scripts before any HTML output
    wp_enqueue_media();

    // Handle "Log Everyone Out" – clear all member session tokens
    if (isset($_POST['mmgr_logout_everyone']) && isset($_POST['mmgr_logout_everyone_nonce']) && wp_verify_nonce($_POST['mmgr_logout_everyone_nonce'], 'mmgr_logout_everyone') && current_user_can('manage_options')) {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'memberships');
        $result = $wpdb->query("UPDATE $table SET session_token = NULL, session_expires = NULL WHERE session_token IS NOT NULL");
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>✓ All members have been logged out and their session cookies invalidated.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>✕ Could not invalidate sessions. Please try again.</p></div>';
        }
    }

    // Handle PWA icon save (separate form, only saves the icon ID)
    if (isset($_POST['mmgr_save_pwa_icon']) && isset($_POST['mmgr_pwa_icon_nonce']) && wp_verify_nonce($_POST['mmgr_pwa_icon_nonce'], 'mmgr_pwa_icon') && current_user_can('manage_options')) {
        update_option('mmgr_pwa_icon_id', isset($_POST['mmgr_pwa_icon_id']) ? absint($_POST['mmgr_pwa_icon_id']) : 0);
        echo '<div class="notice notice-success"><p>✓ PWA icon saved successfully!</p></div>';
    }

    // Handle manual page creation
    if (isset($_POST['mmgr_create_pages']) && isset($_POST['mmgr_create_pages_nonce']) && wp_verify_nonce($_POST['mmgr_create_pages_nonce'], 'mmgr_create_pages')) {
        $result = mmgr_create_plugin_pages();
        
        if (!empty($result['created'])) {
            echo '<div class="notice notice-success"><p><strong>✓ Created:</strong> ' . implode(', ', $result['created']) . '</p></div>';
        }
        
        if (!empty($result['skipped'])) {
            echo '<div class="notice notice-info"><p><strong>⏭️ Already exist:</strong> ' . implode(', ', $result['skipped']) . '</p></div>';
        }
        
        if (!empty($result['errors'])) {
            echo '<div class="notice notice-error"><p><strong>❌ Errors:</strong><br>' . implode('<br>', $result['errors']) . '</p></div>';
        }
        
        if (empty($result['errors'])) {
            echo '<div class="notice notice-success"><p><strong>✓ Done!</strong> View pages in <a href="' . admin_url('edit.php?post_type=page') . '">Pages → All Pages</a></p></div>';
        }
    }
    
    // Handle test email
    if (isset($_POST['send_test_email']) && isset($_POST['mmgr_test_email_nonce']) && wp_verify_nonce($_POST['mmgr_test_email_nonce'], 'mmgr_test_email')) {
        $test_email = sanitize_email($_POST['test_email_address']);
        if ($test_email && is_email($test_email)) {
            $sent = mmgr_send_test_email($test_email);
            if ($sent) {
                echo '<div class="notice notice-success"><p>✓ Test email sent to ' . esc_html($test_email) . '!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>✕ Failed to send test email.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>✕ Invalid email address.</p></div>';
        }
    }
    
    // Handle settings save
    if (isset($_POST['mmgr_save_settings']) && isset($_POST['mmgr_settings_nonce']) && wp_verify_nonce($_POST['mmgr_settings_nonce'], 'mmgr_settings')) {
        update_option('mmgr_code_of_conduct', wp_kses_post($_POST['mmgr_code_of_conduct']));
        update_option('mmgr_registration_title', sanitize_text_field($_POST['mmgr_registration_title']));
        update_option('mmgr_registration_success_url', sanitize_text_field($_POST['mmgr_registration_success_url']));
        update_option('mmgr_checkin_title', sanitize_text_field($_POST['mmgr_checkin_title']));
        update_option('mmgr_checkin_default_mode', sanitize_text_field($_POST['mmgr_checkin_default_mode']));
        update_option('mmgr_enable_welcome_email', isset($_POST['mmgr_enable_welcome_email']) ? 1 : 0);
        update_option('mmgr_attach_qr_code', isset($_POST['mmgr_attach_qr_code']) ? 1 : 0);
        update_option('mmgr_enable_member_portal', isset($_POST['mmgr_enable_member_portal']) ? 1 : 0);
        update_option('mmgr_email_from_name', sanitize_text_field($_POST['mmgr_email_from_name']));
        update_option('mmgr_email_from_email', sanitize_email($_POST['mmgr_email_from_email']));
        update_option('mmgr_email_subject', sanitize_text_field($_POST['mmgr_email_subject']));
        update_option('mmgr_email_template', wp_kses_post($_POST['mmgr_email_template']));
        update_option('mmgr_email_footer', wp_kses_post($_POST['mmgr_email_footer']));
        update_option('mmgr_welcome_pm_enabled', isset($_POST['mmgr_welcome_pm_enabled']) ? 1 : 0);
        update_option('mmgr_welcome_pm_message', wp_kses_post($_POST['mmgr_welcome_pm_message']));
        update_option('mmgr_registration_logo_id', isset($_POST['mmgr_registration_logo_id']) ? absint($_POST['mmgr_registration_logo_id']) : 0);
        if (isset($_POST['mmgr_registration_blurb'])) {
            update_option('mmgr_registration_blurb', wp_kses_post($_POST['mmgr_registration_blurb']));
        }
        
        echo '<div class="notice notice-success"><p>✓ Settings saved successfully!</p></div>';
    }
    
    $pages_to_check = array(
        'mmgr_page_registration' => 'Member Registration',
        'mmgr_page_checkin' => 'Member Check-In',
        'mmgr_page_coc' => 'Code of Conduct',
        'mmgr_page_setup' => 'Member Setup',
        'mmgr_page_login' => 'Member Login',
        'mmgr_page_dashboard' => 'Member Dashboard',
        'mmgr_page_activity' => 'Member Activity',
        'mmgr_page_profile' => 'Member Profile',
        'mmgr_page_community' => 'Member Community',
        'mmgr_page_messages' => 'Member Messages'
    );

    $missing_pages = array();
    foreach ($pages_to_check as $option => $name) {
        $page_id = get_option($option);
        if (!$page_id || !get_post($page_id)) {
            $missing_pages[] = $name;
        }
    }

    $coc = get_option('mmgr_code_of_conduct', 'Add your code of conduct here.');
    $reg_title = get_option('mmgr_registration_title', 'Membership Signup');
    $reg_logo_id = intval(get_option('mmgr_registration_logo_id', 0));
    $reg_logo_url = $reg_logo_id ? wp_get_attachment_url($reg_logo_id) : '';
    $reg_blurb = get_option('mmgr_registration_blurb', '');
    $checkin_title = get_option('mmgr_checkin_title', 'QR Code Scanner');
    $checkin_default_mode = get_option('mmgr_checkin_default_mode', 'hw');
    $enable_email = get_option('mmgr_enable_welcome_email', 1);
    $attach_qr = get_option('mmgr_attach_qr_code', 1);
    $enable_portal = get_option('mmgr_enable_member_portal', 1);
    $from_name = get_option('mmgr_email_from_name', get_bloginfo('name'));
    $from_email = get_option('mmgr_email_from_email', get_option('admin_email'));
    $email_subject = get_option('mmgr_email_subject', 'Welcome to {site_name} - Your Membership is Active! 🎾');
    $email_template = get_option('mmgr_email_template', mmgr_get_default_email_template());
    $email_footer = get_option('mmgr_email_footer', mmgr_get_default_email_footer());
    ?>
    
    <div class="wrap">
        <h1>Membership Settings</h1>
        
        <?php if (!empty($missing_pages)): ?>
            <div class="notice notice-warning" style="padding:20px;border-left:4px solid #ff9800;margin-bottom:20px;">
                <h2 style="margin-top:0;">⚠️ Missing Portal Pages</h2>
                <p><strong>The following pages need to be created:</strong></p>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                    <?php foreach ($missing_pages as $page): ?>
                        <li><strong><?php echo esc_html($page); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" style="margin-top:15px;">
                    <?php wp_nonce_field('mmgr_create_pages', 'mmgr_create_pages_nonce'); ?>
                    <button type="submit" name="mmgr_create_pages" class="button button-primary button-large">🚀 Create Missing Pages Now</button>
                </form>
            </div>
        <?php else: ?>
            <div class="notice notice-success" style="padding:15px;border-left:4px solid #159742;margin-bottom:20px;">
                <p style="margin:0;"><strong>✓ All portal pages are created!</strong></p>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <?php wp_nonce_field('mmgr_settings', 'mmgr_settings_nonce'); ?>
            
            <h2>General Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="reg_title">Registration Form Title</label></th>
                    <td><input name="mmgr_registration_title" id="reg_title" class="regular-text" value="<?php echo esc_attr($reg_title); ?>"></td>
                </tr>
                <tr>
                    <th><label>Registration Page Logo</label></th>
                    <td>
                        <input type="hidden" name="mmgr_registration_logo_id" id="mmgr_registration_logo_id" value="<?php echo esc_attr($reg_logo_id); ?>">
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <div id="mmgr-reg-logo-preview" style="max-width:200px;max-height:100px;border:2px dashed #0073aa;border-radius:6px;display:flex;align-items:center;justify-content:center;background:#fff;overflow:hidden;flex-shrink:0;padding:4px;">
                                <?php if ($reg_logo_url): ?>
                                    <img src="<?php echo esc_url($reg_logo_url); ?>" style="max-width:100%;max-height:96px;object-fit:contain;" alt="Registration Logo">
                                <?php else: ?>
                                    <span style="font-size:28px;">🖼️</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <button type="button" id="mmgr-reg-logo-upload" class="button button-secondary">📤 Upload / Change Logo</button>
                                <button type="button" id="mmgr-reg-logo-remove" class="button" style="margin-left:8px;color:#d63638;<?php echo $reg_logo_id ? '' : 'display:none;'; ?>">✕ Remove</button>
                                <p class="description" style="margin-top:6px;">Logo displayed at the top of the membership registration page.</p>
                            </div>
                        </div>
                        <script>
                        (function() {
                            var regLogoIdField  = document.getElementById('mmgr_registration_logo_id');
                            var regPreviewBox   = document.getElementById('mmgr-reg-logo-preview');
                            var regUploadBtn    = document.getElementById('mmgr-reg-logo-upload');
                            var regRemoveBtn    = document.getElementById('mmgr-reg-logo-remove');
                            var regFrame;

                            regUploadBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (regFrame) { regFrame.open(); return; }
                                regFrame = wp.media({
                                    title:    'Select Registration Logo',
                                    button:   { text: 'Use this image' },
                                    multiple: false,
                                    library:  { type: 'image' }
                                });
                                regFrame.on('select', function() {
                                    var attachment = regFrame.state().get('selection').first().toJSON();
                                    regLogoIdField.value = attachment.id;
                                    var img = document.createElement('img');
                                    img.src = attachment.url;
                                    img.alt = 'Registration Logo';
                                    img.style.cssText = 'max-width:100%;max-height:96px;object-fit:contain;';
                                    regPreviewBox.innerHTML = '';
                                    regPreviewBox.appendChild(img);
                                    regRemoveBtn.style.display = '';
                                });
                                regFrame.open();
                            });

                            regRemoveBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                regLogoIdField.value = '0';
                                regPreviewBox.innerHTML = '<span style="font-size:28px;">🖼️</span>';
                                regRemoveBtn.style.display = 'none';
                            });
                        }());
                        </script>
                    </td>
                </tr>
                <tr>
                    <th><label for="reg_blurb">Club Blurb (above registration form)</label></th>
                    <td>
                        <textarea name="mmgr_registration_blurb" id="reg_blurb" class="large-text" rows="5"><?php echo esc_textarea($reg_blurb); ?></textarea>
                        <p class="description">Short description about the club shown above the registration form. HTML is allowed.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="reg_success_url">Registration Success Redirect URL</label></th>
                    <td>
                        <input name="mmgr_registration_success_url" id="reg_success_url" class="regular-text" value="<?php echo esc_attr(get_option('mmgr_registration_success_url', '/member-login/')); ?>" placeholder="/member-login/">
                        <p class="description">Where to redirect after successful registration. Leave empty for login page.</p>
                    </td>
                </tr>				
                <tr>
                    <th><label for="checkin_title">Check-In Form Title</label></th>
                    <td><input name="mmgr_checkin_title" id="checkin_title" class="regular-text" value="<?php echo esc_attr($checkin_title); ?>"></td>
                </tr>
                <tr>
                    <th><label for="checkin_default_mode">Check-In Default Mode</label></th>
                    <td>
                        <select name="mmgr_checkin_default_mode" id="checkin_default_mode">
                            <option value="hw" <?php selected($checkin_default_mode, 'hw'); ?>>📲 Hardware Scanner</option>
                            <option value="camera" <?php selected($checkin_default_mode, 'camera'); ?>>📱 Camera</option>
                            <option value="manual" <?php selected($checkin_default_mode, 'manual'); ?>>⌨️ Manual Entry</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="coc">Code of Conduct</label></th>
                    <td>
                        <textarea name="mmgr_code_of_conduct" id="coc" class="large-text" rows="10"><?php echo esc_textarea($coc); ?></textarea>
                        <p class="description">
                            Display this content on its own page using the shortcode <code>[membership_code_of_conduct]</code>.<br>
                            It is also shown inline on the registration form so members can read and agree to it before signing up.
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2>📧 Welcome Email Settings</h2>
            <table class="form-table">
                <tr>
                    <th>Enable Features</th>
                    <td>
                        <label><input type="checkbox" name="mmgr_enable_welcome_email" value="1" <?php checked($enable_email, 1); ?>> Send welcome email</label><br>
                        <label><input type="checkbox" name="mmgr_attach_qr_code" value="1" <?php checked($attach_qr, 1); ?>> Attach QR code</label><br>
                        <label><input type="checkbox" name="mmgr_enable_member_portal" value="1" <?php checked($enable_portal, 1); ?>> Enable member portal</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="from_name">From Name</label></th>
                    <td><input name="mmgr_email_from_name" id="from_name" class="regular-text" value="<?php echo esc_attr($from_name); ?>"></td>
                </tr>
                <tr>
                    <th><label for="from_email">From Email</label></th>
                    <td><input name="mmgr_email_from_email" id="from_email" type="email" class="regular-text" value="<?php echo esc_attr($from_email); ?>"></td>
                </tr>
                <tr>
                    <th><label for="email_subject">Subject</label></th>
                    <td>
                        <input name="mmgr_email_subject" id="email_subject" class="large-text" value="<?php echo esc_attr($email_subject); ?>">
                        <p class="description">Available placeholders: <code>{site_name}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{member_name}</code>, <code>{member_code}</code>, <code>{membership_type}</code>, <code>{expire_date}</code>, <code>{start_date}</code></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_template">Email Template</label></th>
                    <td>
                        <textarea name="mmgr_email_template" id="email_template" class="large-text" rows="15"><?php echo esc_textarea($email_template); ?></textarea>
                        <p class="description">
                            <strong>Available placeholders:</strong><br>
                            <code>{first_name}</code> – Member's first name<br>
                            <code>{last_name}</code> – Member's last name<br>
                            <code>{member_name}</code> – Member's full name<br>
                            <code>{member_code}</code> – Unique membership code (also used for the QR code)<br>
                            <code>{membership_type}</code> – Membership level (e.g. Single, Family)<br>
                            <code>{expire_date}</code> – Membership expiry date<br>
                            <code>{start_date}</code> – Membership start date<br>
                            <code>{site_name}</code> – Your site name<br>
                            <code>{site_url}</code> – Your site URL<br>
                            <code>{code_of_conduct}</code> – Link to the Code of Conduct page<br>
                            <code>{portal_link}</code> – Link for the member to set up their portal password<br>
                            <code>{qr_code}</code> – Inline QR code image (shows the member's QR code directly in the email body)
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_footer">Email Footer</label></th>
                    <td>
                        <textarea name="mmgr_email_footer" id="email_footer" class="large-text" rows="5"><?php echo esc_textarea($email_footer); ?></textarea>
                        <p class="description">Available placeholders: <code>{site_name}</code>, <code>{site_url}</code>, <code>{first_name}</code>, <code>{member_name}</code>, <code>{member_code}</code>, <code>{membership_type}</code>, <code>{expire_date}</code></p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><h2 style="margin:20px 0;">💬 Welcome Message (Private Message)</h2></th>
                </tr>
                <tr>
                    <th><label for="welcome_pm_enabled">Send Welcome PM</label></th>
                    <td><label><input type="checkbox" name="mmgr_welcome_pm_enabled" id="welcome_pm_enabled" value="1" <?php checked(get_option('mmgr_welcome_pm_enabled', 1), 1); ?>> Send automatic welcome message</label></td>
                </tr>
                <tr>
                    <th><label for="welcome_pm_message">Welcome Message</label></th>
                    <td>
                        <textarea name="mmgr_welcome_pm_message" id="welcome_pm_message" class="large-text" rows="10"><?php echo esc_textarea(get_option('mmgr_welcome_pm_message', mmgr_get_default_welcome_pm())); ?></textarea>
                        <p class="description">Placeholders: <code>{member_name}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{membership_type}</code>, <code>{site_name}</code></p>
                    </td>
                </tr>
            </table>
            
            <p><button type="submit" name="mmgr_save_settings" class="button button-primary button-large">💾 Save Settings</button></p>
        </form>
        
        <hr>
        <h2>📱 PWA App Icon</h2>
        <?php
        $pwa_icon_id  = intval(get_option('mmgr_pwa_icon_id', 0));
        $pwa_icon_url = $pwa_icon_id ? wp_get_attachment_url($pwa_icon_id) : '';
        ?>
        <div style="background:#f9f0ff;padding:20px;border-radius:6px;border-left:4px solid #9b51e0;margin-bottom:20px;">
            <p>Upload a custom icon that will appear on the <strong>PWA install banner</strong> and on members' home screens after installation. Recommended size: <strong>512×512 px PNG</strong>.</p>
            <form method="post">
                <?php wp_nonce_field('mmgr_pwa_icon', 'mmgr_pwa_icon_nonce'); ?>
                <input type="hidden" name="mmgr_pwa_icon_id" id="mmgr_pwa_icon_id" value="<?php echo esc_attr($pwa_icon_id); ?>">
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:10px;">
                    <div id="mmgr-pwa-icon-preview" style="width:80px;height:80px;border:2px dashed #9b51e0;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#fff;overflow:hidden;flex-shrink:0;">
                        <?php if ($pwa_icon_url): ?>
                            <img src="<?php echo esc_url($pwa_icon_url); ?>" style="width:100%;height:100%;object-fit:cover;" alt="PWA Icon">
                        <?php else: ?>
                            <span style="font-size:32px;">🖼️</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" id="mmgr-pwa-icon-upload" class="button button-secondary">📤 Upload / Change Icon</button>
                        <?php if ($pwa_icon_id): ?>
                            <button type="button" id="mmgr-pwa-icon-remove" class="button" style="margin-left:8px;color:#d63638;">✕ Remove</button>
                        <?php else: ?>
                            <button type="button" id="mmgr-pwa-icon-remove" class="button" style="margin-left:8px;color:#d63638;display:none;">✕ Remove</button>
                        <?php endif; ?>
                        <p class="description" style="margin-top:6px;">If no custom icon is set, a default purple "M" icon will be generated automatically.</p>
                    </div>
                </div>
                <p style="margin-top:16px;">
                    <button type="submit" name="mmgr_save_pwa_icon" class="button button-primary">💾 Save Icon</button>
                </p>
            </form>
        </div>

        <script>
        (function() {
            var iconIdField  = document.getElementById('mmgr_pwa_icon_id');
            var previewBox   = document.getElementById('mmgr-pwa-icon-preview');
            var uploadBtn    = document.getElementById('mmgr-pwa-icon-upload');
            var removeBtn    = document.getElementById('mmgr-pwa-icon-remove');
            var frame;

            uploadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title:    'Select PWA App Icon',
                    button:   { text: 'Use this image' },
                    multiple: false,
                    library:  { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    iconIdField.value = attachment.id;
                    var img = document.createElement('img');
                    img.src = attachment.url;
                    img.alt = 'PWA Icon';
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                    previewBox.innerHTML = '';
                    previewBox.appendChild(img);
                    removeBtn.style.display = '';
                });
                frame.open();
            });

            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                iconIdField.value = '0';
                previewBox.innerHTML = '';
                var placeholder = document.createElement('span');
                placeholder.style.fontSize = '32px';
                placeholder.textContent = '🖼️';
                previewBox.appendChild(placeholder);
                removeBtn.style.display = 'none';
            });
        }());
        </script>

        <hr>
        <h2>📱 PWA & Push Notifications</h2>
        <div style="background:#f9f0ff;padding:20px;border-radius:6px;border-left:4px solid #9b51e0;margin-bottom:20px;">
            <p>The member portal is configured as a <strong>Progressive Web App (PWA)</strong>. Members can:</p>
            <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                <li>Tap <em>"Add to Home Screen"</em> in their mobile browser to install the portal as an app.</li>
                <li>Receive <strong>push notifications</strong> when they get a new message (permission is requested automatically on first visit).</li>
            </ul>
            <?php
            if (function_exists('mmgr_pwa_get_vapid_keys')) {
                $vapid = mmgr_pwa_get_vapid_keys();
                if (!empty($vapid['public'])):
            ?>
            <table class="widefat" style="margin-top:15px;">
                <tr>
                    <th style="width:200px;">VAPID Public Key</th>
                    <td><code style="word-break:break-all;"><?php echo esc_html($vapid['public']); ?></code></td>
                </tr>
                <tr>
                    <th>Service Worker URL</th>
                    <td><code><?php echo esc_html(home_url('/mmgr-sw.js')); ?></code></td>
                </tr>
                <tr>
                    <th>Manifest URL</th>
                    <td><code><?php echo esc_html(home_url('/mmgr-manifest.webmanifest')); ?></code></td>
                </tr>
                <tr>
                    <th>Active Subscriptions</th>
                    <td><?php
                        global $wpdb;
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mmgr_push_subscriptions");
                        echo intval($count);
                    ?> device(s) subscribed</td>
                </tr>
            </table>
            <?php else: ?>
            <p style="color:#c00;">⚠️ Could not generate VAPID keys – ensure PHP's OpenSSL extension is enabled.</p>
            <?php
                endif;
            }
            ?>
        </div>

        <hr>
        <h2>🧪 Test Email</h2>
        <form method="post" style="background:#f0f8ff;padding:20px;border-radius:6px;">
            <?php wp_nonce_field('mmgr_test_email', 'mmgr_test_email_nonce'); ?>
            <input type="email" name="test_email_address" class="regular-text" placeholder="your-email@example.com" required>
            <button type="submit" name="send_test_email" class="button">📤 Send Test Email</button>
        </form>

        <hr>
        <h2>🔒 Security</h2>
        <div style="background:#fff5f5;padding:20px;border-radius:6px;border-left:4px solid #d63638;margin-bottom:20px;">
            <h3 style="margin-top:0;color:#d63638;">Log Everyone Out</h3>
            <p>Immediately invalidate <strong>all active member sessions</strong>. Every logged-in member will be required to sign in again on their next visit.</p>
            <form method="post" onsubmit="return confirm('Are you sure you want to log out ALL members? This will invalidate every active session immediately.');">
                <?php wp_nonce_field('mmgr_logout_everyone', 'mmgr_logout_everyone_nonce'); ?>
                <button type="submit" name="mmgr_logout_everyone" class="button button-large" style="background:#d63638;border-color:#d63638;color:#fff;">🚪 Log Everyone Out</button>
            </form>
        </div>
    </div>
    <?php
}