<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$tbl = $wpdb->prefix . 'membership_levels';

// Handle add/edit/delete
if (isset($_POST['save_level'])) {
    $data = array(
        'level_name' => sanitize_text_field($_POST['level_name']),
        'price' => floatval($_POST['price']),
        'daily_fee' => floatval($_POST['daily_fee']),
        'description' => sanitize_textarea_field($_POST['description'])
    );
    
    if (isset($_POST['level_id']) && !empty($_POST['level_id'])) {
        $wpdb->update($tbl, $data, array('id' => intval($_POST['level_id'])));
        echo '<div class="notice notice-success"><p>Level updated!</p></div>';
    } else {
        $wpdb->insert($tbl, $data);
        echo '<div class="notice notice-success"><p>Level added!</p></div>';
    }
}

if (isset($_GET['delete'])) {
    $wpdb->delete($tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Level deleted!</p></div>';
}

$editing = isset($_GET['edit']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A) : null;
$levels = $wpdb->get_results("SELECT * FROM $tbl ORDER BY level_name", ARRAY_A);

?>
<div class="wrap">
    <h1>Membership Levels</h1>
    
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;">
        <!-- Add/Edit Form -->
        <div>
            <h2><?php echo $editing ? 'Edit Level' : 'Add New Level'; ?></h2>
            <form method="post">
                <?php if ($editing): ?>
                    <input type="hidden" name="level_id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label>Level Name</label></th>
                        <td><input type="text" name="level_name" class="regular-text" value="<?php echo esc_attr($editing['level_name'] ?? ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Price</label></th>
                        <td><input type="number" name="price" step="0.01" value="<?php echo esc_attr($editing['price'] ?? ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Daily Fee</label></th>
                        <td><input type="number" name="daily_fee" step="0.01" value="<?php echo esc_attr($editing['daily_fee'] ?? '5.00'); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Description</label></th>
                        <td><textarea name="description" class="large-text" rows="3"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea></td>
                    </tr>
                </table>
                
                <p>
                    <button type="submit" name="save_level" class="button button-primary"><?php echo $editing ? 'Update' : 'Add'; ?> Level</button>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_levels'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <!-- Levels List -->
        <div>
            <h2>Current Levels</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Level Name</th>
                        <th>Price</th>
                        <th>Daily Fee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($levels as $level): ?>
                        <tr>
                            <td><strong><?php echo esc_html($level['level_name']); ?></strong></td>
                            <td>$<?php echo number_format($level['price'], 2); ?></td>
                            <td>$<?php echo number_format($level['daily_fee'], 2); ?></td>
                            <td>
                                <a href="?page=membership_levels&edit=<?php echo $level['id']; ?>" class="button button-small">Edit</a>
                                <a href="?page=membership_levels&delete=<?php echo $level['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this level?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>