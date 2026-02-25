<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$tbl = $wpdb->prefix . 'membership_fees';

// Handle save/delete
if (isset($_POST['save_fee'])) {
    $data = array(
        'fee_date' => sanitize_text_field($_POST['fee_date']),
        'fee_amount' => floatval($_POST['fee_amount']),
        'event_name' => sanitize_text_field($_POST['event_name']),
        'description' => sanitize_textarea_field($_POST['description'])
    );
    
    if (isset($_POST['fee_id']) && !empty($_POST['fee_id'])) {
        $wpdb->update($tbl, $data, array('id' => intval($_POST['fee_id'])));
    } else {
        $wpdb->insert($tbl, $data);
    }
    echo '<div class="notice notice-success"><p>Special fee saved!</p></div>';
}

if (isset($_GET['delete'])) {
    $wpdb->delete($tbl, array('id' => intval($_GET['delete'])));
    echo '<div class="notice notice-success"><p>Special fee deleted!</p></div>';
}

$editing = isset($_GET['edit']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id = %d", intval($_GET['edit'])), ARRAY_A) : null;
$fees = $wpdb->get_results("SELECT * FROM $tbl ORDER BY fee_date DESC", ARRAY_A);

?>
<div class="wrap">
    <h1>Special Event Fees</h1>
    
    <p>Set special fees for specific dates (e.g., tournaments, events). These will override the normal daily fee.</p>
    
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:30px;">
        <div>
            <h2><?php echo $editing ? 'Edit Fee' : 'Add Special Fee'; ?></h2>
            <form method="post">
                <?php if ($editing): ?>
                    <input type="hidden" name="fee_id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label>Date</label></th>
                        <td><input type="date" name="fee_date" value="<?php echo esc_attr($editing['fee_date'] ?? ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Fee Amount</label></th>
                        <td><input type="number" name="fee_amount" step="0.01" value="<?php echo esc_attr($editing['fee_amount'] ?? ''); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Event Name</label></th>
                        <td><input type="text" name="event_name" class="regular-text" value="<?php echo esc_attr($editing['event_name'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label>Description</label></th>
                        <td><textarea name="description" class="large-text" rows="3"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea></td>
                    </tr>
                </table>
                
                <p>
                    <button type="submit" name="save_fee" class="button button-primary"><?php echo $editing ? 'Update' : 'Add'; ?> Fee</button>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_special_fees'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div>
            <h2>Scheduled Special Fees</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Fee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fees)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:#666;">No special fees scheduled.</td></tr>
                    <?php else: ?>
                        <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($fee['fee_date'])); ?></td>
                                <td><strong><?php echo esc_html($fee['event_name']); ?></strong></td>
                                <td>$<?php echo number_format($fee['fee_amount'], 2); ?></td>
                                <td>
                                    <a href="?page=membership_special_fees&edit=<?php echo $fee['id']; ?>" class="button button-small">Edit</a>
                                    <a href="?page=membership_special_fees&delete=<?php echo $fee['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('Delete?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>