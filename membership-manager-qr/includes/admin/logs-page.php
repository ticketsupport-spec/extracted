<?php
if (!defined('ABSPATH')) exit;

function mmgr_logs_admin() {
    mmgr_ensure_tables_exist();
    global $wpdb;
    $visits_tbl = $wpdb->prefix."membership_visits";
    $members_tbl = $wpdb->prefix."memberships";
    
    $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
    $filter_member = isset($_GET['filter_member']) ? sanitize_text_field($_GET['filter_member']) : '';
    
    // Handle Delete All Logs
    if (isset($_POST['delete_all_logs']) && isset($_POST['delete_all_nonce']) && wp_verify_nonce($_POST['delete_all_nonce'], 'delete_all_logs')) {
        $deleted = $wpdb->query("DELETE FROM $visits_tbl");
        if($deleted !== false) {
            echo "<div class='notice notice-success'><p>✓ All visit logs deleted successfully! (".$deleted." records removed)</p></div>";
        } else {
            echo "<div class='notice notice-error'><p>✕ Error deleting logs.</p></div>";
        }
    }
    
    echo '<div class="wrap"><h1>Visit Logs & Revenue</h1>';
    
    // Delete All Button with Confirmation
    echo '<div style="background:#f0f0f0;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #ff0000;">';
    echo '<h3 style="margin-top:0;">Danger Zone</h3>';
    echo '<p style="margin:10px 0 15px 0;">⚠️ <strong>Delete All Logs:</strong> This will permanently delete ALL visit records. This action cannot be undone!</p>';
    echo '<form method="post" style="display:inline;">';
    wp_nonce_field('delete_all_logs', 'delete_all_nonce');
    echo '<button type="submit" name="delete_all_logs" class="button" style="background:#ff0000;color:white;border-color:#ff0000;" onclick="return confirm(\'⚠️ ARE YOU ABSOLUTELY SURE?\n\nThis will permanently DELETE ALL visit logs. This action CANNOT be undone!\n\nType \"DELETE ALL\" if you want to continue:\') && (function() { var answer = prompt(\'Type DELETE ALL to confirm:\'); return answer === \'DELETE ALL\'; })();">🗑️ Delete All Logs</button>';
    echo '</form>';
    echo '</div>';
    
    // REVENUE REPORTS
    echo "<h2 style='margin-top:30px;margin-bottom:20px;'>📊 Revenue Reports</h2>";
    
    // Daily Revenue
    echo "<div style='background:#f0f8ff;padding:15px;border-radius:6px;margin-bottom:15px;border-left:4px solid #0073aa;'>";
    echo "<h3 style='margin-top:0;'>Daily Revenue (Today)</h3>";
    $today = date('Y-m-d');
    $daily_visit = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE(visit_time) = %s", $today));
    $daily_membership = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount_paid) FROM $members_tbl WHERE DATE(start_date) = %s", $today));
    echo "<p><strong>Visit Revenue (Today):</strong> \$".number_format($daily_visit ?: 0, 2)."</p>";
    echo "<p><strong>Membership Revenue (Today):</strong> \$".number_format($daily_membership ?: 0, 2)."</p>";
    echo "<p style='font-size:16px; font-weight:bold; color:#0073aa;'>Total Daily Revenue: \$".number_format(($daily_visit ?: 0) + ($daily_membership ?: 0), 2)."</p>";
    echo "</div>";
    
    // Weekly Revenue
    echo "<div style='background:#f0fff0;padding:15px;border-radius:6px;margin-bottom:15px;border-left:4px solid #00a000;'>";
    echo "<h3 style='margin-top:0;'>Weekly Revenue (This Week)</h3>";
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d');
    $weekly_visit = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE(visit_time) BETWEEN %s AND %s", $week_start, $week_end));
    $weekly_membership = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount_paid) FROM $members_tbl WHERE DATE(start_date) BETWEEN %s AND %s", $week_start, $week_end));
    echo "<p style='color:#666;'><em>Period: ".$week_start." to ".$week_end."</em></p>";
    echo "<p><strong>Visit Revenue (This Week):</strong> \$".number_format($weekly_visit ?: 0, 2)."</p>";
    echo "<p><strong>Membership Revenue (This Week):</strong> \$".number_format($weekly_membership ?: 0, 2)."</p>";
    echo "<p style='font-size:16px; font-weight:bold; color:#00a000;'>Total Weekly Revenue: \$".number_format(($weekly_visit ?: 0) + ($weekly_membership ?: 0), 2)."</p>";
    echo "</div>";
    
    // Monthly Revenue
    echo "<div style='background:#fff8f0;padding:15px;border-radius:6px;margin-bottom:15px;border-left:4px solid #ff9800;'>";
    echo "<h3 style='margin-top:0;'>Monthly Revenue (This Month)</h3>";
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-d');
    $monthly_visit = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE(visit_time) BETWEEN %s AND %s", $month_start, $month_end));
    $monthly_membership = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount_paid) FROM $members_tbl WHERE DATE(start_date) BETWEEN %s AND %s", $month_start, $month_end));
    echo "<p style='color:#666;'><em>Period: ".$month_start." to ".$month_end."</em></p>";
    echo "<p><strong>Visit Revenue (This Month):</strong> \$".number_format($monthly_visit ?: 0, 2)."</p>";
    echo "<p><strong>Membership Revenue (This Month):</strong> \$".number_format($monthly_membership ?: 0, 2)."</p>";
    echo "<p style='font-size:16px; font-weight:bold; color:#ff9800;'>Total Monthly Revenue: \$".number_format(($monthly_visit ?: 0) + ($monthly_membership ?: 0), 2)."</p>";
    echo "</div>";
    
    // Yearly totals
    echo "<div style='background:#f3e5f5;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #9c27b0;'>";
    echo "<h3 style='margin-top:0;'>Yearly Revenue (Full Year)</h3>";
    $year = date('Y');
    $year_start = $year.'-01-01';
    $year_end = date('Y-m-d');
    $year_visit = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_fee) FROM $visits_tbl WHERE DATE(visit_time) BETWEEN %s AND %s", $year_start, $year_end));
    $year_membership = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount_paid) FROM $members_tbl WHERE DATE(start_date) BETWEEN %s AND %s", $year_start, $year_end));
    echo "<p style='color:#666;'><em>Period: ".$year_start." to ".$year_end."</em></p>";
    echo "<p><strong>Visit Revenue (This Year):</strong> \$".number_format($year_visit ?: 0, 2)."</p>";
    echo "<p><strong>Membership Revenue (This Year):</strong> \$".number_format($year_membership ?: 0, 2)."</p>";
    echo "<p style='font-size:16px; font-weight:bold; color:#9c27b0;'>Total Yearly Revenue: \$".number_format(($year_visit ?: 0) + ($year_membership ?: 0), 2)."</p>";
    echo "</div>";
    
    // VISIT LOGS TABLE
    echo "<hr><h2>Visit Logs</h2>";
    
    echo '<form method="get" class="mmgr-filter-form"><input type="hidden" name="page" value="membership_logs">
    <label>Filter by Date: <input type="date" name="filter_date" value="'.esc_attr($filter_date).'"></label>
    <label>Filter by Member: <input type="text" name="filter_member" placeholder="Member name" value="'.esc_attr($filter_member).'"></label>
    <button type="submit" class="button">Filter</button>
    </form><hr>';
    
    $query = "SELECT v.*, m.name, m.member_code FROM $visits_tbl v 
              LEFT JOIN $members_tbl m ON v.member_id = m.id WHERE 1=1";
    
    if ($filter_date) {
        $query .= $wpdb->prepare(" AND DATE(v.visit_time) = %s", $filter_date);
    }
    if ($filter_member) {
        $query .= $wpdb->prepare(" AND m.name LIKE %s", '%'.$wpdb->esc_like($filter_member).'%');
    }
    
    $query .= " ORDER BY v.visit_time DESC LIMIT 500";
    $logs = $wpdb->get_results($query, ARRAY_A);
    
    echo "<table class='widefat'><thead><tr><th>Date/Time</th><th>Member</th><th>Code</th><th>Fee ($)</th><th>Notes</th><th>Update</th></tr></thead><tbody>";
    
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>".esc_html($log['visit_time'])."</td>";
        echo "<td>".esc_html($log['name'] ?: 'Unknown')."</td>";
        echo "<td>".esc_html($log['member_code'])."</td>";
        echo "<td><input type='number' step='0.01' value='".$log['daily_fee']."' id='fee_".$log['id']."' style='width:70px;'></td>";
        echo "<td><textarea id='notes_".$log['id']."' placeholder='Notes' style='width:150px;height:30px;'>".esc_textarea($log['notes'])."</textarea></td>";
        echo "<td>";
        echo "<button type='button' class='button button-small' onclick='updateFee(".$log['id'].")'>Update</button>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    echo '</div>';
    
    // JAVASCRIPT FOR INLINE UPDATES
    ?>
    <script>
    function updateFee(visitId) {
        var fee = document.getElementById('fee_' + visitId).value;
        var notes = document.getElementById('notes_' + visitId).value;
        
        var formData = new FormData();
        formData.append('action', 'update_fee');
        formData.append('visit_id', visitId);
        formData.append('daily_fee', fee);
        formData.append('notes', notes);
        formData.append('update_fee_nonce', '<?php echo wp_create_nonce('update_fee'); ?>');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('✓ Fee updated successfully!');
            } else {
                alert('✕ Error updating fee: ' + (data.data?.message || 'Unknown error'));
            }
        })
        .catch(error => alert('Error: ' + error));
    }
    </script>
    <?php
}