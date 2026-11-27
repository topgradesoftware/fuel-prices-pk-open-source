type SeoBag = {
    site_name?: string;
    site_url?: string;
    locale?: string;
    hreflang?: string;
    default_description?: string;
    default_keywords?: string;
    og_image?: Record<string, unknown>;
    pages?: Record<string, SeoPageMeta>;
    home_json_ld?: unknown[];
};

type SeoPageMeta = {
    title?: string;
    description?: string;
    keywords?: string;
    robots?: string;
    canonical?: string;
    site_name?: string;
    og?: { type?: string; image?: Record<string, unknown> };
    json_ld?: unknown[];
};

export function resolveSeoMeta(
    shared: SeoBag | null | undefined,
    {
        page = '',
        title = '',
        description = '',
        keywords = '',
        path = '',
        robots = '',
    }: {
        page?: string;
        title?: string;
        description?: string;
        keywords?: string;
        path?: string;
        robots?: string;
    } = {},
) {
    const bag = shared ?? {};
    const pages = bag.pages ?? {};
    const pageMeta = page ? pages[page] : null;

    const resolvedTitle = title || pageMeta?.title || bag.site_name || 'Fuel Prices PK';
    const resolvedDescription =
        description || pageMeta?.description || bag.default_description || '';
    const resolvedKeywords = keywords || pageMeta?.keywords || bag.default_keywords || '';
    const resolvedRobots = robots || pageMeta?.robots || 'index, follow';

    let canonical = pageMeta?.canonical || '';
    if (!canonical && path) {
        const base = (bag.site_url || '').replace(/\/$/, '');
        canonical = path === '/' ? `${base}/` : `${base}/${path.replace(/^\//, '')}`;
    }

    return {
        title: resolvedTitle,
        description: resolvedDescription,
        keywords: resolvedKeywords,
        robots: resolvedRobots,
        canonical,
        site_name: pageMeta?.site_name || bag.site_name || 'Fuel Prices PK',
        hreflang: bag.hreflang || (bag.locale ? String(bag.locale).replace('_', '-') : 'en-GB'),
        og_type: pageMeta?.og?.type || 'website',
        og_image: (pageMeta?.og?.image || bag.og_image || {}) as {
            url?: string;
            width?: number;
            height?: number;
            type?: string;
            alt?: string;
        },
        json_ld: pageMeta?.json_ld || [],
        homeJsonLd: bag.home_json_ld || [],
        theme_color: '#f2a63f',
    };
}
