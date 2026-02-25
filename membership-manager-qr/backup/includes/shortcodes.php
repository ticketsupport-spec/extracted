<?php
if (!defined('ABSPATH')) exit;

// Registration Form Shortcode
add_shortcode('membership_registration', function($atts){
    mmgr_ensure_tables_exist();
    $success = $error = '';
    $first = $last = $email = $phone = $type = $age = '';
    $partner_first = $partner_last = $partner_age = $newsletter = '';
    
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mmgr_register'])) {
        $first = sanitize_text_field($_POST['first_name']);
        $last = sanitize_text_field($_POST['last_name']);
        $type = sanitize_text_field($_POST['level']);
        $phone = sanitize_text_field($_POST['phone']);
        $email = sanitize_email($_POST['email']);
        $age = sanitize_text_field($_POST['age']); // Now stores age range as text
        $newsletter = !empty($_POST['newsletter']) ? 1 : 0;
        $terms = !empty($_POST['agreed_terms']) ? 1 : 0;
        $partner_first = $partner_last = '';
        $partner_age = '';
        $photo_url = '';
        
        if ($type == 'couple') {
            $partner_first = sanitize_text_field($_POST['partner_first_name']);
            $partner_last = sanitize_text_field($_POST['partner_last_name']);
            $partner_age = sanitize_text_field($_POST['partner_age']); // Partner age range
        }
        
        if (!empty($_POST['photo_url'])) {
            $photo_url = esc_url_raw($_POST['photo_url']);
        }
        
        if (!$first || !$last || !$phone || !$email || !$age) {
            $error = 'All required fields must be filled in.';
        } elseif (!$terms) {
            $error = 'You must agree to the Code of Conduct and Terms.';
        } else {
            global $wpdb;
            $tbl = $wpdb->prefix."memberships";
            $code = substr(md5($first.$last.$email.time().rand(1000,9999)),0,12);
            $name = $first." ".$last;
            $partner_name = $partner_first || $partner_last ? trim($partner_first." ".$partner_last) : '';
            $wpdb->insert($tbl, array(
                'member_code' => $code,
                'first_name' => $first,
                'last_name' => $last,
                'partner_first_name' => $partner_first,
                'partner_last_name' => $partner_last,
                'partner_name' => $partner_name,
                'phone' => $phone,
                'email' => $email,
                'level' => $type,
                'age' => $age,
                'partner_age' => $partner_age,
                'newsletter' => $newsletter,
                'agreed_terms' => $terms,
                'photo_url' => $photo_url,
                'name' => $name,
                'start_date' => date('Y-m-d'),
                'expire_date' => date('Y-m-d',strtotime('+1 year'))
            ));
            $success = 'Thank you! Please pass the tablet back to your host.';
        }
    }
    
    $coc = get_option('mmgr_code_of_conduct', 'No Code of Conduct has been set.');
    ob_start();
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; }
    .mmgr-card {
        max-width: 520px;
        width: 100%;
        margin: 0 auto;
        padding: 20px 15px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }
    .mmgr-card h2 { text-align: center; margin: 0 0 20px 0; font-size: 24px; color: #333; }
    .mmgr-field { margin-bottom: 15px; }
    .mmgr-field label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #444; }
    .mmgr-card input[type=text], .mmgr-card input[type=email], .mmgr-card input[type=number], .mmgr-card input[type=tel], .mmgr-card select { width: 100%; padding: 12px; font-size: 16px; border-radius: 6px; border: 1px solid #ccc; background: #fff; -webkit-appearance: none; appearance: none; }
    .mmgr-card input:focus, .mmgr-card select:focus { border-color: #3676d3; outline: none; box-shadow: 0 0 0 3px rgba(54, 118, 211, 0.1); }
    .mmgr-err, .mmgr-ok { margin: 0 0 15px 0; border-radius: 6px; padding: 12px; font-size: 14px; }
    .mmgr-err { background: #ffe2e2; color: #9c1b1b; border: 1px solid #ebb2b2; }
    .mmgr-ok { background: #e2ffe8; color: #159742; border: 1px solid #b1e6bf; }
    .mmgr-card button[type=submit] { background: #3676d3; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 16px; padding: 14px 20px; cursor: pointer; width: 100%; margin-top: 10px; -webkit-appearance: none; appearance: none; }
    .mmgr-card button[type=submit]:active { background: #2563bb; }
    .mmgr-cam { border: 2px solid #3676d3; border-radius: 8px; padding: 12px; margin: 15px 0; background: #f0f5ff; }
    .mmgr-cam h4 { margin: 0 0 10px 0; color: #2573dc; font-size: 16px; }
    #mmgr-v, #mmgr-p { width: 100%; max-width: 100%; height: auto; border-radius: 6px; display: none; margin-bottom: 10px; background: #000; }
    .mmgr-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .mmgr-btns button { flex: 1; min-width: 100px; padding: 12px 10px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; -webkit-appearance: none; appearance: none; }
    .mmgr-btns .start { background: #2573dc; color: #fff; }
    .mmgr-btns .capture { background: #27ae60; color: #fff; }
    .mmgr-btns .stop { background: #e74c3c; color: #fff; }
    .mmgr-btns .retake { background: #f39c12; color: #fff; }
    .mmgr-loading { display: none; text-align: center; padding: 10px; color: #2573dc; font-weight: 600; font-size: 14px; }
    @media (max-width: 480px) {
        .mmgr-card { padding: 15px 10px; margin: 0; border-radius: 0; box-shadow: none; }
        .mmgr-card h2 { font-size: 20px; }
        .mmgr-field label { font-size: 13px; }
        .mmgr-btns { flex-direction: column; }
        .mmgr-btns button { width: 100%; min-width: auto; }
    }
    </style>
    <?php
    if ($success) {
        echo '<div class="mmgr-card"><div class="mmgr-ok">'.esc_html($success).'</div>';
        echo '<h3>New Member</h3><div style="padding:10px;background:#f9f9f9;border-radius:6px;">';
        if (!empty($photo_url)) echo '<div style="text-align:center;margin-bottom:15px;"><img src="'.esc_url($photo_url).'" style="max-width:150px;border-radius:8px;"></div>';
        echo '<p><b>Name:</b> '.esc_html($first.' '.$last).'</p>';
        echo '<p><b>Email:</b> '.esc_html($email).'</p>';
        echo '<p><b>Phone:</b> '.esc_html($phone).'</p>';
        echo '<p><b>Type:</b> '.esc_html($type).'</p>';
        echo '<p><b>Age Range:</b> '.esc_html($age).'</p>';
        if ($type == 'couple' && ($partner_first || $partner_last)) {
            echo '<p><b>Partner:</b> '.esc_html($partner_first.' '.$partner_last).' (Age Range: '.esc_html($partner_age).')</p>';
        }
        echo '</div></div>';
    } else {
        if ($error) echo '<div class="mmgr-card"><div class="mmgr-err">'.esc_html($error).'</div></div>';
    ?>
    <div class="mmgr-card">
    <h2>Membership Signup</h2>
    <form method="POST" enctype="multipart/form-data" autocomplete="off">
      <div class="mmgr-field">
        <label>Membership Type</label>
        <select name="level" id="mmgr-lv" required onchange="document.getElementById('mmgr-prt').style.display=this.value=='couple'?'block':'none';">
          <option value="single">Single</option>
          <option value="couple">Couple</option>
        </select>
      </div>
      <div class="mmgr-field"><label>First Name</label><input name="first_name" required></div>
      <div class="mmgr-field"><label>Last Name</label><input name="last_name" required></div>
      <div class="mmgr-field">
        <label>Age Range</label>
        <select name="age" required>
          <option value="">Select Age Range</option>
          <option value="18-30">18-30</option>
          <option value="31-49">31-49</option>
          <option value="50+">50+</option>
        </select>
      </div>
      <div id="mmgr-prt" style="display:none;">
        <div class="mmgr-field"><label>Partner First Name</label><input name="partner_first_name"></div>
        <div class="mmgr-field"><label>Partner Last Name</label><input name="partner_last_name"></div>
        <div class="mmgr-field">
          <label>Partner Age Range</label>
          <select name="partner_age">
            <option value="">Select Age Range</option>
            <option value="18-30">18-30</option>
            <option value="31-49">31-49</option>
            <option value="50+">50+</option>
          </select>
        </div>
      </div>
      <div class="mmgr-field"><label>Phone</label><input name="phone" type="tel" required></div>
      <div class="mmgr-field"><label>Email</label><input name="email" type="email" required></div>
      
      <div class="mmgr-field">
        <label style="display:flex;align-items:center;font-weight:normal;">
          <input type="checkbox" name="newsletter" value="1" checked style="width:auto;margin-right:8px;"> Newsletter
        </label>
      </div>
      
      <div class="mmgr-cam">
        <h4>📸 Take Photo (Optional)</h4>
        <video id="mmgr-v" autoplay playsinline></video>
        <canvas id="mmgr-c" style="display:none;"></canvas>
        <img id="mmgr-p" alt="Photo">
        <div class="mmgr-loading" id="mmgr-ld">Uploading...</div>
        <div class="mmgr-btns">
          <button type="button" class="start" onclick="mmgrStart(event)">📹 Start</button>
          <button type="button" class="capture" id="mmgr-cap" onclick="mmgrCap(event)" style="display:none;">📷 Capture</button>
          <button type="button" class="stop" id="mmgr-stp" onclick="mmgrStop(event)" style="display:none;">⏹ Stop</button>
          <button type="button" class="retake" id="mmgr-ret" onclick="mmgrRet(event)" style="display:none;">🔄 Retake</button>
        </div>
        <input type="hidden" name="photo_url" id="mmgr-url" value="">
      </div>
      
      <div style="border:1px solid #ddd;border-radius:6px;background:#f9f9f9;margin:15px 0;padding:12px;">
        <strong style="font-size:15px;display:block;margin-bottom:8px;">Code of Conduct</strong>
        <div style="max-height:120px;overflow-y:auto;background:#000000;padding:10px;margin:8px 0;border-radius:4px;font-size:13px;color:#ff00ff;"><?=wpautop($coc)?></div>
        <div style="height:15px;"></div>
        <label style="font-weight:600;display:flex;align-items:flex-start;">
          <input type="checkbox" name="agreed_terms" required style="width:auto;margin:2px 8px 0 0;flex-shrink:0;"> 
          <span>I agree to the Code of Conduct</span>
        </label>
      </div>
      <button type="submit" name="mmgr_register">Submit Application</button>
    </form>
    </div>
    
    <script>
    let mmgrS = null;
    async function mmgrStart(e) {
        e.preventDefault();
        try {
            mmgrS = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
            document.getElementById('mmgr-v').srcObject = mmgrS;
            document.getElementById('mmgr-v').style.display = 'block';
            document.querySelector('.mmgr-btns .start').style.display = 'none';
            document.getElementById('mmgr-cap').style.display = 'inline-block';
            document.getElementById('mmgr-stp').style.display = 'inline-block';
        } catch (err) { alert('Camera error: ' + err.message); }
    }
    function mmgrCap(e) {
        e.preventDefault();
        const v = document.getElementById('mmgr-v');
        const c = document.getElementById('mmgr-c');
        const ctx = c.getContext('2d');
        c.width = v.videoWidth; c.height = v.videoHeight;
        ctx.drawImage(v, 0, 0);
        document.getElementById('mmgr-p').src = c.toDataURL('image/jpeg', 0.9);
        document.getElementById('mmgr-p').style.display = 'block';
        document.getElementById('mmgr-v').style.display = 'none';
        document.getElementById('mmgr-cap').style.display = 'none';
        document.getElementById('mmgr-stp').style.display = 'none';
        document.getElementById('mmgr-ret').style.display = 'inline-block';
        document.getElementById('mmgr-ld').style.display = 'block';
        c.toBlob(blob => {
            const fd = new FormData();
            fd.append('action', 'mmgr_upload_photo');
            fd.append('photo', blob, 'photo.jpg');
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    document.getElementById('mmgr-ld').style.display = 'none';
                    if (d.success) document.getElementById('mmgr-url').value = d.data.url;
                })
                .catch(() => document.getElementById('mmgr-ld').style.display = 'none');
        }, 'image/jpeg', 0.9);
    }
    function mmgrStop(e) {
        e.preventDefault();
        if (mmgrS) mmgrS.getTracks().forEach(t => t.stop());
        document.getElementById('mmgr-v').style.display = 'none';
        document.querySelector('.mmgr-btns .start').style.display = 'inline-block';
        document.getElementById('mmgr-cap').style.display = 'none';
        document.getElementById('mmgr-stp').style.display = 'none';
    }
    function mmgrRet(e) {
        e.preventDefault();
        document.getElementById('mmgr-p').style.display = 'none';
        document.getElementById('mmgr-url').value = '';
        document.getElementById('mmgr-v').style.display = 'block';
        document.getElementById('mmgr-cap').style.display = 'inline-block';
        document.getElementById('mmgr-stp').style.display = 'inline-block';
        document.getElementById('mmgr-ret').style.display = 'none';
    }
    </script>
    <?php
    }
    return ob_get_clean();
});

// Check-in Shortcode
add_shortcode('membership_checkin', function($atts){
    mmgr_ensure_tables_exist();
    ob_start(); ?>
    <div style="max-width:600px;margin:30px auto;background:#fff;padding:20px;border-radius:10px;">
        <h2 style="text-align:center;">✓ Member Check-In</h2>
        <div style="display:flex;gap:10px;margin-bottom:15px;justify-content:center;flex-wrap:wrap;">
            <button class="active" onclick="mmgrM(event,'cam')" style="padding:10px 15px;border:2px solid #ddd;background:#f9f9f9;border-radius:6px;cursor:pointer;font-weight:600;">📱 Camera</button>
            <button onclick="mmgrM(event,'man')" style="padding:10px 15px;border:2px solid #ddd;background:#f9f9f9;border-radius:6px;cursor:pointer;font-weight:600;">⌨️ Manual</button>
            <button onclick="mmgrM(event,'hw')" style="padding:10px 15px;border:2px solid #ddd;background:#f9f9f9;border-radius:6px;cursor:pointer;font-weight:600;">📲 Hardware</button>
        </div>
        
        <div id="cam" style="display:block;">
            <video id="mmgr-vid" style="width:100%;border-radius:8px;display:none;background:#000;margin-bottom:10px;" playsinline></video>
            <button id="btn-start" onclick="mmgrCamStart(event)" style="width:100%;padding:10px;background:#27ae60;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;margin-bottom:10px;">▶ Start Camera</button>
            <button id="btn-stop" onclick="mmgrCamStop(event)" style="width:100%;padding:10px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;display:none;margin-bottom:10px;">⏹ Stop</button>
        </div>
        
        <div id="man" style="display:none;">
            <input type="text" id="man-code" placeholder="Enter code..." style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;font-size:16px;">
            <button onclick="mmgrChk(document.getElementById('man-code').value)" style="width:100%;padding:10px;background:#3676d3;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Submit</button>
        </div>
        
        <div id="hw" style="display:none;">
            <input type="text" id="hw-code" placeholder="Scan here..." style="width:100%;padding:12px;border:2px solid #ddd;border-radius:6px;font-size:16px;margin-bottom:10px;">
            <p style="text-align:center;color:#666;font-size:14px;">📲 Hardware scanner auto-detects</p>
        </div>
        
        <div id="mmgr-res" style="min-height:50px;margin-top:20px;padding:15px;border-radius:8px;"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script>
    let mmgrVid = null, mmgrAct = false, mmgrLast = null;
    function mmgrM(e, m) {
        e.preventDefault();
        if (mmgrAct) mmgrCamStop();
        document.getElementById('cam').style.display = m==='cam'?'block':'none';
        document.getElementById('man').style.display = m==='man'?'block':'none';
        document.getElementById('hw').style.display = m==='hw'?'block':'none';
        if(m==='man') setTimeout(()=>document.getElementById('man-code').focus(),100);
        if(m==='hw') setTimeout(()=>document.getElementById('hw-code').focus(),100);
    }
    async function mmgrCamStart(e) {
        e.preventDefault();
        try {
            mmgrVid = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
            let v = document.getElementById('mmgr-vid');
            v.srcObject = mmgrVid; v.style.display = 'block';
            document.getElementById('btn-start').style.display = 'none';
            document.getElementById('btn-stop').style.display = 'block';
            mmgrAct = true;
            mmgrScan();
        } catch(err) { alert('Camera error'); }
    }
    function mmgrCamStop(e) {
        if(e) e.preventDefault();
        if(mmgrVid) mmgrVid.getTracks().forEach(t=>t.stop());
        mmgrAct = false;
        document.getElementById('mmgr-vid').style.display = 'none';
        document.getElementById('btn-start').style.display = 'block';
        document.getElementById('btn-stop').style.display = 'none';
    }
    function mmgrScan() {
        let v = document.getElementById('mmgr-vid');
        let c = document.createElement('canvas');
        c.width = v.videoWidth; c.height = v.videoHeight;
        let ctx = c.getContext('2d');
        function scan() {
            if(!mmgrAct) return;
            c.width = v.videoWidth; c.height = v.videoHeight;
            ctx.drawImage(v,0,0);
            let d = ctx.getImageData(0,0,c.width,c.height);
            let qr = jsQR(d.data, c.width, c.height);
            if(qr && qr.data && mmgrLast !== qr.data) {
                mmgrLast = qr.data;
                mmgrChk(qr.data);
                setTimeout(()=>{mmgrLast=null;},1000);
            }
            requestAnimationFrame(scan);
        }
        scan();
    }
    document.addEventListener('DOMContentLoaded', ()=> {
        document.getElementById('man-code').addEventListener('keypress', e => {
            if(e.key==='Enter') { mmgrChk(e.target.value); e.target.value=''; e.preventDefault(); }
        });
        document.getElementById('hw-code').addEventListener('keypress', e => {
            if(e.key==='Enter') { mmgrChk(e.target.value); e.target.value=''; e.preventDefault(); }
        });
    });
    function mmgrChk(code) {
        if(!code) return;
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=mmgr_lookup&code='+encodeURIComponent(code))
        .then(r=>r.json())
        .then(d=>{
            if(d.status==='banned') {
                document.getElementById('mmgr-res').innerHTML = '<div style="color:#d00;font-size:2em;font-weight:bold;text-align:center;">BANNED</div>';
            } else if(d.status==='ok') {
                let m = d.member;
                let exp = new Date(m.expire_date);
                let now = new Date();
                let days = Math.ceil((exp-now) / 864e5);
                let expText = days < 0 ? '<span style="color:#b00;">EXPIRED</span>' : days+' days left';
                let html = (m.photo_url ? '<img src="'+m.photo_url+'" style="max-width:85px;float:right;border-radius:7px;">' : '') +
                  '<b>Name:</b> '+m.name+'<br/>' +
                  '<b>Email:</b> '+m.email+'<br/>' +
                  '<b>Phone:</b> '+m.phone+'<br/>'+
                  '<b>Level:</b> '+m.level+'<br/>'+
                  '<b>Age Range:</b> '+m.age+'<br/>'+
                  '<b>Expires:</b> '+m.expire_date+' ('+expText+')<br/>'+
                  '<b>Last Visit:</b> '+(m.last_visited||'Never');
                document.getElementById('mmgr-res').innerHTML = html;
            } else {
                document.getElementById('mmgr-res').innerHTML = '<span style="color:red;">Not Found</span>';
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
});

// Code of Conduct Display Shortcode
add_shortcode('membership_code_of_conduct', function($atts){
    $coc = get_option('mmgr_code_of_conduct', 'No Code of Conduct has been set.');
    
    // Process content to make lines starting with dash purple with no line spacing
    $coc_lines = explode("\n", $coc);
    $processed_coc = '';
    foreach ($coc_lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '-') === 0) {
            $processed_coc .= '<div style="color:#C552FF;font-weight:600;margin:0;padding:0;">' . esc_html($line) . '</div>';
        } else {
            $processed_coc .= $line . "\n";
        }
    }
    
    ob_start();
    ?>
    <div style="max-width:800px;margin:30px auto;padding:30px;background:#000;box-shadow:0 4px 20px rgba(0,0,0,0.5);border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
        <h2 style="text-align:center;color:#fff;font-weight:bold;margin-bottom:25px;font-size:32px;">Code of Conduct</h2>
        <div class="mmgr-coc-content" style="background:#1a1a1a;padding:25px;border-radius:6px;line-height:1.4;font-size:15px;color:#e0e0e0;">
            <?php echo wpautop($processed_coc); ?>
        </div>
    </div>
    <style>
    .mmgr-coc-content h3, .mmgr-coc-content h4, .mmgr-coc-content strong, .mmgr-coc-content b {
        color: #fff !important;
        font-weight: bold !important;
    }
    .mmgr-coc-content p {
        margin: 0 0 8px 0;
        padding: 0;
    }
    </style>
    <?php
    return ob_get_clean();
});
?>