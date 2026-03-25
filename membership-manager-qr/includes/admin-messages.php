<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Messages Page - View and reply to member messages
 */
function mmgr_admin_messages_page() {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    $members_table = $wpdb->prefix . 'memberships';
    
    // Get active conversation from URL
    $active_member_id = isset($_GET['member']) ? intval($_GET['member']) : null;
    
    // Handle sending reply
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_admin_reply'])) {
        check_admin_referer('mmgr_admin_send_message');
        
        $to_member_id = intval($_POST['to_member_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $image_url = null;
        
        // Handle image upload
        if (!empty($_FILES['admin_message_image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['admin_message_image'], $upload_overrides);
            
            if (!isset($movefile['error'])) {
                $image_url = $movefile['url'];
            }
        }
        
        if (!empty($message) || !empty($image_url)) {
            $wpdb->insert($messages_table, array(
                'from_member_id' => 0, // Admin
                'to_member_id' => $to_member_id,
                'message' => $message,
                'image_url' => $image_url,
                'sent_at' => current_time('mysql')
            ));
            
            echo '<div class="notice notice-success"><p>✓ Message sent successfully!</p></div>';
            $active_member_id = $to_member_id; // Stay on this conversation
        }
    }
    
    // Get all conversations (members who have messaged admin)
    $conversations = $wpdb->get_results(
        "SELECT 
            m.id as member_id,
            m.name,
            m.photo_url,
            m.email,
            MAX(msg.sent_at) as last_message_time,
            SUM(CASE WHEN msg.from_member_id != 0 AND msg.read_at IS NULL THEN 1 ELSE 0 END) as unread_count
         FROM $messages_table msg
         INNER JOIN $members_table m ON (
             CASE 
                 WHEN msg.from_member_id = 0 THEN msg.to_member_id 
                 ELSE msg.from_member_id 
             END = m.id
         )
         WHERE msg.from_member_id = 0 OR msg.to_member_id = 0
         GROUP BY m.id
         ORDER BY last_message_time DESC",
        ARRAY_A
    );
    
		// Get messages for active conversation
		$messages = array();
		$active_member = null;
		if ($active_member_id) {
			$messages = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM $messages_table 
				 WHERE (from_member_id = 0 AND to_member_id = %d)
					OR (from_member_id = %d AND to_member_id = 0)
				 ORDER BY sent_at DESC
				 LIMIT 10",
				$active_member_id,
				$active_member_id
			), ARRAY_A);
			
			$messages = array_reverse($messages); // Show in chronological order
        
        $active_member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE id = %d",
            $active_member_id
        ), ARRAY_A);
        
        // Mark as read
        $wpdb->update(
            $messages_table,
            array('read_at' => current_time('mysql')),
            array('from_member_id' => $active_member_id, 'to_member_id' => 0)
        );
    }

    $is_conversation_archived = $active_member_id ? mmgr_is_conversation_archived(0, $active_member_id) : false;
    
    ?>
    
    <div class="wrap mmgr-admin-messages-wrap">
        <h1 class="wp-heading-inline">💬 Member Messages</h1>
        <hr class="wp-header-end">
        
        <div class="mmgr-admin-messages-grid">
            <!-- Sidebar: Conversations List -->
            <div class="mmgr-admin-sidebar">
                <div style="padding:15px;background:#f6f7f7;border-bottom:1px solid #ddd;font-weight:bold;">
                    📬 Conversations (<?php echo count($conversations); ?>)
                </div>
                
                <?php if (empty($conversations)): ?>
                    <div style="padding:40px 20px;text-align:center;color:#666;">
                        <p style="font-size:48px;margin:0;">💬</p>
                        <p>No messages yet</p>
                        <p style="font-size:13px;">Members can message you from their portal</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="mmgr-admin-conv-item <?php echo ($active_member_id == $conv['member_id']) ? 'active' : ''; ?>" 
                             onclick="window.location.href='?page=membership_messages&member=<?php echo $conv['member_id']; ?>'">
                            
                            <?php if (!empty($conv['photo_url'])): ?>
                                <img src="<?php echo esc_url($conv['photo_url']); ?>" class="mmgr-admin-avatar" alt="Avatar">
                            <?php else: ?>
                                <div class="mmgr-admin-avatar-placeholder">👤</div>
                            <?php endif; ?>
                            
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:bold;color:#1d2327;margin-bottom:3px;">
                                    <?php echo esc_html($conv['name']); ?>
                                </div>
                                <div style="font-size:12px;color:#646970;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?php echo esc_html($conv['email']); ?>
                                </div>
                                <div style="font-size:11px;color:#999;margin-top:2px;">
                                    <?php echo human_time_diff(strtotime($conv['last_message_time']), current_time('timestamp')) . ' ago'; ?>
                                </div>
                            </div>
                            
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="mmgr-admin-unread-badge"><?php echo $conv['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Chat Area -->
            <div class="mmgr-admin-chat-area">
                <?php if ($active_member): ?>
                    <!-- Chat Header -->
                    <div class="mmgr-admin-chat-header">
                        <?php if (!empty($active_member['photo_url'])): ?>
                            <img src="<?php echo esc_url($active_member['photo_url']); ?>" style="width:50px;height:50px;border-radius:50%;border:2px solid white;" alt="Avatar">
                        <?php else: ?>
                            <div style="width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,0.3);display:flex;align-items:center;justify-content:center;font-size:24px;">👤</div>
                        <?php endif; ?>
                        
                        <div style="flex:1;">
                            <h3 style="margin:0;color:white;font-size:18px;"><?php echo esc_html($active_member['name']); ?></h3>
                            <div style="font-size:13px;opacity:0.9;"><?php echo esc_html($active_member['email']); ?></div>
                        </div>
                        
                        <a href="<?php echo admin_url('admin.php?page=membership_add&id=' . $active_member['id']); ?>" class="button button-secondary" style="background:rgba(255,255,255,0.2);border:none;color:white;">
                            👤 View Profile
                        </a>
                        
                        <div class="dropdown" style="position:relative;margin-left:8px;">
                            <button onclick="toggleDropdown()" style="background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:18px;line-height:1;">
                                ⋮
                            </button>
                            <div id="chat-dropdown" style="display:none;position:absolute;right:0;top:100%;background:white;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.2);min-width:180px;margin-top:5px;z-index:100;">
                                <button id="admin-archive-btn"
                                        onclick="archiveAdminConversation(<?php echo intval($active_member['id']); ?>, this)"
                                        data-archived="<?php echo $is_conversation_archived ? '1' : '0'; ?>"
                                        data-nonce-archive="<?php echo esc_attr(wp_create_nonce('mmgr_admin_archive_conversation')); ?>"
                                        data-nonce-unarchive="<?php echo esc_attr(wp_create_nonce('mmgr_admin_unarchive_conversation')); ?>"
                                        style="width:100%;padding:12px;border:none;background:none;text-align:left;cursor:pointer;color:#555;">
                                    <?php echo $is_conversation_archived ? '📤 Unarchive Chat' : '📦 Archive Chat'; ?>
                                </button>
                                <button onclick="deleteAdminConversation(<?php echo intval($active_member['id']); ?>)"
                                        style="width:100%;padding:12px;border:none;background:none;text-align:left;cursor:pointer;color:#d63638;">
                                    🗑️ Delete Chat
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Info Box -->
                    <div class="mmgr-member-info-box" style="margin:15px 20px 0 20px;">
                        <h4>📋 Member Information</h4>
                        <div class="mmgr-member-info-row">
                            <span><strong>Member Code:</strong></span>
                            <span><code><?php echo esc_html($active_member['member_code']); ?></code></span>
                        </div>
                        <div class="mmgr-member-info-row">
                            <span><strong>Membership Type:</strong></span>
                            <span><?php echo esc_html($active_member['level']); ?></span>
                        </div>
                        <div class="mmgr-member-info-row">
                            <span><strong>Phone:</strong></span>
                            <span><?php echo esc_html($active_member['phone']); ?></span>
                        </div>
                        <div class="mmgr-member-info-row">
                            <span><strong>Status:</strong></span>
                            <span>
                                <?php if ($active_member['paid']): ?>
                                    <span style="color:#00a32a;">✓ Active</span>
                                <?php else: ?>
                                    <span style="color:#d63638;">⚠️ Payment Pending</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
			<!-- Messages -->
			<div class="mmgr-admin-messages-list" id="admin-messages-container">
				<?php if (empty($messages)): ?>
					<div style="text-align:center;padding:60px 20px;color:#646970;">
						<p style="font-size:48px;margin:0;">💬</p>
						<p>No messages yet</p>
					</div>
				<?php else: ?>
					<!-- Load More Button -->
					<div id="admin-load-more-container" style="text-align:center;padding:15px;display:<?php echo mmgr_get_conversation_count(0, $active_member_id) > 10 ? 'block' : 'none'; ?>;">
						<button type="button" id="admin-load-more-btn" class="button" onclick="adminLoadMoreMessages()">
							⬆️ Load Earlier Messages
						</button>
					</div>
					
					<!-- Messages List -->
					<div id="admin-messages-list">
						<?php foreach ($messages as $msg): ?>
							<div class="mmgr-admin-message-bubble <?php echo $msg['from_member_id'] == 0 ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
								<div class="mmgr-admin-message-content">
									<?php if (!empty($msg['message'])): ?>
										<div><?php echo nl2br(esc_html($msg['message'])); ?></div>
									<?php endif; ?>
									
									<?php if (!empty($msg['image_url']) && !$msg['image_deleted']): ?>
										<img src="<?php echo esc_url($msg['image_url']); ?>" class="mmgr-admin-message-image" onclick="window.open('<?php echo esc_url($msg['image_url']); ?>', '_blank')" alt="Image">
									<?php elseif ($msg['image_deleted']): ?>
										<div style="opacity:0.5;font-style:italic;font-size:12px;">
											🖼️ Image removed
										</div>
									<?php endif; ?>
									
									<div class="mmgr-admin-message-time">
										<?php echo date('M j, g:i A', strtotime($msg['sent_at'])); ?>
										<?php if ($msg['from_member_id'] == 0): ?>
											<span style="margin-left:5px;">(You)</span>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
                    
                    <!-- Input Area -->
                    <div class="mmgr-admin-input-area">
                        <form method="POST" enctype="multipart/form-data" id="admin-message-form">
                            <?php wp_nonce_field('mmgr_admin_send_message'); ?>
                            <input type="hidden" name="to_member_id" value="<?php echo $active_member['id']; ?>">
                            
                            <div id="admin-image-preview" style="margin-bottom:10px;display:none;">
                                <img id="admin-preview-img" style="max-width:200px;border-radius:4px;border:1px solid #ccd0d4;">
                                <button type="button" onclick="removeAdminImagePreview()" class="button" style="margin-left:10px;">
                                    ✕ Remove
                                </button>
                            </div>
                            
                            <div class="mmgr-admin-input-wrapper">
                                <label for="admin-image-upload" class="button" style="cursor:pointer;">
                                    📎 Attach
                                </label>
                                <input type="file" id="admin-image-upload" name="admin_message_image" accept="image/*" style="display:none;" onchange="previewAdminImage(this)">
                                
                                <textarea name="message" class="mmgr-admin-textarea" rows="3" placeholder="Type your message..." id="admin-message-input"></textarea>
                                
                                <button type="submit" name="send_admin_reply" class="button button-primary" style="height:auto;padding:10px 20px;">
                                    ➤ Send
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="mmgr-admin-empty-state">
                        <div style="font-size:64px;margin-bottom:20px;">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Choose a member from the sidebar to view and reply to their messages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<script>
let adminMessageOffset = 10;
const adminMemberId = <?php echo intval($active_member_id ?? 0); ?>;

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

function previewAdminImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('admin-preview-img').src = e.target.result;
            document.getElementById('admin-image-preview').style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeAdminImagePreview() {
    document.getElementById('admin-image-upload').value = '';
    document.getElementById('admin-image-preview').style.display = 'none';
}

// Load more messages
function adminLoadMoreMessages() {
    const btn = document.getElementById('admin-load-more-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Loading...';
    
    const formData = new FormData();
    formData.append('action', 'mmgr_admin_load_more_messages');
    formData.append('member_id', adminMemberId);
    formData.append('offset', adminMessageOffset);
    formData.append('nonce', '<?php echo wp_create_nonce('mmgr_admin_load_more_messages'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const messagesList = document.getElementById('admin-messages-list');
            const fragment = document.createDocumentFragment();
            
            data.data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'mmgr-admin-message-bubble ' + (msg.from_member_id == 0 ? 'sent' : 'received');
                div.setAttribute('data-message-id', msg.id);
                
                let content = '';
                if (msg.message) {
                    content += '<div>' + msg.message.replace(/\n/g, '<br>') + '</div>';
                }
                if (msg.image_url && !msg.image_deleted) {
                    content += '<img src="' + msg.image_url + '" class="mmgr-admin-message-image" onclick="window.open(\'' + msg.image_url + '\', \'_blank\')" alt="Image">';
                }
                content += '<div class="mmgr-admin-message-time">' + msg.sent_at + (msg.from_member_id == 0 ? ' (You)' : '') + '</div>';
                
                div.innerHTML = '<div class="mmgr-admin-message-content">' + content + '</div>';
                fragment.appendChild(div);
            });
            
            messagesList.insertBefore(fragment, messagesList.firstChild);
            adminMessageOffset += data.data.count;
            
            if (data.data.count < 10) {
                document.getElementById('admin-load-more-container').style.display = 'none';
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

// Scroll to bottom
const adminMessagesContainer = document.getElementById('admin-messages-container');
if (adminMessagesContainer) {
    adminMessagesContainer.scrollTop = adminMessagesContainer.scrollHeight;
}

// Toggle dropdown
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
            window.location.href = '<?php echo admin_url('admin.php?page=membership_messages'); ?>';
        }
    });
}

function archiveAdminConversation(memberId, button) {
    const isArchived = button.getAttribute('data-archived') === '1';
    const nonceArchive = button.getAttribute('data-nonce-archive');
    const nonceUnarchive = button.getAttribute('data-nonce-unarchive');
    const action = isArchived ? 'mmgr_admin_unarchive_conversation' : 'mmgr_admin_archive_conversation';
    const nonce = isArchived ? nonceUnarchive : nonceArchive;

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=' + action + '&member_id=' + memberId + '&nonce=' + encodeURIComponent(nonce)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.location.href = '<?php echo admin_url('admin.php?page=membership_messages'); ?>';
        } else {
            alert('✕ ' + (d.data.message || d.data));
        }
    });
}

function deleteAdminConversation(memberId) {
    if (!confirm('Delete this conversation? Messages will be removed from your view only.')) return;
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mmgr_admin_delete_conversation&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_admin_delete_conversation'); ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('✓ ' + d.data.message);
            window.location.href = '<?php echo admin_url('admin.php?page=membership_messages'); ?>';
        } else {
            alert('✕ ' + (d.data.message || d.data));
        }
    });
}
</script>
    <?php
}

// AJAX: Admin archive conversation
add_action('wp_ajax_mmgr_admin_archive_conversation', function() {
    check_ajax_referer('mmgr_admin_archive_conversation', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    $other_member_id = intval($_POST['member_id']);
    $result = mmgr_archive_conversation(0, $other_member_id);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Admin unarchive conversation
add_action('wp_ajax_mmgr_admin_unarchive_conversation', function() {
    check_ajax_referer('mmgr_admin_unarchive_conversation', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    $other_member_id = intval($_POST['member_id']);
    $result = mmgr_unarchive_conversation(0, $other_member_id);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// AJAX: Admin delete conversation
add_action('wp_ajax_mmgr_admin_delete_conversation', function() {
    check_ajax_referer('mmgr_admin_delete_conversation', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    $other_member_id = intval($_POST['member_id']);
    $result = mmgr_delete_conversation(0, $other_member_id);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// Add unread count to admin menu
add_action('admin_footer', function() {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'membership_messages';
    
    $unread_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM $messages_table 
         WHERE to_member_id = 0 AND read_at IS NULL"
    );
    
    if ($unread_count > 0) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#mmgr-message-count').text('<?php echo $unread_count; ?>');
        });
        </script>
        <?php
    }
});