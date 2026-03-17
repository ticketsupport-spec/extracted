<?php
if (!defined('ABSPATH')) exit;

/**
 * PWA & Push Notification support for Membership Manager
 *
 * Provides:
 *  - Web App Manifest (installable "Add to Home Screen")
 *  - Service Worker (offline shell + notification display)
 *  - Web Push Notifications via VAPID / RFC 8291 / RFC 8292
 */

// ---------------------------------------------------------------------------
// Rewrite rules: /mmgr-sw.js  &  /mmgr-manifest.webmanifest
// ---------------------------------------------------------------------------

add_action('init', 'mmgr_pwa_add_rewrite_rules');
function mmgr_pwa_add_rewrite_rules() {
    add_rewrite_rule('^mmgr-sw\.js$',                  'index.php?mmgr_sw=1',       'top');
    add_rewrite_rule('^mmgr-manifest\.webmanifest$',   'index.php?mmgr_manifest=1', 'top');
    add_rewrite_rule('^mmgr-icon-([0-9]+)\.png$',      'index.php?mmgr_icon=$matches[1]', 'top');
}

add_filter('query_vars', function($vars) {
    $vars[] = 'mmgr_sw';
    $vars[] = 'mmgr_manifest';
    $vars[] = 'mmgr_icon';
    return $vars;
});

add_action('template_redirect', 'mmgr_pwa_handle_requests');
function mmgr_pwa_handle_requests() {
    if (get_query_var('mmgr_sw')) {
        mmgr_pwa_serve_sw();
    }
    if (get_query_var('mmgr_manifest')) {
        mmgr_pwa_serve_manifest();
    }
    $icon_size = get_query_var('mmgr_icon');
    if ($icon_size) {
        mmgr_pwa_serve_icon(intval($icon_size));
    }
}

// Flush rewrite rules on activation so the new rules take effect
register_activation_hook(MMGR_PLUGIN_FILE, 'mmgr_pwa_on_activate');
function mmgr_pwa_on_activate() {
    mmgr_pwa_add_rewrite_rules();
    flush_rewrite_rules();
    mmgr_pwa_write_sw_file();
}

// Ensure rewrite rules are flushed whenever the plugin version changes (or on first
// activation). Using the version as the stored value means any plugin update
// automatically re-flushes the rules, preventing the /mmgr-sw.js 404.
add_action('admin_init', 'mmgr_pwa_maybe_flush_rewrites');
function mmgr_pwa_maybe_flush_rewrites() {
    if (get_option('mmgr_pwa_rewrites_flushed') !== MMGR_VERSION) {
        mmgr_pwa_add_rewrite_rules();
        flush_rewrite_rules();
        mmgr_pwa_write_sw_file();
        update_option('mmgr_pwa_rewrites_flushed', MMGR_VERSION);
    }
}

// Safety net: recreate the physical file on admin page loads if it was deleted.
// Uses a transient so the filesystem check runs at most once per hour, keeping
// overhead minimal even on busy sites.
add_action('admin_init', 'mmgr_pwa_ensure_sw_file');
function mmgr_pwa_ensure_sw_file() {
    if (get_transient('mmgr_sw_file_ok')) {
        return;
    }
    if (!file_exists(ABSPATH . 'mmgr-sw.js')) {
        mmgr_pwa_write_sw_file();
    }
    set_transient('mmgr_sw_file_ok', 1, HOUR_IN_SECONDS);
}

// Remove the physical service-worker file on plugin deactivation.
register_deactivation_hook(MMGR_PLUGIN_FILE, 'mmgr_pwa_remove_sw_file');
function mmgr_pwa_remove_sw_file() {
    $file = ABSPATH . 'mmgr-sw.js';
    if (file_exists($file) && !unlink($file)) {
        error_log('[MMGR PWA] Could not delete ' . $file);
    }
    delete_transient('mmgr_sw_file_ok');
}

/**
 * Write a physical mmgr-sw.js to the WordPress document root.
 *
 * nginx (and any other web server that does not process .htaccess) serves
 * static files directly, so WordPress rewrite rules alone are not enough to
 * route /mmgr-sw.js through index.php. Writing the file to disk means the
 * server can always find it, regardless of the server software or rewrite
 * configuration.
 */
function mmgr_pwa_write_sw_file() {
    $messages_url = esc_url(home_url('/member-messages/'));
    $site_url     = esc_url(get_site_url());

    // Build the file content – keep this in sync with mmgr_pwa_serve_sw().
    $content = <<<JS
'use strict';

const MMGR_CACHE = 'mmgr-portal-v1';

// Install: pre-cache the app shell page
self.addEventListener('install', event => {
    self.skipWaiting();
});

// Activate: remove stale caches, claim clients
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== MMGR_CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Push: receive and display notification
self.addEventListener('push', event => {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'New Message', body: event.data.text() }; }

    const title   = data.title  || 'New Message \uD83D\uDCAC';
    const options = {
        body:               data.body   || 'You have a new message',
        icon:               '$site_url/mmgr-icon-192.png',
        badge:              '$site_url/mmgr-icon-72.png',
        tag:                data.tag    || 'mmgr-message',
        data:               { url: data.url || '$messages_url' },
        requireInteraction: true,
        vibrate:            [200, 100, 200],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click: open / focus the messages page
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '$messages_url';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const c of list) {
                if (c.url.includes('/member-') && 'focus' in c) {
                    c.navigate(target);
                    return c.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(target);
        })
    );
});
JS;

    if (file_put_contents(ABSPATH . 'mmgr-sw.js', $content) === false) {
        error_log('[MMGR PWA] Could not write ' . ABSPATH . 'mmgr-sw.js – check directory permissions.');
    } else {
        // Let the safety-net transient expire naturally so it re-verifies next hour.
        delete_transient('mmgr_sw_file_ok');
    }
}

// ---------------------------------------------------------------------------
// Service Worker
// ---------------------------------------------------------------------------

function mmgr_pwa_serve_sw() {
    $messages_url = esc_url(home_url('/member-messages/'));
    $site_url      = esc_url(get_site_url());

    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    ?>
'use strict';

const MMGR_CACHE = 'mmgr-portal-v1';

// Install: pre-cache the app shell page
self.addEventListener('install', event => {
    self.skipWaiting();
});

// Activate: remove stale caches, claim clients
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== MMGR_CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Push: receive and display notification
self.addEventListener('push', event => {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch (e) { data = { title: 'New Message', body: event.data.text() }; }

    const title   = data.title  || 'New Message \uD83D\uDCAC';
    const options = {
        body:               data.body   || 'You have a new message',
        icon:               '<?php echo $site_url; ?>/mmgr-icon-192.png',
        badge:              '<?php echo $site_url; ?>/mmgr-icon-72.png',
        tag:                data.tag    || 'mmgr-message',
        data:               { url: data.url || '<?php echo $messages_url; ?>' },
        requireInteraction: true,
        vibrate:            [200, 100, 200],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click: open / focus the messages page
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '<?php echo $messages_url; ?>';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const c of list) {
                if (c.url.includes('/member-') && 'focus' in c) {
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
// Web App Manifest
// ---------------------------------------------------------------------------

/**
 * Return the URL for the custom PWA icon, or null if none is set.
 * Only returns a URL for valid image attachments.
 */
function mmgr_get_pwa_icon_url(): ?string {
    $icon_id = intval(get_option('mmgr_pwa_icon_id', 0));
    if ($icon_id && wp_attachment_is_image($icon_id)) {
        $url = wp_get_attachment_url($icon_id);
        return $url ?: null;
    }
    return null;
}

function mmgr_pwa_serve_manifest() {
    $site_name = get_bloginfo('name');
    $base      = home_url('/');

    // Use uploaded icon if available, otherwise fall back to dynamic endpoints.
    $custom_icon_url = mmgr_get_pwa_icon_url();
    $icon_192 = $custom_icon_url ?: home_url('/mmgr-icon-192.png');
    $icon_512 = $custom_icon_url ?: home_url('/mmgr-icon-512.png');

    $manifest = [
        'name'             => $site_name . ' Member Portal',
        'short_name'       => $site_name,
        'description'      => 'Member portal for ' . $site_name,
        'start_url'        => home_url('/member-dashboard/'),
        'scope'            => '/',
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'background_color' => '#f9f9f9',
        'theme_color'      => '#9b51e0',
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
        'categories'       => ['utilities'],
    ];

    nocache_headers();
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Icon endpoint (custom upload or GD-generated purple square with "M")
// ---------------------------------------------------------------------------

function mmgr_pwa_serve_icon(int $size) {
    $size = max(16, min(1024, $size));

    // If the admin uploaded a custom PWA icon, serve it directly.
    $icon_id = intval(get_option('mmgr_pwa_icon_id', 0));
    if ($icon_id && wp_attachment_is_image($icon_id)) {
        $icon_path = get_attached_file($icon_id);
        if ($icon_path && file_exists($icon_path)) {
            $allowed_mime = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
            $mime = function_exists('mime_content_type') ? mime_content_type($icon_path) : 'image/png';
            if (!in_array($mime, $allowed_mime, true)) {
                $mime = 'image/png';
            }
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=604800');
            readfile($icon_path);
            exit;
        }
    }

    if (!function_exists('imagecreatetruecolor')) {
        // GD not available – send a 1×1 purple PNG
        header('Content-Type: image/png');
        // Minimal 1×1 purple PNG (base64 decoded)
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQI12NgYGBgAAACAAEiKOiZAAAAAElFTkSuQmCC');
        exit;
    }

    $img = imagecreatetruecolor($size, $size);
    $bg  = imagecolorallocate($img, 155, 81, 224);  // #9b51e0
    $fg  = imagecolorallocate($img, 255, 255, 255); // white

    // Rounded-corner illusion: fill solid then overdraw corners with a "transparent" colour
    imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

    // Draw a simple "M" in the centre
    $font_size = intval($size * 0.45);
    $cx = intval($size / 2);
    $cy = intval($size / 2);

    if (function_exists('imagettftext')) {
        // Try common font locations across Linux, macOS, Windows
        $font_candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ];
        $font = null;
        foreach ($font_candidates as $candidate) {
            if (is_readable($candidate)) { $font = $candidate; break; }
        }
        if ($font) {
            $bbox = imagettfbbox($font_size, 0, $font, 'M');
            $tx   = $cx - intval(($bbox[2] - $bbox[0]) / 2);
            $ty   = $cy - intval(($bbox[5] - $bbox[3]) / 2);
            imagettftext($img, $font_size, 0, $tx, $ty, $fg, $font, 'M');
        } else {
            // No TTF font found – fall back to built-in
            $tx = intval(($size - 8) / 2);
            $ty = intval(($size - 8) / 2);
            imagestring($img, 5, $tx, $ty, 'M', $fg);
        }
    } else {
        // Fallback: built-in font
        $char_w = 8;
        $char_h = 8;
        $tx = intval(($size - $char_w) / 2);
        $ty = intval(($size - $char_h) / 2);
        imagestring($img, 5, $tx, $ty, 'M', $fg);
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    imagepng($img);
    imagedestroy($img);
    exit;
}

// ---------------------------------------------------------------------------
// Inject <link rel="manifest"> + SW registration script into <head>
// ---------------------------------------------------------------------------

add_action('wp_head', 'mmgr_pwa_inject_head', 20);
function mmgr_pwa_inject_head() {
    // Only on member portal pages
    $current_slug = get_post_field('post_name', get_the_ID());
    $portal_slugs = [
        'member-dashboard', 'member-messages', 'member-activity',
        'member-profile',   'member-community', 'members-directory',
        'member-login',     'member-code-of-conduct', 'member-help',
    ];
    if (!in_array($current_slug, $portal_slugs, true)) {
        return;
    }

    $manifest_url    = esc_url(home_url('/mmgr-manifest.webmanifest'));
    $sw_url          = esc_url(home_url('/mmgr-sw.js'));
    $vapid_keys      = mmgr_pwa_get_vapid_keys();
    $vapid_public_b64 = esc_js($vapid_keys['public'] ?? '');
    $ajax_url        = esc_url(admin_url('admin-ajax.php'));
    $save_nonce      = wp_create_nonce('mmgr_save_push_subscription');
    $site_name       = esc_js(get_bloginfo('name'));
    $pwa_icon        = mmgr_get_pwa_icon_url();
    $touch_icon_url  = $pwa_icon ? esc_url($pwa_icon) : esc_url(home_url('/mmgr-icon-192.png'));
    ?>
<!-- PWA Manifest -->
<link rel="manifest" href="<?php echo $manifest_url; ?>">
<meta name="theme-color" content="#9b51e0">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
<link rel="apple-touch-icon" href="<?php echo $touch_icon_url; ?>">

<script>
(function() {
    // Register Service Worker
    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?php echo $sw_url; ?>')
            .then(function(reg) {
                mmgrSetupPush(reg, '<?php echo $vapid_public_b64; ?>');
            })
            .catch(function(err) {
                console.log('[MMGR PWA] SW registration failed:', err);
            });
    });

    function mmgrUrlB64ToUint8(b64) {
        var pad = '='.repeat((4 - b64.length % 4) % 4);
        var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    function mmgrSetupPush(reg, vapidKey) {
        if (!('PushManager' in window) || !('Notification' in window) || !vapidKey) return;

        var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent || '');
        var isAndroid = /android/i.test(navigator.userAgent || '');
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                        || window.navigator.standalone === true;

        // iOS push only works when running as an installed standalone app
        if (isIOS && !isStandalone) return;

        reg.pushManager.getSubscription().then(function(existing) {
            if (existing) {
                // Subscription already exists in the browser — ensure it is also
                // persisted in the database (handles the case where a previous
                // save attempt failed, e.g. before the nopriv AJAX hook existed).
                mmgrSaveSubscription(existing);
                return;
            }

            if (isIOS) {
                // iOS requires Notification.requestPermission() to be initiated
                // by a user gesture — calling it automatically is silently ignored
                mmgrShowIosNotifyPrompt(reg, vapidKey);
                return;
            }

            if (isAndroid) {
                // Modern Chrome on Android also requires a user gesture for
                // Notification.requestPermission() — show a button like iOS
                mmgrShowAndroidNotifyPrompt(reg, vapidKey);
                return;
            }

            // Desktop/PC: show a button so the user opts in consciously
            mmgrShowDesktopNotifyPrompt(reg, vapidKey);
        });
    }

    function mmgrSubscribePush(reg, vapidKey) {
        reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: mmgrUrlB64ToUint8(vapidKey)
        }).then(function(sub) {
            mmgrSaveSubscription(sub);
        }).catch(function(e) {
            console.log('[MMGR PWA] Push subscribe error:', e);
        });
    }

    function mmgrShowAndroidNotifyPrompt(reg, vapidKey) {
        // If already granted subscribe directly; if denied nothing we can do
        if (Notification.permission === 'granted') {
            mmgrSubscribePush(reg, vapidKey);
            return;
        }
        if (Notification.permission === 'denied') return;

        var stale = document.getElementById('mmgr-android-notify-btn');
        if (stale) stale.remove();

        var btn = document.createElement('button');
        btn.id = 'mmgr-android-notify-btn';
        btn.textContent = '\uD83D\uDD14 Enable Notifications';
        btn.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);'
            + 'background:linear-gradient(135deg,#9b51e0,#ce00ff);color:#fff;border:none;'
            + 'padding:13px 24px;border-radius:30px;font-size:15px;font-weight:700;'
            + 'box-shadow:0 4px 16px rgba(155,81,224,0.4);z-index:99998;cursor:pointer;'
            + 'white-space:nowrap;';
        document.body.appendChild(btn);

        btn.addEventListener('click', function() {
            Notification.requestPermission().then(function(perm) {
                btn.remove();
                if (perm === 'granted') {
                    mmgrSubscribePush(reg, vapidKey);
                }
            });
        });
    }

    function mmgrShowDesktopNotifyPrompt(reg, vapidKey) {
        // If already granted subscribe directly; if denied nothing we can do
        if (Notification.permission === 'granted') {
            mmgrSubscribePush(reg, vapidKey);
            return;
        }
        if (Notification.permission === 'denied') return;

        var stale = document.getElementById('mmgr-desktop-notify-btn');
        if (stale) stale.remove();

        var btn = document.createElement('button');
        btn.id = 'mmgr-desktop-notify-btn';
        btn.textContent = '\uD83D\uDD14 Enable Push Notifications';
        btn.style.cssText = 'position:fixed;bottom:24px;right:24px;'
            + 'background:linear-gradient(135deg,#9b51e0,#ce00ff);color:#fff;border:none;'
            + 'padding:12px 20px;border-radius:30px;font-size:14px;font-weight:700;'
            + 'box-shadow:0 4px 16px rgba(155,81,224,0.4);z-index:99998;cursor:pointer;'
            + 'white-space:nowrap;';
        document.body.appendChild(btn);

        btn.addEventListener('click', function() {
            Notification.requestPermission().then(function(perm) {
                btn.remove();
                if (perm === 'granted') {
                    mmgrSubscribePush(reg, vapidKey);
                }
            });
        });
    }

    function mmgrShowIosNotifyPrompt(reg, vapidKey) {
        // If already granted (e.g. subscription was lost after reinstall), subscribe directly
        if (Notification.permission === 'granted') {
            mmgrSubscribePush(reg, vapidKey);
            return;
        }
        // If already denied, nothing we can do programmatically
        if (Notification.permission === 'denied') return;

        // Remove any stale button left from a previous page load
        var stale = document.getElementById('mmgr-ios-notify-btn');
        if (stale) stale.remove();

        var btn = document.createElement('button');
        btn.id = 'mmgr-ios-notify-btn';
        btn.textContent = '\uD83D\uDD14 Enable Notifications';
        btn.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);'
            + 'background:linear-gradient(135deg,#9b51e0,#ce00ff);color:#fff;border:none;'
            + 'padding:13px 24px;border-radius:30px;font-size:15px;font-weight:700;'
            + 'box-shadow:0 4px 16px rgba(155,81,224,0.4);z-index:99998;cursor:pointer;'
            + 'white-space:nowrap;';
        document.body.appendChild(btn);

        btn.addEventListener('click', function() {
            Notification.requestPermission().then(function(perm) {
                if (perm === 'granted') {
                    btn.remove();
                    mmgrSubscribePush(reg, vapidKey);
                } else {
                    // Permission denied — button is no longer useful; remove it
                    btn.remove();
                }
            });
        });
    }

    function mmgrSaveSubscription(sub) {
        var fd = new FormData();
        fd.append('action',       'mmgr_save_push_subscription');
        fd.append('subscription', JSON.stringify(sub.toJSON()));
        fd.append('nonce',        '<?php echo esc_js($save_nonce); ?>');
        fetch('<?php echo $ajax_url; ?>', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.success) console.log('[MMGR PWA] Push subscription saved'); })
            .catch(function(e) { console.log('[MMGR PWA] Push save error:', e); });
    }
}());
</script>
    <?php
}

// ---------------------------------------------------------------------------
// Install banner + instructions modal (injected into page footer)
// ---------------------------------------------------------------------------

add_action('wp_footer', 'mmgr_pwa_inject_install_banner', 20);
function mmgr_pwa_inject_install_banner() {
    // Only on member portal pages
    $current_slug = get_post_field('post_name', get_the_ID());
    $portal_slugs = [
        'member-dashboard', 'member-messages', 'member-activity',
        'member-profile',   'member-community', 'members-directory',
        'member-login',     'member-code-of-conduct', 'member-help',
    ];
    if (!in_array($current_slug, $portal_slugs, true)) {
        return;
    }

    $site_name = esc_html(get_bloginfo('name'));
    $pwa_icon  = mmgr_get_pwa_icon_url();
    $icon_url  = $pwa_icon ? esc_url($pwa_icon) : esc_url(home_url('/mmgr-icon-192.png'));
    ?>
<!-- MMGR PWA Install Banner -->
<style>
#mmgr-install-banner {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 99999;
    background: linear-gradient(135deg, #9b51e0, #ce00ff);
    color: #fff;
    padding: 10px 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}
#mmgr-install-banner.mmgr-banner-visible {
    display: flex;
}
#mmgr-install-banner-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    flex-shrink: 0;
}
#mmgr-install-banner-text {
    flex: 1;
    line-height: 1.3;
}
#mmgr-install-banner-text strong {
    display: block;
    font-size: 13px;
}
#mmgr-install-banner-text span {
    font-size: 12px;
    opacity: 0.9;
}
#mmgr-install-banner-cta {
    background: rgba(255,255,255,0.25);
    border: 1px solid rgba(255,255,255,0.5);
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    flex-shrink: 0;
}
#mmgr-install-banner-dismiss {
    background: none;
    border: none;
    color: rgba(255,255,255,0.8);
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
    flex-shrink: 0;
}
/* Modal overlay */
#mmgr-install-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 100000;
    background: rgba(0,0,0,0.55);
    align-items: flex-end;
    justify-content: center;
}
#mmgr-install-modal.mmgr-modal-open {
    display: flex;
}
#mmgr-install-modal-box {
    background: #fff;
    border-radius: 16px 16px 0 0;
    padding: 24px 20px 32px;
    width: 100%;
    max-width: 480px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    box-shadow: 0 -4px 24px rgba(0,0,0,0.2);
    animation: mmgrSlideUp 0.25s ease;
}
@keyframes mmgrSlideUp {
    from { transform: translateY(40px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
#mmgr-install-modal-box h3 {
    margin: 0 0 6px;
    font-size: 18px;
    color: #9b51e0;
}
#mmgr-install-modal-box p.mmgr-modal-sub {
    margin: 0 0 20px;
    font-size: 13px;
    color: #666;
}
.mmgr-install-step {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
}
.mmgr-install-step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #9b51e0, #ce00ff);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mmgr-install-step-body {
    flex: 1;
    padding-top: 2px;
}
.mmgr-install-step-body strong {
    display: block;
    font-size: 14px;
    color: #222;
    margin-bottom: 2px;
}
.mmgr-install-step-body span {
    font-size: 13px;
    color: #555;
}
#mmgr-install-modal-close {
    display: block;
    width: 100%;
    margin-top: 20px;
    padding: 12px;
    background: #f0e6ff;
    color: #9b51e0;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}
#mmgr-android-install-btn {
    display: none;
    width: 100%;
    margin-top: 12px;
    padding: 13px;
    background: linear-gradient(135deg, #9b51e0, #ce00ff);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}
</style>

<!-- Banner HTML -->
<div id="mmgr-install-banner" role="button" aria-label="Add this app to your home screen">
    <img id="mmgr-install-banner-icon" src="<?php echo $icon_url; ?>" alt="">
    <div id="mmgr-install-banner-text">
        <strong>📲 Add <?php echo $site_name; ?> to your home screen</strong>
        <span>Get push notifications &amp; quick access</span>
    </div>
    <button id="mmgr-install-banner-cta" type="button">Install</button>
    <button id="mmgr-install-banner-dismiss" type="button" aria-label="Dismiss">✕</button>
</div>

<!-- Instructions Modal -->
<div id="mmgr-install-modal" role="dialog" aria-modal="true" aria-labelledby="mmgr-modal-title">
    <div id="mmgr-install-modal-box">
        <h3 id="mmgr-modal-title">📱 Add to Home Screen</h3>
        <p class="mmgr-modal-sub">Follow these steps to install the app:</p>

        <!-- iOS steps (shown by JS when on iOS) -->
        <div id="mmgr-steps-ios">
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">1</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap the Share button 📤</strong>
                    <span>At the bottom of Safari, tap the Share icon (box with upward arrow)</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">2</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Add to Home Screen"</strong>
                    <span>Scroll down in the share sheet and tap <em>Add to Home Screen</em></span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">3</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Add"</strong>
                    <span>Confirm by tapping <em>Add</em> in the top-right corner</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">4</div>
                <div class="mmgr-install-step-body">
                    <strong>Open the app and allow notifications</strong>
                    <span>Launch from your home screen — you'll be prompted to allow push notifications</span>
                </div>
            </div>
        </div>

        <!-- Android/Chrome steps -->
        <div id="mmgr-steps-android" style="display:none;">
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">1</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Install" below</strong>
                    <span>Your browser will ask you to confirm adding this app to your home screen</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">2</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Install" in the browser prompt</strong>
                    <span>The app icon will be added to your home screen automatically</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">3</div>
                <div class="mmgr-install-step-body">
                    <strong>Allow notifications when asked</strong>
                    <span>This lets us send you a notification whenever you receive a new message</span>
                </div>
            </div>
        </div>

        <!-- Android manual steps (shown when beforeinstallprompt has not fired) -->
        <div id="mmgr-steps-android-manual" style="display:none;">
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">1</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap the Chrome menu ⋮</strong>
                    <span>Tap the three-dot menu in the top-right corner of Chrome</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">2</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Add to Home Screen" or "Install App"</strong>
                    <span>The option may appear as <em>Add to Home screen</em> or <em>Install App</em> depending on your Chrome version</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">3</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Add" or "Install" to confirm</strong>
                    <span>The app icon will be placed on your home screen</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">4</div>
                <div class="mmgr-install-step-body">
                    <strong>Open the app and allow notifications</strong>
                    <span>Launch from your home screen — tap Allow when prompted for push notifications</span>
                </div>
            </div>
        </div>

        <!-- Generic/desktop steps (fallback) -->
        <div id="mmgr-steps-generic" style="display:none;">
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">1</div>
                <div class="mmgr-install-step-body">
                    <strong>Open your browser menu</strong>
                    <span>Tap the three-dot ⋮ or settings menu in your browser</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">2</div>
                <div class="mmgr-install-step-body">
                    <strong>Tap "Install app" or "Add to Home screen"</strong>
                    <span>The exact label depends on your browser</span>
                </div>
            </div>
            <div class="mmgr-install-step">
                <div class="mmgr-install-step-num">3</div>
                <div class="mmgr-install-step-body">
                    <strong>Confirm installation</strong>
                    <span>Follow the on-screen prompts to finish</span>
                </div>
            </div>
        </div>

        <button id="mmgr-android-install-btn" type="button">⬇️ Install App Now</button>
        <button id="mmgr-install-modal-close" type="button">Got it — close</button>
    </div>
</div>

<script>
(function() {
    var DISMISS_KEY    = 'mmgr_install_dismissed';
    var DISMISS_DAYS   = 7;
    var banner         = document.getElementById('mmgr-install-banner');
    var modal          = document.getElementById('mmgr-install-modal');
    var ctaBtn         = document.getElementById('mmgr-install-banner-cta');
    var dismissBtn     = document.getElementById('mmgr-install-banner-dismiss');
    var closeBtn       = document.getElementById('mmgr-install-modal-close');
    var androidInstall = document.getElementById('mmgr-android-install-btn');
    var deferredPrompt = null;

    // Don't show if already running as standalone (installed)
    if (window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true) {
        return;
    }

    // Don't show if recently dismissed
    var dismissed = localStorage.getItem(DISMISS_KEY);
    if (dismissed && Date.now() < parseInt(dismissed, 10)) {
        return;
    }

    // Detect platform
    var ua       = navigator.userAgent || '';
    var isIOS    = /iphone|ipad|ipod/i.test(ua);
    var isSafari = /safari/i.test(ua) && !/chrome|crios|fxios/i.test(ua);
    var isAndroid       = /android/i.test(ua);
    var isAndroidChrome = isAndroid && /chrome/i.test(ua) && !/opr/i.test(ua);

    // Current platform — set by showBanner(), read by modal handlers
    var activePlatform = 'generic';

    // For iOS Safari: show banner immediately (no beforeinstallprompt)
    if (isIOS && isSafari) {
        showBanner('ios');
    } else if (isAndroid) {
        // Show manual Android instructions immediately (like iOS).
        // If Chrome fires beforeinstallprompt, the native "Install App Now"
        // button will also be made available when the modal is opened.
        showBanner('android-manual');

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            // deferredPrompt being set is enough; openModal() will show the
            // native "Install App Now" button alongside the manual steps.
        });

        window.addEventListener('appinstalled', function() {
            hideBanner();
        });
    } else {
        // For browsers that fire beforeinstallprompt (Chrome/Edge on desktop)
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            showBanner('generic');
        });

        // Capture successful install
        window.addEventListener('appinstalled', function() {
            hideBanner();
        });
    }

    // Wire up banner/CTA clicks once (not inside showBanner)
    ctaBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        openModal(activePlatform);
    });
    banner.addEventListener('click', function() {
        openModal(activePlatform);
    });

    function showBanner(platform) {
        activePlatform = platform;
        banner.classList.add('mmgr-banner-visible');

        // Add top-padding to body so fixed banner doesn't cover content
        var bannerH = banner.offsetHeight || 58;
        var currentPad = parseFloat(window.getComputedStyle(document.body).paddingTop) || 0;
        document.body.style.paddingTop = (currentPad + bannerH) + 'px';
    }

    function hideBanner() {
        if (!banner.classList.contains('mmgr-banner-visible')) return;
        var bannerH = banner.offsetHeight || 58;
        banner.classList.remove('mmgr-banner-visible');
        var currentPad = parseFloat(window.getComputedStyle(document.body).paddingTop) || 0;
        document.body.style.paddingTop = Math.max(0, currentPad - bannerH) + 'px';
    }

    function openModal(platform) {
        // Show correct steps
        document.getElementById('mmgr-steps-ios').style.display            = 'none';
        document.getElementById('mmgr-steps-android').style.display        = 'none';
        document.getElementById('mmgr-steps-android-manual').style.display = 'none';
        document.getElementById('mmgr-steps-generic').style.display        = 'none';

        if (platform === 'ios') {
            document.getElementById('mmgr-steps-ios').style.display = 'block';
        } else if (platform === 'android') {
            document.getElementById('mmgr-steps-android').style.display = 'block';
            if (deferredPrompt) androidInstall.style.display = 'block';
        } else if (platform === 'android-manual') {
            document.getElementById('mmgr-steps-android-manual').style.display = 'block';
            if (deferredPrompt) androidInstall.style.display = 'block';
        } else {
            document.getElementById('mmgr-steps-generic').style.display = 'block';
            if (deferredPrompt) androidInstall.style.display = 'block';
        }

        modal.classList.add('mmgr-modal-open');
    }

    function closeModal() {
        modal.classList.remove('mmgr-modal-open');
    }

    // Dismiss banner
    dismissBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        hideBanner();
        localStorage.setItem(DISMISS_KEY, Date.now() + DISMISS_DAYS * 86400000);
    });

    // Close modal
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Android native install button
    androidInstall.addEventListener('click', function() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(result) {
            deferredPrompt = null;
            androidInstall.style.display = 'none';
            if (result.outcome === 'accepted') {
                closeModal();
                hideBanner();
            }
        });
    });
}());
</script>
    <?php
}



add_action('wp_ajax_mmgr_save_push_subscription',        'mmgr_ajax_save_push_subscription');
add_action('wp_ajax_nopriv_mmgr_save_push_subscription', 'mmgr_ajax_save_push_subscription');
function mmgr_ajax_save_push_subscription() {
    check_ajax_referer('mmgr_save_push_subscription', 'nonce');

    $member = mmgr_get_current_member();
    if (!$member) {
        wp_send_json_error(['message' => 'Not logged in']);
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
    $table = $wpdb->prefix . 'mmgr_push_subscriptions';

    // Remove any existing record for this endpoint, then insert fresh
    $wpdb->delete($table, ['endpoint' => $endpoint], ['%s']);
    $result = $wpdb->insert($table, [
        'member_id'  => intval($member['id']),
        'endpoint'   => $endpoint,
        'p256dh'     => $p256dh,
        'auth'       => $auth,
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%s', '%s']);

    if ($result) {
        wp_send_json_success(['message' => 'Subscription saved']);
    } else {
        wp_send_json_error(['message' => 'DB error saving subscription']);
    }
}

// ---------------------------------------------------------------------------
// VAPID key management
// ---------------------------------------------------------------------------

/**
 * Return existing VAPID keys (public base64url + private PEM).
 * Auto-generates on first call.
 */
function mmgr_pwa_get_vapid_keys(): array {
    $keys = get_option('mmgr_vapid_keys', []);
    if (!empty($keys['public']) && !empty($keys['private_pem'])) {
        return $keys;
    }
    return mmgr_pwa_generate_vapid_keys();
}

/**
 * Generate a P-256 VAPID key pair and persist it.
 */
function mmgr_pwa_generate_vapid_keys(): array {
    if (!function_exists('openssl_pkey_new')) {
        return [];
    }

    $key = openssl_pkey_new([
        'curve_name'        => 'prime256v1',
        'private_key_type'  => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$key) return [];

    $details = openssl_pkey_get_details($key);
    if (!$details || empty($details['ec']['x'])) return [];

    // Uncompressed public key: 0x04 || x || y (65 bytes)
    $pub_bytes = "\x04" . $details['ec']['x'] . $details['ec']['y'];

    openssl_pkey_export($key, $priv_pem);

    $keys = [
        'public'      => mmgr_pwa_base64url($pub_bytes),
        'private_pem' => $priv_pem,
    ];
    update_option('mmgr_vapid_keys', $keys);
    return $keys;
}

// ---------------------------------------------------------------------------
// Send push notification to a member
// ---------------------------------------------------------------------------

/**
 * Dispatch a Web Push notification to all subscriptions of $member_id.
 * Called automatically from mmgr_send_message().
 */
function mmgr_pwa_send_push_to_member(int $member_id, string $title, string $body, string $url = ''): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mmgr_push_subscriptions';

    $subs = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE member_id = %d", $member_id),
        ARRAY_A
    );
    if (empty($subs)) return;

    $vapid = mmgr_pwa_get_vapid_keys();
    if (empty($vapid)) return;

    $payload = wp_json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url ?: home_url('/member-messages/'),
        'tag'   => 'mmgr-msg-' . $member_id,
    ]);

    foreach ($subs as $sub) {
        $http_status = mmgr_pwa_send_web_push(
            $sub['endpoint'],
            $sub['p256dh'],
            $sub['auth'],
            $payload,
            $vapid
        );

        // Push service reports the subscription is gone → remove it
        if ($http_status === 404 || $http_status === 410) {
            $wpdb->delete($table, ['id' => intval($sub['id'])], ['%d']);
        }
    }
}

// ---------------------------------------------------------------------------
// Web Push: HTTP request
// ---------------------------------------------------------------------------

/**
 * Build + send an encrypted Web Push message.
 * Returns the HTTP status code from the push service, or 0 on local failure.
 */
function mmgr_pwa_send_web_push(string $endpoint, string $p256dh, string $auth, string $payload, array $vapid): int {
    $encrypted = mmgr_pwa_encrypt_payload($payload, $p256dh, $auth);
    if (!$encrypted) return 0;

    $parsed   = wp_parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    $jwt = mmgr_pwa_build_vapid_jwt($audience, $vapid);
    if (!$jwt) return 0;

    $response = wp_remote_post($endpoint, [
        'timeout' => 10,
        'headers' => [
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid['public'],
            'TTL'              => '86400',
        ],
        'body' => $encrypted,
    ]);

    if (is_wp_error($response)) return 0;
    return intval(wp_remote_retrieve_response_code($response));
}

// ---------------------------------------------------------------------------
// RFC 8291 payload encryption (aes128gcm)
// ---------------------------------------------------------------------------

/**
 * Encrypt a push payload per RFC 8291 §3.
 * Returns the encrypted bytes or null on failure.
 */
function mmgr_pwa_encrypt_payload(string $plaintext, string $p256dh, string $auth): ?string {
    if (!function_exists('openssl_pkey_new') || !function_exists('openssl_pkey_derive')) {
        return null;
    }

    // Decode client subscription keys (base64url → raw bytes)
    $client_pub = mmgr_pwa_base64url_decode($p256dh);
    $auth_secret = mmgr_pwa_base64url_decode($auth);

    if (strlen($client_pub) !== 65 || strlen($auth_secret) < 16) {
        return null;
    }

    // Random salt + ephemeral server ECDH key pair
    $salt = random_bytes(16);

    $server_key = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$server_key) return null;

    $sd = openssl_pkey_get_details($server_key);
    $server_pub = "\x04" . $sd['ec']['x'] . $sd['ec']['y']; // 65 bytes

    // ECDH shared secret
    $client_pem = mmgr_pwa_p256_bytes_to_pem($client_pub);
    $client_key = openssl_pkey_get_public($client_pem);
    if (!$client_key) return null;

    $ecdh_secret = openssl_pkey_derive($client_key, $server_key, 32);
    if (!$ecdh_secret || strlen($ecdh_secret) !== 32) return null;

    // RFC 8291 §3.4 key derivation
    // IKM = HKDF(salt=auth_secret, ikm=ecdh_secret, info="WebPush: info\0"||ua_pub||as_pub, len=32)
    $ikm_info = "WebPush: info\x00" . $client_pub . $server_pub;
    $ikm      = mmgr_pwa_hkdf($auth_secret, $ecdh_secret, $ikm_info, 32);

    $cek   = mmgr_pwa_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = mmgr_pwa_hkdf($salt, $ikm, "Content-Encoding: nonce\x00",      12);

    // Encrypt: AES-128-GCM with delimiter byte (0x02 = record padding)
    $tag        = '';
    $ciphertext = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) return null;

    // RFC 8188 header: salt(16) || rs(4 BE uint32) || idlen(1) || keyid(idlen)
    $header = $salt . pack('N', 4096) . chr(strlen($server_pub)) . $server_pub;

    return $header . $ciphertext . $tag;
}

// ---------------------------------------------------------------------------
// RFC 8292 VAPID JWT (ES256)
// ---------------------------------------------------------------------------

/**
 * Build the signed VAPID JWT used as the Authorization header.
 */
function mmgr_pwa_build_vapid_jwt(string $audience, array $vapid): string {
    if (empty($vapid['private_pem'])) return '';

    $header  = mmgr_pwa_base64url(wp_json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = mmgr_pwa_base64url(wp_json_encode([
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => 'mailto:' . sanitize_email(get_option('admin_email', 'admin@example.com')),
    ]));

    $signing_input = $header . '.' . $payload;

    $priv_key = openssl_pkey_get_private($vapid['private_pem']);
    if (!$priv_key) return '';

    $sig_der = '';
    if (!openssl_sign($signing_input, $sig_der, $priv_key, OPENSSL_ALGO_SHA256)) {
        return '';
    }

    // ECDSA signature: DER SEQUENCE → raw R||S (64 bytes for P-256)
    $sig_raw = mmgr_pwa_ecdsa_der_to_raw($sig_der);
    if (!$sig_raw) return '';

    return $signing_input . '.' . mmgr_pwa_base64url($sig_raw);
}

/**
 * Convert an OpenSSL DER-encoded ECDSA signature to the raw R||S form
 * expected by the JWT ES256 standard.
 */
function mmgr_pwa_ecdsa_der_to_raw(string $der): string {
    $offset = 0;
    if (strlen($der) < 8) return '';
    if (ord($der[$offset++]) !== 0x30) return ''; // SEQUENCE
    $offset++;                                     // skip total length

    // R
    if (ord($der[$offset++]) !== 0x02) return ''; // INTEGER
    $r_len = ord($der[$offset++]);
    $r     = substr($der, $offset, $r_len);
    $offset += $r_len;

    // S
    if ($offset >= strlen($der) || ord($der[$offset++]) !== 0x02) return '';
    $s_len = ord($der[$offset++]);
    $s     = substr($der, $offset, $s_len);

    // Strip leading sign-extension byte, then pad to 32 bytes
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * HKDF Extract-then-Expand (RFC 5869) using SHA-256.
 */
function mmgr_pwa_hkdf(string $salt, string $ikm, string $info, int $length): string {
    $prk = hash_hmac('sha256', $ikm, $salt, true); // Extract
    $t   = '';
    $okm = '';
    $i   = 1;
    while (strlen($okm) < $length) {
        $t    = hash_hmac('sha256', $t . $info . chr($i++), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}

/**
 * Convert 65-byte uncompressed P-256 public key to PEM SubjectPublicKeyInfo.
 */
function mmgr_pwa_p256_bytes_to_pem(string $key_bytes): string {
    // DER prefix for EC P-256 SubjectPublicKeyInfo (RFC 5480)
    $prefix = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
            . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    $der    = $prefix . $key_bytes;
    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----\n";
}

/** Base64url encode (no padding). */
function mmgr_pwa_base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** Base64url decode (handles missing padding). */
function mmgr_pwa_base64url_decode(string $b64url): string {
    $pad = str_repeat('=', (4 - strlen($b64url) % 4) % 4);
    return base64_decode(strtr($b64url . $pad, '-_', '+/'));
}
