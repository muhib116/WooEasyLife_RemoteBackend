import assert from 'node:assert/strict';
import test from 'node:test';
import { cleanUrl, stripSensitiveQueryFromLocation } from '../../resources/js/utils/analytics.js';

test('cleanUrl removes phone, mobile, and number and keeps utm params and the hash', () => {
    const href = 'https://app.wpsalehub.com/bd-fraud-checker?phone=01700000000&utm_source=courierfraudbd&mobile=01800000000&number=01900000000&utm_medium=referral#results';

    assert.equal(
        cleanUrl(href),
        'https://app.wpsalehub.com/bd-fraud-checker?utm_source=courierfraudbd&utm_medium=referral#results',
    );
});

test('cleanUrl keeps a relative page path relative', () => {
    assert.equal(
        cleanUrl('/bd-fraud-checker?phone=01700000000&utm_source=x'),
        '/bd-fraud-checker?utm_source=x',
    );
});

test('cleanUrl leaves a url with no sensitive keys unchanged', () => {
    assert.equal(
        cleanUrl('https://app.wpsalehub.com/en/bd-fraud-checker?lang=en#fraud-check'),
        'https://app.wpsalehub.com/en/bd-fraud-checker?lang=en#fraud-check',
    );
});

test('cleanUrl returns an empty string for an empty href', () => {
    assert.equal(cleanUrl(''), '');
});

test('cleanUrl removes a capitalized phone parameter', () => {
    assert.equal(
        cleanUrl('/bd-fraud-checker?Phone=01700000000&utm_source=x'),
        '/bd-fraud-checker?utm_source=x',
    );
});

test('stripSensitiveQueryFromLocation drops phone, mobile, and number and keeps Inertia history state', () => {
    const state = {
        component: 'Seo/BdFraudChecker',
        url: '/bd-fraud-checker?phone=01700000000&mobile=01800000000&number=01900000000&utm_source=x',
        props: {},
    };
    let replaced = null;

    globalThis.window = {
        location: new URL('https://app.wpsalehub.com/bd-fraud-checker?phone=01700000000&mobile=01800000000&number=01900000000&utm_source=x#fraud-check'),
        history: {
            state,
            replaceState(nextState, title, url) {
                replaced = { nextState, title, url };
            },
        },
    };

    const phone = new URLSearchParams(globalThis.window.location.search).get('phone');
    stripSensitiveQueryFromLocation();

    assert.equal(phone, '01700000000');
    assert.equal(replaced.title, '');
    assert.equal(replaced.url, '/bd-fraud-checker?utm_source=x#fraud-check');
    assert.equal(replaced.nextState, state);
    assert.equal(state.url, '/bd-fraud-checker?utm_source=x');

    delete globalThis.window;
});
