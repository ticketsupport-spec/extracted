<?php
if (!defined('ABSPATH')) exit;

/**
 * Build the "Card Requests(#)" submenu label with a pending count badge.
 */
function mmgr_card_requests_menu_label() {
    global $wpdb;
    $card_tbl = $wpdb->prefix . 'mmgr_card_requests';
    // Guard: table may not exist yet on first plugin load
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$card_tbl'") === $card_tbl;
    if (!$table_exists) {
        return 'Card Requests';
    }
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$card_tbl` WHERE status = 'pending'");
    if ($count > 0) {
        return 'Card Requests <span class="awaiting-mod">' . $count . '</span>';
    }
    return 'Card Requests';
}

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Membership Manager',
        'Memberships',
        'manage_options',
        'membership_manager',
        'mmgr_members_page',
        'dashicons-id-alt',
        30
    );
    
    add_submenu_page(
        'membership_manager',
        'All Members',
        'All Members',
        'manage_options',
        'membership_manager',
        'mmgr_members_page'
    );
    
    add_submenu_page(
        'membership_manager',
        'Add New Member',
        'Add New',
        'manage_options',
        'membership_add',
        'mmgr_add_member_page'
    );

    // Member Messages
    add_submenu_page(
        'membership_manager',
        'Member Messages',
        'Messages <span class="awaiting-mod" id="mmgr-message-count"></span>',
        'manage_options',
        'membership_messages',
        'mmgr_admin_messages_page'
    );
    
    add_submenu_page(
        'membership_manager',
        'Accounting',
        'Accounting',
        'manage_options',
        'membership_logs',
        'mmgr_logs_page'
    );
    
    add_submenu_page(
        'membership_manager',
        'Membership Levels',
        'Levels',
        'manage_options',
        'membership_levels',
        'mmgr_levels_page'
    );
    
    add_submenu_page(
        'membership_manager',
		'Special Events',
		'Special Events',
        'manage_options',
        'membership_special_fees',
        'mmgr_special_fees_page'
    );
	
	add_submenu_page(
		'membership_manager',
		'Forum Topics',
		'Forum Topics',
		'manage_options',
		'membership_forum_topics',
		function() {
			require_once MMGR_PLUGIN_DIR . 'includes/admin/forum-topics.php';
		}
	);

	// Card Requests with pending count badge
	add_submenu_page(
		'membership_manager',
		'Card Requests',
		mmgr_card_requests_menu_label(),
		'manage_options',
		'membership_card_requests',
		function() {
			require_once MMGR_PLUGIN_DIR . 'includes/admin/card-requests.php';
		}
	);
    
    add_submenu_page(
        'membership_manager',
        'Newsletter Subscribers',
        'Newsletter',
        'manage_options',
        'membership_newsletter',
        'mmgr_newsletter_page'
    );
    
    add_submenu_page(
        'membership_manager',
        'Plugin Pages',
        'Pages',
        'manage_options',
        'membership_pages',
        'mmgr_pages_overview'
    );
    
    add_submenu_page(
        'membership_manager',
        'Settings',
        'Settings',
        'manage_options',
        'membership_settings',
        function() {
            require_once MMGR_PLUGIN_DIR . 'includes/admin/settings-page.php';
            mmgr_settings_admin();
        }
    );
});

/**
 * AJAX Handler: Mark Member as Paid
 */
add_action('wp_ajax_mmgr_mark_paid', function() {
    check_ajax_referer('mmgr_mark_paid', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'Cash';
    
    if (!$member_id) {
        wp_send_json_error(array('message' => 'Invalid member ID'));
    }
    
    // Get member data
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id = %d", $member_id), ARRAY_A);
    
    if (!$member) {
        wp_send_json_error(array('message' => 'Member not found'));
    }
    
    // Get membership level price
    $levels_tbl = $wpdb->prefix . 'membership_levels';
    $level_price = $wpdb->get_var($wpdb->prepare(
        "SELECT price FROM $levels_tbl WHERE level_name = %s",
        $member['level']
    ));
    
    // Calculate expiry date (1 year from start date or today)
    $start_date = !empty($member['start_date']) ? $member['start_date'] : date('Y-m-d');
    $expire_date = date('Y-m-d', strtotime($start_date . ' +1 year'));
    
    // Update member
    $updated = $wpdb->update(
        $tbl,
        array(
            'paid' => 1,
            'payment_date' => current_time('mysql'),
            'payment_method' => $payment_method,
            'payment_amount' => $level_price,
            'expire_date' => $expire_date
        ),
        array('id' => $member_id)
    );
    
    if ($updated !== false) {
        wp_send_json_success(array(
            'message' => 'Member marked as paid!',
            'expire_date' => date('M d, Y', strtotime($expire_date))
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to update member'));
    }
});

/**
 * All Members Page - Main List
 */
function mmgr_members_page() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'memberships';
    
    // Handle delete
    if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_member_' . $_GET['delete'])) {
        $wpdb->delete($tbl, array('id' => intval($_GET['delete'])));
        echo '<div class="notice notice-success"><p>Member deleted successfully!</p></div>';
    }
    
    // Handle ban/unban
    if (isset($_GET['ban']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ban_member_' . $_GET['ban'])) {
        $member_id = intval($_GET['ban']);
        $reason = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : 'No reason provided';
        $wpdb->update($tbl, 
            array('banned' => 1, 'banned_reason' => $reason, 'banned_on' => current_time('mysql')),
            array('id' => $member_id)
        );
        echo '<div class="notice notice-warning"><p>Member banned successfully!</p></div>';
    }
    
    if (isset($_GET['unban']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'unban_member_' . $_GET['unban'])) {
        $wpdb->update($tbl, 
            array('banned' => 0, 'banned_reason' => null, 'banned_on' => null),
            array('id' => intval($_GET['unban']))
        );
        echo '<div class="notice notice-success"><p>Member unbanned successfully!</p></div>';
    }
    
    // Get all members
    $members = $wpdb->get_results("SELECT * FROM $tbl ORDER BY paid ASC, id DESC", ARRAY_A);
    $total = count($members);
    
    // Count unpaid members
    $unpaid_count = $wpdb->get_var("SELECT COUNT(*) FROM $tbl WHERE paid = 0");
    
    // Get all levels for filter
    $levels = $wpdb->get_results("SELECT level_name FROM {$wpdb->prefix}membership_levels ORDER BY level_name", ARRAY_A);
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Membership Manager</h1>
        <a href="<?php echo admin_url('admin.php?page=membership_add'); ?>" class="page-title-action">Add New</a>
        <hr class="wp-header-end">
        
        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#0073aa;color:white;padding:20px;border-radius:6px;">
                <h3 style="margin:0;font-size:32px;"><?php echo $total; ?></h3>
                <p style="margin:5px 0 0 0;">Total Members</p>
            </div>
            <?php if ($unpaid_count > 0): ?>
            <div style="background:#f0c33c;color:#1d2327;padding:20px;border-radius:6px;">
                <h3 style="margin:0;font-size:32px;"><?php echo $unpaid_count; ?></h3>
                <p style="margin:5px 0 0 0;">⚠️ Pending Payment</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Members Table -->
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:80px;">Photo</th>
                    <th style="width:80px;">QR Code</th>
                    <th>Member</th>
                    <th>Contact</th>
                    <th>Level</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th>Last Visit</th>
                    <th style="width:250px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;padding:40px;color:#666;">
                            No members found. <a href="<?php echo admin_url('admin.php?page=membership_add'); ?>">Add your first member</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $member): 
                        $is_expired = !empty($member['expire_date']) && $member['expire_date'] < date('Y-m-d');
                        $is_banned = !empty($member['banned']) && $member['banned'] == 1;
                        $is_paid = !empty($member['paid']) && $member['paid'] == 1;
                        
                        // Generate QR code URL via AJAX (generates on-the-fly if file missing)
                        $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code=' . urlencode($member['member_code']));
                        
                        // Highlight unpaid members
                        $row_style = '';
                        if ($is_banned) {
                            $row_style = 'background:#ffe6e6;';
                        } elseif (!$is_paid) {
                            $row_style = 'background:#fff3cd;';
                        }
                    ?>
                    <tr style="<?php echo $row_style; ?>">
                        <!-- Photo -->
                        <td>
                            <div style="text-align:center;">
                                <div style="font-size:10px;font-weight:bold;color:#666;text-transform:uppercase;margin-bottom:4px;">
                                    <?php echo esc_html($member['level']); ?>
                                </div>
                                <?php if (!empty($member['photo_url'])): ?>
                                    <img src="<?php echo esc_url($member['photo_url']); ?>" 
                                         style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:2px solid #ccc;" 
                                         alt="Photo">
                                <?php else: ?>
                                    <div style="width:60px;height:60px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:30px;border:2px solid #ccc;margin:0 auto;">👤</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- QR Code -->
                        <td>
                            <a href="<?php echo esc_url($qr_url); ?>" target="_blank">
                                <img src="<?php echo esc_url($qr_url); ?>" 
                                     style="width:60px;height:60px;border:1px solid #ccc;" 
                                     alt="QR Code">
                            </a>
                        </td>
                                    
                        <!-- Member Info -->
                        <td>
                            <strong style="font-size:14px;">
                                <a href="<?php echo admin_url('admin.php?page=membership_add&id=' . $member['id']); ?>">
                                    <?php echo esc_html($member['name']); ?>
                                </a>
                            </strong>
                            <?php if (!empty($member['partner_name'])): ?>
                                <br><span style="color:#666;font-size:12px;">+ <?php echo esc_html($member['partner_name']); ?></span>
                            <?php endif; ?>
                            <br><code style="font-size:11px;color:#d00;"><?php echo esc_html($member['member_code']); ?></code>
                        </td>
                        
                        <!-- Contact -->
                        <td style="font-size:12px;">
                            <?php echo esc_html($member['email']); ?><br>
                            <?php echo esc_html($member['phone']); ?>
                        </td>
                        
                        <!-- Level -->
                        <td>
                            <span style="background:#0073aa;color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">
                                <?php echo esc_html($member['level']); ?>
                            </span>
                        </td>
                        
                        <!-- Payment Status -->
                        <td>
                            <?php if ($is_paid): ?>
                                <span style="background:#00a32a;color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">✓ PAID</span>
                                <?php if (!empty($member['payment_date'])): ?>
                                    <br><span style="font-size:11px;color:#666;"><?php echo date('M d, Y', strtotime($member['payment_date'])); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="background:#f0c33c;color:#1d2327;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">⚠️ UNPAID</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Status -->
                        <td>
                            <?php if ($is_banned): ?>
                                <span style="background:#d00;color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">⛔ BANNED</span>
                            <?php elseif (!$is_paid): ?>
                                <span style="background:#f0c33c;color:#1d2327;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">⏳ PENDING</span>
                            <?php elseif ($is_expired): ?>
                                <span style="background:#d63638;color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">⚠️ EXPIRED</span>
                            <?php else: ?>
                                <span style="background:#00a32a;color:white;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;">✓ ACTIVE</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Expires -->
                        <td style="font-size:12px;">
                            <?php 
                            if (!$is_paid) {
                                echo '<em style="color:#999;">Pending payment</em>';
                            } else {
                                echo !empty($member['expire_date']) ? date('M d, Y', strtotime($member['expire_date'])) : 'N/A';
                            }
                            ?>
                        </td>
                        
                        <!-- Last Visit -->
                        <td style="font-size:12px;">
                            <?php echo !empty($member['last_visited']) ? date('M d, Y', strtotime($member['last_visited'])) : 'Never'; ?>
                        </td>
                        
                        <!-- Actions -->
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=membership_add&id=' . $member['id']); ?>" class="button button-small">Edit</a>
                            
                            <?php if (!$is_paid): ?>
                                <button type="button" 
                                        class="button button-small button-primary" 
                                        onclick="markAsPaid(<?php echo $member['id']; ?>)">
                                    💰 Mark Paid
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($is_banned): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_manager&unban=' . $member['id']), 'unban_member_' . $member['id']); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('Unban this member?');">Unban</a>
                            <?php else: ?>
                                <a href="#" 
                                   class="button button-small" 
                                   onclick="banMember(<?php echo $member['id']; ?>); return false;">Ban</a>
                            <?php endif; ?>
                            
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_manager&delete=' . $member['id']), 'delete_member_' . $member['id']); ?>" 
                               class="button button-small button-link-delete" 
                               style="color:#d63638;"
                               onclick="return confirm('Are you sure you want to delete this member? This cannot be undone!');">Delete</a>
                            
                            <button type="button" 
                                    class="button button-small"
                                    onclick="regenQR(<?php echo $member['id']; ?>, '<?php echo esc_js($member['member_code']); ?>')">
                                🔄 Regen QR
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    function banMember(memberId) {
        const reason = prompt('Enter ban reason:');
        if (reason) {
            const nonce = '<?php echo wp_create_nonce('ban_member_'); ?>' + memberId;
            window.location.href = '<?php echo admin_url('admin.php?page=membership_manager&ban='); ?>' + memberId + '&_wpnonce=' + nonce + '&reason=' + encodeURIComponent(reason);
        }
    }
    
    function markAsPaid(memberId) {
        const method = prompt('Payment method:\n1. Cash\n2. Credit Card\n3. E-Transfer\n4. Other\n\nEnter 1-4:', '1');
        
        const methods = {
            '1': 'Cash',
            '2': 'Credit Card',
            '3': 'E-Transfer',
            '4': 'Other'
        };
        
        const paymentMethod = methods[method] || 'Cash';
        
        if (!confirm('Mark this member as PAID via ' + paymentMethod + '?\n\nThis will:\n- Set expiry date to 1 year from start date\n- Mark membership as active')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'mmgr_mark_paid');
        formData.append('member_id', memberId);
        formData.append('payment_method', paymentMethod);
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_mark_paid'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ ' + data.data.message + '\nExpires: ' + data.data.expire_date);
                location.reload();
            } else {
                alert('✕ Error: ' + (data.data ? data.data.message : 'Unknown error'));
            }
        })
        .catch(err => {
            alert('✕ Error: ' + err.message);
        });
    }
    
    function regenQR(memberId, memberCode) {
        if (!confirm('Regenerate QR code for member ' + memberCode + '?')) return;
        
        const formData = new FormData();
        formData.append('action', 'mmgr_regenerate_qr');
        formData.append('member_id', memberId);
        formData.append('nonce', '<?php echo wp_create_nonce('mmgr_regenerate_qr'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✓ ' + data.data.message);
                location.reload();
            } else {
                alert('✕ ' + (data.data ? data.data.message : 'Failed to regenerate QR'));
            }
        })
        .catch(err => {
            alert('✕ Error: ' + err.message);
        });
    }
    </script>
    <?php
}

/**
 * Add New Member Page
 */
function mmgr_add_member_page() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/add-edit-member.php';
}

/**
 * Visit Logs Page
 */
function mmgr_logs_page() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/visit-logs.php';
}

/**
 * Membership Levels Page
 */
function mmgr_levels_page() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/levels-page.php';
}

/**
 * Special Event Fees Page
 */
function mmgr_special_fees_page() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/special-fees.php';
}

/**
 * Plugin Pages Overview
 */
function mmgr_pages_overview() {
    require_once MMGR_PLUGIN_DIR . 'includes/admin/pages-overview.php';
}

