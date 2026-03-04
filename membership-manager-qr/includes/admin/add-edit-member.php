<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$tbl = $wpdb->prefix . 'memberships';

// Check if editing
$editing = isset($_GET['id']);
$member = null;

if ($editing) {
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id = %d", intval($_GET['id'])), ARRAY_A);
    if (!$member) {
        echo '<div class="wrap"><h1>Member not found</h1></div>';
        return;
    }
}

// Handle QR code regeneration
if ($editing && isset($_POST['mmgr_regenerate_qr']) && isset($_POST['member_nonce_qr']) && wp_verify_nonce($_POST['member_nonce_qr'], 'mmgr_qr_actions')) {
    if ($member && !empty($member['member_code'])) {
        $result = mmgr_regenerate_qr_code($member['member_code']);
        if ($result) {
            echo '<div class="notice notice-success"><p>✓ QR code regenerated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>✕ Failed to regenerate QR code.</p></div>';
        }
    }
}

// Handle assigning a custom member code
if ($editing && isset($_POST['mmgr_assign_code']) && isset($_POST['member_nonce_qr']) && wp_verify_nonce($_POST['member_nonce_qr'], 'mmgr_qr_actions')) {
    $new_code = strtoupper(sanitize_text_field($_POST['custom_member_code'] ?? ''));
    if ($new_code && preg_match('/^[A-Z0-9]{4,20}$/', $new_code)) {
        // Check code is not already in use by another member
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tbl WHERE member_code = %s AND id != %d", $new_code, intval($_GET['id'])));
        if ($existing) {
            echo '<div class="notice notice-error"><p>✕ That member code is already in use by another member.</p></div>';
        } else {
            $wpdb->update($tbl, array('member_code' => $new_code), array('id' => intval($_GET['id'])));
            mmgr_generate_qr_file($new_code);
            $member['member_code'] = $new_code;
            echo '<div class="notice notice-success"><p>✓ Member code assigned and QR code generated.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>✕ Invalid code. Use 4-20 uppercase letters and numbers only.</p></div>';
    }
}

// Handle form submission
if (isset($_POST['mmgr_save_member'])) {
    if (!isset($_POST['member_nonce']) || !wp_verify_nonce($_POST['member_nonce'], 'mmgr_save_member')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
    } else {
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'name' => sanitize_text_field($_POST['first_name'] . ' ' . $_POST['last_name']),
            'partner_first_name' => sanitize_text_field($_POST['partner_first_name']),
            'partner_last_name' => sanitize_text_field($_POST['partner_last_name']),
            'partner_name' => trim(sanitize_text_field($_POST['partner_first_name'] . ' ' . $_POST['partner_last_name'])),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'sex' => sanitize_text_field($_POST['sex']),
            'partner_sex' => sanitize_text_field($_POST['partner_sex']),
            'age' => sanitize_text_field($_POST['age']),
            'partner_age' => sanitize_text_field($_POST['partner_age']) ?: null,
            'level' => sanitize_text_field($_POST['level']),
            'newsletter' => isset($_POST['newsletter']) ? 1 : 0,
            'start_date' => sanitize_text_field($_POST['start_date']),
            'expire_date' => sanitize_text_field($_POST['expire_date']) ?: null,
            'paid' => isset($_POST['paid']) ? 1 : 0,
            'payment_amount' => floatval($_POST['amount_paid'] ?? 0),
        );
        // Remove null values so MySQL uses column defaults
        $data = array_filter($data, function($v) { return $v !== null; });
        
        if ($editing) {
            $wpdb->update($tbl, $data, array('id' => intval($_GET['id'])));
            
            // Handle password management
            if (isset($_POST['member_password']) && !empty($_POST['member_password'])) {
                $password = $_POST['member_password'];
                if (strlen($password) >= 8) {
                    mmgr_set_member_password(intval($_GET['id']), $password);
                    echo '<div class="notice notice-success"><p>✓ Password updated successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>✕ Password must be at least 8 characters.</p></div>';
                }
            }
            
            echo '<div class="notice notice-success"><p>Member updated successfully! <a href="' . admin_url('admin.php?page=membership_manager') . '">Back to list</a></p></div>';
            $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id = %d", intval($_GET['id'])), ARRAY_A);
        } else {
            // Generate member code
            $data['member_code'] = mmgr_generate_member_code($data['name']);
            $wpdb->insert($tbl, $data);
            $new_id = $wpdb->insert_id;
            
            // Generate QR code file
            mmgr_generate_qr_file($data['member_code']);
            
            // Set password if provided
            if (isset($_POST['member_password']) && !empty($_POST['member_password'])) {
                $password = $_POST['member_password'];
                if (strlen($password) >= 8) {
                    mmgr_set_member_password($new_id, $password);
                }
            }
            
            // Send welcome email
            mmgr_send_welcome_email($new_id);
            
            // Send welcome PM
            mmgr_send_welcome_pm($new_id);
            
            echo '<div class="notice notice-success"><p>Member added successfully! <a href="' . admin_url('admin.php?page=membership_edit&id=' . $new_id) . '">Edit member</a> | <a href="' . admin_url('admin.php?page=membership_manager') . '">Back to list</a></p></div>';
        }
    }
}

// Get membership levels
$levels = $wpdb->get_results("SELECT level_name, price FROM {$wpdb->prefix}membership_levels ORDER BY level_name", ARRAY_A);

?>
<div class="wrap">
    <h1><?php echo $editing ? 'Edit Member' : 'Add New Member'; ?></h1>
    
    <form method="post" style="max-width:800px;">
        <?php wp_nonce_field('mmgr_save_member', 'member_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><h2 style="margin:0;">Primary Member</h2></th>
            </tr>
            <tr>
                <th><label for="first_name">First Name *</label></th>
                <td><input type="text" name="first_name" id="first_name" class="regular-text" value="<?php echo esc_attr($member['first_name'] ?? ''); ?>" required></td>
            </tr>
            <tr>
                <th><label for="last_name">Last Name *</label></th>
                <td><input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr($member['last_name'] ?? ''); ?>" required></td>
            </tr>
            <tr>
                <th><label for="sex">Sex</label></th>
                <td>
                    <select name="sex" id="sex">
                        <option value="">Select</option>
                        <option value="Male" <?php selected($member['sex'] ?? '', 'Male'); ?>>Male</option>
                        <option value="Female" <?php selected($member['sex'] ?? '', 'Female'); ?>>Female</option>
                        <option value="Non-binary" <?php selected($member['sex'] ?? '', 'Non-binary'); ?>>Non-binary</option>
                        <option value="Undisclosed" <?php selected($member['sex'] ?? '', 'Undisclosed'); ?>>Prefer not to say</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="age">Date of Birth *</label></th>
                <td><input type="date" name="age" id="age" value="<?php echo esc_attr($member['age'] ?? ''); ?>" required></td>
            </tr>
            
            <tr>
                <th colspan="2"><h2 style="margin:20px 0 0 0;">Partner (Optional)</h2></th>
            </tr>
            <tr>
                <th><label for="partner_first_name">Partner First Name</label></th>
                <td><input type="text" name="partner_first_name" id="partner_first_name" class="regular-text" value="<?php echo esc_attr($member['partner_first_name'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="partner_last_name">Partner Last Name</label></th>
                <td><input type="text" name="partner_last_name" id="partner_last_name" class="regular-text" value="<?php echo esc_attr($member['partner_last_name'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="partner_sex">Partner Sex</label></th>
                <td>
                    <select name="partner_sex" id="partner_sex">
                        <option value="">Select</option>
                        <option value="Male" <?php selected($member['partner_sex'] ?? '', 'Male'); ?>>Male</option>
                        <option value="Female" <?php selected($member['partner_sex'] ?? '', 'Female'); ?>>Female</option>
                        <option value="Non-binary" <?php selected($member['partner_sex'] ?? '', 'Non-binary'); ?>>Non-binary</option>
                        <option value="Undisclosed" <?php selected($member['partner_sex'] ?? '', 'Undisclosed'); ?>>Prefer not to say</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="partner_age">Partner Date of Birth</label></th>
                <td><input type="date" name="partner_age" id="partner_age" value="<?php echo esc_attr($member['partner_age'] ?? ''); ?>"></td>
            </tr>
            
            <tr>
                <th colspan="2"><h2 style="margin:20px 0 0 0;">Contact Information</h2></th>
            </tr>
            <tr>
                <th><label for="email">Email *</label></th>
                <td><input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr($member['email'] ?? ''); ?>" required></td>
            </tr>
            <tr>
                <th><label for="phone">Phone *</label></th>
                <td><input type="tel" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($member['phone'] ?? ''); ?>" required></td>
            </tr>
            
            <tr>
                <th colspan="2"><h2 style="margin:20px 0 0 0;">Membership Details</h2></th>
            </tr>
            <tr>
                <th><label for="level">Membership Level *</label></th>
                <td>
                    <select name="level" id="level" required>
                        <option value="">Select Level</option>
                        <?php foreach ($levels as $lvl): ?>
                            <option value="<?php echo esc_attr($lvl['level_name']); ?>" <?php selected($member['level'] ?? '', $lvl['level_name']); ?>>
                                <?php echo esc_html($lvl['level_name']); ?> - $<?php echo number_format($lvl['price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="start_date">Start Date *</label></th>
                <td><input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($member['start_date'] ?? date('Y-m-d')); ?>" required></td>
            </tr>
            <tr>
                <th><label for="expire_date">Expiration Date *</label></th>
                <td><input type="date" name="expire_date" id="expire_date" value="<?php echo esc_attr($member['expire_date'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" required></td>
            </tr>
            
            <tr>
                <th colspan="2"><h2 style="margin:20px 0 0 0;">Payment</h2></th>
            </tr>
            <tr>
                <th><label for="paid">Payment Status</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="paid" id="paid" value="1" <?php checked($member['paid'] ?? 0, 1); ?>>
                        Mark as paid
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="amount_paid">Amount Paid</label></th>
                <td><input type="number" name="amount_paid" id="amount_paid" step="0.01" value="<?php echo esc_attr($member['payment_amount'] ?? '0.00'); ?>"></td>
            </tr>
            
            <tr>
                <th><label for="newsletter">Newsletter</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="newsletter" id="newsletter" value="1" <?php checked($member['newsletter'] ?? 0, 1); ?>>
                        Subscribe to newsletter
                    </label>
                </td>
            </tr>
            
            <!-- PASSWORD MANAGEMENT SECTION -->
            <tr>
                <th colspan="2"><h2 style="margin:20px 0 0 0;">🔐 Password Management</h2></th>
            </tr>
            <tr>
                <th><label>Password Status</label></th>
                <td>
                    <?php
                    if ($editing) {
                        $has_pwd = !empty($member['password_hash']);
                        
                        if ($has_pwd) {
                            echo '<span style="background:#00a32a;color:white;padding:8px 12px;border-radius:4px;font-weight:bold;">✓ Password Set</span>';
                            echo '<p class="description" style="margin-top:10px;">Member has a password and can log in to the portal.</p>';
                        } else {
                            echo '<span style="background:#f0c33c;color:#1d2327;padding:8px 12px;border-radius:4px;font-weight:bold;">⚠️ No Password</span>';
                            echo '<p class="description" style="margin-top:10px;">Member needs a password to access the member portal.</p>';
                        }
                    } else {
                        echo '<p class="description">Password can be set after creating the member, or member can set it themselves via email link.</p>';
                    }
                    ?>
                </td>
            </tr>
            
            <?php if ($editing): ?>
            <tr>
                <th><label for="member_password">Set/Reset Password</label></th>
                <td>
                    <input type="password" name="member_password" id="member_password" class="regular-text" placeholder="Leave blank to keep current password">
                    <p class="description">
                        <strong>Minimum 8 characters.</strong> Leave blank to keep current password.<br>
                        <label style="margin-top:8px;display:inline-block;">
                            <input type="checkbox" id="show_password_toggle" onclick="togglePasswordVisibility()">
                            Show password
                        </label>
                    </p>
                    <div style="margin-top:15px;">
                        <label style="display:block;margin-bottom:5px;">
                            <input type="checkbox" id="generate_random_password" value="1">
                            <strong>Generate a secure random password</strong>
                        </label>
                        <div id="random_password_display" style="display:none;background:#fff3cd;border-left:4px solid #ff9800;padding:12px;border-radius:4px;margin-top:10px;">
                            <p style="margin:0 0 5px 0;"><strong>Generated Password:</strong></p>
                            <p style="margin:0;"><code id="generated_pwd" style="font-size:16px;font-weight:bold;background:white;padding:8px;border-radius:4px;display:inline-block;"></code> 
                            <button type="button" class="button button-small" onclick="mmgrCopyToClipboard()">📋 Copy</button></p>
                            <p style="margin:8px 0 0 0;font-size:12px;color:#856404;">Share this password securely with the member. It will be set when you save.</p>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th><label>Or Send Setup Link</label></th>
                <td>
                    <button type="button" class="button button-secondary" onclick="resendSetupLink(<?php echo $member['id']; ?>)">
                        📧 Email Password Setup Link to Member
                    </button>
                    <p class="description">
                        Sends a secure link to <strong><?php echo esc_html($member['email']); ?></strong> allowing them to set their own password.<br>
                        Link expires in 7 days.
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <p class="submit">
            <button type="submit" name="mmgr_save_member" class="button button-primary button-large">
                <?php echo $editing ? 'Update Member' : 'Add Member'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=membership_manager'); ?>" class="button button-large">Cancel</a>
        </p>
    </form>
    
    <?php if ($editing && $member): ?>
        <hr>
        <h2>Member Code & QR Code</h2>
        <p><strong>Member Code:</strong> <code style="font-size:18px;color:#d00;"><?php echo esc_html($member['member_code']); ?></code></p>
        <?php
        $qr_ajax_url = admin_url('admin-ajax.php?action=mmgr_qrcode&code=' . urlencode($member['member_code']));
        ?>
        <p><img src="<?php echo esc_url($qr_ajax_url); ?>" style="width:200px;height:200px;border:2px solid #0073aa;" alt="QR Code"></p>
        <p>
            <a href="<?php echo esc_url($qr_ajax_url); ?>" class="button" download="qr-<?php echo esc_attr($member['member_code']); ?>.png">💾 Download QR Code</a>
        </p>
        
        <h3>QR Code Actions</h3>
        <form method="post" style="display:inline-block;margin-right:10px;">
            <?php wp_nonce_field('mmgr_qr_actions', 'member_nonce_qr'); ?>
            <button type="submit" name="mmgr_regenerate_qr" class="button" onclick="return confirm('Regenerate QR code for this member?')">🔄 Regenerate QR Code</button>
        </form>
        
        <details style="margin-top:15px;border:1px solid #ccc;padding:15px;border-radius:4px;max-width:500px;">
            <summary style="cursor:pointer;font-weight:bold;">📋 Assign QR Code (use a pre-printed card)</summary>
            <div style="margin-top:10px;">
                <p class="description">Enter the code from a pre-printed QR card to assign it to this member.</p>
                <form method="post">
                    <?php wp_nonce_field('mmgr_qr_actions', 'member_nonce_qr'); ?>
                    <input type="text" name="custom_member_code" placeholder="e.g. ABC123XYZ" style="text-transform:uppercase;width:200px;" maxlength="20">
                    <button type="submit" name="mmgr_assign_code" class="button button-primary" onclick="return confirm('Assign this code to the member? The current QR code will be replaced.')">✓ Assign Code</button>
                </form>
            </div>
        </details>
    <?php endif; ?>
</div>

<script>
function togglePasswordVisibility() {
    const pwdField = document.getElementById('member_password');
    const checkbox = document.getElementById('show_password_toggle');
    pwdField.type = checkbox.checked ? 'text' : 'password';
}

document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('generate_random_password');
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                const password = mmgrGenerateRandomPassword();
                document.getElementById('generated_pwd').textContent = password;
                document.getElementById('member_password').value = password;
                document.getElementById('member_password').type = 'text';
                document.getElementById('random_password_display').style.display = 'block';
                document.getElementById('member_password').style.background = '#fffacd';
                document.getElementById('show_password_toggle').checked = true;
            } else {
                document.getElementById('member_password').value = '';
                document.getElementById('member_password').type = 'password';
                document.getElementById('random_password_display').style.display = 'none';
                document.getElementById('member_password').style.background = '';
                document.getElementById('show_password_toggle').checked = false;
            }
        });
    }
});

function mmgrGenerateRandomPassword() {
    const length = 12;
    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

function mmgrCopyToClipboard() {
    const text = document.getElementById('generated_pwd').textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('✓ Password copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy. Please select and copy manually.');
    });
}

function resendSetupLink(memberId) {
    if (!confirm('Send password setup email to this member?')) return;
    
    const button = event.target;
    button.disabled = true;
    button.textContent = 'Sending...';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mmgr_resend_setup_link&member_id=' + memberId + '&nonce=<?php echo wp_create_nonce('mmgr_resend_setup'); ?>'
    })
    .then(r => r.json())
    .then(d => {
        button.disabled = false;
        button.textContent = '📧 Email Password Setup Link to Member';
        
        if (d.success) {
            alert('✓ ' + d.data.message);
        } else {
            alert('✕ ' + d.data.message);
        }
    })
    .catch(err => {
        button.disabled = false;
        button.textContent = '📧 Email Password Setup Link to Member';
        alert('Error sending email. Please try again.');
    });
}
</script>