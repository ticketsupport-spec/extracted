<?php
if (!defined('ABSPATH')) exit;

function mmgr_admin_page() {
    global $wpdb;
    $tbl = $wpdb->prefix."memberships";
    $levels_tbl = $wpdb->prefix."membership_levels";
    $visits_tbl = $wpdb->prefix."membership_visits";
    $fees_tbl = $wpdb->prefix."membership_special_fees";
    $levels = $wpdb->get_results("SELECT * FROM $levels_tbl ORDER BY id",ARRAY_A);
    $editing = isset($_GET['edit']);
    $success = $error = false;

    if (isset($_GET['delete']) && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'delete_member')) {
        $wpdb->delete($tbl, array('id'=>intval($_GET['delete'])));
        echo "<div class='notice notice-success'><p>✓ Member deleted.</p></div>";
    }

    // Manual Check-In with Fee Selection
    if (isset($_GET['checkin']) && isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'checkin_member')) {
        $member_id = intval($_GET['checkin']);
        $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d", $member_id), ARRAY_A);
        
        if ($member && !$member['banned']) {
            // Get available fees for today
            $today = date('Y-m-d');
            $special_fee = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fees_tbl WHERE event_date=%s AND active=1", $today), ARRAY_A);
            $standard_fee = mmgr_get_daily_fee($member['level']);
            
            // Show fee selection modal
            echo '<div class="wrap" style="max-width:500px;">';
            echo '<h1>Select Fee for Check-In</h1>';
            echo '<div style="background:#fff;padding:20px;border-radius:8px;border:2px solid #0073aa;">';
            echo '<h3>Member: '.esc_html($member['name']).'</h3>';
            echo '<h3>Level: '.esc_html($member['level']).'</h3>';
            echo '<form method="post" id="fee-select-form">';
            echo '<div style="margin:20px 0;">';
            echo '<label><input type="radio" name="fee_type" value="standard" checked> Standard Fee: $'.number_format($standard_fee, 2).'</label>';
            echo '</div>';
            
            if ($special_fee) {
                echo '<div style="margin:20px 0;">';
                echo '<label><input type="radio" name="fee_type" value="special"> Special Event Fee: $'.number_format($special_fee['fee_amount'], 2).' - '.esc_html($special_fee['event_name']).'</label>';
                echo '</div>';
            }
            
            echo '<div style="margin:20px 0;">';
            echo '<label><input type="radio" name="fee_type" value="custom"> Custom Fee: $<input type="number" name="custom_fee" step="0.01" min="0" style="width:80px;" disabled></label>';
            echo '</div>';
            echo '<input type="hidden" name="member_id" value="'.$member_id.'">';
            echo '<input type="hidden" name="standard_fee" value="'.$standard_fee.'">';
            if($special_fee) {
                echo '<input type="hidden" name="special_fee" value="'.$special_fee['fee_amount'].'">';
            }
            wp_nonce_field('checkin_manual', 'checkin_nonce');
            echo '<p>';
            echo '<button type="submit" name="submit_checkin" class="button button-primary button-large">✓ Record Check-In</button>';
            echo ' <a href="'.admin_url('admin.php?page=membership_manager').'" class="button button-large">Cancel</a>';
            echo '</p>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            
            // JavaScript for custom fee input
            echo '<script>
            document.querySelectorAll("input[name=\"fee_type\"]").forEach(radio => {
                radio.addEventListener("change", function() {
                    const customInput = document.querySelector("input[name=\"custom_fee\"]");
                    customInput.disabled = this.value !== "custom";
                });
            });
            </script>';
            return;
        } else {
            echo "<div class='notice notice-error'><p>✕ Cannot check in this member.</p></div>";
        }
    }

    // Handle fee selection form submission
    if (isset($_POST['submit_checkin']) && isset($_POST['checkin_nonce']) && wp_verify_nonce($_POST['checkin_nonce'], 'checkin_manual')) {
        $member_id = intval($_POST['member_id']);
        $fee_type = sanitize_text_field($_POST['fee_type']);
        $fee = 0;
        
        if ($fee_type === 'standard') {
            $fee = floatval($_POST['standard_fee']);
        } elseif ($fee_type === 'special') {
            $fee = floatval($_POST['special_fee']);
        } elseif ($fee_type === 'custom') {
            $fee = floatval($_POST['custom_fee']);
        }
        
        $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d", $member_id), ARRAY_A);
        
        $wpdb->insert($visits_tbl, array(
            'member_id' => $member_id,
            'visit_time' => current_time('mysql'),
            'daily_fee' => $fee,
            'notes' => 'Manual check-in by admin'
        ));
        $wpdb->update($tbl, array('last_visited' => current_time('mysql')), array('id' => $member_id));
        echo "<div class='notice notice-success'><p>✓ Check-in recorded for ".esc_html($member['name'])." - Fee: \$".number_format($fee, 2)."</p></div>";
    }

    if (isset($_GET['ban'])) {
        $ban_id = intval($_GET['ban']);
        $reason = isset($_POST['ban_reason']) ? sanitize_text_field($_POST['ban_reason']) : '';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ban_confirm'])) {
            $wpdb->update($tbl, array('banned'=>1,'banned_reason'=>$reason,'banned_on'=>current_time('mysql')), array('id'=>$ban_id));
            echo "<div class='notice notice-warning'><p>✓ Member banned.</p></div>";
        } else {
            echo "<div class='wrap'><h1>Ban Member</h1><form method='post'><input type='hidden' name='ban_confirm' value='1'><label>Reason: <input name='ban_reason' class='regular-text'></label><br><button type='submit' class='button button-primary'>Ban</button><a href='".admin_url('admin.php?page=membership_manager')."' class='button'>Cancel</a></form></div>";
            return;
        }
    }

    if ($editing && empty($row ?? null)) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
        if (!$row) { $editing = false; $error="Member not found."; }
    } else if (!$editing) {
        $row = array('id'=>0,'first_name'=>'','last_name'=>'','partner_first_name'=>'','partner_last_name'=>'','name'=>'','email'=>'','phone'=>'','sex'=>'','partner_sex'=>'','level'=>'Single','partner_name'=>'','paid'=>0,'payment_amount'=>0.00,'age'=>'','partner_age'=>'','newsletter'=>0,'agreed_terms'=>1,'photo_url'=>'','last_visited'=>null,'banned'=>0,'banned_reason'=>'','banned_on'=>null,'member_code'=>'');
    }

    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['unban']) && isset($row['id'])) {
        $wpdb->update($tbl, array('banned'=>0,'banned_reason'=>'','banned_on'=>null), array('id'=>$row['id']));
        $success = "Member unbanned.";
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d", $row['id']), ARRAY_A);
        $editing = true;
    }

    if (isset($_POST['regenerate_code']) && isset($_POST['id']) && intval($_POST['id']) > 0) {
        $id = intval($_POST['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d", $id), ARRAY_A);
        if ($row) {
            $new_code = mmgr_generate_member_code($row['first_name'].$row['last_name']);
            $wpdb->update($tbl, array('member_code' => $new_code), array('id' => $row['id']));
            mmgr_generate_qr_file($new_code);
            $row['member_code'] = $new_code;
            $success = "New QR code generated.";
            $editing = true;
        }
    }

    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mmgr_save']) && isset($_POST['member_nonce']) && wp_verify_nonce($_POST['member_nonce'], 'mmgr_save')) {
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'partner_first_name' => isset($_POST['partner_first_name']) ? sanitize_text_field($_POST['partner_first_name']) : '',
            'partner_last_name' => isset($_POST['partner_last_name']) ? sanitize_text_field($_POST['partner_last_name']) : '',
            'name' => sanitize_text_field($_POST['first_name']) . ' ' . sanitize_text_field($_POST['last_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'level' => sanitize_text_field($_POST['level']),
            'sex' => sanitize_text_field($_POST['sex'] ?? ''),
            'partner_sex' => isset($_POST['partner_sex']) ? sanitize_text_field($_POST['partner_sex']) : '',
            'age' => sanitize_text_field($_POST['age']),
            'partner_age' => isset($_POST['partner_age']) ? sanitize_text_field($_POST['partner_age']) : '',
            'newsletter' => isset($_POST['newsletter']) ? 1 : 0,
            'agreed_terms' => isset($_POST['agreed_terms']) ? 1 : 0,
            'paid' => isset($_POST['paid']) ? 1 : 0,
            'payment_amount' => floatval($_POST['amount_paid']),
            'photo_url' => isset($_POST['photo_url']) ? esc_url_raw($_POST['photo_url']) : ''
        );
        $data['partner_name'] = trim($data['partner_first_name'] . ' ' . $data['partner_last_name']);
        
        $id = intval($_POST['id']);
        if ($id > 0) {
            $wpdb->update($tbl, $data, array('id'=>$id));
            $success = "Member updated.";
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d", $id), ARRAY_A);
            $editing = true;
        } else {
            $code = mmgr_generate_member_code($data['first_name'].$data['last_name']);
            $data['member_code'] = $code;
            $data['start_date'] = date('Y-m-d');
            $data['expire_date'] = date('Y-m-d',strtotime('+1 year'));
            $wpdb->insert($tbl, $data);
            mmgr_generate_qr_file($code);
            $success = "Member added. Code: ".esc_html($code);
            $editing = true;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE member_code=%s", $code), ARRAY_A);
        }
    }

    echo '<div class="wrap"><h1>Membership Management</h1>';
    if ($success) echo '<div class="notice notice-success"><p>✓ '.$success.'</p></div>';
    if ($error) echo '<div class="notice notice-error"><p>✕ '.$error.'</p></div>';

    // Show member code and QR with print buttons when editing
    if ($editing && $row && $row['id']) {
        $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code='.urlencode($row['member_code']));
        
        echo '<div style="background:#e7f3ff;border:2px solid #0073aa;padding:20px;border-radius:8px;margin-bottom:20px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">';
        echo '<div>';
        echo '<h2 style="margin:0 0 10px 0;color:#0073aa;">📋 Member Code: <span style="font-size:28px;font-weight:bold;font-family:monospace;color:#d00;">'.esc_html($row['member_code']).'</span></h2>';
        echo '<p style="margin:0;"><small>Use this code for manual check-in or QR code scanning</small></p>';
        echo '</div>';
        echo '<div>';
        echo '<img src="'.esc_url($qr_url).'" style="width:120px;height:120px;border:2px solid #0073aa;border-radius:6px;background:white;padding:5px;" alt="QR Code">';
        echo '</div>';
        echo '</div>';
        echo '<div style="margin-top:15px;display:flex;gap:10px;flex-wrap:wrap;">';
        echo '<button type="button" onclick="printQRCode(\''.esc_js($row['member_code']).'\', \''.esc_js($row['name']).'\')" class="button button-primary">🖨️ Print QR Code Card</button>';
        echo '<a href="'.esc_url($qr_url).'" download="qr-'.esc_attr($row['member_code']).'.png" class="button">💾 Download QR Code</a>';
        echo '</div>';
        echo '</div>';
        
        // Add print script
        ?>
        <script>
        function printQRCode(code, name) {
            var qrUrl = '<?php echo admin_url("admin-ajax.php?action=mmgr_qrcode&code="); ?>' + encodeURIComponent(code);
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
        <?php
    }

    if ($editing && $row['banned']) {
        echo "<div class='notice notice-warning'><p><strong>🚫 BANNED MEMBER</strong><br>";
        if ($row['banned_reason']) echo "Reason: ".esc_html($row['banned_reason'])."<br>";
        echo "Banned on: ".esc_html($row['banned_on'])."<br>";
        echo "<form method='post' style='margin-top:10px;'><button type='submit' name='unban' class='button'>Unban Member</button></form></p></div>";
    }
    ?>
    <form method="POST" enctype="multipart/form-data" class="mmgr-member-form">
    <input type='hidden' name='id' value='<?php echo intval($row['id'] ?? 0); ?>'>
    <div class="mmgr-form-section">
    <h2><?php echo ($editing ? 'Edit Member' : 'Add New Member'); ?></h2>
    <table class="form-table"><tbody>
    <tr><th scope="row"><label for="level">Type *</label></th><td><select name='level' id='level' onchange="document.getElementById('mmgr_partner').style.display=this.value=='Couple'?'table-row':'none';">
    <?php foreach($levels as $lvl) echo "<option value='".esc_attr($lvl['level_name'])."' ".($row['level']==$lvl['level_name']?'selected':'').">".esc_html($lvl['level_name'])."</option>"; ?>
    </select></td></tr>
    <tr><th scope="row"><label for="first_name">First Name *</label></th><td><input name='first_name' id='first_name' required value="<?php echo esc_attr($row['first_name'] ?? ''); ?>"></td></tr>
    <tr><th scope="row"><label for="last_name">Last Name *</label></th><td><input name='last_name' id='last_name' required value="<?php echo esc_attr($row['last_name'] ?? ''); ?>"></td></tr>
    <tr><th scope="row"><label for="sex">Sex *</label></th><td><select name='sex' id='sex' required>
      <option value=''>Select Sex</option>
      <option value='Male' <?php echo ($row['sex']=='Male'?'selected':''); ?>>Male</option>
      <option value='Female' <?php echo ($row['sex']=='Female'?'selected':''); ?>>Female</option>
      <option value='Non-binary' <?php echo ($row['sex']=='Non-binary'?'selected':''); ?>>Non-binary</option>
      <option value='Undisclosed' <?php echo ($row['sex']=='Undisclosed'?'selected':''); ?>>Undisclosed</option>
    </select></td></tr>
    <tr><th scope="row"><label for="dob">Date of Birth *</label></th><td><input type="date" name="age" id="dob" required value="<?php echo esc_attr($row['age'] ?? ''); ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"></td></tr>
    <tr id="mmgr_partner" style="display:<?php echo ($row['level']=='Couple'?'table-row':'none'); ?>">
      <th scope="row">Partner</th><td>
        First: <input name="partner_first_name" value="<?php echo esc_attr($row['partner_first_name'] ?? ''); ?>"> 
        Last: <input name="partner_last_name" value="<?php echo esc_attr($row['partner_last_name'] ?? ''); ?>">
        Sex: <select name='partner_sex'>
          <option value=''>Select</option>
          <option value='Male' <?php echo ($row['partner_sex']=='Male'?'selected':''); ?>>Male</option>
          <option value='Female' <?php echo ($row['partner_sex']=='Female'?'selected':''); ?>>Female</option>
          <option value='Non-binary' <?php echo ($row['partner_sex']=='Non-binary'?'selected':''); ?>>Non-binary</option>
          <option value='Undisclosed' <?php echo ($row['partner_sex']=='Undisclosed'?'selected':''); ?>>Undisclosed</option>
        </select>
        DOB: <input type="date" name="partner_age" value="<?php echo esc_attr($row['partner_age'] ?? ''); ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
      </td>
    </tr>
    <tr><th scope="row"><label for="phone">Phone *</label></th><td><input name='phone' id='phone' type="tel" required value="<?php echo esc_attr($row['phone'] ?? ''); ?>"></td></tr>
    <tr><th scope="row"><label for="email">Email *</label></th><td><input name='email' id='email' type="email" required value="<?php echo esc_attr($row['email'] ?? ''); ?>"></td></tr>
    <tr><th scope="row"><label for="photo">Photo</label></th><td><?php if (!empty($row['photo_url'])): ?><img src="<?php echo esc_url($row['photo_url']); ?>" style="max-width:90px;display:block;margin-bottom:8px;" alt="Member photo"><?php endif; ?><input type="text" name="photo_url" id="photo" value="<?php echo esc_attr($row['photo_url'] ?? ''); ?>" class="regular-text"><button type="button" onclick="mmgrUploadPhoto(this)" class="button">Upload</button></td></tr>
    <tr><th scope="row"><label for="newsletter">Newsletter</label></th><td><input type="checkbox" name="newsletter" id="newsletter" value="1" <?php echo !empty($row['newsletter'])?'checked':''; ?>></td></tr>
    <tr><th scope="row"><label for="agreed">Agreed to Terms</label></th><td><input type="checkbox" name="agreed_terms" id="agreed" value="1" <?php echo !empty($row['agreed_terms'])?'checked':''; ?>></td></tr>
    <tr><th scope="row"><label for="paid">Paid</label></th><td><input type="checkbox" name="paid" id="paid" value="1" <?php echo !empty($row['paid'])?'checked':''; ?>></td></tr>
    <tr><th scope="row"><label for="amount_paid">Amount Paid ($)</label></th><td><input name="amount_paid" id="amount_paid" type="number" step="0.01" min="0" value="<?php echo esc_attr($row['payment_amount'] ?? 0); ?>"></td></tr>
    </tbody></table>
    </div>
    <?php wp_nonce_field('mmgr_save', 'member_nonce'); ?>
    <p><button type='submit' name='mmgr_save' class="button button-primary"><?php echo ($editing?'Update':'Add'); ?> Member</button>
    <?php if ($editing && $row['id']) { ?>
    <button type="submit" name="regenerate_code" class="button" onclick="return confirm('Generate new QR code?')">Regenerate Code</button>
    <?php } ?>
    </p>
    </form>
    <?php
    
    // Show affiliated accounts when editing
    if ($editing && $row && $row['id']) {
        $affiliated = mmgr_get_affiliated_accounts($row['id'], $row['phone'], $row['email']);
        
        if (!empty($affiliated)) {
            echo '<div style="background:#fff3cd;border-left:4px solid #ff9800;padding:15px;border-radius:6px;margin:20px 0;">';
            echo '<h3 style="margin-top:0;color:#856404;">🔗 Affiliated Accounts (' . count($affiliated) . ')</h3>';
            echo '<p style="margin-bottom:15px;color:#856404;">The following accounts share the same phone number or email address:</p>';
            echo '<table class="widefat"><thead><tr><th>Member Code</th><th>Name</th><th>Level</th><th>Match Type</th><th>Contact</th><th>Expires</th><th>Actions</th></tr></thead><tbody>';
            
            foreach ($affiliated as $aff) {
                $match_icon = $aff['match_type'] === 'phone' ? '📞' : '📧';
                $match_label = $aff['match_type'] === 'phone' ? 'Phone Match' : 'Email Match';
                
                echo '<tr>';
                echo '<td><strong style="font-family:monospace;color:#d00;">'.esc_html($aff['member_code']).'</strong></td>';
                echo '<td>'.esc_html($aff['name']).'</td>';
                echo '<td>'.esc_html($aff['level']).'</td>';
                echo '<td>'.$match_icon.' '.esc_html($match_label).'</td>';
                echo '<td>';
                if ($aff['match_type'] === 'phone') {
                    echo '📞 '.esc_html($aff['phone']);
                } else {
                    echo '📧 '.esc_html($aff['email']);
                }
                echo '</td>';
                echo '<td>'.esc_html($aff['expire_date']).'</td>';
                echo '<td><a href="'.admin_url('admin.php?page=membership_add&id='.$aff['id']).'" class="button button-small">View</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        }
        
        // Show recent visits
        $visits = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$visits_tbl` WHERE member_id=%d ORDER BY visit_time DESC LIMIT 20", $row['id']), ARRAY_A);
        echo "<h2>Recent Visits (Last 20)</h2>";
        if ($visits) {
            echo "<table class='widefat'><thead><tr><th>Date/Time</th><th>Fee</th><th>Notes</th></tr></thead><tbody>";
            foreach($visits as $v) {
                echo "<tr><td>".esc_html($v['visit_time'])."</td><td>\$".number_format($v['daily_fee'], 2)."</td><td>".esc_html($v['notes'])."</td></tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No visits yet.</p>";
        }
    }
    
    // SEARCH AND FILTER SECTION
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $filter_level = isset($_GET['filter_level']) ? sanitize_text_field($_GET['filter_level']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    
    echo "<hr><h2>Search & Filter Members</h2>";
    echo '<form method="get" style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:20px;">';
    echo '<input type="hidden" name="page" value="membership_manager">';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end;">';
    
    // Search input
    echo '<div>';
    echo '<label for="search" style="display:block;margin-bottom:5px;font-weight:bold;">Search</label>';
    echo '<input type="text" name="search" id="search" value="'.esc_attr($search_query).'" placeholder="Name, email, phone, or code..." style="width:100%;" />';
    echo '</div>';
    
    // Level filter
    echo '<div>';
    echo '<label for="filter_level" style="display:block;margin-bottom:5px;font-weight:bold;">Level</label>';
    echo '<select name="filter_level" id="filter_level" style="width:100%;">';
    echo '<option value="">All Levels</option>';
    $all_levels = $wpdb->get_results("SELECT DISTINCT level FROM $tbl WHERE level IS NOT NULL AND level != '' ORDER BY level", ARRAY_A);
    foreach($all_levels as $lvl) {
        $selected = ($filter_level === $lvl['level']) ? 'selected' : '';
        echo '<option value="'.esc_attr($lvl['level']).'" '.$selected.'>'.esc_html($lvl['level']).'</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // Status filter
    echo '<div>';
    echo '<label for="filter_status" style="display:block;margin-bottom:5px;font-weight:bold;">Status</label>';
    echo '<select name="filter_status" id="filter_status" style="width:100%;">';
    echo '<option value="">All Status</option>';
    echo '<option value="active" '.($filter_status === 'active' ? 'selected' : '').'>Active</option>';
    echo '<option value="expired" '.($filter_status === 'expired' ? 'selected' : '').'>Expired</option>';
    echo '<option value="banned" '.($filter_status === 'banned' ? 'selected' : '').'>Banned</option>';
    echo '</select>';
    echo '</div>';
    
    // Buttons
    echo '<div style="display:flex;gap:10px;">';
    echo '<button type="submit" class="button button-primary">🔍 Search</button>';
    echo '<a href="'.admin_url('admin.php?page=membership_manager').'" class="button">Clear</a>';
    echo '</div>';
    
    echo '</div>';
    echo '</form>';
    
    // Build the query with search and filters
    $query = "SELECT * FROM `$tbl` WHERE 1=1";
    
    if (!empty($search_query)) {
        $search_like = '%' . $wpdb->esc_like($search_query) . '%';
        $query .= $wpdb->prepare(" AND (first_name LIKE %s OR last_name LIKE %s OR name LIKE %s OR email LIKE %s OR phone LIKE %s OR member_code LIKE %s)", 
            $search_like, $search_like, $search_like, $search_like, $search_like, $search_like);
    }
    
    if (!empty($filter_level)) {
        $query .= $wpdb->prepare(" AND level = %s", $filter_level);
    }
    
    if (!empty($filter_status)) {
        if ($filter_status === 'banned') {
            $query .= " AND banned = 1";
        } elseif ($filter_status === 'expired') {
            $query .= " AND expire_date < CURDATE() AND banned = 0";
        } elseif ($filter_status === 'active') {
            $query .= " AND expire_date >= CURDATE() AND banned = 0";
        }
    }
    
    $query .= " ORDER BY id DESC LIMIT 100";
    $rows = $wpdb->get_results($query, ARRAY_A);
    
    // Show result count
    $total_count = count($rows);
    $result_text = $total_count === 1 ? '1 member' : $total_count . ' members';
    if (!empty($search_query) || !empty($filter_level) || !empty($filter_status)) {
        echo "<p style='background:#e7f3ff;padding:10px;border-left:4px solid #0073aa;border-radius:4px;'><strong>Found {$result_text}</strong>";
        if (!empty($search_query)) echo " matching \"<strong>".esc_html($search_query)."</strong>\"";
        echo "</p>";
    }
    
    // Generate Blank QR Cards button - ADDED FEATURE
    echo '<div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #00a32a;">';
    echo '<h3 style="margin-top:0;">🎫 Pre-Print Blank QR Cards</h3>';
    echo '<p>Generate QR codes for future members. Print these cards and assign them during registration.</p>';
    echo '<button type="button" onclick="generateBlankCards()" class="button button-primary">🎫 Generate Blank QR Cards</button>';
    echo '</div>';
    ?>
    <script>
    function generateBlankCards() {
        var quantity = prompt('How many blank QR cards to generate? (1-100)', '10');
        if (!quantity || quantity < 1 || quantity > 100) return;
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write('<html><head><title>Blank QR Cards</title>');
        printWindow.document.write('<style>@media print{@page{size:letter;margin:0.5in;}}body{font-family:Arial;}.card{width:3.5in;height:2in;border:2px dashed #ccc;padding:10px;margin:10px;display:inline-block;text-align:center;page-break-inside:avoid;}.card img{width:140px;height:140px;margin:5px auto;}.code{font-size:14px;font-weight:bold;font-family:monospace;margin-top:5px;}</style>');
        printWindow.document.write('</head><body>');
        
        for(var i = 0; i < quantity; i++) {
            var code = 'MB' + Date.now() + Math.random().toString(36).substr(2, 6).toUpperCase();
            var qrUrl = '<?php echo admin_url("admin-ajax.php?action=mmgr_qrcode&code="); ?>' + encodeURIComponent(code);
            printWindow.document.write('<div class="card">');
            printWindow.document.write('<img src="' + qrUrl + '">');
            printWindow.document.write('<div class="code">' + code + '</div>');
            printWindow.document.write('</div>');
        }
        
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        setTimeout(function() { printWindow.print(); }, 500);
    }
    </script>
    <?php
    
    // ALL MEMBERS TABLE - WITH QR CODE UNDER MEMBER CODE AND LEVEL ABOVE PHOTO
    $rows = $wpdb->get_results($query, ARRAY_A);
    echo "<h2>All Members</h2><table class='widefat'><thead><tr><th>Member Code & QR</th><th>Photo & Level</th><th>Name</th><th>Email</th><th>Phone</th><th>DOB</th><th>Expires</th><th>Last Visit</th><th>Affiliates</th><th>Actions</th></tr></thead><tbody>";
    
    if (empty($rows)) {
        echo "<tr><td colspan='10' style='text-align:center;padding:40px;color:#666;'>No members found</td></tr>";
    }
    
    foreach($rows as $m) {
        // Photo with Level above it
        $photo_display = '<div style="text-align:center;">';
        $photo_display .= '<div style="font-weight:bold;color:#0073aa;margin-bottom:5px;">'.esc_html($m['level']).'</div>';
        if (!empty($m['photo_url'])) {
            $photo_display .= '<img src="'.esc_url($m['photo_url']).'" style="width:80px;height:80px;object-fit:cover;border-radius:4px;display:block;margin:0 auto;" alt="Member photo">';
        } else {
            $photo_display .= '<div style="width:80px;height:80px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;margin:0 auto;color:#999;">No Photo</div>';
        }
        $photo_display .= '</div>';
        
        // Count affiliated accounts
        $aff_count = count(mmgr_get_affiliated_accounts($m['id'], $m['phone'], $m['email']));
        $aff_display = $aff_count > 0 ? '<span style="background:#ff9800;color:white;padding:2px 8px;border-radius:10px;font-weight:bold;">'.$aff_count.'</span>' : '—';
        
        // Generate QR code URL
        $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code='.urlencode($m['member_code']));
        
        echo "<tr>";
        
        // Member Code & QR Code column
        echo "<td style='text-align:center;'>";
        echo "<strong style='font-family:monospace;font-size:14px;color:#d00;display:block;margin-bottom:5px;'>".esc_html($m['member_code'])."</strong>";
        echo "<img src='".esc_url($qr_url)."' style='max-width:80px;height:auto;display:block;margin:0 auto;' alt='QR Code'>";
        echo "</td>";
        
        // Photo & Level column
        echo "<td>{$photo_display}</td>";
        
        echo "<td>".esc_html($m['first_name']." ".$m['last_name'])."</td>";
        echo "<td>".esc_html($m['email'])."</td>";
        echo "<td>".esc_html($m['phone'])."</td>";
        echo "<td>".esc_html($m['age'])."</td>";
        echo "<td>".esc_html($m['expire_date'])."</td>";
        echo "<td>".($m['last_visited'] ? esc_html(date('M d, Y', strtotime($m['last_visited']))) : "Never")."</td>";
        echo "<td style='text-align:center;'>{$aff_display}</td>";
		echo "<td>";
		echo "<a href='".admin_url('admin.php?page=membership_add&id='.$m['id'])."'>Edit</a> | ";
		echo "<a href='".home_url('/member-dashboard/?view_member='.$m['id'])."' target='_blank' style='color:#0073aa;font-weight:bold;'>👁️ View Portal</a> | ";
		echo "<a href='".admin_url('admin.php?page=membership_manager&checkin='.$m['id'].'&nonce='.wp_create_nonce('checkin_member'))."'>Check-In</a> | ";
		echo ($m['banned'] ? "<span style='color:red;'>🚫 BANNED</span>" : "<a href='".admin_url('admin.php?page=membership_manager&ban='.$m['id'])."'>Ban</a>");
		echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}	