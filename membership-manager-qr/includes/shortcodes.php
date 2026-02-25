<?php
if (!defined('ABSPATH')) exit;


// Registration Form Shortcode
add_shortcode('membership_registration', function($atts){
    ob_start();
    
    $reg_title = get_option('mmgr_registration_title', 'Membership Signup');
    $coc_url = get_option('mmgr_coc_url', '');
    $success_url = get_option('mmgr_registration_success_url', '');
    
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
        <h2><?php echo esc_html($reg_title); ?></h2>
        <form method="POST">
            <?php wp_nonce_field('mmgr_register', 'reg_nonce'); ?>
            
            <div class="mmgr-field">
                <label>Membership Type *</label>
                <select name="level" id="mmgr_level" required onchange="document.getElementById('partner_fields').style.display=this.value=='Couple'?'block':'none';">
                    <?php
                    global $wpdb;
                    $levels = $wpdb->get_results("SELECT level_name, price FROM {$wpdb->prefix}membership_levels ORDER BY id", ARRAY_A);
                    foreach($levels as $lvl) {
                        echo '<option value="'.esc_attr($lvl['level_name']).'">'.esc_html($lvl['level_name']).' - $'.number_format($lvl['price'], 2).'</option>';
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
            
            <div id="partner_fields" style="display:none;border-top:2px solid #ccc;margin-top:20px;padding-top:20px;">
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
    <label style="font-weight:bold;display:block;margin-bottom:10px;">Code of Conduct</label>
 <div style="max-height:300px;overflow-y:auto;padding:3px;border:2px solid #0073aa;border-radius:6px;background:#f9f9f9;margin-bottom:15px;">
    <?php 
    // Process the code of conduct content - replace <hr> with purple styled version
    $coc_processed = str_replace(
        array('<hr>', '<hr/>', '<hr />'),
        '<hr style="border:none;border-top:3px solid #9b51e0;margin:15px 0;">',
        $coc_content
    );
    
    $lines = explode("\n", $coc_processed);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check if line contains the styled <hr>
        if (strpos($line, '<hr style=') !== false) {
            echo $line;
        }
        // Lines starting with "-" are highlighted
        elseif (strpos($line, '-') === 0) {
            echo '<p style="background:#9b51e0;color:white;padding:3px;margin:5px 0;border-radius:4px;font-weight:bold;">' . esc_html(ltrim($line, '- ')) . '</p>';
        }
        // Regular text
        else {
            echo '<p style="margin:8px 0;line-height:1.6;padding:3px;">' . esc_html($line) . '</p>';
        }
    }
    ?>
</div>
    <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="agreed_coc" value="1" required>
        <strong>I agree to follow the rules and Code of Conduct listed above *</strong>
    </label>
</div>
<?php else: ?>
<div class="mmgr-field">
    <label>
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
    
    ?>
    <div class="mmgr-checkin-container">
        <h2><?php echo esc_html($checkin_title); ?></h2>
        
        <div class="mmgr-mode-switch">
            <button onclick="switchMode('hw')" id="btn-hw" class="<?php echo $default_mode === 'hw' ? 'active' : ''; ?>">📱 Hardware Scanner</button>
            <button onclick="switchMode('camera')" id="btn-camera" class="<?php echo $default_mode === 'camera' ? 'active' : ''; ?>">📷 Camera Scanner</button>
            <button onclick="switchMode('manual')" id="btn-manual" class="<?php echo $default_mode === 'manual' ? 'active' : ''; ?>">⌨️ Manual Entry</button>
        </div>
        
        <div id="mode-hw" class="mmgr-mode" style="display:<?php echo $default_mode === 'hw' ? 'block' : 'none'; ?>;">
            <input type="text" id="hw-input" placeholder="Scan QR code here..." autofocus style="width:100%;padding:15px;font-size:18px;border:2px solid #0073aa;border-radius:6px;">
            <p style="color:#666;margin-top:10px;">Focus this field and scan a QR code with your scanner</p>
        </div>
        
        <div id="mode-camera" class="mmgr-mode" style="display:<?php echo $default_mode === 'camera' ? 'block' : 'none'; ?>;">
            <div id="camera-view" style="max-width:500px;margin:0 auto;"></div>
            <button onclick="startCamera()" id="start-camera-btn" class="mmgr-button">Start Camera</button>
        </div>
        
        <div id="mode-manual" class="mmgr-mode" style="display:<?php echo $default_mode === 'manual' ? 'block' : 'none'; ?>;">
            <input type="text" id="manual-input" placeholder="Enter member code..." style="width:100%;padding:15px;font-size:18px;border:2px solid #0073aa;border-radius:6px;">
            <button onclick="manualCheckin()" class="mmgr-button" style="margin-top:10px;">Check In</button>
        </div>
        
        <div id="checkin-result" style="margin-top:30px;"></div>
    </div>
    
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    let html5QrCode;
    
    function switchMode(mode) {
        document.querySelectorAll('.mmgr-mode').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.mmgr-mode-switch button').forEach(btn => btn.classList.remove('active'));
        
        document.getElementById('mode-' + mode).style.display = 'block';
        document.getElementById('btn-' + mode).classList.add('active');
        
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
                let html = '<div class="mmgr-member-card" style="background:white;border:3px solid #00a32a;border-radius:12px;padding:20px;margin:20px 0;box-shadow:0 4px 6px rgba(0,0,0,0.1);">';
                
                // Photo and basic info
                html += '<div style="display:flex;gap:20px;align-items:start;margin-bottom:20px;">';
                
                // Photo
                if (member.photo_url) {
                    html += '<img src="' + member.photo_url + '" style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #00a32a;">';
                } else {
                    html += '<div style="width:100px;height:100px;background:#f0f0f0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:50px;border:3px solid #ccc;">👤</div>';
                }
                
                // Member details
                html += '<div style="flex:1;">';
                html += '<h2 style="margin:0 0 5px 0;color:#00a32a;">✅ ' + member.name + '</h2>';
                
                if (member.partner_name) {
                    html += '<p style="margin:5px 0;color:#666;">+ ' + member.partner_name + '</p>';
                }
                
                html += '<p style="margin:5px 0;"><strong>Level:</strong> ' + member.level + '</p>';
                html += '<p style="margin:5px 0;"><strong>Code:</strong> <code>' + member.member_code + '</code></p>';
                html += '<p style="margin:5px 0;"><strong>Phone:</strong> ' + member.phone + '</p>';
                html += '<p style="margin:5px 0;"><strong>Email:</strong> ' + member.email + '</p>';
                
                if (member.is_expired) {
                    html += '<p style="margin:10px 0;padding:10px;background:#fff3cd;border-left:4px solid #f0c33c;border-radius:4px;"><strong>⚠️ Membership Expired:</strong> ' + member.expire_date + '</p>';
                } else {
                    html += '<p style="margin:5px 0;"><strong>Expires:</strong> ' + member.expire_date + '</p>';
                }
                
                html += '<p style="margin:5px 0;"><strong>Last Visit:</strong> ' + member.last_visited + '</p>';
                html += '</div></div>';
                
				// Payment section
				html += '<div style="background:#f0f8ff;padding:15px;border-radius:6px;margin-top:15px;">';
				html += '<div style="margin-bottom:15px;">';
				html += '<label for="daily_fee_' + member.id + '" style="display:block;margin-bottom:5px;font-weight:bold;">Daily Fee:</label>';
				html += '<div style="display:flex;align-items:center;gap:10px;">';
				html += '<span style="font-size:24px;font-weight:bold;">$</span>';
				html += '<input type="number" id="daily_fee_' + member.id + '" value="' + dailyFee.toFixed(2) + '" step="0.01" min="0" style="width:120px;padding:10px;font-size:18px;border:2px solid #0073aa;border-radius:6px;font-weight:bold;">';
				html += '<button onclick="applyDiscount(' + member.id + ')" style="background:#f0c33c;color:#1d2327;border:none;padding:8px 15px;border-radius:6px;font-size:14px;font-weight:bold;cursor:pointer;">🎟️ Apply Discount</button>';
				html += '</div>';
				html += '<p style="margin:5px 0 0 0;font-size:12px;color:#666;">Edit amount for discounts, coupons, or special pricing</p>';
				html += '</div>';
				html += '<div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;">';
				html += '<label style="display:flex;align-items:center;gap:5px;"><input type="radio" name="payment_status_' + member.id + '" value="1" checked> 💵 Paid</label>';
				html += '<label style="display:flex;align-items:center;gap:5px;"><input type="radio" name="payment_status_' + member.id + '" value="0"> ⚠️ Unpaid</label>';
				html += '</div>';
				html += '<input type="text" id="visit_notes_' + member.id + '" placeholder="Notes (optional - e.g., 50% discount coupon)" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;margin-bottom:10px;">';
				html += '<button onclick="confirmPayment(' + member.id + ')" style="background:#00a32a;color:white;border:none;padding:12px 24px;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;width:100%;">✓ Confirm Check-In</button>';
				html += '</div>'; // Close payment section

				html += '</div>'; // Close member card

				result.innerHTML = html;
                
            } else {
                result.innerHTML = '<div class="mmgr-error">' + (data.data ? data.data.message : 'An error occurred') + '</div>';
            }
        })
        .catch(err => {
            const result = document.getElementById('checkin-result');
            result.innerHTML = '<div class="mmgr-error">❌ Error: ' + err.message + '</div>';
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
						result.innerHTML = '<div class="mmgr-success" style="background:#d4edda;color:#155724;padding:20px;border-radius:6px;border-left:4px solid #00a32a;font-size:18px;">' + data.data.message + '</div>';
						setTimeout(() => {
							result.innerHTML = '';
							const hwInput = document.getElementById('hw-input');
							if (hwInput) hwInput.focus();
						}, 3000);
					} else {
						result.innerHTML = '<div class="mmgr-error" style="background:#f8d7da;color:#721c24;padding:15px;border-radius:6px;border-left:4px solid #d63638;">' + (data.data ? data.data.message : 'Error confirming payment') + '</div>';
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
    $content = get_option('mmgr_coc_content', '');
    return '<div class="mmgr-coc">' . wp_kses_post($content) . '</div>';
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