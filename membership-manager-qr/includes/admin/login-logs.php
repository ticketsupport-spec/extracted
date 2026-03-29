<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$logs_tbl   = $wpdb->prefix . 'mmgr_login_logs';
$members_tbl = $wpdb->prefix . 'memberships';

// Guard: table may not exist before the first portal init run.
if ($wpdb->get_var("SHOW TABLES LIKE '$logs_tbl'") !== $logs_tbl) {
    echo '<div class="wrap"><h1>Login Logs</h1><div class="notice notice-warning"><p>The login logs table has not been created yet. It will be created on next page load after the plugin initialises.</p></div></div>';
    return;
}

// Handle clear all logs
if (isset($_GET['clear_login_logs']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_login_logs')) {
    $wpdb->query("TRUNCATE TABLE `$logs_tbl`");
    echo '<div class="notice notice-success"><p>All login logs have been cleared.</p></div>';
}

// Handle CSV export
if (isset($_GET['export_login_logs_csv']) && current_user_can('manage_options')) {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_login_logs_csv')) {
        wp_die('Security check failed.');
    }
    $all_logs = $wpdb->get_results("
        SELECT l.*, m.name AS member_name, m.member_code
        FROM `$logs_tbl` l
        LEFT JOIN `$members_tbl` m ON l.member_id = m.id
        ORDER BY l.logged_at DESC
    ", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="login-logs-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('ID', 'Date/Time', 'Login Email', 'Member Name', 'Member Code', 'Profile Email', 'Email Match', 'Result', 'Failure Reason', 'IP Address'));
    foreach ($all_logs as $row) {
        $csv_reason_labels = array(
            'no_password'        => 'No password set',
            'invalid_credentials'=> 'Wrong email/password',
            'new_registration'   => 'New account created',
            'portal_visit'       => 'Dashboard visit',
        );
        $r = $row['failure_reason'] ?? '';
        if ($row['failure_reason'] === 'new_registration') {
            $result_label = 'Registered';
        } elseif ($row['failure_reason'] === 'portal_visit') {
            $result_label = 'Visit';
        } elseif ($row['success']) {
            $result_label = 'Success';
        } else {
            $result_label = 'Failed';
        }
        fputcsv($out, array(
            $row['id'],
            $row['logged_at'],
            $row['login_email'],
            $row['member_name'] ?: 'N/A',
            $row['member_code'] ?: 'N/A',
            $row['member_email'] ?: 'N/A',
            $row['email_match'] === null ? 'N/A' : ($row['email_match'] ? 'Yes' : 'No'),
            $result_label,
            $r ? ($csv_reason_labels[$r] ?? $r) : '',
            $row['ip_address'] ?: '',
        ));
    }
    fclose($out);
    exit;
}

// Filters
$filter_success = isset($_GET['filter_success']) ? $_GET['filter_success'] : '';
$filter_email   = isset($_GET['filter_email'])   ? sanitize_text_field($_GET['filter_email']) : '';
$filter_type    = isset($_GET['filter_type'])    ? sanitize_text_field($_GET['filter_type'])  : '';

// Pagination
$per_page     = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset       = ($current_page - 1) * $per_page;

// Build WHERE clause
$where   = array('1=1');
$prepare = array();

if ($filter_success !== '') {
    $where[]   = 'l.success = %d';
    $prepare[] = intval($filter_success);
}
if ($filter_email !== '') {
    $where[]   = 'l.login_email LIKE %s';
    $prepare[] = '%' . $wpdb->esc_like($filter_email) . '%';
}
if ($filter_type === 'registration') {
    $where[] = "l.failure_reason = 'new_registration'";
} elseif ($filter_type === 'login') {
    $where[] = "(l.failure_reason IS NULL OR (l.failure_reason != 'new_registration' AND l.failure_reason != 'portal_visit'))";
} elseif ($filter_type === 'visit') {
    $where[] = "l.failure_reason = 'portal_visit'";
}

$where_sql = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) FROM `$logs_tbl` l WHERE $where_sql";
$total_logs = empty($prepare)
    ? (int) $wpdb->get_var($count_sql)
    : (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare));

$total_pages = max(1, ceil($total_logs / $per_page));

$query_args   = array_merge($prepare, array($per_page, $offset));
$logs_sql     = "
    SELECT l.*, m.name AS member_name, m.member_code
    FROM `$logs_tbl` l
    LEFT JOIN `$members_tbl` m ON l.member_id = m.id
    WHERE $where_sql
    ORDER BY l.logged_at DESC
    LIMIT %d OFFSET %d
";
$logs = empty($prepare)
    ? $wpdb->get_results($wpdb->prepare($logs_sql, $per_page, $offset), ARRAY_A)
    : $wpdb->get_results($wpdb->prepare($logs_sql, $query_args), ARRAY_A);

// Stats
$total_registrations = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$logs_tbl` WHERE failure_reason = 'new_registration'");
$total_visits        = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$logs_tbl` WHERE failure_reason = 'portal_visit'");
$total_success  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$logs_tbl` WHERE success = 1 AND (failure_reason IS NULL OR (failure_reason != 'new_registration' AND failure_reason != 'portal_visit'))");
$total_failed   = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$logs_tbl` WHERE success = 0");
$total_mismatch = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$logs_tbl` WHERE email_match = 0");

$base_url     = admin_url('admin.php?page=membership_login_logs');

// Build a URL that preserves current filter parameters for use in pagination links.
$paged_base_url = $base_url;
if ($filter_success !== '') {
    $paged_base_url = add_query_arg('filter_success', $filter_success, $paged_base_url);
}
if ($filter_email !== '') {
    $paged_base_url = add_query_arg('filter_email', $filter_email, $paged_base_url);
}
if ($filter_type !== '') {
    $paged_base_url = add_query_arg('filter_type', $filter_type, $paged_base_url);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">🔐 Login &amp; Registration Logs</h1>
    <a href="<?php echo wp_nonce_url($base_url . '&export_login_logs_csv=1', 'export_login_logs_csv'); ?>"
       class="page-title-action">📥 Export CSV</a>
    <a href="<?php echo wp_nonce_url($base_url . '&clear_login_logs=1', 'clear_login_logs'); ?>"
       class="page-title-action"
       style="background:#d63638;border-color:#d63638;color:white;"
       onclick="return confirm('⚠️ WARNING: This will permanently delete ALL login logs. Are you sure?');">
        🗑️ Clear All Logs
    </a>
    <hr class="wp-header-end">

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0;">
        <div style="background:#0073aa;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_logs); ?></h3>
            <p style="margin:5px 0 0;">Total Records</p>
        </div>
        <div style="background:#00a32a;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_success); ?></h3>
            <p style="margin:5px 0 0;">Successful Logins</p>
        </div>
        <div style="background:#d63638;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_failed); ?></h3>
            <p style="margin:5px 0 0;">Failed Attempts</p>
        </div>
        <div style="background:#8e44ad;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_registrations); ?></h3>
            <p style="margin:5px 0 0;">New Registrations</p>
        </div>
        <div style="background:#1d6fa4;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_visits); ?></h3>
            <p style="margin:5px 0 0;">Portal Visits</p>
        </div>
        <div style="background:#e67e22;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_mismatch); ?></h3>
            <p style="margin:5px 0 0;">Email Mismatches</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="membership_login_logs">
        <input type="text" name="filter_email" placeholder="Filter by email…"
               value="<?php echo esc_attr($filter_email); ?>" style="width:220px;">
        <select name="filter_success">
            <option value="">All Results</option>
            <option value="1" <?php selected($filter_success, '1'); ?>>Successful</option>
            <option value="0" <?php selected($filter_success, '0'); ?>>Failed</option>
        </select>
        <select name="filter_type">
            <option value="">All Types</option>
            <option value="login" <?php selected($filter_type, 'login'); ?>>Logins Only</option>
            <option value="registration" <?php selected($filter_type, 'registration'); ?>>Registrations Only</option>
            <option value="visit" <?php selected($filter_type, 'visit'); ?>>Visits Only</option>
        </select>
        <button type="submit" class="button">Filter</button>
        <?php if ($filter_success !== '' || $filter_email !== '' || $filter_type !== ''): ?>
            <a href="<?php echo esc_url($base_url); ?>" class="button">Clear Filters</a>
        <?php endif; ?>
    </form>

    <p><strong>Showing:</strong> <?php echo number_format($total_logs); ?> records</p>

    <?php if ($total_pages > 1): ?>
    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo number_format($total_logs); ?> items</span>
            <span class="pagination-links">
                <?php if ($current_page > 1): ?>
                    <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $paged_base_url)); ?>">«</a>
                    <a class="prev-page button"  href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $paged_base_url)); ?>">‹</a>
                <?php endif; ?>
                <span class="paging-input">
                    <span class="tablenav-paging-text"><?php echo $current_page; ?> of <span class="total-pages"><?php echo $total_pages; ?></span></span>
                </span>
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $paged_base_url)); ?>">›</a>
                    <a class="last-page button"  href="<?php echo esc_url(add_query_arg('paged', $total_pages, $paged_base_url)); ?>">»</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th style="width:155px;">Date / Time</th>
                <th>Email Used to Log In</th>
                <th>Member Name</th>
                <th>Profile Email</th>
                <th style="width:100px;">Email Match</th>
                <th style="width:90px;">Result</th>
                <th>Failure Reason</th>
                <th style="width:120px;">IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;color:#666;">
                        No login attempts have been recorded yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log):
                    $is_success  = (bool) $log['success'];
                    $email_match = $log['email_match'];
                    $row_style   = '';
                    if ($is_success && $email_match === '0') {
                        $row_style = 'background:#fff3cd;';  // mismatch warning (shouldn't happen post-fix)
                    }
                ?>
                <tr style="<?php echo $row_style; ?>">
                    <td><?php echo esc_html(wp_date('M d, Y g:i A', strtotime($log['logged_at']))); ?></td>
                    <td>
                        <strong><?php echo esc_html($log['login_email']); ?></strong>
                    </td>
                    <td>
                        <?php if ($log['member_name']): ?>
                            <?php echo esc_html($log['member_name']); ?>
                            <?php if ($log['member_code']): ?>
                                <br><code style="font-size:11px;"><?php echo esc_html($log['member_code']); ?></code>
                            <?php endif; ?>
                        <?php else: ?>
                            <em style="color:#999;">Unknown</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $log['member_email'] ? esc_html($log['member_email']) : '<em style="color:#999;">N/A</em>'; ?>
                    </td>
                    <td>
                        <?php if ($email_match === null || $email_match === ''): ?>
                            <span style="color:#999;">—</span>
                        <?php elseif ($email_match == 1): ?>
                            <span style="background:#00a32a;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">✔ Yes</span>
                        <?php else: ?>
                            <span style="background:#d63638;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">✘ No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['failure_reason'] === 'new_registration'): ?>
                            <span style="background:#8e44ad;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">✨ Registered</span>
                        <?php elseif ($log['failure_reason'] === 'portal_visit'): ?>
                            <span style="background:#1d6fa4;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">👁 Visit</span>
                        <?php elseif ($is_success): ?>
                            <span style="background:#00a32a;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">✔ Success</span>
                        <?php else: ?>
                            <span style="background:#d63638;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">✘ Failed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $reason_labels = array(
                            'no_password'        => 'No password set',
                            'invalid_credentials'=> 'Wrong email/password',
                            'new_registration'   => 'New account created',
                            'portal_visit'       => 'Dashboard visit',
                        );
                        $r = $log['failure_reason'] ?? '';
                        echo $r ? esc_html($reason_labels[$r] ?? $r) : '<em style="color:#999;">—</em>';
                        ?>
                    </td>
                    <td><code style="font-size:11px;"><?php echo esc_html($log['ip_address'] ?: '—'); ?></code></td>
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
                    <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $paged_base_url)); ?>">«</a>
                    <a class="prev-page button"  href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $paged_base_url)); ?>">‹</a>
                <?php endif; ?>
                <span class="paging-input">
                    <span class="tablenav-paging-text"><?php echo $current_page; ?> of <span class="total-pages"><?php echo $total_pages; ?></span></span>
                </span>
                <?php if ($current_page < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $paged_base_url)); ?>">›</a>
                    <a class="last-page button"  href="<?php echo esc_url(add_query_arg('paged', $total_pages, $paged_base_url)); ?>">»</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
</div>
