'use strict';

/**
 * Tests for mmgrUnescapeAlias – the client-side helper that removes
 * unnecessary backslash escape sequences from community aliases before
 * they are rendered to the user.
 *
 * Run with: node tests/test-alias-unescape.js
 */

// ---------------------------------------------------------------------------
// Inline implementation (mirrors the function in member-portal-shortcodes.php)
// ---------------------------------------------------------------------------
function mmgrUnescapeAlias(str) {
    return String(str).replace(/\\([\\'"])/g, '$1');
}

// ---------------------------------------------------------------------------
// Minimal test harness
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

// ---------------------------------------------------------------------------
// Test cases
// ---------------------------------------------------------------------------
console.log('\nmmgrUnescapeAlias tests\n');

assert(
    "escaped apostrophe: we\\'re → we're",
    mmgrUnescapeAlias("we\\'re"),
    "we're"
);

assert(
    "alias with no escapes is unchanged",
    mmgrUnescapeAlias("AzzWhooper"),
    "AzzWhooper"
);

assert(
    "escaped double-quote is unescaped",
    mmgrUnescapeAlias('say \\"hello\\"'),
    'say "hello"'
);

assert(
    "escaped backslash is reduced to a single backslash",
    mmgrUnescapeAlias("path\\\\file"),
    "path\\file"
);

assert(
    "escaped backslash followed by apostrophe: \\\\'  → backslash + apostrophe",
    mmgrUnescapeAlias("\\\\'"),
    "\\'"
);

assert(
    "multiple escaped apostrophes in one string",
    mmgrUnescapeAlias("it\\'s we\\'re they\\'re"),
    "it's we're they're"
);

assert(
    "empty string returns empty string",
    mmgrUnescapeAlias(""),
    ""
);

assert(
    "non-string number is coerced to string",
    mmgrUnescapeAlias(42),
    "42"
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
console.log('\nResults:', passed, 'passed,', failed, 'failed\n');
if (failed > 0) {
    process.exit(1);
}
