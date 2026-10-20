'use strict';
// Run with: node tests/test-admin-message-read-receipts.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '../membership-manager-qr/includes/admin-messages.php'), 'utf8');
const code = source.match(/function adminLoadMoreMessages\(\) \{[\s\S]*?\n\}/)[0]
    .replace(/<\?php[\s\S]*?\?>/g, 'test');
let passed = 0;
function expect(condition, description) {
    assert.ok(condition, description);
    passed++;
    console.log('PASS: ' + description);
}
function element() {
    return {
        children: [], style: {},
        firstElementChild: {children: [], appendChild(child) { this.children.push(child); }},
        setAttribute() {},
        appendChild(child) { this.children.push(child); },
        insertBefore(fragment) { this.children.unshift(...fragment.children); }
    };
}
const list = element();
const button = element();
const container = element();
const messages = [
    {id: 1, from_member_id: 0, message: 'Unread message', sent_at: 'Oct 19', read_status: 'Unread'},
    {id: 2, from_member_id: '0', message: 'Read message', sent_at: 'Oct 19', read_status: 'Read on 20/10/2026 18:45'},
    {id: 3, from_member_id: 7, message: 'Incoming', sent_at: 'Oct 19', read_status: ''},
    {id: 4, from_member_id: 0, message: '', image_url: 'https://example.com/photo.jpg', sent_at: 'Oct 19',
        read_status: 'Read on <img src=x onerror=alert(1)>'}
];
const context = {
    adminMemberId: 7, adminMessageOffset: 10,
    FormData: class { append() {} },
    document: {
        getElementById: id => id === 'admin-load-more-btn' ? button :
            id === 'admin-messages-list' ? list : container,
        createElement: element, createDocumentFragment: element
    },
    fetch: () => Promise.resolve({json: () => Promise.resolve({success: true, data: {messages, count: messages.length}})}),
    alert: () => { throw new Error('Unexpected prompt'); }
};
vm.runInNewContext(code, context);
context.adminLoadMoreMessages();
setImmediate(() => {
    try {
        expect(list.children.length === 4, 'Earlier messages are still prepended');
        const statuses = list.children.map(bubble => bubble.firstElementChild.children);
        expect(statuses[0][0].textContent === 'Unread', 'Earlier unread outgoing messages show Unread');
        expect(statuses[1][0].textContent === 'Read on 20/10/2026 18:45', 'Earlier read messages show read date and time');
        expect(statuses[2].length === 0, 'Incoming messages do not get receipts');
        expect(statuses[3][0].textContent === messages[3].read_status && statuses[3][0].innerHTML === undefined,
            'Image-only messages show receipts using safe text rather than HTML');
        expect(context.adminMessageOffset === 14 && !button.disabled, 'Pagination offset and button behavior are preserved');
        console.log(`Results: ${passed} passed`);
    } catch (error) {
        console.error(error);
        process.exitCode = 1;
    }
});
