/**
 * Lightweight regression checks for marketing linkify.
 * Run: node tests/js/internalPathLinks.spec.mjs
 */
import assert from 'node:assert/strict';
import { linkifyInternalPaths } from '../../resources/js/utils/internalPathLinks.js';

function isBrokenByHomeLabel(segs) {
    return segs.some((s, i) => (
        s.type === 'text'
        && /https?:$/.test(String(s.text || '').trim())
        && segs[i + 1]?.type === 'link'
        && segs[i + 1]?.href === '/'
    ));
}

const linkedin = linkifyInternalPaths(
    'Contact: https://www.linkedin.com/in/dev-muhib · Product trial: https://app.wpsalehub.com/pricing',
    true,
);
assert.equal(isBrokenByHomeLabel(linkedin), false);
assert.ok(linkedin.some((s) => s.external && s.href === 'https://www.linkedin.com/in/dev-muhib' && s.label === 'LinkedIn'));
assert.ok(linkedin.some((s) => s.external && s.href === 'https://app.wpsalehub.com/pricing'));
assert.equal(linkedin.some((s) => s.type === 'link' && s.href === '/' && !s.external), false);

const mixed = linkifyInternalPaths('Read /about or /en · home /', true);
assert.ok(mixed.some((s) => s.href === '/about' && s.label === 'Bangla About'));
assert.ok(mixed.some((s) => s.href === '/en' && s.label === 'English home'));
assert.ok(mixed.some((s) => s.href === '/' && s.label === 'Bangla home'));

const bn = linkifyInternalPaths('হোম: / · প্রাইসিং: /pricing', false);
assert.ok(bn.some((s) => s.href === '/' && s.label === 'হোম'));
assert.ok(bn.some((s) => s.href === '/pricing'));

// Trailing sentence punctuation must not become part of the URL.
const punct = linkifyInternalPaths(
    'Connect on https://www.linkedin.com/in/dev-muhib. Or visit https://app.wpsalehub.com।',
    true,
);
assert.ok(punct.some((s) => s.external && s.href === 'https://www.linkedin.com/in/dev-muhib'));
assert.ok(punct.some((s) => s.external && s.href === 'https://app.wpsalehub.com' && s.label === 'WPSaleHub'));
assert.ok(punct.some((s) => s.type === 'text' && s.text.startsWith('. ')));

// Short locale roots must not steal longer unknown /en/... paths.
const unknownEn = linkifyInternalPaths('See /en/some-unknown-spoke for details', true);
assert.equal(unknownEn.some((s) => s.href === '/en'), false);
assert.ok(unknownEn.some((s) => s.type === 'text' && s.text.includes('/en/some-unknown-spoke')));

const knownEn = linkifyInternalPaths('Read /en/facebook-page-cod-management next', true);
assert.ok(knownEn.some((s) => s.href === '/en/facebook-page-cod-management' && s.label === 'Facebook Page COD guide'));
assert.equal(knownEn.some((s) => s.href === '/en'), false);

console.log('internalPathLinks.spec.mjs: ok');
