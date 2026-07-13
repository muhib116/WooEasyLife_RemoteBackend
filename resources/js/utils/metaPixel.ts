/**
 * Meta Pixel helpers for public marketing pages.
 * Base snippet loads from app.blade.php when marketing.meta_pixel_id is set.
 *
 * Conversion policy (solid Ads signals):
 * - Lead / StartTrial / Subscribe fire only after successful inquiry submit
 * - Purchase is NOT fired on inquiry (payment still pending admin review)
 * - eventID = inquiry_{id} for browser ↔ future CAPI dedupe
 *
 * Standard: PageView, ViewContent, Search, Contact, Lead,
 * InitiateCheckout, AddPaymentInfo, StartTrial, Subscribe
 * Custom: CtaClick, ScrollDepth, WizardStep, DownloadUnlocked
 */

type MetaParam = string | number | boolean | null | undefined;
type MetaParams = Record<string, MetaParam | MetaParam[] | Record<string, MetaParam>[]>;
type Fbq = (...args: unknown[]) => void;
type TrackOptions = {
    eventID?: string | number | null;
};

const firedKeys = new Set<string>();

function getFbq(): Fbq | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const fbq = (window as Window & { fbq?: Fbq }).fbq;

    return typeof fbq === 'function' ? fbq : null;
}

function cleanParams(params?: MetaParams): Record<string, unknown> | undefined {
    if (!params) {
        return undefined;
    }

    const cleaned: Record<string, unknown> = {};

    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            return;
        }

        cleaned[key] = value;
    });

    return Object.keys(cleaned).length ? cleaned : undefined;
}

function resolveEventID(options?: TrackOptions): string | undefined {
    if (options?.eventID === undefined || options?.eventID === null || options?.eventID === '') {
        return undefined;
    }

    return String(options.eventID);
}

export function inquiryEventId(inquiryId?: string | number | null): string | undefined {
    if (inquiryId === undefined || inquiryId === null || inquiryId === '') {
        return undefined;
    }

    return `inquiry_${inquiryId}`;
}

export function trackMetaEvent(
    event: string,
    params?: MetaParams,
    options?: TrackOptions,
): boolean {
    const fbq = getFbq();

    if (!fbq) {
        return false;
    }

    const cleaned = cleanParams(params) ?? {};
    const eventID = resolveEventID(options);

    if (eventID) {
        fbq('track', event, cleaned, { eventID });
    } else if (Object.keys(cleaned).length) {
        fbq('track', event, cleaned);
    } else {
        fbq('track', event);
    }

    return true;
}

export function trackMetaCustom(
    event: string,
    params?: MetaParams,
    options?: TrackOptions,
): boolean {
    const fbq = getFbq();

    if (!fbq) {
        return false;
    }

    const cleaned = cleanParams(params) ?? {};
    const eventID = resolveEventID(options);

    if (eventID) {
        fbq('trackCustom', event, cleaned, { eventID });
    } else if (Object.keys(cleaned).length) {
        fbq('trackCustom', event, cleaned);
    } else {
        fbq('trackCustom', event);
    }

    return true;
}

/** Fire once per browser session for a given key. */
export function trackOnce(key: string, track: () => boolean): boolean {
    if (firedKeys.has(key)) {
        return false;
    }

    const ok = track();

    if (ok) {
        firedKeys.add(key);
    }

    return ok;
}

export function planValue(plan?: { package_price?: number | string | null } | null): number {
    const raw = plan?.package_price;

    if (raw === null || raw === undefined || raw === '') {
        return 0;
    }

    const value = Number(raw);

    return Number.isFinite(value) ? value : 0;
}

export function planContentParams(plan?: {
    id?: number | string | null;
    title?: string | null;
    package_price?: number | string | null;
    package_duration?: string | null;
} | null) {
    if (!plan) {
        return {
            currency: 'BDT',
            value: 0,
        };
    }

    return {
        content_ids: plan.id != null ? [String(plan.id)] : undefined,
        content_name: plan.title ?? undefined,
        content_type: 'product',
        content_category: plan.package_duration ?? undefined,
        value: planValue(plan),
        currency: 'BDT',
        num_items: 1,
    };
}

export function trackViewContent(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('ViewContent', params, options);
}

export function trackSearch(params?: {
    search_string?: string;
    content_category?: string;
}): boolean {
    return trackMetaEvent('Search', {
        search_string: params?.search_string,
        content_category: params?.content_category ?? 'fraud_check',
    });
}

export function trackContact(params?: {
    method?: string;
    content_name?: string;
}): boolean {
    return trackMetaEvent('Contact', {
        content_name: params?.content_name ?? params?.method ?? 'contact',
    });
}

export function trackLead(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('Lead', params, options);
}

export function trackInitiateCheckout(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('InitiateCheckout', params, options);
}

export function trackAddPaymentInfo(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('AddPaymentInfo', params, options);
}

/** Meta Ads "Start trial" optimization event — fire only after successful free-trial submit. */
export function trackStartTrial(params?: {
    value?: number;
    currency?: string;
    content_name?: string | null;
    content_ids?: string[];
    predicted_ltv?: number;
    order_id?: string | number | null;
}, options?: TrackOptions): boolean {
    return trackMetaEvent('StartTrial', {
        value: params?.value ?? 0,
        currency: params?.currency ?? 'BDT',
        content_name: params?.content_name ?? undefined,
        content_ids: params?.content_ids,
        predicted_ltv: params?.predicted_ltv,
        order_id: params?.order_id != null ? String(params.order_id) : undefined,
    }, options);
}

/** Paid SaaS inquiry submitted (payment still pending review). Prefer this over Purchase. */
export function trackSubscribe(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('Subscribe', {
        currency: 'BDT',
        ...params,
    }, options);
}

/**
 * Reserved for confirmed payment / merchant conversion.
 * Do not call on inquiry submit — that inflates Meta ROAS.
 */
export function trackPurchase(params?: MetaParams, options?: TrackOptions): boolean {
    return trackMetaEvent('Purchase', {
        currency: 'BDT',
        ...params,
    }, options);
}

export function trackCtaClick(params: {
    location: string;
    label?: string | null;
    href?: string | null;
    plan_id?: string | number | null;
}): boolean {
    return trackMetaCustom('CtaClick', {
        location: params.location,
        label: params.label ?? undefined,
        href: params.href ?? undefined,
        plan_id: params.plan_id != null ? String(params.plan_id) : undefined,
    });
}

export function trackScrollDepth(percent: number, pageKey?: string): boolean {
    return trackOnce(`scroll:${pageKey ?? 'page'}:${percent}`, () =>
        trackMetaCustom('ScrollDepth', {
            percent,
            page: pageKey ?? (typeof window !== 'undefined' ? window.location.pathname : undefined),
        }),
    );
}

export function trackWizardStep(step: string, plan?: { id?: number | string | null; title?: string | null } | null): boolean {
    return trackMetaCustom('WizardStep', {
        step,
        content_ids: plan?.id != null ? [String(plan.id)] : undefined,
        content_name: plan?.title ?? undefined,
    });
}

export function trackDownloadUnlocked(params?: MetaParams): boolean {
    return trackMetaCustom('DownloadUnlocked', params);
}

const SCROLL_THRESHOLDS = [25, 50, 75, 90] as const;

export function attachScrollDepthTracking(pageKey?: string): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const key = pageKey ?? window.location.pathname;
    let ticking = false;

    const onScroll = () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(() => {
            ticking = false;
            const doc = document.documentElement;
            const scrollable = doc.scrollHeight - window.innerHeight;

            if (scrollable <= 0) {
                trackScrollDepth(90, key);
                return;
            }

            const percent = Math.round((window.scrollY / scrollable) * 100);

            SCROLL_THRESHOLDS.forEach((threshold) => {
                if (percent >= threshold) {
                    trackScrollDepth(threshold, key);
                }
            });
        });
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    return () => window.removeEventListener('scroll', onScroll);
}
