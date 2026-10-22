'use strict';
// Run with: node tests/test-registration-dates.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync(path.join(__dirname, '../membership-manager-qr/includes/shortcodes.php'), 'utf8');
const registration = source.slice(0, source.indexOf('// Check-in Shortcode'));
const script = registration.match(/<script>\s*(\(function\(\) \{[\s\S]*?\}\)\(\);)\s*<\/script>/)[1];
const styles = fs.readFileSync(path.join(__dirname, '../membership-manager-qr/includes/styles.php'), 'utf8');
let passed = 0;
function expect(condition, description) {
    assert.ok(condition, description);
    passed++;
    console.log('PASS: ' + description);
}
function dateField(showPicker) {
    return {
        showPicker,
        handlers: {},
        addEventListener(type, callback) { this.handlers[type] = callback; }
    };
}

let primaryOpens = 0;
let partnerOpens = 0;
const primary = dateField(function() { primaryOpens++; });
const partner = dateField(function() { partnerOpens++; });
const unsupported = dateField(undefined);
const blocked = dateField(function() { throw new Error('Picker blocked'); });
vm.runInNewContext(script, {
    document: {
        currentScript: {
            parentElement: {
                querySelectorAll(selector) {
                    assert.strictEqual(selector, 'input[type="date"]');
                    return [primary, partner, unsupported, blocked];
                }
            }
        }
    }
});
expect(primaryOpens === 0 && partnerOpens === 0, 'Calendars do not open before user interaction');
primary.handlers.click();
partner.handlers.click();
expect(primaryOpens === 1, 'Clicking the primary DOB field opens its calendar');
expect(partnerOpens === 1, 'Clicking the partner DOB field opens its calendar');
assert.doesNotThrow(() => unsupported.handlers.click());
expect(true, 'Browsers without showPicker keep native date entry');
assert.doesNotThrow(() => blocked.handlers.click());
expect(true, 'Blocked picker requests do not break date entry');
expect(/type="date" name="dob" required max=/.test(registration), 'Primary DOB remains required with its age limit');
expect(/type="date" name="partner_dob" max=/.test(registration), 'Partner DOB retains its age limit');
expect(/\.mmgr-registration-form \.mmgr-field input\[type="date"\]\s*\{[^}]*color-scheme: dark;/.test(styles),
    'Registration date controls use a dark color scheme');
expect(/\.mmgr-registration-form \.mmgr-field input\[type="date"\]::-webkit-calendar-picker-indicator\s*\{[^}]*filter: brightness\(0\) invert\(1\);[^}]*opacity: 1;/.test(styles),
    'Registration calendar icons are white and fully visible');
console.log(`Results: ${passed} passed`);
