<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$topics_tbl = $wpdb->prefix . 'membership_forum_topics';
$members_tbl = $wpdb->prefix . 'memberships';

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_topic'])) {
    if (!isset($_POST['topic_nonce']) || !wp_verify_nonce($_POST['topic_nonce'], 'mmgr_save_topic')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $topic_id = isset($_POST['topic_id']) ? intval($_POST['topic_id']) : 0;
        $topic_name = sanitize_text_field($_POST['topic_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $icon = sanitize_text_field($_POST['icon']);
        $active = isset($_POST['active']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        $moderator_id = !empty($_POST['moderator_id']) ? intval($_POST['moderator_id']) : null;
        
        $data = array(
            'topic_name' => $topic_name,
            'description' => $description,
            'icon' => $icon,
            'active' => $active,
            'sort_order' => $sort_order,
            'moderator_id' => $moderator_id,
        );
        
        if ($topic_id > 0) {
            $wpdb->update($topics_tbl, $data, array('id' => $topic_id));
            echo '<div class="notice notice-success"><p>Topic updated successfully!</p></div>';
        } else {
            $wpdb->insert($topics_tbl, $data);
            echo '<div class="notice notice-success"><p>Topic created successfully!</p></div>';
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_topic_' . $_GET['delete'])) {
    $wpdb->delete($topics_tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Topic deleted successfully!</p></div>';
}

// Get all topics
$topics = $wpdb->get_results("SELECT t.*, m.name as moderator_name FROM $topics_tbl t LEFT JOIN $members_tbl m ON t.moderator_id = m.id ORDER BY t.sort_order, t.id", ARRAY_A);

// Get all active members for moderator dropdown
$all_members = $wpdb->get_results("SELECT id, name FROM $members_tbl WHERE banned = 0 AND (active IS NULL OR active = 1) ORDER BY name", ARRAY_A);

// Edit mode
$edit_topic = null;
if (isset($_GET['edit'])) {
    $edit_topic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $topics_tbl WHERE id = %d", $_GET['edit']), ARRAY_A);
}

?>
<div class="wrap">
    <h1 class="wp-heading-inline">Forum Topics</h1>
    <hr class="wp-header-end">
    
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;margin-top:20px;">
        <!-- Add/Edit Form -->
        <div>
            <div class="card">
                <h2><?php echo $edit_topic ? 'Edit Topic' : 'Add New Topic'; ?></h2>
                
                <form method="POST">
                    <?php wp_nonce_field('mmgr_save_topic', 'topic_nonce'); ?>
                    <?php if ($edit_topic): ?>
                        <input type="hidden" name="topic_id" value="<?php echo $edit_topic['id']; ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Topic Name *</label></th>
                            <td>
                                <input type="text" name="topic_name" value="<?php echo $edit_topic ? esc_attr($edit_topic['topic_name']) : ''; ?>" required class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Description</label></th>
                            <td>
                                <textarea name="description" rows="3" class="large-text"><?php echo $edit_topic ? esc_textarea($edit_topic['description']) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Icon (Emoji)</label></th>
                            <td>
                                <input type="text" name="icon" value="<?php echo $edit_topic ? esc_attr($edit_topic['icon']) : '💬'; ?>" class="regular-text" placeholder="💬">
                                <p class="description">Use an emoji like 💬 🏆 🎾 📢</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Sort Order</label></th>
                            <td>
                                <input type="number" name="sort_order" value="<?php echo $edit_topic ? esc_attr($edit_topic['sort_order']) : '0'; ?>" class="small-text">
                                <p class="description">Lower numbers appear first</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Active</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="active" value="1" <?php echo (!$edit_topic || $edit_topic['active']) ? 'checked' : ''; ?>>
                                    Show this topic to members
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="moderator_id">Moderator</label></th>
                            <td>
                                <select name="moderator_id" id="moderator_id" class="regular-text">
                                    <option value="">— No moderator —</option>
                                    <?php foreach ($all_members as $m): ?>
                                        <option value="<?php echo intval($m['id']); ?>"
                                            <?php selected(($edit_topic['moderator_id'] ?? ''), $m['id']); ?>>
                                            <?php echo esc_html($m['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Member responsible for moderating this topic's discussions</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="save_topic" class="button button-primary">
                            <?php echo $edit_topic ? 'Update Topic' : 'Create Topic'; ?>
                        </button>
                        <?php if ($edit_topic): ?>
                            <a href="<?php echo admin_url('admin.php?page=membership_forum_topics'); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Topics List -->
        <div>
            <h2>Existing Topics</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;">Icon</th>
                        <th>Topic Name</th>
                        <th>Description</th>
                        <th>Moderator</th>
                        <th style="width:80px;">Sort Order</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topics)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;">No topics found. Create your first topic!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td style="font-size:24px;text-align:center;"><?php echo esc_html($topic['icon']); ?></td>
                                <td><strong><?php echo esc_html($topic['topic_name']); ?></strong></td>
                                <td><?php echo esc_html($topic['description']); ?></td>
                                <td>
                                    <?php if (!empty($topic['moderator_name'])): ?>
                                        <span title="Moderator">👤 <?php echo esc_html($topic['moderator_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?php echo $topic['sort_order']; ?></td>
                                <td>
                                    <?php if ($topic['active']): ?>
                                        <span style="color:#00a32a;font-weight:bold;">● Active</span>
                                    <?php else: ?>
                                        <span style="color:#999;">○ Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=membership_forum_topics&edit=<?php echo $topic['id']; ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_forum_topics&delete=' . $topic['id']), 'delete_topic_' . $topic['id']); ?>" 
                                       class="button button-small button-link-delete" 
                                       style="color:#d63638;"
                                       onclick="return confirm('Delete this topic? All posts in this topic will remain but be orphaned.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>