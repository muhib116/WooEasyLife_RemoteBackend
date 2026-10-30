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
            window.gtag('event', name, params);
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
