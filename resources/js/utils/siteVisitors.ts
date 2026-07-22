const VISITOR_KEY = 'wel_site_vid';
const UTM_KEY = 'wel_site_utm';
const ENDPOINT = '/analytics/visitors/event';

type SiteVisitorEvent =
    | 'page_view'
    | 'heartbeat'
    | 'scroll_depth'
    | 'cta_click'
    | 'tool_action';

type UtmBag = {
    utm_source?: string;
    utm_medium?: string;
    utm_campaign?: string;
    utm_content?: string;
    utm_term?: string;
};

type TrackPayload = {
    path: string;
    event: SiteVisitorEvent;
    visitor_id: string;
    engaged_ms?: number;
    scroll_pct?: number;
    cta_label?: string;
    action_name?: string;
    referrer?: string;
    search_keyword?: string;
} & UtmBag;

function randomHex(bytes = 16): string {
    const arr = new Uint8Array(bytes);
    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        crypto.getRandomValues(arr);
    } else {
        for (let i = 0; i < bytes; i += 1) {
            arr[i] = Math.floor(Math.random() * 256);
        }
    }
    return Array.from(arr, (b) => b.toString(16).padStart(2, '0')).join('');
}

export function getSiteVisitorId(): string {
    if (typeof window === 'undefined') {
        return '';
    }
    try {
        const existing = localStorage.getItem(VISITOR_KEY);
        if (existing && /^[a-f0-9]{16,64}$/i.test(existing)) {
            return existing.toLowerCase();
        }
        const id = randomHex(16);
        localStorage.setItem(VISITOR_KEY, id);
        return id;
    } catch {
        return randomHex(16);
    }
}

function captureUtmsFromLocation(): UtmBag {
    if (typeof window === 'undefined') {
        return {};
    }
    try {
        const params = new URLSearchParams(window.location.search);
        const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as const;
        const found: UtmBag = {};
        let any = false;
        keys.forEach((key) => {
            const val = params.get(key);
            if (val) {
                found[key] = val.slice(0, 120);
                any = true;
            }
        });
        if (any) {
            sessionStorage.setItem(UTM_KEY, JSON.stringify(found));
            return found;
        }
        const raw = sessionStorage.getItem(UTM_KEY);
        if (raw) {
            const parsed = JSON.parse(raw) as UtmBag;
            return parsed && typeof parsed === 'object' ? parsed : {};
        }
    } catch {
        // ignore
    }
    return {};
}

function normalizePath(path?: string | null): string {
    const raw = (path || (typeof window !== 'undefined' ? window.location.pathname : '/')).split('?')[0] || '/';
    if (!raw.startsWith('/')) {
        return `/${raw}`;
    }
    if (raw.length > 1 && raw.endsWith('/')) {
        return raw.replace(/\/+$/, '') || '/';
    }
    return raw || '/';
}

function send(payload: TrackPayload): void {
    if (typeof window === 'undefined') {
        return;
    }

    const body = JSON.stringify(payload);
    try {
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            const blob = new Blob([body], { type: 'application/json' });
            if (navigator.sendBeacon(ENDPOINT, blob)) {
                return;
            }
        }
    } catch {
        // fall through to fetch
    }

    try {
        void fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    } catch {
        // never block UI
    }
}

function extractSearchKeywordFromReferrer(referrer?: string): string | undefined {
    if (!referrer) {
        return undefined;
    }
    try {
        const url = new URL(referrer);
        for (const key of ['q', 'query', 'p', 'text', 'keyword']) {
            const val = url.searchParams.get(key);
            if (val && val.trim()) {
                return val.trim().slice(0, 255);
            }
        }
    } catch {
        // ignore
    }
    return undefined;
}

function basePayload(path: string, event: SiteVisitorEvent): TrackPayload {
    const utms = captureUtmsFromLocation();
    const referrer = typeof document !== 'undefined' ? document.referrer || undefined : undefined;
    const searchKeyword = utms.utm_term || extractSearchKeywordFromReferrer(referrer);

    return {
        path: normalizePath(path),
        event,
        visitor_id: getSiteVisitorId(),
        referrer,
        search_keyword: searchKeyword,
        ...utms,
    };
}

export function trackPageView(path?: string): void {
    send(basePayload(path ?? '', 'page_view'));
}

export function trackHeartbeat(path: string, engagedMs: number): void {
    send({
        ...basePayload(path, 'heartbeat'),
        engaged_ms: Math.max(0, Math.round(engagedMs)),
    });
}

export function trackScrollDepth(path: string, percent: number): void {
    send({
        ...basePayload(path, 'scroll_depth'),
        scroll_pct: Math.max(1, Math.min(100, Math.round(percent))),
    });
}

export function trackCta(path: string, label: string): void {
    send({
        ...basePayload(path, 'cta_click'),
        cta_label: (label || 'cta').slice(0, 120),
    });
}

export function trackToolAction(path: string, actionName: string): void {
    send({
        ...basePayload(path, 'tool_action'),
        action_name: (actionName || 'action').slice(0, 120),
    });
}

const SITE_SCROLL_THRESHOLDS = [25, 50, 75, 90] as const;
const firedScroll = new Set<string>();

export function attachSiteScrollDepthTracking(path?: string): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const pagePath = normalizePath(path);
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
            const percent = scrollable <= 0
                ? 90
                : Math.round((window.scrollY / scrollable) * 100);

            SITE_SCROLL_THRESHOLDS.forEach((threshold) => {
                if (percent < threshold) {
                    return;
                }
                const key = `${pagePath}:${threshold}`;
                if (firedScroll.has(key)) {
                    return;
                }
                firedScroll.add(key);
                trackScrollDepth(pagePath, threshold);
            });
        });
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    return () => window.removeEventListener('scroll', onScroll);
}

export function attachSiteEngagementTracking(path?: string, intervalMs = 15000): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const pagePath = normalizePath(path);
    const startedAt = Date.now();
    let engagedMs = 0;
    let lastTick = Date.now();
    let visible = typeof document === 'undefined' ? true : document.visibilityState !== 'hidden';

    const accumulate = () => {
        const now = Date.now();
        if (visible) {
            engagedMs += now - lastTick;
        }
        lastTick = now;
    };

    const flush = () => {
        accumulate();
        if (engagedMs >= 1000) {
            trackHeartbeat(pagePath, engagedMs);
        }
    };

    const onVisibility = () => {
        accumulate();
        visible = document.visibilityState !== 'hidden';
        lastTick = Date.now();
        if (!visible) {
            flush();
        }
    };

    const timer = window.setInterval(() => {
        if (visible) {
            flush();
        }
    }, Math.max(5000, intervalMs));

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', flush);

    // Ensure we don't send zero immediately; first tick after interval.
    void startedAt;

    return () => {
        flush();
        window.clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('pagehide', flush);
    };
}
