'use strict';

// Run with: node tests/test-checkin-notes.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(__dirname + '/../membership-manager-qr/includes/shortcodes.php', 'utf8');
let passed = 0;
function expect(condition, description) {
    assert.ok(condition, description);
    passed++;
    console.log('PASS: ' + description);
}

class Element {
    constructor(tag) {
        this.tag = tag;
        this.children = [];
        this.style = {};
        this.listeners = {};
        this.hidden = false;
        this.disabled = false;
    }
    append(...children) { children.forEach(child => this.appendChild(child)); }
    appendChild(child) { child.parent = this; this.children.push(child); }
    replaceChildren() { this.children = []; }
    remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    setAttribute(name, value) { this[name] = value; }
    addEventListener(name, handler) { this.listeners[name] = handler; }
    focus() { this.focused = true; }
    close() { this.open = false; }
    querySelectorAll() {
        return this.children.flatMap(child => [
            ...(['button', 'textarea'].includes(child.tag) ? [child] : []),
            ...child.querySelectorAll()
        ]);
    }
    click() { if (!this.disabled) this.listeners.click(); }
}

const dialog = new Element('dialog');
const container = new Element('div');
const status = new Element('p');
dialog.append(container, status);
const elements = {
    'mmgr-admin-notes-dialog': dialog,
    'mmgr-admin-notes-text': container,
    'mmgr-admin-notes-status': status
};
const requests = [];
let confirmDelete = true;
let nextResponse = { success: true, data: { note: 'Saved text', message: 'Note saved.' } };
let networkError = false;
const context = vm.createContext({
    document: {
        createElement: tag => new Element(tag),
        getElementById: id => elements[id]
    },
    FormData,
    confirm: () => confirmDelete,
    fetch: async (url, options) => {
        requests.push(options.body);
        if (networkError) throw new Error('Network unavailable');
        return { json: async () => nextResponse };
    }
});
const start = source.indexOf('    function mmgrRenderCheckinNotes(');
const end = source.indexOf('    // ── Helpers', start);
assert.ok(start > 0 && end > start);
vm.runInContext(`
    const mmgrAjaxUrl = '/ajax';
    const mmgrNoteNonce = 'test-nonce';
    let mmgrNotePending = false;
    ${source.slice(start, end)}
`, context);
const notes = [
    { id: 12, member_id: 7, created_at: '2026-10-19', note: '<script>unsafe</script>\nLine two' },
    { id: 13, member_id: 7, created_at: '2026-10-18', note: 'Second note' }
];
function render() { dialog.open = true; context.mmgrRenderCheckinNotes(notes); }
function parts(index = 0) {
    const [text, editor, controls] = container.children[index].children;
    const [edit, remove, save, cancel] = controls.children;
    return { text, editor, edit, remove, save, cancel };
}
const settle = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    render();
    expect(container.children.length === 2, 'Each note has its own controls');
    let p = parts();
    expect(p.text.textContent.includes('<script>unsafe</script>'), 'Note contents are rendered as literal text');
    expect(p.edit.textContent === 'EDIT NOTE' && p.remove.textContent === 'DELETE NOTE', 'Requested action labels appear');
    expect(source.includes('>REMIND ME NEXT TIME</button>') && source.includes('<form method="dialog">'), 'Reminder dismisses the popup without a write');
    p.edit.click();
    expect(!p.editor.hidden && p.text.hidden && p.editor.focused, 'Edit opens a focused multiline editor');
    p.editor.value = 'Discard this';
    p.cancel.click();
    expect(p.editor.hidden && p.editor.value === notes[0].note && requests.length === 0, 'Cancel preserves the original note without a request');
    p.edit.click();
    p.editor.value = 'New <b>text</b>';
    p.save.click();
    expect(p.save.disabled && p.editor.disabled, 'Actions are disabled during a save');
    let prevented = false;
    dialog.listeners.cancel({ preventDefault() { prevented = true; } });
    expect(prevented, 'Escape cannot dismiss an in-flight mutation');
    await settle();
    expect(requests[0].get('action') === 'mmgr_checkin_note' && requests[0].get('nonce') === 'test-nonce', 'Save sends the secured note action');
    expect(requests[0].get('note_id') === '12' && requests[0].get('member_id') === '7', 'Save targets only the selected note and member');
    expect(notes[0].note === 'Saved text' && p.editor.hidden && !p.edit.disabled, 'Save displays the canonical server text and restores controls');
    expect(container.children.length === 2 && dialog.open, 'Editing preserves other notes and the check-in popup');

    nextResponse = { success: false, data: { message: 'Please enter a note.' } };
    p.edit.click();
    p.editor.value = '';
    p.save.click();
    await settle();
    expect(status.textContent === 'Please enter a note.' && !p.editor.hidden && !p.save.disabled, 'Server errors keep the editor open for retry');
    networkError = true;
    p.save.click();
    await settle();
    expect(status.textContent === 'Network unavailable' && !p.save.disabled, 'Network errors restore usable controls');
    networkError = false;
    confirmDelete = false;
    const before = requests.length;
    p.remove.click();
    expect(requests.length === before && container.children.length === 2, 'Cancelled deletion leaves notes unchanged');
    confirmDelete = true;
    p.remove.click();
    await settle();
    expect(container.children.length === 2, 'Failed deletion does not remove a note');
    nextResponse = { success: true, data: { message: 'Note deleted.' } };
    p.remove.click();
    await settle();
    expect(container.children.length === 1 && dialog.open, 'Deleting one note preserves remaining notes');
    expect(requests.at(-1).get('operation') === 'delete', 'Delete uses the delete operation');
    parts().remove.click();
    await settle();
    expect(container.children.length === 0 && !dialog.open, 'Deleting the final note closes the popup');
    render();
    p = parts();
    expect(container.children.length === 2 && p.editor.hidden, 'A new check-in resets the popup state');
    console.log(`\nResults: ${passed} passed, 0 failed`);
})().catch(error => { console.error(error); process.exitCode = 1; });
