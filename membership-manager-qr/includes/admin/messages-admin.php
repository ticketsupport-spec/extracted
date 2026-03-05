<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$messages_table = $wpdb->prefix . 'membership_messages';
$members_table = $wpdb->prefix . 'memberships';
$msg_reports_table = $wpdb->prefix . 'membership_message_reports';

// Handle admin send message to all members
if (isset($_POST['send_broadcast']) && isset($_POST['broadcast_nonce'])) {
    if (!wp_verify_nonce($_POST['broadcast_nonce'], 'mmgr_broadcast')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $message = sanitize_textarea_field($_POST['broadcast_message']);
        
        // Get all members
        $all_members = $wpdb->get_results("SELECT id FROM $members_table", ARRAY_A);
        
        $sent_count = 0;
        foreach ($all_members as $member) {
            // Send from admin (member_id = 0)
            $wpdb->insert($messages_table, array(
                'from_member_id' => 0,
                'to_member_id' => $member['id'],
                'message' => $message,
                'sent_at' => current_time('mysql')
            ));
            $sent_count++;
        }
        
        echo '<div class="notice notice-success"><p>✓ Broadcast sent to ' . $sent_count . ' members!</p></div>';
    }
}

// Get all images (for admin review)
$all_images = $wpdb->get_results(
    "SELECT m.*, 
     from_m.name as from_name, 
     to_m.name as to_name,
     CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as has_pending_report
     FROM $messages_table m
     LEFT JOIN $members_table from_m ON m.from_member_id = from_m.id
     LEFT JOIN $members_table to_m ON m.to_member_id = to_m.id
     LEFT JOIN $msg_reports_table r ON r.message_id = m.id AND r.status = 'pending'
     WHERE m.image_url IS NOT NULL AND m.image_url != ''
     ORDER BY m.sent_at DESC
     LIMIT 100",
    ARRAY_A
);

// Get statistics
$total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $messages_table");
$total_images = $wpdb->get_var("SELECT COUNT(*) FROM $messages_table WHERE image_url IS NOT NULL AND image_url != ''");
$reported_count = 0;
if ($wpdb->get_var("SHOW TABLES LIKE '$msg_reports_table'") === $msg_reports_table) {
    $reported_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$msg_reports_table` WHERE status = 'pending'");
}

?>
<div class="wrap">
    <h1>💬 Messages Management</h1>
    
    <!-- Statistics -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;margin-bottom:30px;">
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border-left:4px solid #0073aa;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Total Messages</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#0073aa;"><?php echo number_format($total_messages); ?></p>
        </div>
        
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border-left:4px solid #9b51e0;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Images Shared</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#9b51e0;"><?php echo number_format($total_images); ?></p>
        </div>
        
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);border-left:4px solid #d63638;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Reported Messages</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#d63638;"><?php echo number_format($reported_count); ?></p>
        </div>
    </div>
    
    <!-- Tabs -->
    <div style="background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px;">
        <div style="display:flex;border-bottom:2px solid #e0e0e0;">
            <button class="mmgr-admin-tab active" onclick="showAdminTab('broadcast')" style="flex:1;padding:15px;border:none;background:none;cursor:pointer;font-weight:bold;border-bottom:3px solid #9b51e0;">
                📢 Broadcast
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=membership_reported_items')); ?>"
               style="flex:1;padding:15px;border:none;background:none;cursor:pointer;font-weight:bold;text-align:center;text-decoration:none;color:#d63638;display:flex;align-items:center;justify-content:center;gap:6px;">
                🚩 Reported Items <?php if ($reported_count > 0): ?><span class="awaiting-mod"><?php echo $reported_count; ?></span><?php endif; ?>
            </a>
            <button class="mmgr-admin-tab" onclick="showAdminTab('images')" style="flex:1;padding:15px;border:none;background:none;cursor:pointer;font-weight:bold;">
                🖼️ All Images
            </button>
        </div>
        
		<!-- Broadcast Tab -->
		<div id="broadcast-tab" class="mmgr-admin-tab-content" style="padding:30px;">
			<h2>📢 Send Message to All Members</h2>
			<p>Send a broadcast message from Admin to all registered members.</p>
			
			<!-- ADD THIS PREVIEW BOX -->
			<div style="background:#f0e6ff;border-left:4px solid #9b51e0;padding:20px;border-radius:6px;margin-bottom:30px;">
				<h3 style="margin:0 0 10px 0;color:#9b51e0;">📬 Current Welcome Message</h3>
				<p style="margin:0 0 10px 0;font-size:13px;color:#666;">This message is automatically sent to new members when they register.</p>
				<div style="background:white;padding:15px;border-radius:6px;white-space:pre-wrap;font-size:14px;line-height:1.6;max-height:300px;overflow-y:auto;">
					<?php echo esc_html(get_option('mmgr_welcome_pm_message', mmgr_get_default_welcome_pm())); ?>
				</div>
				<p style="margin:10px 0 0 0;font-size:13px;">
					<a href="<?php echo admin_url('admin.php?page=membership_settings'); ?>" class="button button-secondary">
						✏️ Edit Welcome Message
					</a>
					<?php if (get_option('mmgr_welcome_pm_enabled', 1)): ?>
						<span style="color:#00a32a;margin-left:10px;">✓ Enabled</span>
					<?php else: ?>
						<span style="color:#d63638;margin-left:10px;">⊘ Disabled</span>
					<?php endif; ?>
				</p>
			</div>
			
			<form method="POST">
				<?php wp_nonce_field('mmgr_broadcast', 'broadcast_nonce'); ?>
				
				<div style="margin-bottom:20px;">
					<label style="display:block;font-weight:bold;margin-bottom:8px;">Broadcast Message *</label>
					<textarea name="broadcast_message" rows="6" required placeholder="Type your message to all members..." style="width:100%;max-width:600px;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:15px;font-family:inherit;"></textarea>
				</div>
				
				<button type="submit" name="send_broadcast" class="button button-primary button-large" style="background:#9b51e0;border-color:#9b51e0;">
					📤 Send to All Members
				</button>
			</form>
		</div>
		
        
        <!-- All Images Tab -->
        <div id="images-tab" class="mmgr-admin-tab-content" style="padding:30px;display:none;">
            <h2>🖼️ All Shared Images</h2>
            <p style="color:#666;margin-bottom:20px;">Review all images shared in member messages for moderation purposes.</p>
            
            <?php if (empty($all_images)): ?>
                <p style="text-align:center;padding:40px;color:#666;">
                    No images shared yet.
                </p>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));gap:20px;">
                    <?php foreach ($all_images as $img): ?>
                        <div style="background:#f9f9f9;border:2px solid #e0e0e0;border-radius:8px;overflow:hidden;">
                            <?php if (!empty($img['image_url'])): ?>
                                <a href="<?php echo esc_url($img['image_url']); ?>" target="_blank">
                                    <img src="<?php echo esc_url($img['image_url']); ?>" style="width:100%;height:200px;object-fit:cover;display:block;" alt="Shared image">
                                </a>
                            <?php endif; ?>
                            
                            <div style="padding:12px;">
                                <div style="font-size:12px;color:#666;margin-bottom:5px;">
                                    <strong>From:</strong> <?php echo esc_html($img['from_name']); ?><br>
                                    <strong>To:</strong> <?php echo esc_html($img['to_name']); ?><br>
                                    <strong>Date:</strong> <?php echo date('M j, Y', strtotime($img['sent_at'])); ?>
                                </div>
                                
                                <?php if ($img['image_deleted']): ?>
                                    <span style="background:#ff9800;color:white;padding:3px 8px;border-radius:10px;font-size:11px;">
                                        🗑️ Deleted by sender
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($img['has_pending_report']): ?>
                                    <span style="background:#d00;color:white;padding:3px 8px;border-radius:10px;font-size:11px;margin-left:5px;">
                                        🚩 Reported
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($img['message'])): ?>
                                    <div style="margin-top:8px;padding:8px;background:white;border-radius:4px;font-size:12px;">
                                        <?php echo esc_html(substr($img['message'], 0, 80)) . (strlen($img['message']) > 80 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($all_images) >= 100): ?>
                    <p style="margin-top:20px;text-align:center;color:#666;font-size:13px;">
                        Showing 100 most recent images
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showAdminTab(tabName) {
    // Reset all tabs
    document.querySelectorAll('.mmgr-admin-tab').forEach(tab => {
        tab.style.borderBottom = 'none';
        tab.style.color = '#666';
    });
    document.querySelectorAll('.mmgr-admin-tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Show selected tab
    const tabs = document.querySelectorAll('.mmgr-admin-tab');
    const contents = document.querySelectorAll('.mmgr-admin-tab-content');
    
    if (tabName === 'broadcast') {
        tabs[0].style.borderBottom = '3px solid #9b51e0';
        tabs[0].style.color = '#000';
        contents[0].style.display = 'block';
    } else if (tabName === 'images') {
        tabs[1].style.borderBottom = '3px solid #9b51e0';
        tabs[1].style.color = '#000';
        contents[1].style.display = 'block';
    }
}
</script>