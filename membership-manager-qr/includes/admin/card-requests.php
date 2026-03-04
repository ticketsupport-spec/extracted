<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$card_tbl    = $wpdb->prefix . 'mmgr_card_requests';
$members_tbl = $wpdb->prefix . 'memberships';

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'mmgr_card_status')) {
    $request_id = intval($_POST['request_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    if (in_array($new_status, array('pending', 'ready', 'completed'))) {
        $update_data = array('status' => $new_status);
        if ($new_status === 'completed') {
            $update_data['completed_date'] = current_time('mysql');
        }
        $wpdb->update($card_tbl, $update_data, array('id' => $request_id));
        echo '<div class="notice notice-success is-dismissible"><p>✅ Status updated.</p></div>';
    }
}

// Get all requests joined with member info
$requests = $wpdb->get_results(
    "SELECT cr.*, m.name, m.member_code, m.email
     FROM $card_tbl cr
     LEFT JOIN $members_tbl m ON cr.member_id = m.id
     ORDER BY cr.request_date DESC",
    ARRAY_A
);

$pending_count = 0;
foreach ($requests as $r) {
    if ($r['status'] === 'pending') $pending_count++;
}

?>
<div class="wrap">
    <h1 class="wp-heading-inline">🎫 Card Requests
        <?php if ($pending_count > 0): ?>
            <span class="awaiting-mod" style="background:#d63638;color:white;border-radius:10px;padding:2px 8px;font-size:14px;margin-left:8px;"><?php echo $pending_count; ?></span>
        <?php endif; ?>
    </h1>
    <hr class="wp-header-end">

    <?php if (empty($requests)): ?>
        <div class="notice notice-info"><p>No card requests yet.</p></div>
    <?php else: ?>
    <table class="widefat fixed striped" style="margin-top:20px;">
        <thead>
            <tr>
                <th style="width:180px;">Member</th>
                <th style="width:130px;">Member Code</th>
                <th style="width:150px;">Requested On</th>
                <th style="width:100px;">Status</th>
                <th style="width:150px;">QR Code</th>
                <th>Update Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $req): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($req['name'] ?? '—'); ?></strong><br>
                    <small style="color:#666;"><?php echo esc_html($req['email'] ?? ''); ?></small>
                </td>
                <td>
                    <code style="font-size:13px;color:#d63638;"><?php echo esc_html($req['member_code'] ?? '—'); ?></code>
                </td>
                <td>
                    <?php echo esc_html(date('M j, Y', strtotime($req['request_date']))); ?>
                </td>
                <td>
                    <?php
                    $status_styles = array(
                        'pending'   => 'background:#fff3cd;color:#856404;',
                        'ready'     => 'background:#d1ecf1;color:#0c5460;',
                        'completed' => 'background:#d4edda;color:#155724;',
                    );
                    $s = $req['status'];
                    $style = $status_styles[$s] ?? '';
                    ?>
                    <span style="<?php echo $style; ?>padding:3px 10px;border-radius:12px;font-size:12px;font-weight:bold;">
                        <?php echo esc_html(ucfirst($s)); ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($req['member_code'])): ?>
                        <?php $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code=' . urlencode($req['member_code'])); ?>
                        <button type="button" onclick="mmgrPrintCardQR('<?php echo esc_js($req['member_code']); ?>','<?php echo esc_js($req['name'] ?? ''); ?>')" class="button button-small button-primary">🖨️ Print QR</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=membership_add&edit=' . intval($req['member_id']))); ?>" class="button button-small" style="margin-top:4px;display:inline-block;">👤 View Member</a>
                    <?php else: ?>
                        <span style="color:#999;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <?php wp_nonce_field('mmgr_card_status', '_wpnonce'); ?>
                        <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                        <select name="new_status">
                            <option value="pending"   <?php selected($req['status'], 'pending'); ?>>Pending</option>
                            <option value="ready"     <?php selected($req['status'], 'ready'); ?>>Ready for Pickup</option>
                            <option value="completed" <?php selected($req['status'], 'completed'); ?>>Completed</option>
                        </select>
                        <button type="submit" name="update_status" class="button button-small button-primary">Update</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function mmgrPrintCardQR(code, name) {
    var qrUrl = '<?php echo esc_js(admin_url('admin-ajax.php?action=mmgr_qrcode&code=')); ?>' + encodeURIComponent(code);
    var printWindow = window.open('', '_blank', 'width=400,height=500');
    printWindow.document.write('<html><head><title>QR Code - ' + code + '</title>');
    printWindow.document.write('<style>@media print{@page{size:4in 3in;margin:0;}}body{text-align:center;font-family:Arial;padding:15px;margin:0;}h2{margin:10px 0;font-size:18px;}img{border:2px solid #000;padding:8px;margin:15px 0;background:white;}.code{font-size:20px;font-weight:bold;font-family:monospace;margin:10px 0;}.name{font-size:14px;margin:5px 0;}.site{font-size:12px;color:#666;margin-top:10px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>Member QR Code</h2>');
    printWindow.document.write('<img src="' + qrUrl + '" width="200" height="200">');
    printWindow.document.write('<div class="code">' + code + '</div>');
    printWindow.document.write('<div class="name">' + name + '</div>');
    printWindow.document.write('<div class="site"><?php echo esc_js(get_bloginfo('name')); ?></div>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(function() { printWindow.print(); }, 250);
}
</script>
