<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$cleaning_tbl = $wpdb->prefix . 'membership_cleaning_log';
$rooms_tbl    = $wpdb->prefix . 'membership_rooms';
$staff_tbl    = $wpdb->prefix . 'membership_staff';

// Guard: table may not exist before the first plugin init run.
if ($wpdb->get_var("SHOW TABLES LIKE '$cleaning_tbl'") !== $cleaning_tbl) {
    echo '<div class="wrap"><h1>Cleaning Log</h1><div class="notice notice-warning"><p>The cleaning log table has not been created yet. It will be created on next page load after the plugin initialises.</p></div></div>';
    return;
}

// Handle CSV export
if (isset($_GET['export_cleaning_log_csv']) && current_user_can('manage_options')) {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_cleaning_log_csv')) {
        wp_die('Security check failed.');
    }
    $all_logs = $wpdb->get_results("
        SELECT cl.*, r.room_name, s.name AS staff_name
        FROM `$cleaning_tbl` cl
        LEFT JOIN `$rooms_tbl` r ON r.id = cl.room_id
        LEFT JOIN `$staff_tbl` s ON s.id = cl.staff_id
        ORDER BY cl.cleaned_at DESC
    ", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cleaning-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('ID', 'Date / Time Cleaned', 'Room', 'Staff Member'));
    foreach ($all_logs as $row) {
        fputcsv($out, array(
            $row['id'],
            $row['cleaned_at'],
            $row['room_name'] ?: 'Unknown Room',
            $row['staff_name'] ?: 'Unknown Staff',
        ));
    }
    fclose($out);
    exit;
}

// Handle clear all logs
if (isset($_GET['clear_cleaning_log']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_cleaning_log')) {
    $wpdb->query("TRUNCATE TABLE `$cleaning_tbl`");
    echo '<div class="notice notice-success"><p>All cleaning log records have been cleared.</p></div>';
}

// Filters
$filter_room  = isset($_GET['filter_room'])  ? intval($_GET['filter_room'])                   : 0;
$filter_staff = isset($_GET['filter_staff']) ? intval($_GET['filter_staff'])                  : 0;
$filter_date  = isset($_GET['filter_date'])  ? sanitize_text_field($_GET['filter_date'])      : '';

// Pagination
$per_page     = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset       = ($current_page - 1) * $per_page;

// Build WHERE clause
$where   = array('1=1');
$prepare = array();

if ($filter_room) {
    $where[]   = 'cl.room_id = %d';
    $prepare[] = $filter_room;
}
if ($filter_staff) {
    $where[]   = 'cl.staff_id = %d';
    $prepare[] = $filter_staff;
}
if ($filter_date) {
    $where[]   = 'DATE(cl.cleaned_at) = %s';
    $prepare[] = $filter_date;
}

$where_sql = implode(' AND ', $where);

$count_sql  = "SELECT COUNT(*) FROM `$cleaning_tbl` cl WHERE $where_sql";
$total_logs = empty($prepare)
    ? (int) $wpdb->get_var($count_sql)
    : (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare));

$total_pages = max(1, ceil($total_logs / $per_page));

$logs_sql = "
    SELECT cl.*, r.room_name, s.name AS staff_name
    FROM `$cleaning_tbl` cl
    LEFT JOIN `$rooms_tbl` r ON r.id = cl.room_id
    LEFT JOIN `$staff_tbl` s ON s.id = cl.staff_id
    WHERE $where_sql
    ORDER BY cl.cleaned_at DESC
    LIMIT %d OFFSET %d
";
$query_args = array_merge($prepare, array($per_page, $offset));
$logs = empty($prepare)
    ? $wpdb->get_results($wpdb->prepare($logs_sql, $per_page, $offset), ARRAY_A)
    : $wpdb->get_results($wpdb->prepare($logs_sql, $query_args), ARRAY_A);

// Stats
$total_entries = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$cleaning_tbl`");
$total_rooms_cleaned = (int) $wpdb->get_var("SELECT COUNT(DISTINCT room_id) FROM `$cleaning_tbl`");

// Last cleaning per room (for the summary table)
$room_summary = $wpdb->get_results("
    SELECT r.room_name, s.name AS staff_name, cl.cleaned_at
    FROM (
        SELECT room_id, MAX(id) AS last_id
        FROM `$cleaning_tbl`
        GROUP BY room_id
    ) latest
    INNER JOIN `$cleaning_tbl` cl ON cl.id = latest.last_id
    LEFT JOIN `$rooms_tbl` r ON r.id = cl.room_id
    LEFT JOIN `$staff_tbl` s ON s.id = cl.staff_id
    ORDER BY cl.cleaned_at DESC
", ARRAY_A);

// Dropdown lists for filters
$all_rooms = $wpdb->get_results("SELECT id, room_name FROM `$rooms_tbl` ORDER BY sort_order ASC, id ASC", ARRAY_A);
$all_staff = $wpdb->get_results("SELECT id, name FROM `$staff_tbl` WHERE active = 1 ORDER BY name ASC", ARRAY_A);

$base_url = admin_url('admin.php?page=membership_cleaning_log');

$paged_base_url = $base_url;
if ($filter_room)  { $paged_base_url = add_query_arg('filter_room',  $filter_room,  $paged_base_url); }
if ($filter_staff) { $paged_base_url = add_query_arg('filter_staff', $filter_staff, $paged_base_url); }
if ($filter_date)  { $paged_base_url = add_query_arg('filter_date',  $filter_date,  $paged_base_url); }
?>

<div class="wrap">
    <h1 class="wp-heading-inline">🧹 Cleaning Log</h1>
    <a href="<?php echo wp_nonce_url($base_url . '&export_cleaning_log_csv=1', 'export_cleaning_log_csv'); ?>"
       class="page-title-action">📥 Export CSV</a>
    <a href="<?php echo wp_nonce_url($base_url . '&clear_cleaning_log=1', 'clear_cleaning_log'); ?>"
       class="page-title-action"
       style="background:#d63638;border-color:#d63638;color:white;"
       onclick="return confirm('⚠️ WARNING: This will permanently delete ALL cleaning log records. Are you sure?');">
        🗑️ Clear All Logs
    </a>
    <hr class="wp-header-end">

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
        <div style="background:#6f42c1;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_entries); ?></h3>
            <p style="margin:5px 0 0;">Total Cleaning Records</p>
        </div>
        <div style="background:#00a32a;color:white;padding:20px;border-radius:6px;">
            <h3 style="margin:0;font-size:28px;"><?php echo number_format($total_rooms_cleaned); ?></h3>
            <p style="margin:5px 0 0;">Rooms Logged</p>
        </div>
    </div>

    <?php if (!empty($room_summary)): ?>
    <!-- Last Cleaned Per Room Summary -->
    <h2 style="margin-top:30px;">Last Cleaned Per Room</h2>
    <table class="widefat fixed striped" style="margin-bottom:30px;">
        <thead>
            <tr>
                <th>Room</th>
                <th>Last Cleaned</th>
                <th>By</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($room_summary as $row): ?>
            <tr>
                <td><strong><?php echo esc_html($row['room_name'] ?: 'Unknown Room'); ?></strong></td>
                <td><?php echo esc_html(wp_date('M d, Y g:i A', strtotime($row['cleaned_at']))); ?></td>
                <td><?php echo esc_html($row['staff_name'] ?: 'Unknown Staff'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Filters -->
    <h2>Full Log</h2>
    <form method="GET" action="" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="membership_cleaning_log">
        <select name="filter_room">
            <option value="">All Rooms</option>
            <?php foreach ($all_rooms as $room): ?>
                <option value="<?php echo esc_attr($room['id']); ?>" <?php selected($filter_room, $room['id']); ?>>
                    <?php echo esc_html($room['room_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="filter_staff">
            <option value="">All Staff</option>
            <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo esc_attr($staff['id']); ?>" <?php selected($filter_staff, $staff['id']); ?>>
                    <?php echo esc_html($staff['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>">
        <button type="submit" class="button">Filter</button>
        <?php if ($filter_room || $filter_staff || $filter_date): ?>
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
                <th style="width:165px;">Date / Time Cleaned</th>
                <th>Room</th>
                <th>Staff Member</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="3" style="text-align:center;padding:40px;color:#666;">
                        No cleaning records found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html(wp_date('M d, Y g:i A', strtotime($log['cleaned_at']))); ?></td>
                    <td><strong><?php echo esc_html($log['room_name'] ?: 'Unknown Room'); ?></strong></td>
                    <td><?php echo esc_html($log['staff_name'] ?: 'Unknown Staff'); ?></td>
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
