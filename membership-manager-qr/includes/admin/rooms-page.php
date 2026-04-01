<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$rooms_tbl = $wpdb->prefix . 'membership_rooms';

$success = $error = '';

// ── Save (add / edit) ─────────────────────────────────────────────────────
if (isset($_POST['save_room']) && wp_verify_nonce($_POST['room_nonce'], 'mmgr_save_room')) {
    $room_name  = sanitize_text_field($_POST['room_name']);
    $sort_order = intval($_POST['sort_order']);
    $active     = isset($_POST['active']) ? 1 : 0;
    $room_id    = intval($_POST['room_id']);

    if (empty($room_name)) {
        $error = 'Room name is required.';
    } elseif ($room_id) {
        $wpdb->update(
            $rooms_tbl,
            array('room_name' => $room_name, 'sort_order' => $sort_order, 'active' => $active),
            array('id' => $room_id)
        );
        $success = 'Room updated.';
    } else {
        $wpdb->insert($rooms_tbl, array(
            'room_name'  => $room_name,
            'sort_order' => $sort_order,
            'active'     => $active,
        ));
        $success = 'Room added.';
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_room_' . intval($_GET['delete']))) {
    $del_id = intval($_GET['delete']);
    $wpdb->delete($rooms_tbl, array('id' => $del_id));
    $success = 'Room deleted.';
}

// ── Fetch ─────────────────────────────────────────────────────────────────
$rooms   = $wpdb->get_results("SELECT * FROM `$rooms_tbl` ORDER BY sort_order ASC, id ASC", ARRAY_A);
$editing = null;
if (isset($_GET['edit'])) {
    $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$rooms_tbl` WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

$sort_orders = count($rooms) > 0 ? array_column($rooms, 'sort_order') : array();
$next_order  = $sort_orders ? (max($sort_orders) + 10) : 10;
?>
<div class="wrap">
    <h1>Rooms Management</h1>
    <p class="description" style="font-size:14px;margin-bottom:20px;">
        These rooms appear on the staff check-in page when a staff member taps <strong>Cleaning Log</strong>.
        Staff can tap a room to log that they cleaned it.
    </p>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($success); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:320px 1fr;gap:30px;margin-top:20px;align-items:start;">

        <!-- ── Form ───────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:6px;">
            <h2 style="margin-top:0;"><?php echo $editing ? 'Edit Room' : 'Add New Room'; ?></h2>
            <form method="POST">
                <?php wp_nonce_field('mmgr_save_room', 'room_nonce'); ?>
                <input type="hidden" name="room_id" value="<?php echo $editing ? intval($editing['id']) : 0; ?>">

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:bold;margin-bottom:4px;">Room Name *</label>
                    <input type="text" name="room_name" required
                           placeholder="e.g. Locker Room A, Main Hall, Restrooms"
                           value="<?php echo $editing ? esc_attr($editing['room_name']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-weight:bold;margin-bottom:4px;">Sort Order</label>
                        <input type="number" name="sort_order"
                               value="<?php echo $editing ? intval($editing['sort_order']) : $next_order; ?>"
                               style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                        <p class="description" style="margin-top:3px;font-size:11px;">Lower = appears first.</p>
                    </div>
                    <div style="padding-top:24px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                            <input type="checkbox" name="active" value="1"
                                   <?php checked($editing ? intval($editing['active']) : 1, 1); ?>>
                            Active
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" name="save_room" class="button button-primary">
                        <?php echo $editing ? '💾 Update Room' : '➕ Add Room'; ?>
                    </button>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_rooms'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── List ───────────────────────────────────────────────── -->
        <div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Room Name</th>
                        <th style="width:70px;text-align:center;">Order</th>
                        <th style="width:90px;text-align:center;">Status</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:#666;">No rooms yet — add one using the form.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td style="padding:10px 8px;"><strong><?php echo esc_html($room['room_name']); ?></strong></td>
                            <td style="text-align:center;"><?php echo intval($room['sort_order']); ?></td>
                            <td style="text-align:center;">
                                <?php if ($room['active']): ?>
                                    <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Active</span>
                                <?php else: ?>
                                    <span style="background:#999;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;">
                                <a href="<?php echo admin_url('admin.php?page=membership_rooms&edit=' . intval($room['id'])); ?>"
                                   class="button button-small">Edit</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_rooms&delete=' . intval($room['id'])), 'delete_room_' . intval($room['id'])); ?>"
                                   class="button button-small"
                                   style="color:#d63638;border-color:#d63638;"
                                   onclick="return confirm('Delete room \'<?php echo esc_js($room['room_name']); ?>\'?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
