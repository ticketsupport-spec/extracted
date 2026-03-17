<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$help_tbl = $wpdb->prefix . 'membership_help_topics';

// Ensure table exists (in case migration hasn't run yet)
if ($wpdb->get_var("SHOW TABLES LIKE '$help_tbl'") !== $help_tbl) {
    mmgr_migrate_help_topics();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_help_topic'])) {
    if (!isset($_POST['help_topic_nonce']) || !wp_verify_nonce($_POST['help_topic_nonce'], 'mmgr_save_help_topic')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $topic_id   = isset($_POST['topic_id']) ? intval($_POST['topic_id']) : 0;
        $title      = sanitize_text_field($_POST['topic_title']);
        $content    = wp_kses_post($_POST['topic_content']);
        $category   = sanitize_text_field($_POST['topic_category']);
        $sort_order = intval($_POST['sort_order']);
        $active     = isset($_POST['active']) ? 1 : 0;

        $data = array(
            'title'      => $title,
            'content'    => $content,
            'category'   => $category ?: 'General',
            'sort_order' => $sort_order,
            'active'     => $active,
        );

        if ($topic_id > 0) {
            $wpdb->update($help_tbl, $data, array('id' => $topic_id));
            echo '<div class="notice notice-success"><p>Help topic updated successfully!</p></div>';
        } else {
            $wpdb->insert($help_tbl, $data);
            echo '<div class="notice notice-success"><p>Help topic created successfully!</p></div>';
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_help_topic_' . intval($_GET['delete']))) {
    $wpdb->delete($help_tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Help topic deleted.</p></div>';
}

// Get all topics
$topics = $wpdb->get_results("SELECT * FROM $help_tbl ORDER BY sort_order ASC, category ASC, title ASC", ARRAY_A);

// Edit mode
$edit_topic = null;
if (isset($_GET['edit'])) {
    $edit_topic = $wpdb->get_row($wpdb->prepare("SELECT * FROM $help_tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

// Collect distinct categories for the filter/list
$categories = $wpdb->get_col("SELECT DISTINCT category FROM $help_tbl ORDER BY category ASC");

?>
<div class="wrap">
    <h1 class="wp-heading-inline">❓ Help Topics</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_help_topics')); ?>" class="page-title-action">+ Add New Topic</a>
    <hr class="wp-header-end">

    <p style="margin-bottom:20px;color:#555;">
        Help topics are shown on the member-facing <strong>Help Center</strong> page (<code>[mmgr_member_help]</code>).
        Members can search by keyword or browse by category.
    </p>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;margin-top:20px;">

        <!-- Add / Edit Form -->
        <div>
            <div class="card" style="padding:20px;">
                <h2 style="margin-top:0;"><?php echo $edit_topic ? 'Edit Help Topic' : 'Add New Help Topic'; ?></h2>

                <form method="POST">
                    <?php wp_nonce_field('mmgr_save_help_topic', 'help_topic_nonce'); ?>
                    <?php if ($edit_topic): ?>
                        <input type="hidden" name="topic_id" value="<?php echo intval($edit_topic['id']); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="topic_title">Question / Title *</label></th>
                            <td>
                                <input type="text" id="topic_title" name="topic_title"
                                       value="<?php echo $edit_topic ? esc_attr($edit_topic['title']) : ''; ?>"
                                       required class="large-text" placeholder="e.g. How do I reset my password?">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="topic_content">Answer / Content *</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    $edit_topic ? wp_kses_post($edit_topic['content']) : '',
                                    'topic_content',
                                    array(
                                        'textarea_name' => 'topic_content',
                                        'textarea_rows' => 10,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'tinymce'       => true,
                                        'quicktags'     => true,
                                    )
                                );
                                ?>
                                <p class="description">Use the editor above to format your answer and insert images from the Media Library.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="topic_category">Category</label></th>
                            <td>
                                <input type="text" id="topic_category" name="topic_category"
                                       value="<?php echo $edit_topic ? esc_attr($edit_topic['category']) : 'General'; ?>"
                                       class="regular-text" list="mmgr-existing-categories"
                                       placeholder="e.g. Account, Events, Community">
                                <datalist id="mmgr-existing-categories">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <p class="description">Used to group topics on the help page. Type a new name or pick an existing one.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sort_order">Sort Order</label></th>
                            <td>
                                <input type="number" id="sort_order" name="sort_order"
                                       value="<?php echo $edit_topic ? intval($edit_topic['sort_order']) : 0; ?>"
                                       class="small-text">
                                <p class="description">Lower numbers appear first. Topics with the same order are sorted alphabetically.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Active</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="active" value="1"
                                           <?php checked(!$edit_topic || $edit_topic['active']); ?>>
                                    Show this topic to members
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="save_help_topic" class="button button-primary">
                            <?php echo $edit_topic ? 'Update Topic' : 'Create Topic'; ?>
                        </button>
                        <?php if ($edit_topic): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=membership_help_topics')); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- Topics List -->
        <div>
            <h2>Existing Help Topics</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title / Question</th>
                        <th style="width:120px;">Category</th>
                        <th style="width:60px;">Order</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topics)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;color:#666;">
                                No help topics yet. Create your first topic!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($topic['title']); ?></strong>
                                    <div style="font-size:12px;color:#666;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;">
                                        <?php echo esc_html(wp_strip_all_tags($topic['content'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background:#f0f0f0;padding:2px 8px;border-radius:10px;font-size:12px;">
                                        <?php echo esc_html($topic['category']); ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?php echo intval($topic['sort_order']); ?></td>
                                <td>
                                    <?php if ($topic['active']): ?>
                                        <span style="color:#00a32a;font-weight:bold;">● Active</span>
                                    <?php else: ?>
                                        <span style="color:#999;">○ Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=membership_help_topics&edit=' . intval($topic['id']))); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=membership_help_topics&delete=' . intval($topic['id'])), 'delete_help_topic_' . intval($topic['id']))); ?>"
                                       class="button button-small button-link-delete"
                                       style="color:#d63638;"
                                       onclick="return confirm('Delete this help topic?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:30px;padding:15px;background:#f0f6fc;border-left:4px solid #0073aa;border-radius:4px;">
                <h3 style="margin-top:0;">ℹ️ How the Help Center Works</h3>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                    <li>Add the shortcode <code>[mmgr_member_help]</code> to any WordPress page to display the member-facing help/FAQ page.</li>
                    <li>Members can <strong>search by keyword</strong> — matching questions and answers are highlighted in real time.</li>
                    <li>Topics are grouped by <strong>Category</strong> and displayed as an expandable accordion.</li>
                    <li>Only <strong>Active</strong> topics are shown to members.</li>
                    <li>A <strong>❓ help icon</strong> is shown in the top navigation bar so members can reach the help page at any time.</li>
                    <li>Use the <strong>Add Media</strong> button in the editor to upload and insert images directly into help topic answers.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
