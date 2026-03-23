<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$items_tbl = $wpdb->prefix . 'membership_orientation_items';
$comp_tbl  = $wpdb->prefix . 'membership_orientation_completions';

$success = $error = '';

// ── Save (add / edit) ──────────────────────────────────────────────────────
if (isset($_POST['save_orientation_item']) && wp_verify_nonce($_POST['orientation_item_nonce'], 'mmgr_save_orientation_item')) {
    $title      = sanitize_text_field($_POST['title']);
    $sort_order = intval($_POST['sort_order']);
    $active     = isset($_POST['active']) ? 1 : 0;
    $item_id    = intval($_POST['item_id']);

    if (empty($title)) {
        $error = 'Checklist item text is required.';
    } elseif ($item_id) {
        $wpdb->update(
            $items_tbl,
            array('title' => $title, 'sort_order' => $sort_order, 'active' => $active),
            array('id' => $item_id)
        );
        $success = 'Item updated successfully.';
    } else {
        $wpdb->insert(
            $items_tbl,
            array('title' => $title, 'sort_order' => $sort_order, 'active' => $active)
        );
        $success = 'Item added. It will appear on the next check-in for any member who has not yet completed it.';
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_orientation_item_' . intval($_GET['delete']))) {
    $del_id = intval($_GET['delete']);
    $wpdb->delete($items_tbl, array('id' => $del_id));
    // Remove completions for this item so no orphan records
    $wpdb->delete($comp_tbl, array('item_id' => $del_id));
    $success = 'Item deleted.';
}

// ── Fetch all items ───────────────────────────────────────────────────────
$items = $wpdb->get_results("SELECT * FROM `$items_tbl` ORDER BY sort_order ASC, id ASC", ARRAY_A);

// ── Edit mode ─────────────────────────────────────────────────────────────
$editing = null;
if (isset($_GET['edit'])) {
    $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$items_tbl` WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

$sort_orders = count($items) > 0 ? array_column($items, 'sort_order') : array();
$next_order  = $sort_orders ? (max($sort_orders) + 10) : 10;
?>
<div class="wrap">
    <h1>Orientation Checklist Items</h1>
    <p class="description" style="font-size:14px;margin-bottom:20px;">
        These items appear as a tablet-friendly checklist for staff during member orientation. Staff check each one off as they walk the member through it. Adding a new item here automatically prompts staff to cover it with <strong>every existing member</strong> on their next visit.
    </p>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($success); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:380px 1fr;gap:30px;margin-top:20px;align-items:start;">

        <!-- ── Form ───────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:6px;">
            <h2 style="margin-top:0;"><?php echo $editing ? 'Edit Item' : 'Add New Item'; ?></h2>
            <form method="POST">
                <?php wp_nonce_field('mmgr_save_orientation_item', 'orientation_item_nonce'); ?>
                <input type="hidden" name="item_id" value="<?php echo $editing ? intval($editing['id']) : 0; ?>">

                <div style="margin-bottom:15px;">
                    <label style="display:block;font-weight:bold;margin-bottom:6px;">Checklist Item Text *</label>
                    <textarea name="title" rows="3"
                              style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;"
                              required><?php echo $editing ? esc_textarea($editing['title']) : ''; ?></textarea>
                    <p class="description" style="margin-top:4px;">This text is shown to staff on the check-in tablet.</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px;">
                    <div>
                        <label style="display:block;font-weight:bold;margin-bottom:6px;">Sort Order</label>
                        <input type="number" name="sort_order"
                               value="<?php echo $editing ? intval($editing['sort_order']) : $next_order; ?>"
                               style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                        <p class="description" style="margin-top:4px;">Lower numbers appear first.</p>
                    </div>
                    <div style="padding-top:26px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                            <input type="checkbox" name="active" value="1"
                                   <?php checked($editing ? intval($editing['active']) : 1, 1); ?>>
                            Active
                        </label>
                        <p class="description" style="margin-top:4px;">Inactive items are hidden from staff.</p>
                    </div>
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" name="save_orientation_item" class="button button-primary">
                        <?php echo $editing ? '💾 Update Item' : '➕ Add Item'; ?>
                    </button>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_orientation'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── List ───────────────────────────────────────────────── -->
        <div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Checklist Item</th>
                        <th style="width:60px;text-align:center;">Order</th>
                        <th style="width:80px;text-align:center;">Status</th>
                        <th style="width:120px;text-align:center;">Completions</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:30px;color:#666;">
                                No items yet — add one using the form.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item):
                            $completions = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM `$comp_tbl` WHERE item_id = %d",
                                $item['id']
                            ));
                        ?>
                            <tr>
                                <td style="padding:10px 8px;">
                                    <?php echo esc_html($item['title']); ?>
                                </td>
                                <td style="text-align:center;"><?php echo intval($item['sort_order']); ?></td>
                                <td style="text-align:center;">
                                    <?php if ($item['active']): ?>
                                        <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Active</span>
                                    <?php else: ?>
                                        <span style="background:#999;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-size:13px;color:#444;">
                                    <?php echo number_format($completions); ?> member<?php echo $completions !== 1 ? 's' : ''; ?>
                                </td>
                                <td style="padding:8px;">
                                    <a href="<?php echo admin_url('admin.php?page=membership_orientation&edit=' . intval($item['id'])); ?>"
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(
                                            admin_url('admin.php?page=membership_orientation&delete=' . intval($item['id'])),
                                            'delete_orientation_item_' . intval($item['id'])
                                        ); ?>"
                                       class="button button-small"
                                       style="color:#d63638;border-color:#d63638;"
                                       onclick="return confirm('Delete this orientation item?\n\nMembers who already completed it will retain their completion records, but it will no longer appear in new orientations.');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:15px;padding:14px 16px;background:#f0f8ff;border-left:4px solid #0073aa;border-radius:4px;font-size:13px;line-height:1.6;">
                <strong>ℹ️ How it works:</strong>
                <ul style="margin:6px 0 0 18px;padding:0;">
                    <li>When a member checks in, any active items they haven't completed yet appear as a checklist.</li>
                    <li>Staff take the tablet with them and check each item off during the walkthrough.</li>
                    <li>Each completion is saved instantly via AJAX and logged on the member's account.</li>
                    <li><strong>Adding a new item here will automatically prompt staff to cover it with every existing member on their next visit</strong>, no matter how long they've been a member.</li>
                    <li>Deactivating an item hides it from new check-ins but does not erase past completion records.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
