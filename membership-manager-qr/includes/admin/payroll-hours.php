<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$staff_tbl    = $wpdb->prefix . 'membership_staff';
$time_tbl     = $wpdb->prefix . 'membership_staff_time_logs';

$success = $error = '';

// ── Mark entry as paid ────────────────────────────────────────────────────
if (isset($_POST['mmgr_mark_paid_entry']) && wp_verify_nonce($_POST['payroll_nonce'], 'mmgr_payroll')) {
    $entry_id = intval($_POST['entry_id']);
    $paid     = isset($_POST['paid']) ? 1 : 0;
    $wpdb->update($time_tbl, array('paid' => $paid), array('id' => $entry_id));
    $success = $paid ? 'Entry marked as paid.' : 'Entry marked as unpaid.';
}

// ── Mark ALL unpaid entries for a staff member as paid ────────────────────
if (isset($_POST['mmgr_mark_staff_paid']) && wp_verify_nonce($_POST['payroll_nonce'], 'mmgr_payroll')) {
    $target_staff_id = intval($_POST['target_staff_id']);
    $wpdb->update($time_tbl, array('paid' => 1), array('staff_id' => $target_staff_id, 'paid' => 0));
    $success = 'All unpaid hours for this staff member marked as paid.';
}

// ── Save edited entry ─────────────────────────────────────────────────────
if (isset($_POST['mmgr_save_time_entry']) && wp_verify_nonce($_POST['payroll_nonce'], 'mmgr_payroll')) {
    $entry_id      = intval($_POST['entry_id']);
    $clock_in_raw  = sanitize_text_field($_POST['clock_in']);
    $clock_out_raw = sanitize_text_field($_POST['clock_out']);
    $notes         = sanitize_textarea_field($_POST['notes']);

    $clock_in  = !empty($clock_in_raw)  ? date('Y-m-d H:i:s', strtotime($clock_in_raw))  : null;
    $clock_out = !empty($clock_out_raw) ? date('Y-m-d H:i:s', strtotime($clock_out_raw)) : null;

    if (!$clock_in) {
        $error = 'Clock-in time is required.';
    } else {
        $update_data = array('clock_in' => $clock_in, 'notes' => $notes);
        if ($clock_out) {
            $update_data['clock_out'] = $clock_out;
        } else {
            // Allow clearing clock_out (staff forgot to clock out – leave open)
            $update_data['clock_out'] = null;
        }
        $wpdb->update($time_tbl, $update_data, array('id' => $entry_id));
        $success = 'Time entry updated.';
    }
}

// ── Add new entry ─────────────────────────────────────────────────────────
if (isset($_POST['mmgr_add_time_entry']) && wp_verify_nonce($_POST['payroll_nonce'], 'mmgr_payroll')) {
    $add_staff_id  = intval($_POST['add_staff_id']);
    $clock_in_raw  = sanitize_text_field($_POST['add_clock_in']);
    $clock_out_raw = sanitize_text_field($_POST['add_clock_out']);
    $notes         = sanitize_textarea_field($_POST['add_notes']);

    $clock_in  = !empty($clock_in_raw)  ? date('Y-m-d H:i:s', strtotime($clock_in_raw))  : null;
    $clock_out = !empty($clock_out_raw) ? date('Y-m-d H:i:s', strtotime($clock_out_raw)) : null;

    if (!$add_staff_id || !$clock_in) {
        $error = 'Staff member and clock-in time are required.';
    } else {
        $insert_data = array('staff_id' => $add_staff_id, 'clock_in' => $clock_in, 'notes' => $notes);
        if ($clock_out) $insert_data['clock_out'] = $clock_out;
        $wpdb->insert($time_tbl, $insert_data);
        $success = 'Time entry added.';
    }
}

// ── Delete entry ──────────────────────────────────────────────────────────
if (isset($_GET['delete_entry']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_time_entry_' . intval($_GET['delete_entry']))) {
    $wpdb->delete($time_tbl, array('id' => intval($_GET['delete_entry'])));
    $success = 'Time entry deleted.';
}

// ── Filters ───────────────────────────────────────────────────────────────
$filter_staff = isset($_GET['filter_staff']) ? intval($_GET['filter_staff']) : 0;
$filter_paid  = isset($_GET['filter_paid'])  ? sanitize_text_field($_GET['filter_paid']) : 'unpaid';

$edit_entry_id = isset($_GET['edit_entry']) ? intval($_GET['edit_entry']) : 0;
$editing_entry = $edit_entry_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `$time_tbl` WHERE id = %d", $edit_entry_id), ARRAY_A) : null;

// ── All staff (for filter & add form) ────────────────────────────────────
$all_staff = $wpdb->get_results("SELECT id, name FROM `$staff_tbl` WHERE active = 1 ORDER BY name ASC", ARRAY_A);

// ── Build query ───────────────────────────────────────────────────────────
$where_parts = array();
$where_vals  = array();

if ($filter_staff) {
    $where_parts[] = 'l.staff_id = %d';
    $where_vals[]  = $filter_staff;
}
if ($filter_paid === 'unpaid') {
    $where_parts[] = 'l.paid = 0';
} elseif ($filter_paid === 'paid') {
    $where_parts[] = 'l.paid = 1';
}

$where_sql = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
$query_sql = "SELECT l.*, s.name AS staff_name
              FROM `$time_tbl` l
              JOIN `$staff_tbl` s ON s.id = l.staff_id
              $where_sql
              ORDER BY l.clock_in DESC";

$entries = $where_vals
    ? $wpdb->get_results($wpdb->prepare($query_sql, ...$where_vals), ARRAY_A)
    : $wpdb->get_results($query_sql, ARRAY_A);

// ── Helper: round to 15 min and format ───────────────────────────────────
function mmgr_ph_format($minutes) {
    $minutes = (int) $minutes;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0 && $m > 0) return $h . 'h ' . $m . 'm';
    if ($h > 0)            return $h . 'h';
    return $m . 'm';
}

function mmgr_ph_round15($raw_minutes) {
    return (int) round($raw_minutes / 15) * 15;
}
?>
<div class="wrap">
    <h1>Payroll Hours</h1>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($success); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- ── Filters ───────────────────────────────────────────────────── -->
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:16px 0;">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="page" value="membership_payroll_hours">

            <div>
                <label style="display:block;font-weight:600;font-size:13px;margin-bottom:3px;">Staff Member</label>
                <select name="filter_staff" style="padding:6px;border:1px solid #8c8f94;border-radius:4px;">
                    <option value="0">— All Staff —</option>
                    <?php foreach ($all_staff as $s): ?>
                        <option value="<?php echo intval($s['id']); ?>" <?php selected($filter_staff, $s['id']); ?>>
                            <?php echo esc_html($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block;font-weight:600;font-size:13px;margin-bottom:3px;">Status</label>
                <select name="filter_paid" style="padding:6px;border:1px solid #8c8f94;border-radius:4px;">
                    <option value="all"    <?php selected($filter_paid,'all');    ?>>All</option>
                    <option value="unpaid" <?php selected($filter_paid,'unpaid'); ?>>Unpaid only</option>
                    <option value="paid"   <?php selected($filter_paid,'paid');   ?>>Paid only</option>
                </select>
            </div>

            <button type="submit" class="button">Filter</button>
        </form>
    </div>

    <!-- ── Add New Entry ─────────────────────────────────────────────── -->
    <details style="margin-bottom:20px;">
        <summary style="cursor:pointer;font-weight:600;font-size:14px;padding:10px;background:#f0f0f0;border:1px solid #ddd;border-radius:4px;">➕ Add Missing Time Entry</summary>
        <div style="background:#fff;border:1px solid #ddd;border-top:none;padding:20px;border-radius:0 0 4px 4px;">
            <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;align-items:end;">
                <?php wp_nonce_field('mmgr_payroll', 'payroll_nonce'); ?>
                <div>
                    <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Staff Member *</label>
                    <select name="add_staff_id" required style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
                        <option value="">— Select —</option>
                        <?php foreach ($all_staff as $s): ?>
                            <option value="<?php echo intval($s['id']); ?>"><?php echo esc_html($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Clock In *</label>
                    <input type="datetime-local" name="add_clock_in" required style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
                </div>
                <div>
                    <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Clock Out</label>
                    <input type="datetime-local" name="add_clock_out" style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
                </div>
                <div>
                    <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Notes</label>
                    <input type="text" name="add_notes" placeholder="Optional note" style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
                </div>
                <div style="padding-top:22px;">
                    <button type="submit" name="mmgr_add_time_entry" class="button button-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </details>

    <!-- ── Edit Modal ────────────────────────────────────────────────── -->
    <?php if ($editing_entry): ?>
    <div style="background:#fff;border:2px solid #0073aa;border-radius:6px;padding:20px;margin-bottom:20px;">
        <h3 style="margin-top:0;">✏️ Edit Time Entry #<?php echo intval($editing_entry['id']); ?></h3>
        <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;align-items:end;">
            <?php wp_nonce_field('mmgr_payroll', 'payroll_nonce'); ?>
            <input type="hidden" name="entry_id" value="<?php echo intval($editing_entry['id']); ?>">
            <div>
                <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Clock In *</label>
                <input type="datetime-local" name="clock_in" required
                       value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($editing_entry['clock_in']))); ?>"
                       style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
            </div>
            <div>
                <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Clock Out</label>
                <input type="datetime-local" name="clock_out"
                       value="<?php echo $editing_entry['clock_out'] ? esc_attr(date('Y-m-d\TH:i', strtotime($editing_entry['clock_out']))) : ''; ?>"
                       style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
                <p class="description" style="font-size:11px;margin-top:2px;">Leave blank if still clocked in.</p>
            </div>
            <div>
                <label style="display:block;font-weight:bold;margin-bottom:4px;font-size:13px;">Notes</label>
                <input type="text" name="notes" value="<?php echo esc_attr($editing_entry['notes']); ?>"
                       style="width:100%;padding:7px;border:1px solid #8c8f94;border-radius:4px;">
            </div>
            <div style="padding-top:22px;display:flex;gap:8px;">
                <button type="submit" name="mmgr_save_time_entry" class="button button-primary">💾 Save</button>
                <a href="<?php echo admin_url('admin.php?page=membership_payroll_hours' . ($filter_staff ? '&filter_staff=' . $filter_staff : '') . '&filter_paid=' . urlencode($filter_paid)); ?>" class="button">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Entries Table ─────────────────────────────────────────────── -->
    <?php if (empty($entries)): ?>
        <p style="color:#666;padding:20px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;text-align:center;">No time entries found for the selected filters.</p>
    <?php else: ?>

        <?php
        // Group by staff for totals display
        $by_staff = array();
        foreach ($entries as $e) {
            $by_staff[$e['staff_id']]['name']    = $e['staff_name'];
            $by_staff[$e['staff_id']]['entries'][] = $e;
        }
        ?>

        <?php foreach ($by_staff as $sid => $sdata): ?>
        <div style="margin-bottom:30px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
                <h3 style="margin:0;font-size:1rem;">👤 <?php echo esc_html($sdata['name']); ?></h3>
                <?php
                // Calculate unpaid totals for this staff in current view
                $unpaid_min = 0;
                foreach ($sdata['entries'] as $e) {
                    if (!$e['paid'] && $e['clock_out']) {
                        $diff = (strtotime($e['clock_out']) - strtotime($e['clock_in'])) / 60;
                        $unpaid_min += mmgr_ph_round15($diff);
                    }
                }
                ?>
                <?php if ($filter_paid !== 'paid' && $unpaid_min > 0): ?>
                    <span style="background:#fff3cd;border:1px solid #f0ad4e;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                        Unpaid: <?php echo esc_html(mmgr_ph_format($unpaid_min)); ?>
                    </span>
                    <form method="POST" style="display:inline;">
                        <?php wp_nonce_field('mmgr_payroll', 'payroll_nonce'); ?>
                        <input type="hidden" name="target_staff_id" value="<?php echo intval($sid); ?>">
                        <button type="submit" name="mmgr_mark_staff_paid" class="button button-small button-primary"
                                onclick="return confirm('Mark all unpaid hours for <?php echo esc_js($sdata['name']); ?> as paid?');">
                            ✅ Mark All as Paid
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <table class="widefat fixed striped" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:160px;">Clock In</th>
                        <th style="width:160px;">Clock Out</th>
                        <th style="width:90px;text-align:center;">Duration</th>
                        <th style="width:80px;text-align:center;">Paid</th>
                        <th>Notes</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sdata['entries'] as $e): ?>
                    <?php
                    $duration_min = 0;
                    if ($e['clock_out']) {
                        $raw_min      = (strtotime($e['clock_out']) - strtotime($e['clock_in'])) / 60;
                        $duration_min = mmgr_ph_round15($raw_min);
                    }
                    $edit_url   = admin_url('admin.php?page=membership_payroll_hours&edit_entry=' . intval($e['id']) . ($filter_staff ? '&filter_staff=' . $filter_staff : '') . '&filter_paid=' . urlencode($filter_paid));
                    $delete_url = wp_nonce_url(admin_url('admin.php?page=membership_payroll_hours&delete_entry=' . intval($e['id'])), 'delete_time_entry_' . intval($e['id']));
                    ?>
                    <tr <?php echo (!$e['clock_out']) ? 'style="background:#fef9e7;"' : ''; ?>>
                        <td><?php echo esc_html(wp_date('M j, Y g:i A', strtotime($e['clock_in']))); ?></td>
                        <td>
                            <?php if ($e['clock_out']): ?>
                                <?php echo esc_html(wp_date('M j, Y g:i A', strtotime($e['clock_out']))); ?>
                            <?php else: ?>
                                <span style="color:#e65100;font-weight:600;">⚠️ Still clocked in</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php echo $duration_min ? esc_html(mmgr_ph_format($duration_min)) : '—'; ?>
                        </td>
                        <td style="text-align:center;">
                            <form method="POST" style="display:inline;">
                                <?php wp_nonce_field('mmgr_payroll', 'payroll_nonce'); ?>
                                <input type="hidden" name="entry_id" value="<?php echo intval($e['id']); ?>">
                                <input type="checkbox" name="paid" value="1"
                                       <?php checked($e['paid'], 1); ?>
                                       onchange="this.form.submit()"
                                       title="<?php echo $e['paid'] ? 'Paid' : 'Not paid'; ?>">
                                <button type="submit" name="mmgr_mark_paid_entry" style="display:none;"></button>
                            </form>
                        </td>
                        <td style="color:#555;"><?php echo esc_html($e['notes'] ?: '—'); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                            <a href="<?php echo esc_url($delete_url); ?>"
                               class="button button-small"
                               style="color:#d63638;border-color:#d63638;"
                               onclick="return confirm('Delete this time entry?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
