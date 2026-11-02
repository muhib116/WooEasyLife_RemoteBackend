/**
 * Public checker bands. Same cutoffs as PublicFraudCheckService::riskLabel()
 * in app/Services/PublicFraudCheckService.php (>= 75 safe, >= 45 caution, else risky).
 * Used only when the API did not send a risk label. Do not change those PHP labels from here.
 */
export const PUBLIC_FRAUD_BAND = {
    highAt: 75,
    midAt: 45,
};

const TONE_BAND = {
    safe: 'high',
    caution: 'mid',
    risky: 'low',
    neutral: 'none',
};

const LABEL_BAND = {
    'safe customer': 'high',
    'নিরাপদ গ্রাহক': 'high',
    'caution advised': 'mid',
    'সতর্কতা প্রয়োজন': 'mid',
    'risky customer': 'low',
    'ঝুঁকিপূর্ণ গ্রাহক': 'low',
    'no history': 'none',
    'ইতিহাস নেই': 'none',
};

const trackedFraudResults = new WeakSet();
const trackedSubscriptionIds = new Set();

const SENSITIVE_QUERY_KEYS = ['phone', 'mobile', 'number'];

function deleteSensitiveParams(params) {
    const keys = [];

    params.forEach((value, key) => {
        if (SENSITIVE_QUERY_KEYS.includes(String(key).toLowerCase())) {
            keys.push(key);
        }
    });

    keys.forEach((key) => params.delete(key));
}

/**
 * Drop phone, mobile, and number from a URL. Other query params and the hash stay.
 * Absolute hrefs stay absolute. A path (or ?query / #hash) stays a path.
 */
export function cleanUrl(href) {
    if (typeof href !== 'string' || href === '') {
        return '';
    }

    const hasScheme = /^[a-z][a-z0-9+.-]*:/i.test(href);
    const relative = href.startsWith('/') || href.startsWith('?') || href.startsWith('#');

    if (!hasScheme && !relative) {
        return href;
    }

    try {
        const url = new URL(href, 'https://app.wpsalehub.com');
        deleteSensitiveParams(url.searchParams);

        if (!hasScheme) {
            return `${url.pathname}${url.search}${url.hash}`;
        }

        return url.toString();
    } catch {
        return href;
    }
}

/**
 * Remove phone, mobile, and number from the address bar without a reload.
 * The existing history state is kept so Inertia can still restore the page.
 * Its url is cleaned too, so a later scroll save does not write the number back.
 */
function browserWindow() {
    if (typeof window !== 'undefined') {
        return window;
    }

    if (typeof globalThis !== 'undefined' && globalThis.window) {
        return globalThis.window;
    }

    return undefined;
}

export function stripSensitiveQueryFromLocation() {
    const browser = browserWindow();

    if (!browser) {
        return;
    }

    const url = new URL(browser.location.href);

    const before = url.search;
    deleteSensitiveParams(url.searchParams);

    if (url.search === before) {
        return;
    }

    const next = `${url.pathname}${url.search}${url.hash}`;
    const state = browser.history.state;

    try {
        if (state && typeof state === 'object' && typeof state.url === 'string') {
            state.url = cleanUrl(state.url);
        }
    } catch {
        // Keep the address-bar update even if the history object is sealed.
    }

    browser.history.replaceState(state, '', next);
}

/**
 * Inertia writes the original page url after setup. Stripping before that
 * write makes the urls differ, so Inertia pushes a second history entry.
 * Read the query first, then call this so the removal happens after that write.
 */
export function stripSensitiveQueryFromLocationSoon() {
    queueMicrotask(() => {
        stripSensitiveQueryFromLocation();
        setTimeout(stripSensitiveQueryFromLocation, 0);
    });
}

function looksLikeUrl(value) {
    return value.startsWith('/') || value.startsWith('?') || value.startsWith('#') || /^[a-z][a-z0-9+.-]*:/i.test(value);
}

function sanitizeEventParams(params) {
    const clean = {};

    Object.entries(params || {}).forEach(([key, value]) => {
        if (SENSITIVE_QUERY_KEYS.includes(String(key).toLowerCase())) {
            return;
        }

        clean[key] = typeof value === 'string' && looksLikeUrl(value) ? cleanUrl(value) : value;
    });

    return clean;
}

/**
 * GA4 via the gtag.js stub in resources/views/app.blade.php.
 * Never throws if the tag is blocked or missing.
 *
 * Do not pass a phone number (full, partial, masked, or hashed), customer name,
 * address, order data, or the raw checker/subscription API body.
 */
export function trackEvent(name, params = {}) {
    try {
        if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
            window.gtag('event', name, sanitizeEventParams(params));
        }
    } catch {
        // Analytics must not break the page.
    }
}

export function fraudResultBand(result) {
    const tone = String(result?.risk_tone ?? '').trim().toLowerCase();

    if (TONE_BAND[tone]) {
        return TONE_BAND[tone];
    }

    const label = String(result?.risk_label ?? '').trim().toLowerCase();

    if (LABEL_BAND[label]) {
        return LABEL_BAND[label];
    }

    const total = Number(result?.report?.total_order ?? 0);
    const rateText = String(result?.report?.success_rate ?? '').toLowerCase();

    if (!Number.isFinite(total) || total === 0 || rateText.includes('no order') || rateText.includes('no history')) {
        return 'none';
    }

    const match = rateText.match(/(\d+)/);

    if (!match) {
        return 'none';
    }

    const percent = Number(match[1]);

    if (percent >= PUBLIC_FRAUD_BAND.highAt) {
        return 'high';
    }

    if (percent >= PUBLIC_FRAUD_BAND.midAt) {
        return 'mid';
    }

    return 'low';
}

/**
 * Once per successful result object. A new search is a new object and fires again.
 * Re-renders and a second pass of the same object do not.
 * Params are only page_language, result_band, and checker_page.
 */
export function trackFraudCheckComplete(result, pageLanguage, checkerPage) {
    if (!result || typeof result !== 'object' || result.limited) {
        return;
    }

    if (trackedFraudResults.has(result)) {
        return;
    }

    trackedFraudResults.add(result);

    trackEvent('fraud_check_complete', {
        page_language: pageLanguage === 'en' ? 'en' : 'bn',
        result_band: fraudResultBand(result),
        checker_page: typeof checkerPage === 'string' ? checkerPage : '',
    });
}

/**
 * Once per inquiry id. sessionStorage covers reload and back-forward, where the
 * Inertia flash can still be on the restored page.
 */
export function trackSubscriptionRequest(planName, pageLanguage, inquiryId) {
    const id = inquiryId == null ? '' : String(inquiryId);

    if (id === '' || planName == null || String(planName) === '') {
        return;
    }

    const key = `ga:subscription_request:${id}`;

    if (trackedSubscriptionIds.has(key)) {
        return;
    }

    try {
        if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(key) === '1') {
            trackedSubscriptionIds.add(key);

            return;
        }
    } catch {
        // Private mode can block storage; the in-memory set still dedupes this document.
    }

    trackedSubscriptionIds.add(key);

    try {
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem(key, '1');
        }
    } catch {
        // Ignore storage failures.
    }

    trackEvent('subscription_request', {
        plan_name: String(planName),
        page_language: pageLanguage === 'en' ? 'en' : 'bn',
    });
}
