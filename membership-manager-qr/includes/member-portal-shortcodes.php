<?php
if (!defined('ABSPATH')) exit;


function mmgr_get_portal_navigation($active_page = '') {
    ob_start();
    ?>

    
    <div class="mmgr-portal-nav-wrapper">
        <button class="mmgr-nav-toggle-btn" onclick="document.getElementById('mmgr-nav-items').classList.toggle('active');">
            <span>☰ MENU</span>
        </button>
        
        <div id="mmgr-nav-items" class="mmgr-nav-items-container">
            <a href="<?php echo home_url('/member-dashboard/'); ?>" class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">🏠 Dashboard</a>
            <a href="<?php echo home_url('/member-activity/'); ?>" class="<?php echo $active_page === 'activity' ? 'active' : ''; ?>">📊 Activity</a>
            <a href="<?php echo home_url('/member-messages/'); ?>" class="<?php echo $active_page === 'messages' ? 'active' : ''; ?>">💬 Messages</a>
            <a href="<?php echo home_url('/member-profile/'); ?>" class="<?php echo $active_page === 'profile' ? 'active' : ''; ?>">👤 Profile</a>
            <a href="<?php echo home_url('/member-community/'); ?>" class="<?php echo $active_page === 'community' ? 'active' : ''; ?>">👥 Community</a>
			<a href="<?php echo home_url('/members-directory/'); ?>" class="<?php echo $active_page === 'directory' ? 'active' : ''; ?>">📋 Directory</a>
			<a href="<?php echo home_url('/member-dashboard/?action=logout'); ?>" class="logout">🚪 Logout</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * Upcoming Events Widget for Portal Footer
 */
add_shortcode('mmgr_upcoming_events', function($atts) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'membership_events';
    
    // Get only future events
    $events = $wpdb->get_results(
        "SELECT * FROM $events_table 
         WHERE active = 1 AND event_date >= CURDATE()
         ORDER BY event_date ASC, sort_order ASC
         LIMIT 6",
        ARRAY_A
    );
    
    if (empty($events)) {
        return '<div class="mmgr-events-widget"><p style="text-align:center;color:#666;">No upcoming events scheduled.</p></div>';
    }
    
    ob_start();
    ?>
    <div class="mmgr-events-widget" style="margin-top:20px;">
        <h3 style="color:#ce00ff;border-bottom:2px solid #ce00ff;padding-bottom:10px;margin-bottom:15px;">📅 Upcoming Events</h3>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:15px;">
            <?php foreach ($events as $event): ?>
                <div style="background:#f9f9f9;border:2px solid #9b51e0;border-radius:8px;overflow:hidden;transition:transform 0.2s;">
                    <?php if (!empty($event['image_url'])): ?>
                        <img src="<?php echo esc_url($event['image_url']); ?>" style="width:100%;height:180px;object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%;height:180px;background:linear-gradient(135deg, #9b51e0, #ce00ff);display:flex;align-items:center;justify-content:center;color:white;font-size:48px;">
                            📅
                        </div>
                    <?php endif; ?>
                    
                    <div style="padding:15px;">
                        <h4 style="margin:0 0 8px 0;color:#0073aa;">
                            <?php echo esc_html($event['event_name']); ?>
                        </h4>
                        
                        <p style="margin:0 0 8px 0;color:#666;font-size:14px;">
                            📆 <?php echo esc_html(date('M d, Y', strtotime($event['event_date']))); ?>
                        </p>
                        
                        <?php if (!empty($event['start_time'])): ?>
                            <p style="margin:0 0 8px 0;color:#666;font-size:14px;">
                                🕐 <?php echo esc_html(date('g:i A', strtotime($event['start_time']))); 
                                if (!empty($event['end_time'])) {
                                    echo ' - ' . esc_html(date('g:i A', strtotime($event['end_time'])));
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['location'])): ?>
                            <p style="margin:0 0 8px 0;color:#666;font-size:14px;">
                                📍 <?php echo esc_html($event['location']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($event['description'])): ?>
                            <p style="margin:0;color:#333;font-size:13px;line-height:1.4;">
                                <?php echo wp_kses_post($event['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Member Portal Footer
 */
add_shortcode('mmgr_portal_footer', function($atts) {
    ob_start();
    ?>
    <div class="mmgr-portal-footer" style="background:#f9f9f9;padding:30px;margin-top:40px;border-top:3px solid #ce00ff;">
        <?php echo do_shortcode('[mmgr_upcoming_events]'); ?>
        
        <hr style="margin-top:30px;border:none;border-top:1px solid #ddd;">
        
        <footer style="text-align:center;padding:20px;color:#666;">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> - Member Portal</p>
        </footer>
    </div>
    <?php
    return ob_get_clean();
});


/**
 * Password Setup Page
 */
add_shortcode('mmgr_password_setup', function() {
    $success = $error = '';
    
    // Get token from URL
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if (!$token) {
        return '<div class="mmgr-portal-card"><p style="color:#d00;">Invalid access. Please use the link from your email.</p></div>';
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_password'])) {
        if (!isset($_POST['setup_nonce']) || !wp_verify_nonce($_POST['setup_nonce'], 'mmgr_setup_password')) {
            $error = 'Security check failed.';
        } else {
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];
            
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                // Verify token NOW (when actually setting password)
                $member_id = mmgr_verify_portal_token($token);
                
                if ($member_id) {
                    mmgr_set_member_password($member_id, $password);
                    
                    // Delete token ONLY after successful password setup
                    delete_transient('mmgr_portal_token_' . $member_id);
                    
                    // Auto-login
                    mmgr_create_member_session($member_id);
                    
                    $success = true;
                } else {
                    $error = 'Invalid or expired link. Please contact support.';
                }
            }
        }
    }
    
    // Only verify token is valid when SHOWING the form (not submitting)
    if (!$success && empty($error)) {
        $member_id = mmgr_verify_portal_token($token);
        if (!$member_id) {
            return '<div class="mmgr-portal-card">
                <h3 style="color:#d00;">⚠️ Link Expired or Invalid</h3>
                <p>This setup link has expired or is invalid.</p>
                <p>Please contact support to request a new setup link.</p>
            </div>';
        }
    }
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <?php if ($success): ?>
            <div class="mmgr-portal-card">
                <div style="text-align:center;padding:40px 20px;">
                    <div style="font-size:64px;margin-bottom:20px;">✓</div>
                    <h2 style="color:#159742;margin-bottom:10px;">Password Set Successfully!</h2>
                    <p>Your account is now ready. Redirecting to your dashboard...</p>
                    <script>
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_url(home_url('/member-dashboard/')); ?>';
                        }, 2000);
                    </script>
                </div>
            </div>
        <?php else: ?>
            <div class="mmgr-portal-card">
                <h2>🔐 Set Your Password</h2>
                <p>Create a secure password for your member account.</p>
                
                <?php if ($error): ?>
                    <div class="mmgr-error" style="background:#ffe2e2;border-left:4px solid #d00;padding:15px;border-radius:6px;margin:20px 0;color:#d00;">
                        ⚠️ <?php echo esc_html($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php wp_nonce_field('mmgr_setup_password', 'setup_nonce'); ?>
                    
                    <div class="mmgr-field">
                        <label>Password *</label>
                        <input type="password" name="password" required minlength="8" placeholder="At least 8 characters" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <div class="mmgr-field">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" required minlength="8" placeholder="Re-enter password" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                    </div>
                    
                    <button type="submit" name="setup_password" class="mmgr-btn-primary" style="width:100%;padding:14px;background:#FF2197;color:white;border:none;border-radius:6px;font-size:18px;font-weight:bold;cursor:pointer;margin-top:10px;">
                        Set Password & Continue
                    </button>
                </form>
                
                <div style="margin-top:30px;padding:20px;background:#f0f8ff;border-radius:6px;">
                    <h4 style="margin:0 0 10px 0;">What's Next?</h4>
                    <ul style="margin:10px 0;padding-left:20px;">
                        <li>Access your member dashboard</li>
                        <li>View your digital QR code</li>
                        <li>Check your visit history</li>
                        <li>Update your profile</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Member Login Page
 */
add_shortcode('mmgr_member_login', function() {
    // Redirect if already logged in
    if (mmgr_is_member_logged_in()) {
        wp_redirect(home_url('/member-dashboard/'));
        exit;
    }
    
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_login'])) {
        if (!isset($_POST['login_nonce']) || !wp_verify_nonce($_POST['login_nonce'], 'mmgr_member_login')) {
            $error = 'Security check failed.';
        } else {
            $email = sanitize_email($_POST['email']);
            $password = $_POST['password'];
            
            $result = mmgr_verify_member_login($email, $password);
            
            if (is_array($result) && isset($result['error']) && $result['error'] === 'no_password') {
                $error = 'You haven\'t set up your password yet. Please check your email for the setup link.';
            } elseif ($result) {
                mmgr_create_member_session($result['id']);
                wp_redirect(home_url('/member-dashboard/'));
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
    
    // Get registration page URL
    $registration_url = get_permalink(get_option('mmgr_page_registration'));
    if (!$registration_url) {
        $registration_url = mmgr_get_absolute_url(get_option('mmgr_registration_page_url', '/member-registration'));
    }
    
    ob_start();
    ?>

 
    
    <div class="mmgr-login-wrapper">
        <div class="mmgr-login-container">
            <!-- Header -->
            <div class="mmgr-login-header">
                <h1>🔐 Member Portal</h1>
                <p>Welcome back! Sign in to access your account</p>
            </div>
            
            <!-- Login Form -->
            <div class="mmgr-login-body">
                <?php if ($error): ?>
                    <div class="mmgr-login-error">
                        ⚠️ <?php echo esc_html($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php wp_nonce_field('mmgr_member_login', 'login_nonce'); ?>
                    
                    <div class="mmgr-login-field">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="your-email@example.com" autocomplete="email">
                    </div>
                    
                    <div class="mmgr-login-field">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                    </div>
                    
                    <button type="submit" name="member_login" class="mmgr-login-btn">
                        Sign In
                    </button>
                </form>
                
                <div class="mmgr-login-help">
                    Don't have a password? Check your welcome email for the setup link.
                </div>
            </div>
            
            <!-- Sign Up Section -->
            <div class="mmgr-login-signup">
                <p>Not a member yet?</p>
                <a href="<?php echo esc_url($registration_url); ?>" class="mmgr-signup-btn">
                    ✨ Create New Account
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Member Dashboard
 */
add_shortcode('mmgr_member_dashboard', function() {
    // ADMIN BYPASS: Allow admins to view as any member
    if (current_user_can('manage_options') && isset($_GET['view_member'])) {
        global $wpdb;
        $member_id = intval($_GET['view_member']);
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}memberships WHERE id = %d",
            $member_id
        ), ARRAY_A);
        
        if (!$member) {
            return '<div class="mmgr-portal-card"><p style="color:#d00;">Member not found.</p></div>';
        }
        
        $admin_mode = true;
    } else {
        // Normal member login check
        $member = mmgr_get_current_member();
        
        if (!$member) {
            wp_redirect(home_url('/member-login/'));
            exit;
        }
        
        $admin_mode = false;
    }
    
    // Handle logout
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        mmgr_logout_member();
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code=' . urlencode($member['member_code']));
    $card_request = mmgr_get_card_request_status($member['id']);
    
    // Calculate days until expiration (only if paid and has expiry date)
    $is_expired = false;
    $days_left = 0;

    if (!empty($member['paid']) && $member['paid'] == 1 && !empty($member['expire_date'])) {
        $expire_date = new DateTime($member['expire_date']);
        $today = new DateTime();
        $days_left = $today->diff($expire_date)->days;
        $is_expired = $today > $expire_date;
    }
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <?php if ($admin_mode ?? false): ?>
            <div style="background:#ff9800;color:white;padding:15px;text-align:center;font-weight:bold;margin-bottom:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                🔐 ADMIN MODE: Viewing as <?php echo esc_html($member['name']); ?> 
                <a href="<?php echo admin_url('admin.php?page=membership_manager&edit=' . $member['id']); ?>" style="color:white;text-decoration:underline;margin-left:15px;">Edit Member</a>
                <a href="<?php echo admin_url('admin.php?page=membership_manager'); ?>" style="color:white;text-decoration:underline;margin-left:15px;">← Back to Admin</a>
            </div>
        <?php endif; ?>
        
<!-- Navigation -->
<?php echo mmgr_get_portal_navigation('dashboard'); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Welcome back, <?php echo esc_html($member['first_name']); ?>! 👋</h1>
        </div>
        
        <div class="mmgr-portal-grid">
            <!-- QR Code Card -->
            <div class="mmgr-portal-card">
                <h3>📱 Your Digital Membership Card</h3>
                <div style="text-align:center;padding:20px;">
                    <img src="<?php echo esc_url($qr_url); ?>" style="max-width:200px;border:2px solid #0073aa;padding:10px;border-radius:8px;background:white;" alt="QR Code">
                    <p style="font-family:monospace;font-size:18px;font-weight:bold;color:#d00;margin:15px 0;">
                        <?php echo esc_html($member['member_code']); ?>
                    </p>
                    <p style="font-size:14px;color:#666;">Show this QR code when checking in</p>
                    <div style="margin-top:15px;">
                        <a href="<?php echo esc_url($qr_url); ?>" download="my-membership-card.png" class="mmgr-btn-secondary">💾 Download</a>
                    </div>
                </div>
            </div>
            
            <!-- Membership Info -->
            <div class="mmgr-portal-card">
                <h3>📋 Membership Details</h3>
                
                <?php if (isset($member['paid']) && !$member['paid']): ?>
                    <div style="background:#fff3cd;border-left:4px solid #ff9800;padding:15px;margin-bottom:20px;border-radius:6px;">
                        <h4 style="margin:0 0 5px 0;color:#ff9800;">⚠️ Membership Fees Due</h4>
                        <p style="margin:0;font-size:14px;">Please contact us to complete your payment and activate your membership.</p>
                    </div>
                <?php endif; ?>
                
                <table class="mmgr-info-table">
                    <tr>
                        <td><strong>Type:</strong></td>
                        <td><?php echo esc_html($member['level']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Member Since:</strong></td>
                        <td><?php echo date('F j, Y', strtotime($member['start_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <?php if (isset($member['paid']) && !$member['paid']): ?>
                                <span style="color:#ff9800;font-weight:bold;">⚠️ PAYMENT PENDING</span>
                            <?php elseif ($is_expired): ?>
                                <span style="color:#d00;font-weight:bold;">❌ EXPIRED</span>
                            <?php else: ?>
                                <span style="color:#159742;font-weight:bold;">✓ ACTIVE</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Expires:</strong></td>
                        <td>
                            <?php if (isset($member['paid']) && !$member['paid']): ?>
                                <em style="color:#999;">Pending payment</em>
                            <?php elseif (!empty($member['expire_date'])): ?>
                                <?php echo date('F j, Y', strtotime($member['expire_date'])); ?>
                                <?php if ($is_expired): ?>
                                    <span style="color:#d00;font-weight:bold;"> (EXPIRED)</span>
                                <?php else: ?>
                                    <span style="color:#159742;"> (<?php echo $days_left; ?> days left)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <em style="color:#999;">Not set</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Last Visit:</strong></td>
                        <td><?php echo $member['last_visited'] ? date('F j, Y', strtotime($member['last_visited'])) : 'Never'; ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Physical Card Request -->
            <div class="mmgr-portal-card">
                <h3>🎫 Physical Membership Card</h3>
                <?php if ($card_request): ?>
                    <?php if ($card_request['status'] === 'pending'): ?>
                        <div class="mmgr-status-badge status-pending">
                            ⏳ Request Pending
                        </div>
                        <p>Your card request was submitted on <?php echo date('F j, Y', strtotime($card_request['request_date'])); ?>.</p>
                        <p style="font-size:14px;color:#666;">We'll notify you when it's ready for pickup!</p>
                    <?php elseif ($card_request['status'] === 'ready'): ?>
                        <div class="mmgr-status-badge status-ready">
                            ✓ Card Ready!
                        </div>
                        <p style="color:#159742;font-weight:bold;">Your card is ready for pickup!</p>
                    <?php else: ?>
                        <div class="mmgr-status-badge status-completed">
                            ✓ Card Picked Up
                        </div>
                        <p>Picked up on <?php echo date('F j, Y', strtotime($card_request['completed_date'])); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Don't have a physical card yet? Request one to be printed!</p>
                    <p style="font-size:14px;color:#666;margin-bottom:15px;">Your card will include your name, photo, and QR code.</p>
                    <button onclick="requestCard()" class="mmgr-btn-primary">🎫 Request Physical Card</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function requestCard() {
        if (!confirm('Request a physical membership card? You\'ll be notified when it\'s ready.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'mmgr_request_card');
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_request_card'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('✓ ' + d.data.message);
                location.reload();
            } else {
                alert('✕ ' + d.data.message);
            }
        })
        .catch(err => alert('Error submitting request'));
    }
    </script>
    <?php
    return ob_get_clean();
});

// AJAX handler for card request
add_action('wp_ajax_mmgr_request_card', function() {
    check_ajax_referer('mmgr_request_card', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $result = mmgr_request_card($member['id']);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

add_action('wp_ajax_nopriv_mmgr_request_card', function() {
    wp_send_json_error(array('message' => 'Not logged in'));
});


/**
 * Member Activity Page - Visit History
 */
add_shortcode('mmgr_member_activity', function() {
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    // Get visit history
    global $wpdb;
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    
    $visits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $visits_tbl WHERE member_id = %d ORDER BY visit_time DESC LIMIT 50",
        $member['id']
    ), ARRAY_A);
    
    $total_visits = count($visits);
    
    // Get recent likes (last 30 days)
    $likes_tbl = $wpdb->prefix . 'membership_likes';
    $likes = $wpdb->get_results($wpdb->prepare(
        "SELECT l.liked_at, m.id, m.community_alias 
         FROM $likes_tbl l 
         LEFT JOIN {$wpdb->prefix}memberships m ON l.liked_member_id = m.id 
         WHERE l.member_id = %d AND l.liked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY l.liked_at DESC 
         LIMIT 20",
        $member['id']
    ), ARRAY_A);
    
    // Get recent forum posts (last 30 days)
    $posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $topics_tbl = $wpdb->prefix . 'membership_forum_topics';
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT p.id, p.posted_at, t.id as topic_id, t.topic_name 
         FROM $posts_tbl p 
         LEFT JOIN $topics_tbl t ON p.topic_id = t.id 
         WHERE p.member_id = %d AND p.posted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY p.posted_at DESC 
         LIMIT 20",
        $member['id']
    ), ARRAY_A);

    // Get recent forum post likes (last 30 days)
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    $post_likes = $wpdb->get_results($wpdb->prepare(
        "SELECT pl.liked_at, p.id as post_id, t.id as topic_id, t.topic_name
         FROM $post_likes_tbl pl 
         LEFT JOIN {$wpdb->prefix}membership_forum_posts p ON pl.post_id = p.id
         LEFT JOIN {$wpdb->prefix}membership_forum_topics t ON p.topic_id = t.id 
         WHERE pl.member_id = %d AND pl.liked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY pl.liked_at DESC 
         LIMIT 20",
        $member['id']
    ), ARRAY_A);
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('activity'); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Activity 📊</h1>
        </div>
        
        <!-- Main Grid - Responsive -->
        <div class="mmgr-activity-grid">
            <!-- Total Visits Card -->
            <div class="mmgr-portal-card">
                <h3>📈 Total Visits</h3>
                <p style="font-size:64px;font-weight:bold;color:#0073aa;margin:40px 0;text-align:center;">
                    <?php echo $total_visits; ?>
                </p>
            </div>
            
            <!-- Recent Likes -->
            <div class="mmgr-portal-card">
                <h3>❤️ Recent Likes</h3>
                <div style="height: 250px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px;">
                    <?php if (empty($likes)): ?>
                        <p style="text-align: center; color: #999; font-size: 13px; margin: 40px 0;">
                            No likes yet
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($likes as $like): ?>
                                <div style="padding: 10px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #FF2197; cursor: pointer; transition: all 0.3s;" 
                                     onclick="window.location.href='<?php echo home_url('/member-community-profile/'); ?>?id=<?php echo $like['id']; ?>'">
                                    <div style="font-weight: bold; color: #9b51e0; font-size: 14px;">
                                        ❤️ <?php echo esc_html($like['community_alias'] ?: 'Member'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo human_time_diff(strtotime($like['liked_at']), current_time('timestamp')) . ' ago'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Forum Posts -->
            <div class="mmgr-portal-card">
                <h3>💬 Recent Posts</h3>
                <div style="height: 250px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px;">
                    <?php if (empty($posts)): ?>
                        <p style="text-align: center; color: #999; font-size: 13px; margin: 40px 0;">
                            No posts yet
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($posts as $post): ?>
                                <div style="padding: 10px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #9b51e0; cursor: pointer; transition: all 0.3s;" 
                                     onclick="window.location.href='<?php echo home_url('/member-community/'); ?>?topic=<?php echo $post['topic_id']; ?>'">
                                    <div style="font-weight: bold; color: #0073aa; font-size: 14px;">
                                        📝 <?php echo esc_html($post['topic_name'] ?: 'Forum Post'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo human_time_diff(strtotime($post['posted_at']), current_time('timestamp')) . ' ago'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Post Likes -->
            <div class="mmgr-portal-card">
                <h3>💗 Recent Post Likes</h3>
                <div style="height: 250px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px;">
                    <?php if (empty($post_likes)): ?>
                        <p style="text-align: center; color: #999; font-size: 13px; margin: 40px 0;">
                            No post likes yet
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($post_likes as $like): ?>
                                <div style="padding: 10px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #FF2197; cursor: pointer; transition: all 0.3s;" 
                                     onclick="window.location.href='<?php echo home_url('/member-community/'); ?>?topic=<?php echo $like['topic_id']; ?>'">
                                    <div style="font-weight: bold; color: #9b51e0; font-size: 14px;">
                                        💗 <?php echo esc_html(substr($like['topic_name'], 0, 20)); ?>...
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo human_time_diff(strtotime($like['liked_at']), current_time('timestamp')) . ' ago'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Visits Table -->
        <div class="mmgr-portal-card">
            <h3>📅 Recent Visits</h3>
            
            <?php if (empty($visits)): ?>
                <p style="text-align:center;padding:40px;color:#666;">
                    No visits recorded yet. Come visit us soon! 🎾
                </p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;border-bottom:2px solid #ddd;">
                            <th style="padding:12px;text-align:left;">Date & Time</th>
                            <th style="padding:12px;text-align:left;">Daily Fee</th>
                            <th style="padding:12px;text-align:left;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): 
                            // Parse payment status from notes
                            $is_paid = strpos($visit['notes'], 'PAID') !== false;
                            $is_unpaid = strpos($visit['notes'], 'UNPAID') !== false;
                        ?>
                            <tr style="border-bottom:1px solid #e0e0e0;">
                                <td style="padding:12px;">
                                    <strong><?php echo date('F j, Y', strtotime($visit['visit_time'])); ?></strong><br>
                                    <span style="color:#666;font-size:13px;"><?php echo date('g:i A', strtotime($visit['visit_time'])); ?></span>
                                </td>
                                <td style="padding:12px;">
                                    <strong style="color:#00a32a;">$<?php echo number_format($visit['daily_fee'], 2); ?></strong>
                                    <?php if ($is_paid): ?>
                                        <span style="background:#00a32a;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px;">✓ PAID</span>
                                    <?php elseif ($is_unpaid): ?>
                                        <span style="background:#f0c33c;color:#1d2327;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px;">⚠️ UNPAID</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px;color:#666;font-size:13px;">
                                    <?php 
                                    // Remove PAID/UNPAID prefix from notes for display
                                    $display_notes = preg_replace('/^(PAID|UNPAID)\s*-\s*/', '', $visit['notes']);
                                    echo !empty($display_notes) ? esc_html($display_notes) : '—';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_visits >= 50): ?>
                    <p style="margin-top:20px;text-align:center;color:#666;font-size:13px;">
                        Showing your 50 most recent visits
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});



/**
 * Member Profile Page - Update Info
 */
add_shortcode('mmgr_member_profile', function() {
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    $success = $error = '';
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        error_log('Profile update POST received');
        error_log('FILES array: ' . print_r($_FILES, true));
        error_log('Community photo file: ' . (!empty($_FILES['community_photo']) ? $_FILES['community_photo']['name'] : 'NOT FOUND'));
	}
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        if (!isset($_POST['profile_nonce']) || !wp_verify_nonce($_POST['profile_nonce'], 'mmgr_update_profile')) {
            $error = 'Security check failed.';
        } else {
            global $wpdb;
            $tbl = $wpdb->prefix . 'memberships';
            
            $phone = sanitize_text_field($_POST['phone']);
            $email = sanitize_email($_POST['email']);
            $community_alias = sanitize_text_field($_POST['community_alias']);
            $community_bio = sanitize_textarea_field($_POST['community_bio'] ?? '');
            $community_photo_url = $member['community_photo_url']; // Keep existing unless replaced
            
            // Handle community photo upload
            if (!empty($_FILES['community_photo']['name'])) {
                $upload = wp_handle_upload($_FILES['community_photo'], array('test_form' => false));
                
                if (isset($upload['url'])) {
                    $community_photo_url = $upload['url'];
                } else {
                    $error = 'Photo upload failed.';
                }
            }
            
            // Validate email
            if (!is_email($email)) {
                $error = 'Invalid email address.';
            } else {
			// Update member
			$updated = $wpdb->update(
				$tbl,
				array(
					'phone' => $phone,
					'email' => $email,
					'community_alias' => $community_alias,
					'community_bio' => $community_bio,
					'community_photo_url' => $community_photo_url
				),
				array('id' => $member['id'])
			);

			if ($updated !== false) {
				$success = 'Profile updated successfully!';
				// Refresh member data
				$member = mmgr_get_current_member(true); // Force refresh
			} else {
				$error = 'Failed to update profile.';
			}
            }
        }
    }
    
    // Handle password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        if (!isset($_POST['password_nonce']) || !wp_verify_nonce($_POST['password_nonce'], 'mmgr_change_password')) {
            $error = 'Security check failed.';
        } else {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            $verify = mmgr_verify_member_login($member['email'], $current_password);
            
            if (!$verify) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                mmgr_set_member_password($member['id'], $new_password);
                $success = 'Password changed successfully!';
            }
        }
    }
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
<!-- Navigation -->
<?php echo mmgr_get_portal_navigation('profile'); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Your Profile 👤</h1>
        </div>
        
        <?php if ($success): ?>
            <div style="background:#d4edda;border-left:4px solid #00a32a;padding:15px;border-radius:6px;margin-bottom:20px;color:#155724;">
                ✓ <?php echo esc_html($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background:#ffe2e2;border-left:4px solid #d00;padding:15px;border-radius:6px;margin-bottom:20px;color:#d00;">
                ⚠️ <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Profile Info -->
        <div class="mmgr-portal-card">
            <h3>📋 Personal Information</h3>
            
             <form method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('mmgr_update_profile', 'profile_nonce'); ?>
				
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Name</label>
                    <input type="text" value="<?php echo esc_attr($member['name']); ?>" disabled style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:6px;background:#f5f5f5;color:#666;">
                    <p style="margin:5px 0 0 0;font-size:13px;color:#999;">Contact support to change your name</p>
                </div>
                
                <?php if (!empty($member['partner_name'])): ?>
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Partner Name</label>
                    <input type="text" value="<?php echo esc_attr($member['partner_name']); ?>" disabled style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:6px;background:#f5f5f5;color:#666;">
                </div>
                <?php endif; ?>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Email *</label>
                    <input type="email" name="email" value="<?php echo esc_attr($member['email']); ?>" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                </div>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Phone *</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($member['phone']); ?>" required style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                </div>
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Community Alias</label>
                    <input type="text" name="community_alias" value="<?php echo esc_attr($member['community_alias'] ?? ''); ?>" placeholder="Name for community posts (optional)" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                    <p style="margin:5px 0 0 0;font-size:13px;color:#999;">This name will be used in community forums instead of your real name</p>
                </div>

                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Community Bio</label>
                    <textarea name="community_bio" rows="4" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;resize:vertical;" placeholder="Tell other members about yourself (optional)"><?php echo esc_textarea($member['community_bio'] ?? ''); ?></textarea>
                    <p style="margin:5px 0 0 0;font-size:13px;color:#999;">A short bio shown on your community profile page</p>
                </div>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Community Photo</label>
                    <div id="community-photo-preview" style="margin-bottom:10px;">
                        <?php if (!empty($member['community_photo_url'])): ?>
                            <img src="<?php echo esc_url($member['community_photo_url']); ?>" style="max-width:150px;border-radius:8px;border:2px solid #ddd;">
                            <button type="button" onclick="removeCommunityPhoto()" style="margin-left:10px;background:#d00;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">
                                🗑️ Remove Photo
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="community_photo" accept="image/*" id="community-photo-input" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:6px;" onchange="previewCommunityPhoto(this)">
                    <p style="margin:5px 0 0 0;font-size:13px;color:#999;">Profile picture for community posts (optional)</p>
                </div>                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Membership Type</label>
                    <input type="text" value="<?php echo esc_attr($member['level']); ?>" disabled style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:6px;background:#f5f5f5;color:#666;">
                </div>
                
                <button type="submit" name="update_profile" class="mmgr-btn-primary" style="background:#0073aa;color:white;padding:12px 30px;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;">
                    💾 Save Changes
                </button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="mmgr-portal-card" style="margin-top:30px;">
            <h3>🔒 Change Password</h3>
            
            <form method="POST">
                <?php wp_nonce_field('mmgr_change_password', 'password_nonce'); ?>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Current Password *</label>
                    <input type="password" name="current_password" required placeholder="Enter current password" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                </div>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">New Password *</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="At least 8 characters" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                </div>
                
                <div class="mmgr-field" style="margin-bottom:20px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="8" placeholder="Re-enter new password" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;">
                </div>
                
                <button type="submit" name="change_password" class="mmgr-btn-primary" style="background:#d63638;color:white;padding:12px 30px;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;">
                    🔑 Change Password
                </button>
            </form>
        </div>
        
        <!-- Account Info -->
        <div class="mmgr-portal-card" style="margin-top:30px;">
            <h3>ℹ️ Account Information</h3>
            
            <table style="width:100%;">
                <tr style="border-bottom:1px solid #e0e0e0;">
                    <td style="padding:12px;font-weight:bold;">Member Code:</td>
                    <td style="padding:12px;"><code><?php echo esc_html($member['member_code']); ?></code></td>
                </tr>
                <tr style="border-bottom:1px solid #e0e0e0;">
                    <td style="padding:12px;font-weight:bold;">Member Since:</td>
                    <td style="padding:12px;"><?php echo date('F j, Y', strtotime($member['start_date'])); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #e0e0e0;">
                    <td style="padding:12px;font-weight:bold;">Account Created:</td>
                    <td style="padding:12px;"><?php echo date('F j, Y', strtotime($member['start_date'])); ?></td>
                </tr>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Member Community Page - Forum/Discussion Board
 */
add_shortcode('mmgr_member_community', function() {
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    global $wpdb;
    $posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $topics_tbl = $wpdb->prefix . 'membership_forum_topics';
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    
    $success = $error = '';
    
    // Handle new post submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
        if (!isset($_POST['post_nonce']) || !wp_verify_nonce($_POST['post_nonce'], 'mmgr_submit_post')) {
            $error = 'Security check failed.';
        } else {
            $topic_id = intval($_POST['topic_id']);
            $message = sanitize_textarea_field($_POST['message']);
            $photo_url = '';
            
            // Handle photo upload
            if (!empty($_FILES['photo']['name'])) {
                $upload = wp_handle_upload($_FILES['photo'], array('test_form' => false));
                
                if (!isset($upload['error'])) {
                    $photo_url = $upload['url'];
                } else {
                    $error = 'Photo upload failed: ' . $upload['error'];
                }
            }
            
            if (empty($error) && !empty($message)) {
                $wpdb->insert($posts_tbl, array(
                    'member_id' => $member['id'],
                    'topic_id' => $topic_id,
                    'message' => $message,
                    'photo_url' => $photo_url,
                    'posted_at' => current_time('mysql')
                ));
                
                $success = 'Post submitted successfully!';
            } elseif (empty($message)) {
                $error = 'Please enter a message.';
            }
        }
    }
    
    // Get selected topic (default to first topic)
    $selected_topic_id = isset($_GET['topic']) ? intval($_GET['topic']) : 0;
    
    // Get all topics
    $topics = $wpdb->get_results("SELECT * FROM $topics_tbl WHERE active = 1 ORDER BY sort_order, id", ARRAY_A);
    
    if (empty($topics)) {
        $topics = array(array('id' => 0, 'topic_name' => 'General Discussion', 'description' => 'General community discussion'));
    }
    
    if ($selected_topic_id === 0 && !empty($topics)) {
        $selected_topic_id = $topics[0]['id'];
    }
    
    // Get posts for selected topic with like counts
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, m.name as member_name, m.community_alias, m.community_photo_url as member_photo,
                COUNT(pl.id) as like_count,
                (SELECT COUNT(*) FROM $post_likes_tbl WHERE post_id = p.id AND member_id = %d) as user_liked
         FROM $posts_tbl p 
         LEFT JOIN {$wpdb->prefix}memberships m ON p.member_id = m.id 
         LEFT JOIN $post_likes_tbl pl ON p.id = pl.post_id
         WHERE p.topic_id = %d 
         GROUP BY p.id
         ORDER BY p.posted_at ASC 
         LIMIT 50",
        $member['id'],
        $selected_topic_id
    ), ARRAY_A);
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('community'); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Community Forum 💬</h1>
        </div>
        
        <?php if ($success): ?>
            <div style="background:#d4edda;border-left:4px solid #00a32a;padding:15px;border-radius:6px;margin-bottom:20px;color:#155724;">
                ✓ <?php echo esc_html($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background:#ffe2e2;border-left:4px solid #d00;padding:15px;border-radius:6px;margin-bottom:20px;color:#d00;">
                ⚠️ <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Topic Selector -->
        <div class="mmgr-portal-card" style="margin-bottom:20px;">
            <h3>📋 Discussion Topics</h3>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($topics as $topic): ?>
                    <a href="?topic=<?php echo $topic['id']; ?>" 
                       style="padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;transition:all 0.3s;<?php echo $topic['id'] == $selected_topic_id ? 'background:#FF2197;color:white;' : 'background:#f0f0f0;color:#333;'; ?>">
                        <?php echo esc_html($topic['topic_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- New Post Form -->
        <div class="mmgr-portal-card" style="margin-bottom:30px;">
            <h3>✍️ Create New Post</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('mmgr_submit_post', 'post_nonce'); ?>
                <input type="hidden" name="topic_id" value="<?php echo $selected_topic_id; ?>">
                
                <div class="mmgr-field" style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Your Message *</label>
                    <textarea name="message" rows="4" required placeholder="Share your thoughts..." style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;font-family:inherit;"></textarea>
                </div>
                
                <div class="mmgr-field" style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">📷 Add Photo (Optional)</label>
                    <input type="file" name="photo" accept="image/*" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:6px;">
                </div>
                
                <button type="submit" name="submit_post" style="background:#FF2197;color:white;padding:12px 30px;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;">
                    📤 Post Message
                </button>
            </form>
        </div>
        
        <!-- Posts Feed -->
        <div class="mmgr-portal-card">
            <h3>💬 Recent Posts</h3>
            
            <?php if (empty($posts)): ?>
                <p style="text-align:center;padding:40px;color:#666;">
                    No posts yet. Be the first to start the conversation! 🎉
                </p>
            <?php else: ?>
                <!-- Scrollable Posts Container -->
                <div style="height:600px;overflow-y:auto;border:2px solid #f0f0f0;border-radius:8px;padding:20px;background:#fff;">
                    <div style="display:flex;flex-direction:column;gap:20px;">
                        <?php foreach ($posts as $post): ?>
                            <div style="border:2px solid #f0f0f0;border-radius:8px;padding:20px;background:#fafafa;flex-shrink:0;">
                                <!-- Post Header -->
                                <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid #e0e0e0;">
                                    <?php if (!empty($post['member_photo'])): ?>
                                        <img src="<?php echo esc_url($post['member_photo']); ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #FF2197;" alt="Photo">
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:24px;border:2px solid #ccc;flex-shrink:0;">👤</div>
                                    <?php endif; ?>
                                    
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <strong style="font-size:16px;color:#000;">
                                                <?php 
                                                $display_name = !empty($post['community_alias']) ? $post['community_alias'] : $post['member_name'];
                                                echo esc_html($display_name); 
                                                ?>
                                            </strong>
                                            <?php if ($post['member_id'] == $member['id']): ?>
                                                <span style="background:#FF2197;color:white;padding:2px 8px;border-radius:10px;font-size:11px;">You</span>
                                            <?php else: ?>
                                                <!-- PM Button -->
                                                <button onclick="openPMModal(<?php echo $post['member_id']; ?>, '<?php echo esc_attr($display_name); ?>')" style="background:none;border:none;font-size:18px;cursor:pointer;padding:0;margin:0;" title="Send Private Message">
                                                    ✉️
                                                </button>
                                                
                                                <!-- Block/Unblock Button -->
                                                <?php
                                                $is_blocked = $wpdb->get_var($wpdb->prepare(
                                                    "SELECT id FROM {$wpdb->prefix}membership_blocks WHERE member_id = %d AND blocked_member_id = %d",
                                                    $member['id'],
                                                    $post['member_id']
                                                ));
                                                ?>
                                                <button onclick="toggleBlock(<?php echo $post['member_id']; ?>, this)" style="background:none;border:none;font-size:16px;cursor:pointer;padding:0;margin:0;" title="<?php echo $is_blocked ? 'Unblock' : 'Block'; ?>">
                                                    <?php echo $is_blocked ? '🚫' : '⊘'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>                                 
                                        <br>
                                        <span style="font-size:13px;color:#666;"><?php echo date('F j, Y @ g:i A', strtotime($post['posted_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Post Message -->
                                <div style="margin-bottom:15px;font-size:15px;line-height:1.6;color:#333;">
                                    <?php echo nl2br(esc_html($post['message'])); ?>
                                </div>
                                
                                <!-- Post Photo -->
                                <?php if (!empty($post['photo_url'])): ?>
                                    <div style="margin-top:15px;">
                                        <a href="<?php echo esc_url($post['photo_url']); ?>" target="_blank">
                                            <img src="<?php echo esc_url($post['photo_url']); ?>" style="max-width:100%;height:auto;border-radius:8px;border:2px solid #e0e0e0;" alt="Post photo">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Like Button -->
                                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e0e0e0;display:flex;gap:15px;align-items:center;">
                                    <button onclick="togglePostLike(<?php echo $post['id']; ?>, this)" 
                                            class="mmgr-post-like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                            style="background:<?php echo $post['user_liked'] ? '#FF2197' : 'white'; ?>;color:<?php echo $post['user_liked'] ? 'white' : '#FF2197'; ?>;border:2px solid #FF2197;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:14px;transition:all 0.3s;">
                                        ❤️ Like (<?php echo intval($post['like_count']); ?>)
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <p style="margin-top:15px;text-align:center;color:#666;font-size:13px;">
                    📜 Scroll to see more posts | Showing <?php echo count($posts); ?> posts
                </p>
            <?php endif; ?>
        </div>

<script>
    // Auto-scroll to bottom of posts on page load
    document.addEventListener('DOMContentLoaded', function() {
        const postsContainer = document.querySelector('[style*="height:600px"]');
        if (postsContainer) {
            postsContainer.scrollTop = postsContainer.scrollHeight;
        }
    });
    
    // PM Modal
    function openPMModal(memberId, memberName) {
        const message = prompt('Send message to ' + memberName + ':', '');
        if (message !== null && message.trim() !== '') {
            sendPrivateMessage(memberId, message);
        }
    }
    
    function sendPrivateMessage(memberId, message) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=mmgr_send_pm&recipient_id=' + memberId + '&message=' + encodeURIComponent(message) + '&nonce=' + '<?php echo wp_create_nonce('mmgr_send_pm'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Message sent!');
            } else {
                alert('❌ ' + data.data);
            }
        });
    }
    
    function toggleBlock(memberId, button) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=mmgr_toggle_block&member_id=' + memberId + '&nonce=' + '<?php echo wp_create_nonce('mmgr_toggle_block'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.textContent = data.data.blocked ? '🚫' : '⊘';
                button.title = data.data.blocked ? 'Unblock' : 'Block';
                alert(data.data.message);
            } else {
                alert('❌ ' + data.data);
            }
        });
    }
    
    // Like Post
    function togglePostLike(postId, button) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=mmgr_toggle_post_like&post_id=' + postId + '&nonce=' + '<?php echo wp_create_nonce('mmgr_toggle_post_like'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.liked) {
                    button.classList.add('liked');
                    button.style.background = '#FF2197';
                    button.style.color = 'white';
                } else {
                    button.classList.remove('liked');
                    button.style.background = 'white';
                    button.style.color = '#FF2197';
                }
                button.textContent = '❤️ Like (' + data.data.like_count + ')';
            } else {
                alert('❌ ' + data.data.message);
            }
        });
    }
</script>		
    <?php
    return ob_get_clean();
});

/**
 * Member Messages Page
 */
add_shortcode('mmgr_member_messages', function() {
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    $success = $error = '';
    $active_conversation = isset($_GET['chat']) ? intval($_GET['chat']) : null;
    
    // Handle send message
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        if (!isset($_POST['message_nonce']) || !wp_verify_nonce($_POST['message_nonce'], 'mmgr_send_message')) {
            $error = 'Security check failed.';
        } else {
            $to_member_id = intval($_POST['to_member_id']);
            $message = sanitize_textarea_field($_POST['message']);
            $image_url = null;
            
            // Handle image upload
            if (!empty($_FILES['message_image']['name'])) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                
                $upload_overrides = array('test_form' => false);
                $movefile = wp_handle_upload($_FILES['message_image'], $upload_overrides);
                
                if (!isset($movefile['error'])) {
                    $image_url = $movefile['url'];
                } else {
                    $error = 'Image upload failed: ' . $movefile['error'];
                }
            }
            
            if (empty($error) && (!empty($message) || !empty($image_url))) {
                $result = mmgr_send_message($member['id'], $to_member_id, $message, $image_url);
                if ($result['success']) {
                    wp_redirect(add_query_arg('chat', $to_member_id, get_permalink()));
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
    
    // Get conversations list
    $conversations = mmgr_get_conversations_list($member['id']);
    
    // Get contacts
    $contacts = mmgr_get_contacts($member['id']);
    
    // Add admin as default contact (member_id = 0 represents admin)
    $admin_contact = array(
        'id' => 0,
        'name' => 'Admin / Support',
        'photo_url' => null,
        'is_admin' => true
    );
    array_unshift($contacts, $admin_contact);
    
		// Get conversation messages if active
		$messages = array();
		$other_member = null;
		$total_messages = 0;
		if ($active_conversation !== null) {
			// Get first 5 messages
			$messages = mmgr_get_conversation($member['id'], $active_conversation, 5, 0);
			$total_messages = mmgr_get_conversation_count($member['id'], $active_conversation);
	
		// Get other member info
		if ($active_conversation == 0) {
			$other_member = $admin_contact;
		} else {
			global $wpdb;
			$other_member = $wpdb->get_row($wpdb->prepare(
				"SELECT id, name, community_alias, community_photo_url FROM {$wpdb->prefix}memberships WHERE id = %d",
				$active_conversation
			), ARRAY_A);
		}
        
        // Mark messages as read
        mmgr_mark_messages_read($active_conversation, $member['id']);
    }
    
    ob_start();
    ?>
 
   
    
    <div class="mmgr-portal-container mmgr-messages-container">
        <!-- Navigation -->
<!-- Navigation -->
<?php echo mmgr_get_portal_navigation('messages'); ?>

         <div class="mmgr-portal-titlecc">
            <h1>💬 Messages</h1>
        </div>      
        
        <?php if ($success): ?>
            <div style="background:#d4edda;border-left:4px solid #00a32a;padding:15px;border-radius:6px;margin-bottom:20px;color:#155724;">
                ✓ <?php echo esc_html($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background:#ffe2e2;border-left:4px solid #d00;padding:15px;border-radius:6px;margin-bottom:20px;color:#d00;">
                ⚠️ <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="mmgr-messages-grid">
            <!-- Sidebar -->
            <div class="mmgr-sidebar">
                <!-- Tabs -->
                <div class="mmgr-tabs">
                    <button class="mmgr-tab active" onclick="showTab('conversations')">💬 Chats</button>
                    <button class="mmgr-tab" onclick="showTab('contacts')">👥 Contacts</button>
                </div>
                
                <!-- Conversations Tab -->
                <div id="conversations-tab" class="mmgr-tab-content">
                    <?php if (empty($conversations)): ?>
                        <div style="padding:40px 20px;text-align:center;color:#999;">
                            <p style="font-size:48px;margin:0;">💬</p>
                            <p>No conversations yet</p>
                            <p style="font-size:13px;">Start chatting with your contacts!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="mmgr-conversation-item <?php echo ($active_conversation == $conv['member']['id']) ? 'active' : ''; ?>" 
                                 onclick="window.location.href='?chat=<?php echo $conv['member']['id']; ?>'">
                                
                                <?php if (!empty($conv['member']['photo_url'])): ?>
                                    <img src="<?php echo esc_url($conv['member']['photo_url']); ?>" class="mmgr-conversation-avatar" alt="Avatar">
                                <?php else: ?>
                                    <div class="mmgr-avatar-placeholder">
                                        <?php echo $conv['member']['id'] == 0 ? '🛡️' : '👤'; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:bold;color:#000;margin-bottom:3px;">
                                        <?php echo esc_html(mmgr_get_display_name($conv['member'])); ?>
                                    </div>
                                    <div style="font-size:12px;color:#666;">
                                        <?php echo human_time_diff(strtotime($conv['last_message_time']), current_time('timestamp')) . ' ago'; ?>
                                    </div>
                                </div>
                                
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="mmgr-unread-badge"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Contacts Tab -->
                <div id="contacts-tab" class="mmgr-tab-content" style="display:none;">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="mmgr-conversation-item" onclick="window.location.href='?chat=<?php echo $contact['id']; ?>'">
                            <?php if (!empty($contact['photo_url'])): ?>
                                <img src="<?php echo esc_url($contact['photo_url']); ?>" class="mmgr-conversation-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="mmgr-avatar-placeholder">
                                    <?php echo isset($contact['is_admin']) ? '🛡️' : '👤'; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="flex:1;">
                                <div style="font-weight:bold;color:#000;">
                                    <?php echo esc_html(mmgr_get_display_name($contact)); ?>
                                    <?php if (isset($contact['is_admin'])): ?>
                                        <span style="background:#ff9800;color:white;padding:2px 6px;border-radius:10px;font-size:10px;margin-left:5px;">ADMIN</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="padding:15px;border-top:2px solid #e0e0e0;">
                        <button onclick="showAddContact()" style="width:100%;padding:10px;background:#9b51e0;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">
                            ➕ Add Contact
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="mmgr-chat-area">
                <?php if ($active_conversation !== null && $other_member): ?>
                    <!-- Chat Header -->
                    <div class="mmgr-chat-header">
					<?php if (!empty($other_member['community_photo_url'])): ?>
						<img src="<?php echo esc_url($other_member['community_photo_url']); ?>" style="width:50px;height:50px;border-radius:50%;border:2px solid white;" alt="Avatar">
					<?php else: ?>
						<div style="width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;font-size:24px;">
							<?php echo $other_member['id'] == 0 ? '🛡️' : '👤'; ?>
						</div>
					<?php endif; ?>
												
						<div style="flex:1;">
							<h3 style="margin:0;color:white;">
								<?php 
								$display_name = !empty($other_member['community_alias']) ? $other_member['community_alias'] : $other_member['name'];
								echo esc_html($display_name); 
								?>
							</h3>
						</div>
                        
                        <?php if ($other_member['id'] != 0): ?>
                            <div class="dropdown" style="position:relative;">
                                <button onclick="toggleDropdown()" style="background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 12px;border-radius:6px;cursor:pointer;">
                                    ⋮
                                </button>
                                <div id="chat-dropdown" style="display:none;position:absolute;right:0;top:100%;background:white;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.2);min-width:150px;margin-top:5px;z-index:100;">
                                    <button onclick="blockMember(<?php echo $other_member['id']; ?>)" style="width:100%;padding:12px;border:none;background:none;text-align:left;cursor:pointer;color:#d00;">
                                        🚫 Block Member
                                    </button>
                                    <button onclick="deleteConversation(<?php echo $other_member['id']; ?>)" style="width:100%;padding:12px;border:none;background:none;text-align:left;cursor:pointer;color:#666;">
                                        🗑️ Delete Chat
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
					<!-- Load Earlier Messages (above scroll box so always visible) -->
					<div id="load-more-container" style="text-align:center;padding:15px;display:<?php echo (!empty($messages) && $total_messages > 5) ? 'block' : 'none'; ?>;">
						<button type="button" id="load-more-btn" class="mmgr-load-more-btn" onclick="loadMoreMessages()">
							⬆️ Load Earlier Messages
						</button>
					</div>

					<!-- Messages -->
					<div class="mmgr-chat-messages" id="messages-container">
						<?php if (empty($messages)): ?>
							<div style="text-align:center;padding:60px 20px;color:#999;">
								<p style="font-size:48px;margin:0;">👋</p>
								<p>No messages yet. Say hello!</p>
							</div>
						<?php else: ?>
							<!-- Messages List -->
							<div id="messages-list">
								<?php foreach ($messages as $msg): ?>
                                <div class="mmgr-message-bubble <?php echo $msg['from_member_id'] == $member['id'] ? 'sent' : 'received'; ?>">
                                    <div class="mmgr-message-content">
                                        <?php if (!empty($msg['message'])): ?>
                                            <div><?php echo nl2br(esc_html($msg['message'])); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($msg['image_url']) && !$msg['image_deleted']): ?>
                                            <img src="<?php echo esc_url($msg['image_url']); ?>" class="mmgr-message-image" onclick="window.open('<?php echo esc_url($msg['image_url']); ?>', '_blank')" alt="Image">
                                            <?php if ($msg['from_member_id'] == $member['id']): ?>
                                                <div style="margin-top:5px;">
                                                    <button onclick="deleteImage(<?php echo $msg['id']; ?>)" style="font-size:11px;background:rgba(0,0,0,0.2);border:none;color:white;padding:3px 8px;border-radius:10px;cursor:pointer;">
                                                        🗑️ Delete Image
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($msg['image_deleted']): ?>
                                            <div style="opacity:0.5;font-style:italic;font-size:13px;">
                                                🖼️ Image removed by sender
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mmgr-message-time">
                                            <?php echo date('M j, g:i A', strtotime($msg['sent_at'])); ?>
                                        </div>
                                        
                                        <?php if ($msg['from_member_id'] != $member['id'] && $msg['from_member_id'] != 0): ?>
                                            <button onclick="reportMessage(<?php echo $msg['id']; ?>)" style="font-size:10px;background:none;border:none;color:#d00;cursor:pointer;margin-top:5px;padding:0;">
                                                🚩 Report
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
						</div>	
                        <?php endif; ?>
                    </div>
                    
                    <!-- Input Area -->
                    <div class="mmgr-chat-input">
                        <form method="POST" enctype="multipart/form-data" id="message-form">
                            <?php wp_nonce_field('mmgr_send_message', 'message_nonce'); ?>
                            <input type="hidden" name="to_member_id" value="<?php echo $active_conversation; ?>">
                            
                            <div id="image-preview" style="margin-bottom:10px;display:none;">
                                <img id="preview-img" style="max-width:200px;border-radius:8px;border:2px solid #ddd;">
                                <button type="button" onclick="removeImagePreview()" style="margin-left:10px;background:#d00;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">
                                    ✕ Remove
                                </button>
                            </div>
                            
                            <div class="mmgr-input-wrapper">
                                <label for="image-upload" style="cursor:pointer;padding:12px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                    📎
                                </label>
                                <input type="file" id="image-upload" name="message_image" accept="image/*" style="display:none;" onchange="previewImage(this)">
                                
                                <textarea name="message" class="mmgr-message-textarea" rows="2" placeholder="Type a message..." id="message-input"></textarea>
                                
                                <button type="submit" name="send_message" class="mmgr-send-btn">
                                    ➤ Send
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="mmgr-empty-state">
                        <div style="font-size:64px;margin-bottom:20px;">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Choose a contact from the sidebar to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function previewCommunityPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('community-photo-preview');
                preview.innerHTML = '<img src="' + e.target.result + '" style="max-width:150px;border-radius:8px;border:2px solid #ddd;"><button type="button" onclick="removeCommunityPhoto()" style="margin-left:10px;background:#d00;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">🗑️ Remove Photo</button>';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeCommunityPhoto() {
        document.getElementById('community-photo-input').value = '';
        document.getElementById('community-photo-preview').innerHTML = '';
    }	
    function showTab(tab) {
        document.querySelectorAll('.mmgr-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.mmgr-tab-content').forEach(c => c.style.display = 'none');
        
        if (tab === 'conversations') {
            document.querySelectorAll('.mmgr-tab')[0].classList.add('active');
            document.getElementById('conversations-tab').style.display = 'block';
        } else {
            document.querySelectorAll('.mmgr-tab')[1].classList.add('active');
            document.getElementById('contacts-tab').style.display = 'block';
        }
    }
    
    function toggleDropdown() {
        const dropdown = document.getElementById('chat-dropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const dropdown = document.getElementById('chat-dropdown');
            if (dropdown) dropdown.style.display = 'none';
        }
    });
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-img').src = e.target.result;
                document.getElementById('image-preview').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeImagePreview() {
        document.getElementById('image-upload').value = '';
        document.getElementById('image-preview').style.display = 'none';
    }
    
    let messageOffset = 5;
    const otherMemberId = <?php echo intval($active_conversation ?? 0); ?>;
    
    function loadMoreMessages() {
        const btn = document.getElementById('load-more-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Loading...';
        
        const formData = new FormData();
        formData.append('action', 'mmgr_load_more_messages');
        formData.append('other_member_id', otherMemberId);
        formData.append('offset', messageOffset);
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_load_more_messages'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const messagesList = document.getElementById('messages-list');
                const fragment = document.createDocumentFragment();
                
                data.data.messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'mmgr-message-bubble ' + (msg.from_member_id == <?php echo $member['id']; ?> ? 'sent' : 'received');
                    div.setAttribute('data-message-id', msg.id);
                    
                    let content = '';
                    if (msg.message) {
                        content += '<div>' + msg.message.replace(/\n/g, '<br>') + '</div>';
                    }
                    if (msg.image_url && !msg.image_deleted) {
                        content += '<img src="' + msg.image_url + '" class="mmgr-message-image" onclick="window.open(\'' + msg.image_url + '\', \'_blank\')" alt="Image">';
                    }
                    
                    div.innerHTML = '<div class="mmgr-message-content">' + content + '<div class="mmgr-message-time">' + msg.sent_at + '</div></div>';
                    fragment.appendChild(div);
                });
                
                messagesList.insertBefore(fragment, messagesList.firstChild);
                messageOffset += data.data.count;
                
                if (data.data.count < 10) {
                    document.getElementById('load-more-container').style.display = 'none';
                }
            }
            
            btn.disabled = false;
            btn.textContent = '⬆️ Load Earlier Messages';
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = '⬆️ Load Earlier Messages';
            alert('Error loading messages');
        });
    }
    
    // Scroll to bottom of messages
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Auto-refresh messages every 10 seconds
    <?php if ($active_conversation !== null): ?>
    setInterval(function() {
        location.reload();
    }, 10000);
    <?php endif; ?>
    
    function deleteImage(messageId) {
        if (!confirm('Delete this image? It will be hidden from both parties but retained on the server.')) return;
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_delete_image&message_id=' + messageId + '&nonce=<?php echo wp_create_nonce('mmgr_delete_image'); ?>'
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('✓ ' + d.data.message);
                location.reload();
            } else {
                alert('✕ ' + d.data.message);
            }
        });
    }
    
    function reportMessage(messageId) {
        const reason = prompt('Why are you reporting this message?');
        if (!reason) return;
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_report_message&message_id=' + messageId + '&reason=' + encodeURIComponent(reason) + '&nonce=<?php echo wp_create_nonce('mmgr_report_message'); ?>'
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('✓ ' + d.data.message);
            } else {
                alert('✕ ' + d.data.message);
            }
        });
    }
    
    function blockMember(memberId) {
        if (!confirm('Block this member? They will no longer be able to message you.')) return;
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_block_member&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_block_member'); ?>'
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('✓ ' + d.data.message);
                window.location.href = '<?php echo home_url('/member-messages/'); ?>';
            } else {
                alert('✕ ' + d.data.message);
            }
        });
    }
    
    function deleteConversation(memberId) {
        if (!confirm('Delete this conversation? Messages will be removed from your view only.')) return;
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_delete_conversation&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_delete_conversation'); ?>'
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert('✓ ' + d.data.message);
                window.location.href = '<?php echo home_url('/member-messages/'); ?>';
            } else {
                alert('✕ ' + d.data.message);
            }
        });
    }
    
    function showAddContact() {
        alert('Contact management coming soon!');
    }
    </script>
    <?php
    return ob_get_clean();
});

// AJAX: Delete message image
add_action('wp_ajax_mmgr_delete_image', function() {
    check_ajax_referer('mmgr_delete_image', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $message_id = intval($_POST['message_id']);
    $result = mmgr_delete_message_image($message_id, $member['id']);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Report message
add_action('wp_ajax_mmgr_report_message', function() {
    check_ajax_referer('mmgr_report_message', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $message_id = intval($_POST['message_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    $result = mmgr_report_message($message_id, $reason);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Block member
add_action('wp_ajax_mmgr_block_member', function() {
    check_ajax_referer('mmgr_block_member', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $blocked_member_id = intval($_POST['member_id']);
    $result = mmgr_block_member($member['id'], $blocked_member_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Unblock member
add_action('wp_ajax_mmgr_unblock_member', function() {
    check_ajax_referer('mmgr_unblock_member', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $blocked_member_id = intval($_POST['member_id']);
    $result = mmgr_unblock_member($member['id'], $blocked_member_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Delete conversation
add_action('wp_ajax_mmgr_delete_conversation', function() {
    check_ajax_referer('mmgr_delete_conversation', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $other_member_id = intval($_POST['member_id']);
    $result = mmgr_delete_conversation($member['id'], $other_member_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Add contact
add_action('wp_ajax_mmgr_add_contact', function() {
    check_ajax_referer('mmgr_add_contact', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $contact_member_id = intval($_POST['member_id']);
    $result = mmgr_add_contact($member['id'], $contact_member_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Remove contact
add_action('wp_ajax_mmgr_remove_contact', function() {
    check_ajax_referer('mmgr_remove_contact', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $contact_member_id = intval($_POST['member_id']);
    $result = mmgr_remove_contact($member['id'], $contact_member_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
	
	
});


/**
 * Send Private Message via AJAX
 */
add_action('wp_ajax_mmgr_send_pm', function() {
    check_ajax_referer('mmgr_send_pm', 'nonce');
    
    $current_member = mmgr_get_current_member();
    if (!$current_member) {
        wp_send_json_error('You must be logged in to send messages.');
    }
    
    $recipient_id = intval($_POST['recipient_id']);
    $message = sanitize_textarea_field($_POST['message']);
    
    if (empty($message)) {
        wp_send_json_error('Message cannot be empty.');
    }
    
    // Check if recipient is blocked by sender
    global $wpdb;
    $is_blocked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_blocks WHERE member_id = %d AND blocked_member_id = %d",
        $current_member['id'],
        $recipient_id
    ));
    
    if ($is_blocked) {
        wp_send_json_error('You have blocked this user.');
    }
    
    // Check if sender is blocked by recipient
    $sender_blocked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_blocks WHERE member_id = %d AND blocked_member_id = %d",
        $recipient_id,
        $current_member['id']
    ));
    
    if ($sender_blocked) {
        wp_send_json_error('This user has blocked you.');
    }
    
    // Add to contacts if not already there
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->prefix}membership_contacts (member_id, contact_id) VALUES (%d, %d)",
        $current_member['id'],
        $recipient_id
    ));
    
    // Add recipient to sender's contacts too
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->prefix}membership_contacts (member_id, contact_id) VALUES (%d, %d)",
        $recipient_id,
        $current_member['id']
    ));
    
	// Save message
	$wpdb->insert($wpdb->prefix . 'membership_messages', array(
		'from_member_id' => $current_member['id'],
		'to_member_id' => $recipient_id,
		'message' => $message,
		'sent_at' => current_time('mysql'),
		'read_at' => NULL
	));
		
    wp_send_json_success(array(
        'message' => 'Message sent successfully!'
    ));
});

/**
 * Toggle Block User via AJAX
 */
add_action('wp_ajax_mmgr_toggle_block', function() {
    check_ajax_referer('mmgr_toggle_block', 'nonce');
    
    $current_member = mmgr_get_current_member();
    if (!$current_member) {
        wp_send_json_error('You must be logged in.');
    }
    
    $block_member_id = intval($_POST['member_id']);
    
    if ($block_member_id == $current_member['id']) {
        wp_send_json_error('You cannot block yourself.');
    }
    
    global $wpdb;
    $blocks_table = $wpdb->prefix . 'membership_blocks';
    
    // Check if already blocked
    $is_blocked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $blocks_table WHERE member_id = %d AND blocked_member_id = %d",
        $current_member['id'],
        $block_member_id
    ));
    
    if ($is_blocked) {
        // Unblock
        $wpdb->delete($blocks_table, array(
            'member_id' => $current_member['id'],
            'blocked_member_id' => $block_member_id
        ));
        
        wp_send_json_success(array(
            'blocked' => false,
            'message' => 'User unblocked.'
        ));
    } else {
        // Block
        $wpdb->insert($blocks_table, array(
            'member_id' => $current_member['id'],
            'blocked_member_id' => $block_member_id
        ));
        
        wp_send_json_success(array(
            'blocked' => true,
            'message' => 'User blocked.'
        ));
    }
});

/**
 * Members Directory - List all members with aliases in table format
 */
add_shortcode('mmgr_members_directory', function() {
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    global $wpdb;
    
    // Get all members with aliases (excluding the current user and banned members)
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, community_alias, community_photo_url 
         FROM {$wpdb->prefix}memberships 
         WHERE community_alias IS NOT NULL AND community_alias != '' AND id != %d AND banned = 0
         ORDER BY community_alias ASC",
        $member['id']
    ), ARRAY_A);
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('directory'); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Members Directory 👥</h1>
        </div>
        
        <!-- Members Table -->
        <div class="mmgr-portal-card">
            <?php if (empty($members)): ?>
                <p style="text-align:center;padding:40px;color:#666;">
                    No members with community aliases yet. 🤷
                </p>
            <?php else: ?>
                <table class="mmgr-directory-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Photo</th>
                            <th>Alias</th>
                            <th style="width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): 
                            // Check if already liked
                            $is_liked = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}membership_likes WHERE member_id = %d AND liked_member_id = %d",
                                $member['id'],
                                $m['id']
                            ));
                        ?>
                            <tr>
                                <!-- Photo -->
                                <td>
                                    <?php if (!empty($m['community_photo_url'])): ?>
                                        <img src="<?php echo esc_url($m['community_photo_url']); ?>" 
                                             class="mmgr-directory-photo"
                                             onclick="viewCommunityProfile(<?php echo $m['id']; ?>)"
                                             alt="<?php echo esc_attr($m['community_alias']); ?>">
                                    <?php else: ?>
                                        <div style="width:60px;height:60px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:30px;border:3px solid #ccc;cursor:pointer;" 
                                             onclick="viewCommunityProfile(<?php echo $m['id']; ?>)">
                                            👤
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Alias -->
                                <td>
                                    <div class="mmgr-directory-alias" onclick="viewCommunityProfile(<?php echo $m['id']; ?>)">
                                        <?php echo esc_html($m['community_alias']); ?>
                                    </div>
                                </td>
                                
                                <!-- Actions -->
                                <td>
                                    <div class="mmgr-directory-actions">
                                        <!-- Message Button -->
                                        <button onclick="openPMModalDynamic(<?php echo $m['id']; ?>)" 
                                                class="mmgr-directory-btn mmgr-directory-btn-message"
                                                title="Send Message">
                                            ✉️ Message
                                        </button>
                                        
                                        <!-- Like Button -->
                                        <button onclick="toggleLike(<?php echo $m['id']; ?>, this)" 
                                                class="mmgr-directory-btn mmgr-directory-btn-like <?php echo $is_liked ? 'liked' : ''; ?>"
                                                title="<?php echo $is_liked ? 'Unlike' : 'Like'; ?>">
                                            ❤️ <?php echo $is_liked ? 'Liked' : 'Like'; ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mmgr-directory-count">
                    Showing <?php echo count($members); ?> member<?php echo count($members) !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function viewCommunityProfile(memberId) {
            window.location.href = '<?php echo home_url('/member-community-profile/'); ?>?id=' + memberId;
        }

        function openPMModalDynamic(memberId) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mmgr_get_member_alias&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_get_member_alias'); ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const memberName = d.data.alias;
                    const message = prompt('Send message to ' + memberName + ':', '');
                    if (message !== null && message.trim() !== '') {
                        sendPrivateMessage(memberId, message);
                    }
                }
            });
        }
        
        function toggleLike(memberId, button) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mmgr_toggle_like&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_toggle_like'); ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (d.data.liked) {
                        button.classList.add('liked');
                        button.textContent = '❤️ Liked';
                    } else {
                        button.classList.remove('liked');
                        button.textContent = '❤️ Like';
                    }
                } else {
                    alert('❌ ' + d.data.message);
                }
            });
        }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * Toggle Like via AJAX
 */
add_action('wp_ajax_mmgr_toggle_like', function() {
    check_ajax_referer('mmgr_toggle_like', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $liked_member_id = intval($_POST['member_id']);
    
    if ($liked_member_id == $member['id']) {
        wp_send_json_error(array('message' => 'You cannot like yourself'));
    }
    
    global $wpdb;
    $likes_table = $wpdb->prefix . 'membership_likes';
    
    // Check if already liked
    $is_liked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $likes_table WHERE member_id = %d AND liked_member_id = %d",
        $member['id'],
        $liked_member_id
    ));
    
    if ($is_liked) {
        // Unlike
        $wpdb->delete($likes_table, array(
            'member_id' => $member['id'],
            'liked_member_id' => $liked_member_id
        ));
        
        wp_send_json_success(array('liked' => false));
    } else {
        // Like
        $wpdb->insert($likes_table, array(
            'member_id' => $member['id'],
            'liked_member_id' => $liked_member_id,
            'liked_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array('liked' => true));
    }
});

/**
 * Community Profile Page - Shows member activity and stats
 */
add_shortcode('mmgr_member_community_profile', function() {
    // Check if member is logged in
    $current_member = mmgr_get_current_member();
    
    if (!$current_member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    // Get profile member ID from URL
    $profile_member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($profile_member_id === 0) {
        return '<div class="mmgr-portal-container"><p style="color:#d00;">Invalid member profile.</p></div>';
    }
    
    global $wpdb;
    
    // Get member info (including bio)
    $profile_member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, community_alias, community_photo_url, community_bio FROM {$wpdb->prefix}memberships WHERE id = %d AND active = 1",
        $profile_member_id
    ), ARRAY_A);
    
    if (!$profile_member) {
        return '<div class="mmgr-portal-container"><p style="color:#d00;">Member not found.</p></div>';
    }
    
    // Get activity stats (posts per forum)
    $posts_table = $wpdb->prefix . 'membership_forum_posts';
    $topics_table = $wpdb->prefix . 'membership_forum_topics';
    
    $activity = $wpdb->get_results($wpdb->prepare(
        "SELECT t.topic_name, COUNT(p.id) as post_count 
         FROM $posts_table p 
         LEFT JOIN $topics_table t ON p.topic_id = t.id 
         WHERE p.member_id = %d 
         GROUP BY p.topic_id 
         ORDER BY post_count DESC",
        $profile_member_id
    ), ARRAY_A);
    
    $total_posts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $posts_table WHERE member_id = %d",
        $profile_member_id
    ));

    // Check if current member has liked this profile
    $is_liked = false;
    $is_blocked = false;
    $private_note = '';
    if ($profile_member_id != $current_member['id']) {
        $is_liked = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}membership_likes WHERE member_id = %d AND liked_member_id = %d",
            $current_member['id'],
            $profile_member_id
        ));

        $is_blocked = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}membership_blocks WHERE member_id = %d AND blocked_member_id = %d",
            $current_member['id'],
            $profile_member_id
        ));

        // Load existing private note
        $private_note = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT note FROM {$wpdb->prefix}membership_member_notes WHERE viewer_member_id = %d AND profile_member_id = %d",
            $current_member['id'],
            $profile_member_id
        ));
    }

    // Get total likes this profile has received
    $total_likes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}membership_likes WHERE liked_member_id = %d",
        $profile_member_id
    ));
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('directory'); ?>
        
        <!-- Profile Header -->
        <div class="mmgr-portal-card" style="text-align:center;margin-bottom:30px;">
            <?php if (!empty($profile_member['community_photo_url'])): ?>
                <img src="<?php echo esc_url($profile_member['community_photo_url']); ?>" 
                     style="width:150px;height:150px;border-radius:50%;object-fit:cover;border:4px solid #FF2197;margin-bottom:20px;">
            <?php else: ?>
                <div style="width:150px;height:150px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:80px;border:4px solid #ccc;margin:0 auto 20px;">
                    👤
                </div>
            <?php endif; ?>
            
            <h1 style="margin:0 0 10px 0;"><?php echo esc_html($profile_member['community_alias']); ?></h1>

            <!-- Like count display -->
            <p style="margin:0 0 15px 0;color:#888;font-size:15px;">❤️ <span id="profile-like-count"><?php echo $total_likes; ?></span> <?php echo $total_likes === 1 ? 'like' : 'likes'; ?></p>

            <?php if (!empty($profile_member['community_bio'])): ?>
                <!-- Member Bio -->
                <div style="background:#f9f9f9;padding:15px 20px;border-radius:8px;margin-bottom:20px;text-align:left;font-size:15px;color:#444;line-height:1.6;">
                    <?php echo nl2br(esc_html($profile_member['community_bio'])); ?>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <?php if ($profile_member_id != $current_member['id']): ?>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:20px;">
                    <button onclick="openPMModal(<?php echo $profile_member_id; ?>, '<?php echo esc_attr($profile_member['community_alias']); ?>')" 
                            style="background:#FF2197;color:white;padding:12px 30px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        ✉️ Send Message
                    </button>

                    <button id="like-btn" onclick="toggleProfileLike(<?php echo $profile_member_id; ?>, this)"
                            style="background:<?php echo $is_liked ? '#FF2197' : 'white'; ?>;color:<?php echo $is_liked ? 'white' : '#FF2197'; ?>;border:2px solid #FF2197;padding:12px 30px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        ❤️ <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
                    </button>
                    
                    <button onclick="toggleBlock(<?php echo $profile_member_id; ?>, this)" 
                            style="background:<?php echo $is_blocked ? '#d00' : '#999'; ?>;color:white;padding:12px 30px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        <?php echo $is_blocked ? '🚫 Unblock' : '⊘ Block'; ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Activity Stats -->
        <div class="mmgr-portal-card">
            <h2>📊 Community Activity</h2>
            
            <div style="background:#f9f9f9;padding:20px;border-radius:8px;margin-bottom:20px;">
                <h3 style="margin:0 0 20px 0;">Total Forum Posts: <strong><?php echo $total_posts; ?></strong></h3>
                
                <?php if (!empty($activity)): ?>
                    <div style="display:flex;flex-direction:column;gap:15px;">
                        <?php foreach ($activity as $act): ?>
                            <div style="background:white;padding:15px;border-radius:6px;border-left:4px solid #FF2197;">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <strong><?php echo esc_html($act['topic_name']); ?></strong>
                                    <span style="background:#FF2197;color:white;padding:5px 15px;border-radius:20px;font-weight:bold;">
                                        <?php echo $act['post_count']; ?> posts
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#666;">No forum activity yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($profile_member_id != $current_member['id']): ?>
        <!-- Private Notes (only visible to the viewer) -->
        <div class="mmgr-portal-card" style="margin-top:20px;">
            <h2>🔒 My Private Notes</h2>
            <p style="color:#888;font-size:14px;margin-top:0;">Only you can see these notes.</p>
            <textarea id="private-note-text" rows="4" style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:15px;resize:vertical;"
                      placeholder="Add a private note about this member..."><?php echo esc_textarea($private_note); ?></textarea>
            <button onclick="savePrivateNote(<?php echo $profile_member_id; ?>)"
                    style="margin-top:10px;background:#0073aa;color:white;padding:10px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;font-size:15px;">
                💾 Save Note
            </button>
            <span id="note-save-status" style="margin-left:12px;color:#00a32a;font-size:14px;display:none;"></span>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function openPMModal(memberId, memberName) {
            const message = prompt('Send message to ' + memberName + ':', '');
            if (message !== null && message.trim() !== '') {
                sendPrivateMessage(memberId, message);
            }
        }
        
        function sendPrivateMessage(memberId, message) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mmgr_send_pm&recipient_id=' + memberId + '&message=' + encodeURIComponent(message) + '&nonce=' + '<?php echo wp_create_nonce('mmgr_send_pm'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Message sent!');
                } else {
                    alert('❌ ' + data.data);
                }
            })
            .catch(error => {
                alert('❌ Error sending message: ' + error);
            });
        }

        function toggleProfileLike(memberId, button) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mmgr_toggle_like&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_toggle_like'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const liked = data.data.liked;
                    button.textContent = liked ? '❤️ Unlike' : '❤️ Like';
                    button.style.background = liked ? '#FF2197' : 'white';
                    button.style.color = liked ? 'white' : '#FF2197';
                    // Update like count
                    const countEl = document.getElementById('profile-like-count');
                    if (countEl) {
                        countEl.textContent = parseInt(countEl.textContent) + (liked ? 1 : -1);
                    }
                } else {
                    alert('❌ ' + data.data);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
            });
        }
        
        function toggleBlock(memberId, button) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mmgr_toggle_block&member_id=' + memberId + '&nonce=' + '<?php echo wp_create_nonce('mmgr_toggle_block'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = data.data.blocked ? '🚫 Unblock' : '⊘ Block';
                    button.style.background = data.data.blocked ? '#d00' : '#999';
                    alert(data.data.message);
                } else {
                    alert('❌ ' + data.data);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
            });
        }

        function savePrivateNote(profileMemberId) {
            const note = document.getElementById('private-note-text').value;
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mmgr_save_member_note&profile_member_id=' + profileMemberId + '&note=' + encodeURIComponent(note) + '&nonce=<?php echo wp_create_nonce('mmgr_save_member_note'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                const status = document.getElementById('note-save-status');
                if (data.success) {
                    status.textContent = '✅ Saved!';
                    status.style.color = '#00a32a';
                } else {
                    status.textContent = '❌ ' + data.data;
                    status.style.color = '#d00';
                }
                status.style.display = 'inline';
                setTimeout(() => { status.style.display = 'none'; }, 3000);
            })
            .catch(error => {
                alert('❌ Error saving note: ' + error);
            });
        }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * AJAX: Save private member note
 */
add_action('wp_ajax_mmgr_save_member_note', function() {
    check_ajax_referer('mmgr_save_member_note', 'nonce');

    $current_member = mmgr_get_current_member();
    if (!$current_member) {
        wp_send_json_error('Not logged in');
    }

    $profile_member_id = intval($_POST['profile_member_id']);
    if ($profile_member_id <= 0 || $profile_member_id === $current_member['id']) {
        wp_send_json_error('Invalid member');
    }

    global $wpdb;

    // Verify the profile member exists and is active
    $profile_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}memberships WHERE id = %d AND active = 1",
        $profile_member_id
    ));
    if (!$profile_exists) {
        wp_send_json_error('Member not found');
    }

    $note = sanitize_textarea_field($_POST['note'] ?? '');

    $notes_table = $wpdb->prefix . 'membership_member_notes';

    // Check if note exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $notes_table WHERE viewer_member_id = %d AND profile_member_id = %d",
        $current_member['id'],
        $profile_member_id
    ));

    if ($existing) {
        if ($note === '') {
            $wpdb->delete($notes_table, array(
                'viewer_member_id' => $current_member['id'],
                'profile_member_id' => $profile_member_id
            ));
        } else {
            $wpdb->update(
                $notes_table,
                array('note' => $note, 'updated_at' => current_time('mysql')),
                array('viewer_member_id' => $current_member['id'], 'profile_member_id' => $profile_member_id)
            );
        }
    } elseif ($note !== '') {
        $wpdb->insert($notes_table, array(
            'viewer_member_id' => $current_member['id'],
            'profile_member_id' => $profile_member_id,
            'note' => $note,
            'updated_at' => current_time('mysql')
        ));
    }

    wp_send_json_success();
});

add_action('wp_ajax_mmgr_get_member_alias', function() {
    check_ajax_referer('mmgr_get_member_alias', 'nonce');
    
    $member_id = intval($_POST['member_id']);
    global $wpdb;
    
    $alias = $wpdb->get_var($wpdb->prepare(
        "SELECT community_alias FROM {$wpdb->prefix}memberships WHERE id = %d",
        $member_id
    ));
    
    wp_send_json_success(array('alias' => $alias ?: 'Member'));
});


/**
 * Toggle Post Like via AJAX
 */
add_action('wp_ajax_mmgr_toggle_post_like', function() {
    check_ajax_referer('mmgr_toggle_post_like', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $post_id = intval($_POST['post_id']);
    
    global $wpdb;
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    
    // Check if already liked
    $is_liked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $post_likes_tbl WHERE member_id = %d AND post_id = %d",
        $member['id'],
        $post_id
    ));
    
    if ($is_liked) {
        // Unlike
        $wpdb->delete($post_likes_tbl, array(
            'member_id' => $member['id'],
            'post_id' => $post_id
        ));
    } else {
        // Like
        $wpdb->insert($post_likes_tbl, array(
            'member_id' => $member['id'],
            'post_id' => $post_id,
            'liked_at' => current_time('mysql')
        ));
    }
    
    // Get updated like count
    $like_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $post_likes_tbl WHERE post_id = %d",
        $post_id
    ));
    
    wp_send_json_success(array(
        'liked' => !$is_liked,
        'like_count' => $like_count
    ));
});

/**
 * Load shared JavaScript functions globally
 */
add_action('wp_footer', function() {
    ?>
    <script>
    function sendPrivateMessage(memberId, message) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=mmgr_send_pm&recipient_id=' + memberId + '&message=' + encodeURIComponent(message) + '&nonce=' + '<?php echo wp_create_nonce('mmgr_send_pm'); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Message sent!');
            } else {
                alert('❌ ' + data.data);
            }
        })
        .catch(error => {
            alert('❌ Error: ' + error);
        });
    }
    </script>
    <?php
});