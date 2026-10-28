/**
 * ChatGPT Ads Measurement Pixel.
 * The base snippet is in app.blade.php. The API key never reaches the browser.
 */

type Oaiq = (...args: unknown[]) => void;

const viewedPaths = new Set<string>();

function getOaiq(): Oaiq | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const oaiq = (window as Window & { oaiq?: Oaiq }).oaiq;

    return typeof oaiq === 'function' ? oaiq : null;
}

/** BDT uses two minor-unit digits, so ৳999 is 99900. */
export function takaToMinor(taka: number): number {
    if (!Number.isFinite(taka) || taka <= 0) {
        return 0;
    }

    return Math.round(taka * 100);
}

export function trackOpenAiPageViewed(path: string, title?: string | null): boolean {
    const oaiq = getOaiq();
    const page = path || '/';

    if (!oaiq || viewedPaths.has(page)) {
        return false;
    }

    oaiq('measure', 'page_viewed', {
        type: 'contents',
        contents: [
            {
                id: page,
                name: title || 'Page',
                content_type: 'page',
            },
        ],
    });
    viewedPaths.add(page);

    return true;
}

export function trackOpenAiPlanEnrollment(input: {
    isTrial: boolean;
    planId?: string | number | null;
    amountTaka?: number | null;
    eventId?: string | null;
}): boolean {
    const oaiq = getOaiq();

    if (!oaiq || input.planId === undefined || input.planId === null || input.planId === '') {
        return false;
    }

    const data: Record<string, unknown> = {
        type: 'plan_enrollment',
        plan_id: String(input.planId),
    };
    const minor = takaToMinor(Number(input.amountTaka ?? 0));

    if (!input.isTrial && minor > 0) {
        data.amount = minor;
        data.currency = 'BDT';
    }

    const options = input.eventId ? { event_id: input.eventId } : undefined;

    oaiq(
        'measure',
        input.isTrial ? 'trial_started' : 'subscription_created',
        data,
        options,
    );

    return true;
}
