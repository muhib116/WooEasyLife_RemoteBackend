/**
 * Normalize a URL or domain string to a lowercase hostname.
 * Mirrors backend DomainNormalizer / getDomainFromUrl behaviour.
 */
export function normalizeDomainInput(value?: string | null): string | null {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return null;
    }

    try {
        const withScheme = /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
        const host = new URL(withScheme).hostname.toLowerCase().replace(/\.$/, '');

        return host || null;
    } catch {
        return null;
    }
}

/**
 * Basic public hostname check (requires a real TLD, rejects localhost/IPs).
 */
export function isValidDomainHost(host: string | null | undefined): boolean {
    if (!host) {
        return false;
    }

    if (host === 'localhost' || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(host)) {
        return false;
    }

    return /^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i.test(host);
}

export function validateDomainInput(value?: string | null): {
    valid: boolean;
    domain: string | null;
    message: string | null;
} {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return {
            valid: false,
            domain: null,
            message: 'ডোমেইন নাম/ওয়েবসাইটের নাম লিখুন।',
        };
    }

    const domain = normalizeDomainInput(raw);

    if (!domain || !isValidDomainHost(domain)) {
        return {
            valid: false,
            domain: null,
            message: 'সঠিক ডোমেইন নাম লিখুন (যেমন: myshop.com)।',
        };
    }

    return {
        valid: true,
        domain,
        message: null,
    };
}
