/**
 * Turn raw internal paths (and absolute https URLs) in marketing copy into
 * labeled link tokens. Only known marketing routes are linked as internal
 * paths (avoids /wp-login, /images/…). Absolute http(s) URLs become external
 * links and must never be rewritten by the `/` home-path label.
 *
 * Optional Bangla case markers glued after a path (-এ, -তে, …) are dropped
 * so labeled links don’t leave a dangling “-এ”.
 */

const LABELS = {
    '/': { bn: 'হোম', en: 'Bangla home' },
    '/en': { bn: 'ইংরেজি হোম', en: 'English home' },
    '/about': { bn: 'About পেজ', en: 'Bangla About' },
    '/en/about': { bn: 'ইংরেজি About', en: 'English About' },
    '/bd-fraud-checker': { bn: 'ফ্রড চেকার', en: 'BD Fraud Checker' },
    '/en/bd-fraud-checker': { bn: 'ফ্রড চেকার (EN)', en: 'BD Fraud Checker' },
    '/fake-order-protection': { bn: 'ফেক অর্ডার প্রোটেকশন', en: 'Fake Order Protection' },
    '/en/fake-order-protection': { bn: 'ফেক অর্ডার প্রোটেকশন (EN)', en: 'Fake Order Protection' },
    '/courier-auto-entry': { bn: 'কুরিয়ার অটো এন্ট্রি', en: 'Courier Auto Entry' },
    '/en/courier-auto-entry': { bn: 'কুরিয়ার অটো এন্ট্রি (EN)', en: 'Courier Auto Entry' },
    '/return-loss-calculator': { bn: 'রিটার্ন লস ক্যালকুলেটর', en: 'Return Loss Calculator' },
    '/en/return-loss-calculator': { bn: 'রিটার্ন লস ক্যালকুলেটর (EN)', en: 'Return Loss Calculator' },
    '/courier-charge-calculator': { bn: 'কুরিয়ার চার্জ ক্যালকুলেটর', en: 'Courier Charge Calculator' },
    '/en/courier-charge-calculator': { bn: 'কুরিয়ার চার্জ ক্যালকুলেটর (EN)', en: 'Courier Charge Calculator' },
    '/ads-roas-calculator': { bn: 'Ads ROAS ক্যালকুলেটর', en: 'Ads ROAS Calculator' },
    '/en/ads-roas-calculator': { bn: 'Ads ROAS Calculator', en: 'Ads ROAS Calculator' },
    '/woocommerce-bangladesh': { bn: 'WooCommerce Bangladesh গাইড', en: 'WooCommerce Bangladesh guide' },
    '/en/woocommerce-bangladesh': { bn: 'ইংরেজি গাইড', en: 'WooCommerce Bangladesh guide' },
    '/pricing': { bn: 'প্রাইসিং / ট্রায়াল', en: 'Pricing / free trial' },
    '/fraudbd-alternative': { bn: 'FraudBD Alternative', en: 'FraudBD Alternative' },
    '/en/fraudbd-alternative': { bn: 'FraudBD Alternative (EN)', en: 'FraudBD Alternative' },
    '/steadfast-integration': { bn: 'Steadfast ইন্টিগ্রেশন', en: 'Steadfast integration' },
    '/en/steadfast-integration': { bn: 'Steadfast (EN)', en: 'Steadfast integration' },
    '/pathao-courier-guide': { bn: 'Pathao কুরিয়ার গাইড', en: 'Pathao courier guide' },
    '/en/pathao-courier-guide': { bn: 'Pathao (EN)', en: 'Pathao courier guide' },
    '/redx-courier-guide': { bn: 'RedX কুরিয়ার গাইড', en: 'RedX courier guide' },
    '/en/redx-courier-guide': { bn: 'RedX (EN)', en: 'RedX courier guide' },
    '/woocommerce-mobile-app': { bn: 'মোবাইল অ্যাপ গাইড', en: 'Mobile app guide' },
    '/en/woocommerce-mobile-app': { bn: 'Mobile app (EN)', en: 'Mobile app guide' },
    '/customer-verification': { bn: 'কাস্টমার ভেরিফিকেশন', en: 'Customer verification' },
    '/en/customer-verification': { bn: 'Customer verification (EN)', en: 'Customer verification' },
    '/cod-return-reduction': { bn: 'COD রিটার্ন কমান', en: 'COD return reduction' },
    '/en/cod-return-reduction': { bn: 'COD returns (EN)', en: 'COD return reduction' },
    '/woocommerce-notifications': { bn: 'নোটিফিকেশন অটোমেশন', en: 'Notifications automation' },
    '/en/woocommerce-notifications': { bn: 'Notifications (EN)', en: 'Notifications automation' },
    '/facebook-ads-for-woocommerce': { bn: 'Facebook Ads গাইড', en: 'Facebook Ads guide' },
    '/en/facebook-ads-for-woocommerce': { bn: 'Facebook Ads (EN)', en: 'Facebook Ads guide' },
    '/facebook-page-cod-management': { bn: 'Facebook Page COD গাইড', en: 'Facebook Page COD guide' },
    '/en/facebook-page-cod-management': { bn: 'Facebook Page COD (EN)', en: 'Facebook Page COD guide' },
    '/ki-vabe-fake-order-atkabo': { bn: 'কিভাবে ফেক অর্ডার আটকাবো', en: 'How to stop fake orders (BN)' },
    '/en/ki-vabe-fake-order-atkabo': { bn: 'How to stop fake orders (EN)', en: 'How to stop fake orders' },
    '/fake-customer-check': { bn: 'Fake Customer Check', en: 'Fake Customer Check' },
    '/en/fake-customer-check': { bn: 'Fake Customer Check (EN)', en: 'Fake Customer Check' },
    '/courier-checker': { bn: 'Courier Checker', en: 'Courier Checker' },
    '/fake-order-check': { bn: 'Fake Order Check', en: 'Fake Order Check' },
    '/bd-courier-ratio-checker': { bn: 'BD Courier Ratio Checker', en: 'BD Courier Ratio Checker' },
    '/pathao-fraud-check': { bn: 'Pathao ফ্রড চেক', en: 'Pathao fraud check' },
    '/steadfast-fraud-check': { bn: 'Steadfast ফ্রড চেক', en: 'Steadfast fraud check' },
    '/redx-fraud-check': { bn: 'RedX ফ্রড চেক', en: 'RedX fraud check' },
    '/faq': { bn: 'FAQ হাব', en: 'FAQ hub' },
    '/faq/courier-success-rate-kivabe-bujhbo': { bn: 'সাকসেস রেট কীভাবে বুঝবেন', en: 'How to read success rate' },
    '/faq/success-rate-kom-hole-ki-korbo': { bn: 'রেট কম হলে কী করবেন', en: 'What to do when rate is low' },
    '/faq/cod-order-otp-kokhon': { bn: 'COD-এ OTP কখন নেবেন', en: 'When to use COD OTP' },
    '/faq/woocommerce-customer-blacklist': { bn: 'কাস্টমার ব্ল্যাকলিস্ট', en: 'Customer blacklist' },
    '/faq/duplicate-cod-order-block': { bn: 'ডুপ্লিকেট অর্ডার ব্লক', en: 'Duplicate order block' },
    '/faq/customer-delivery-history-check': { bn: 'ডেলিভারি হিস্টোরি চেক', en: 'Delivery history check' },
    '/faq/customer-fraud-score-ki': { bn: 'ফ্রড স্কোর কী', en: 'What fraud score means' },
    '/faq/cod-return-loss-hisab': { bn: 'রিটার্ন লস হিসাব', en: 'Return loss math' },
    '/blog/blacklist-customer-after-returns': { bn: 'রিটার্নের পর ব্ল্যাকলিস্ট', en: 'Blacklist after returns' },
};

const KNOWN_PATHS = Object.keys(LABELS).sort((a, b) => b.length - a.length);

/** Bangla case markers sometimes glued onto slugs in copy (e.g. /pricing-এ). */
const BN_PATH_SUFFIX_RE = /^(?:-এ|-তে|-র|-য়|-য়ে|-য়ের|-কে|-ও)/u;

/** Absolute URLs — must be linked as-is, never rewritten by the `/` home label. */
const ABS_URL_RE = /https?:\/\/[^\s<>"'））\]},]+/gi;

/** Trailing punctuation that belongs to the sentence, not the URL. */
const URL_TRAILING_PUNCT_RE = /[.,;:!?।)"'»…]+$/u;

export function labelForInternalPath(path, isEn = false) {
    const key = String(path || '').replace(/\/+$/, '') || '/';
    const entry = LABELS[key];
    if (entry) return isEn ? entry.en : entry.bn;
    return key;
}

/**
 * True when an internal path may start at idx (not mid-URL / mid-token).
 * Prevents `https://…` from matching `/` as Bangla/English home.
 * Also blocks bare `/` inside BN compounds like ক্যানসেল/রিটার্ন or হলুদ/লাল.
 */
function canMatchInternalPathAt(raw, idx, path) {
    if (idx === 0) {
        if (path === '/') {
            const next = raw[idx + 1] || '';
            // Bare home `/` needs a non-letter/digit on the right (space, ·, punct, end).
            return !next || !/\p{L}|\p{N}/u.test(next);
        }
        return true;
    }
    const prev = raw[idx - 1];
    // Latin URL/slug chars → inside absolute URL or longer token
    if (/[a-z0-9.:_-]/i.test(prev)) return false;
    if (path === '/') {
        // Unicode letter/digit on either side → slash is a separator, not home.
        if (/\p{L}|\p{N}/u.test(prev)) return false;
        const next = raw[idx + 1] || '';
        if (next && /\p{L}|\p{N}/u.test(next)) return false;
    }
    return true;
}

function labelForAbsoluteUrl(url) {
    try {
        const u = new URL(url);
        const host = u.hostname.replace(/^www\./, '');
        if (host.includes('linkedin.com')) return 'LinkedIn';
        if (host.includes('instagram.com')) return 'Instagram';
        if (host.includes('facebook.com')) {
            if (/wooeasylife/i.test(u.pathname)) return 'Facebook page';
            return 'Facebook';
        }
        if (host.includes('wpsalehub.com')) {
            if (u.pathname.includes('pricing')) return 'Pricing / free trial';
            return 'WPSaleHub';
        }
        if (host.includes('freetoolssite.com')) return 'FreeToolsSite';
        return host;
    } catch {
        return url;
    }
}

/**
 * @param {string} text
 * @param {boolean} isEn
 * @returns {Array<{ type: 'text', text: string } | { type: 'link', href: string, label: string, external?: boolean }>}
 */
function linkifyKnownPathsInPlainText(raw, isEn = false) {
    if (!raw) return [];

    const hits = [];
    for (const path of KNOWN_PATHS) {
        let from = 0;
        while (from < raw.length) {
            const idx = raw.indexOf(path, from);
            if (idx < 0) break;
            if (!canMatchInternalPathAt(raw, idx, path)) {
                from = idx + path.length;
                continue;
            }
            const afterPath = idx + path.length;
            const next = raw[afterPath] || '';
            const next2 = raw[afterPath + 1] || '';
            // Skip longer Latin slug prefixes: /foo vs /foo-bar, and /en vs /en/foo
            if (next && /[a-z0-9]/i.test(next)) {
                from = afterPath;
                continue;
            }
            if (next === '/' && /[a-z0-9]/i.test(next2)) {
                from = afterPath;
                continue;
            }
            let end = afterPath;
            const suffix = raw.slice(afterPath).match(BN_PATH_SUFFIX_RE);
            if (suffix) {
                end += suffix[0].length;
            }
            const overlaps = hits.some((h) => idx < h.end && end > h.start);
            if (!overlaps) {
                hits.push({ start: idx, end, path });
            }
            from = end;
        }
    }

    hits.sort((a, b) => a.start - b.start);
    if (!hits.length) return [{ type: 'text', text: raw }];

    const parts = [];
    let last = 0;
    for (const hit of hits) {
        if (hit.start > last) {
            parts.push({ type: 'text', text: raw.slice(last, hit.start) });
        }
        parts.push({
            type: 'link',
            href: hit.path,
            label: labelForInternalPath(hit.path, isEn),
        });
        last = hit.end;
    }
    if (last < raw.length) {
        parts.push({ type: 'text', text: raw.slice(last) });
    }
    return parts;
}

/**
 * @param {string} text
 * @param {boolean} isEn
 * @returns {Array<{ type: 'text', text: string } | { type: 'link', href: string, label: string, external?: boolean }>}
 */
export function linkifyInternalPaths(text, isEn = false) {
    const raw = String(text || '');
    if (!raw) return [];

    const absHits = [];
    ABS_URL_RE.lastIndex = 0;
    let m;
    while ((m = ABS_URL_RE.exec(raw)) !== null) {
        let url = m[0];
        const trailing = url.match(URL_TRAILING_PUNCT_RE);
        if (trailing) {
            url = url.slice(0, -trailing[0].length);
        }
        if (!url) continue;
        absHits.push({ start: m.index, end: m.index + url.length, url });
    }

    if (!absHits.length) {
        return linkifyKnownPathsInPlainText(raw, isEn);
    }

    const parts = [];
    let last = 0;
    for (const hit of absHits) {
        if (hit.start > last) {
            parts.push(...linkifyKnownPathsInPlainText(raw.slice(last, hit.start), isEn));
        }
        parts.push({
            type: 'link',
            href: hit.url,
            label: labelForAbsoluteUrl(hit.url),
            external: true,
        });
        last = hit.end;
    }
    if (last < raw.length) {
        parts.push(...linkifyKnownPathsInPlainText(raw.slice(last), isEn));
    }
    return parts;
}
