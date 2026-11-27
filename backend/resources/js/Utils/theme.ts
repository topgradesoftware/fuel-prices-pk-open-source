import { AccentColors, DEFAULT_BASE, DEFAULT_ACCENT, type BaseMode } from '../Theme/OilPriceThemes';

const BASE_KEY   = 'fuelpk.base';
const ACCENT_KEY = 'fuelpk.accent';

// ── Storage helpers ──────────────────────────────────────────────────────────

export function getStoredBase(): BaseMode {
    try {
        const v = localStorage.getItem(BASE_KEY);
        if (v === 'dark' || v === 'light') return v;

        // Legacy fallback
        const old = localStorage.getItem('fuelpk.theme') || localStorage.getItem('theme');
        if (old) {
            const wasLight = ['light', 'olive', 'sterile', 'slate', 'hospital',
                'campus', 'international', 'decent', 'modern', 'ocean', 'navy',
                'clinical', 'medgreen', 'finance-gold', 'ledger', 'amber-light',
                'travel-sunset', 'travel-sky'];
            return wasLight.includes(old) ? 'light' : 'dark';
        }
    } catch { /* ignore */ }
    return DEFAULT_BASE;
}

export function getStoredAccent(): string {
    try {
        const v = localStorage.getItem(ACCENT_KEY);
        if (v && AccentColors[v]) return v;

        // Legacy: map old theme keys to accent
        const old = localStorage.getItem('fuelpk.theme') || localStorage.getItem('theme');
        const legacyMap: Record<string, string> = {
            light: 'amber', dark: 'amber',
            blue: 'blue', 'dark-blue': 'blue', sky: 'sky', 'travel-sky': 'sky',
            emerald: 'emerald', 'dark-green': 'emerald', medgreen: 'emerald',
            sunset: 'rose', rose: 'rose', crimson: 'crimson',
            purple: 'purple', 'dark-purple': 'purple', midnight: 'violet',
            teal: 'teal', 'dark-teal': 'teal', academic: 'teal',
            orange: 'orange', 'dark-orange': 'orange', 'travel-sunset': 'orange',
            gold: 'gold', 'finance-gold': 'gold', amber: 'amber',
            green: 'green', olive: 'olive', ledger: 'green',
            navy: 'navy', hospital: 'navy', campus: 'navy',
            modern: 'indigo', decent: 'blue', premium: 'indigo',
            clinical: 'cyan', ocean: 'sky',
            'pos-carbon': 'amber', 'pos-titanium': 'amber', 'pos-modern': 'indigo',
        };
        if (old && legacyMap[old]) return legacyMap[old];
    } catch { /* ignore */ }
    return DEFAULT_ACCENT;
}

function save(base: BaseMode, accent: string): void {
    try {
        localStorage.setItem(BASE_KEY, base);
        localStorage.setItem(ACCENT_KEY, accent);
        localStorage.setItem('theme', base);
        localStorage.setItem('topgrade_theme', base);
        localStorage.setItem('fuelpk.theme', `${base}-${accent}`);
    } catch { /* ignore */ }
}

// ── Apply ────────────────────────────────────────────────────────────────────

/**
 * Apply full theme — ALL 11 CSS variables are set from the accent's
 * dark or light palette. This gives a complete visual transformation,
 * not just a color tint.
 */
export function applyTheme(base: BaseMode, accent: string): void {
    const resolvedBase   = (base === 'dark' || base === 'light') ? base : DEFAULT_BASE;
    const resolvedAccent = AccentColors[accent] ? accent : DEFAULT_ACCENT;

    const root    = document.documentElement;
    const palette = AccentColors[resolvedAccent][resolvedBase];

    // Apply ALL CSS variables — full transformation
    Object.entries(palette).forEach(([key, val]) => root.style.setProperty(key, val));

    // data-theme drives App.vue :global CSS blocks (fallback layer)
    root.setAttribute('data-theme', resolvedBase);
    root.setAttribute('data-accent', resolvedAccent);

    // Remove old theme-* classes, add new ones
    Array.from(root.classList)
        .filter(c => c.startsWith('theme-'))
        .forEach(c => root.classList.remove(c));
    root.classList.add(`theme-${resolvedBase}`, `accent-${resolvedAccent}`);

    // Dark mode helpers
    if (resolvedBase === 'dark') {
        root.classList.add('dark');
        root.style.colorScheme = 'dark';
    } else {
        root.classList.remove('dark');
        root.style.colorScheme = 'light';
    }

    save(resolvedBase, resolvedAccent);
}

export function initTheme(): void {
    applyTheme(getStoredBase(), getStoredAccent());
}
