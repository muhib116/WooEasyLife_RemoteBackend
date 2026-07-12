const BD_MOBILE_PATTERN = /^01[3-9]\d{8}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;

/**
 * Normalize common BD mobile inputs to 01XXXXXXXXX.
 * Accepts: 017..., +88017..., 88017..., 017-... with spaces.
 */
export function normalizeBdMobile(value?: string | null): string | null {
    const digits = String(value ?? '').replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    let normalized = digits;

    if (normalized.startsWith('880') && normalized.length === 13) {
        normalized = `0${normalized.slice(3)}`;
    } else if (normalized.startsWith('88') && normalized.length === 12) {
        normalized = `0${normalized.slice(2)}`;
    }

    return normalized || null;
}

export function isValidBdMobile(value?: string | null): boolean {
    const normalized = normalizeBdMobile(value);

    return Boolean(normalized && BD_MOBILE_PATTERN.test(normalized));
}

export function validateBdMobile(
    value?: string | null,
    label = 'মোবাইল নম্বর',
): { valid: boolean; value: string | null; message: string | null } {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return {
            valid: false,
            value: null,
            message: `${label} লিখুন।`,
        };
    }

    const normalized = normalizeBdMobile(raw);

    if (!normalized || !BD_MOBILE_PATTERN.test(normalized)) {
        return {
            valid: false,
            value: null,
            message: `সঠিক বাংলাদেশি ${label} লিখুন (যেমন: 017XXXXXXXX)।`,
        };
    }

    return {
        valid: true,
        value: normalized,
        message: null,
    };
}

export function isValidEmail(value?: string | null): boolean {
    const email = String(value ?? '').trim();

    if (!email || email.length > 255) {
        return false;
    }

    return EMAIL_PATTERN.test(email);
}

export function validateEmail(value?: string | null): {
    valid: boolean;
    value: string | null;
    message: string | null;
} {
    const email = String(value ?? '').trim();

    if (!email) {
        return {
            valid: false,
            value: null,
            message: 'ইমেইল ঠিকানা লিখুন।',
        };
    }

    if (!isValidEmail(email)) {
        return {
            valid: false,
            value: null,
            message: 'সঠিক ইমেইল ঠিকানা লিখুন (যেমন: name@example.com)।',
        };
    }

    return {
        valid: true,
        value: email.toLowerCase(),
        message: null,
    };
}
