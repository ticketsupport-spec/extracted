<?php
if (!defined('ABSPATH')) exit;


// Registration Form Shortcode
add_shortcode('membership_registration', function($atts){
    ob_start();
    
    $reg_title = get_option('mmgr_registration_title', 'Membership Signup');
    $coc_url = get_option('mmgr_coc_url', '');
    $success_url = get_option('mmgr_registration_success_url', '');
    $reg_logo_id = intval(get_option('mmgr_registration_logo_id', 0));
    $reg_logo_url = $reg_logo_id ? wp_get_attachment_url($reg_logo_id) : '';
    $reg_blurb = get_option('mmgr_registration_blurb', '');
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mmgr_register'])) {
        if (!isset($_POST['reg_nonce']) || !wp_verify_nonce($_POST['reg_nonce'], 'mmgr_register')) {
            echo '<div class="mmgr-error">Security check failed.</div>';
        } else {
            global $wpdb;
            $tbl = $wpdb->prefix . 'memberships';
            
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $sex = sanitize_text_field($_POST['sex']);
            $dob = sanitize_text_field($_POST['dob']);
            $level = sanitize_text_field($_POST['level']);
            $newsletter = isset($_POST['newsletter']) ? 1 : 0;
            
            $partner_first_name = isset($_POST['partner_first_name']) ? sanitize_text_field($_POST['partner_first_name']) : '';
            $partner_last_name = isset($_POST['partner_last_name']) ? sanitize_text_field($_POST['partner_last_name']) : '';
            $partner_sex = isset($_POST['partner_sex']) ? sanitize_text_field($_POST['partner_sex']) : '';
            $partner_dob = isset($_POST['partner_dob']) ? sanitize_text_field($_POST['partner_dob']) : '';
            
            if (!mmgr_validate_age($dob)) {
                echo '<div class="mmgr-error">You must be 18 or older to register.</div>';
            } elseif ($level === 'Couple' && $partner_dob && !mmgr_validate_age($partner_dob)) {
                echo '<div class="mmgr-error">Partner must be 18 or older.</div>';
            } else {
                $code = mmgr_generate_member_code($first_name . $last_name);
                $name = $first_name . ' ' . $last_name;
                $partner_name = trim($partner_first_name . ' ' . $partner_last_name);
                
                $data = array(
                    'member_code' => $code,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'partner_first_name' => $partner_first_name,
                    'partner_last_name' => $partner_last_name,
                    'name' => $name,
                    'partner_name' => $partner_name,
                    'email' => $email,
                    'phone' => $phone,
                    'sex' => $sex,
                    'partner_sex' => $partner_sex,
                    'age' => $dob,
                    'partner_age' => $partner_dob ?: null,
                    'level' => $level,
                    'newsletter' => $newsletter,
                    'agreed_terms' => 1,
                    'start_date' => date('Y-m-d'),
                    'expire_date' => null,  // NO EXPIRY until paid
                    'paid' => 0,            // Unpaid by default
                    'payment_date' => null,
                    'payment_method' => null,
                    'payment_amount' => null
                );
                
                $wpdb->insert($tbl, $data);
				
                
				// Generate QR code file for email attachment
				mmgr_generate_qr_file($code);
				               
                // Send welcome email
                mmgr_send_welcome_email($wpdb->insert_id);
                
				// Send welcome private message
				mmgr_send_welcome_pm($wpdb->insert_id);
				
				
                echo '<div class="mmgr-success">Registration successful! Check your email for login details.</div>';
                
                // Get redirect URL from settings, default to login page
                $redirect_url = get_option('mmgr_registration_success_url', '');
                if (empty($redirect_url)) {
                    $redirect_url = home_url('/member-login/');
                } else {
                    $redirect_url = mmgr_get_absolute_url($redirect_url);
                }
                
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "'.esc_url($redirect_url).'";
                    }, 2000);
                </script>';
            }
        }
    }
    
    ?>
    <div class="mmgr-registration-form">
        <?php if ($reg_logo_url): ?>
        <div class="mmgr-registration-logo" style="text-align:center;margin-bottom:20px;">
            <img src="<?php echo esc_url($reg_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" style="max-width:100%;max-height:150px;object-fit:contain;">
        </div>
        <?php endif; ?>
        <?php if (!empty($reg_blurb)): ?>
        <div class="mmgr-registration-blurb" style="margin-bottom:24px;">
            <?php echo wp_kses_post($reg_blurb); ?>
        </div>
        <?php endif; ?>
        <h2><?php echo esc_html($reg_title); ?></h2>
        <form method="POST">
            <?php wp_nonce_field('mmgr_register', 'reg_nonce'); ?>
            
            <div class="mmgr-field">
                <label>Membership Type *</label>
                <select name="level" id="mmgr_level" required onchange="document.getElementById('partner_fields').style.display=this.value=='Couple'?'block':'none';">
                    <?php
                    global $wpdb;
                    $levels = $wpdb->get_results("SELECT level_name FROM {$wpdb->prefix}membership_levels ORDER BY id", ARRAY_A);
                    foreach($levels as $lvl) {
                        echo '<option value="'.esc_attr($lvl['level_name']).'">'.esc_html($lvl['level_name']).'</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="mmgr-field">
                <label>First Name *</label>
                <input type="text" name="first_name" required>
            </div>
            
            <div class="mmgr-field">
                <label>Last Name *</label>
                <input type="text" name="last_name" required>
            </div>
            
            <div class="mmgr-field">
                <label>Sex *</label>
                <select name="sex" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Non-binary">Non-binary</option>
                    <option value="Undisclosed">Prefer not to say</option>
                </select>
            </div>
            
            <div class="mmgr-field">
                <label>Date of Birth *</label>
                <input type="date" name="dob" required max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
            </div>
            
            <div id="partner_fields" style="display:none;border-top:2px solid #FF2197;margin-top:20px;padding-top:20px;">
                <h3>Partner Information</h3>
                <div class="mmgr-field">
                    <label>Partner First Name</label>
                    <input type="text" name="partner_first_name">
                </div>
                <div class="mmgr-field">
                    <label>Partner Last Name</label>
                    <input type="text" name="partner_last_name">
                </div>
                <div class="mmgr-field">
                    <label>Partner Sex</label>
                    <select name="partner_sex">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Non-binary">Non-binary</option>
                        <option value="Undisclosed">Prefer not to say</option>
                    </select>
                </div>
                <div class="mmgr-field">
                    <label>Partner Date of Birth</label>
                    <input type="date" name="partner_dob" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                </div>
            </div>
            
            <div class="mmgr-field">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="mmgr-field">
                <label>Phone *</label>
                <input type="tel" name="phone" required>
            </div>
            
           <div class="mmgr-field">
    <label>
        <input type="checkbox" name="newsletter" value="1" checked>
        Subscribe to newsletter
    </label>
</div>

<?php 
$coc_content = get_option('mmgr_code_of_conduct', '');
if (!empty($coc_content)): ?>
<div class="mmgr-field">
    <label style="font-weight:bold;display:block;margin-bottom:10px;color:#FF2197;">Code of Conduct</label>
 <div style="max-height:300px;overflow-y:auto;padding:12px;border:2px solid #FF2197;border-radius:6px;margin-bottom:15px;">
    <?php
    $coc_lines   = explode("\n", $coc_content);
    $coc_html    = '';
    $coc_in_list = false;

    foreach ($coc_lines as $coc_line) {
        $coc_line = trim($coc_line);

        if (empty($coc_line)) {
            if ($coc_in_list) {
                $coc_html .= '</ul>';
                $coc_in_list = false;
            }
            continue;
        }

        if (strlen($coc_line) > 500) {
            continue;
        }

        // ## Heading ## → <h3>
        if (preg_match('/^##\s+(.+?)\s+##$/', $coc_line, $coc_matches)) {
            if ($coc_in_list) {
                $coc_html .= '</ul>';
                $coc_in_list = false;
            }
            $coc_html .= '<h3>' . esc_html($coc_matches[1]) . '</h3>';
        }
        // * bullet → <li>
        elseif (preg_match('/^\*\s+(.+)$/', $coc_line, $coc_matches)) {
            if (!$coc_in_list) {
                $coc_html .= '<ul>';
                $coc_in_list = true;
            }
            $coc_html .= '<li>' . esc_html($coc_matches[1]) . '</li>';
        }
        // Regular text → <p>
        else {
            if ($coc_in_list) {
                $coc_html .= '</ul>';
                $coc_in_list = false;
            }
            $coc_html .= '<p>' . esc_html($coc_line) . '</p>';
        }
    }

    if ($coc_in_list) {
        $coc_html .= '</ul>';
    }

    echo '<div class="mmgr-coc">' . $coc_html . '</div>';
    ?>
</div>
    <label style="display:flex;align-items:center;gap:8px;color:#fff;">
        <input type="checkbox" name="agreed_coc" value="1" required>
        <strong>I agree to follow the rules and Code of Conduct listed above *</strong>
    </label>
</div>
<?php else: ?>
<div class="mmgr-field">
    <label style="color:#fff;">
        <input type="checkbox" name="agreed_coc" value="1" required>
        <strong>I agree to follow the rules and Code of Conduct *</strong>
    </label>
</div>
<?php endif; ?>
            
            <button type="submit" name="mmgr_register" class="mmgr-submit">Register</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});

// Check-in Shortcode
add_shortcode('membership_checkin', function($atts){
    ob_start();
    
    $checkin_title = get_option('mmgr_checkin_title', 'QR Code Scanner');
    $default_mode = get_option('mmgr_checkin_default_mode', 'hw');
    
    $btn_base_style   = 'flex:1 !important;padding:10px 16px !important;border:2px solid #0073aa !important;border-radius:6px !important;background:#fff !important;color:#0073aa !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;';
    $btn_active_style = 'flex:1 !important;padding:10px 16px !important;border:2px solid #0073aa !important;border-radius:6px !important;background:#0073aa !important;color:#fff !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;';
    ?>
    <div class="mmgr-checkin-container" style="max-width:700px !important;margin:0 auto !important;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important;">
        <h2 style="font-size:1.5rem !important;font-weight:700 !important;color:#9b51e0 !important;margin-bottom:20px !important;text-align:center !important;"><?php echo esc_html($checkin_title); ?></h2>
        
        <div class="mmgr-mode-switch" style="display:flex !important;gap:10px !important;margin-bottom:20px !important;flex-wrap:wrap !important;">
            <button onclick="switchMode('hw')" id="btn-hw" class="<?php echo $default_mode === 'hw' ? 'active' : ''; ?>" style="<?php echo $default_mode === 'hw' ? esc_attr($btn_active_style) : esc_attr($btn_base_style); ?>">📱 Hardware Scanner</button>
            <button onclick="switchMode('camera')" id="btn-camera" class="<?php echo $default_mode === 'camera' ? 'active' : ''; ?>" style="<?php echo $default_mode === 'camera' ? esc_attr($btn_active_style) : esc_attr($btn_base_style); ?>">📷 Camera Scanner</button>
            <button onclick="switchMode('manual')" id="btn-manual" class="<?php echo $default_mode === 'manual' ? 'active' : ''; ?>" style="<?php echo $default_mode === 'manual' ? esc_attr($btn_active_style) : esc_attr($btn_base_style); ?>">⌨️ Manual Entry</button>
        </div>
        
        <div id="mode-hw" class="mmgr-mode" style="<?php echo $default_mode === 'hw' ? 'display:block !important;' : 'display:none !important;'; ?>">
            <input type="text" id="hw-input" class="mmgr-scan-input" placeholder="Scan QR code here..." autofocus style="width:100% !important;padding:15px !important;font-size:18px !important;border:2px solid #0073aa !important;border-radius:6px !important;box-sizing:border-box !important;">
            <p class="mmgr-scan-hint" style="color:#666 !important;margin-top:10px !important;font-size:14px !important;">Focus this field and scan a QR code with your scanner</p>
        </div>
        
        <div id="mode-camera" class="mmgr-mode" style="<?php echo $default_mode === 'camera' ? 'display:block !important;' : 'display:none !important;'; ?>">
            <div id="camera-view" class="mmgr-camera-view" style="max-width:500px !important;margin:0 auto 12px !important;"></div>
            <button onclick="startCamera()" id="start-camera-btn" class="mmgr-button" style="background:linear-gradient(135deg,#0073aa 0%,#005a87 100%) !important;color:#fff !important;padding:10px 20px !important;border:none !important;border-radius:20px !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;display:inline-block !important;text-decoration:none !important;text-align:center !important;">Start Camera</button>
        </div>
        
        <div id="mode-manual" class="mmgr-mode" style="<?php echo $default_mode === 'manual' ? 'display:block !important;' : 'display:none !important;'; ?>">
            <input type="text" id="manual-input" class="mmgr-scan-input" placeholder="Enter member code..." style="width:100% !important;padding:15px !important;font-size:18px !important;border:2px solid #0073aa !important;border-radius:6px !important;box-sizing:border-box !important;">
            <div class="mmgr-manual-actions" style="margin-top:10px !important;"><button onclick="manualCheckin()" class="mmgr-button" style="background:linear-gradient(135deg,#0073aa 0%,#005a87 100%) !important;color:#fff !important;padding:10px 20px !important;border:none !important;border-radius:20px !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;display:inline-block !important;text-decoration:none !important;text-align:center !important;">Check In</button></div>
        </div>
        
        <div id="checkin-result" class="mmgr-checkin-result" style="margin-top:30px !important;"></div>
    </div>
    
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    let html5QrCode;
    const mmgrBtnBase   = '<?php echo esc_js($btn_base_style); ?>';
    const mmgrBtnActive = '<?php echo esc_js($btn_active_style); ?>';

    function switchMode(mode) {
        document.querySelectorAll('.mmgr-mode').forEach(el => el.style.setProperty('display', 'none', 'important'));
        document.querySelectorAll('.mmgr-mode-switch button').forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('style', mmgrBtnBase);
        });
        
        document.getElementById('mode-' + mode).style.setProperty('display', 'block', 'important');
        const activeBtn = document.getElementById('btn-' + mode);
        activeBtn.classList.add('active');
        activeBtn.setAttribute('style', mmgrBtnActive);
        
        if (mode === 'hw') {
            document.getElementById('hw-input').focus();
        }
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
        }
    }
    
    // Hardware Scanner - Enter key support
    document.getElementById('hw-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            processCheckin(this.value);
            this.value = '';
        }
    });
    
    // Manual Entry - Enter key support
    document.getElementById('manual-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            processCheckin(this.value);
            this.value = '';
        }
    });
    
    function manualCheckin() {
        const code = document.getElementById('manual-input').value;
        if (code) {
            processCheckin(code);
            document.getElementById('manual-input').value = '';
        }
    }
    
    function startCamera() {
        html5QrCode = new Html5Qrcode("camera-view");
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                processCheckin(decodedText);
                html5QrCode.stop();
            }
        );
    }
    
    function processCheckin(code) {
        const formData = new FormData();
        formData.append('action', 'mmgr_checkin');
        formData.append('code', code);
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            const result = document.getElementById('checkin-result');
            
            if (data.success && data.data && data.data.member) {
                const member = data.data.member;
                const dailyFee = data.data.daily_fee || 0;
                
                // Build member info card
                let html = '<div class="mmgr-member-card" style="background:#fff !important;border:3px solid #00a32a !important;border-radius:12px !important;padding:20px !important;margin:20px 0 !important;box-shadow:0 4px 6px rgba(0,0,0,0.1) !important;">';
                
                // Photo and basic info
                html += '<div class="mmgr-member-card-header" style="display:flex !important;gap:20px !important;align-items:flex-start !important;margin-bottom:20px !important;">';
                
                // Photo
                if (member.photo_url) {
                    html += '<img src="' + member.photo_url + '" class="mmgr-member-photo" alt="Photo of ' + member.name + '" style="width:100px !important;height:100px !important;object-fit:cover !important;border-radius:50% !important;border:3px solid #00a32a !important;flex-shrink:0 !important;">';
                } else {
                    html += '<div class="mmgr-member-avatar" style="width:100px !important;height:100px !important;background:#f0f0f0 !important;border-radius:50% !important;display:flex !important;align-items:center !important;justify-content:center !important;font-size:50px !important;border:3px solid #ccc !important;flex-shrink:0 !important;">👤</div>';
                }
                
                // Member details
                html += '<div class="mmgr-member-info" style="flex:1 !important;">';
                html += '<h2 class="mmgr-member-name" style="margin:0 0 5px 0 !important;color:#00a32a !important;font-size:1.25rem !important;">✅ ' + member.name + '</h2>';
                
                if (member.partner_name) {
                    html += '<p style="margin:5px 0 !important;font-size:14px !important;">+ ' + member.partner_name + '</p>';
                }
                
                html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Level:</strong> ' + member.level + '</p>';
                html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Code:</strong> <code>' + member.member_code + '</code></p>';
                html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Phone:</strong> ' + member.phone + '</p>';
                html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Email:</strong> ' + member.email + '</p>';
                
                if (member.is_expired) {
                    html += '<p class="mmgr-expired-notice" style="margin:10px 0 !important;padding:10px !important;background:#fff3cd !important;border-left:4px solid #f0c33c !important;border-radius:4px !important;"><strong>⚠️ Membership Expired:</strong> ' + member.expire_date + '</p>';
                } else {
                    html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Expires:</strong> ' + member.expire_date + '</p>';
                }
                
                html += '<p style="margin:5px 0 !important;font-size:14px !important;"><strong>Last Visit:</strong> ' + member.last_visited + '</p>';
                html += '</div></div>';
                
				// Payment section
				html += '<div class="mmgr-payment-section" style="background:#f0f8ff !important;padding:15px !important;border-radius:6px !important;margin-top:15px !important;">';
				html += '<div class="mmgr-fee-group" style="margin-bottom:15px !important;">';
				html += '<label for="daily_fee_' + member.id + '" class="mmgr-fee-label" style="display:block !important;margin-bottom:5px !important;font-weight:bold !important;">Daily Fee:</label>';
				html += '<div class="mmgr-fee-row" style="display:flex !important;align-items:center !important;gap:10px !important;margin-bottom:8px !important;">';
				html += '<span class="mmgr-fee-amount" style="font-size:24px !important;font-weight:bold !important;">$</span>';
				html += '<input type="number" id="daily_fee_' + member.id + '" value="' + dailyFee.toFixed(2) + '" step="0.01" min="0" class="mmgr-fee-input" style="width:120px !important;padding:10px !important;font-size:18px !important;border:2px solid #0073aa !important;border-radius:6px !important;font-weight:bold !important;">';
				html += '<button onclick="applyDiscount(' + member.id + ')" class="mmgr-discount-btn" style="background:#f0c33c !important;color:#1d2327 !important;border:none !important;padding:8px 15px !important;border-radius:6px !important;font-size:14px !important;font-weight:bold !important;cursor:pointer !important;">🎟️ Apply Discount</button>';
				html += '</div>';
				html += '<p class="mmgr-fee-hint" style="margin:5px 0 0 0 !important;font-size:12px !important;color:#666 !important;">Edit amount for discounts, coupons, or special pricing</p>';
				html += '</div>';
				html += '<div class="mmgr-payment-options" style="display:flex !important;gap:10px !important;align-items:center !important;margin-bottom:10px !important;">';
				html += '<label style="display:flex !important;align-items:center !important;gap:5px !important;cursor:pointer !important;"><input type="radio" name="payment_status_' + member.id + '" value="1" checked> 💵 Paid</label>';
				html += '<label style="display:flex !important;align-items:center !important;gap:5px !important;cursor:pointer !important;"><input type="radio" name="payment_status_' + member.id + '" value="0"> ⚠️ Unpaid</label>';
				html += '</div>';
				html += '<input type="text" id="visit_notes_' + member.id + '" placeholder="Notes (optional - e.g., 50% discount coupon)" class="mmgr-notes-input" style="width:100% !important;padding:10px !important;border:1px solid #ccc !important;border-radius:4px !important;margin-bottom:10px !important;box-sizing:border-box !important;">';
				html += '<button onclick="confirmPayment(' + member.id + ')" class="mmgr-confirm-btn" style="background:#00a32a !important;color:#fff !important;border:none !important;padding:12px 24px !important;border-radius:6px !important;font-size:16px !important;font-weight:bold !important;cursor:pointer !important;width:100% !important;">✓ Confirm Check-In</button>';
				html += '</div>'; // Close payment section

				html += '</div>'; // Close member card

				result.innerHTML = html;
                
            } else {
                result.innerHTML = '<div class="mmgr-error" style="background:#f8d7da !important;border-left:4px solid #dc3232 !important;color:#721c24 !important;padding:12px 15px !important;border-radius:6px !important;margin:15px 0 !important;font-weight:600 !important;font-size:14px !important;">' + (data.data ? data.data.message : 'An error occurred') + '</div>';
            }
        })
        .catch(err => {
            const result = document.getElementById('checkin-result');
            result.innerHTML = '<div class="mmgr-error" style="background:#f8d7da !important;border-left:4px solid #dc3232 !important;color:#721c24 !important;padding:12px 15px !important;border-radius:6px !important;margin:15px 0 !important;font-weight:600 !important;font-size:14px !important;">❌ Error: ' + err.message + '</div>';
            console.error('Check-in error:', err);
        });
    }
    
			 function confirmPayment(memberId) {
				const paid = document.querySelector('input[name="payment_status_' + memberId + '"]:checked').value;
				const notes = document.getElementById('visit_notes_' + memberId).value;
				const dailyFee = parseFloat(document.getElementById('daily_fee_' + memberId).value) || 0;
				
				const formData = new FormData();
				formData.append('action', 'mmgr_confirm_payment');
				formData.append('member_id', memberId);
				formData.append('daily_fee', dailyFee);
				formData.append('paid', paid);
				formData.append('notes', notes);
				
				fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
					method: 'POST',
					body: formData
				})
				.then(r => r.json())
				.then(data => {
					const result = document.getElementById('checkin-result');
					if (data.success) {
						result.innerHTML = '<div class="mmgr-success" style="background:#d4edda !important;border-left:4px solid #00a32a !important;color:#155724 !important;padding:12px 15px !important;border-radius:6px !important;margin:15px 0 !important;font-weight:600 !important;font-size:14px !important;">' + data.data.message + '</div>';
						setTimeout(() => {
							result.innerHTML = '';
							const hwInput = document.getElementById('hw-input');
							if (hwInput) hwInput.focus();
						}, 3000);
					} else {
						result.innerHTML = '<div class="mmgr-error" style="background:#f8d7da !important;border-left:4px solid #dc3232 !important;color:#721c24 !important;padding:12px 15px !important;border-radius:6px !important;margin:15px 0 !important;font-weight:600 !important;font-size:14px !important;">' + (data.data ? data.data.message : 'Error confirming payment') + '</div>';
					}
				});
			}
			function applyDiscount(memberId) {
				const feeInput = document.getElementById('daily_fee_' + memberId);
				const currentFee = parseFloat(feeInput.value) || 0;
				
				const discountOptions = [
					{ label: '10% Off', multiplier: 0.9 },
					{ label: '25% Off', multiplier: 0.75 },
					{ label: '50% Off', multiplier: 0.5 },
					{ label: 'Free Entry', multiplier: 0 },
					{ label: 'Custom Amount', multiplier: null }
				];
				
				let message = 'Select discount:\n\n';
				discountOptions.forEach((opt, idx) => {
					message += (idx + 1) + '. ' + opt.label + '\n';
				});
				
				const choice = prompt(message + '\nEnter 1-5:');
				
				if (choice && choice >= 1 && choice <= 5) {
					const selected = discountOptions[choice - 1];
					
					if (selected.multiplier !== null) {
						const newFee = currentFee * selected.multiplier;
						feeInput.value = newFee.toFixed(2);
						
						// Auto-add to notes
						const notesField = document.getElementById('visit_notes_' + memberId);
						if (notesField) {
							notesField.value = selected.label + ' applied';
						}
					} else {
						// Custom amount
						const custom = prompt('Enter custom fee amount:', currentFee.toFixed(2));
						if (custom !== null) {
							feeInput.value = parseFloat(custom).toFixed(2);
						}
					}
				}
			}			
    </script>
    <?php
    return ob_get_clean();
});

// Code of Conduct Display Shortcode
add_shortcode('membership_code_of_conduct', function($atts){
    $content = get_option('mmgr_code_of_conduct', '');
    if (empty($content)) {
        ob_start();
        ?>
        <div class="mmgr-portal-container">
            <!-- Navigation -->
            <?php echo mmgr_get_portal_navigation('coc'); ?>
            <div class="mmgr-portal-titlecc">
                <h1>Code of Conduct 📜</h1>
            </div>
            <div class="mmgr-portal-card">
                <div class="mmgr-coc"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $lines    = explode("\n", $content);
    $html     = '';
    $in_list  = false;

    foreach ($lines as $line) {
        $line = trim($line);

        if (empty($line)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            continue;
        }

        // Skip unreasonably long lines to prevent regex backtracking issues.
        if (strlen($line) > 500) {
            continue;
        }

        // ## Heading ## → <h3>
        if (preg_match('/^##\s+(.+?)\s+##$/', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $html .= '<h3>' . esc_html($matches[1]) . '</h3>';
        }
        // * bullet → <li>
        elseif (preg_match('/^\*\s+(.+)$/', $line, $matches)) {
            if (!$in_list) {
                $html .= '<ul>';
                $in_list = true;
            }
            $html .= '<li>' . esc_html($matches[1]) . '</li>';
        }
        // Regular text → <p>
        else {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $html .= '<p>' . esc_html($line) . '</p>';
        }
    }

    if ($in_list) {
        $html .= '</ul>';
    }

    ob_start();
    ?>
    <div class="mmgr-portal-container">
        <!-- Navigation -->
        <?php echo mmgr_get_portal_navigation('coc'); ?>
        <div class="mmgr-portal-titlecc">
            <h1>Code of Conduct 📜</h1>
        </div>
        <div class="mmgr-portal-card">
            <div class="mmgr-coc"><?php echo $html; ?></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// Admin Quick Links Shortcode - Only visible to administrators
add_shortcode('mmgr_admin_quick_links', function($atts){
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return '';
    }
    
    // Get stored URLs and convert to absolute URLs
    $checkin_url = mmgr_get_absolute_url(get_option('mmgr_checkin_page_url', ''));
    $registration_url = mmgr_get_absolute_url(get_option('mmgr_registration_page_url', ''));
    $admin_url = admin_url('admin.php?page=membership_manager');
    $logs_url = admin_url('admin.php?page=membership_logs');
    
    // Get portal page URLs
    $login_url = get_permalink(get_option('mmgr_page_login'));
    $dashboard_url = get_permalink(get_option('mmgr_page_dashboard'));
    $activity_url = get_permalink(get_option('mmgr_page_activity'));
    $profile_url = get_permalink(get_option('mmgr_page_profile'));
    $community_url = get_permalink(get_option('mmgr_page_community'));
    $coc_url = get_permalink(get_option('mmgr_page_coc'));
    
    ob_start();
    ?>
    <div class="mmgr-admin-quick-links" style="background:#f0f8ff;border:2px solid #0073aa;border-radius:8px;padding:20px;margin:20px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin:0 0 15px 0;color:#0073aa;border-bottom:2px solid #0073aa;padding-bottom:10px;">
            🔐 Admin Quick Access
        </h3>
        
        <!-- Main Pages -->
        <h4 style="margin:15px 0 10px 0;color:#666;font-size:14px;text-transform:uppercase;">Main Pages</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;">
            
            <?php if ($checkin_url): ?>
            <a href="<?php echo esc_url($checkin_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#0073aa;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#005a87'"
               onmouseout="this.style.background='#0073aa'">
                📱 Check-In Page
            </a>
            <?php endif; ?>
            
            <?php if ($registration_url): ?>
            <a href="<?php echo esc_url($registration_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#00a32a;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#008a20'"
               onmouseout="this.style.background='#00a32a'">
                📝 Registration Page
            </a>
            <?php endif; ?>
            
            <?php if ($coc_url): ?>
            <a href="<?php echo esc_url($coc_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#9b51e0;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#7d3cb8'"
               onmouseout="this.style.background='#9b51e0'">
                📋 Code of Conduct
            </a>
            <?php endif; ?>
            
        </div>
        
        <!-- Member Portal Pages - BLACK & HOT PINK THEME -->
        <h4 style="margin:15px 0 10px 0;color:#000;font-size:14px;text-transform:uppercase;background:linear-gradient(90deg, #000 0%, #FF2197 100%);color:white;padding:8px 12px;border-radius:4px;">
            💎 Member Portal
        </h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;">
            
            <?php if ($login_url): ?>
            <a href="<?php echo esc_url($login_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#000;color:#FF2197;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:all 0.3s;border:2px solid #FF2197;"
               onmouseover="this.style.background='#FF2197';this.style.color='#000'"
               onmouseout="this.style.background='#000';this.style.color='#FF2197'">
                🔐 Member Login
            </a>
            <?php endif; ?>
            
            <?php if ($dashboard_url): ?>
            <a href="<?php echo esc_url($dashboard_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#FF2197;color:#000;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:all 0.3s;border:2px solid #000;"
               onmouseover="this.style.background='#000';this.style.color='#FF2197'"
               onmouseout="this.style.background='#FF2197';this.style.color='#000'">
                🏠 Member Dashboard
            </a>
            <?php endif; ?>
            
            <?php if ($activity_url): ?>
            <a href="<?php echo esc_url($activity_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#000;color:#FF2197;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:all 0.3s;border:2px solid #FF2197;"
               onmouseover="this.style.background='#FF2197';this.style.color='#000'"
               onmouseout="this.style.background='#000';this.style.color='#FF2197'">
                📊 Activity
            </a>
            <?php endif; ?>
            
            <?php if ($profile_url): ?>
            <a href="<?php echo esc_url($profile_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#FF2197;color:#000;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:all 0.3s;border:2px solid #000;"
               onmouseover="this.style.background='#000';this.style.color='#FF2197'"
               onmouseout="this.style.background='#FF2197';this.style.color='#000'">
                👤 Profile
            </a>
            <?php endif; ?>
            
            <?php if ($community_url): ?>
            <a href="<?php echo esc_url($community_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#000;color:#FF2197;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:all 0.3s;border:2px solid #FF2197;"
               onmouseover="this.style.background='#FF2197';this.style.color='#000'"
               onmouseout="this.style.background='#000';this.style.color='#FF2197'">
                💬 Community
            </a>
            <?php endif; ?>
            
        </div>
        
        <!-- Admin Pages -->
        <h4 style="margin:15px 0 10px 0;color:#666;font-size:14px;text-transform:uppercase;">Admin Dashboard</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
            
            <a href="<?php echo esc_url($admin_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#d63638;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#b32d2e'"
               onmouseout="this.style.background='#d63638'">
                👥 Manage Members
            </a>
            
            <a href="<?php echo esc_url($logs_url); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#f0c33c;color:#1d2327;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#dba617'"
               onmouseout="this.style.background='#f0c33c'">
                📊 Visit Logs
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=membership_levels'); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#8e44ad;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#6c3483'"
               onmouseout="this.style.background='#8e44ad'">
                🎯 Membership Levels
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=membership_special_fees'); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#e67e22;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#ca6f1e'"
               onmouseout="this.style.background='#e67e22'">
                🎉 Special Events
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=membership_settings'); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#16a085;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#138d75'"
               onmouseout="this.style.background='#16a085'">
                ⚙️ Settings
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=membership_pages'); ?>" 
               class="mmgr-quick-btn" 
               style="display:block;background:#34495e;color:white;text-decoration:none;padding:15px;border-radius:6px;text-align:center;font-weight:bold;transition:background 0.3s;"
               onmouseover="this.style.background='#2c3e50'"
               onmouseout="this.style.background='#34495e'">
                📄 Plugin Pages
            </a>
            
        </div>
        
        <?php if (empty($checkin_url) || empty($registration_url)): ?>
        <div style="margin-top:15px;padding:10px;background:#fff3cd;border-left:4px solid #f0c33c;border-radius:4px;">
            <p style="margin:0;color:#856404;">
                ⚠️ <strong>Setup Required:</strong> 
                <a href="<?php echo admin_url('admin.php?page=membership_settings'); ?>" style="color:#856404;text-decoration:underline;">
                    Configure page URLs in settings
                </a> to enable all quick links.
            </p>
        </div>
        <?php endif; ?>
        
        <p style="margin:10px 0 0 0;font-size:12px;color:#666;text-align:center;">
            This admin panel is only visible to administrators
        </p>
    </div>
    <?php
    return ob_get_clean();
});