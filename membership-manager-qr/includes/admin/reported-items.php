<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) {
    wp_die('Permission denied.');
}

global $wpdb;
$members_table      = $wpdb->prefix . 'memberships';
$messages_table     = $wpdb->prefix . 'membership_messages';
$msg_reports_table  = $wpdb->prefix . 'membership_message_reports';
$posts_table        = $wpdb->prefix . 'membership_forum_posts';
$topics_table       = $wpdb->prefix . 'membership_forum_topics';
$post_reports_table = $wpdb->prefix . 'membership_forum_post_reports';

// Handle dismiss actions
if (isset($_POST['dismiss_report']) && isset($_POST['dismiss_nonce'])) {
    if (!wp_verify_nonce($_POST['dismiss_nonce'], 'mmgr_dismiss_report')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $report_id   = intval($_POST['report_id']);
        $report_type = sanitize_key($_POST['report_type']);

        if ($report_type === 'message') {
            $wpdb->update($msg_reports_table, array('status' => 'reviewed'), array('id' => $report_id));
        } elseif ($report_type === 'forum_post') {
            $wpdb->update($post_reports_table, array('status' => 'reviewed'), array('id' => $report_id));
        }

        echo '<div class="notice notice-success"><p>✓ Report marked as reviewed.</p></div>';
    }
}

// Get pending reported messages (join reports table with messages and members)
$reported_messages = array();
$msg_reports_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$msg_reports_table'") === $msg_reports_table;
if ($msg_reports_table_exists) {
    $reported_messages = $wpdb->get_results(
        "SELECT r.id as report_id,
                r.reported_at,
                r.reason,
                r.status,
                m.id as message_id,
                m.message,
                m.image_url,
                m.sent_at,
                m.from_member_id,
                m.to_member_id,
                reporter.id   as reporter_id,
                reporter.name as reporter_name,
                sender.id     as sender_id,
                sender.name   as sender_name,
                recip.id      as recipient_id,
                recip.name    as recipient_name
         FROM $msg_reports_table r
         LEFT JOIN $messages_table m       ON r.message_id = m.id
         LEFT JOIN $members_table reporter ON r.reported_by = reporter.id
         LEFT JOIN $members_table sender   ON m.from_member_id = sender.id
         LEFT JOIN $members_table recip    ON m.to_member_id   = recip.id
         WHERE r.status = 'pending'
         ORDER BY r.reported_at DESC",
        ARRAY_A
    );
}

// Get pending reported forum posts (join reports table with posts, topics and members)
$reported_posts = array();
$post_reports_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$post_reports_table'") === $post_reports_table;
if ($post_reports_table_exists) {
    $reported_posts = $wpdb->get_results(
        "SELECT r.id as report_id,
                r.reported_at,
                r.reason,
                r.status,
                p.id as post_id,
                p.message as post_message,
                p.posted_at,
                p.photo_url,
                t.id   as topic_id,
                t.topic_name,
                reporter.id   as reporter_id,
                reporter.name as reporter_name,
                author.id     as author_id,
                author.name   as author_name,
                author.community_alias as author_alias
         FROM $post_reports_table r
         LEFT JOIN $posts_table p   ON r.post_id   = p.id
         LEFT JOIN $topics_table t  ON p.topic_id  = t.id
         LEFT JOIN $members_table reporter ON r.reported_by = reporter.id
         LEFT JOIN $members_table author   ON p.member_id   = author.id
         WHERE r.status = 'pending'
         ORDER BY r.reported_at DESC",
        ARRAY_A
    );
}

$pending_msg_count  = count($reported_messages);
$pending_post_count = count($reported_posts);
$total_pending      = $pending_msg_count + $pending_post_count;

$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'messages';
?>
<div class="wrap">
    <h1>🚩 Reported Items</h1>
    <p style="color:#666;margin-bottom:20px;">Review content reported by members. Click "Mark Reviewed" to dismiss a report after taking any necessary action.</p>

    <!-- Summary stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px,1fr));gap:20px;margin-bottom:30px;">
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:4px solid #d63638;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Total Pending</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#d63638;"><?php echo $total_pending; ?></p>
        </div>
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:4px solid #9b51e0;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Reported Messages</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#9b51e0;"><?php echo $pending_msg_count; ?></p>
        </div>
        <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:4px solid #FF2197;">
            <h3 style="margin:0 0 10px 0;color:#666;font-size:14px;">Reported Forum Posts</h3>
            <p style="margin:0;font-size:32px;font-weight:bold;color:#FF2197;"><?php echo $pending_post_count; ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div style="background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
        <div style="display:flex;border-bottom:2px solid #e0e0e0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=membership_reported_items&tab=messages')); ?>"
               style="flex:1;padding:15px;text-align:center;text-decoration:none;font-weight:bold;border-bottom:<?php echo $active_tab === 'messages' ? '3px solid #9b51e0;color:#000' : 'none;color:#666'; ?>;">
                💬 Reported Messages <?php if ($pending_msg_count > 0): ?><span class="awaiting-mod"><?php echo $pending_msg_count; ?></span><?php endif; ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=membership_reported_items&tab=forum_posts')); ?>"
               style="flex:1;padding:15px;text-align:center;text-decoration:none;font-weight:bold;border-bottom:<?php echo $active_tab === 'forum_posts' ? '3px solid #FF2197;color:#000' : 'none;color:#666'; ?>;">
                📋 Reported Forum Posts <?php if ($pending_post_count > 0): ?><span class="awaiting-mod"><?php echo $pending_post_count; ?></span><?php endif; ?>
            </a>
        </div>

        <!-- Reported Messages Tab -->
        <?php if ($active_tab === 'messages'): ?>
        <div style="padding:30px;">
            <h2>💬 Reported Private Messages</h2>
            <?php if (empty($reported_messages)): ?>
                <p style="text-align:center;padding:40px;color:#666;">No pending reported messages. 🎉</p>
            <?php else: ?>
                <table class="widefat striped" style="margin-top:20px;">
                    <thead>
                        <tr>
                            <th>Reported By</th>
                            <th>Offending Sender</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Reason</th>
                            <th>Reported On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reported_messages as $r): ?>
                        <tr>
                            <td>
                                <?php if (!empty($r['reporter_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['reporter_id'])); ?>" title="View reporter profile">
                                        <?php echo esc_html($r['reporter_name'] ?: '(unknown)'); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['from_member_id'] == 0): ?>
                                    <em>Admin / Support</em>
                                <?php elseif (!empty($r['sender_id'])): ?>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['sender_id'])); ?>" title="View sender profile">
                                            <?php echo esc_html($r['sender_name'] ?: '(unknown)'); ?>
                                        </a>
                                    </strong>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['to_member_id'] == 0): ?>
                                    <em>Admin / Support</em>
                                <?php elseif (!empty($r['recipient_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['recipient_id'])); ?>">
                                        <?php echo esc_html($r['recipient_name'] ?: '(unknown)'); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:280px;">
                                <?php if (!empty($r['message'])): ?>
                                    <span style="font-style:italic;">"<?php echo esc_html(mb_substr($r['message'], 0, 120)) . (mb_strlen($r['message']) > 120 ? '…' : ''); ?>"</span>
                                <?php else: ?>
                                    <em>(no text)</em>
                                <?php endif; ?>
                                <?php if (!empty($r['image_url'])): ?>
                                    <br><a href="<?php echo esc_url($r['image_url']); ?>" target="_blank" style="color:#0073aa;font-size:12px;">📎 View Image</a>
                                <?php endif; ?>
                            </td>
                            <td style="color:#d63638;max-width:200px;"><strong><?php echo esc_html($r['reason']); ?></strong></td>
                            <td style="white-space:nowrap;"><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($r['reported_at']))); ?></td>
                            <td style="white-space:nowrap;">
                                <?php if (!empty($r['sender_id']) && $r['from_member_id'] != 0): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['sender_id'])); ?>"
                                       class="button button-small" style="margin-bottom:4px;display:inline-block;">👤 Sender Profile</a><br>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <?php wp_nonce_field('mmgr_dismiss_report', 'dismiss_nonce'); ?>
                                    <input type="hidden" name="report_id"   value="<?php echo intval($r['report_id']); ?>">
                                    <input type="hidden" name="report_type" value="message">
                                    <button type="submit" name="dismiss_report" class="button button-small"
                                            onclick="return confirm('Mark this report as reviewed?')">
                                        ✓ Mark Reviewed
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Reported Forum Posts Tab -->
        <?php elseif ($active_tab === 'forum_posts'): ?>
        <div style="padding:30px;">
            <h2>📋 Reported Forum Posts</h2>
            <?php if (empty($reported_posts)): ?>
                <p style="text-align:center;padding:40px;color:#666;">No pending reported forum posts. 🎉</p>
            <?php else: ?>
                <table class="widefat striped" style="margin-top:20px;">
                    <thead>
                        <tr>
                            <th>Reported By</th>
                            <th>Offending Author</th>
                            <th>Forum Topic</th>
                            <th>Post Content</th>
                            <th>Reason</th>
                            <th>Reported On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reported_posts as $r):
                            $author_display = !empty($r['author_alias']) ? $r['author_alias'] : ($r['author_name'] ?: '(unknown)');
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($r['reporter_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['reporter_id'])); ?>" title="View reporter profile">
                                        <?php echo esc_html($r['reporter_name'] ?: '(unknown)'); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['author_id'])): ?>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['author_id'])); ?>" title="View author profile">
                                            <?php echo esc_html($author_display); ?>
                                        </a>
                                    </strong>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['topic_id'])): ?>
                                    <a href="<?php echo esc_url(home_url('/member-community/?topic=' . $r['topic_id'])); ?>" target="_blank" title="View forum topic">
                                        <?php echo esc_html($r['topic_name'] ?: 'Topic #' . $r['topic_id']); ?> ↗
                                    </a>
                                <?php else: ?>
                                    <em>Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:280px;">
                                <?php if (!empty($r['post_message'])): ?>
                                    <span style="font-style:italic;">"<?php echo esc_html(mb_substr($r['post_message'], 0, 120)) . (mb_strlen($r['post_message']) > 120 ? '…' : ''); ?>"</span>
                                <?php else: ?>
                                    <em>(no text)</em>
                                <?php endif; ?>
                                <?php if (!empty($r['photo_url'])): ?>
                                    <br><a href="<?php echo esc_url($r['photo_url']); ?>" target="_blank" style="color:#0073aa;font-size:12px;">📷 View Photo</a>
                                <?php endif; ?>
                            </td>
                            <td style="color:#d63638;max-width:200px;"><strong><?php echo esc_html($r['reason']); ?></strong></td>
                            <td style="white-space:nowrap;"><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($r['reported_at']))); ?></td>
                            <td style="white-space:nowrap;">
                                <?php if (!empty($r['author_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_manager&edit=' . $r['author_id'])); ?>"
                                       class="button button-small" style="margin-bottom:4px;display:inline-block;">👤 Author Profile</a><br>
                                <?php endif; ?>
                                <?php if (!empty($r['topic_id'])): ?>
                                    <a href="<?php echo esc_url(home_url('/member-community/?topic=' . $r['topic_id'])); ?>"
                                       target="_blank" class="button button-small" style="margin-bottom:4px;display:inline-block;">📋 View Forum</a><br>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <?php wp_nonce_field('mmgr_dismiss_report', 'dismiss_nonce'); ?>
                                    <input type="hidden" name="report_id"   value="<?php echo intval($r['report_id']); ?>">
                                    <input type="hidden" name="report_type" value="forum_post">
                                    <button type="submit" name="dismiss_report" class="button button-small"
                                            onclick="return confirm('Mark this report as reviewed?')">
                                        ✓ Mark Reviewed
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
