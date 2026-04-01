<?php
if (!defined('ABSPATH')) exit;

/**
 * Staff Check-In Shortcode [mmgr_staff_checkin]
 *
 * QR scanner page for staff to:
 *  - Clock In
 *  - Clock Out
 *  - Log room cleaning
 */
add_shortcode('mmgr_staff_checkin', function() {
    ob_start();

    $btn_base   = 'flex:1 !important;padding:10px 16px !important;border:2px solid #0073aa !important;border-radius:6px !important;background:#fff !important;color:#0073aa !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;';
    $btn_active = 'flex:1 !important;padding:10px 16px !important;border:2px solid #0073aa !important;border-radius:6px !important;background:#0073aa !important;color:#fff !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;';
    ?>
    <div class="mmgr-staff-container" style="max-width:700px !important;margin:0 auto !important;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important;">
        <h2 style="font-size:1.5rem !important;font-weight:700 !important;color:#9b51e0 !important;margin-bottom:20px !important;text-align:center !important;">Staff Check-In</h2>

        <!-- Scanner mode switcher -->
        <div class="mmgr-staff-mode-switch" style="display:flex !important;gap:10px !important;margin-bottom:20px !important;flex-wrap:wrap !important;">
            <button onclick="staffSwitchMode('hw')" id="staff-btn-hw" style="<?php echo esc_attr($btn_base); ?>">📱 Hardware Scanner</button>
            <button onclick="staffSwitchMode('camera')" id="staff-btn-camera" style="<?php echo esc_attr($btn_active); ?>">📷 Camera Scanner</button>
            <button onclick="staffSwitchMode('manual')" id="staff-btn-manual" style="<?php echo esc_attr($btn_base); ?>">⌨️ Manual Entry</button>
        </div>

        <!-- Hardware scanner input -->
        <div id="staff-mode-hw" class="mmgr-staff-mode" style="display:none !important;">
            <input type="text" id="staff-hw-input" placeholder="Scan staff QR code here..."
                   style="width:100% !important;padding:15px !important;font-size:18px !important;border:2px solid #0073aa !important;border-radius:6px !important;box-sizing:border-box !important;">
            <p style="color:#666 !important;margin-top:10px !important;font-size:14px !important;">Focus this field and scan a QR code with your scanner</p>
        </div>

        <!-- Camera scanner -->
        <div id="staff-mode-camera" class="mmgr-staff-mode" style="display:block !important;">
            <div id="staff-camera-view" style="max-width:500px !important;margin:0 auto 12px !important;"></div>
            <button onclick="staffStartCamera()" id="staff-start-camera-btn"
                    style="background:linear-gradient(135deg,#0073aa 0%,#005a87 100%) !important;color:#fff !important;padding:10px 20px !important;border:none !important;border-radius:20px !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;">Start Camera</button>
        </div>

        <!-- Manual entry -->
        <div id="staff-mode-manual" class="mmgr-staff-mode" style="display:none !important;">
            <input type="text" id="staff-manual-input" placeholder="Enter staff code..."
                   style="width:100% !important;padding:15px !important;font-size:18px !important;border:2px solid #0073aa !important;border-radius:6px !important;box-sizing:border-box !important;">
            <div style="margin-top:10px !important;">
                <button onclick="staffManualScan()"
                        style="background:linear-gradient(135deg,#0073aa 0%,#005a87 100%) !important;color:#fff !important;padding:10px 20px !important;border:none !important;border-radius:20px !important;font-size:14px !important;font-weight:600 !important;cursor:pointer !important;">Look Up</button>
            </div>
        </div>

        <!-- Result area -->
        <div id="staff-scan-result" style="margin-top:30px !important;"></div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    (function() {
        const mmgrStaffAjax = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        const btnBase   = '<?php echo esc_js($btn_base); ?>';
        const btnActive = '<?php echo esc_js($btn_active); ?>';
        let staffQrCode;

        // ── Mode switcher ───────────────────────────────────────────────────
        window.staffSwitchMode = function(mode) {
            document.querySelectorAll('.mmgr-staff-mode').forEach(el => el.style.setProperty('display','none','important'));
            document.querySelectorAll('.mmgr-staff-mode-switch button').forEach(btn => btn.setAttribute('style', btnBase));
            document.getElementById('staff-mode-' + mode).style.setProperty('display','block','important');
            document.getElementById('staff-btn-' + mode).setAttribute('style', btnActive);
            if (mode === 'hw') document.getElementById('staff-hw-input').focus();
            if (staffQrCode && staffQrCode.isScanning) staffQrCode.stop();
        };

        // ── Hardware / manual entry ─────────────────────────────────────────
        document.getElementById('staff-hw-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { staffProcessScan(this.value.trim()); this.value = ''; }
        });
        document.getElementById('staff-manual-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { staffProcessScan(this.value.trim()); this.value = ''; }
        });
        window.staffManualScan = function() {
            const v = document.getElementById('staff-manual-input').value.trim();
            if (v) { staffProcessScan(v); document.getElementById('staff-manual-input').value = ''; }
        };

        // ── Camera ──────────────────────────────────────────────────────────
        window.staffStartCamera = function() {
            staffQrCode = new Html5Qrcode('staff-camera-view');
            staffQrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 250 },
                function(text) { staffProcessScan(text); staffQrCode.stop(); }
            );
        };

        // ── Process scan: fetch staff info ──────────────────────────────────
        function staffProcessScan(code) {
            const result = document.getElementById('staff-scan-result');
            result.innerHTML = '<p style="text-align:center;color:#666;">Looking up staff…</p>';
            const fd = new FormData();
            fd.append('action', 'mmgr_staff_scan');
            fd.append('code', code);
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('mmgr_staff_scan')); ?>');
            fetch(mmgrStaffAjax, { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderStaffCard(data.data, code);
                    } else {
                        result.innerHTML = '<div style="background:#fff3cd;border-left:4px solid #f0ad4e;padding:16px;border-radius:6px;margin-top:10px;">' +
                            '<strong>⚠️ ' + mmgrEsc(data.data && data.data.message ? data.data.message : 'Unknown error') + '</strong></div>';
                    }
                })
                .catch(() => { result.innerHTML = '<p style="color:red;">Connection error. Please try again.</p>'; });
        }

        // ── Render action card ───────────────────────────────────────────────
        function renderStaffCard(data, code) {
            const staff = data.staff;
            const isClockedIn = data.is_clocked_in;
            const hoursText   = data.hours_this_period !== undefined ? data.hours_this_period : '';

            let html = '<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
            html += '<div style="text-align:center;margin-bottom:20px;">';
            html += '<div style="font-size:48px;margin-bottom:8px;">👤</div>';
            html += '<h3 style="margin:0;font-size:1.4rem;color:#333;">' + mmgrEsc(staff.name) + '</h3>';
            if (staff.position) {
                html += '<p style="margin:4px 0 0;color:#666;font-size:14px;">' + mmgrEsc(staff.position) + '</p>';
            }
            if (hoursText !== '') {
                html += '<p style="margin:8px 0 0;color:#0073aa;font-size:13px;font-weight:600;">Hours this pay period: <strong>' + mmgrEsc(String(hoursText)) + '</strong></p>';
            }
            if (isClockedIn) {
                html += '<p style="margin:6px 0 0;background:#d4edda;color:#155724;padding:4px 12px;border-radius:20px;display:inline-block;font-size:13px;">🟢 Currently Clocked In</p>';
            } else {
                html += '<p style="margin:6px 0 0;background:#f8d7da;color:#721c24;padding:4px 12px;border-radius:20px;display:inline-block;font-size:13px;">🔴 Not Clocked In</p>';
            }
            html += '</div>';

            // Action buttons
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">';

            // Clock In
            html += '<button onclick="staffClockIn(' + staff.id + ')" ' +
                (isClockedIn ? 'disabled style="opacity:0.4;cursor:not-allowed;' : 'style="') +
                'background:linear-gradient(135deg,#28a745,#1e7e34);color:#fff;padding:16px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;width:100%;">' +
                '⏱️ CLOCK IN</button>';

            // Clock Out
            html += '<button onclick="staffClockOut(' + staff.id + ')" ' +
                (!isClockedIn ? 'disabled style="opacity:0.4;cursor:not-allowed;' : 'style="') +
                'background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;padding:16px;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;width:100%;">' +
                '🛑 CLOCK OUT</button>';

            html += '</div>';

            // Cleaning Log button
            html += '<button onclick="staffShowCleaningLog(' + staff.id + ')" ' +
                'style="background:linear-gradient(135deg,#6f42c1,#5a32a3);color:#fff;padding:14px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;width:100%;margin-bottom:12px;">🧹 CLEANING LOG</button>';

            // Cleaning room list (hidden initially)
            html += '<div id="staff-cleaning-rooms-' + staff.id + '" style="display:none;margin-top:10px;"></div>';

            // Dismiss
            html += '<div style="text-align:center;margin-top:12px;">';
            html += '<button onclick="document.getElementById(\'staff-scan-result\').innerHTML=\'\'" ' +
                'style="background:#f0f0f0;border:1px solid #ccc;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;">✖ Dismiss</button>';
            html += '</div>';
            html += '</div>';

            document.getElementById('staff-scan-result').innerHTML = html;
        }

        // ── Clock In ─────────────────────────────────────────────────────────
        window.staffClockIn = function(staffId) {
            const fd = new FormData();
            fd.append('action', 'mmgr_staff_clock_in');
            fd.append('staff_id', staffId);
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('mmgr_staff_clock_in')); ?>');
            fetch(mmgrStaffAjax, { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        returnToScan('✅ ' + mmgrEsc(data.data.message), 'success');
                    } else {
                        showStaffMessage('⚠️ ' + mmgrEsc(data.data ? data.data.message : 'Error'), 'warning');
                    }
                });
        };

        // ── Clock Out ────────────────────────────────────────────────────────
        window.staffClockOut = function(staffId) {
            const fd = new FormData();
            fd.append('action', 'mmgr_staff_clock_out');
            fd.append('staff_id', staffId);
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('mmgr_staff_clock_out')); ?>');
            fetch(mmgrStaffAjax, { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        returnToScan('✅ ' + mmgrEsc(data.data.message), 'success');
                    } else {
                        showStaffMessage('⚠️ ' + mmgrEsc(data.data ? data.data.message : 'Error'), 'warning');
                    }
                });
        };

        // ── Cleaning Log ─────────────────────────────────────────────────────
        window.staffShowCleaningLog = function(staffId) {
            const container = document.getElementById('staff-cleaning-rooms-' + staffId);
            if (container.style.display !== 'none') {
                container.style.display = 'none';
                return;
            }
            container.innerHTML = '<p style="text-align:center;color:#666;font-size:13px;">Loading rooms…</p>';
            container.style.display = 'block';

            const fd = new FormData();
            fd.append('action', 'mmgr_staff_get_rooms');
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('mmgr_staff_get_rooms')); ?>');
            fetch(mmgrStaffAjax, { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.rooms && data.data.rooms.length > 0) {
                        let html = '<p style="font-weight:700;margin:0 0 10px;font-size:14px;color:#555;">Tap a room to log it as cleaned:</p>';
                        html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;">';
                        data.data.rooms.forEach(function(room) {
                            html += '<button onclick="staffLogCleaning(' + staffId + ',' + room.id + ',this)" ' +
                                'data-room-id="' + room.id + '" ' +
                                'style="background:#6f42c1;color:#fff;border:none;border-radius:6px;padding:12px 8px;font-size:13px;font-weight:600;cursor:pointer;text-align:center;">' +
                                mmgrEsc(room.room_name) + '</button>';
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p style="color:#666;font-size:13px;">No rooms configured yet. Add rooms in the admin area.</p>';
                    }
                });
        };

        window.staffLogCleaning = function(staffId, roomId, btn) {
            const origText  = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳';

            const fd = new FormData();
            fd.append('action', 'mmgr_staff_log_cleaning');
            fd.append('staff_id', staffId);
            fd.append('room_id', roomId);
            fd.append('nonce', '<?php echo esc_js(wp_create_nonce('mmgr_staff_log_cleaning')); ?>');
            fetch(mmgrStaffAjax, { method:'POST', body:fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        returnToScan('✅ ' + origText + ' logged as cleaned.', 'success');
                    } else {
                        btn.disabled = false;
                        btn.textContent = origText;
                        alert(data.data ? data.data.message : 'Error logging clean.');
                    }
                });
        };

        // ── Helpers ──────────────────────────────────────────────────────────
        function returnToScan(msg, type) {
            const colors = { success:'#d4edda', warning:'#fff3cd', error:'#f8d7da' };
            const border  = { success:'#28a745',  warning:'#f0ad4e', error:'#dc3545' };
            const result  = document.getElementById('staff-scan-result');
            result.innerHTML = '<div style="background:' + (colors[type]||colors.success) + ';border-left:4px solid ' + (border[type]||border.success) + ';padding:14px 16px;border-radius:6px;font-weight:600;font-size:15px;">' + msg + '</div>';
            // Return to scan screen after 2.5 seconds
            setTimeout(function() {
                result.innerHTML = '';
                staffSwitchMode('camera');
            }, 2500);
        }

        function showStaffMessage(msg, type) {
            const colors = { success:'#d4edda', warning:'#fff3cd', error:'#f8d7da' };
            const border  = { success:'#28a745',  warning:'#f0ad4e', error:'#dc3545' };
            const result  = document.getElementById('staff-scan-result');
            const notice  = document.createElement('div');
            notice.style.cssText = 'background:' + (colors[type]||colors.success) + ';border-left:4px solid ' + (border[type]||border.success) + ';padding:14px 16px;border-radius:6px;margin-bottom:12px;font-weight:600;';
            notice.textContent = msg;
            result.prepend(notice);
        }

        function mmgrEsc(str) {
            const d = document.createElement('div');
            d.textContent = String(str);
            return d.innerHTML;
        }

        // Auto-start camera on page load since it is the default mode
        staffStartCamera();
    })();
    </script>
    <?php
    return ob_get_clean();
});
