<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$staff_tbl = $wpdb->prefix . 'membership_staff';

$success = $error = '';

// ── Save (add / edit) ─────────────────────────────────────────────────────
if (isset($_POST['save_staff']) && wp_verify_nonce($_POST['staff_nonce'], 'mmgr_save_staff')) {
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name  = sanitize_text_field($_POST['last_name']);
    $position   = sanitize_text_field($_POST['position']);
    $active     = isset($_POST['active']) ? 1 : 0;
    $staff_id   = intval($_POST['staff_id']);

    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif ($staff_id) {
        $wpdb->update(
            $staff_tbl,
            array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'name'       => $first_name . ' ' . $last_name,
                'position'   => $position,
                'active'     => $active,
            ),
            array('id' => $staff_id)
        );
        $success = 'Staff member updated.';
    } else {
        // Generate unique staff code
        $staff_code = strtoupper(substr(md5($first_name . $last_name . time() . rand()), 0, 12));
        $wpdb->insert($staff_tbl, array(
            'staff_code' => $staff_code,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'name'       => $first_name . ' ' . $last_name,
            'position'   => $position,
            'active'     => $active,
        ));
        $success = 'Staff member added. Their QR code has been generated.';
    }
}

// ── Remove ────────────────────────────────────────────────────────────────
if (isset($_GET['remove']) && wp_verify_nonce($_GET['_wpnonce'], 'remove_staff_' . intval($_GET['remove']))) {
    $del_id = intval($_GET['remove']);
    $wpdb->update($staff_tbl, array('active' => 0), array('id' => $del_id));
    $success = 'Staff member deactivated.';
}

// ── Fetch staff ───────────────────────────────────────────────────────────
$all_staff = $wpdb->get_results("SELECT * FROM `$staff_tbl` ORDER BY name ASC", ARRAY_A);
$editing   = null;
if (isset($_GET['edit'])) {
    $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$staff_tbl` WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}
?>
<div class="wrap">
    <h1>Staff Management</h1>

    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($success); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:30px;margin-top:20px;align-items:start;">

        <!-- ── Form ───────────────────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:6px;">
            <h2 style="margin-top:0;"><?php echo $editing ? 'Edit Staff Member' : 'Add New Staff Member'; ?></h2>
            <form method="POST">
                <?php wp_nonce_field('mmgr_save_staff', 'staff_nonce'); ?>
                <input type="hidden" name="staff_id" value="<?php echo $editing ? intval($editing['id']) : 0; ?>">

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:bold;margin-bottom:4px;">First Name *</label>
                    <input type="text" name="first_name" required
                           value="<?php echo $editing ? esc_attr($editing['first_name']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:bold;margin-bottom:4px;">Last Name *</label>
                    <input type="text" name="last_name" required
                           value="<?php echo $editing ? esc_attr($editing['last_name']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:bold;margin-bottom:4px;">Position / Role</label>
                    <input type="text" name="position" placeholder="e.g. Front Desk, Cleaner"
                           value="<?php echo $editing ? esc_attr($editing['position']) : ''; ?>"
                           style="width:100%;padding:8px;border:1px solid #8c8f94;border-radius:4px;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:bold;">
                        <input type="checkbox" name="active" value="1"
                               <?php checked($editing ? intval($editing['active']) : 1, 1); ?>>
                        Active
                    </label>
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" name="save_staff" class="button button-primary">
                        <?php echo $editing ? '💾 Update Staff Member' : '➕ Add Staff Member'; ?>
                    </button>
                    <?php if ($editing): ?>
                        <a href="<?php echo admin_url('admin.php?page=membership_staff'); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── Staff List ─────────────────────────────────────────── -->
        <div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="width:140px;">Position</th>
                        <th style="width:80px;text-align:center;">Status</th>
                        <th style="width:100px;text-align:center;">QR Code</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_staff)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:30px;color:#666;">No staff members yet — add one using the form.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_staff as $s): ?>
                        <tr>
                            <td style="padding:10px 8px;">
                                <strong><?php echo esc_html($s['name']); ?></strong><br>
                                <span style="font-size:11px;color:#888;font-family:monospace;"><?php echo esc_html($s['staff_code']); ?></span>
                            </td>
                            <td><?php echo esc_html($s['position'] ?: '—'); ?></td>
                            <td style="text-align:center;">
                                <?php if ($s['active']): ?>
                                    <span style="background:#00a32a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Active</span>
                                <?php else: ?>
                                    <span style="background:#999;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php
                                $qr_url = mmgr_generate_qr_code($s['staff_code']);
                                if ($qr_url):
                                ?>
                                    <img src="<?php echo esc_url($qr_url); ?>" alt="QR" style="width:60px;height:60px;display:block;margin:0 auto;">
                                <?php else: ?>
                                    <span style="font-size:11px;color:#999;">Generating…</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;">
                                <a href="<?php echo admin_url('admin.php?page=membership_staff&edit=' . intval($s['id'])); ?>"
                                   class="button button-small">Edit</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_staff&remove=' . intval($s['id'])), 'remove_staff_' . intval($s['id'])); ?>"
                                   class="button button-small"
                                   style="color:#d63638;border-color:#d63638;"
                                   onclick="return confirm('Deactivate <?php echo esc_js($s['name']); ?>?');">Remove</a>
                                <?php if ($qr_url): ?>
                                    <button onclick="mmgrPrintStaffQR('<?php echo esc_js($s['name']); ?>','<?php echo esc_js($qr_url); ?>')"
                                            class="button button-small" style="margin-top:4px;">🖨️ Print QR</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Print Modal -->
<div id="mmgr-staff-qr-print-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:30px;border-radius:8px;text-align:center;max-width:320px;width:90%;">
        <h3 id="mmgr-print-staff-name" style="margin:0 0 16px;font-size:1.1rem;"></h3>
        <img id="mmgr-print-qr-img" src="" alt="QR Code" style="width:200px;height:200px;display:block;margin:0 auto 16px;">
        <p id="mmgr-print-staff-code" style="font-family:monospace;font-size:13px;color:#555;margin:0 0 20px;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="window.print()" class="button button-primary">🖨️ Print</button>
            <button onclick="document.getElementById('mmgr-staff-qr-print-modal').style.display='none';" class="button">Close</button>
        </div>
    </div>
</div>

<style>
@media print {
    #mmgr-staff-qr-print-modal { background: #fff !important; }
    body * { visibility: hidden; }
    #mmgr-staff-qr-print-modal, #mmgr-staff-qr-print-modal * { visibility: visible; }
    #mmgr-staff-qr-print-modal { position: absolute; left: 0; top: 0; }
}
</style>

<script>
function mmgrPrintStaffQR(name, qrUrl) {
    document.getElementById('mmgr-print-staff-name').textContent = name;
    document.getElementById('mmgr-print-qr-img').src = qrUrl;
    const modal = document.getElementById('mmgr-staff-qr-print-modal');
    modal.style.display = 'flex';
}
</script>
