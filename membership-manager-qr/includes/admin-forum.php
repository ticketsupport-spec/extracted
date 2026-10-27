<?php
if (!defined('ABSPATH')) exit;

function mmgr_forum_posts_menu_label() {
    global $wpdb;
    $table = $wpdb->prefix . 'membership_forum_posts';
    if (!$wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'admin_read_at'")) {
        return 'Forum Posts';
    }
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE admin_read_at IS NULL");
    return 'Forum Posts' . ($count > 0 ? ' <span class="awaiting-mod">' . $count . '</span>' : '');
}

function mmgr_admin_forum_moderate($action, $post_id, $input) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('permission', 'Permission denied.');
    }
    if (!wp_verify_nonce($input['forum_nonce'] ?? '', 'mmgr_admin_forum_' . $post_id)) {
        return new WP_Error('nonce', 'Security check failed.');
    }
    if (!in_array($action, array('edit', 'delete', 'ban', 'unban'), true)) {
        return new WP_Error('action', 'Unknown forum action.');
    }

    global $wpdb;
    $posts = $wpdb->prefix . 'membership_forum_posts';
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$posts` WHERE id = %d", $post_id), ARRAY_A);
    if (!$post) {
        return new WP_Error('missing', 'Post not found.');
    }

    if ($action === 'edit') {
        $message = sanitize_textarea_field(wp_unslash($input['message'] ?? ''));
        if ($message === '') {
            return new WP_Error('empty', 'Message cannot be empty.');
        }
        if ($message === $post['message']) {
            return 'Post saved.';
        }
        if ($wpdb->insert($wpdb->prefix . 'membership_forum_post_history', array(
            'post_id' => $post_id,
            'old_message' => $post['message'],
            'saved_at' => current_time('mysql'),
        )) === false) {
            return new WP_Error('history', 'Could not save post history. The post was not changed.');
        }
        $result = $wpdb->update($posts, array('message' => $message, 'edited_at' => current_time('mysql')), array('id' => $post_id));
        $notice = 'Post saved.';
    } elseif ($action === 'delete') {
        // Remove dependent rows first; a failed cleanup leaves the post available for retry.
        $comments = $wpdb->prefix . 'membership_forum_post_comments';
        $comment_likes = $wpdb->prefix . 'membership_forum_comment_likes';
        if ($wpdb->query($wpdb->prepare(
            "DELETE cl FROM `$comment_likes` cl INNER JOIN `$comments` c ON cl.comment_id = c.id WHERE c.post_id = %d",
            $post_id
        )) === false) {
            return new WP_Error('delete', 'Could not delete post comments. Please try again.');
        }
        foreach (array('comments', 'likes', 'photos', 'history', 'reports') as $suffix) {
            if ($wpdb->delete($wpdb->prefix . 'membership_forum_post_' . $suffix, array('post_id' => $post_id)) === false) {
                return new WP_Error('delete', 'Could not delete related post data. Please try again.');
            }
        }
        $result = $wpdb->delete($posts, array('id' => $post_id));
        $notice = 'Post deleted.';
    } else {
        // A forum ban does not disable the member's membership or private messages.
        $result = $wpdb->update($wpdb->prefix . 'memberships', array(
            'forum_banned' => $action === 'ban' ? 1 : 0,
            'forum_banned_reason' => $action === 'ban' ? sanitize_textarea_field(wp_unslash($input['reason'] ?? '')) : null,
        ), array('id' => (int) $post['member_id']));
        $notice = $action === 'ban' ? 'Member banned from posting and commenting in the forum.' : 'Forum ban lifted.';
    }
    return $result === false ? new WP_Error('database', 'Could not save the change. Please try again.') : $notice;
}

function mmgr_admin_forum_mark_read($ids) {
    if (!current_user_can('manage_options') || empty($ids)) {
        return;
    }
    global $wpdb;
    $ids = array_map('absint', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    return $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}membership_forum_posts SET admin_read_at = %s WHERE admin_read_at IS NULL AND id IN ($placeholders)",
        array_merge(array(current_time('mysql')), $ids)
    ));
}

function mmgr_admin_forum_load() {
    if (($_GET['page'] ?? '') !== 'membership_forum_posts') {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    global $wpdb, $mmgr_admin_forum, $submenu;
    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forum_action'])) {
        $notice = mmgr_admin_forum_moderate(
            sanitize_key($_POST['forum_action']),
            absint($_POST['post_id'] ?? 0),
            $_POST
        );
    }
    $page = max(1, absint($_GET['paged'] ?? 1));
    $edit = absint($_GET['edit'] ?? 0);
    $posts = $wpdb->prefix . 'membership_forum_posts';
    $where = $edit ? $wpdb->prepare('WHERE p.id = %d', $edit) : '';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, m.name AS member_name, m.community_alias, m.forum_banned, t.topic_name
         FROM `$posts` p
         LEFT JOIN {$wpdb->prefix}memberships m ON p.member_id = m.id
         LEFT JOIN {$wpdb->prefix}membership_forum_topics t ON p.topic_id = t.id
         $where ORDER BY p.id DESC LIMIT %d OFFSET %d",
        20, $edit ? 0 : ($page - 1) * 20
    ), ARRAY_A);
    $rows = $rows ?: array();
    $photos = array();
    if ($rows) {
        $ids = array_map('absint', array_column($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $photo_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, photo_url FROM {$wpdb->prefix}membership_forum_post_photos WHERE post_id IN ($placeholders) ORDER BY sort_order, id",
            $ids
        ), ARRAY_A);
        foreach ($photo_rows ?: array() as $photo) {
            $photos[$photo['post_id']][] = $photo['photo_url'];
        }
        // Only posts actually loaded for this page are read, never off-page or newly arriving posts.
        if (mmgr_admin_forum_mark_read($ids) === false && !$notice) {
            $notice = new WP_Error('read', 'Could not mark these posts as read. Please reload to try again.');
        }
    }
    $mmgr_admin_forum = array(
        'rows' => $rows, 'photos' => $photos, 'notice' => $notice, 'page' => $page, 'edit' => $edit,
        'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `$posts`"),
    );
    // WordPress registers the menu before the page load hook; refresh it before rendering.
    if (isset($submenu['membership_manager'])) {
        foreach ($submenu['membership_manager'] as &$item) {
            if ($item[2] === 'membership_forum_posts') {
                $item[0] = mmgr_forum_posts_menu_label();
            }
        }
        unset($item);
    }
}

function mmgr_admin_forum_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    global $mmgr_admin_forum;
    $data = $mmgr_admin_forum;
    $url = admin_url('admin.php?page=membership_forum_posts');
    ?>
    <div class="wrap">
        <h1>Forum Posts</h1>
        <p>Posts shown here are automatically marked as read for all administrators. Other pages remain unread. Ban restricts forum posting and commenting only.</p>
        <?php if ($data['notice']): ?>
            <div class="notice <?php echo is_wp_error($data['notice']) ? 'notice-error' : 'notice-success'; ?>"><p><?php
                echo esc_html(is_wp_error($data['notice']) ? $data['notice']->get_error_message() : $data['notice']);
            ?></p></div>
        <?php endif; ?>
        <?php if ($data['edit']): ?><p><a href="<?php echo esc_url($url); ?>">← All forum posts</a></p><?php endif; ?>
        <?php if (!$data['rows']): ?><p>No forum posts found.</p><?php endif; ?>
        <?php foreach ($data['rows'] as $post):
            $id = (int) $post['id'];
            $photos = $data['photos'][$id] ?? array();
            if (!empty($post['photo_url'])) array_unshift($photos, $post['photo_url']);
        ?>
            <div class="card" style="max-width:none;">
                <h2>#<?php echo $id; ?> — <?php echo esc_html($post['topic_name'] ?: 'Deleted / unknown topic'); ?>
                    <?php if (empty($post['admin_read_at'])): ?><span class="awaiting-mod">New</span><?php endif; ?>
                    <?php if (!empty($post['hidden'])): ?><span> (Hidden from members)</span><?php endif; ?>
                </h2>
                <p><strong><?php echo esc_html($post['member_name'] ?: 'Unknown member'); ?></strong>
                    <?php if (!empty($post['community_alias'])): ?>(<?php echo esc_html($post['community_alias']); ?>)<?php endif; ?>
                    — <?php echo esc_html($post['posted_at']); ?>
                    <?php if (!empty($post['edited_at'])): ?> · Edited <?php echo esc_html($post['edited_at']); ?><?php endif; ?>
                </p>
                <div style="white-space:pre-wrap;overflow-wrap:anywhere;"><?php echo esc_html($post['message']); ?></div>
                <?php foreach (array_unique($photos) as $photo): ?>
                    <a href="<?php echo esc_url($photo); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($photo); ?>" alt="Post photo" style="max-width:180px;max-height:180px;"></a>
                <?php endforeach; ?>
                <?php if ($data['edit']): ?>
                    <form method="post">
                        <?php wp_nonce_field('mmgr_admin_forum_' . $id, 'forum_nonce'); ?>
                        <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="forum_action" value="edit">
                        <p><label for="forum-message-<?php echo $id; ?>">Message</label></p>
                        <textarea id="forum-message-<?php echo $id; ?>" name="message" rows="6" class="large-text" required><?php echo esc_textarea($post['message']); ?></textarea>
                        <p><button class="button button-primary">Save post</button></p>
                    </form>
                <?php else: ?>
                    <p><a class="button" href="<?php echo esc_url(add_query_arg('edit', $id, $url)); ?>">Edit</a></p>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Permanently delete this post, its comments and associated data?');">
                    <?php wp_nonce_field('mmgr_admin_forum_' . $id, 'forum_nonce'); ?>
                    <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                    <button class="button button-link-delete" name="forum_action" value="delete">Delete</button>
                </form>
                <?php if (!empty($post['member_name'])): ?>
                    <form method="post" onsubmit="return confirm('Change this member’s forum ban?');">
                        <?php wp_nonce_field('mmgr_admin_forum_' . $id, 'forum_nonce'); ?>
                        <input type="hidden" name="post_id" value="<?php echo $id; ?>">
                        <?php if (empty($post['forum_banned'])): ?>
                            <p><label>Ban reason <input type="text" name="reason" class="regular-text"></label></p>
                        <?php endif; ?>
                        <p><button class="button" name="forum_action" value="<?php echo empty($post['forum_banned']) ? 'ban' : 'unban'; ?>"><?php echo empty($post['forum_banned']) ? 'Ban from forum' : 'Unban from forum'; ?></button></p>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$data['edit']): ?>
            <p>
                <?php if ($data['page'] > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $data['page'] - 1, $url)); ?>">Previous</a><?php endif; ?>
                <span>Page <?php echo (int) $data['page']; ?> of <?php echo max(1, (int) ceil($data['total'] / 20)); ?></span>
                <?php if ($data['page'] * 20 < $data['total']): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $data['page'] + 1, $url)); ?>">Next</a><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
