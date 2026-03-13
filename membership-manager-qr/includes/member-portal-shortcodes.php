<?php
if (!defined('ABSPATH')) exit;


function mmgr_get_portal_navigation($active_page = '', $member = null) {
    // Build a member-specific query suffix so that each member's URLs are unique.
    // This prevents NGINX from serving a page cached for one member to another.
    $uc = ($member && !empty($member['member_code'])) ? rawurlencode($member['member_code']) : '';

    $dashboard_url  = $uc ? home_url('/member-dashboard/?usercod=' . $uc)  : home_url('/member-dashboard/');
    $activity_url   = $uc ? home_url('/member-activity/?usercod=' . $uc)   : home_url('/member-activity/');
    $messages_url   = $uc ? home_url('/member-messages/?usercod=' . $uc)   : home_url('/member-messages/');
    $profile_url    = $uc ? home_url('/member-profile/?usercod=' . $uc)    : home_url('/member-profile/');
    $community_url  = $uc ? home_url('/member-community/?usercod=' . $uc)  : home_url('/member-community/');
    $directory_url  = $uc ? home_url('/members-directory/?usercod=' . $uc) : home_url('/members-directory/');
    $coc_url        = $uc ? home_url('/member-code-of-conduct/?usercod=' . $uc) : home_url('/member-code-of-conduct/');
    $logout_url     = $uc ? home_url('/member-dashboard/?usercod=' . $uc . '&action=logout') : home_url('/member-dashboard/?action=logout');

    // Pending friend request count for nav badge
    $pending_fr_count = 0;
    if ( $member && ! empty( $member['id'] ) ) {
        $pending_fr_count = mmgr_get_pending_friend_request_count( (int) $member['id'] );
    }

    ob_start();
    ?>

    
    <div class="mmgr-portal-nav-wrapper">
        <button class="mmgr-nav-toggle-btn" onclick="document.getElementById('mmgr-nav-items').classList.toggle('active');">
            ☰ MENU <span id="mmgr-nav-unread-badge" class="mmgr-nav-unread-badge" style="display:none;"></span>
        </button>
        
        <div id="mmgr-nav-items" class="mmgr-nav-items-container">
            <a href="<?php echo esc_url($dashboard_url); ?>" class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">🏠 Dashboard</a>
            <a href="<?php echo esc_url($activity_url); ?>" class="<?php echo $active_page === 'activity' ? 'active' : ''; ?>">📊 Activity<?php if ($pending_fr_count > 0): ?> <span class="mmgr-nav-unread-badge" style="display:inline-block;"><?php echo $pending_fr_count; ?></span><?php endif; ?></a>
            <a href="<?php echo esc_url($messages_url); ?>" class="<?php echo $active_page === 'messages' ? 'active' : ''; ?>">💬 Messages <span id="mmgr-messages-unread-badge" class="mmgr-nav-unread-badge" style="display:none;"></span></a>
            <a href="<?php echo esc_url($profile_url); ?>" class="<?php echo $active_page === 'profile' ? 'active' : ''; ?>">👤 Profile</a>
            <a href="<?php echo esc_url($community_url); ?>" class="<?php echo $active_page === 'community' ? 'active' : ''; ?>">👥 Community</a>
			<a href="<?php echo esc_url($directory_url); ?>" class="<?php echo $active_page === 'directory' ? 'active' : ''; ?>">📋 Directory</a>
			<a href="<?php echo esc_url($coc_url); ?>" class="<?php echo $active_page === 'coc' ? 'active' : ''; ?>">📜 Code of Conduct</a>
			<a href="<?php echo esc_url($logout_url); ?>" class="logout">🚪 Logout</a>
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
    nocache_headers();
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
    nocache_headers();
    // Do NOT auto-redirect if a session cookie already exists.
    // Automatically redirecting to the dashboard when any valid session is present
    // causes a security issue on shared devices: person B visiting the login page
    // would be silently forwarded to person A's dashboard without ever entering
    // their own credentials.  Always show the login form so whoever submits it
    // gets their own, fresh session.
    
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
                mmgr_create_member_session($result['id'], $email);
                wp_redirect(add_query_arg('usercod', $result['member_code'], home_url('/member-dashboard/')));
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
    // Prevent this page from being cached by page-caching plugins or CDNs.
    // Caching a member's personalised dashboard and then serving it to a
    // different visitor would expose private account information.
    nocache_headers();
    
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

    // Enforce ?usercod= parameter for NGINX cache isolation (skip for admin view-as-member).
    if (!($admin_mode ?? false)) {
        mmgr_enforce_usercod($member);
    }
    
    // Handle logout
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        mmgr_logout_member();
        wp_redirect(home_url('/member-login/'));
        exit;
    }
    
    $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code=' . urlencode($member['member_code']));
    $card_request = mmgr_get_card_request_status($member['id']);

    // Get unread messages count
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    $unread_messages = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $messages_table WHERE to_member_id = %d AND read_at IS NULL AND deleted_by_receiver = 0",
        $member['id']
    ));

    // Get total likes received count (profile likes + post likes)
    $likes_table = $wpdb->prefix . 'membership_likes';
    $profile_likes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $likes_table WHERE liked_member_id = %d",
        $member['id']
    ));
    $post_likes_tbl = $wpdb->prefix . 'membership_forum_post_likes';
    $forum_tbl = $wpdb->prefix . 'mmgr_forum_posts';
    $post_likes_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $post_likes_tbl WHERE post_id IN (SELECT id FROM $forum_tbl WHERE member_id = %d)",
        $member['id']
    ));
    $total_likes = $profile_likes + $post_likes_count;
    
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
                <a href="<?php echo admin_url('admin.php?page=membership_add&id=' . $member['id']); ?>" style="color:white;text-decoration:underline;margin-left:15px;">Edit Member</a>
                <a href="<?php echo admin_url('admin.php?page=membership_manager'); ?>" style="color:white;text-decoration:underline;margin-left:15px;">← Back to Admin</a>
            </div>
        <?php endif; ?>
        
<!-- Navigation -->
<?php echo mmgr_get_portal_navigation('dashboard', $member); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Welcome back, <?php echo esc_html($member['first_name']); ?>! 👋</h1>
            <p>You have <a href="<?php echo esc_url(add_query_arg('usercod', $member['member_code'], home_url('/member-messages/'))); ?>" aria-label="View your unread messages"><?php echo esc_html($unread_messages); ?> unread <?php echo $unread_messages === 1 ? 'message' : 'messages'; ?></a> and <a href="<?php echo esc_url(add_query_arg('usercod', $member['member_code'], home_url('/members-directory/'))); ?>" aria-label="View members who liked your content"><?php echo esc_html($total_likes); ?> <?php echo $total_likes === 1 ? 'like' : 'likes'; ?></a></p>
            <?php if (empty($member['community_alias']) || empty($member['community_bio']) || empty($member['community_photo_url'])): ?>
            <p>Set up your community profile. Add a Photo, Bio and an Alias - <a href="<?php echo esc_url(add_query_arg('usercod', $member['member_code'], home_url('/member-profile/'))); ?>">Click Here</a></p>
            <?php endif; ?>
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
    nocache_headers();
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($member);
    
    // Get visit history
    global $wpdb;
    $visits_tbl = $wpdb->prefix . 'membership_visits';
    
    $visits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $visits_tbl WHERE member_id = %d ORDER BY visit_time DESC LIMIT 50",
        $member['id']
    ), ARRAY_A);
    
    $total_visits = count($visits);
    
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

    // Get ALL likes this member has RECEIVED (profile likes + photo likes + post likes),
    // sorted newest first, first 10 for the initial render.
    $received_likes_per_page = 10;
    $received_likes = mmgr_get_received_likes($member['id'], 0, $received_likes_per_page);

    // Total received-like count (for Load More logic)
    $total_received_likes = mmgr_count_received_likes($member['id']);

    // Get items this member has LIKED (sent likes), first 10 for the initial render.
    $sent_likes_per_page = 10;
    $sent_likes = mmgr_get_sent_likes($member['id'], 0, $sent_likes_per_page);

    // Total sent-like count (for Load More logic)
    $total_sent_likes = mmgr_count_sent_likes($member['id']);

    // Friends data
    $pending_requests = $wpdb->get_results($wpdb->prepare(
        "SELECT f.id as request_id, f.requester_id, m.community_alias, m.community_photo_url, f.created_at
         FROM {$wpdb->prefix}membership_friends f
         JOIN {$wpdb->prefix}memberships m ON m.id = f.requester_id
         WHERE f.requestee_id = %d AND f.status = 'pending'
         ORDER BY f.created_at DESC",
        $member['id']
    ), ARRAY_A);

    $my_friends   = mmgr_get_friends((int) $member['id']);
    $friend_feed  = mmgr_get_friend_activity_feed((int) $member['id'], 0, 15);
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('activity', $member); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Activity 📊</h1>
        </div>
        
        <!-- Main Grid - Responsive -->
        <div class="mmgr-activity-grid">
            <!-- Total Visits Card -->
            <div class="mmgr-portal-card">
                <h3>📈 Total live events attended with club!</h3>
                <p style="font-size:64px;font-weight:bold;color:#0073aa;margin:40px 0;text-align:center;">
                    <?php echo $total_visits; ?>
                </p>
            </div>
            
            <!-- Likes Received -->
            <div class="mmgr-portal-card">
                <h3>❤️ Likes Received</h3>
                <div id="mmgr-received-likes-list" style="max-height:300px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:6px;padding:15px;display:flex;flex-direction:column;gap:12px;">
                    <?php if (empty($received_likes)): ?>
                        <p style="text-align:center;color:#999;font-size:13px;margin:40px 0;">
                            No likes received yet
                        </p>
                    <?php else: ?>
                        <?php foreach ($received_likes as $like): ?>
                            <?php echo mmgr_render_received_like_item($like); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($total_received_likes > $received_likes_per_page): ?>
                <div style="text-align:center;margin-top:12px;">
                    <button id="mmgr-likes-load-more"
                            onclick="mmgrLoadMoreLikes(this)"
                            data-offset="<?php echo $received_likes_per_page; ?>"
                            data-nonce="<?php echo wp_create_nonce('mmgr_load_received_likes'); ?>"
                            style="background:white;color:#FF2197;border:1.5px solid #FF2197;padding:7px 22px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:bold;">
                        Load More
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Things I Liked -->
            <div class="mmgr-portal-card">
                <h3>👍 Things I Liked</h3>
                <div id="mmgr-sent-likes-list" style="max-height:300px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:6px;padding:15px;display:flex;flex-direction:column;gap:12px;">
                    <?php if (empty($sent_likes)): ?>
                        <p style="text-align:center;color:#999;font-size:13px;margin:40px 0;">
                            You haven't liked anything yet
                        </p>
                    <?php else: ?>
                        <?php foreach ($sent_likes as $like): ?>
                            <?php echo mmgr_render_sent_like_item($like); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($total_sent_likes > $sent_likes_per_page): ?>
                <div style="text-align:center;margin-top:12px;">
                    <button id="mmgr-sent-likes-load-more"
                            onclick="mmgrLoadMoreSentLikes(this)"
                            data-offset="<?php echo $sent_likes_per_page; ?>"
                            data-nonce="<?php echo wp_create_nonce('mmgr_load_sent_likes'); ?>"
                            style="background:white;color:#9b51e0;border:1.5px solid #9b51e0;padding:7px 22px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:bold;">
                        Load More
                    </button>
                </div>
                <?php endif; ?>
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
                                     onclick="window.location.href='<?php echo home_url('/member-community/'); ?>?topic=<?php echo intval($post['topic_id']); ?>&usercod=<?php echo rawurlencode($member['member_code']); ?>'">
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
        </div>
        
        <!-- ============================================================ -->
        <!-- FRIENDS SECTION                                              -->
        <!-- ============================================================ -->

        <!-- Pending Friend Requests -->
        <?php if (!empty($pending_requests)): ?>
        <div class="mmgr-portal-card" style="border-left:4px solid #0073aa;margin-bottom:20px;">
            <h3 style="color:#0073aa;">🔔 Friend Requests (<?php echo count($pending_requests); ?>)</h3>
            <div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
                <?php foreach ($pending_requests as $req):
                    $req_alias   = esc_html(mmgr_unescape_alias($req['community_alias'] ?: 'Member'));
                    $req_id      = (int) $req['requester_id'];
                    $req_photo   = $req['community_photo_url'];
                    $profile_url = esc_url(home_url('/member-community-profile/?id=' . $req_id));
                    $nonce_res   = wp_create_nonce('mmgr_friend_respond');
                    $nonce_unf   = wp_create_nonce('mmgr_unfriend');
                ?>
                <div id="req-row-<?php echo $req_id; ?>" style="display:flex;align-items:center;gap:12px;padding:10px;background:#f0f8ff;border-radius:8px;">
                    <a href="<?php echo $profile_url; ?>">
                        <?php if ($req_photo): ?>
                            <img src="<?php echo esc_url($req_photo); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #0073aa;" alt="">
                        <?php else: ?>
                            <div style="width:44px;height:44px;border-radius:50%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:22px;">👤</div>
                        <?php endif; ?>
                    </a>
                    <div style="flex:1;">
                        <strong><a href="<?php echo $profile_url; ?>" style="color:#0073aa;text-decoration:none;"><?php echo $req_alias; ?></a></strong>
                        <div style="font-size:12px;color:#888;"><?php echo human_time_diff(strtotime($req['created_at']), current_time('timestamp')); ?> ago</div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button onclick="activityFriendRespond(<?php echo $req_id; ?>,this,'accept','<?php echo esc_attr($nonce_res); ?>')"
                                style="background:#00a32a;color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:13px;">
                            ✅ Accept
                        </button>
                        <button onclick="activityFriendRespond(<?php echo $req_id; ?>,this,'decline','<?php echo esc_attr($nonce_unf); ?>')"
                                style="background:#aaa;color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">
                            ✕ Decline
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- My Friends List -->
        <div class="mmgr-portal-card" style="margin-bottom:20px;">
            <h3>👥 My Friends (<?php echo count($my_friends); ?>)</h3>
            <?php if (empty($my_friends)): ?>
                <p style="color:#999;font-size:14px;">You haven't connected with any friends yet. Visit the <a href="<?php echo esc_url(home_url('/members-directory/?usercod=' . rawurlencode($member['member_code']))); ?>" style="color:#FF2197;">Members Directory</a> to find people to friend!</p>
            <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;">
                    <?php foreach ($my_friends as $f):
                        $f_alias       = esc_html(mmgr_unescape_alias($f['community_alias'] ?: $f['name']));
                        $f_profile_url = esc_url(home_url('/member-community-profile/?id=' . $f['id'] . '&usercod=' . rawurlencode($member['member_code'])));
                    ?>
                    <a href="<?php echo $f_profile_url; ?>" style="display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;color:#333;max-width:72px;">
                        <?php if (!empty($f['community_photo_url'])): ?>
                            <img src="<?php echo esc_url($f['community_photo_url']); ?>"
                                 style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:3px solid #28a745;" alt="">
                        <?php else: ?>
                            <div style="width:52px;height:52px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:24px;border:3px solid #28a745;">👤</div>
                        <?php endif; ?>
                        <span style="font-size:12px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;"><?php echo $f_alias; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Friends Activity Feed -->
        <?php if (!empty($friend_feed)): ?>
        <div class="mmgr-portal-card" style="margin-bottom:20px;">
            <h3>📡 Friends Activity</h3>
            <p style="font-size:13px;color:#888;margin-top:0;">Recent things your friends have been doing in the community.</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">
                <?php foreach ($friend_feed as $fitem): ?>
                    <?php echo mmgr_render_friend_activity_item($fitem); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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
    <script>
    function mmgrLoadMoreLikes(button) {
        var offset = parseInt(button.dataset.offset, 10);
        var nonce  = button.dataset.nonce;
        button.disabled = true;
        button.textContent = 'Loading…';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_load_received_likes&offset=' + offset + '&nonce=' + encodeURIComponent(nonce)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var list = document.getElementById('mmgr-received-likes-list');
                list.insertAdjacentHTML('beforeend', data.data.html);
                if (data.data.has_more) {
                    button.dataset.offset = data.data.next_offset;
                    button.disabled = false;
                    button.textContent = 'Load More';
                } else {
                    button.parentNode.removeChild(button);
                }
            } else {
                button.disabled = false;
                button.textContent = 'Load More';
            }
        })
        .catch(function() {
            button.disabled = false;
            button.textContent = 'Load More';
        });
    }

    function mmgrLoadMoreSentLikes(button) {
        var offset = parseInt(button.dataset.offset, 10);
        var nonce  = button.dataset.nonce;
        button.disabled = true;
        button.textContent = 'Loading…';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mmgr_load_sent_likes&offset=' + offset + '&nonce=' + encodeURIComponent(nonce)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var list = document.getElementById('mmgr-sent-likes-list');
                list.insertAdjacentHTML('beforeend', data.data.html);
                if (data.data.has_more) {
                    button.dataset.offset = data.data.next_offset;
                    button.disabled = false;
                    button.textContent = 'Load More';
                } else {
                    button.parentNode.removeChild(button);
                }
            } else {
                button.disabled = false;
                button.textContent = 'Load More';
            }
        })
        .catch(function() {
            button.disabled = false;
            button.textContent = 'Load More';
        });
    }

    /**
     * Accept or decline a friend request from the activity page.
     * For 'decline' we call mmgr_unfriend (which also deletes pending rows).
     */
    function activityFriendRespond(requesterId, btn, actionType, nonce) {
        btn.disabled = true;
        var row = document.getElementById('req-row-' + requesterId);

        var ajaxAction = (actionType === 'accept') ? 'mmgr_friend_respond' : 'mmgr_unfriend';
        var body;
        if (actionType === 'accept') {
            body = 'action=' + ajaxAction + '&profile_id=' + requesterId + '&action_type=accept&nonce=' + encodeURIComponent(nonce);
        } else {
            body = 'action=' + ajaxAction + '&profile_id=' + requesterId + '&nonce=' + encodeURIComponent(nonce);
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (row) {
                    row.innerHTML = '<div style="padding:8px;color:#888;font-style:italic;">'
                        + (actionType === 'accept' ? '✅ Friend request accepted!' : '✕ Request declined.')
                        + '</div>';
                    setTimeout(function() { if (row) row.remove(); }, 2000);
                }
            } else {
                btn.disabled = false;
                alert('❌ ' + (data.data.message || data.data));
            }
        })
        .catch(function() {
            btn.disabled = false;
            alert('❌ Connection error');
        });
    }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * Member Profile Page - Update Info
 */
add_shortcode('mmgr_member_profile', function() {
    nocache_headers();
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($member);
    
    $success = $error = '';

    // Show success message after PRG redirect
    if (isset($_GET['profile_updated']) && sanitize_key($_GET['profile_updated']) === '1') {
        $success = 'Profile updated successfully!';
    }

    // Handle profile update
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
            
            // Handle community photo removal
            if (isset($_POST['remove_community_photo']) && $_POST['remove_community_photo'] === '1') {
                $community_photo_url = '';
            }
            
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
            if (empty($error) && !is_email($email)) {
                $error = 'Invalid email address.';
            }

            if (empty($error)) {
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
                    // PRG: redirect to avoid re-submission on refresh and to load fresh data
                    wp_redirect(esc_url_raw(add_query_arg('profile_updated', '1')));
                    exit;
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
<?php echo mmgr_get_portal_navigation('profile', $member); ?>
        
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
        <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('mmgr_update_profile', 'profile_nonce'); ?>
        <div class="mmgr-portal-card">
            <h3>📋 Personal Information</h3>
				
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
                    <label style="display:block;font-weight:bold;margin-bottom:5px;">Membership Type</label>
                    <input type="text" value="<?php echo esc_attr($member['level']); ?>" disabled style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:6px;background:#f5f5f5;color:#666;">
                </div>
            </div>

        <!-- Your Online Profile -->
        <div class="mmgr-portal-card" style="margin-top:30px;">
            <h3>🌐 Your Online Profile</h3>

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
                    <input type="hidden" name="remove_community_photo" id="remove-community-photo" value="0">
                    <div id="community-photo-preview" style="margin-bottom:10px;">
                        <?php if (!empty($member['community_photo_url'])): ?>
                            <img src="<?php
                                $photo_url = $member['community_photo_url'];
                                $photo_path = str_replace(
                                    array(site_url('/'), home_url('/')),
                                    array(ABSPATH, ABSPATH),
                                    $photo_url
                                );
                                $v = file_exists($photo_path) ? filemtime($photo_path) : substr(md5($photo_url), 0, 8);
                                echo esc_url(add_query_arg('v', $v, $photo_url));
                            ?>" style="max-width:150px;border-radius:8px;border:2px solid #ddd;">
                            <button type="button" onclick="removeCommunityPhoto()" style="margin-left:10px;background:#d00;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">
                                🗑️ Remove Photo
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="community_photo" accept="image/*" id="community-photo-input" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:6px;" onchange="previewCommunityPhoto(this)">
                    <p style="margin:5px 0 0 0;font-size:13px;color:#999;">Profile picture for community posts (optional)</p>
                </div>
                
                <button type="submit" name="update_profile" class="mmgr-btn-primary" style="background:#0073aa;color:white;padding:12px 30px;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;">
                    💾 Save Changes
                </button>
            </div>
        </form>
        
        <!-- Bio Photos Gallery (up to 50) -->
        <?php
        global $wpdb;
        $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';
        $bio_photos = $wpdb->get_results($wpdb->prepare(
            "SELECT id, photo_url FROM $bio_photos_tbl WHERE member_id = %d ORDER BY sort_order ASC, id ASC",
            $member['id']
        ), ARRAY_A);
        $photo_count = count($bio_photos);
        ?>
        <div class="mmgr-portal-card" style="margin-top:30px;">
            <h3>📸 My Bio Photos <span style="font-size:13px;font-weight:normal;color:#888;">(<?php echo $photo_count; ?>/50)</span></h3>
            <p style="color:#666;font-size:14px;margin-top:0;">These photos are shown on your community profile page. You can add up to 50 photos.</p>

            <?php if (!empty($bio_photos)): ?>
            <div id="bio-photos-grid" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <?php foreach ($bio_photos as $bp): ?>
                <div id="bio-photo-<?php echo intval($bp['id']); ?>" style="position:relative;display:inline-block;">
                    <img src="<?php echo esc_url($bp['photo_url']); ?>"
                         style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid #ddd;display:block;">
                    <button type="button"
                            onclick="deleteBioPhoto(<?php echo intval($bp['id']); ?>)"
                            style="position:absolute;top:4px;right:4px;background:rgba(200,0,0,0.85);color:white;border:none;border-radius:50%;width:24px;height:24px;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                            title="Delete photo">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div id="bio-photos-grid" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;"></div>
            <?php endif; ?>

            <?php if ($photo_count < 50): ?>
            <div id="bio-photo-upload-area">
                <label style="display:block;font-weight:bold;margin-bottom:8px;">Add Photo</label>
                <input type="file" id="bio-photo-input" accept="image/*" style="margin-bottom:8px;">
                <br>
                <button type="button" onclick="uploadBioPhoto()" style="background:#0073aa;color:white;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:15px;font-weight:bold;">
                    📷 Upload Photo
                </button>
                <span id="bio-photo-status" style="margin-left:12px;font-size:14px;"></span>
            </div>
            <?php else: ?>
            <div id="bio-photo-upload-area">
                <p style="color:#d63638;font-weight:bold;">Maximum of 50 photos reached. Delete a photo to add a new one.</p>
            </div>
            <?php endif; ?>
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
    <script>
    function uploadBioPhoto() {
        const input = document.getElementById('bio-photo-input');
        const status = document.getElementById('bio-photo-status');
        if (!input.files || !input.files[0]) {
            status.style.color = '#d63638';
            status.textContent = '⚠️ Please select a photo first.';
            return;
        }
        const formData = new FormData();
        formData.append('action', 'mmgr_upload_bio_photo');
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_bio_photo'); ?>');
        formData.append('photo', input.files[0]);
        status.style.color = '#0073aa';
        status.textContent = '⏳ Uploading…';
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    status.style.color = '#00a32a';
                    status.textContent = '✅ Photo added!';
                    input.value = '';
                    // Insert new photo tile using DOM methods (avoids XSS via string concatenation)
                    const grid = document.getElementById('bio-photos-grid');
                    const wrap = document.createElement('div');
                    wrap.id = 'bio-photo-' + parseInt(data.data.id, 10);
                    wrap.style.cssText = 'position:relative;display:inline-block;';
                    const img = document.createElement('img');
                    img.src = data.data.url;
                    img.style.cssText = 'width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid #ddd;display:block;';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.title = 'Delete photo';
                    btn.textContent = '✕';
                    btn.style.cssText = 'position:absolute;top:4px;right:4px;background:rgba(200,0,0,0.85);color:white;border:none;border-radius:50%;width:24px;height:24px;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;';
                    const photoId = parseInt(data.data.id, 10);
                    btn.addEventListener('click', function() { deleteBioPhoto(photoId); });
                    wrap.appendChild(img);
                    wrap.appendChild(btn);
                    grid.appendChild(wrap);
                    // If limit reached, hide the upload area without a full reload
                    if (data.data.count >= 50) {
                        const uploadArea = document.getElementById('bio-photo-upload-area');
                        if (uploadArea) {
                            uploadArea.innerHTML = '<p style="color:#d63638;font-weight:bold;">Maximum of 50 photos reached. Delete a photo to add a new one.</p>';
                        }
                    }
                } else {
                    status.style.color = '#d63638';
                    status.textContent = '❌ ' + (data.data && data.data.message ? data.data.message : 'Upload failed.');
                }
            });
    }
    function deleteBioPhoto(photoId) {
        if (!confirm('Delete this photo?')) return;
        const formData = new FormData();
        formData.append('action', 'mmgr_delete_bio_photo');
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_bio_photo'); ?>');
        formData.append('photo_id', photoId);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const el = document.getElementById('bio-photo-' + photoId);
                    if (el) el.remove();
                } else {
                    alert('❌ ' + (data.data && data.data.message ? data.data.message : 'Delete failed.'));
                }
            });
    }
    
    function previewCommunityPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('community-photo-preview');
                preview.innerHTML = '';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'max-width:150px;border-radius:8px;border:2px solid #ddd;';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '🗑️ Remove Photo';
                btn.style.cssText = 'margin-left:10px;background:#d00;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;';
                btn.addEventListener('click', removeCommunityPhoto);
                preview.appendChild(img);
                preview.appendChild(btn);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeCommunityPhoto() {
        document.getElementById('community-photo-input').value = '';
        document.getElementById('community-photo-preview').innerHTML = '';
        document.getElementById('remove-community-photo').value = '1';
    }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * Member Community Page - Forum/Discussion Board
 */

/**
 * AJAX: Upload a bio photo (up to 50 per member)
 */
add_action('wp_ajax_nopriv_mmgr_upload_bio_photo', function() { do_action('wp_ajax_mmgr_upload_bio_photo'); });
add_action('wp_ajax_mmgr_upload_bio_photo', function() {
    check_ajax_referer('mmgr_bio_photo', 'nonce');
    $member = mmgr_get_current_member();
    if (!$member) wp_send_json_error(array('message' => 'Not logged in.'));

    global $wpdb;
    $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';

    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bio_photos_tbl WHERE member_id = %d",
        $member['id']
    ));
    if ($count >= 50) {
        wp_send_json_error(array('message' => 'Maximum of 50 photos reached.'));
    }

    if (empty($_FILES['photo']['name'])) {
        wp_send_json_error(array('message' => 'No file provided.'));
    }

    // Validate MIME type is an image
    $allowed_mime_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    $file_type = wp_check_filetype($_FILES['photo']['name']);
    $detected_type = mime_content_type($_FILES['photo']['tmp_name']);
    if (!in_array($detected_type, $allowed_mime_types, true) || !in_array($file_type['type'], $allowed_mime_types, true)) {
        wp_send_json_error(array('message' => 'Only image files (JPEG, PNG, GIF, WebP) are allowed.'));
    }

    $upload = wp_handle_upload($_FILES['photo'], array('test_form' => false, 'mimes' => array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    )));
    if (isset($upload['error'])) {
        wp_send_json_error(array('message' => 'Upload failed: ' . $upload['error']));
    }

    $wpdb->insert($bio_photos_tbl, array(
        'member_id'  => $member['id'],
        'photo_url'  => $upload['url'],
        'sort_order' => $count,
        'created_at' => current_time('mysql'),
    ));
    $new_id = (int) $wpdb->insert_id;

    wp_send_json_success(array(
        'id'    => $new_id,
        'url'   => esc_url_raw($upload['url']),
        'count' => $count + 1,
    ));
});

/**
 * AJAX: Delete a bio photo (owner only)
 */
add_action('wp_ajax_nopriv_mmgr_delete_bio_photo', function() { do_action('wp_ajax_mmgr_delete_bio_photo'); });
add_action('wp_ajax_mmgr_delete_bio_photo', function() {
    check_ajax_referer('mmgr_bio_photo', 'nonce');
    $member = mmgr_get_current_member();
    if (!$member) wp_send_json_error(array('message' => 'Not logged in.'));

    global $wpdb;
    $bio_photos_tbl = $wpdb->prefix . 'membership_bio_photos';
    $photo_id = intval($_POST['photo_id']);

    // Verify the photo belongs to this member
    $photo = $wpdb->get_row($wpdb->prepare(
        "SELECT id, photo_url FROM $bio_photos_tbl WHERE id = %d AND member_id = %d",
        $photo_id, $member['id']
    ), ARRAY_A);

    if (!$photo) {
        wp_send_json_error(array('message' => 'Photo not found.'));
    }

    // Delete the file from disk
    $upload_dir = wp_upload_dir();
    $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $photo['photo_url']);
    if (file_exists($local_path)) {
        if (!unlink($local_path)) {
            error_log('MMGR: Failed to delete bio photo file: ' . $local_path);
        }
    }

    $wpdb->delete($bio_photos_tbl, array('id' => $photo_id));
    wp_send_json_success();
});

add_shortcode('mmgr_member_community', function() {
    nocache_headers();
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($member);
    
    global $wpdb;
    $posts_tbl       = $wpdb->prefix . 'membership_forum_posts';
    $topics_tbl      = $wpdb->prefix . 'membership_forum_topics';
    $post_likes_tbl     = $wpdb->prefix . 'membership_forum_post_likes';
    $topic_mods_tbl     = $wpdb->prefix . 'membership_forum_topic_mods';
    $post_hist_tbl      = $wpdb->prefix . 'membership_forum_post_history';
    $comments_tbl       = $wpdb->prefix . 'membership_forum_post_comments';
    $comment_likes_tbl  = $wpdb->prefix . 'membership_forum_comment_likes';
    
    $success = $error = '';
    
    // Get selected topic (default to first topic) - needed before post submission
    $selected_topic_id = isset($_GET['topic']) ? intval($_GET['topic']) : 0;

    // Check if current member is a moderator of the selected topic
    $is_moderator = false;
    if ($selected_topic_id > 0) {
        $is_moderator = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $topic_mods_tbl WHERE topic_id = %d AND member_id = %d",
            $selected_topic_id, $member['id']
        ));
    }

    // Handle new post submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
        if (!isset($_POST['post_nonce']) || !wp_verify_nonce($_POST['post_nonce'], 'mmgr_submit_post')) {
            $error = 'Security check failed.';
        } else {
            // Check forum ban/suspension
            $member_status = $wpdb->get_row($wpdb->prepare(
                "SELECT forum_banned, forum_suspended, forum_suspended_until FROM {$wpdb->prefix}memberships WHERE id = %d",
                $member['id']
            ), ARRAY_A);
            if (!empty($member_status['forum_banned'])) {
                $error = '⛔ You have been banned from posting in the forum. Please contact a moderator if you believe this is in error.';
            } elseif (!empty($member_status['forum_suspended']) && !empty($member_status['forum_suspended_until']) && strtotime($member_status['forum_suspended_until']) > time()) {
                $until = date_i18n('F j, Y', strtotime($member_status['forum_suspended_until']));
                $error = '⏸️ Your forum posting is suspended until ' . $until . '. Please contact a moderator if you have questions.';
            } else {
                $topic_id  = intval($_POST['topic_id']);
                $message   = sanitize_textarea_field($_POST['message']);
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
                        'topic_id'  => $topic_id,
                        'message'   => $message,
                        'photo_url' => $photo_url,
                        'posted_at' => current_time('mysql')
                    ));
                    $success = 'Post submitted successfully!';
                } elseif (empty($message)) {
                    $error = 'Please enter a message.';
                }
            }
        }
    }
    
    // Get all topics with moderator names
    $topics = $wpdb->get_results("SELECT t.* FROM $topics_tbl t WHERE t.active = 1 ORDER BY t.sort_order, t.id", ARRAY_A);
    
    if (empty($topics)) {
        $topics = array(array('id' => 0, 'topic_name' => 'General Discussion', 'description' => 'General community discussion'));
    }
    
    if ($selected_topic_id === 0 && !empty($topics)) {
        $selected_topic_id = $topics[0]['id'];
    }

    // Get selected topic info
    $selected_topic = null;
    foreach ($topics as $t) {
        if ($t['id'] == $selected_topic_id) {
            $selected_topic = $t;
            break;
        }
    }

    // Get moderators for selected topic
    $topic_moderators = array();
    if ($selected_topic_id > 0) {
        $topic_moderators = $wpdb->get_results($wpdb->prepare(
            "SELECT tm.member_id, m.name, m.community_alias FROM $topic_mods_tbl tm JOIN {$wpdb->prefix}memberships m ON tm.member_id = m.id WHERE tm.topic_id = %d",
            $selected_topic_id
        ), ARRAY_A);
        // Re-check is_moderator (for first topic auto-select case)
        foreach ($topic_moderators as $tm) {
            if ($tm['member_id'] == $member['id']) { $is_moderator = true; break; }
        }
    }

    // Get posts for selected topic with like counts
    $hidden_filter = $is_moderator ? '' : 'AND p.hidden = 0';
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, m.name as member_name, m.community_alias, m.community_photo_url as member_photo,
                m.forum_suspended, m.forum_suspended_until, m.forum_banned,
                COUNT(DISTINCT pl.id) as like_count,
                (SELECT COUNT(*) FROM $post_likes_tbl WHERE post_id = p.id AND member_id = %d) as user_liked,
                (SELECT COUNT(*) FROM $comments_tbl WHERE post_id = p.id) as comment_count
         FROM $posts_tbl p 
         LEFT JOIN {$wpdb->prefix}memberships m ON p.member_id = m.id 
         LEFT JOIN $post_likes_tbl pl ON p.id = pl.post_id
         WHERE p.topic_id = %d $hidden_filter
         GROUP BY p.id
         ORDER BY p.posted_at ASC 
         LIMIT 50",
        $member['id'],
        $selected_topic_id
    ), ARRAY_A);

    // Fetch all comments for currently visible posts (one query for all posts)
    $comments_by_post = array();
    if (!empty($posts)) {
        $post_ids     = array_map('intval', array_column($posts, 'id'));
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $all_comments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, m.name as member_name, m.community_alias,
                        COUNT(DISTINCT cl.id) as like_count,
                        SUM(CASE WHEN cl.member_id = %d THEN 1 ELSE 0 END) as user_liked
                 FROM $comments_tbl c
                 JOIN {$wpdb->prefix}memberships m ON c.member_id = m.id
                 LEFT JOIN $comment_likes_tbl cl ON c.id = cl.comment_id
                 WHERE c.post_id IN ($placeholders)
                 GROUP BY c.id
                 ORDER BY c.posted_at ASC",
                $member['id'],
                ...$post_ids
            ),
            ARRAY_A
        );
        foreach ($all_comments as $c) {
            $comments_by_post[$c['post_id']][] = $c;
        }
    }

    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('community', $member); ?>
        
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
            <?php if (!empty($topic_moderators)): ?>
                <div style="margin-top:12px;font-size:13px;color:#666;">
                    🛡️ <strong>Moderator<?php echo count($topic_moderators) > 1 ? 's' : ''; ?>:</strong>
                    <?php foreach ($topic_moderators as $i => $tm): ?>
                        <?php $mod_display = !empty($tm['community_alias']) ? mmgr_unescape_alias($tm['community_alias']) : $tm['name']; ?>
                        <?php echo esc_html($mod_display); ?><?php echo $i < count($topic_moderators) - 1 ? ', ' : ''; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                            <?php
                            $display_name = !empty($post['community_alias']) ? mmgr_unescape_alias($post['community_alias']) : $post['member_name'];
                            $is_own_post  = ($post['member_id'] == $member['id']);
                            $is_mod_target = $is_moderator && !$is_own_post; // can act on other members' posts
                            $is_hidden    = !empty($post['hidden']);
                            // Determine suspend/ban status for moderator display
                            $target_is_suspended = !empty($post['forum_suspended']) && !empty($post['forum_suspended_until']) && strtotime($post['forum_suspended_until']) > time();
                            $target_is_banned    = !empty($post['forum_banned']);
                            ?>
                            <div style="border:2px solid <?php echo ($is_moderator && $is_hidden) ? '#dc3232' : '#f0f0f0'; ?>;border-radius:8px;padding:20px;background:<?php echo ($is_moderator && $is_hidden) ? '#ffeaea' : '#fafafa'; ?>;flex-shrink:0;">
                                <!-- Post Header -->
                                <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid #e0e0e0;">
                                    <?php if (!empty($post['member_photo'])): ?>
                                        <img src="<?php echo esc_url($post['member_photo']); ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #FF2197;" alt="Photo">
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:24px;border:2px solid #ccc;flex-shrink:0;">👤</div>
                                    <?php endif; ?>
                                    
                                    <div style="flex:1;min-width:0;">
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                            <strong style="font-size:16px;color:#000;">
                                                <?php echo esc_html($display_name); ?>
                                            </strong>
                                            <?php if ($is_own_post): ?>
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

                                            <?php if ($is_mod_target && $target_is_banned): ?>
                                                <span style="background:#d00;color:white;padding:2px 8px;border-radius:10px;font-size:11px;" title="Forum banned">⛔ Forum Banned</span>
                                            <?php elseif ($is_mod_target && $target_is_suspended): ?>
                                                <?php $sus_until = date_i18n('M j, Y', strtotime($post['forum_suspended_until'])); ?>
                                                <span style="background:#ff9800;color:white;padding:2px 8px;border-radius:10px;font-size:11px;" title="Suspended until <?php echo esc_attr($sus_until); ?>">⏸️ Suspended</span>
                                            <?php endif; ?>
                                            <?php if ($is_moderator && $is_hidden): ?>
                                                <span style="background:#dc3232;color:white;padding:2px 8px;border-radius:10px;font-size:11px;">🙈 Hidden</span>
                                            <?php endif; ?>
                                        </div>                                 
                                        <br>
                                        <span style="font-size:13px;color:#666;"><?php echo date('F j, Y @ g:i A', strtotime($post['posted_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Post Message -->
                                <div id="post-message-<?php echo $post['id']; ?>" style="margin-bottom:15px;font-size:15px;line-height:1.6;color:#333;">
                                    <?php echo nl2br(esc_html($post['message'])); ?>
                                    <?php if (!empty($post['edited_at'])): ?>
                                        <span style="font-size:12px;color:#999;display:block;margin-top:4px;">✏️ Edited <?php echo date_i18n('F j, Y @ g:i A', strtotime($post['edited_at'])); ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Inline Edit Form (hidden by default, shown for post author) -->
                                <?php if ($is_own_post): ?>
                                <div id="post-edit-form-<?php echo $post['id']; ?>" style="display:none;margin-bottom:15px;">
                                    <textarea id="post-edit-text-<?php echo $post['id']; ?>" rows="4" style="width:100%;padding:10px;border:2px solid #FF2197;border-radius:6px;font-size:15px;font-family:inherit;"><?php echo esc_textarea($post['message']); ?></textarea>
                                    <div style="margin-top:8px;display:flex;gap:8px;">
                                        <button onclick="savePostEdit(<?php echo $post['id']; ?>)" style="background:#FF2197;color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:bold;">💾 Save</button>
                                        <button onclick="cancelPostEdit(<?php echo $post['id']; ?>)" style="background:#e0e0e0;color:#333;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;">✕ Cancel</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Post Photo (thumbnail → lightbox) -->
                                <?php if (!empty($post['photo_url'])): ?>
                                    <div id="post-photo-<?php echo $post['id']; ?>" style="margin-top:15px;">
                                        <img src="<?php echo esc_url($post['photo_url']); ?>"
                                             alt="Post photo"
                                             onclick="openPhotoLightbox('<?php echo esc_js($post['photo_url']); ?>')"
                                             style="max-width:150px;max-height:150px;object-fit:cover;border-radius:8px;border:2px solid #e0e0e0;cursor:pointer;">
                                        <?php if ($is_moderator): ?>
                                        <div style="margin-top:6px;">
                                            <button onclick="removePostPhoto(<?php echo $post['id']; ?>)"
                                                    style="background:white;color:#d00;border:2px solid #d00;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;">
                                                🗑 Remove Photo
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Like Button (+ Edit button for own posts + Moderator actions) -->
                                <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e0e0e0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                    <button onclick="togglePostLike(<?php echo $post['id']; ?>, this)" 
                                            class="mmgr-post-like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                            style="background:<?php echo $post['user_liked'] ? '#FF2197' : 'white'; ?>;color:<?php echo $post['user_liked'] ? 'white' : '#FF2197'; ?>;border:2px solid #FF2197;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:14px;transition:all 0.3s;">
                                        ❤️ Like (<?php echo intval($post['like_count']); ?>)
                                    </button>
                                    <?php if ($is_own_post): ?>
                                    <button onclick="togglePostEditForm(<?php echo $post['id']; ?>)" style="background:white;color:#666;border:2px solid #ccc;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;">
                                        ✏️ Edit
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($is_moderator && !empty($post['edited_at'])): ?>
                                    <button onclick="viewPostHistory(<?php echo $post['id']; ?>)" style="background:white;color:#0073aa;border:2px solid #0073aa;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;">
                                        📋 Edit History
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($is_moderator): ?>
                                    <button onclick="toggleHidePost(<?php echo $post['id']; ?>, <?php echo $is_hidden ? 'true' : 'false'; ?>)"
                                            style="background:white;color:<?php echo $is_hidden ? '#00a32a' : '#555'; ?>;border:2px solid <?php echo $is_hidden ? '#00a32a' : '#ccc'; ?>;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;">
                                        <?php echo $is_hidden ? '👁 Unhide Post' : '🙈 Hide Post'; ?>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($is_mod_target): ?>
                                    <button onclick="forumSuspendMember(<?php echo $post['member_id']; ?>, '<?php echo esc_attr($display_name); ?>', <?php echo $target_is_suspended ? 'true' : 'false'; ?>)" 
                                            style="background:white;color:#ff9800;border:2px solid #ff9800;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;">
                                        <?php echo $target_is_suspended ? '▶ Unsuspend' : '⏸️ Suspend (30d)'; ?>
                                    </button>
                                    <button onclick="forumBanMember(<?php echo $post['member_id']; ?>, '<?php echo esc_attr($display_name); ?>', <?php echo $target_is_banned ? 'true' : 'false'; ?>)" 
                                            style="background:white;color:#d00;border:2px solid #d00;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;">
                                        <?php echo $target_is_banned ? '✓ Unban' : '⛔ Forum Ban'; ?>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (!$is_own_post): ?>
                                    <button onclick="reportForumPost(<?php echo $post['id']; ?>, '<?php echo esc_js($display_name); ?>')"
                                            style="background:white;color:#999;border:2px solid #ddd;padding:8px 14px;border-radius:6px;cursor:pointer;font-size:13px;margin-left:auto;">
                                        🚩 Report
                                    </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Comments Section -->
                                <?php
                                $post_comments = isset($comments_by_post[$post['id']]) ? $comments_by_post[$post['id']] : array();
                                $comment_count_val = intval($post['comment_count']);
                                ?>
                                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e0e0e0;">
                                    <button onclick="toggleComments(<?php echo $post['id']; ?>)" style="background:white;color:#0073aa;border:2px solid #0073aa;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;">
                                        💬 Comments (<span id="comment-count-<?php echo $post['id']; ?>"><?php echo $comment_count_val; ?></span>)
                                    </button>

                                    <div id="comments-section-<?php echo $post['id']; ?>" style="display:none;margin-top:12px;">
                                        <div id="comments-list-<?php echo $post['id']; ?>" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
                                            <?php if (empty($post_comments)): ?>
                                                <p id="no-comments-<?php echo $post['id']; ?>" style="color:#999;font-size:13px;margin:0;">No comments yet. Be the first!</p>
                                            <?php else: ?>
                                                <?php foreach ($post_comments as $cmt): ?>
                                                    <?php $cmt_author = !empty($cmt['community_alias']) ? mmgr_unescape_alias($cmt['community_alias']) : $cmt['member_name']; ?>
                                                    <div style="background:#f0f0f0;border-left:3px solid #FF2197;padding:10px 12px;border-radius:0 6px 6px 0;font-size:14px;">
                                                        <strong><?php echo esc_html($cmt_author); ?></strong>
                                                        <span style="color:#999;font-size:12px;margin-left:8px;"><?php echo date_i18n('M j, Y @ g:i A', strtotime($cmt['posted_at'])); ?></span>
                                                        <div style="margin-top:4px;color:#333;white-space:pre-wrap;"><?php echo esc_html($cmt['comment']); ?></div>
                                                        <div style="margin-top:6px;">
                                                            <button onclick="toggleCommentLike(<?php echo intval($cmt['id']); ?>, this)"
                                                                    class="mmgr-comment-like-btn <?php echo !empty($cmt['user_liked']) ? 'liked' : ''; ?>"
                                                                    style="background:<?php echo !empty($cmt['user_liked']) ? '#FF2197' : 'white'; ?>;color:<?php echo !empty($cmt['user_liked']) ? 'white' : '#FF2197'; ?>;border:2px solid #FF2197;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;">
                                                                ❤️ Like (<?php echo intval($cmt['like_count']); ?>)
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex;gap:8px;align-items:flex-start;">
                                            <textarea id="comment-input-<?php echo $post['id']; ?>" placeholder="Write a comment..." rows="2"
                                                      style="flex:1;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:14px;font-family:inherit;resize:vertical;min-width:0;"></textarea>
                                            <button onclick="submitComment(<?php echo $post['id']; ?>)"
                                                    style="background:#FF2197;color:white;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:14px;white-space:nowrap;flex-shrink:0;">
                                                Post
                                            </button>
                                        </div>
                                    </div>
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
    
    // Like Comment
    function toggleCommentLike(commentId, button) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=mmgr_toggle_comment_like&comment_id=' + commentId + '&nonce=<?php echo wp_create_nonce('mmgr_toggle_comment_like'); ?>'
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

    // Edit Post
    function togglePostEditForm(postId) {
        const msgDiv  = document.getElementById('post-message-' + postId);
        const editDiv = document.getElementById('post-edit-form-' + postId);
        if (!editDiv) return;
        const isHidden = editDiv.style.display === 'none';
        editDiv.style.display = isHidden ? 'block' : 'none';
        msgDiv.style.display  = isHidden ? 'none'  : 'block';
    }

    function cancelPostEdit(postId) {
        togglePostEditForm(postId);
    }

    function savePostEdit(postId) {
        const textarea = document.getElementById('post-edit-text-' + postId);
        const message  = textarea ? textarea.value.trim() : '';
        if (!message) { alert('❌ Message cannot be empty.'); return; }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_edit_forum_post&post_id=' + postId
                + '&message=' + encodeURIComponent(message)
                + '&nonce=<?php echo wp_create_nonce('mmgr_edit_forum_post'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const msgDiv = document.getElementById('post-message-' + postId);
                // Safely escape each line then join with <br> (mirrors PHP nl2br + esc_html)
                const lines = data.data.message.split('\n');
                let html = lines.map(l => {
                    const d = document.createElement('div');
                    d.textContent = l;
                    return d.innerHTML;
                }).join('<br>');
                // Append the edited timestamp returned from the server
                html += '<span style="font-size:12px;color:#999;display:block;margin-top:4px;">'
                     + data.data.edited_label + '</span>';
                msgDiv.innerHTML = html;
                // Also update the textarea so Cancel shows the saved text
                if (textarea) textarea.value = data.data.message;
                togglePostEditForm(postId);
            } else {
                alert('❌ ' + (data.data && data.data.message ? data.data.message : 'Error saving post.'));
            }
        });
    }

    // Moderator: Suspend member from forum (30 days) or unsuspend
    function forumSuspendMember(memberId, memberName, isSuspended) {
        if (isSuspended) {
            if (!confirm('Lift suspension for ' + memberName + '?')) return;
            doForumModAction('mmgr_forum_unsuspend_member', memberId, '');
        } else {
            const reason = prompt('Suspend ' + memberName + ' from the forum for 30 days.\nEnter reason (visible to moderators only):', '');
            if (reason === null) return;
            doForumModAction('mmgr_forum_suspend_member', memberId, reason);
        }
    }

    // Moderator: Ban member from forum or unban
    function forumBanMember(memberId, memberName, isBanned) {
        if (isBanned) {
            if (!confirm('Lift forum ban for ' + memberName + '?')) return;
            doForumModAction('mmgr_forum_unban_member', memberId, '');
        } else {
            const reason = prompt('Permanently ban ' + memberName + ' from posting in the forum.\nEnter reason (visible to moderators only):', '');
            if (reason === null) return;
            doForumModAction('mmgr_forum_ban_member', memberId, reason);
        }
    }

    function doForumModAction(action, memberId, reason) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=' + action + '&member_id=' + memberId
                + '&reason=' + encodeURIComponent(reason)
                + '&topic_id=<?php echo $selected_topic_id; ?>'
                + '&nonce=<?php echo wp_create_nonce('mmgr_forum_mod_action'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.data.message);
                location.reload();
            } else {
                alert('❌ ' + (data.data ? data.data.message : 'Action failed.'));
            }
        });
    }

    // Moderator: View edit history of a post
    function viewPostHistory(postId) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_forum_post_history&post_id=' + postId
                + '&nonce=<?php echo wp_create_nonce('mmgr_forum_mod_action'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let historyText = '📋 Edit History for Post #' + postId + ':\n\n';
                if (data.data.history.length === 0) {
                    historyText += '(No previous versions found)';
                } else {
                    data.data.history.forEach((h, i) => {
                        historyText += 'Version ' + (i + 1) + ' (saved ' + h.saved_at + '):\n' + h.old_message + '\n\n';
                    });
                }
                alert(historyText);
            } else {
                alert('❌ ' + (data.data ? data.data.message : 'Failed to load history.'));
            }
        });
    }

    // Moderator: Toggle hide/unhide a post
    function toggleHidePost(postId, isHidden) {
        if (!confirm((isHidden ? 'Unhide' : 'Hide') + ' this post?')) return;
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_forum_hide_post&post_id=' + postId
                + '&hidden=' + (isHidden ? 0 : 1)
                + '&topic_id=<?php echo $selected_topic_id; ?>'
                + '&nonce=<?php echo wp_create_nonce('mmgr_forum_mod_action'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.data.message);
                location.reload();
            } else {
                alert('❌ ' + (data.data ? data.data.message : 'Action failed.'));
            }
        });
    }

    // Toggle comments section visibility
    function toggleComments(postId) {
        const section = document.getElementById('comments-section-' + postId);
        if (section) {
            section.style.display = section.style.display === 'none' ? 'block' : 'none';
        }
    }

    // Submit a comment on a post via AJAX
    function submitComment(postId) {
        const textarea = document.getElementById('comment-input-' + postId);
        const comment = textarea ? textarea.value.trim() : '';
        if (!comment) { alert('❌ Please enter a comment.'); return; }
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_add_post_comment&post_id=' + postId
                + '&comment=' + encodeURIComponent(comment)
                + '&nonce=<?php echo wp_create_nonce('mmgr_add_post_comment'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('comments-list-' + postId);
                const noMsg = document.getElementById('no-comments-' + postId);
                if (noMsg) noMsg.remove();
                const div = document.createElement('div');
                div.style.cssText = 'background:#f0f0f0;border-left:3px solid #FF2197;padding:10px 12px;border-radius:0 6px 6px 0;font-size:14px;';
                const strong = document.createElement('strong');
                strong.textContent = data.data.author;
                const timeSpan = document.createElement('span');
                timeSpan.style.cssText = 'color:#999;font-size:12px;margin-left:8px;';
                timeSpan.textContent = data.data.posted_at;
                const bodyDiv = document.createElement('div');
                bodyDiv.style.cssText = 'margin-top:4px;color:#333;white-space:pre-wrap;';
                bodyDiv.textContent = data.data.comment;
                const likeDiv = document.createElement('div');
                likeDiv.style.cssText = 'margin-top:6px;';
                const likeBtn = document.createElement('button');
                likeBtn.className = 'mmgr-comment-like-btn';
                likeBtn.style.cssText = 'background:white;color:#FF2197;border:2px solid #FF2197;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;';
                likeBtn.textContent = '❤️ Like (0)';
                likeBtn.setAttribute('onclick', 'toggleCommentLike(' + data.data.comment_id + ', this)');
                likeDiv.appendChild(likeBtn);
                div.appendChild(strong);
                div.appendChild(timeSpan);
                div.appendChild(bodyDiv);
                div.appendChild(likeDiv);
                list.appendChild(div);
                const countSpan = document.getElementById('comment-count-' + postId);
                if (countSpan) countSpan.textContent = parseInt(countSpan.textContent || '0') + 1;
                if (textarea) textarea.value = '';
            } else {
                alert('❌ ' + (data.data && data.data.message ? data.data.message : 'Error posting comment.'));
            }
        });
    }

    // Moderator: Remove photo from a post
    function removePostPhoto(postId) {
        if (!confirm('Remove this photo from the post?')) return;
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_forum_remove_photo&post_id=' + postId
                + '&topic_id=<?php echo $selected_topic_id; ?>'
                + '&nonce=<?php echo wp_create_nonce('mmgr_forum_mod_action'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const photoDiv = document.getElementById('post-photo-' + postId);
                if (photoDiv) photoDiv.remove();
            } else {
                alert('❌ ' + (data.data ? data.data.message : 'Failed to remove photo.'));
            }
        });
    }

    // Photo lightbox
    function openPhotoLightbox(url) {
        const lb = document.getElementById('mmgr-photo-lightbox');
        const img = document.getElementById('mmgr-lightbox-img');
        img.src = url;
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closePhotoLightbox() {
        const lb = document.getElementById('mmgr-photo-lightbox');
        lb.style.display = 'none';
        document.getElementById('mmgr-lightbox-img').src = '';
        document.body.style.overflow = '';
    }

    // Report a forum post
    function reportForumPost(postId, memberName) {
        const reason = prompt('Why are you reporting this post by ' + memberName + '?\n(Your report will be reviewed by an admin.)');
        if (!reason || reason.trim() === '') return;
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mmgr_report_forum_post&post_id=' + postId
                + '&reason=' + encodeURIComponent(reason.trim())
                + '&nonce=<?php echo wp_create_nonce('mmgr_report_forum_post'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Report submitted. Thank you — an admin will review it.');
            } else {
                alert('❌ ' + (data.data ? data.data.message : 'Could not submit report.'));
            }
        });
    }
</script>

<!-- Photo Lightbox Overlay -->
<div id="mmgr-photo-lightbox"
     onclick="if(event.target===this)closePhotoLightbox();"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;align-items:center;justify-content:center;">
    <div style="position:relative;max-width:90vw;max-height:90vh;">
        <button onclick="closePhotoLightbox()"
                style="position:absolute;top:-14px;right:-14px;width:30px;height:30px;border-radius:50%;background:#FF2197;color:white;border:none;font-size:18px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:1;">✕</button>
        <img id="mmgr-lightbox-img" src="" alt="Full size photo"
             style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px;display:block;">
    </div>
</div>
    <?php
    return ob_get_clean();
});

/**
 * Member Messages Page
 */
add_shortcode('mmgr_member_messages', function() {
    nocache_headers();
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($member);
    
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
                    wp_redirect(esc_url_raw(add_query_arg(array('chat' => $to_member_id, 'usercod' => $member['member_code']), home_url('/member-messages/'))));
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
<?php echo mmgr_get_portal_navigation('messages', $member); ?>

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
								$display_name = !empty($other_member['community_alias']) ? mmgr_unescape_alias($other_member['community_alias']) : $other_member['name'];
								echo esc_html($display_name); 
								?>
							</h3>
						</div>
                        
                        <?php if ($other_member['id'] != 0): ?>
                            <div class="dropdown" style="position:relative;">
                                <button onclick="toggleDropdown()" style="background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 12px;border-radius:6px;cursor:pointer;">
                                    ⋮
                                </button>
                                <div id="chat-dropdown" style="display:none;position:absolute;right:0;top:100%;background:white;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.2);min-width:180px;margin-top:5px;z-index:100;">
                                    <?php
                                    $chat_friend_status = mmgr_get_friendship_status((int)$member['id'], (int)$other_member['id']);
                                    $chat_friend_nonce_req = wp_create_nonce('mmgr_friend_request');
                                    $chat_friend_nonce_res = wp_create_nonce('mmgr_friend_respond');
                                    $chat_friend_nonce_unf = wp_create_nonce('mmgr_unfriend');
                                    switch ($chat_friend_status) {
                                        case 'none':
                                            $cff_label  = '🤝 Add Friend';
                                            $cff_action = 'request';
                                            break;
                                        case 'pending_sent':
                                            $cff_label  = '⏳ Cancel Request';
                                            $cff_action = 'cancel';
                                            break;
                                        case 'pending_received':
                                            $cff_label  = '✅ Accept Friend';
                                            $cff_action = 'accept';
                                            break;
                                        case 'accepted':
                                            $cff_label  = '👥 Unfriend';
                                            $cff_action = 'unfriend';
                                            break;
                                        default:
                                            $cff_label  = '';
                                            $cff_action = '';
                                    }
                                    if ($cff_label):
                                    ?>
                                    <button id="chat-friend-btn"
                                            onclick="chatFriendAction(<?php echo intval($other_member['id']); ?>,'<?php echo esc_attr($cff_action); ?>')"
                                            data-status="<?php echo esc_attr($chat_friend_status); ?>"
                                            data-nonce-request="<?php echo esc_attr($chat_friend_nonce_req); ?>"
                                            data-nonce-respond="<?php echo esc_attr($chat_friend_nonce_res); ?>"
                                            data-nonce-unfriend="<?php echo esc_attr($chat_friend_nonce_unf); ?>"
                                            style="width:100%;padding:12px;border:none;background:none;text-align:left;cursor:pointer;color:#0073aa;">
                                        <?php echo esc_html($cff_label); ?>
                                    </button>
                                    <?php endif; ?>
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
                        <p>Choose a contact to start messaging - you can add contacts from the Community and Directory pages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
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
                window.location.href = '<?php echo home_url('/member-messages/'); ?>?usercod=<?php echo rawurlencode($member['member_code']); ?>';
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
                window.location.href = '<?php echo home_url('/member-messages/'); ?>?usercod=<?php echo rawurlencode($member['member_code']); ?>';
            } else {
                alert('✕ ' + d.data.message);
            }
        });
    }
    
    function showAddContact() {
        alert('Contact management coming soon!');
    }

    /**
     * Friend action handler for the messages page.
     */
    function chatFriendAction(memberId, actionType) {
        const btn = document.getElementById('chat-friend-btn');
        if (!btn) return;
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '…';

        let ajaxAction, nonce, body;

        if (actionType === 'request') {
            ajaxAction = 'mmgr_friend_request';
            nonce = btn.dataset.nonceRequest;
            body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&nonce=' + encodeURIComponent(nonce);
        } else if (actionType === 'cancel' || actionType === 'unfriend') {
            ajaxAction = 'mmgr_unfriend';
            nonce = btn.dataset.nonceUnfriend;
            body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&nonce=' + encodeURIComponent(nonce);
        } else if (actionType === 'accept') {
            ajaxAction = 'mmgr_friend_respond';
            nonce = btn.dataset.nonceRespond;
            body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&action_type=accept&nonce=' + encodeURIComponent(nonce);
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                const newStatus = data.data.status;
                if (newStatus === 'pending_sent') {
                    btn.textContent = '⏳ Cancel Request';
                    btn.setAttribute('onclick', 'chatFriendAction(' + memberId + ",'cancel')");
                } else if (newStatus === 'accepted') {
                    btn.textContent = '👥 Unfriend';
                    btn.setAttribute('onclick', 'chatFriendAction(' + memberId + ",'unfriend')");
                } else {
                    btn.textContent = '🤝 Add Friend';
                    btn.setAttribute('onclick', 'chatFriendAction(' + memberId + ",'request')");
                }
            } else {
                btn.textContent = origText;
                alert('❌ ' + (data.data.message || data.data));
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = origText;
            alert('❌ Connection error');
        });
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
add_action('wp_ajax_nopriv_mmgr_send_pm', function() { do_action('wp_ajax_mmgr_send_pm'); });
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
        wp_send_json_error('Do not try to block yourself dumb dumb!');
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
    nocache_headers();
    // Check if member is logged in
    $member = mmgr_get_current_member();
    
    if (!$member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($member);
    
    global $wpdb;

    // Record that this member visited the website today (throttled: update at most once every 5 minutes)
    $last_visited = $member['last_visited'];
    if (empty($last_visited) || (strtotime(current_time('mysql')) - strtotime($last_visited)) > 300) {
        $wpdb->update(
            $wpdb->prefix . 'memberships',
            array('last_visited' => current_time('mysql')),
            array('id' => $member['id'])
        );
    }

    // Get all members with aliases (including the current user, excluding banned members)
    $members = $wpdb->get_results(
        "SELECT id, name, community_alias, community_photo_url 
         FROM {$wpdb->prefix}memberships 
         WHERE community_alias IS NOT NULL AND community_alias != '' AND banned = 0
         ORDER BY community_alias ASC",
        ARRAY_A
    );

    // Get members who visited the website today and have a community alias
    $today_start = date('Y-m-d 00:00:00');
    $today_end   = date('Y-m-d 23:59:59');
    $online_members = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT id, community_alias, community_photo_url
         FROM {$wpdb->prefix}memberships
         WHERE last_visited BETWEEN %s AND %s
           AND community_alias IS NOT NULL AND community_alias != ''
           AND banned = 0
         ORDER BY community_alias ASC",
        $today_start, $today_end
    ), ARRAY_A);
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('directory', $member); ?>
        
        <!-- Welcome -->
        <div class="mmgr-portal-titlecc">
            <h1>Members Directory 👥</h1>
        </div>

        <!-- Who's Online Today -->
        <div class="mmgr-portal-card" id="mmgr-who-online-card">
            <h3 style="color:var(--portal-primary);margin-bottom:14px;">
                🟢 Who's Online Today
                <span id="mmgr-online-count" style="font-size:13px;font-weight:normal;color:#666;margin-left:8px;">(<?php echo count($online_members); ?>)</span>
            </h3>
            <div id="mmgr-online-list">
            <?php if (empty($online_members)): ?>
                <p style="color:#888;font-size:14px;">No members have checked in today yet.</p>
            <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                <?php foreach ($online_members as $om): ?>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;" onclick="viewCommunityProfile(<?php echo intval($om['id']); ?>)">
                        <?php if (!empty($om['community_photo_url'])): ?>
                            <img src="<?php echo esc_url($om['community_photo_url']); ?>"
                                 alt="<?php echo esc_attr(mmgr_unescape_alias($om['community_alias'])); ?>"
                                 style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:3px solid #28a745;">
                        <?php else: ?>
                            <div style="width:54px;height:54px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:26px;border:3px solid #28a745;">👤</div>
                        <?php endif; ?>
                        <span style="font-size:12px;color:#333;max-width:64px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html(mmgr_unescape_alias($om['community_alias'])); ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
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
                            // Friendship status (skip self)
                            $dir_friend_status = ($m['id'] === $member['id']) ? 'self' : mmgr_get_friendship_status((int)$member['id'], (int)$m['id']);
                        ?>
                            <tr>
                                <!-- Photo -->
                                <td>
                                    <?php if (!empty($m['community_photo_url'])): ?>
                                        <img src="<?php echo esc_url($m['community_photo_url']); ?>" 
                                             class="mmgr-directory-photo"
                                             onclick="viewCommunityProfile(<?php echo $m['id']; ?>)"
                                             alt="<?php echo esc_attr(mmgr_unescape_alias($m['community_alias'])); ?>">
                                    <?php else: ?>
                                        <div style="width:60px;height:60px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:30px;border:3px solid #ccc;cursor:pointer;" 
                                             onclick="viewCommunityProfile(<?php echo $m['id']; ?>)">
                                            👤
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Alias + Actions -->
                                <td>
                                    <div class="mmgr-directory-alias" onclick="viewCommunityProfile(<?php echo $m['id']; ?>)">
                                        <?php echo esc_html(mmgr_unescape_alias($m['community_alias'])); ?>
                                    </div>
                                    <?php $award_html = mmgr_render_member_award_badges($m['id']); if ($award_html): ?>
                                        <div class="mmgr-directory-awards" style="margin:4px 0;"><?php echo $award_html; ?></div>
                                    <?php endif; ?>
                                    <div class="mmgr-directory-actions">
                                        <!-- Message Button -->
                                        <button onclick="openPMModalDynamic(<?php echo $m['id']; ?>)" 
                                                class="mmgr-directory-btn mmgr-directory-btn-message"
                                                title="Send Message">
                                            ✉️
                                        </button>
                                        
                                        <!-- Like Button -->
                                        <button onclick="toggleLike(<?php echo $m['id']; ?>, this)" 
                                                class="mmgr-directory-btn mmgr-directory-btn-like <?php echo $is_liked ? 'liked' : ''; ?>"
                                                title="<?php echo $is_liked ? 'Click to UNLOVE' : 'Click if you Love this?'; ?>">
                                            <?php echo $is_liked ? '❤' : 'LOVE?'; ?>
                                        </button>

                                        <!-- Friend Button (hidden for self) -->
                                        <?php if ($dir_friend_status !== 'self'):
                                            $dir_fb_id   = 'dir-friend-' . $m['id'];
                                            $dir_fb_nonce_req = wp_create_nonce('mmgr_friend_request');
                                            $dir_fb_nonce_res = wp_create_nonce('mmgr_friend_respond');
                                            $dir_fb_nonce_unf = wp_create_nonce('mmgr_unfriend');
                                            switch ($dir_friend_status) {
                                                case 'accepted':
                                                    $dir_fb_label   = '👥';
                                                    $dir_fb_title   = 'Friends – click to unfriend';
                                                    $dir_fb_action  = 'unfriend';
                                                    $dir_fb_class   = 'mmgr-directory-btn-friend accepted';
                                                    break;
                                                case 'pending_sent':
                                                    $dir_fb_label   = '⏳';
                                                    $dir_fb_title   = 'Request sent – click to cancel';
                                                    $dir_fb_action  = 'cancel';
                                                    $dir_fb_class   = 'mmgr-directory-btn-friend pending';
                                                    break;
                                                case 'pending_received':
                                                    $dir_fb_label   = '✅';
                                                    $dir_fb_title   = 'Accept friend request';
                                                    $dir_fb_action  = 'accept';
                                                    $dir_fb_class   = 'mmgr-directory-btn-friend incoming';
                                                    break;
                                                default:
                                                    $dir_fb_label   = '🤝';
                                                    $dir_fb_title   = 'Add Friend';
                                                    $dir_fb_action  = 'request';
                                                    $dir_fb_class   = 'mmgr-directory-btn-friend';
                                                    break;
                                            }
                                        ?>
                                        <button id="<?php echo esc_attr($dir_fb_id); ?>"
                                                onclick="dirFriendAction(<?php echo $m['id']; ?>,'<?php echo esc_attr($dir_fb_id); ?>','<?php echo esc_attr($dir_fb_action); ?>')"
                                                class="mmgr-directory-btn <?php echo esc_attr($dir_fb_class); ?>"
                                                data-nonce-request="<?php echo esc_attr($dir_fb_nonce_req); ?>"
                                                data-nonce-respond="<?php echo esc_attr($dir_fb_nonce_res); ?>"
                                                data-nonce-unfriend="<?php echo esc_attr($dir_fb_nonce_unf); ?>"
                                                title="<?php echo esc_attr($dir_fb_title); ?>">
                                            <?php echo $dir_fb_label; ?>
                                        </button>
                                        <?php endif; ?>
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
        /**
         * Remove backslash escape sequences from a community alias string.
         * Aliases stored in the database may contain unnecessary backslashes
         * (e.g. "we\'re"). This ensures they render cleanly (e.g. "we're").
         *
         * @param {string} str - The alias string to unescape.
         * @returns {string} The unescaped alias string.
         */
        function mmgrUnescapeAlias(str) {
            return String(str).replace(/\\([\\'"])/g, '$1');
        }

        function viewCommunityProfile(memberId) {
            window.location.href = '<?php echo home_url('/member-community-profile/'); ?>?id=' + encodeURIComponent(memberId) + '&usercod=<?php echo rawurlencode($member['member_code']); ?>';
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
                    const memberName = mmgrUnescapeAlias(d.data.alias);
                    const message = prompt('Send message to ' + memberName + ':', '');
                    if (message !== null && message.trim() !== '') {
                        sendPrivateMessage(memberId, message);
                    }
                }
            });
        }

        function sendPrivateMessage(memberId, message) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mmgr_send_pm&recipient_id=' + memberId + '&message=' + encodeURIComponent(message) + '&nonce=<?php echo wp_create_nonce('mmgr_send_pm'); ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('✅ ' + d.data.message);
                } else {
                    alert('❌ ' + d.data);
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
                        button.textContent = '❤';
                    } else {
                        button.classList.remove('liked');
                        button.textContent = 'LOVE?';
                    }
                } else {
                    alert('❌ ' + d.data.message);
                }
            });
        }

        // Who's Online auto-refresh (every 2 minutes)
        var MMGR_ONLINE_REFRESH_MS = 120000;

        function mmgrBuildOnlineCard(m) {
            var wrapper = document.createElement('div');
            wrapper.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;';
            wrapper.addEventListener('click', function() { viewCommunityProfile(m.id); });

            if (m.photo) {
                var img = document.createElement('img');
                img.src = m.photo;
                img.alt = mmgrUnescapeAlias(m.alias);
                img.style.cssText = 'width:54px;height:54px;border-radius:50%;object-fit:cover;border:3px solid #28a745;';
                wrapper.appendChild(img);
            } else {
                var avatar = document.createElement('div');
                avatar.style.cssText = 'width:54px;height:54px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:26px;border:3px solid #28a745;';
                avatar.textContent = '👤';
                wrapper.appendChild(avatar);
            }

            var label = document.createElement('span');
            label.style.cssText = 'font-size:12px;color:#333;max-width:64px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
            label.textContent = mmgrUnescapeAlias(m.alias);
            wrapper.appendChild(label);

            return wrapper;
        }

        function mmgrRefreshOnline() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=mmgr_who_is_online&nonce=<?php echo wp_create_nonce('mmgr_who_is_online'); ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                var list = document.getElementById('mmgr-online-list');
                var countEl = document.getElementById('mmgr-online-count');
                if (!list) return;
                countEl.textContent = '(' + d.data.count + ')';
                list.innerHTML = '';
                if (d.data.count === 0) {
                    var msg = document.createElement('p');
                    msg.style.cssText = 'color:#888;font-size:14px;';
                    msg.textContent = 'No members have checked in today yet.';
                    list.appendChild(msg);
                    return;
                }
                var grid = document.createElement('div');
                grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:12px;';
                d.data.members.forEach(function(m) {
                    grid.appendChild(mmgrBuildOnlineCard(m));
                });
                list.appendChild(grid);
            });
        }
        setInterval(mmgrRefreshOnline, MMGR_ONLINE_REFRESH_MS);

        /**
         * Directory page friend action handler.
         */
        function dirFriendAction(memberId, btnId, actionType) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            const origText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '…';

            let ajaxAction, nonce, body;

            if (actionType === 'request') {
                ajaxAction = 'mmgr_friend_request';
                nonce = btn.dataset.nonceRequest;
                body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&nonce=' + encodeURIComponent(nonce);
            } else if (actionType === 'cancel' || actionType === 'unfriend') {
                ajaxAction = 'mmgr_unfriend';
                nonce = btn.dataset.nonceUnfriend;
                body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&nonce=' + encodeURIComponent(nonce);
            } else if (actionType === 'accept') {
                ajaxAction = 'mmgr_friend_respond';
                nonce = btn.dataset.nonceRespond;
                body = 'action=' + ajaxAction + '&profile_id=' + memberId + '&action_type=accept&nonce=' + encodeURIComponent(nonce);
            }

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const newStatus = data.data.status;
                    if (newStatus === 'pending_sent') {
                        btn.textContent = '⏳';
                        btn.title = 'Request sent – click to cancel';
                        btn.classList.remove('accepted', 'incoming');
                        btn.classList.add('pending');
                        btn.setAttribute('onclick', "dirFriendAction(" + memberId + ",'" + btnId + "','cancel')");
                    } else if (newStatus === 'accepted') {
                        btn.textContent = '👥';
                        btn.title = 'Friends – click to unfriend';
                        btn.classList.remove('pending', 'incoming');
                        btn.classList.add('accepted');
                        btn.setAttribute('onclick', "dirFriendAction(" + memberId + ",'" + btnId + "','unfriend')");
                    } else {
                        btn.textContent = '🤝';
                        btn.title = 'Add Friend';
                        btn.classList.remove('pending', 'accepted', 'incoming');
                        btn.setAttribute('onclick', "dirFriendAction(" + memberId + ",'" + btnId + "','request')");
                    }
                } else {
                    btn.textContent = origText;
                    alert('❌ ' + (data.data.message || data.data));
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = origText;
                alert('❌ Connection error');
            });
        }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * Toggle Like via AJAX
 */
add_action('wp_ajax_nopriv_mmgr_toggle_like', function() { do_action('wp_ajax_mmgr_toggle_like'); });
add_action('wp_ajax_mmgr_toggle_like', function() {
    check_ajax_referer('mmgr_toggle_like', 'nonce');
    
    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $liked_member_id = intval($_POST['member_id']);
    
    if ($liked_member_id == $member['id']) {
        wp_send_json_error(array('message' => 'We know you love yourself already!'));
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
 * AJAX: Who is Online Today
 */
add_action('wp_ajax_nopriv_mmgr_who_is_online', function() { do_action('wp_ajax_mmgr_who_is_online'); });
add_action('wp_ajax_mmgr_who_is_online', function() {
    check_ajax_referer('mmgr_who_is_online', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }

    global $wpdb;
    $today_start = date('Y-m-d 00:00:00');
    $today_end   = date('Y-m-d 23:59:59');

    $online = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT id, community_alias, community_photo_url
         FROM {$wpdb->prefix}memberships
         WHERE last_visited BETWEEN %s AND %s
           AND community_alias IS NOT NULL AND community_alias != ''
           AND banned = 0
         ORDER BY community_alias ASC",
        $today_start, $today_end
    ), ARRAY_A);

    $result = array_map(function($m) {
        return [
            'id'    => intval($m['id']),
            'alias' => esc_html(mmgr_unescape_alias($m['community_alias'])),
            'photo' => !empty($m['community_photo_url']) ? esc_url($m['community_photo_url']) : '',
        ];
    }, $online);

    wp_send_json_success(['members' => $result, 'count' => count($result)]);
});

/**
 * Community Profile Page - Shows member activity and stats
 */
add_shortcode('mmgr_member_community_profile', function() {
    nocache_headers();
    // Check if member is logged in
    $current_member = mmgr_get_current_member();
    
    if (!$current_member) {
        wp_redirect(home_url('/member-login/'));
        exit;
    }

    mmgr_enforce_usercod($current_member);
    
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
    $friendship_status = 'self';
    $mutual_friends = [];
    if ($profile_member_id !== (int) $current_member['id']) {
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

        // Friendship status
        $friendship_status = mmgr_get_friendship_status( (int) $current_member['id'], $profile_member_id );
        $mutual_friends    = mmgr_get_mutual_friends( (int) $current_member['id'], $profile_member_id );
    }

    // Get total likes this profile has received (all sources)
    $total_likes =
        (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}membership_likes WHERE liked_member_id = %d",
            $profile_member_id
        )) +
        (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}membership_bio_photo_likes bpl
             JOIN {$wpdb->prefix}membership_bio_photos bp ON bpl.photo_id = bp.id
             WHERE bp.member_id = %d",
            $profile_member_id
        )) +
        (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}membership_forum_post_likes fpl
             JOIN {$wpdb->prefix}membership_forum_posts fp ON fpl.post_id = fp.id
             WHERE fp.member_id = %d",
            $profile_member_id
        ));
    
    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('directory', $current_member); ?>
        
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
            
            <h1 style="margin:0 0 10px 0;"><?php echo esc_html(mmgr_unescape_alias($profile_member['community_alias'])); ?></h1>

            <!-- Community Award Badges -->
            <?php $profile_awards = mmgr_render_member_award_badges($profile_member_id, true); if ($profile_awards): ?>
                <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:4px;margin-bottom:12px;">
                    <?php echo $profile_awards; ?>
                </div>
            <?php endif; ?>

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
                    <button onclick="openPMModal(<?php echo $profile_member_id; ?>, '<?php echo esc_attr(mmgr_unescape_alias($profile_member['community_alias'])); ?>')" 
                            style="background:#FF2197;color:white;padding:12px 30px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        ✉️ Send Message
                    </button>

                    <button id="like-btn" onclick="toggleProfileLike(<?php echo $profile_member_id; ?>, this)"
                            style="background:<?php echo $is_liked ? '#FF2197' : 'white'; ?>;color:<?php echo $is_liked ? 'white' : '#FF2197'; ?>;border:2px solid #FF2197;padding:12px 30px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        ❤️ <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
                    </button>

                    <!-- Friend Button -->
                    <?php
                    $friend_btn_id    = 'friend-btn-' . $profile_member_id;
                    $friend_nonce_req = wp_create_nonce('mmgr_friend_request');
                    $friend_nonce_res = wp_create_nonce('mmgr_friend_respond');
                    $friend_nonce_unf = wp_create_nonce('mmgr_unfriend');
                    switch ($friendship_status) {
                        case 'none':
                            $fb_label = '🤝 Add Friend';
                            $fb_bg    = '#0073aa'; $fb_color = 'white';
                            $fb_onclick = "friendAction({$profile_member_id},'{$friend_btn_id}','request')";
                            break;
                        case 'pending_sent':
                            $fb_label = '⏳ Request Sent';
                            $fb_bg    = '#aaa'; $fb_color = 'white';
                            $fb_onclick = "friendAction({$profile_member_id},'{$friend_btn_id}','cancel')";
                            break;
                        case 'pending_received':
                            $fb_label = '✅ Accept Friend';
                            $fb_bg    = '#00a32a'; $fb_color = 'white';
                            $fb_onclick = "friendAction({$profile_member_id},'{$friend_btn_id}','accept')";
                            break;
                        case 'accepted':
                            $fb_label = '👥 Friends';
                            $fb_bg    = '#28a745'; $fb_color = 'white';
                            $fb_onclick = "friendAction({$profile_member_id},'{$friend_btn_id}','unfriend')";
                            break;
                        default:
                            $fb_label = '';
                            $fb_onclick = '';
                            $fb_bg = ''; $fb_color = '';
                    }
                    if ($friendship_status !== 'self'):
                    ?>
                    <button id="<?php echo esc_attr($friend_btn_id); ?>"
                            onclick="<?php echo $fb_onclick; ?>"
                            data-status="<?php echo esc_attr($friendship_status); ?>"
                            data-nonce-request="<?php echo esc_attr($friend_nonce_req); ?>"
                            data-nonce-respond="<?php echo esc_attr($friend_nonce_res); ?>"
                            data-nonce-unfriend="<?php echo esc_attr($friend_nonce_unf); ?>"
                            style="background:<?php echo esc_attr($fb_bg); ?>;color:<?php echo esc_attr($fb_color); ?>;border:2px solid <?php echo esc_attr($fb_bg); ?>;padding:12px 30px;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        <?php echo esc_html($fb_label); ?>
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="toggleBlock(<?php echo $profile_member_id; ?>, this)" 
                            style="background:<?php echo $is_blocked ? '#d00' : '#999'; ?>;color:white;padding:12px 30px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;font-size:16px;">
                        <?php echo $is_blocked ? '🚫 Unblock' : '⊘ Block'; ?>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Mutual Friends -->
            <?php if (!empty($mutual_friends) && $profile_member_id != $current_member['id']): ?>
                <div style="margin-bottom:16px;">
                    <p style="font-size:14px;color:#666;margin:0 0 8px 0;">
                        👥 <?php echo count($mutual_friends); ?> mutual friend<?php echo count($mutual_friends) !== 1 ? 's' : ''; ?>:
                        <?php $mf_names = array_map(function($mf) { return esc_html(mmgr_unescape_alias($mf['community_alias'] ?: $mf['name'])); }, $mutual_friends); echo implode(', ', $mf_names); ?>
                    </p>
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

        <!-- Bio Photos Gallery -->
        <?php
        $bio_photos_tbl2  = $wpdb->prefix . 'membership_bio_photos';
        $profile_bio_photos = $wpdb->get_results($wpdb->prepare(
            "SELECT id, photo_url FROM $bio_photos_tbl2 WHERE member_id = %d ORDER BY sort_order ASC, id ASC",
            $profile_member_id
        ), ARRAY_A);
        if (!empty($profile_bio_photos)):
            // Collect photo IDs for bulk like-count query
            $bio_photo_ids = array_map('intval', array_column($profile_bio_photos, 'id'));
            $bio_photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
            $id_placeholders = implode(',', array_fill(0, count($bio_photo_ids), '%d'));

            // Get like counts per photo
            $photo_like_counts_raw = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT photo_id, COUNT(*) AS like_count FROM $bio_photo_likes_tbl WHERE photo_id IN ($id_placeholders) GROUP BY photo_id",
                    ...$bio_photo_ids
                ), ARRAY_A
            );
            $photo_like_counts = array();
            foreach ($photo_like_counts_raw as $row) {
                $photo_like_counts[$row['photo_id']] = (int) $row['like_count'];
            }

            // Get which photos the current member has liked
            $my_photo_likes = array();
            if ($current_member) {
                $liked_rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT photo_id FROM $bio_photo_likes_tbl WHERE member_id = %d AND photo_id IN ($id_placeholders)",
                        array_merge(array($current_member['id']), $bio_photo_ids)
                    ), ARRAY_A
                );
                foreach ($liked_rows as $row) {
                    $my_photo_likes[$row['photo_id']] = true;
                }
            }
        ?>
        <div class="mmgr-portal-card" style="margin-top:20px;">
            <h2>📸 Photos</h2>
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:10px;">
                <?php foreach ($profile_bio_photos as $bp):
                    $pid        = (int) $bp['id'];
                    $plikes     = isset($photo_like_counts[$pid]) ? $photo_like_counts[$pid] : 0;
                    $p_is_liked = !empty($my_photo_likes[$pid]);
                ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                    <img src="<?php echo esc_url($bp['photo_url']); ?>"
                         onclick="openPhotoLightbox('<?php echo esc_js($bp['photo_url']); ?>')"
                         style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid #e0e0e0;cursor:pointer;transition:transform 0.2s;"
                         onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"
                         alt="Bio photo">
                    <div style="display:flex;align-items:center;gap:5px;">
                        <?php if ($profile_member_id != $current_member['id']): ?>
                        <button onclick="togglePhotoLike(<?php echo $pid; ?>, this)"
                                data-liked="<?php echo $p_is_liked ? '1' : '0'; ?>"
                                style="background:<?php echo $p_is_liked ? '#FF2197' : 'white'; ?>;color:<?php echo $p_is_liked ? 'white' : '#FF2197'; ?>;border:1.5px solid #FF2197;padding:3px 10px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:bold;">
                            ❤️ <?php echo $p_is_liked ? 'Unlike' : 'Like'; ?>
                        </button>
                        <?php endif; ?>
                        <span id="photo-like-count-<?php echo $pid; ?>" style="font-size:13px;color:#888;"><?php echo $plikes; ?> ❤️</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
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

        function openPhotoLightbox(url) {
            let lb = document.getElementById('mmgr-profile-lightbox');
            if (!lb) {
                lb = document.createElement('div');
                lb.id = 'mmgr-profile-lightbox';
                lb.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:99999;cursor:zoom-out;';
                lb.onclick = function(e) { if (e.target === lb) lb.style.display = 'none'; };
                lb.innerHTML = '<img id="mmgr-profile-lb-img" src="" style="max-width:90vw;max-height:90vh;border-radius:8px;border:3px solid white;" alt="Photo">'
                    + '<button onclick="document.getElementById(\'mmgr-profile-lightbox\').style.display=\'none\'" style="position:absolute;top:16px;right:20px;background:rgba(0,0,0,0.6);color:white;border:none;border-radius:50%;width:36px;height:36px;font-size:20px;cursor:pointer;line-height:1;">✕</button>';
                document.body.appendChild(lb);
            }
            document.getElementById('mmgr-profile-lb-img').src = url;
            lb.style.display = 'flex';
        }

        function togglePhotoLike(photoId, button) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mmgr_toggle_photo_like&photo_id=' + photoId + '&nonce=<?php echo wp_create_nonce('mmgr_toggle_photo_like'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const liked = data.data.liked;
                    button.textContent = liked ? '❤️ Unlike' : '❤️ Like';
                    button.style.background = liked ? '#FF2197' : 'white';
                    button.style.color = liked ? 'white' : '#FF2197';
                    button.dataset.liked = liked ? '1' : '0';
                    const countEl = document.getElementById('photo-like-count-' + photoId);
                    if (countEl) {
                        countEl.textContent = data.data.like_count + ' ❤️';
                    }
                } else {
                    alert('❌ ' + data.data);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error);
            });
        }

        /**
         * Friend action handler for community profile page.
         * actionType: 'request' | 'cancel' | 'accept' | 'decline' | 'unfriend'
         */
        function friendAction(profileId, btnId, actionType) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            const origText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '…';

            let ajaxAction, nonce, body;

            if (actionType === 'request') {
                ajaxAction = 'mmgr_friend_request';
                nonce = btn.dataset.nonceRequest;
                body = 'action=' + ajaxAction + '&profile_id=' + profileId + '&nonce=' + encodeURIComponent(nonce);
            } else if (actionType === 'cancel' || actionType === 'unfriend') {
                ajaxAction = 'mmgr_unfriend';
                nonce = btn.dataset.nonceUnfriend;
                body = 'action=' + ajaxAction + '&profile_id=' + profileId + '&nonce=' + encodeURIComponent(nonce);
            } else if (actionType === 'accept' || actionType === 'decline') {
                ajaxAction = 'mmgr_friend_respond';
                nonce = btn.dataset.nonceRespond;
                body = 'action=' + ajaxAction + '&profile_id=' + profileId + '&action_type=' + actionType + '&nonce=' + encodeURIComponent(nonce);
            }

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    const newStatus = data.data.status;
                    btn.dataset.status = newStatus;
                    if (newStatus === 'pending_sent') {
                        btn.textContent = '⏳ Request Sent';
                        btn.style.background = '#aaa';
                        btn.style.borderColor = '#aaa';
                        btn.setAttribute('onclick', "friendAction(" + profileId + ",'" + btnId + "','cancel')");
                    } else if (newStatus === 'accepted') {
                        btn.textContent = '👥 Friends';
                        btn.style.background = '#28a745';
                        btn.style.borderColor = '#28a745';
                        btn.setAttribute('onclick', "friendAction(" + profileId + ",'" + btnId + "','unfriend')");
                    } else {
                        btn.textContent = '🤝 Add Friend';
                        btn.style.background = '#0073aa';
                        btn.style.borderColor = '#0073aa';
                        btn.setAttribute('onclick', "friendAction(" + profileId + ",'" + btnId + "','request')");
                    }
                } else {
                    btn.textContent = origText;
                    alert('❌ ' + (data.data.message || data.data));
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = origText;
                alert('❌ Connection error');
            });
        }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * AJAX: Save private member note
 */
add_action('wp_ajax_nopriv_mmgr_save_member_note', function() { do_action('wp_ajax_mmgr_save_member_note'); });
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

/**
 * AJAX: Toggle bio photo like
 */
add_action('wp_ajax_nopriv_mmgr_toggle_photo_like', function() { do_action('wp_ajax_mmgr_toggle_photo_like'); });
add_action('wp_ajax_mmgr_toggle_photo_like', function() {
    check_ajax_referer('mmgr_toggle_photo_like', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error('Not logged in');
    }

    $photo_id = intval($_POST['photo_id']);
    if ($photo_id <= 0) {
        wp_send_json_error('Invalid photo');
    }

    global $wpdb;
    $bio_photo_likes_tbl = $wpdb->prefix . 'membership_bio_photo_likes';
    $bio_photos_tbl      = $wpdb->prefix . 'membership_bio_photos';

    // Verify photo exists and does not belong to the current member (can't like own photos)
    $photo = $wpdb->get_row($wpdb->prepare(
        "SELECT id, member_id FROM $bio_photos_tbl WHERE id = %d",
        $photo_id
    ), ARRAY_A);
    if (!$photo) {
        wp_send_json_error('Photo not found');
    }
    if ((int) $photo['member_id'] === (int) $member['id']) {
        wp_send_json_error('Cannot like your own photo');
    }

    // Toggle like
    $is_liked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $bio_photo_likes_tbl WHERE member_id = %d AND photo_id = %d",
        $member['id'],
        $photo_id
    ));

    if ($is_liked) {
        $wpdb->delete($bio_photo_likes_tbl, array(
            'member_id' => $member['id'],
            'photo_id'  => $photo_id,
        ));
    } else {
        $wpdb->insert($bio_photo_likes_tbl, array(
            'member_id' => $member['id'],
            'photo_id'  => $photo_id,
            'liked_at'  => current_time('mysql'),
        ));
    }

    $like_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bio_photo_likes_tbl WHERE photo_id = %d",
        $photo_id
    ));

    wp_send_json_success(array(
        'liked'      => !$is_liked,
        'like_count' => $like_count,
    ));
});

add_action('wp_ajax_nopriv_mmgr_get_member_alias', function() { do_action('wp_ajax_mmgr_get_member_alias'); });
add_action('wp_ajax_mmgr_get_member_alias', function() {
    check_ajax_referer('mmgr_get_member_alias', 'nonce');
    
    $member_id = intval($_POST['member_id']);
    global $wpdb;
    
    $alias = $wpdb->get_var($wpdb->prepare(
        "SELECT community_alias FROM {$wpdb->prefix}memberships WHERE id = %d",
        $member_id
    ));
    
    wp_send_json_success(array('alias' => mmgr_unescape_alias($alias ?: 'Member')));
});


/**
 * Toggle Post Like via AJAX
 */
add_action('wp_ajax_nopriv_mmgr_toggle_post_like', function() { do_action('wp_ajax_mmgr_toggle_post_like'); });
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
 * Toggle Comment Like via AJAX
 */
add_action('wp_ajax_nopriv_mmgr_toggle_comment_like', function() { do_action('wp_ajax_mmgr_toggle_comment_like'); });
add_action('wp_ajax_mmgr_toggle_comment_like', function() {
    check_ajax_referer('mmgr_toggle_comment_like', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }

    $comment_id = intval($_POST['comment_id']);

    global $wpdb;
    $comment_likes_tbl = $wpdb->prefix . 'membership_forum_comment_likes';

    // Check if already liked
    $is_liked = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $comment_likes_tbl WHERE member_id = %d AND comment_id = %d",
        $member['id'],
        $comment_id
    ));

    if ($is_liked) {
        // Unlike
        $wpdb->delete($comment_likes_tbl, array(
            'member_id'  => $member['id'],
            'comment_id' => $comment_id
        ));
    } else {
        // Like
        $wpdb->insert($comment_likes_tbl, array(
            'member_id'  => $member['id'],
            'comment_id' => $comment_id,
            'liked_at'   => current_time('mysql')
        ));
    }

    // Get updated like count
    $like_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $comment_likes_tbl WHERE comment_id = %d",
        $comment_id
    ));

    wp_send_json_success(array(
        'liked'      => !$is_liked,
        'like_count' => $like_count
    ));
});

/**
 * Edit Forum Post via AJAX (author only)
 */
add_action('wp_ajax_nopriv_mmgr_edit_forum_post', function() { do_action('wp_ajax_mmgr_edit_forum_post'); });
add_action('wp_ajax_mmgr_edit_forum_post', function() {
    check_ajax_referer('mmgr_edit_forum_post', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }

    $post_id = intval($_POST['post_id']);
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (empty($message)) {
        wp_send_json_error(array('message' => 'Message cannot be empty.'));
    }

    global $wpdb;
    $posts_tbl    = $wpdb->prefix . 'membership_forum_posts';
    $history_tbl  = $wpdb->prefix . 'membership_forum_post_history';

    // Verify the post belongs to this member
    $post = $wpdb->get_row(
        $wpdb->prepare("SELECT id, member_id, message FROM $posts_tbl WHERE id = %d", $post_id),
        ARRAY_A
    );

    if (!$post) {
        wp_send_json_error(array('message' => 'Post not found.'));
    }

    if (intval($post['member_id']) !== intval($member['id'])) {
        wp_send_json_error(array('message' => 'You can only edit your own posts.'));
    }

    // Save previous version to history before updating
    $wpdb->insert($history_tbl, array(
        'post_id'     => $post_id,
        'old_message' => $post['message'],
        'saved_at'    => current_time('mysql'),
    ));

    $wpdb->update(
        $posts_tbl,
        array('message' => $message, 'edited_at' => current_time('mysql')),
        array('id' => $post_id),
        array('%s', '%s'),
        array('%d')
    );

    $edited_label = '✏️ Edited ' . date_i18n('F j, Y @ g:i A', current_time('timestamp'));

    wp_send_json_success(array('message' => $message, 'edited_label' => $edited_label));
});

/**
 * AJAX: Moderator – suspend a member from posting in the forum for 30 days
 */
add_action('wp_ajax_nopriv_mmgr_forum_suspend_member', function() { do_action('wp_ajax_mmgr_forum_suspend_member'); });
add_action('wp_ajax_mmgr_forum_suspend_member', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $topic_id  = intval($_POST['topic_id']);
    $target_id = intval($_POST['member_id']);
    $reason    = sanitize_textarea_field($_POST['reason'] ?? '');

    // Verify current member is a moderator of this topic
    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_forum_topic_mods WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    $until = date('Y-m-d H:i:s', strtotime('+30 days', current_time('timestamp')));
    $wpdb->update(
        $wpdb->prefix . 'memberships',
        array('forum_suspended' => 1, 'forum_suspended_until' => $until, 'forum_suspended_reason' => $reason),
        array('id' => $target_id)
    );

    wp_send_json_success(array('message' => 'Member suspended from the forum for 30 days.'));
});

/**
 * AJAX: Moderator – lift a forum suspension
 */
add_action('wp_ajax_nopriv_mmgr_forum_unsuspend_member', function() { do_action('wp_ajax_mmgr_forum_unsuspend_member'); });
add_action('wp_ajax_mmgr_forum_unsuspend_member', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $topic_id  = intval($_POST['topic_id']);
    $target_id = intval($_POST['member_id']);

    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_forum_topic_mods WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    $wpdb->update(
        $wpdb->prefix . 'memberships',
        array('forum_suspended' => 0, 'forum_suspended_until' => null, 'forum_suspended_reason' => null),
        array('id' => $target_id)
    );

    wp_send_json_success(array('message' => 'Forum suspension lifted.'));
});

/**
 * AJAX: Moderator – ban a member from posting in the forum
 */
add_action('wp_ajax_nopriv_mmgr_forum_ban_member', function() { do_action('wp_ajax_mmgr_forum_ban_member'); });
add_action('wp_ajax_mmgr_forum_ban_member', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $topic_id  = intval($_POST['topic_id']);
    $target_id = intval($_POST['member_id']);
    $reason    = sanitize_textarea_field($_POST['reason'] ?? '');

    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_forum_topic_mods WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    $wpdb->update(
        $wpdb->prefix . 'memberships',
        array('forum_banned' => 1, 'forum_banned_reason' => $reason),
        array('id' => $target_id)
    );

    wp_send_json_success(array('message' => 'Member banned from posting in the forum.'));
});

/**
 * AJAX: Moderator – lift a forum ban
 */
add_action('wp_ajax_nopriv_mmgr_forum_unban_member', function() { do_action('wp_ajax_mmgr_forum_unban_member'); });
add_action('wp_ajax_mmgr_forum_unban_member', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $topic_id  = intval($_POST['topic_id']);
    $target_id = intval($_POST['member_id']);

    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}membership_forum_topic_mods WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    $wpdb->update(
        $wpdb->prefix . 'memberships',
        array('forum_banned' => 0, 'forum_banned_reason' => null),
        array('id' => $target_id)
    );

    wp_send_json_success(array('message' => 'Forum ban lifted.'));
});

/**
 * AJAX: Moderator – view edit history of a post
 */
add_action('wp_ajax_mmgr_forum_post_history', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $post_id     = intval($_POST['post_id']);
    $posts_tbl   = $wpdb->prefix . 'membership_forum_posts';
    $history_tbl = $wpdb->prefix . 'membership_forum_post_history';
    $mods_tbl    = $wpdb->prefix . 'membership_forum_topic_mods';

    // Get post topic to verify moderator
    $topic_id = $wpdb->get_var($wpdb->prepare("SELECT topic_id FROM $posts_tbl WHERE id = %d", $post_id));
    if (!$topic_id) wp_send_json_error(array('message' => 'Post not found.'));

    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $mods_tbl WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT old_message, saved_at FROM $history_tbl WHERE post_id = %d ORDER BY saved_at ASC",
        $post_id
    ), ARRAY_A);

    $formatted = array_map(function($h) {
        return array(
            'old_message' => $h['old_message'],
            'saved_at'    => date_i18n('F j, Y @ g:i A', strtotime($h['saved_at'])),
        );
    }, $history);

    wp_send_json_success(array('history' => $formatted));
});

/**
 * AJAX: Moderator – hide or unhide a forum post
 */
add_action('wp_ajax_nopriv_mmgr_forum_hide_post', function() { do_action('wp_ajax_mmgr_forum_hide_post'); });
add_action('wp_ajax_mmgr_forum_hide_post', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $post_id  = intval($_POST['post_id']);
    $hide     = intval($_POST['hidden']); // 1 = hide, 0 = unhide
    $topic_id = intval($_POST['topic_id']);
    $posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $mods_tbl  = $wpdb->prefix . 'membership_forum_topic_mods';

    // Verify moderator
    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $mods_tbl WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    // Verify post belongs to the topic
    $post_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $posts_tbl WHERE id = %d AND topic_id = %d",
        $post_id, $topic_id
    ));
    if (!$post_exists) wp_send_json_error(array('message' => 'Post not found.'));

    $wpdb->update($posts_tbl, array('hidden' => $hide ? 1 : 0), array('id' => $post_id));
    $msg = $hide ? 'Post hidden from public view.' : 'Post is now visible to all members.';
    wp_send_json_success(array('message' => $msg));
});

/**
 * AJAX: Moderator – remove photo from a forum post
 */
add_action('wp_ajax_nopriv_mmgr_forum_remove_photo', function() { do_action('wp_ajax_mmgr_forum_remove_photo'); });
add_action('wp_ajax_mmgr_forum_remove_photo', function() {
    check_ajax_referer('mmgr_forum_mod_action', 'nonce');

    $current = mmgr_get_current_member();
    if (!$current) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $post_id  = intval($_POST['post_id']);
    $topic_id = intval($_POST['topic_id']);
    $posts_tbl = $wpdb->prefix . 'membership_forum_posts';
    $mods_tbl  = $wpdb->prefix . 'membership_forum_topic_mods';

    // Verify moderator
    $is_mod = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $mods_tbl WHERE topic_id = %d AND member_id = %d",
        $topic_id, $current['id']
    ));
    if (!$is_mod) wp_send_json_error(array('message' => 'Permission denied.'));

    // Verify post belongs to the topic
    $post_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $posts_tbl WHERE id = %d AND topic_id = %d",
        $post_id, $topic_id
    ));
    if (!$post_exists) wp_send_json_error(array('message' => 'Post not found.'));

    $wpdb->update($posts_tbl, array('photo_url' => ''), array('id' => $post_id));
    wp_send_json_success(array('message' => 'Photo removed.'));
});

/**
 * AJAX: Any member – add a comment to a forum post
 */
add_action('wp_ajax_nopriv_mmgr_add_post_comment', function() { do_action('wp_ajax_mmgr_add_post_comment'); });
add_action('wp_ajax_mmgr_add_post_comment', function() {
    check_ajax_referer('mmgr_add_post_comment', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) wp_send_json_error(array('message' => 'Not logged in'));

    global $wpdb;
    $post_id = intval($_POST['post_id']);
    $comment = sanitize_textarea_field($_POST['comment'] ?? '');

    if (empty($comment)) {
        wp_send_json_error(array('message' => 'Comment cannot be empty.'));
    }

    // Check forum ban/suspension
    $status = $wpdb->get_row($wpdb->prepare(
        "SELECT forum_banned, forum_suspended, forum_suspended_until FROM {$wpdb->prefix}memberships WHERE id = %d",
        $member['id']
    ), ARRAY_A);
    if (!empty($status['forum_banned'])) {
        wp_send_json_error(array('message' => '⛔ You have been banned from the forum.'));
    }
    if (!empty($status['forum_suspended']) && !empty($status['forum_suspended_until']) && strtotime($status['forum_suspended_until']) > time()) {
        wp_send_json_error(array('message' => '⏸️ Your forum access is currently suspended.'));
    }

    $posts_tbl    = $wpdb->prefix . 'membership_forum_posts';
    $mods_tbl     = $wpdb->prefix . 'membership_forum_topic_mods';
    $comments_tbl = $wpdb->prefix . 'membership_forum_post_comments';

    // Verify post exists; hidden posts cannot be commented on by non-moderators
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT id, topic_id, hidden FROM $posts_tbl WHERE id = %d",
        $post_id
    ), ARRAY_A);
    if (!$post) wp_send_json_error(array('message' => 'Post not found.'));

    if (!empty($post['hidden'])) {
        $is_mod = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $mods_tbl WHERE topic_id = %d AND member_id = %d",
            $post['topic_id'], $member['id']
        ));
        if (!$is_mod) wp_send_json_error(array('message' => 'This post is not available.'));
    }

    $wpdb->insert($comments_tbl, array(
        'post_id'   => $post_id,
        'member_id' => $member['id'],
        'comment'   => $comment,
        'posted_at' => current_time('mysql'),
    ));

    $comment_id = $wpdb->insert_id;
    $display_name = !empty($member['community_alias']) ? mmgr_unescape_alias($member['community_alias']) : $member['name'];

    wp_send_json_success(array(
        'comment_id' => $comment_id,
        'author'    => $display_name,
        'comment'   => $comment,
        'posted_at' => date_i18n('M j, Y @ g:i A', current_time('timestamp')),
    ));
});

/**
 * AJAX: Any member – report a forum post
 */
add_action('wp_ajax_nopriv_mmgr_report_forum_post', function() { do_action('wp_ajax_mmgr_report_forum_post'); });
add_action('wp_ajax_mmgr_report_forum_post', function() {
    check_ajax_referer('mmgr_report_forum_post', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(array('message' => 'Not logged in.'));
    }

    global $wpdb;
    $post_id = intval($_POST['post_id']);
    $reason  = sanitize_textarea_field($_POST['reason'] ?? '');

    if (empty($reason)) {
        wp_send_json_error(array('message' => 'Please provide a reason for the report.'));
    }

    $posts_tbl   = $wpdb->prefix . 'membership_forum_posts';
    $reports_tbl = $wpdb->prefix . 'membership_forum_post_reports';

    // Verify post exists
    $post = $wpdb->get_row($wpdb->prepare("SELECT id, member_id FROM $posts_tbl WHERE id = %d", $post_id), ARRAY_A);
    if (!$post) {
        wp_send_json_error(array('message' => 'Post not found.'));
    }

    // Prevent reporting own posts
    if ($post['member_id'] == $member['id']) {
        wp_send_json_error(array('message' => 'You cannot report your own post.'));
    }

    $result = $wpdb->insert($reports_tbl, array(
        'post_id'     => $post_id,
        'reported_by' => $member['id'],
        'reason'      => $reason,
        'reported_at' => current_time('mysql'),
        'status'      => 'pending',
    ));

    if ($result) {
        wp_send_json_success(array('message' => 'Report submitted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Failed to submit report.'));
    }
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