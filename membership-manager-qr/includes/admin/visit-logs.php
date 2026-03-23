<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$visits_tbl = $wpdb->prefix . 'membership_visits';
$members_tbl = $wpdb->prefix . 'memberships';

// Handle delete all logs
if (isset($_GET['delete_all_logs']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_all_logs')) {
    $wpdb->query("TRUNCATE TABLE $visits_tbl");
    echo '<div class="notice notice-success"><p>All visit logs have been deleted!</p></div>';
}

// Handle delete single log
if (isset($_GET['delete_log']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_log_' . $_GET['delete_log'])) {
    $wpdb->delete($visits_tbl, array('id' => intval($_GET['delete_log'])));
    echo '<div class="notice notice-success"><p>Log deleted successfully!</p></div>';
}

// Handle CSV export
if (isset($_GET['export_logs_csv']) && current_user_can('manage_options')) {
    $logs = $wpdb->get_results("
        SELECT 
            v.id,
            v.visit_time,
            v.daily_fee,
            v.notes,
            v.is_first_visit,
            v.orientation_done,
            v.id_verified,
            m.name,
            m.member_code,
            m.level,
            m.phone,
            m.email
        FROM $visits_tbl v
        LEFT JOIN $members_tbl m ON v.member_id = m.id
        ORDER BY v.visit_time DESC
    ", ARRAY_A);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visit-logs-'.date('Y-m-d').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Date/Time', 'Member Name', 'Member Code', 'Level', 'Phone', 'Email', 'Daily Fee', 'First Visit', 'Orientation Done', 'ID Verified', 'Notes'));
    
    foreach ($logs as $log) {
        fputcsv($output, array(
            $log['id'],
            $log['visit_time'],
            $log['name'] ?: 'Unknown',
            $log['member_code'] ?: 'N/A',
            $log['level'] ?: 'N/A',
            $log['phone'] ?: 'N/A',
            $log['email'] ?: 'N/A',
            '$' . number_format($log['daily_fee'], 2),
            $log['is_first_visit'] ? 'Yes' : 'No',
            $log['orientation_done'] ? 'Yes' : 'No',
            $log['id_verified'] ? 'Yes' : 'No',
            $log['notes'] ?: ''
        ));
    }
    
    fclose($output);
    exit;
}

// Pagination
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count
$total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $visits_tbl");
$total_pages = ceil($total_logs / $per_page);

// Get logs with member info
$logs = $wpdb->get_results($wpdb->prepare("
    SELECT 
        v.id,
        v.visit_time,
        v.daily_fee,
        v.notes,
        v.is_first_visit,
        v.orientation_done,
        v.id_verified,
        m.name,
        m.member_code,
        m.level,
        m.photo_url
    FROM $visits_tbl v
    LEFT JOIN $members_tbl m ON v.member_id = m.id
    ORDER BY v.visit_time DESC
    LIMIT %d OFFSET %d
", $per_page, $offset), ARRAY_A);

// Calculate stats
$today = date('Y-m-d');
$this_month = date('Y-m');

$today_visits = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $visits_tbl WHERE DATE(visit_time) = %s",
    $today
));

$today_revenue = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE(visit_time) = %s AND notes LIKE %s",
    $today,
    '%PAID%'
));

$month_visits = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $visits_tbl WHERE DATE_FORMAT(visit_time, '%%Y-%%m') = %s",
    $this_month
));

$month_revenue = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE_FORMAT(visit_time, '%%Y-%%m') = %s AND notes LIKE %s",
    $this_month,
    '%PAID%'
));

?>

<div class="wrap">
    <h1 class="wp-heading-inline">Visit Logs</h1>
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_logs&export_logs_csv=1'), 'export_logs_csv'); ?>" class="page-title-action">📥 Export CSV</a>
    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_logs&delete_all_logs=1'), 'delete_all_logs'); ?>" 
       class="page-title-action" 
       style="background:#d63638;border-color:#d63638;color:white;"
       onclick="return confirm('⚠️ WARNING: This will permanently delete ALL visit logs. This cannot be undone!\n\nAre you absolutely sure?');">
        🗑️ Delete All Logs
    </a>
    <hr class="wp-header-end">
    
    <!-- Stats Dashboard -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
        <div style="background:#0073aa;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:32px;"><?php echo number_format($today_visits); ?></h3>
            <p style="margin:5px 0 0 0;">Today's Visits</p>
        </div>
        <div style="background:#00a32a;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:32px;">$<?php echo number_format($today_revenue ?: 0, 2); ?></h3>
            <p style="margin:5px 0 0 0;">Today's Revenue (Paid)</p>
        </div>
        <div style="background:#8e44ad;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:32px;"><?php echo number_format($month_visits); ?></h3>
            <p style="margin:5px 0 0 0;">This Month's Visits</p>
        </div>
        <div style="background:#e67e22;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:32px;">$<?php echo number_format($month_revenue ?: 0, 2); ?></h3>
            <p style="margin:5px 0 0 0;">This Month's Revenue</p>
        </div>
    </div>
    
    <p><strong>Total Logs:</strong> <?php echo number_format($total_logs); ?></p>
    
    <?php if ($total_pages > 1): ?>
    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo number_format($total_logs); ?> items</span>
            <span class="pagination-links">
                <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=1'); ?>">«</a>
                    <a class="prev-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . ($current_page - 1)); ?>">‹</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <span class="tablenav-paging-text"><?php echo $current_page; ?> of <span class="total-pages"><?php echo $total_pages; ?></span></span>
                </span>
                
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . ($current_page + 1)); ?>">›</a>
                    <a class="last-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . $total_pages); ?>">»</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th style="width:60px;">Photo</th>
                <th>Member</th>
                <th>Code</th>
                <th>Level</th>
                <th>Visit Date/Time</th>
                <th>Daily Fee</th>
                <th>Status/Notes</th>
                <th style="width:100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;color:#666;">
                        No visit logs yet. Check-ins will appear here.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $is_paid = strpos($log['notes'], 'PAID') !== false;
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($log['photo_url'])): ?>
                                <img src="<?php echo esc_url($log['photo_url']); ?>" 
                                     style="width:50px;height:50px;object-fit:cover;border-radius:50%;border:2px solid #ccc;">
                            <?php else: ?>
                                <div style="width:50px;height:50px;background:#f0f0f0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;border:2px solid #ccc;">👤</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($log['name'] ?: 'Unknown Member'); ?></strong>
                        </td>
                        <td>
                            <code><?php echo esc_html($log['member_code'] ?: 'N/A'); ?></code>
                        </td>
                        <td>
                            <span style="background:#0073aa;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;">
                                <?php echo esc_html($log['level'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M d, Y g:i A', strtotime($log['visit_time'])); ?>
                        </td>
                        <td>
                            <strong>$<?php echo number_format($log['daily_fee'], 2); ?></strong>
                        </td>
                        <td>
                            <?php if ($is_paid): ?>
                                <span style="background:#00a32a;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;">💵 PAID</span>
                            <?php else: ?>
                                <span style="background:#d63638;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;">⚠️ UNPAID</span>
                            <?php endif; ?>
                            <?php if (!empty($log['is_first_visit'])): ?>
                                <span style="background:#c00;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;margin-left:3px;">🎉 FIRST VISIT</span>
                            <?php endif; ?>
                            <?php if (!empty($log['orientation_done'])): ?>
                                <span style="background:#6a0dad;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;margin-left:3px;">🎓 Orientation ✓</span>
                            <?php endif; ?>
                            <?php if (!empty($log['id_verified'])): ?>
                                <span style="background:#d4600a;color:white;padding:3px 8px;border-radius:10px;font-size:11px;font-weight:bold;margin-left:3px;">🪪 ID Verified ✓</span>
                            <?php endif; ?>
                            <?php if (!empty($log['notes'])): ?>
                                <br><small style="color:#666;"><?php echo esc_html($log['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=membership_logs&delete_log=' . $log['id']), 'delete_log_' . $log['id']); ?>" 
                               class="button button-small button-link-delete" 
                               style="color:#d63638;"
                               onclick="return confirm('Delete this log entry?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo number_format($total_logs); ?> items</span>
            <span class="pagination-links">
                <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=1'); ?>">«</a>
                    <a class="prev-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . ($current_page - 1)); ?>">‹</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <span class="tablenav-paging-text"><?php echo $current_page; ?> of <span class="total-pages"><?php echo $total_pages; ?></span></span>
                </span>
                
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . ($current_page + 1)); ?>">›</a>
                    <a class="last-page button" href="<?php echo admin_url('admin.php?page=membership_logs&paged=' . $total_pages); ?>">»</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
</div>