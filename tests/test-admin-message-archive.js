'use strict';
// Run with: node tests/test-admin-message-archive.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '../membership-manager-qr/includes/admin-messages.php'), 'utf8');
const code = source.match(/function archiveAllAdminConversations\(button\) \{[\s\S]*?\n\}/)[0]
    .replace(/<\?php echo esc_url\(admin_url\('([^']+)'\)\); \?>/g, 'https://example.com/wp-admin/$1');
let passed = 0;
function expect(condition, description) {
    assert.ok(condition, description);
    passed++;
    console.log('PASS: ' + description);
}
async function run(options = {}) {
    const requests = [];
    const alerts = [];
    const button = {disabled: false, getAttribute: () => 'nonce&value'};
    const context = {
        confirm: () => options.confirm !== false,
        alert: message => alerts.push(message),
        window: {location: {href: ''}},
        fetch: (url, request) => {
            requests.push({url, ...request});
            return options.reject ? Promise.reject(new Error('Offline')) :
                Promise.resolve({json: () => options.invalidJson ? Promise.reject(new Error('Invalid JSON')) :
                    Promise.resolve(options.response || {success: true})});
        }
    };
    vm.runInNewContext(code, context);
    context.archiveAllAdminConversations(button);
    const disabledWhilePending = button.disabled;
    await new Promise(resolve => setImmediate(resolve));
    return {requests, alerts, button, disabledWhilePending, href: context.window.location.href};
}

(async () => {
    let result = await run({confirm: false});
    expect(result.requests.length === 0 && !result.button.disabled, 'Cancellation sends no request');
    result = await run();
    expect(result.disabledWhilePending, 'Disables button while archiving');
    expect(result.requests.length === 1 && result.requests[0].method === 'POST', 'Posts one bulk request');
    expect(result.requests[0].url === 'https://example.com/wp-admin/admin-ajax.php', 'Uses WordPress AJAX URL');
    expect(result.requests[0].body === 'action=mmgr_admin_archive_all_conversations&nonce=nonce%26value', 'Submits bulk action and encoded nonce');
    expect(result.href === 'https://example.com/wp-admin/admin.php?page=membership_messages&tab=archived', 'Success opens Archived tab');
    result = await run({response: {success: false, data: {message: 'Database unavailable'}}});
    expect(!result.button.disabled && result.alerts[0].includes('Database unavailable'), 'Server error is displayed and allows retry');
    expect(result.href === '', 'Server failure does not redirect');
    for (const failure of [{reject: true}, {invalidJson: true}]) {
        result = await run(failure);
        expect(!result.button.disabled && result.alerts[0].includes('try again'), 'Network or JSON failure allows retry');
        expect(result.href === '', 'Transport failure does not redirect');
    }
    console.log(`Results: ${passed} passed`);
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
