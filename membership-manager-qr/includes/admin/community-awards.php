<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$awards_tbl = $wpdb->prefix . 'membership_community_awards';

// Ensure table exists (in case migration hasn't run yet)
if ($wpdb->get_var("SHOW TABLES LIKE '$awards_tbl'") !== $awards_tbl) {
    mmgr_migrate_community_awards();
}

// Handle add/edit award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_award'])) {
    if (!isset($_POST['award_nonce']) || !wp_verify_nonce($_POST['award_nonce'], 'mmgr_save_award')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $award_id      = isset($_POST['award_id']) ? intval($_POST['award_id']) : 0;
        $award_name    = sanitize_text_field($_POST['award_name']);
        $award_icon    = sanitize_text_field($_POST['award_icon']);
        $criteria_type = sanitize_text_field($_POST['criteria_type']);
        $min_threshold = absint($_POST['min_threshold']);
        $max_threshold = (isset($_POST['max_threshold']) && $_POST['max_threshold'] !== '') ? absint($_POST['max_threshold']) : null;
        $sort_order    = intval($_POST['sort_order']);
        $active        = isset($_POST['active']) ? 1 : 0;

        // Validate criteria type
        $allowed_types = array('visits', 'likes', 'posts');
        if (!in_array($criteria_type, $allowed_types, true)) {
            $criteria_type = 'visits';
        }

        $data = array(
            'award_name'    => $award_name,
            'award_icon'    => $award_icon,
            'criteria_type' => $criteria_type,
            'min_threshold' => $min_threshold,
            'max_threshold' => $max_threshold,
            'sort_order'    => $sort_order,
            'active'        => $active,
        );

        if ($award_id > 0) {
            $wpdb->update($awards_tbl, $data, array('id' => $award_id));
            echo '<div class="notice notice-success"><p>Award updated successfully!</p></div>';
        } else {
            $wpdb->insert($awards_tbl, $data);
            echo '<div class="notice notice-success"><p>Award created successfully!</p></div>';
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_award_' . intval($_GET['delete']))) {
    $wpdb->delete($awards_tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Award deleted.</p></div>';
}

// Get all awards
$awards = $wpdb->get_results("SELECT * FROM $awards_tbl ORDER BY sort_order, criteria_type, min_threshold", ARRAY_A);

// Edit mode
$edit_award = null;
if (isset($_GET['edit'])) {
    $edit_award = $wpdb->get_row($wpdb->prepare("SELECT * FROM $awards_tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

$criteria_labels = array(
    'visits' => '🎟️ Live Event Visits',
    'likes'  => '❤️ Likes Received',
    'posts'  => '💬 Forum Posts & Comments',
);

?>
<div class="wrap">
    <h1 class="wp-heading-inline">🏅 Community Awards</h1>
    <hr class="wp-header-end">

    <p style="margin-bottom:20px;color:#555;">
        Community awards are automatically assigned to members based on their activity. Awards appear as icons
        next to a member's alias in the directory and on their profile page.
    </p>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;margin-top:20px;">
        <!-- Add/Edit Form -->
        <div>
            <div class="card" style="padding:20px;">
                <h2><?php echo $edit_award ? 'Edit Award' : 'Add New Award'; ?></h2>

                <form method="POST">
                    <?php wp_nonce_field('mmgr_save_award', 'award_nonce'); ?>
                    <?php if ($edit_award): ?>
                        <input type="hidden" name="award_id" value="<?php echo intval($edit_award['id']); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="award_name">Award Name *</label></th>
                            <td>
                                <input type="text" id="award_name" name="award_name"
                                       value="<?php echo $edit_award ? esc_attr($edit_award['award_name']) : ''; ?>"
                                       required class="regular-text" placeholder="e.g. NEWBIE, GREENHORN">
                                <p class="description">Label shown on the award badge.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="award_icon">Icon *</label></th>
                            <td>
                                <input type="text" id="award_icon" name="award_icon"
                                       value="<?php echo $edit_award ? esc_attr($edit_award['award_icon']) : '🏅'; ?>"
                                       required class="regular-text" placeholder="🏅 or image URL">
                                <p class="description">Enter an emoji (e.g. 🌱 ⭐ 🏆 🔥) or a full image URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="criteria_type">Criteria *</label></th>
                            <td>
                                <select id="criteria_type" name="criteria_type">
                                    <?php foreach ($criteria_labels as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>"
                                            <?php selected($edit_award ? $edit_award['criteria_type'] : 'visits', $val); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Which metric triggers this award.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="min_threshold">Minimum Count *</label></th>
                            <td>
                                <input type="number" id="min_threshold" name="min_threshold" min="0"
                                       value="<?php echo $edit_award ? intval($edit_award['min_threshold']) : 0; ?>"
                                       required class="small-text">
                                <p class="description">Minimum number of visits/likes/posts to earn this award.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="max_threshold">Maximum Count</label></th>
                            <td>
                                <input type="number" id="max_threshold" name="max_threshold" min="0"
                                       value="<?php echo ($edit_award && $edit_award['max_threshold'] !== null) ? intval($edit_award['max_threshold']) : ''; ?>"
                                       class="small-text" placeholder="—">
                                <p class="description">Leave blank for no upper limit (award applies to everyone above the minimum).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sort_order">Sort Order</label></th>
                            <td>
                                <input type="number" id="sort_order" name="sort_order"
                                       value="<?php echo $edit_award ? intval($edit_award['sort_order']) : 0; ?>"
                                       class="small-text">
                                <p class="description">Lower numbers appear first when multiple awards are displayed.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Active</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="active" value="1"
                                           <?php checked(!$edit_award || $edit_award['active']); ?>>
                                    Show this award to members
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="save_award" class="button button-primary">
                            <?php echo $edit_award ? 'Update Award' : 'Create Award'; ?>
                        </button>
                        <?php if ($edit_award): ?>
                            <a href="<?php echo admin_url('admin.php?page=membership_community_awards'); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- Awards List -->
        <div>
            <h2>Existing Awards</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:60px;">Icon</th>
                        <th>Name</th>
                        <th>Criteria</th>
                        <th>Range</th>
                        <th style="width:70px;">Sort</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($awards)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:#666;">
                                No awards defined yet. Create your first award!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($awards as $award): ?>
                            <tr>
                                <td style="text-align:center;">
                                    <?php
                                    $icon = $award['award_icon'];
                                    if ( filter_var( $icon, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) parse_url( $icon, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
                                        echo '<img src="' . esc_url($icon) . '" style="width:32px;height:32px;object-fit:contain;" alt="' . esc_attr($award['award_name']) . '">';
                                    } else {
                                        echo '<span style="font-size:24px;">' . esc_html($icon) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><strong><?php echo esc_html($award['award_name']); ?></strong></td>
                                <td><?php echo esc_html($criteria_labels[$award['criteria_type']] ?? $award['criteria_type']); ?></td>
                                <td>
                                    <?php
                                    $min = intval($award['min_threshold']);
                                    $max = ($award['max_threshold'] !== null) ? intval($award['max_threshold']) : null;
                                    if ($max !== null) {
                                        echo esc_html($min . ' – ' . $max);
                                    } else {
                                        echo esc_html($min . '+');
                                    }
                                    ?>
                                </td>
                                <td style="text-align:center;"><?php echo intval($award['sort_order']); ?></td>
                                <td>
                                    <?php if ($award['active']): ?>
                                        <span style="color:#00a32a;font-weight:bold;">● Active</span>
                                    <?php else: ?>
                                        <span style="color:#999;">○ Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=membership_community_awards&edit=' . intval($award['id'])); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_community_awards&delete=' . intval($award['id'])), 'delete_award_' . intval($award['id'])); ?>"
                                       class="button button-small button-link-delete"
                                       style="color:#d63638;"
                                       onclick="return confirm('Delete this award?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:30px;padding:15px;background:#f0f6fc;border-left:4px solid #0073aa;border-radius:4px;">
                <h3 style="margin-top:0;">ℹ️ How Awards Work</h3>
                <ul style="list-style:disc;margin-left:20px;line-height:1.8;">
                    <li><strong>🎟️ Live Event Visits</strong> – Total number of times the member has checked in to a live event.</li>
                    <li><strong>❤️ Likes Received</strong> – Total number of likes (profile, photo, and post likes) the member has received from others.</li>
                    <li><strong>💬 Forum Posts &amp; Comments</strong> – Total number of forum posts and comments the member has made.</li>
                </ul>
                <p style="margin-bottom:0;">A member earns an award when their count falls within the configured range. Awards are displayed as icons in the member directory and on their profile page.</p>
            </div>
        </div>
    </div>
</div>
