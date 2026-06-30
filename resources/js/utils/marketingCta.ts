/**
 * Shared primary CTA copy for marketing pages (landing, pricing, layout).
 * Free trial CTAs always navigate to the pricing page.
 */
export function primaryCtaLabel(): string {
    return 'ফ্রি ট্রায়াল শুরু করুন';
}

/** Compact label for mobile header bar */
export function primaryCtaShortLabel(): string {
    return 'ফ্রি ট্রায়াল';
}

export function primaryCtaUrl(): string {
    return route('pricing');
}
