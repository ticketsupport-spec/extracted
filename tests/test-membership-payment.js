'use strict';
// Run with: node tests/test-membership-payment.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '../membership-manager-qr/includes/admin/add-edit-member.php'), 'utf8');
const code = source.match(/document.addEventListener\('DOMContentLoaded', function\(\) \{[\s\S]*?\n\}\);/)[0];
let passed = 0;
function expect(condition, description) {
    assert.ok(condition, description);
    passed++;
    console.log('PASS: ' + description);
}
function form(checked, value) {
    const paid = {checked, addEventListener(event, callback) { this.change = callback; }};
    const operation = {value};
    vm.runInNewContext(code, {
        document: {
            addEventListener(event, callback) { callback(); },
            getElementById(id) { return id === 'paid' ? paid : id === 'membership_operation' ? operation : null; }
        }
    });
    return {paid, operation};
}

for (const initiallyPaid of [false, true]) {
    const {paid, operation} = form(initiallyPaid, '');
    expect(operation.value === '', 'Loading a form does not record a payment or renew an existing membership');
    paid.checked = true;
    paid.change();
    expect(operation.value === 'paid', 'Mark as paid defaults to a new paid membership / renewal');
    paid.checked = false;
    paid.change();
    expect(operation.value === '', 'Unchecking paid cancels the automatic paid operation');
}
for (const value of ['comp_1', 'comp_12']) {
    const {paid, operation} = form(false, value);
    paid.checked = true;
    paid.change();
    expect(operation.value === value, 'Explicit complimentary grants remain available: ' + value);
}
console.log(`Results: ${passed} passed`);
