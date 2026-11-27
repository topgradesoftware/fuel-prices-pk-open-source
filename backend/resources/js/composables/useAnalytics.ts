type AnalyticsParam = string | number | boolean | null | undefined;
type AnalyticsParams = Record<string, AnalyticsParam>;

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
        dataLayer?: unknown[];
    }
}

const SCROLL_MILESTONES = [25, 50, 75, 90] as const;

/** Dual-fire legacy names toward the growth-roadmap GA4 taxonomy. */
const EVENT_ALIASES: Record<string, string> = {
    share: 'share_clicked',
    pwa_install: 'install_pwa',
    app_download_click: 'download_clicked',
    notification_subscribe: 'subscribe_notifications',
    notification_enabled: 'subscribe_notifications',
    history_range: 'chart_viewed',
    history_range_changed: 'chart_viewed',
    search_used: 'search_fuel_price',
    favorite_added: 'favorite_station',
};

function canTrack(): boolean {
    return typeof window !== 'undefined' && typeof window.gtag === 'function';
}

function emitGtag(name: string, params: Record<string, string | number | boolean>): void {
    window.gtag!('event', name, params);
}

/** Fire a GA4 event when the gtag snippet is present. */
export function trackEvent(name: string, params: AnalyticsParams = {}): void {
    if (!canTrack()) return;
    const cleaned: Record<string, string | number | boolean> = {};
    for (const [key, value] of Object.entries(params)) {
        if (value === undefined || value === null || value === '') continue;
        cleaned[key] = value;
    }
    emitGtag(name, cleaned);
    const alias = EVENT_ALIASES[name];
    if (alias && alias !== name) {
        emitGtag(alias, cleaned);
    }
}

/** Virtual page view for SPA tab / deep-link changes. */
export function trackPageView(path: string, title?: string): void {
    if (!canTrack()) return;
    const location =
        typeof window !== 'undefined' ? `${window.location.origin}${path}` : path;
    window.gtag!('event', 'page_view', {
        page_path: path,
        page_title: title || document.title,
        page_location: location,
    });
}

/**
 * Track scroll depth once per milestone (25/50/75/90).
 * Returns a cleanup function.
 */
export function initScrollDepthTracking(): () => void {
    if (typeof window === 'undefined') return () => undefined;

    const seen = new Set<number>();
    let ticking = false;

    const measure = (): void => {
        ticking = false;
        const doc = document.documentElement;
        const scrollTop = window.scrollY || doc.scrollTop;
        const height = doc.scrollHeight - window.innerHeight;
        if (height <= 0) return;
        const percent = Math.min(100, Math.round((scrollTop / height) * 100));
        for (const milestone of SCROLL_MILESTONES) {
            if (percent >= milestone && !seen.has(milestone)) {
                seen.add(milestone);
                trackEvent('scroll', { percent_scrolled: milestone });
            }
        }
    };

    const onScroll = (): void => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(measure);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    measure();

    return () => window.removeEventListener('scroll', onScroll);
}

/** Debounce helper for high-frequency calculator inputs. */
export function debounceTrack(
    name: string,
    waitMs = 1200,
): (params?: AnalyticsParams) => void {
    let timer: ReturnType<typeof setTimeout> | null = null;
    return (params: AnalyticsParams = {}) => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            trackEvent(name, params);
            timer = null;
        }, waitMs);
    };
}
