<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-only PWA for Membership Manager
 *
 * Provides an installable Progressive Web App restricted to WordPress admins:
 *  - Web App Manifest scoped to /wp-admin/ (served only to manage_options users)
 *  - Service Worker for offline shell and admin push notifications
 *  - Push notifications delivered to admin devices when members send messages
 *  - Install banner and instructions injected into plugin admin pages
 */

// ---------------------------------------------------------------------------
// Rewrite rules: /mmgr-admin-sw.js  &  /mmgr-admin-manifest.webmanifest
// ---------------------------------------------------------------------------

add_action('init', 'mmgr_admin_pwa_add_rewrite_rules');
function mmgr_admin_pwa_add_rewrite_rules() {
    add_rewrite_rule('^mmgr-admin-sw\.js$',                  'index.php?mmgr_admin_sw=1',       'top');
    add_rewrite_rule('^mmgr-admin-manifest\.webmanifest$',   'index.php?mmgr_admin_manifest=1', 'top');
}

add_filter('query_vars', 'mmgr_admin_pwa_query_vars');
function mmgr_admin_pwa_query_vars($vars) {
    $vars[] = 'mmgr_admin_sw';
    $vars[] = 'mmgr_admin_manifest';
    return $vars;
}

add_action('template_redirect', 'mmgr_admin_pwa_handle_requests');
function mmgr_admin_pwa_handle_requests() {
    if (get_query_var('mmgr_admin_sw')) {
        mmgr_admin_pwa_serve_sw();
    }
    if (get_query_var('mmgr_admin_manifest')) {
        mmgr_admin_pwa_serve_manifest();
    }
}

// Flush rewrite rules whenever the plugin version changes.
add_action('admin_init', 'mmgr_admin_pwa_maybe_flush_rewrites');
function mmgr_admin_pwa_maybe_flush_rewrites() {
    if (get_option('mmgr_admin_pwa_rewrites_flushed') !== MMGR_VERSION) {
        mmgr_admin_pwa_add_rewrite_rules();
        flush_rewrite_rules();
        mmgr_admin_pwa_write_sw_file();
        update_option('mmgr_admin_pwa_rewrites_flushed', MMGR_VERSION);
    }
}

// Safety net: recreate the physical SW file if it was deleted.
add_action('admin_init', 'mmgr_admin_pwa_ensure_sw_file');
function mmgr_admin_pwa_ensure_sw_file() {
    if (get_transient('mmgr_admin_sw_file_ok')) {
        return;
    }
    if (!file_exists(ABSPATH . 'mmgr-admin-sw.js')) {
        mmgr_admin_pwa_write_sw_file();
    }
    set_transient('mmgr_admin_sw_file_ok', 1, HOUR_IN_SECONDS);
}

// Remove the physical SW file on plugin deactivation.
register_deactivation_hook(MMGR_PLUGIN_FILE, 'mmgr_admin_pwa_remove_sw_file');
function mmgr_admin_pwa_remove_sw_file() {
    $file = ABSPATH . 'mmgr-admin-sw.js';
    if (file_exists($file) && !unlink($file)) {
        error_log('[MMGR Admin PWA] Could not delete ' . $file);
    }
    delete_transient('mmgr_admin_sw_file_ok');
}

/**
 * Write a physical mmgr-admin-sw.js to the WordPress document root.
 *
 * nginx (and any other server that bypasses .htaccess) serves static files
 * directly, so a rewrite-only approach is not reliable. Writing the file to
 * disk ensures it is always reachable.
 */
function mmgr_admin_pwa_write_sw_file() {
    $admin_url = esc_url(admin_url('admin.php?page=membership_messages'));
    $site_url  = esc_url(get_site_url());

    $content = <<<JS
'use strict';

const MMGR_ADMIN_CACHE = 'mmgr-admin-v1';

self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== MMGR_ADMIN_CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Push: receive and display admin notification
self.addEventListener('push', event => {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'New Member Message', body: event.data.text() }; }

    const title   = data.title  || 'New Member Message \uD83D\uDCAC';
    const options = {
        body:               data.body   || 'A member sent you a message',
        icon:               '$site_url/mmgr-icon-192.png',
        badge:              '$site_url/mmgr-icon-72.png',
        tag:                data.tag    || 'mmgr-admin-message',
        data:               { url: data.url || '$admin_url' },
        requireInteraction: true,
        vibrate:            [200, 100, 200],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click: open / focus the admin messages page
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '$admin_url';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const c of list) {
                if (c.url.includes('/wp-admin/') && 'focus' in c) {
                    c.navigate(target);
                    return c.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(target);
        })
    );
});
JS;

    if (file_put_contents(ABSPATH . 'mmgr-admin-sw.js', $content) === false) {
        error_log('[MMGR Admin PWA] Could not write ' . ABSPATH . 'mmgr-admin-sw.js – check directory permissions.');
    } else {
        delete_transient('mmgr_admin_sw_file_ok');
    }
}

// ---------------------------------------------------------------------------
// Service Worker (rewrite fallback)
// ---------------------------------------------------------------------------

function mmgr_admin_pwa_serve_sw() {
    $admin_url = esc_url(admin_url('admin.php?page=membership_messages'));
    $site_url  = esc_url(get_site_url());

    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    ?>
'use strict';

const MMGR_ADMIN_CACHE = 'mmgr-admin-v1';

self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== MMGR_ADMIN_CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('push', event => {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'New Member Message', body: event.data.text() }; }

    const title   = data.title  || 'New Member Message \uD83D\uDCAC';
    const options = {
        body:               data.body   || 'A member sent you a message',
        icon:               '<?php echo $site_url; ?>/mmgr-icon-192.png',
        badge:              '<?php echo $site_url; ?>/mmgr-icon-72.png',
        tag:                data.tag    || 'mmgr-admin-message',
        data:               { url: data.url || '<?php echo $admin_url; ?>' },
        requireInteraction: true,
        vibrate:            [200, 100, 200],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '<?php echo $admin_url; ?>';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const c of list) {
                if (c.url.includes('/wp-admin/') && 'focus' in c) {
                    c.navigate(target);
                    return c.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(target);
        })
    );
});
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// Web App Manifest — restricted to logged-in admins
// ---------------------------------------------------------------------------

function mmgr_admin_pwa_serve_manifest() {
    // Only serve to logged-in WordPress admins
    if (!current_user_can('manage_options')) {
        status_header(403);
        exit;
    }

    $site_name = get_bloginfo('name');
    $start_url = admin_url('admin.php?page=membership_manager');
    $scope     = admin_url(); // /wp-admin/

    $custom_icon_url = function_exists('mmgr_get_pwa_icon_url') ? mmgr_get_pwa_icon_url() : null;
    $icon_192 = $custom_icon_url ?: home_url('/mmgr-icon-192.png');
    $icon_512 = $custom_icon_url ?: home_url('/mmgr-icon-512.png');

    $manifest = [
        'name'             => $site_name . ' Admin',
        'short_name'       => 'Admin',
        'description'      => 'Membership Manager admin panel for ' . $site_name,
        'start_url'        => $start_url,
        'scope'            => $scope,
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'background_color' => '#1d2327',
        'theme_color'      => '#0073aa',
        'lang'             => get_locale(),
        'icons'            => [
            [
                'src'     => $icon_192,
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src'     => $icon_512,
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
        ],
        'categories'       => ['business', 'utilities'],
    ];

    nocache_headers();
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ---------------------------------------------------------------------------
// Inject manifest link + SW registration into WP Admin <head>
// (only on our plugin's admin pages, only for manage_options users)
// ---------------------------------------------------------------------------

add_action('admin_head', 'mmgr_admin_pwa_inject_head', 20);
function mmgr_admin_pwa_inject_head() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'membership') === false) {
        return;
    }

    $manifest_url     = esc_url(home_url('/mmgr-admin-manifest.webmanifest'));
    $sw_url           = esc_url(home_url('/mmgr-admin-sw.js'));
    $vapid_keys       = function_exists('mmgr_pwa_get_vapid_keys') ? mmgr_pwa_get_vapid_keys() : [];
    $vapid_public_b64 = esc_js($vapid_keys['public'] ?? '');
    $ajax_url         = esc_url(admin_url('admin-ajax.php'));
    $save_nonce       = wp_create_nonce('mmgr_save_admin_push_subscription');
    $custom_icon_url  = function_exists('mmgr_get_pwa_icon_url') ? mmgr_get_pwa_icon_url() : null;
    $touch_icon_url   = $custom_icon_url ? esc_url($custom_icon_url) : esc_url(home_url('/mmgr-icon-192.png'));
    ?>
<!-- MMGR Admin PWA -->
<link rel="manifest" href="<?php echo $manifest_url; ?>">
<meta name="theme-color" content="#0073aa">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_bloginfo('name') . ' Admin'); ?>">
<link rel="apple-touch-icon" href="<?php echo $touch_icon_url; ?>">
<script>
(function() {
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?php echo $sw_url; ?>', { scope: '/wp-admin/' })
            .then(function(reg) {
                mmgrAdminSetupPush(reg, '<?php echo $vapid_public_b64; ?>');
            })
            .catch(function(err) {
                console.log('[MMGR Admin PWA] SW registration failed:', err);
            });
    });

    function mmgrUrlB64ToUint8(b64) {
        var pad = '='.repeat((4 - b64.length % 4) % 4);
        var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    function mmgrAdminSetupPush(reg, vapidKey) {
        if (!('PushManager' in window) || !('Notification' in window) || !vapidKey) return;

        reg.pushManager.getSubscription().then(function(existing) {
            if (existing) {
                mmgrAdminSaveSubscription(existing);
                return;
            }

            if (Notification.permission === 'granted') {
                mmgrAdminSubscribePush(reg, vapidKey);
                return;
            }
            if (Notification.permission === 'denied') return;

            mmgrAdminShowNotifyPrompt(reg, vapidKey);
        });
    }

    function mmgrAdminSubscribePush(reg, vapidKey) {
        reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: mmgrUrlB64ToUint8(vapidKey)
        }).then(function(sub) {
            mmgrAdminSaveSubscription(sub);
        }).catch(function(e) {
            console.log('[MMGR Admin PWA] Push subscribe error:', e);
        });
    }

    function mmgrAdminShowNotifyPrompt(reg, vapidKey) {
        var stale = document.getElementById('mmgr-admin-notify-btn');
        if (stale) stale.remove();

        var btn = document.createElement('button');
        btn.id = 'mmgr-admin-notify-btn';
        btn.textContent = '\uD83D\uDD14 Enable Admin Push Notifications';
        btn.style.cssText = 'position:fixed;bottom:24px;right:24px;'
            + 'background:linear-gradient(135deg,#0073aa,#005177);color:#fff;border:none;'
            + 'padding:12px 20px;border-radius:30px;font-size:14px;font-weight:700;'
            + 'box-shadow:0 4px 16px rgba(0,115,170,0.4);z-index:99998;cursor:pointer;'
            + 'white-space:nowrap;font-family:-apple-system,BlinkMacSystemFont,sans-serif;';
        document.body.appendChild(btn);

        btn.addEventListener('click', function() {
            Notification.requestPermission().then(function(perm) {
                btn.remove();
                if (perm === 'granted') {
                    mmgrAdminSubscribePush(reg, vapidKey);
                }
            });
        });
    }

    function mmgrAdminSaveSubscription(sub) {
        var fd = new FormData();
        fd.append('action',       'mmgr_save_admin_push_subscription');
        fd.append('subscription', JSON.stringify(sub.toJSON()));
        fd.append('nonce',        '<?php echo esc_js($save_nonce); ?>');
        fetch('<?php echo $ajax_url; ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.success) console.log('[MMGR Admin PWA] Push subscription saved'); })
            .catch(function(e) { console.log('[MMGR Admin PWA] Push save error:', e); });
    }
}());
</script>
    <?php
}

// ---------------------------------------------------------------------------
// Install banner + instructions modal (admin footer, plugin pages only)
// ---------------------------------------------------------------------------

add_action('admin_footer', 'mmgr_admin_pwa_inject_install_banner', 20);
function mmgr_admin_pwa_inject_install_banner() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'membership') === false) {
        return;
    }

    $site_name = esc_html(get_bloginfo('name'));
    $custom_icon_url = function_exists('mmgr_get_pwa_icon_url') ? mmgr_get_pwa_icon_url() : null;
    $icon_url  = $custom_icon_url ? esc_url($custom_icon_url) : esc_url(home_url('/mmgr-icon-192.png'));
    ?>
<!-- MMGR Admin PWA Install Banner -->
<style>
#mmgr-admin-install-banner {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: linear-gradient(135deg, #0073aa, #005177);
    color: #fff;
    padding: 12px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 14px;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.25);
    align-items: center;
    gap: 12px;
}
#mmgr-admin-install-banner img {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    flex-shrink: 0;
}
#mmgr-admin-install-banner .mmgr-admin-banner-text { flex: 1; }
#mmgr-admin-install-banner .mmgr-admin-banner-text strong { display: block; font-size: 15px; }
#mmgr-admin-install-banner .mmgr-admin-banner-text span  { font-size: 12px; opacity: 0.85; }
#mmgr-admin-banner-install-btn {
    background: #fff;
    color: #0073aa;
    border: none;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
}
#mmgr-admin-banner-close {
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    border: none;
    font-size: 22px;
    cursor: pointer;
    flex-shrink: 0;
    padding: 0 4px;
    line-height: 1;
}
#mmgr-admin-install-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 100000;
    background: rgba(0, 0, 0, 0.65);
    align-items: center;
    justify-content: center;
}
#mmgr-admin-install-modal-box {
    background: #fff;
    border-radius: 16px;
    padding: 32px 28px;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.3);
    position: relative;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: #1d2327;
    text-align: center;
}
#mmgr-admin-install-modal-box h3 { font-size: 20px; margin-bottom: 12px; }
#mmgr-admin-install-modal-box p  { font-size: 14px; line-height: 1.6; color: #444; margin-bottom: 16px; }
#mmgr-admin-install-modal-box img { width: 72px; height: 72px; border-radius: 14px; margin-bottom: 16px; }
#mmgr-admin-install-modal-close {
    position: absolute;
    top: 14px;
    right: 18px;
    background: none;
    border: none;
    font-size: 26px;
    cursor: pointer;
    color: #999;
}
.mmgr-admin-install-step {
    text-align: left;
    background: #f6f7f7;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 10px;
    font-size: 13px;
}
</style>

<div id="mmgr-admin-install-banner">
    <img src="<?php echo $icon_url; ?>" alt="">
    <div class="mmgr-admin-banner-text">
        <strong><?php echo $site_name; ?> Admin App</strong>
        <span>Install for quick access &amp; push notifications when members message you</span>
    </div>
    <button id="mmgr-admin-banner-install-btn">Install App</button>
    <button id="mmgr-admin-banner-close" aria-label="Close">&times;</button>
</div>

<div id="mmgr-admin-install-modal" role="dialog" aria-modal="true" aria-labelledby="mmgr-admin-modal-title">
    <div id="mmgr-admin-install-modal-box">
        <button id="mmgr-admin-install-modal-close" aria-label="Close">&times;</button>
        <img src="<?php echo $icon_url; ?>" alt="App icon">
        <h3 id="mmgr-admin-modal-title">Install <?php echo $site_name; ?> Admin App</h3>
        <p>Add this app to your home screen for fast access to the admin panel and real-time push notifications when members send you messages.</p>
        <div class="mmgr-admin-install-step">📱 <strong>iPhone/iPad:</strong> Tap the <strong>Share</strong> icon in Safari, then choose <em>"Add to Home Screen"</em>.</div>
        <div class="mmgr-admin-install-step">🤖 <strong>Android:</strong> Tap the browser menu and choose <em>"Add to Home Screen"</em> or <em>"Install App"</em>.</div>
        <div class="mmgr-admin-install-step">💻 <strong>Desktop (Chrome/Edge):</strong> Click the <strong>install icon ⊕</strong> in the address bar.</div>
    </div>
</div>

<script>
(function() {
    var banner     = document.getElementById('mmgr-admin-install-banner');
    var modal      = document.getElementById('mmgr-admin-install-modal');
    var closeBtn   = document.getElementById('mmgr-admin-banner-close');
    var installBtn = document.getElementById('mmgr-admin-banner-install-btn');
    var modalClose = document.getElementById('mmgr-admin-install-modal-close');

    var deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        if (!sessionStorage.getItem('mmgr_admin_banner_dismissed')) {
            banner.style.display = 'flex';
        }
    });

    window.addEventListener('appinstalled', function() {
        banner.style.display = 'none';
        deferredPrompt = null;
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            banner.style.display = 'none';
            sessionStorage.setItem('mmgr_admin_banner_dismissed', '1');
        });
    }

    if (installBtn) {
        installBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function() {
                    banner.style.display = 'none';
                    deferredPrompt = null;
                });
            } else {
                // iOS / desktop fallback: show instructions modal
                banner.style.display = 'none';
                modal.style.display = 'flex';
            }
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Already installed as a standalone PWA — hide the banner permanently
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        banner.style.display = 'none';
    }
}());
</script>
    <?php
}

// ---------------------------------------------------------------------------
// Admin PWA info page (shown under the plugin submenu)
// ---------------------------------------------------------------------------

function mmgr_admin_pwa_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $site_name       = esc_html(get_bloginfo('name'));
    $custom_icon_url = function_exists('mmgr_get_pwa_icon_url') ? mmgr_get_pwa_icon_url() : null;
    $icon_url        = $custom_icon_url ? esc_url($custom_icon_url) : esc_url(home_url('/mmgr-icon-192.png'));
    $manifest_url    = esc_url(home_url('/mmgr-admin-manifest.webmanifest'));

    global $wpdb;
    $sub_count = 0;
    $table = $wpdb->prefix . 'mmgr_admin_push_subscriptions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
        $sub_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    }
    ?>
    <div class="wrap">
        <h1>📱 Admin App (PWA)</h1>
        <p>Install the <strong><?php echo $site_name; ?> Admin App</strong> on your device to get quick access to the membership admin panel and receive push notifications when members send you messages.</p>

        <div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:24px;">

            <!-- Install card -->
            <div style="background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,0.08);flex:1;min-width:280px;max-width:480px;">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                    <img src="<?php echo $icon_url; ?>" alt="App icon" style="width:64px;height:64px;border-radius:12px;">
                    <div>
                        <strong style="font-size:18px;display:block;"><?php echo $site_name; ?> Admin</strong>
                        <span style="color:#777;font-size:13px;">Admin-only PWA &bull; Membership Manager</span>
                    </div>
                </div>

                <button id="mmgr-admin-pwa-install-btn"
                        style="display:inline-block;background:#0073aa;color:#fff;border:none;padding:12px 24px;
                               border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;width:100%;margin-bottom:12px;">
                    ⬇ Install Admin App
                </button>
                <p style="font-size:12px;color:#888;text-align:center;margin:0;">
                    Only admins with WordPress login can install and access this app.
                </p>
            </div>

            <!-- How to install card -->
            <div style="background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,0.08);flex:1;min-width:280px;max-width:480px;">
                <h2 style="margin-top:0;font-size:16px;">How to Install</h2>
                <ul style="margin:0;padding:0;list-style:none;">
                    <li style="padding:10px 0;border-bottom:1px solid #f0f0f0;">
                        📱 <strong>iPhone/iPad:</strong> Open this page in Safari → tap <strong>Share ⬆</strong> → <em>"Add to Home Screen"</em>
                    </li>
                    <li style="padding:10px 0;border-bottom:1px solid #f0f0f0;">
                        🤖 <strong>Android:</strong> Tap the browser menu → <em>"Add to Home Screen"</em> or <em>"Install App"</em>
                    </li>
                    <li style="padding:10px 0;">
                        💻 <strong>Desktop (Chrome/Edge):</strong> Click the install icon <strong>⊕</strong> in the address bar
                    </li>
                </ul>
            </div>

            <!-- Push notification status card -->
            <div style="background:#fff;border-radius:12px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,0.08);flex:1;min-width:280px;max-width:480px;">
                <h2 style="margin-top:0;font-size:16px;">🔔 Push Notifications</h2>
                <p style="font-size:13px;color:#444;">
                    Receive instant push notifications on your device whenever a member sends a message to admin.
                </p>
                <p style="font-size:13px;">
                    <strong>Active admin subscriptions:</strong>
                    <span style="background:#0073aa;color:#fff;border-radius:10px;padding:2px 10px;font-size:12px;margin-left:6px;">
                        <?php echo $sub_count; ?>
                    </span>
                </p>
                <p style="font-size:12px;color:#777;margin:0;">
                    Push notifications are automatically set up when you visit any admin page in this plugin. You will be prompted to allow notifications on first visit.
                </p>
            </div>

        </div><!-- end flex row -->

        <div style="margin-top:24px;background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-top:0;font-size:16px;">ℹ️ Security &amp; Access</h2>
            <ul style="font-size:13px;color:#444;margin:0;padding-left:20px;line-height:1.8;">
                <li>The admin app manifest is only served to users authenticated as WordPress administrators (<code>manage_options</code> capability).</li>
                <li>The app opens with the WordPress admin session — your existing login is used automatically.</li>
                <li>If you log out of WordPress, the app will redirect to the WordPress login page.</li>
                <li>Push notification subscriptions are tied to WordPress admin accounts and are stored securely in the database.</li>
            </ul>
        </div>

    </div><!-- end wrap -->

    <script>
    (function() {
        var installBtn = document.getElementById('mmgr-admin-pwa-install-btn');
        var modal = document.getElementById('mmgr-admin-install-modal');

        var deferredPrompt = null;

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) {
                installBtn.textContent = '⬇ Install Admin App';
                installBtn.disabled = false;
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function() {
                        deferredPrompt = null;
                    });
                } else if (modal) {
                    modal.style.display = 'flex';
                } else {
                    alert('To install:\n\niPhone/iPad: Share → Add to Home Screen\nAndroid: Browser menu → Add to Home Screen\nDesktop: Click ⊕ in address bar');
                }
            });
        }

        // Already installed
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            if (installBtn) {
                installBtn.textContent = '✓ Already Installed';
                installBtn.style.background = '#00a32a';
                installBtn.disabled = true;
            }
        }
    }());
    </script>
    <?php
}

// ---------------------------------------------------------------------------
// AJAX: Save admin push subscription (logged-in admins only)
// ---------------------------------------------------------------------------

add_action('wp_ajax_mmgr_save_admin_push_subscription', 'mmgr_ajax_save_admin_push_subscription');
function mmgr_ajax_save_admin_push_subscription() {
    check_ajax_referer('mmgr_save_admin_push_subscription', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }

    $raw = isset($_POST['subscription']) ? wp_unslash($_POST['subscription']) : '';
    if (empty($raw)) {
        wp_send_json_error(['message' => 'No subscription data']);
        return;
    }

    $sub = json_decode($raw, true);
    if (!isset($sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth'])) {
        wp_send_json_error(['message' => 'Invalid subscription structure']);
        return;
    }

    $endpoint = esc_url_raw($sub['endpoint']);
    $p256dh   = sanitize_text_field($sub['keys']['p256dh']);
    $auth     = sanitize_text_field($sub['keys']['auth']);

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        wp_send_json_error(['message' => 'Missing subscription fields']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'mmgr_admin_push_subscriptions';

    // Remove any existing record for this endpoint, then insert fresh.
    $wpdb->delete($table, ['endpoint' => $endpoint], ['%s']);
    $result = $wpdb->insert($table, [
        'wp_user_id' => get_current_user_id(),
        'endpoint'   => $endpoint,
        'p256dh'     => $p256dh,
        'auth'       => $auth,
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%s', '%s']);

    if ($result) {
        wp_send_json_success(['message' => 'Admin push subscription saved']);
    } else {
        wp_send_json_error(['message' => 'DB error saving subscription']);
    }
}

// ---------------------------------------------------------------------------
// Send push notification to all admin subscribers
// ---------------------------------------------------------------------------

/**
 * Dispatch a Web Push notification to all registered admin devices.
 * Called from mmgr_send_message() when a member messages admin (to_member_id = 0).
 *
 * @param string $title  Notification title.
 * @param string $body   Notification body text.
 * @param string $url    URL to open on notification click (defaults to admin messages page).
 */
function mmgr_admin_pwa_send_push_to_admins(string $title, string $body, string $url = ''): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mmgr_admin_push_subscriptions';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return;
    }

    $subs = $wpdb->get_results("SELECT id, endpoint, p256dh, auth FROM `$table`", ARRAY_A);
    if (empty($subs)) {
        return;
    }

    if (!function_exists('mmgr_pwa_get_vapid_keys') || !function_exists('mmgr_pwa_send_web_push')) {
        return;
    }

    $vapid = mmgr_pwa_get_vapid_keys();
    if (empty($vapid)) {
        return;
    }

    $target_url = $url ?: admin_url('admin.php?page=membership_messages');
    $payload = wp_json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $target_url,
        'tag'   => 'mmgr-admin-msg',
    ]);

    foreach ($subs as $sub) {
        $http_status = mmgr_pwa_send_web_push(
            $sub['endpoint'],
            $sub['p256dh'],
            $sub['auth'],
            $payload,
            $vapid
        );

        // Subscription gone — remove stale record.
        if ($http_status === 404 || $http_status === 410) {
            $wpdb->delete($table, ['id' => intval($sub['id'])], ['%d']);
        }
    }
}
