<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page('Memberships', 'Memberships', 'manage_options', 'membership_manager', 'mmgr_admin_page','dashicons-groups');
    add_submenu_page('membership_manager','Membership Levels','Levels','manage_options','membership_levels','mmgr_levels_admin');
    add_submenu_page('membership_manager','Membership Settings','Settings','manage_options','membership_settings','mmgr_settings_admin');
});

function mmgr_settings_admin() {
    mmgr_ensure_tables_exist();
    if(isset($_POST['mmgr_coc_save'])) {
        update_option('mmgr_code_of_conduct', wp_kses_post($_POST['mmgr_code_of_conduct']));
        echo "<div class='updated'><p>Code of Conduct saved!</p></div>";
    }
    $coc = get_option('mmgr_code_of_conduct', 'Add your code of conduct here.');
    echo '<div class="wrap"><h2>Membership Settings</h2>
    <form method="post"><table class="form-table">
    <tr><th>Code of Conduct</th><td><textarea name="mmgr_code_of_conduct" class="large-text" rows="10">'.esc_textarea($coc).'</textarea></td></tr>
    </table>
    <p><button type="submit" name="mmgr_coc_save" class="button button-primary">Save</button></p>
    </form></div>';
}

function mmgr_levels_admin() {
    mmgr_ensure_tables_exist();
    global $wpdb;
    $tbl = $wpdb->prefix."membership_levels";
    
    if(isset($_POST['level_save'])) {
        $name = sanitize_text_field($_POST['level_name']);
        $price = floatval($_POST['level_price']);
        if(isset($_POST['id']) && $_POST['id']) {
            $wpdb->update($tbl, array('level_name'=>$name,'price'=>$price), array('id'=>intval($_POST['id'])));
            echo "<div class='notice notice-success'><p>Level updated.</p></div>";
        } else {
            $wpdb->insert($tbl, array('level_name'=>$name,'price'=>$price));
            echo "<div class='notice notice-success'><p>Level added.</p></div>";
        }
    }
    
    if(isset($_GET['delete'])) {
        $wpdb->delete($tbl, array('id'=>intval($_GET['delete'])));
        echo "<div class='notice notice-warning'><p>Level deleted.</p></div>";
    }
    
    echo "<div class='wrap'><h2>Membership Levels</h2>";
    echo '<form method="post"><h3>'.(isset($_GET['edit'])?'Edit':'Add New').' Level</h3>';
    
    if(isset($_GET['edit'])) {
        $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d",intval($_GET['edit'])),ARRAY_A);
    } else {
        $edit = array('id'=>'','level_name'=>'','price'=>'');
    }
    
    echo '<input type="hidden" name="id" value="'.esc_attr($edit['id']).'">
        <label>Level Name: <input name="level_name" required value="'.esc_attr($edit['level_name']).'"></label>
        <label>Price: $<input name="level_price" type="number" step="0.01" min="0" required value="'.esc_attr($edit['price']).'"></label>
        <button type="submit" name="level_save" class="button">'.(isset($_GET['edit'])?'Update':'Add').'</button>
    </form>';
    
    echo "<hr><h3>All Levels</h3><table class='widefat'><tr><th>ID</th><th>Name</th><th>Price</th><th>Edit</th><th>Delete</th></tr>";
    foreach($wpdb->get_results("SELECT * FROM `$tbl`",ARRAY_A) as $row){
        echo "<tr><td>{$row['id']}</td><td>{$row['level_name']}</td><td>\${$row['price']}</td>
        <td><a href='?page=membership_levels&edit={$row['id']}'>Edit</a></td>
        <td><a href='?page=membership_levels&delete={$row['id']}' onclick='return confirm(\"Delete?\")'>Delete</a></td></tr>";
    }
    echo "</table></div>";
}

function mmgr_admin_page() {
    mmgr_ensure_tables_exist();
    global $wpdb;
    $tbl = $wpdb->prefix."memberships";
    $levels_tbl = $wpdb->prefix."membership_levels";
    $visits_tbl = $wpdb->prefix."membership_visits";
    $levels = $wpdb->get_results("SELECT * FROM $levels_tbl",ARRAY_A);
    $editing = isset($_GET['edit']);
    $success = $error = false;

    if (isset($_GET['delete'])) {
        $wpdb->delete($tbl, array('id'=>intval($_GET['delete'])));
        echo "<div class='notice notice-success'><p>Member deleted.</p></div>";
    }

    if (isset($_GET['ban'])) {
        $ban_id = intval($_GET['ban']);
        $reason = isset($_POST['ban_reason']) ? sanitize_text_field($_POST['ban_reason']) : '';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ban_confirm'])) {
            $wpdb->update($tbl, array('banned'=>1,'banned_reason'=>$reason,'banned_on'=>current_time('mysql')), array('id'=>$ban_id));
            echo "<div class='notice notice-warning'><p>Member banned.</p></div>";
        } else {
            echo "<div class='wrap'><h2>Ban Member</h2><form method='post'><input type='hidden' name='ban_confirm' value='1'><label>Reason: <input name='ban_reason' class='regular-text'></label><br><button type='submit' class='button button-primary'>Ban</button><a href='".admin_url('admin.php?page=membership_manager')."' class='button'>Cancel</a></form></div>";
            return;
        }
    }

    if ($editing && empty($row)) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE id=%d", intval($_GET['edit'])), ARRAY_A);
        if (!$row) { $editing = false; $error="Member not found."; }
    } else if (!$editing) {
        $row = array('id'=>0,'first_name'=>'','last_name'=>'','partner_first_name'=>'','partner_last_name'=>'','name'=>'','email'=>'','phone'=>'','level'=>'standard','partner_name'=>'','notes'=>'','paid'=>0,'amount_paid'=>0.00,'age'=>'','partner_age'=>'','newsletter'=>0,'agreed_terms'=>0,'photo_url'=>'','last_visited'=>null,'banned'=>0,'banned_reason'=>'','banned_on'=>null);
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
            $row['member_code'] = $new_code;
            $success = "New QR code generated.";
            $editing = true;
        }
    }

    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mmgr_save'])) {
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'partner_first_name' => isset($_POST['partner_first_name']) ? sanitize_text_field($_POST['partner_first_name']) : '',
            'partner_last_name' => isset($_POST['partner_last_name']) ? sanitize_text_field($_POST['partner_last_name']) : '',
            'name' => sanitize_text_field($_POST['first_name']) . ' ' . sanitize_text_field($_POST['last_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'level' => sanitize_text_field($_POST['level']),
            'age' => sanitize_text_field($_POST['age']),
            'partner_age' => isset($_POST['partner_age']) ? sanitize_text_field($_POST['partner_age']) : '',
            'newsletter' => isset($_POST['newsletter']) ? 1 : 0,
            'agreed_terms' => isset($_POST['agreed_terms']) ? 1 : 0,
            'notes' => sanitize_textarea_field($_POST['notes']),
            'paid' => isset($_POST['paid']) ? 1 : 0,
            'amount_paid' => floatval($_POST['amount_paid']),
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
            $success = "Member added.";
            $editing = true;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$tbl` WHERE member_code=%s", $code), ARRAY_A);
        }
    }

    echo '<div class="wrap"><h2>Membership Admin</h2>';
    if ($success) echo '<div class="notice notice-success"><p>'.$success.'</p></div>';
    if ($error) echo '<div class="notice notice-error"><p>'.$error.'</p></div>';

    if ($editing && $row['banned']) {
        echo "<div class='notice notice-warning'><b>BANNED MEMBER</b><br>";
        if ($row['banned_reason']) echo "Reason: ".esc_html($row['banned_reason'])."<br>";
        echo "<form method='post' style='margin-top:10px;'><input type='hidden' name='unban' value='1'><button type='submit' class='button'>Unban</button></form></div>";
    }
    ?>
    <form method="POST" enctype="multipart/form-data">
    <input type='hidden' name='id' value='<?=intval($row['id'])?>'>
    <table class="form-table"><tbody>
    <tr><th>Type</th><td><select name='level' id='mmgr_level' onchange="document.getElementById('mmgr_partner').style.display=this.value=='couple'?'table-row':'none';">
    <?php foreach($levels as $lvl) echo "<option value='".esc_attr($lvl['level_name'])."' ".($row['level']==$lvl['level_name']?'selected':'').">".esc_html($lvl['level_name'])."</option>";?>
    </select></td></tr>
    <tr><th>First Name</th><td><input name='first_name' required value="<?=esc_attr($row['first_name']);?>" class="regular-text"></td></tr>
    <tr><th>Last Name</th><td><input name='last_name' required value="<?=esc_attr($row['last_name']);?>" class="regular-text"></td></tr>
    <tr><th>Age Range</th><td>
        <select name="age" required>
            <option value="">Select Age Range</option>
            <option value="18-30" <?=($row['age']=='18-30'?'selected':'')?>>18-30</option>
            <option value="31-49" <?=($row['age']=='31-49'?'selected':'')?>>31-49</option>
            <option value="50+" <?=($row['age']=='50+'?'selected':'')?>>50+</option>
        </select>
    </td></tr>
    <tr id="mmgr_partner" style="display:<?=$row['level']=='couple'?'table-row':'none'?>">
      <th>Partner</th><td>First: <input name="partner_first_name" value="<?=esc_attr($row['partner_first_name']);?>"> Last: <input name="partner_last_name" value="<?=esc_attr($row['partner_last_name']);?>"> 
      Age Range: <select name="partner_age">
            <option value="">Select</option>
            <option value="18-30" <?=($row['partner_age']=='18-30'?'selected':'')?>>18-30</option>
            <option value="31-49" <?=($row['partner_age']=='31-49'?'selected':'')?>>31-49</option>
            <option value="50+" <?=($row['partner_age']=='50+'?'selected':'')?>>50+</option>
        </select>
      </td>
    </tr>
    <tr><th>Phone</th><td><input name='phone' required value="<?=esc_attr($row['phone']);?>" class="regular-text"></td></tr>
    <tr><th>Email</th><td><input name='email' type="email" required value="<?=esc_attr($row['email']);?>" class="regular-text"></td></tr>
    <tr><th>Photo</th><td><?php if (!empty($row['photo_url'])): ?><img src="<?=esc_url($row['photo_url']);?>" style="max-width:90px;display:block;margin-bottom:8px;"><?php endif; ?><input type="text" name="photo_url" value="<?=esc_attr($row['photo_url']);?>" class="regular-text"><button type="button" onclick="mmgrUploadPhoto(this)">Upload</button><script>function mmgrUploadPhoto(btn) { var u = wp.media({title: 'Photo',button: { text: 'Use' },multiple: false}).on('select', function() { var a = u.state().get('selection').first().toJSON(); btn.previousElementSibling.value = a.url; }).open(); }</script></td></tr>
    <tr><th>Newsletter</th><td><input type="checkbox" name="newsletter" value="1" <?=!empty($row['newsletter'])?'checked':''?>></td></tr>
    <tr><th>Agreed Terms</th><td><input type="checkbox" name="agreed_terms" value="1" <?=!empty($row['agreed_terms'])?'checked':''?>></td></tr>
    <tr><th>Notes</th><td><textarea name="notes" class="large-text"><?=esc_textarea($row['notes']);?></textarea></td></tr>
    <tr><th>Paid</th><td><input type="checkbox" name="paid" value="1" <?=!empty($row['paid'])?'checked':''?>></td></tr>
    <tr><th>Amount Paid</th><td><input name="amount_paid" type="number" step="0.01" min="0" value="<?=esc_attr($row['amount_paid']);?>"></td></tr>
    </tbody></table>
    <button type='submit' name='mmgr_save' class="button button-primary"><?=($editing?'Update':'Add')?></button>
    <?php if ($editing && $row['id']) { ?><form method="POST" style="display:inline"><input type="hidden" name="id" value="<?=intval($row['id'])?>"><button type="submit" name="regenerate_code" class="button" onclick="return confirm('New QR code?')">Regenerate Code</button></form><?php } ?>
    </form>
    <?php
    if ($editing && $row && $row['id']) {
        $visits = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$visits_tbl` WHERE member_id=%d ORDER BY visit_time DESC", $row['id']), ARRAY_A);
        echo "<h3>Visits</h3>";
        if ($visits) {
            foreach($visits as $v) echo "<div>".esc_html($v['visit_time'])."</div>";
        }
    }
    
    $rows = $wpdb->get_results("SELECT * FROM `$tbl` ORDER BY id DESC", ARRAY_A);
    echo "<h3>All Members</h3><table class='widefat'><thead><tr><th>ID</th><th>Photo</th><th>Name</th><th>Level</th><th>Email</th><th>Phone</th><th>Expires</th><th>Last Visit</th><th>QR Card</th><th>Actions</th></tr></thead><tbody>";
    foreach($rows as $m) {
        $photo_thumb = '';
        if (!empty($m['photo_url'])) {
            $photo_thumb = '<img src="'.esc_url($m['photo_url']).'" style="max-width:50px;height:50px;object-fit:cover;border-radius:4px;">';
        }
        
        $qr_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code='.urlencode($m['member_code']));
        $qr_card = '<a href="'.$qr_url.'" target="_blank" title="View/Print QR Card"><img src="'.$qr_url.'" style="max-width:64px;height:64px;border:1px solid #ddd;padding:2px;background:#fff;"></a>';
        
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$photo_thumb}</td>";
        echo "<td>".esc_html($m['first_name']." ".$m['last_name'])."</td>";
        echo "<td>".esc_html($m['level'])."</td>";
        echo "<td>".esc_html($m['email'])."</td>";
        echo "<td>".esc_html($m['phone'])."</td>";
        echo "<td>".esc_html($m['expire_date'])."</td>";
        echo "<td>".($m['last_visited'] ? esc_html($m['last_visited']) : "Never")."</td>";
        echo "<td>{$qr_card}</td>";
        echo "<td>";
        echo "<a href='".admin_url('admin.php?page=membership_manager&edit='.$m['id'])."'>Edit</a> | ";
        echo "<a href='".admin_url('admin.php?page=membership_manager&delete='.$m['id'])."' onclick='return confirm(\"Delete?\")'>Delete</a> | ";
        echo ($m['banned'] ? "<span style='color:red;'>BANNED</span>" : "<a href='".admin_url('admin.php?page=membership_manager&ban='.$m['id'])."'>Ban</a>");
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
}
?>