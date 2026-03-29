'use strict';

/**
 * Tests for admin PWA push notification helpers –
 * specifically the payload-building logic and subscription-staleness detection
 * that mirrors the PHP in admin-pwa.php.
 *
 * Run with: node tests/test-admin-pwa.js
 */

// ---------------------------------------------------------------------------
// Minimal test harness (same pattern as test-alias-unescape.js)
// ---------------------------------------------------------------------------
let passed = 0;
let failed = 0;

function assert(description, actual, expected) {
    if (actual === expected) {
        console.log('  PASS:', description);
        passed++;
    } else {
        console.error('  FAIL:', description);
        console.error('       expected:', JSON.stringify(expected));
        console.error('       actual  :', JSON.stringify(actual));
        failed++;
    }
}

function assertDeep(description, actual, expected) {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a === e) {
        console.log('  PASS:', description);
        passed++;
    } else {
        console.error('  FAIL:', description);
        console.error('       expected:', e);
        console.error('       actual  :', a);
        failed++;
    }
}

// ---------------------------------------------------------------------------
// Inline mirrors of JS logic used by admin-pwa.php
// ---------------------------------------------------------------------------

/**
 * Build an admin push payload object (mirrors wp_json_encode call in
 * mmgr_admin_pwa_send_push_to_admins).
 */
function buildAdminPushPayload(title, body, url, tag) {
    return {
        title: title,
        body:  body,
        url:   url   || 'https://example.com/wp-admin/admin.php?page=membership_messages',
        tag:   tag   || 'mmgr-admin-msg',
    };
}

/**
 * Decide whether a push subscription is stale based on the HTTP status code
 * returned by the push service (mirrors logic in mmgr_admin_pwa_send_push_to_admins).
 */
function isStaleSubscription(httpStatus) {
    return httpStatus === 404 || httpStatus === 410;
}

/**
 * Sanitise a subscription endpoint URL (mirrors esc_url_raw behaviour: reject
 * obviously invalid values while leaving valid HTTPS URLs untouched).
 */
function sanitiseEndpoint(raw) {
    if (typeof raw !== 'string') return '';
    raw = raw.trim();
    if (!raw.startsWith('https://')) return '';
    // Reject strings containing null bytes or newlines
    if (/[\x00\n\r]/.test(raw)) return '';
    return raw;
}

/**
 * Validate admin push subscription structure (mirrors JSON validation in
 * mmgr_ajax_save_admin_push_subscription).
 */
function validateAdminSubscription(sub) {
    if (!sub || typeof sub !== 'object') return false;
    if (typeof sub.endpoint !== 'string' || !sub.endpoint) return false;
    if (!sub.keys || typeof sub.keys !== 'object') return false;
    if (typeof sub.keys.p256dh !== 'string' || !sub.keys.p256dh) return false;
    if (typeof sub.keys.auth !== 'string' || !sub.keys.auth) return false;
    return true;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------
console.log('\nAdmin PWA – push payload tests\n');

assertDeep(
    'buildAdminPushPayload returns expected fields',
    buildAdminPushPayload('New message from Alice', 'Hello admin', 'https://example.com/wp-admin/', 'mmgr-admin-msg'),
    {
        title: 'New message from Alice',
        body:  'Hello admin',
        url:   'https://example.com/wp-admin/',
        tag:   'mmgr-admin-msg',
    }
);

assertDeep(
    'buildAdminPushPayload uses defaults when url and tag are omitted',
    buildAdminPushPayload('Test', 'Body', '', ''),
    {
        title: 'Test',
        body:  'Body',
        url:   'https://example.com/wp-admin/admin.php?page=membership_messages',
        tag:   'mmgr-admin-msg',
    }
);

console.log('\nAdmin PWA – stale subscription detection\n');

assert('HTTP 404 is stale',    isStaleSubscription(404), true);
assert('HTTP 410 is stale',    isStaleSubscription(410), true);
assert('HTTP 201 is not stale', isStaleSubscription(201), false);
assert('HTTP 200 is not stale', isStaleSubscription(200), false);
assert('HTTP 0 is not stale',   isStaleSubscription(0),   false);

console.log('\nAdmin PWA – endpoint sanitisation\n');

assert(
    'valid HTTPS endpoint passes through',
    sanitiseEndpoint('https://fcm.googleapis.com/fcm/send/abc123'),
    'https://fcm.googleapis.com/fcm/send/abc123'
);
assert(
    'HTTP endpoint is rejected',
    sanitiseEndpoint('http://fcm.googleapis.com/fcm/send/abc123'),
    ''
);
assert(
    'empty string is rejected',
    sanitiseEndpoint(''),
    ''
);
assert(
    'non-string is rejected',
    sanitiseEndpoint(null),
    ''
);
assert(
    'endpoint with newline is rejected',
    sanitiseEndpoint('https://example.com/push\nX-Injected: header'),
    ''
);

console.log('\nAdmin PWA – subscription validation\n');

assert(
    'valid subscription passes',
    validateAdminSubscription({
        endpoint: 'https://fcm.googleapis.com/send/xyz',
        keys: { p256dh: 'AABBCC', auth: 'DDEEFF' },
    }),
    true
);
assert(
    'subscription without endpoint fails',
    validateAdminSubscription({
        keys: { p256dh: 'AABBCC', auth: 'DDEEFF' },
    }),
    false
);
assert(
    'subscription without keys fails',
    validateAdminSubscription({
        endpoint: 'https://fcm.googleapis.com/send/xyz',
    }),
    false
);
assert(
    'subscription with empty p256dh fails',
    validateAdminSubscription({
        endpoint: 'https://fcm.googleapis.com/send/xyz',
        keys: { p256dh: '', auth: 'DDEEFF' },
    }),
    false
);
assert(
    'null subscription fails',
    validateAdminSubscription(null),
    false
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
console.log('\nResults:', passed, 'passed,', failed, 'failed\n');
if (failed > 0) {
    process.exit(1);
}
