/**
 * Shared primary CTA copy for marketing pages (landing, pricing, layout).
 * Free trial CTAs always navigate to the pricing page.
 */
export function primaryCtaLabel(locale: 'bn' | 'en' = 'bn'): string {
    return locale === 'en' ? 'Start free trial' : 'ফ্রি ট্রায়াল শুরু করুন';
}

/** Compact label for mobile header bar */
export function primaryCtaShortLabel(locale: 'bn' | 'en' = 'bn'): string {
    return locale === 'en' ? 'Free trial' : 'ফ্রি ট্রায়াল';
}

export function primaryCtaUrl(): string {
    return route('pricing');
}

type MarketingAuthProps = {
    user?: { id: number } | null;
    portal?: unknown | null;
};

/** Public marketing login always targets the merchant portal sign-in. */
export function merchantLoginHref(auth?: MarketingAuthProps | null): string {
    if (auth?.portal) {
        return route('portal.dashboard');
    }

    return route('merchant.login');
}

export function merchantLoginLabel(
    auth?: MarketingAuthProps | null,
    locale: 'bn' | 'en' = 'bn',
): string {
    if (auth?.portal) {
        return locale === 'en' ? 'Portal' : 'পোর্টাল';
    }

    return locale === 'en' ? 'Log in' : 'লগইন';
}
