<script setup lang="ts">
import NewsTicker from '@/Components/NewsTicker.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import {
    debounceTrack,
    initScrollDepthTracking,
    trackEvent,
    trackPageView,
} from '@/composables/useAnalytics';
import {
    parseGoogleAuthBody,
    parseNearbyQuery,
    parsePushSubscribe,
    toQuery,
} from '@/lib/schemas';
import { usePage, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import SearchSelect from '../../Components/SearchSelect.vue';
import ThemeSwitcher from '../../Components/ThemeSwitcher.vue';
import AppSidebar from '../../Components/AppSidebar.vue';
import { applyTheme, initTheme, getStoredBase, getStoredAccent } from '../../Utils/theme';
import { AccentColors } from '../../Theme/OilPriceThemes';
import { t, type Locale, type MsgKey } from '../../i18n/fuel';
import {
    canPromptInstall,
    isStandalone,
    onPwaInstallAvailable,
    onPwaInstalled,
    promptPwaInstall,
} from '../../pwa';

type AuthUser = {
    id: number;
    name: string;
    email: string | null;
    avatar_url: string | null;
    is_guest: boolean;
    auth_provider: string;
};

type AuthConfig = {
    google_login: boolean;
    google_client_id: string | null;
};

declare global {
    interface Window {
        google?: {
            accounts: {
                id: {
                    initialize: (config: Record<string, unknown>) => void;
                    renderButton: (el: HTMLElement, config: Record<string, unknown>) => void;
                    cancel: () => void;
                };
            };
        };
    }
}

type Country = {
    code: string;
    name: string;
    currency: string;
    currency_symbol: string;
    unit_volume: string;
};
type City = { id: string; name: string; province?: string | null; country_code?: string | null };
type Company = { id: string; name: string; short: string; country_code?: string | null };
type FuelType = { id: string; name: string; unit: string; category: string };
type PriceRow = {
    company_id: string | null;
    city_id: string;
    country_code?: string | null;
    fuel_type_id: string | null;
    price: number;
    previous_price: number | null;
    unit: string;
    currency?: string | null;
    effective_date: string | null;
    source?: string | null;
};
type HistoryPoint = { date: string; price: number };
type Station = {
    id: string;
    name: string;
    address: string;
    lat: number;
    lng: number;
    is_open_24h: boolean;
    is_open_now?: boolean | null;
    distance_km?: number | null;
    company_id: string;
    company: Company | string | null;
    city_id: string;
    city: City | string | null;
    country_code?: string | null;
    maps_url: string;
    source?: string;
};
type Vehicle = { id: string; name: string; mileage: number; tank: number; fuel: string };
type PriceSnapshot = { petrol: number; hsd: number; savedAt: string };

const page = usePage();
const homeFaqs = computed(() => {
    const seo = page.props.seo as { home_faq?: Array<{ question?: string; answer?: string }> } | undefined;
    return Array.isArray(seo?.home_faq) ? seo.home_faq : [];
});

const props = withDefaults(
    defineProps<{
        countries?: Country[];
        default_country?: string;
        cities?: City[];
        companies?: Company[];
        fuel_types?: FuelType[];
        prices?: PriceRow[];
        history?: Record<string, HistoryPoint[]>;
        stations?: Station[];
        last_sync?: { status: string; finished_at: string | null } | null;
        auth?: AuthConfig | null;
        initial_tab?: Tab;
        seo_page?: string;
        initial_calc_mode?: 'fuel' | 'tank' | 'trip' | null;
        fuel_news?: Array<{ id: number; title: string; slug: string; url?: string; published_at?: string | null }>;
    }>(),
    {
        countries: () => [],
        default_country: 'PK',
        cities: () => [],
        companies: () => [],
        fuel_types: () => [],
        prices: () => [],
        history: () => ({}),
        stations: () => [],
        fuel_news: () => [],
        last_sync: null,
        auth: null,
        initial_tab: 'home',
        seo_page: 'home',
        initial_calc_mode: null,
    },
);

type Tab = 'home' | 'compare' | 'history' | 'stations' | 'calc' | 'garage' | 'more';

const VALID_TABS: Tab[] = ['home', 'compare', 'history', 'stations', 'calc', 'garage', 'more'];

function resolveInitialTab(value: string | undefined): Tab {
    return VALID_TABS.includes(value as Tab) ? (value as Tab) : 'home';
}

const tab = ref<Tab>(resolveInitialTab(props.initial_tab));
const sideNavCollapsed = ref(false);
const openSection = ref<string>('main');

try {
    const saved = localStorage.getItem('fuelpk.openNavSection');
    if (saved !== null) {
        openSection.value = saved;
    }
} catch {
    /* ignore */
}

function toggleNavSection(sec: string): void {
    if (openSection.value === sec) {
        openSection.value = '';
    } else {
        openSection.value = sec;
    }
    try {
        localStorage.setItem('fuelpk.openNavSection', openSection.value);
    } catch {
        /* ignore */
    }
}

function toggleSideNav(): void {
    sideNavCollapsed.value = !sideNavCollapsed.value;
    try {
        localStorage.setItem('fuelpk.sideNavCollapsed', sideNavCollapsed.value ? '1' : '0');
    } catch {
        /* ignore */
    }
}
const seoPageKey = computed(() => props.seo_page || 'home');
const trackCalcInput = debounceTrack('calculator_used', 1500);
let stopScrollTracking: (() => void) | null = null;
let analyticsReady = false;

function tabAnalyticsPath(name: Tab): string {
    if (name === 'calc') return '/fuel-calculator';
    if (name === 'history') return '/price-history';
    if (name === 'home') return '/';
    return `/app/${name}`;
}

function tabAnalyticsTitle(name: Tab): string {
    const titles: Record<Tab, string> = {
        home: 'Home',
        compare: 'Compare',
        history: 'Price history',
        stations: 'Stations',
        calc: 'Fuel calculator',
        garage: 'Garage',
        more: 'More',
    };
    return titles[name];
}
const isOffline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);

const locale = ref<Locale>('en');
type ThemePref = string;
type ThemeResolved = 'dark' | 'light';

const themePref = ref<string>(getStoredBase());
const resolvedTheme = ref<ThemeResolved>(getStoredBase());
const currentAccent = ref<string>(getStoredAccent());
const themePickerOpen = ref(false);

const accentList = Object.entries(AccentColors).map(([id, def]) => ({
    id,
    name: def.name,
    icon: def.icon,
    color: def.dark['--amber'],
}));
let systemThemeMql: MediaQueryList | null = null;
const alertsEnabled = ref(false);
const pwaInstalled = ref(false);
const pwaInstallable = ref(false);
const pwaBannerOpen = ref(false);
const pwaBannerDismissed = ref(false);
let stopPwaInstallListen: (() => void) | null = null;
let stopPwaInstalledListen: (() => void) | null = null;

const AUTH_TOKEN_KEY = 'fuelpk.auth_token';
const AUTH_USER_KEY = 'fuelpk.auth_user';
const authUser = ref<AuthUser | null>(null);
const authToken = ref<string | null>(null);
const authBusy = ref(false);
const authError = ref('');
const googleBtnHomeEl = ref<HTMLElement | null>(null);
const googleBtnMoreEl = ref<HTMLElement | null>(null);
const googleBtnCompulsoryEl = ref<HTMLElement | null>(null);
const googleLoginEnabled = computed(
    () => Boolean(props.auth?.google_login && props.auth?.google_client_id),
);

const allCountries = computed(() =>
    (props.countries ?? []).length
        ? (props.countries ?? [])
        : [{
            code: 'PK',
            name: 'Pakistan',
            currency: 'PKR',
            currency_symbol: 'Rs',
            unit_volume: 'L',
        }],
);

function storedCountryCode(): string {
    try {
        const saved = localStorage.getItem('fuelpk.country');
        if (saved && allCountries.value.some((c) => c.code === saved)) return saved;
    } catch {
        /* ignore */
    }
    return props.default_country || 'PK';
}

const countryCode = ref(storedCountryCode());

const selectedCountry = computed(
    () => allCountries.value.find((c) => c.code === countryCode.value) ?? allCountries.value[0],
);

const currencyLabel = computed(() => selectedCountry.value?.currency_symbol || 'Rs');

/** Cities for the active country (fetched per-country; props only ship default PK). */
const cities = ref<City[]>(
    (props.cities ?? []).filter((c) => (c.country_code || 'PK') === countryCode.value),
);
const companies = ref<Company[]>(
    (props.companies ?? []).filter((c) => (c.country_code || 'PK') === countryCode.value),
);
const catalogLoading = ref(false);
const fuelTypes = computed(() => props.fuel_types ?? []);
const priceRows = computed(() =>
    (props.prices ?? []).filter((p) => (p.country_code || 'PK') === countryCode.value),
);
const stationRows = computed(() =>
    (props.stations ?? []).filter((s) => (s.country_code || 'PK') === countryCode.value),
);

const countryOptions = computed(() =>
    allCountries.value.map((c) => ({
        id: c.code,
        label: c.name,
        sub: c.currency,
    })),
);

function readStoredCityId(): string {
    try {
        return localStorage.getItem('fuelpk.lastCity') || '';
    } catch {
        return '';
    }
}

function persistCityId(id: string): void {
    if (!id) return;
    try {
        localStorage.setItem('fuelpk.lastCity', id);
    } catch {
        /* ignore */
    }
}

/** Prefer last/GPS city over National so the home view opens on a real place. */
function defaultCityForCountry(): string {
    const stored = readStoredCityId();
    if (stored && stored !== 'national' && cities.value.some((c) => c.id === stored)) {
        return stored;
    }
    const favorite = (() => {
        try {
            return localStorage.getItem('fuelpk.favoriteCity') || '';
        } catch {
            return '';
        }
    })();
    if (favorite && favorite !== 'national' && cities.value.some((c) => c.id === favorite)) {
        return favorite;
    }
    const firstLocal = cities.value.find((c) => c.id !== 'national');
    return firstLocal?.id
        || cities.value.find((c) => c.id === 'national')?.id
        || cities.value[0]?.id
        || 'national';
}

const city = ref(defaultCityForCountry());

async function loadCountryCatalog(code: string): Promise<void> {
    catalogLoading.value = true;
    try {
        const [citiesRes, companiesRes] = await Promise.all([
            fetch(`/api/v1/cities?country=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            }),
            fetch(`/api/v1/companies?country=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            }),
        ]);
        if (citiesRes.ok) {
            const json = (await citiesRes.json()) as {
                data?: { items?: Array<{ slug?: string; id?: string | number; name: string; province?: string | null }> };
            };
            const items = json.data?.items ?? [];
            const mapped: City[] = [
                { id: 'national', name: 'National', province: null, country_code: code },
                ...items.map((c) => ({
                    id: String(c.slug ?? c.id),
                    name: c.name,
                    province: c.province ?? null,
                    country_code: code,
                })),
            ];
            // Dedupe national if API ever returns it.
            const seen = new Set<string>();
            cities.value = mapped.filter((c) => {
                if (seen.has(c.id)) return false;
                seen.add(c.id);
                return true;
            });
        }
        if (companiesRes.ok) {
            const json = (await companiesRes.json()) as {
                data?: { items?: Array<{ slug?: string; id?: string | number; name: string; short_name?: string }> };
            };
            const items = json.data?.items ?? [];
            companies.value = items.map((c) => ({
                id: String(c.slug ?? c.id),
                name: c.name,
                short: c.short_name || c.name,
                country_code: code,
            }));
        }
    } catch {
        /* keep previous catalog */
    } finally {
        catalogLoading.value = false;
    }
}

async function applyCountrySelection(code: string): Promise<void> {
    if (!code || code === countryCode.value) return;
    countryCode.value = code;
    try {
        localStorage.setItem('fuelpk.country', code);
    } catch {
        /* ignore */
    }
    await loadCountryCatalog(code);
    city.value = defaultCityForCountry();
    trackEvent('country_changed', { country: code });
    void initUserLocation();
}
const fuelId = ref(
    fuelTypes.value.find((f) => f.id === 'petrol')?.id || fuelTypes.value[0]?.id || 'petrol',
);
const stationCompany = ref('');
const stationQuery = ref('');
const nearbyStations = ref<Station[]>([]);
const nearbyLoading = ref(false);
const nearbyError = ref('');
const nearbyOrigin = ref<{ lat: number; lng: number } | null>(null);
/** Default to Near me so Stations uses the user's GPS when allowed. */
const useNearby = ref(true);
const locationReady = ref(false);
/** Bumps when user leaves Near me so in-flight GPS requests cannot overwrite city. */
let nearbyRequestGen = 0;

/** Approx city centers (PK) for matching GPS → city filter. */
const CITY_COORDS: Record<string, [number, number]> = {
    karachi: [24.8607, 67.0011],
    lahore: [31.5204, 74.3587],
    islamabad: [33.6844, 73.0479],
    rawalpindi: [33.5651, 73.0169],
    faisalabad: [31.4504, 73.135],
    multan: [30.1575, 71.5249],
    peshawar: [34.0151, 71.5249],
    quetta: [30.1798, 66.975],
    hyderabad: [25.396, 68.3578],
    gujranwala: [32.1877, 74.1945],
    sialkot: [32.4945, 74.5229],
    bahawalpur: [29.3956, 71.6836],
    sargodha: [32.0836, 72.6711],
    'rahim-yar-khan': [28.4212, 70.2989],
    sukkur: [27.7052, 68.8574],
    abbottabad: [34.1688, 73.2215],
    mardan: [34.1989, 72.0231],
    attock: [33.7667, 72.36],
    jhang: [31.2781, 72.3317],
    chiniot: [31.72, 72.9789],
    sheikhupura: [31.7131, 73.9783],
    kasur: [31.1167, 74.45],
    okara: [30.8081, 73.4458],
    sahiwal: [30.6708, 73.1064],
    jhelum: [32.9333, 73.7333],
    gujrat: [32.5733, 74.0789],
    'dera-ghazi-khan': [30.05, 70.6333],
    muzaffargarh: [30.0703, 71.1933],
    mianwali: [32.5853, 71.5436],
    chakwal: [32.93, 72.8556],
    murree: [33.907, 73.3903],
    'wah-cantonment': [33.7715, 72.7511],
    wah: [33.7715, 72.7511],
    larkana: [27.559, 68.212],
    'mirpur-khas': [25.527, 69.012],
    nawabshah: [26.2442, 68.41],
    jacobabad: [28.281, 68.438],
    thatta: [24.747, 67.924],
    'dera-ismail-khan': [31.831, 70.901],
    'd-i-khan': [31.831, 70.901],
    bannu: [32.985, 70.604],
    kohat: [33.587, 71.443],
    nowshera: [34.015, 71.975],
    swabi: [34.12, 72.47],
    mansehra: [34.333, 73.2],
    haripur: [33.997, 72.935],
    mingora: [34.7717, 72.36],
    swat: [35.2227, 72.4258],
    gwadar: [25.1216, 62.3254],
    gawadar: [25.1216, 62.3254],
    turbat: [26.004, 63.043],
    hub: [25.04, 66.88],
    chaman: [30.921, 66.45],
    gilgit: [35.9208, 74.3144],
    skardu: [35.2971, 75.6339],
    muzaffarabad: [34.37, 73.471],
    mirpur: [33.148, 73.752],
    kotli: [33.518, 73.902],
};

function haversineKm(aLat: number, aLng: number, bLat: number, bLng: number): number {
    const toRad = (d: number) => (d * Math.PI) / 180;
    const R = 6371;
    const dLat = toRad(bLat - aLat);
    const dLng = toRad(bLng - aLng);
    const s =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(s));
}

function nearestCityId(lat: number, lng: number): string {
    let bestId = 'national';
    let bestKm = Number.POSITIVE_INFINITY;
    for (const c of cities.value) {
        const coords = CITY_COORDS[c.id];
        if (!coords) continue;
        const km = haversineKm(lat, lng, coords[0], coords[1]);
        if (km < bestKm) {
            bestKm = km;
            bestId = c.id;
        }
    }
    // Only snap when within ~120 km of a known city center.
    return bestKm <= 120 ? bestId : 'national';
}

function getCurrentPosition(): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            reject(Object.assign(new Error('Location not supported'), { code: 0 }));
            return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 120000,
        });
    });
}
const compareCompanyQuery = ref('');
type HistoryRange = 'd7' | 'd30' | 'd180' | 'd365';
type HistoryLayout = 'line' | 'area' | 'bars' | 'cards';
type HistoryFuelFocus = 'all' | 'petrol' | 'hsd' | 'lpg';
const HISTORY_FUEL_IDS = ['petrol', 'hsd', 'lpg'] as const;
const HISTORY_RANGE_DAYS: Record<HistoryRange, number> = {
    d7: 7,
    d30: 30,
    d180: 180,
    d365: 365,
};
function resolveHistoryRange(raw: string | null): HistoryRange {
    if (raw === 'd7' || raw === 'd30' || raw === 'd180' || raw === 'd365') return raw;
    if (raw === 'weekly') return 'd7';
    if (raw === 'monthly') return 'd30';
    if (raw === 'yearly') return 'd365';
    return 'd30';
}
const historyRange = ref<HistoryRange>(
    typeof window !== 'undefined'
        ? resolveHistoryRange(new URLSearchParams(window.location.search).get('range'))
        : 'd30',
);
const historyLayout = ref<HistoryLayout>('area');
const historyFuelFocus = ref<HistoryFuelFocus>('all');
const calc = ref<'fuel' | 'tank' | 'trip'>(
    props.initial_calc_mode === 'tank' || props.initial_calc_mode === 'trip' || props.initial_calc_mode === 'fuel'
        ? props.initial_calc_mode
        : 'fuel',
);

/** Retention MVP (local). */
const favoriteCityId = ref('');
const favoriteFuelId = ref('petrol');
const recentCities = ref<string[]>([]);
const recentCalcs = ref<string[]>([]);

/** Major cities pinned near the top of the city dropdown (chips removed — dropdown only). */
const PINNED_CITY_IDS_BY_COUNTRY: Record<string, readonly string[]> = {
    PK: [
        'national',
        'karachi',
        'lahore',
        'islamabad',
        'rawalpindi',
        'peshawar',
        'quetta',
        'multan',
        'faisalabad',
    ],
    IN: [
        'national',
        'new-delhi',
        'mumbai',
        'bengaluru',
        'hyderabad',
        'chennai',
        'kolkata',
        'pune',
    ],
    BD: [
        'national',
        'dhaka',
        'chattogram',
        'khulna',
        'rajshahi',
        'sylhet',
    ],
};
const POPULAR_FUEL_IDS = ['petrol', 'hsd', 'lpg', 'hobc'] as const;

const cityOptions = computed(() => {
    const pinned = PINNED_CITY_IDS_BY_COUNTRY[countryCode.value] ?? ['national'];
    const pinRank = new Map<string, number>(pinned.map((id, i) => [id, i]));
    return [...cities.value]
        .sort((a, b) => {
            const pa = pinRank.get(a.id);
            const pb = pinRank.get(b.id);
            if (pa !== undefined || pb !== undefined) {
                return (pa ?? 999) - (pb ?? 999);
            }
            return a.name.localeCompare(b.name);
        })
        .map((c) => ({
            id: c.id,
            label: c.name,
            sub: c.province || undefined,
        }));
});

const popularFuels = computed(() =>
    POPULAR_FUEL_IDS.map((id) => fuelTypes.value.find((f) => f.id === id)).filter(
        (f): f is NonNullable<typeof f> => Boolean(f),
    ),
);

/** Brief first-paint skeleton so mobile home never flashes empty. */
const homeBooting = ref(true);
const showCityTools = computed(
    () => tab.value === 'home' || tab.value === 'compare' || tab.value === 'stations',
);
const showHomeSkeleton = computed(
    () => homeBooting.value || (tab.value === 'home' && priceRows.value.length === 0),
);

const companyOptions = computed(() =>
    companies.value.map((c) => ({
        id: c.id,
        label: c.short || c.name,
        sub: c.name,
    })),
);

const fuelOptions = computed(() =>
    fuelTypes.value.map((f) => ({
        id: f.id,
        label: f.name,
        sub: f.category,
    })),
);

const DEFAULT_VEHICLES: Vehicle[] = [
    { id: 'honda_city', name: 'Honda City', mileage: 15, tank: 42, fuel: 'petrol' },
    { id: 'cd70', name: 'CD 70', mileage: 55, tank: 10, fuel: 'petrol' },
];

const vehicles = ref<Vehicle[]>([]);
const selectedVehicleId = ref('');

const newVehicleName = ref('');
const newVehicleMileage = ref(15);
const newVehicleTank = ref(40);
const newVehicleFuel = ref('petrol');

const distance = ref(50);
const mileage = ref(15);
const tankCapacity = ref(42);
const currentLevel = ref(25);
const tripDistance = ref(120);

const FUEL_COLOR: Record<string, string> = {
    petrol: '#F2A63F',
    hi_octane: '#E8604C',
    hsd: '#5FB3D9',
    lpg: '#4FBE8D',
    kerosene: '#B592E8',
    ldo: '#8B95A1',
    octane_plus: '#E8604C',
};

/** Brand-inspired accents for compare / station cards (dark UI friendly). */
const COMPANY_THEME: Record<string, { accent: string; soft: string }> = {
    pso: { accent: '#00A651', soft: 'rgba(0, 166, 81, 0.16)' },
    shell: { accent: '#FBCE07', soft: 'rgba(251, 206, 7, 0.14)' },
    wafi: { accent: '#DD1D21', soft: 'rgba(221, 29, 33, 0.14)' },
    total: { accent: '#ED1C24', soft: 'rgba(237, 28, 36, 0.14)' },
    attock: { accent: '#2E7D32', soft: 'rgba(46, 125, 50, 0.16)' },
    apl: { accent: '#2E7D32', soft: 'rgba(46, 125, 50, 0.16)' },
    go: { accent: '#F57C00', soft: 'rgba(245, 124, 0, 0.16)' },
    hascol: { accent: '#1565C0', soft: 'rgba(21, 101, 192, 0.16)' },
    puma: { accent: '#C62828', soft: 'rgba(198, 40, 40, 0.16)' },
    cnergyico: { accent: '#00838F', soft: 'rgba(0, 131, 143, 0.16)' },
    be_energy: { accent: '#E31837', soft: 'rgba(227, 24, 55, 0.14)' },
    euro_oil: { accent: '#3949AB', soft: 'rgba(57, 73, 171, 0.16)' },
    taj: { accent: '#6A1B9A', soft: 'rgba(106, 27, 154, 0.16)' },
    askar: { accent: '#455A64', soft: 'rgba(69, 90, 100, 0.18)' },
    zoom: { accent: '#00ACC1', soft: 'rgba(0, 172, 193, 0.16)' },
    flow: { accent: '#039BE5', soft: 'rgba(3, 155, 229, 0.16)' },
    jinn: { accent: '#8D6E63', soft: 'rgba(141, 110, 99, 0.18)' },
    vital: { accent: '#43A047', soft: 'rgba(67, 160, 71, 0.16)' },
    quality1: { accent: '#5E35B1', soft: 'rgba(94, 53, 177, 0.16)' },
    oto: { accent: '#EF6C00', soft: 'rgba(239, 108, 0, 0.16)' },
    national: { accent: '#01411C', soft: 'rgba(1, 65, 28, 0.22)' },
    pakwheels: { accent: '#1E88E5', soft: 'rgba(30, 136, 229, 0.16)' },
};

function companyTheme(companyId: string | null | undefined): { accent: string; soft: string } {
    if (!companyId) {
        return { accent: '#F2A63F', soft: 'rgba(242, 166, 63, 0.12)' };
    }
    return COMPANY_THEME[companyId] || { accent: '#F2A63F', soft: 'rgba(242, 166, 63, 0.12)' };
}

function accentMarkInk(accent: string): string {
    const hex = accent.replace('#', '').trim();
    if (hex.length < 6) {
        return '#ffffff';
    }
    const r = Number.parseInt(hex.slice(0, 2), 16) / 255;
    const g = Number.parseInt(hex.slice(2, 4), 16) / 255;
    const b = Number.parseInt(hex.slice(4, 6), 16) / 255;
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    // Yellow/light accents keep dark letters; others use white like the corner badges.
    return luminance > 0.65 ? '#0b0f14' : '#ffffff';
}

function companyCardStyle(companyId: string | null | undefined): Record<string, string> {
    const theme = companyTheme(companyId);
    return {
        '--co-accent': theme.accent,
        '--co-soft': theme.soft,
        '--co-mark-ink': accentMarkInk(theme.accent),
    };
}

const FUEL_THEME: Record<string, { accent: string; soft: string }> = {
    petrol: { accent: '#00A651', soft: 'rgba(0, 166, 81, 0.16)' },
    hobc: { accent: '#F2A63F', soft: 'rgba(242, 166, 63, 0.16)' },
    hsd: { accent: '#1565C0', soft: 'rgba(21, 101, 192, 0.16)' },
    lpg: { accent: '#6A1B9A', soft: 'rgba(106, 27, 154, 0.16)' },
    kerosene: { accent: '#8D6E63', soft: 'rgba(141, 110, 99, 0.18)' },
    ldo: { accent: '#00838F', soft: 'rgba(0, 131, 143, 0.16)' },
    octane_plus: { accent: '#F57C00', soft: 'rgba(245, 124, 0, 0.16)' },
    hi_octane: { accent: '#E31837', soft: 'rgba(227, 24, 55, 0.14)' },
    premium: { accent: '#C9A227', soft: 'rgba(201, 162, 39, 0.16)' },
    e10: { accent: '#00ACC1', soft: 'rgba(0, 172, 193, 0.16)' },
    cng: { accent: '#455A64', soft: 'rgba(69, 90, 100, 0.18)' },
};

function fuelCardStyle(fuelId: string): Record<string, string> {
    const theme = FUEL_THEME[fuelId] || { accent: '#F2A63F', soft: 'rgba(242, 166, 63, 0.12)' };
    return {
        '--co-accent': theme.accent,
        '--co-soft': theme.soft,
        '--co-mark-ink': accentMarkInk(theme.accent),
    };
}

function fuelMark(name: string): string {
    const letters = name.replace(/[^A-Za-z0-9]/g, '');
    return (letters.slice(0, 2) || 'FU').toUpperCase();
}

function homePriceRow(fuelId: string): PriceRow | null {
    // SKO / LDO: prefer PSO/OGRA/news notified ex-depot rates over TrackMate
    // placeholders that often lag by Rs 50+.
    if (fuelId === 'kerosene' || fuelId === 'ldo' || fuelId === 'lsd') {
        const official = pickBestPrice(
            priceRows.value.filter(
                (p) =>
                    p.fuel_type_id === fuelId
                    && (p.city_id === 'national' || !p.city_id)
                    && (p.source === 'ogra_daily'
                        || p.source === 'pso_official'
                        || p.source === 'propakistani'),
            ),
        );
        if (official) {
            return official;
        }
    }

    return (
        findPrice(fuelId, undefined, 'national')
        || findPrice(fuelId, 'pso', city.value)
        || findPrice(fuelId)
    );
}

function homePrice(fuelId: string): number {
    // Prefer the newest national notified rate; do not hard-lock to PSO —
    // stale PSO rows were beating fresher TrackMate / OGRA aggregates.
    return homePriceRow(fuelId)?.price ?? 0;
}

/**
 * Prior notified rate for the home delta badge.
 * Never trust a contaminated previous_price alone (composite leftovers produced
 * fake +30 spikes). Prefer an older national row / history point, then a sane
 * stored previous_price.
 */
function priorNotifiedPrice(fuelId: string, current: PriceRow): number | null {
    const curDate = current.effective_date;
    const curPrice = current.price;

    if (curDate) {
        const older = priceRows.value
            .filter(
                (p) =>
                    p.fuel_type_id === fuelId
                    && p.city_id === 'national'
                    && !!p.effective_date
                    && p.effective_date < curDate
                    && p.price > 0
                    && Math.abs(p.price - curPrice) > 0.009,
            )
            .sort((a, b) => (b.effective_date || '').localeCompare(a.effective_date || ''));
        if (older.length > 0) {
            const bestDay = older[0].effective_date;
            const sameDay = older.filter((p) => p.effective_date === bestDay);
            return pickBestPrice(sameDay)?.price ?? older[0].price;
        }

        const hist = [...(historyMap.value[fuelId] || [])]
            .filter((p) => p.date < curDate && p.price > 0 && Math.abs(p.price - curPrice) > 0.009)
            .sort((a, b) => b.date.localeCompare(a.date));
        if (hist.length > 0) {
            return hist[0].price;
        }
    }

    const stored = current.previous_price;
    if (stored === null || stored === undefined || stored <= 0) {
        return null;
    }
    const delta = Math.abs(curPrice - stored);
    // Fortnight OGRA moves are almost always well under Rs 25.
    if (delta <= 25 || delta / curPrice <= 0.08) {
        return stored;
    }
    return null;
}

function msg(key: MsgKey): string {
    if (key === 'currencyRs') {
        return currencyLabel.value;
    }
    return t(locale.value, key);
}

function formatDateLabel(d: Date): string {
    return d.toLocaleDateString(locale.value === 'ur' ? 'ur-PK' : 'en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

const todayLabel = computed(() => formatDateLabel(new Date()));

/** Most recent effective_date among the primary fuels shown on home. */
const latestEffectiveDate = computed<Date | null>(() => {
    let latest: number | null = null;
    for (const id of HOME_PRIMARY_IDS) {
        const row = homePriceRow(id);
        if (!row?.effective_date) continue;
        const ts = Date.parse(row.effective_date);
        if (Number.isNaN(ts)) continue;
        if (latest === null || ts > latest) latest = ts;
    }
    return latest === null ? null : new Date(latest);
});

/** Label shown under "Today's prices" — the real effective date, not the clock. */
const effectiveLabel = computed(() =>
    latestEffectiveDate.value ? formatDateLabel(latestEffectiveDate.value) : todayLabel.value,
);

/** Prices are stale when the newest effective date is more than 1 day old. */
const pricesStale = computed<boolean>(() => {
    const d = latestEffectiveDate.value;
    if (!d) return false;
    const ageDays = (Date.now() - d.getTime()) / 86_400_000;
    return ageDays > 1.5;
});

const themePrefLabel = computed(() => {
    if (themePref.value === 'system') return msg('themeSystem');
    if (themePref.value === 'dark') return msg('themeDark');
    return msg('themeLight');
});

function sourceRank(source: string | null | undefined): number {
    switch (source) {
        case 'ogra_daily':
            return 200;
        case 'propakistani':
            return 150;
        case 'pso_official':
            return 100;
        case 'trackmate':
            return 50;
        default:
            return 10;
    }
}

function pickBestPrice(rows: PriceRow[]): PriceRow | null {
    if (rows.length === 0) return null;
    // Newest effective_date wins; source trust is only a same-day tiebreaker.
    return [...rows].sort((a, b) => {
        const dateCmp = (b.effective_date || '').localeCompare(a.effective_date || '');
        if (dateCmp !== 0) return dateCmp;
        return sourceRank(b.source) - sourceRank(a.source);
    })[0] ?? null;
}

function findPrice(fuelTypeId: string, companyId?: string, cityId?: string): PriceRow | null {
    const cid = cityId ?? city.value;
    const matches = priceRows.value.filter((p) => p.fuel_type_id === fuelTypeId);

    const exactCompanyCity = pickBestPrice(
        matches.filter((p) => (!companyId || p.company_id === companyId) && p.city_id === cid),
    );
    if (exactCompanyCity) return exactCompanyCity;

    const companyNational = pickBestPrice(
        matches.filter((p) => (!companyId || p.company_id === companyId) && p.city_id === 'national'),
    );
    if (companyNational) return companyNational;

    const anyCity = pickBestPrice(
        matches.filter((p) => !companyId || p.company_id === companyId),
    );
    if (anyCity) return anyCity;

    return pickBestPrice(matches);
}

function priceFor(fuelTypeId: string, companyId?: string, cityId?: string): number {
    return findPrice(fuelTypeId, companyId, cityId)?.price ?? 0;
}

function prevFor(fuelTypeId: string, companyId?: string, cityId?: string): number | null {
    return findPrice(fuelTypeId, companyId, cityId)?.previous_price ?? null;
}

function companyLabel(company: Company | string | null | undefined): string {
    if (!company) return '';
    return typeof company === 'string' ? company : company.name;
}

function cityLabel(c: City | string | null | undefined): string {
    if (!c) return '';
    return typeof c === 'string' ? c : c.name;
}

function applyLocale(next: Locale): void {
    locale.value = next;
    localStorage.setItem('fuelpk.locale', next);
    const lang = next === 'ur' ? 'ur' : 'en';
    const dir = next === 'ur' ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;
    document.documentElement.dir = dir;
    document.body?.classList.toggle('locale-ur', next === 'ur');
    document.body?.setAttribute('dir', dir);
}

function systemPrefersDark(): boolean {
    return typeof window !== 'undefined'
        && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolveTheme(pref: ThemePref): ThemeResolved {
    if (pref === 'system') {
        return systemPrefersDark() ? 'dark' : 'light';
    }
    return pref;
}

function paintTheme(resolved: ThemeResolved): void {
    resolvedTheme.value = resolved;
    document.documentElement.dataset.theme = resolved;
    document.body.style.background = resolved === 'light' ? '#e8edf3' : '#0b0f14';
}

function applyThemePref(pref: string): void {
    const base = (pref === 'light' ? 'light' : 'dark') as 'dark' | 'light';
    themePref.value = base;
    applyTheme(base, currentAccent.value);
    resolvedTheme.value = base;
}

function applyAccentPref(accent: string): void {
    currentAccent.value = accent;
    applyTheme(themePref.value as 'dark' | 'light', accent);
}

function openThemePicker(): void {
    themePickerOpen.value = true;
}

function closeThemePicker(): void {
    themePickerOpen.value = false;
}

function onSystemThemeChange(): void {
    if (themePref.value === 'system') {
        paintTheme(resolveTheme('system'));
    }
}

function loadVehicles(): void {
    try {
        const raw = localStorage.getItem('fuelpk.vehicles');
        if (raw) {
            const parsed = JSON.parse(raw) as Vehicle[];
            if (Array.isArray(parsed) && parsed.length) {
                vehicles.value = parsed;
                return;
            }
        }
    } catch {
        /* ignore */
    }
    vehicles.value = DEFAULT_VEHICLES.map((v) => ({ ...v }));
    saveVehicles();
}

function saveVehicles(): void {
    localStorage.setItem('fuelpk.vehicles', JSON.stringify(vehicles.value));
}

function loadSelectedVehicle(): void {
    const saved = localStorage.getItem('fuelpk.selectedVehicle');
    if (saved && vehicles.value.some((v) => v.id === saved)) {
        selectedVehicleId.value = saved;
        return;
    }
    selectedVehicleId.value = vehicles.value[0]?.id || '';
}

const selectedVehicle = computed(
    () => vehicles.value.find((v) => v.id === selectedVehicleId.value) || vehicles.value[0] || null,
);

function syncCalcFromVehicle(): void {
    if (!selectedVehicle.value) return;
    mileage.value = selectedVehicle.value.mileage;
    tankCapacity.value = selectedVehicle.value.tank;
}

watch(selectedVehicleId, () => {
    localStorage.setItem('fuelpk.selectedVehicle', selectedVehicleId.value);
    syncCalcFromVehicle();
});

watch(tab, (next) => {
    if (next === 'calc') {
        syncCalcFromVehicle();
        pushRecentCalc(calc.value);
        persistCalcInputs();
    }
    if (next === 'history') {
        trackEvent('history_viewed', { surface: 'tab' });
    }
    if (next === 'compare') {
        trackEvent('comparison_used', { surface: 'tab' });
    }
    try {
        localStorage.setItem('fuelpk.lastTab', next);
    } catch {
        /* ignore */
    }
});

watch([distance, mileage, tankCapacity, currentLevel, tripDistance, calc], () => {
    persistCalcInputs();
});

watch(calc, (mode) => {
    pushRecentCalc(mode);
});

function addVehicle(): void {
    const name = newVehicleName.value.trim();
    if (!name) return;
    const vehicle: Vehicle = {
        id: `v_${Date.now()}`,
        name,
        mileage: Math.min(200, Math.max(1, Number(newVehicleMileage.value) || 15)),
        tank: Math.max(1, newVehicleTank.value),
        fuel: newVehicleFuel.value,
    };
    vehicles.value.push(vehicle);
    saveVehicles();
    selectedVehicleId.value = vehicle.id;
    newVehicleName.value = '';
    newVehicleMileage.value = 15;
    newVehicleTank.value = 40;
    newVehicleFuel.value = 'petrol';
    trackEvent('add_vehicle', { fuel_type: vehicle.fuel });
}

function deleteVehicle(id: string): void {
    vehicles.value = vehicles.value.filter((v) => v.id !== id);
    saveVehicles();
    if (selectedVehicleId.value === id) {
        selectedVehicleId.value = vehicles.value[0]?.id || '';
    }
    trackEvent('delete_vehicle');
}

function useInCalc(id: string): void {
    selectedVehicleId.value = id;
    syncCalcFromVehicle();
    tab.value = 'calc';
    trackEvent('use_vehicle_in_calc', { vehicle_id: id });
}

type HomeFuelCard = {
    id: string;
    name: string;
    unit: string;
    mark: string;
};

const HOME_PRIMARY_IDS = ['petrol', 'hsd', 'lpg'] as const;
const HOME_OCTANE_IDS = ['hobc', 'octane_plus', 'hi_octane', 'premium', 'super'] as const;
const HOME_OTHER_IDS = ['kerosene', 'ldo', 'e10', 'cng'] as const;

const OCTANE_SHORT: Record<string, string> = {
    hobc: 'HOBC',
    octane_plus: 'Octane+',
    hi_octane: 'Hi-Octane',
    premium: 'Premium',
    super: 'Super',
};

function fuelTypeById(id: string) {
    return fuelTypes.value.find((f) => f.id === id) ?? null;
}

/** Petrol, diesel, LPG — the fuels people check first. */
const homePrimaryCards = computed((): HomeFuelCard[] => {
    const cards: HomeFuelCard[] = [];
    for (const id of HOME_PRIMARY_IDS) {
        const fuel = fuelTypeById(id);
        if (!fuel || homePrice(id) <= 0) continue;
        cards.push({
            id,
            name: fuel.name,
            unit: fuel.unit,
            mark: fuelMark(fuel.name),
        });
    }
    return cards;
});

/**
 * Specialty fuels. Identical-price high-octane variants collapse into one card
 * (e.g. HOBC / Hi-Octane / Premium) so the grid is not four copies of Rs 345.
 */
const homeSpecialtyCards = computed((): HomeFuelCard[] => {
    const cards: HomeFuelCard[] = [];

    type OctaneRow = { id: (typeof HOME_OCTANE_IDS)[number]; name: string; unit: string; price: number };
    const octaneRows: OctaneRow[] = [];
    for (const id of HOME_OCTANE_IDS) {
        const fuel = fuelTypeById(id);
        const price = homePrice(id);
        if (!fuel || price <= 0) continue;
        octaneRows.push({ id, name: fuel.name, unit: fuel.unit, price: +price.toFixed(2) });
    }

    const byPrice = new Map<number, OctaneRow[]>();
    for (const row of octaneRows) {
        const group = byPrice.get(row.price) ?? [];
        group.push(row);
        byPrice.set(row.price, group);
    }

    const groups = [...byPrice.values()].sort(
        (a, b) => HOME_OCTANE_IDS.indexOf(a[0].id) - HOME_OCTANE_IDS.indexOf(b[0].id),
    );
    for (const group of groups) {
        group.sort((a, b) => HOME_OCTANE_IDS.indexOf(a.id) - HOME_OCTANE_IDS.indexOf(b.id));
        const lead = group[0];
        cards.push({
            id: lead.id,
            name:
                group.length === 1
                    ? lead.name
                    : group.map((g) => OCTANE_SHORT[g.id] || g.name).join(' / '),
            unit: lead.unit,
            mark: group.length === 1 ? fuelMark(lead.name) : 'HO',
        });
    }

    for (const id of HOME_OTHER_IDS) {
        const fuel = fuelTypeById(id);
        if (!fuel || homePrice(id) <= 0) continue;
        cards.push({
            id,
            name: fuel.name,
            unit: fuel.unit,
            mark: fuelMark(fuel.name),
        });
    }

    return cards;
});

const SPECIALTY_CARD_STYLE = {
    '--co-accent': '#6b7c8f',
    '--co-soft': 'rgba(107, 124, 143, 0.12)',
    '--co-mark-ink': '#ffffff',
} as Record<string, string>;

const compareRows = computed(() => {
    const q = compareCompanyQuery.value.trim().toLowerCase();

    return companies.value
        .map((c) => {
            const row = findPrice(fuelId.value, c.id, city.value);
            return {
                ...c,
                price: row?.price ?? 0,
                previous: row?.previous_price ?? null,
            };
        })
        .filter((r) => r.price > 0)
        .filter(
            (r) =>
                !q ||
                r.name.toLowerCase().includes(q) ||
                r.short.toLowerCase().includes(q) ||
                r.id.toLowerCase().includes(q),
        )
        .sort((a, b) => a.price - b.price);
});

/** Lowest price among visible compare rows; null when empty. */
const cheapestPrice = computed(() => compareRows.value[0]?.price ?? null);

/** True only when at least one company is strictly cheaper than another. */
const hasComparePriceSpread = computed(() => {
    const rows = compareRows.value;
    if (rows.length < 2) return false;
    return rows[0].price < rows[rows.length - 1].price;
});

function isBestComparePrice(price: number): boolean {
    return (
        hasComparePriceSpread.value
        && cheapestPrice.value !== null
        && price === cheapestPrice.value
    );
}

const selectedFuel = computed(
    () => fuelTypes.value.find((f) => f.id === fuelId.value) || fuelTypes.value[0],
);

const calcFuelId = computed(() => {
    const preferred = selectedVehicle.value?.fuel || 'petrol';
    if (fuelTypes.value.some((f) => f.id === preferred)) return preferred;
    return 'petrol';
});

const calcPrice = computed(() => priceFor(calcFuelId.value, 'pso', city.value) || priceFor(calcFuelId.value));

const litresNeeded = computed(() => distance.value / Math.max(mileage.value, 0.01));
const fuelCost = computed(() => litresNeeded.value * calcPrice.value);
const costPerKm = computed(() => calcPrice.value / Math.max(mileage.value, 0.01));
const litresToFill = computed(() => tankCapacity.value * (1 - currentLevel.value / 100));
const fillCost = computed(() => litresToFill.value * calcPrice.value);
const tripLitres = computed(() => tripDistance.value / Math.max(mileage.value, 0.01));
const oneWayCost = computed(() => tripLitres.value * calcPrice.value);
const roundTripCost = computed(() => oneWayCost.value * 2);

const filteredStations = computed(() => {
    const q = stationQuery.value.trim().toLowerCase();
    const source = useNearby.value && nearbyStations.value.length ? nearbyStations.value : stationRows.value;

    return source.filter((s) => {
        if (!useNearby.value || !nearbyStations.value.length) {
            if (city.value !== 'national' && s.city_id !== city.value) return false;
        }
        if (stationCompany.value && s.company_id !== stationCompany.value) return false;
        if (!q) return true;

        const hay = [
            s.name,
            s.address,
            companyLabel(s.company),
            cityLabel(s.city),
            s.company_id,
            s.city_id,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(q);
    });
});

async function loadNearbyStations(forceRefresh = false): Promise<void> {
    if (!useNearby.value) return;

    const requestId = ++nearbyRequestGen;
    nearbyLoading.value = true;
    nearbyError.value = '';

    try {
        const position =
            !forceRefresh && nearbyOrigin.value
                ? ({
                      coords: {
                          latitude: nearbyOrigin.value.lat,
                          longitude: nearbyOrigin.value.lng,
                      },
                  } as GeolocationPosition)
                : await getCurrentPosition();

        // User may have switched to By city while GPS was pending.
        if (requestId !== nearbyRequestGen || !useNearby.value) return;

        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        nearbyOrigin.value = { lat, lng };

        const matched = nearestCityId(lat, lng);
        if (matched && matched !== 'national') {
            city.value = matched;
            persistCityId(matched);
        }

        const nearby = parseNearbyQuery({
            lat,
            lng,
            radius: 5000,
            limit: 20,
            company: stationCompany.value || null,
            q: stationQuery.value.trim() || null,
        });
        if (!nearby.ok) {
            throw new Error(msg('nearbyLoadFailed'));
        }
        const params = new URLSearchParams(toQuery(nearby.data));

        const res = await fetch(`/api/v1/stations/nearby?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const json = (await res.json()) as {
            success?: boolean;
            message?: string;
            data?: { items?: Station[] };
        };

        if (requestId !== nearbyRequestGen || !useNearby.value) return;

        if (!res.ok || !json.success) {
            throw new Error(json.message || msg('nearbyLoadFailed'));
        }

        nearbyStations.value = (json.data?.items ?? []).map((s) => ({
            ...s,
            id: String(s.id),
            company_id: s.company_id || '',
            city_id: s.city_id || '',
            address: s.address || '',
        }));
        locationReady.value = true;
    } catch (e) {
        if (requestId !== nearbyRequestGen || !useNearby.value) return;
        nearbyStations.value = [];
        locationReady.value = false;
        const err = e as { code?: number; message?: string };
        // Fall back to By city so users are not stuck on an empty Near me list.
        useNearby.value = false;
        if (err.code === 1) {
            nearbyError.value = msg('locationBlocked');
        } else {
            // Never surface raw API/exception text — use stable i18n copy.
            nearbyError.value = msg('locationFailed');
        }
    } finally {
        if (requestId === nearbyRequestGen) {
            nearbyLoading.value = false;
        }
    }
}

/** Ask for GPS on first load so city + Near me default to the user (not National). */
async function initUserLocation(): Promise<void> {
    try {
        const position = await getCurrentPosition();
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        nearbyOrigin.value = { lat, lng };
        const matched = nearestCityId(lat, lng);
        if (matched && matched !== 'national') {
            city.value = matched;
            persistCityId(matched);
        }
        useNearby.value = true;
        // Preload nearby pumps so Stations is ready when opened.
        await loadNearbyStations();
    } catch {
        // Silent on home load — Stations tab will show a clear message if needed.
        useNearby.value = false;
        locationReady.value = false;
    }
}

watch(tab, (next) => {
    if (next === 'stations' && useNearby.value) {
        void loadNearbyStations();
    }
});

const historyMap = computed(() =>
    countryCode.value === 'PK' ? (props.history ?? {}) : {},
);

/** Rolling lookback windows (7 / 30 / 180 / 365 days). */
function dateInHistoryFilter(dateStr: string): boolean {
    const d = new Date(`${dateStr}T12:00:00`);
    if (Number.isNaN(d.getTime())) return false;
    const days = HISTORY_RANGE_DAYS[historyRange.value] ?? 30;
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    start.setDate(start.getDate() - (days - 1));
    return d >= start;
}

const historyPeriodHint = computed(() => {
    const days = HISTORY_RANGE_DAYS[historyRange.value] ?? 30;
    return msg('historyRollingHint').replace('{days}', String(days));
});

type ChartRow = Record<string, number | string>;

const chartSeries = computed(() => {
    const available = HISTORY_FUEL_IDS.filter((id) => (historyMap.value[id] || []).length > 0);

    const dateMap = new Map<string, ChartRow>();
    for (const id of available) {
        for (const point of historyMap.value[id] || []) {
            const row = dateMap.get(point.date) || { date: point.date };
            row[id] = point.price;
            dateMap.set(point.date, row);
        }
    }

    const rows = Array.from(dateMap.values())
        .filter((r) => dateInHistoryFilter(String(r.date)))
        .sort((a, b) => String(a.date).localeCompare(String(b.date)));

    // Need ≥2 in-period points to draw a real trend (avoids LPG stub lines).
    const chartable = available.filter(
        (id) => rows.filter((r) => typeof r[id] === 'number').length >= 2,
    );
    const ids =
        historyFuelFocus.value === 'all'
            ? [...chartable]
            : chartable.filter((id) => id === historyFuelFocus.value);

    return { ids, rows, available, chartable };
});

const historyPctChange = computed(() => {
    const out: Record<string, number | null> = {};
    for (const id of chartSeries.value.ids) {
        const vals = chartSeries.value.rows
            .map((r) => r[id])
            .filter((v): v is number => typeof v === 'number');
        if (vals.length < 2 || vals[0] === 0) {
            out[id] = null;
            continue;
        }
        out[id] = +(((vals[vals.length - 1] - vals[0]) / vals[0]) * 100).toFixed(2);
    }
    return out;
});

const CHART_PAD = { top: 18, right: 16, bottom: 36, left: 48 };
const CHART_W = 720;
const CHART_H = 300;
const CHART_INNER_W = CHART_W - CHART_PAD.left - CHART_PAD.right;
const CHART_INNER_H = CHART_H - CHART_PAD.top - CHART_PAD.bottom;

const chartBounds = computed(() => {
    const values: number[] = [];
    for (const row of chartSeries.value.rows) {
        for (const id of chartSeries.value.ids) {
            if (typeof row[id] === 'number') values.push(Number(row[id]));
        }
    }
    if (!values.length) return { min: 0, max: 1, span: 1 };
    const min = Math.min(...values);
    const max = Math.max(...values);
    const pad = Math.max((max - min) * 0.08, 1);
    const lo = Math.max(0, min - pad);
    const hi = max + pad;
    return { min: lo, max: hi, span: Math.max(hi - lo, 1) };
});

function chartX(index: number, count: number): number {
    if (count <= 1) return CHART_PAD.left + CHART_INNER_W / 2;
    return CHART_PAD.left + (index / (count - 1)) * CHART_INNER_W;
}

function chartY(value: number): number {
    const { min, span } = chartBounds.value;
    return CHART_PAD.top + CHART_INNER_H - ((value - min) / span) * CHART_INNER_H;
}

function chartPoints(id: string): Array<{ x: number; y: number; value: number }> {
    const rows = chartSeries.value.rows.filter((r) => typeof r[id] === 'number');
    return rows.map((r, i) => ({
        x: chartX(i, rows.length),
        y: chartY(Number(r[id])),
        value: Number(r[id]),
    }));
}

function chartLinePath(id: string): string {
    const pts = chartPoints(id);
    if (!pts.length) return '';
    if (pts.length === 1) {
        // Flat stub so a single revision still draws.
        const p = pts[0];
        return `M${(p.x - 18).toFixed(1)},${p.y.toFixed(1)} L${(p.x + 18).toFixed(1)},${p.y.toFixed(1)}`;
    }
    return pts
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`)
        .join(' ');
}

function chartAreaPath(id: string): string {
    const pts = chartPoints(id);
    if (!pts.length) return '';
    const baseY = CHART_PAD.top + CHART_INNER_H;
    if (pts.length === 1) {
        const p = pts[0];
        const x1 = p.x - 22;
        const x2 = p.x + 22;
        return `M${x1.toFixed(1)},${baseY.toFixed(1)} L${x1.toFixed(1)},${p.y.toFixed(1)} L${x2.toFixed(1)},${p.y.toFixed(1)} L${x2.toFixed(1)},${baseY.toFixed(1)} Z`;
    }
    const line = pts
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`)
        .join(' ');
    const last = pts[pts.length - 1];
    const first = pts[0];
    return `${line} L${last.x.toFixed(1)},${baseY.toFixed(1)} L${first.x.toFixed(1)},${baseY.toFixed(1)} Z`;
}

type BarRect = { x: number; y: number; w: number; h: number; color: string; label: string; value: number };

const chartBars = computed((): BarRect[] => {
    const { rows, ids } = chartSeries.value;
    if (!rows.length || !ids.length) return [];
    const groupW = CHART_INNER_W / Math.max(rows.length, 1);
    const barW = Math.max(10, Math.min(28, (groupW * 0.72) / ids.length));
    const rects: BarRect[] = [];
    rows.forEach((row, i) => {
        const groupX = CHART_PAD.left + i * groupW + groupW / 2;
        ids.forEach((id, fi) => {
            if (typeof row[id] !== 'number') return;
            const value = Number(row[id]);
            const y = chartY(value);
            const base = CHART_PAD.top + CHART_INNER_H;
            const offset = (fi - (ids.length - 1) / 2) * (barW + 3);
            rects.push({
                x: groupX + offset - barW / 2,
                y,
                w: barW,
                h: Math.max(4, base - y),
                color: FUEL_COLOR[id] || '#F2A63F',
                label: `${fuelLabel(id)} · ${formatChartDate(String(row.date))}`,
                value,
            });
        });
    });
    return rects;
});

const chartYTicks = computed(() => {
    const { min, max, span } = chartBounds.value;
    const steps = 4;
    return Array.from({ length: steps + 1 }, (_, i) => {
        const value = min + (span * i) / steps;
        return { value, y: chartY(value), label: value.toFixed(0) };
    });
});

const chartXLabels = computed(() => {
    const rows = chartSeries.value.rows;
    if (!rows.length) return [] as Array<{ x: number; label: string }>;
    const maxLabels = historyRange.value === 'yearly' ? 6 : historyRange.value === 'monthly' ? 6 : 7;
    const step = Math.max(1, Math.ceil(rows.length / maxLabels));
    const labels: Array<{ x: number; label: string }> = [];
    for (let i = 0; i < rows.length; i += step) {
        labels.push({
            x: chartX(i, rows.length),
            label: formatChartDate(String(rows[i].date), true),
        });
    }
    const last = rows.length - 1;
    if (labels.length && labels[labels.length - 1].x !== chartX(last, rows.length)) {
        labels.push({
            x: chartX(last, rows.length),
            label: formatChartDate(String(rows[last].date), true),
        });
    }
    return labels;
});

function fuelLabel(id: string): string {
    return fuelTypes.value.find((f) => f.id === id)?.name || id;
}

/* --- Chart hover tooltip (mouse + touch) --- */
const chartHoverIdx = ref<number | null>(null);

function onChartMove(evt: MouseEvent | TouchEvent): void {
    const svg = evt.currentTarget as SVGSVGElement | null;
    const rows = chartSeries.value.rows;
    if (!svg || !rows.length) return;
    const rect = svg.getBoundingClientRect();
    const clientX = 'touches' in evt ? (evt.touches[0]?.clientX ?? 0) : evt.clientX;
    const px = ((clientX - rect.left) / rect.width) * CHART_W;
    if (rows.length === 1) {
        chartHoverIdx.value = 0;
        return;
    }
    const rel = (px - CHART_PAD.left) / CHART_INNER_W;
    chartHoverIdx.value = Math.min(rows.length - 1, Math.max(0, Math.round(rel * (rows.length - 1))));
}

function onChartLeave(): void {
    chartHoverIdx.value = null;
}

const chartHoverData = computed(() => {
    const idx = chartHoverIdx.value;
    if (idx === null) return null;
    const rows = chartSeries.value.rows;
    const row = rows[idx];
    if (!row) return null;
    const entries = chartSeries.value.ids
        .filter((id) => typeof row[id] === 'number')
        .map((id) => ({
            id,
            label: shortFuelLabel(id),
            value: Number(row[id]),
            color: FUEL_COLOR[id] || '#F2A63F',
        }));
    if (!entries.length) return null;
    return {
        x: chartX(idx, rows.length),
        date: formatChartDate(String(row.date)),
        entries,
    };
});

const chartTooltipStyle = computed(() => {
    const d = chartHoverData.value;
    if (!d) return {};
    const pct = (d.x / CHART_W) * 100;
    return {
        left: `${pct}%`,
        transform: pct > 58 ? 'translateX(calc(-100% - 14px))' : 'translateX(14px)',
    };
});

function shortFuelLabel(id: string): string {
    if (id === 'petrol') return msg('petrolLabel');
    if (id === 'hsd') return msg('dieselShort');
    if (id === 'lpg') return msg('lpgShort');
    return fuelLabel(id);
}

function formatChartDate(dateStr: string, short = false): string {
    const d = new Date(`${dateStr}T12:00:00`);
    if (Number.isNaN(d.getTime())) return dateStr;
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-GB';
    if (short) {
        if (historyRange.value === 'yearly') {
            return d.toLocaleDateString(loc, { month: 'short' });
        }
        return d.toLocaleDateString(loc, { day: 'numeric', month: 'short' });
    }
    return d.toLocaleDateString(loc, { day: 'numeric', month: 'short', year: 'numeric' });
}

type FuelStat = {
    id: string;
    name: string;
    color: string;
    latest: number | null;
    high: number | null;
    low: number | null;
    change: number | null;
    spark: string;
};

const fuelStats = computed((): FuelStat[] => {
    return chartSeries.value.ids.map((id) => {
        const vals = chartSeries.value.rows
            .filter((r) => typeof r[id] === 'number')
            .map((r) => Number(r[id]));
        const latest = vals.length ? vals[vals.length - 1] : null;
        const first = vals.length ? vals[0] : null;
        const high = vals.length ? Math.max(...vals) : null;
        const low = vals.length ? Math.min(...vals) : null;
        const change = latest !== null && first !== null ? +(latest - first).toFixed(2) : null;

        let spark = '';
        if (vals.length >= 2) {
            const min = Math.min(...vals);
            const max = Math.max(...vals);
            const span = Math.max(max - min, 1);
            spark = vals
                .map((v, i) => {
                    const x = (i / (vals.length - 1)) * 120;
                    const y = 36 - ((v - min) / span) * 28 - 4;
                    return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
                })
                .join(' ');
        }

        return {
            id,
            name: fuelLabel(id),
            color: FUEL_COLOR[id] || '#F2A63F',
            latest,
            high,
            low,
            change,
            spark,
        };
    });
});

function setHistoryRange(next: HistoryRange): void {
    historyRange.value = next;
    trackEvent('history_range', { range: next });
    trackEvent('history_range_changed', { range: next });
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('range', next);
        if (tab.value !== 'history') {
            // keep path; history deep-link uses /price-history
        }
        window.history.replaceState({}, '', url.toString());
    } catch {
        /* ignore */
    }
}

function shareHistoryChart(): void {
    const url = new URL(window.location.origin + '/price-history');
    url.searchParams.set('range', historyRange.value);
    url.searchParams.set('tab', 'history');
    const text = `${msg('historyTitle')} (${historyPeriodHint.value}) — ${url.toString()}`;
    trackEvent('share_clicked', { content_type: 'history_chart', range: historyRange.value });
    if (navigator.share) {
        void navigator.share({ title: msg('historyTitle'), text, url: url.toString() });
        return;
    }
    void navigator.clipboard?.writeText(url.toString());
}

function toggleFavoriteCity(): void {
    if (favoriteCityId.value === city.value) {
        favoriteCityId.value = '';
        localStorage.removeItem('fuelpk.favoriteCity');
        trackEvent('favorite_removed', { type: 'city', city_id: city.value });
        return;
    }
    favoriteCityId.value = city.value;
    localStorage.setItem('fuelpk.favoriteCity', city.value);
    trackEvent('favorite_added', { type: 'city', city_id: city.value });
}

function setFavoriteFuel(id: string): void {
    favoriteFuelId.value = id;
    localStorage.setItem('fuelpk.favoriteFuel', id);
    fuelId.value = id;
    trackEvent('favorite_added', { type: 'fuel', fuel_type: id });
}

function pushRecentCity(id: string): void {
    const next = [id, ...recentCities.value.filter((c) => c !== id)].slice(0, 8);
    recentCities.value = next;
    localStorage.setItem('fuelpk.recentCities', JSON.stringify(next));
}

function pushRecentCalc(mode: string): void {
    const next = [mode, ...recentCalcs.value.filter((c) => c !== mode)].slice(0, 6);
    recentCalcs.value = next;
    localStorage.setItem('fuelpk.recentCalcs', JSON.stringify(next));
}

function openRecentCalc(mode: string): void {
    if (mode === 'fuel' || mode === 'tank' || mode === 'trip') {
        calc.value = mode;
    }
    tab.value = 'calc';
}

function persistCalcInputs(): void {
    try {
        localStorage.setItem(
            'fuelpk.calcInputs',
            JSON.stringify({
                distance: distance.value,
                mileage: mileage.value,
                tankCapacity: tankCapacity.value,
                currentLevel: currentLevel.value,
                tripDistance: tripDistance.value,
                calc: calc.value,
            }),
        );
    } catch {
        /* ignore */
    }
}

function loadRetentionPrefs(): void {
    try {
        favoriteCityId.value = localStorage.getItem('fuelpk.favoriteCity') || '';
        favoriteFuelId.value = localStorage.getItem('fuelpk.favoriteFuel') || 'petrol';
        recentCities.value = JSON.parse(localStorage.getItem('fuelpk.recentCities') || '[]') as string[];
        recentCalcs.value = JSON.parse(localStorage.getItem('fuelpk.recentCalcs') || '[]') as string[];
        const raw = localStorage.getItem('fuelpk.calcInputs');
        if (raw) {
            const saved = JSON.parse(raw) as Record<string, number | string>;
            if (typeof saved.distance === 'number') distance.value = saved.distance;
            if (typeof saved.mileage === 'number') mileage.value = saved.mileage;
            if (typeof saved.tankCapacity === 'number') tankCapacity.value = saved.tankCapacity;
            if (typeof saved.currentLevel === 'number') currentLevel.value = saved.currentLevel;
            if (typeof saved.tripDistance === 'number') tripDistance.value = saved.tripDistance;
            if (saved.calc === 'fuel' || saved.calc === 'tank' || saved.calc === 'trip') {
                calc.value = saved.calc;
            }
        }
        // Prefer a real city (saved / favorite) — never force National as the boot default.
        const stored = readStoredCityId();
        if (stored && stored !== 'national' && cities.value.some((c) => c.id === stored)) {
            city.value = stored;
        } else if (
            favoriteCityId.value
            && favoriteCityId.value !== 'national'
            && cities.value.some((c) => c.id === favoriteCityId.value)
        ) {
            city.value = favoriteCityId.value;
        } else if (!cities.value.some((c) => c.id === city.value) || city.value === 'national') {
            city.value = defaultCityForCountry();
        }
        if (favoriteFuelId.value) {
            fuelId.value = favoriteFuelId.value;
        }
        const lastTab = localStorage.getItem('fuelpk.lastTab') as Tab | null;
        if (lastTab && VALID_TABS.includes(lastTab) && !props.initial_tab) {
            // only restore when landing on home without deep-link tab
        }
    } catch {
        /* ignore */
    }
}

function setHistoryFuelFocus(next: string): void {
    const focus = (next === 'petrol' || next === 'hsd' || next === 'lpg' || next === 'all'
        ? next
        : 'all') as HistoryFuelFocus;
    historyFuelFocus.value = focus;
    if (focus !== 'all') {
        trackEvent('fuel_type_changed', { fuel_type: focus, surface: 'history' });
    }
}

/** Manual city pick (dropdown) — show that city's catalog pumps, not GPS Near me. */
function applyCitySelection(id: string): void {
    nearbyRequestGen += 1;
    city.value = id;
    useNearby.value = false;
    nearbyStations.value = [];
    nearbyError.value = '';
    nearbyLoading.value = false;
}

function selectPopularFuel(id: string): void {
    if (fuelId.value === id) return;
    fuelId.value = id;
}

function onFaqToggle(event: Event): void {
    const el = event.target as HTMLDetailsElement | null;
    if (el?.open) {
        trackEvent('faq_opened');
    }
}

type ChangeMeta = { change: number; flat: boolean; up: boolean; noPrior: boolean };

function priceChangeMeta(price: number, previous: number | null): ChangeMeta {
    if (previous === null) return { change: 0, flat: true, up: false, noPrior: true };
    const change = +(price - previous).toFixed(2);
    return { change, flat: Math.abs(change) < 0.01, up: change > 0, noPrior: false };
}

/** Card unit label: "litre" / "kg" from stored "Rs/L" / "Rs/kg". */
function priceUnitWord(unit: string | null | undefined): string {
    const u = (unit || '').toLowerCase();
    if (u.includes('kg')) return msg('unitKg');
    return msg('unitLitre');
}

/** Consumer mood: up = painful, down = relief. */
function changeMoodEmoji(meta: ChangeMeta): string {
    if (meta.noPrior || meta.flat) return '😐';
    const abs = Math.abs(meta.change);
    if (meta.up) {
        if (abs >= 10) return '😭';
        if (abs >= 3) return '😡';
        return '😢';
    }
    if (abs >= 10) return '🥳';
    if (abs >= 3) return '😄';
    return '🙂';
}

function changeMeta(fuelIdKey: string): ChangeMeta {
    const row = homePriceRow(fuelIdKey);
    if (!row) return { change: 0, flat: true, up: false, noPrior: true };
    const prev = priorNotifiedPrice(fuelIdKey, row);
    if (prev === null) return { change: 0, flat: true, up: false, noPrior: true };
    const change = +(row.price - prev).toFixed(2);
    return { change, flat: Math.abs(change) < 0.01, up: change > 0, noPrior: false };
}

function changeStatusLabel(meta: ChangeMeta): string {
    if (meta.noPrior) return msg('noPriorChange');
    if (meta.flat) return msg('unchanged');
    return '';
}

/** Week-over-week movement from history (vs the point closest to 7 days ago). */
function trend15d(fuelIdKey: string): { up: boolean; flat: boolean; diff: number } | null {
    const points = historyMap.value[fuelIdKey];
    const current = homePrice(fuelIdKey);
    if (!points || points.length < 2 || !current) return null;

    const target = Date.now() - 15 * 86400000;
    let base: HistoryPoint | null = null;
    let baseTime = -Infinity;
    let oldest: HistoryPoint | null = null;
    let oldestTime = Infinity;
    for (const p of points) {
        const t = new Date(`${p.date}T12:00:00`).getTime();
        if (Number.isNaN(t) || !p.price) continue;
        if (t <= target && t > baseTime) {
            base = p;
            baseTime = t;
        }
        if (t < oldestTime) {
            oldest = p;
            oldestTime = t;
        }
    }
    base = base ?? oldest;
    if (!base || oldestTime > Date.now() - 86400000) return null;

    const diff = +(current - base.price).toFixed(2);
    return { up: diff > 0, flat: Math.abs(diff) < 0.01, diff: Math.abs(diff) };
}

function currentSnapshot(): PriceSnapshot {
    return {
        petrol: priceFor('petrol', undefined, 'national'),
        hsd: priceFor('hsd', undefined, 'national'),
        savedAt: new Date().toISOString(),
    };
}

function checkPriceAlerts(): void {
    const current = currentSnapshot();
    let previous: PriceSnapshot | null = null;

    try {
        const raw = localStorage.getItem('fuelpk.lastSnapshot');
        if (raw) previous = JSON.parse(raw) as PriceSnapshot;
    } catch {
        /* ignore */
    }

    if (alertsEnabled.value && previous) {
        const petrolChanged = Math.abs(current.petrol - previous.petrol) >= 0.01;
        const hsdChanged = Math.abs(current.hsd - previous.hsd) >= 0.01;

        if ((petrolChanged || hsdChanged) && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            const parts: string[] = [];
            if (petrolChanged) {
                parts.push(`${msg('petrolLabel')} ${msg('currencyRs')} ${current.petrol.toFixed(2)}`);
            }
            if (hsdChanged) {
                parts.push(`${msg('hsdLabel')} ${msg('currencyRs')} ${current.hsd.toFixed(2)}`);
            }
            void showLocalNotification(msg('appName'), parts.join(' · '));
        }
    }

    localStorage.setItem('fuelpk.lastSnapshot', JSON.stringify(current));
}

/**
 * Show a local notification safely. On Android Chrome the page-context
 * `new Notification()` constructor throws "Illegal constructor" — notifications
 * must go through the service worker registration there. Fall back to the
 * constructor on desktop where the SW may be unavailable.
 */
async function showLocalNotification(title: string, body: string): Promise<void> {
    const options: NotificationOptions = { body, icon: '/icons/icon-192.png' };
    try {
        if ('serviceWorker' in navigator) {
            const reg = await navigator.serviceWorker.ready;
            await reg.showNotification(title, options);
            return;
        }
    } catch {
        /* fall through to the constructor */
    }
    try {
        new Notification(title, options);
    } catch {
        /* notifications unsupported in this context — ignore */
    }
}

/* --- Web Push (VAPID) — server-sent alerts even when the app is closed --- */
function urlBase64ToUint8Array(base64: string): Uint8Array {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

async function subscribeWebPush(): Promise<void> {
    try {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        const key = document.querySelector('meta[name="vapid-key"]')?.getAttribute('content') || '';
        if (!key) return;
        const reg = await navigator.serviceWorker.ready;
        const sub =
            (await reg.pushManager.getSubscription())
            ?? (await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(key) as unknown as BufferSource,
            }));
        const json = sub.toJSON();
        if (!json.endpoint || !json.keys) return;
        const pushBody = parsePushSubscribe({
            endpoint: json.endpoint,
            keys: json.keys,
            locale: locale.value,
        });
        if (!pushBody.ok) return;
        await fetch('/api/v1/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(pushBody.data),
        });
        trackEvent('web_push_subscribed');
    } catch {
        /* push unsupported or user dismissed — local alerts still work */
    }
}

async function unsubscribeWebPush(): Promise<void> {
    try {
        if (!('serviceWorker' in navigator)) return;
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return;
        await fetch('/api/v1/push/unsubscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ endpoint: sub.endpoint }),
        });
        await sub.unsubscribe();
    } catch {
        /* ignore */
    }
}

async function enableAlerts(): Promise<void> {
    if (typeof Notification === 'undefined') return;
    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
        alertsEnabled.value = true;
        localStorage.setItem('fuelpk.alerts', '1');
        checkPriceAlerts();
        void subscribeWebPush();
        trackEvent('notification_subscribe', { method: 'browser' });
        trackEvent('notification_enabled', { method: 'browser' });
        trackEvent('price_alert_created', { channel: 'web_push' });
    } else {
        trackEvent('notification_subscribe_denied', { permission });
        trackEvent('notification_dismissed', { reason: permission });
    }
}

function disableAlerts(): void {
    alertsEnabled.value = false;
    localStorage.removeItem('fuelpk.alerts');
    void unsubscribeWebPush();
    trackEvent('notification_unsubscribe', { method: 'browser' });
    trackEvent('price_alert_deleted', { channel: 'web_push' });
}

async function fetchRecentNotifications(): Promise<void> {
    try {
        const res = await fetch('/api/v1/notifications?per_page=20');
        if (!res.ok) return;
        const json = (await res.json()) as {
            data?: { items?: Array<{ id: number; title: string; body: string; created_at?: string }> };
        };
        const items = json.data?.items ?? [];
        localStorage.setItem('fuelpk.notifications', JSON.stringify(items));
    } catch {
        /* offline — keep previous cache */
    }
}

function saveOfflineCache(): void {
    const recentNotifications = (() => {
        try {
            return JSON.parse(localStorage.getItem('fuelpk.notifications') || '[]');
        } catch {
            return [];
        }
    })();

    localStorage.setItem(
        'fuelpk.cache',
        JSON.stringify({
            prices: priceRows.value,
            cities: cities.value,
            companies: companies.value,
            fuel_types: fuelTypes.value,
            history: historyMap.value,
            stations: stationRows.value,
            notifications: recentNotifications,
            savedAt: new Date().toISOString(),
        }),
    );
}

function shareAppUrl(): string {
    if (typeof window === 'undefined') return 'https://oilprice.topgradesoftware.com/';
    return `${window.location.origin}/`;
}

function sharePricesText(includeUrl = true): string {
    const petrol = priceFor('petrol', undefined, 'national') || priceFor('petrol');
    const hsd = priceFor('hsd', undefined, 'national') || priceFor('hsd');
    return [
        msg('appName'),
        `${msg('petrolLabel')}: ${msg('currencyRs')} ${petrol.toFixed(2)}/${msg('unitL')}`,
        `${msg('hsdLabel')}: ${msg('currencyRs')} ${hsd.toFixed(2)}/${msg('unitL')}`,
        todayLabel.value,
        ...(includeUrl ? [shareAppUrl()] : []),
    ].join('\n');
}

function whatsappShareUrl(): string {
    return `https://wa.me/?text=${encodeURIComponent(sharePricesText())}`;
}

const shareSheetOpen = ref(false);
const shareCopied = ref(false);

const nativeShareAvailable = computed(
    () => typeof navigator !== 'undefined' && typeof navigator.share === 'function',
);

function openShareSheet(): void {
    shareCopied.value = false;
    shareSheetOpen.value = true;
    trackEvent('share_sheet_open', { content_type: 'fuel_prices' });
}

function closeShareSheet(): void {
    shareSheetOpen.value = false;
}

async function shareNative(): Promise<void> {
    try {
        // URL passed separately so targets render it as a clickable link
        // (text-only shares can drop or bury the trailing URL line).
        await navigator.share({
            title: msg('appName'),
            text: sharePricesText(false),
            url: shareAppUrl(),
        });
        trackEvent('share', { method: 'native', content_type: 'fuel_prices' });
        closeShareSheet();
    } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') {
            trackEvent('share', { method: 'native', content_type: 'fuel_prices', outcome: 'abort' });
        }
    }
}

/** iOS needs `sms:&body=`; Android and others use `sms:?body=`. */
function smsShareUrl(): string {
    const body = encodeURIComponent(sharePricesText());
    const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    return ios ? `sms:&body=${body}` : `sms:?body=${body}`;
}

function shareVia(method: string): void {
    const text = sharePricesText(); // prices + app URL
    const textNoUrl = sharePricesText(false);
    const url = shareAppUrl();

    const links: Record<string, string> = {
        whatsapp: whatsappShareUrl(),
        sms: smsShareUrl(),
        email: `mailto:?subject=${encodeURIComponent(msg('appName'))}&body=${encodeURIComponent(text)}`,
        telegram: `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(textNoUrl)}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}&quote=${encodeURIComponent(textNoUrl)}`,
        x: `https://twitter.com/intent/tweet?text=${encodeURIComponent(textNoUrl)}&url=${encodeURIComponent(url)}`,
    };

    const link = links[method];
    if (!link) return;

    if (method === 'sms' || method === 'email') {
        window.location.href = link;
    } else {
        window.open(link, '_blank', 'noopener,noreferrer');
    }
    trackEvent('share', { method, content_type: 'fuel_prices' });
    closeShareSheet();
}

async function copyShareText(): Promise<void> {
    try {
        await navigator.clipboard.writeText(sharePricesText());
        shareCopied.value = true;
        trackEvent('share', { method: 'copy', content_type: 'fuel_prices' });
        setTimeout(() => {
            shareCopied.value = false;
            closeShareSheet();
        }, 1200);
    } catch {
        /* clipboard blocked — leave the sheet open so another option can be used */
    }
}

/** Entry point for every Share button: always presents the options sheet. */
function sharePrices(): void {
    openShareSheet();
}

function persistAuth(token: string, user: AuthUser): void {
    authToken.value = token;
    authUser.value = user;
    try {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
    } catch {
        /* ignore */
    }
}

function clearAuth(): void {
    authToken.value = null;
    authUser.value = null;
    try {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
    } catch {
        /* ignore */
    }
}

function loadStoredAuth(): void {
    try {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const raw = localStorage.getItem(AUTH_USER_KEY);
        if (!token || !raw) return;
        authToken.value = token;
        authUser.value = JSON.parse(raw) as AuthUser;
    } catch {
        clearAuth();
    }
}

function decodeJwtPayload(token: string): Record<string, unknown> | null {
    try {
        const part = token.split('.')[1];
        if (!part) return null;
        const padded = part.replace(/-/g, '+').replace(/_/g, '/');
        const json = atob(padded.padEnd(padded.length + ((4 - (padded.length % 4)) % 4), '='));
        return JSON.parse(json) as Record<string, unknown>;
    } catch {
        return null;
    }
}

async function exchangeGoogleCredential(idToken: string): Promise<void> {
    authBusy.value = true;
    authError.value = '';

    // Show profile immediately from the Google JWT while the server verifies.
    const claims = decodeJwtPayload(idToken);
    if (claims && (claims.email || claims.name)) {
        authUser.value = {
            id: 0,
            name: String(claims.name || 'User'),
            email: claims.email ? String(claims.email) : null,
            auth_provider: 'google',
            is_guest: false,
            avatar_url: claims.picture ? String(claims.picture) : null,
        };
    }

    try {
        const authBody = parseGoogleAuthBody({ id_token: idToken });
        if (!authBody.ok) {
            throw new Error(msg('googleSignInFailed'));
        }
        const res = await fetch('/api/v1/auth/google', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(authBody.data),
        });
        const json = (await res.json()) as {
            success?: boolean;
            message?: string;
            data?: { token?: string; user?: AuthUser };
        };
        if (!res.ok || !json.data?.token || !json.data.user) {
            throw new Error(json.message || msg('googleSignInFailed'));
        }
        persistAuth(json.data.token, json.data.user);
    } catch (err) {
        // Prefer API's intentional auth message when present; never show stacks/paths.
        const apiMessage = err instanceof Error ? err.message.trim() : '';
        const safeAuthMessages = new Set([
            'Google login is disabled.',
            'Google login is not configured.',
            'Invalid Google ID token.',
            'Invalid Google ID token payload.',
            'Google token audience mismatch.',
            'Google token issuer mismatch.',
            'Google token expired.',
            'Google token signing key not found.',
            'Google token signature invalid.',
            'Unable to fetch Google certificates.',
            'Validation failed.',
            'Unauthenticated.',
            'Forbidden.',
            'Server error.',
            'Request failed.',
        ]);
        authError.value = safeAuthMessages.has(apiMessage) ? apiMessage : msg('googleSignInFailed');
        clearAuth();
    } finally {
        authBusy.value = false;
    }
}

function loadGoogleScript(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (window.google?.accounts?.id) {
            resolve();
            return;
        }
        const existing = document.querySelector<HTMLScriptElement>('script[data-google-gis]');
        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('GIS load failed')), { once: true });
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.dataset.googleGis = '1';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('GIS load failed'));
        document.head.appendChild(script);
    });
}

async function setupGoogleButton(): Promise<void> {
    if (!googleLoginEnabled.value || authUser.value) return;
    const hosts = [googleBtnHomeEl.value, googleBtnMoreEl.value, googleBtnCompulsoryEl.value].filter(
        (el): el is HTMLElement => Boolean(el),
    );
    if (!hosts.length) return;
    const clientId = props.auth?.google_client_id;
    if (!clientId) return;

    try {
        await loadGoogleScript();
        await nextTick();
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
        if (!window.google?.accounts?.id) return;

        const liveHosts = [googleBtnHomeEl.value, googleBtnMoreEl.value, googleBtnCompulsoryEl.value].filter(
            (el): el is HTMLElement => Boolean(el),
        );
        if (!liveHosts.length) return;

        window.google.accounts.id.initialize({
            client_id: clientId,
            callback: (response: { credential?: string }) => {
                if (response.credential) void exchangeGoogleCredential(response.credential);
            },
            auto_select: false,
            cancel_on_tap_outside: true,
        });

        const theme = resolvedTheme.value === 'light' ? 'outline' : 'filled_black';
        for (const host of liveHosts) {
            host.innerHTML = '';
            const isHome = host === googleBtnHomeEl.value || host === googleBtnCompulsoryEl.value;
            const measured = Math.floor(host.getBoundingClientRect().width || host.clientWidth || 0);
            const width = isHome
                ? Math.max(160, Math.min(220, measured || 180))
                : Math.max(240, Math.min(320, measured || 280));
            window.google.accounts.id.renderButton(host, {
                theme,
                size: isHome ? 'medium' : 'large',
                text: isHome ? 'signin_with' : 'continue_with',
                shape: 'rectangular',
                width,
            });
        }
    } catch {
        authError.value = msg('googleLoginUnavailable');
    }
}

async function logoutAuth(): Promise<void> {
    const token = authToken.value;
    clearAuth();
    authError.value = '';
    if (token) {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                },
            });
        } catch {
            /* ignore */
        }
    }
    void nextTick(() => setupGoogleButton());
}

function dismissInstallBanner(): void {
    pwaBannerOpen.value = false;
    pwaBannerDismissed.value = true;
    try {
        sessionStorage.setItem('fuelpk.installBannerDismissed', '1');
    } catch {
        /* ignore */
    }
    trackEvent('pwa_install_dismiss');
}

async function installPwa(): Promise<void> {
    trackEvent('pwa_install_click');
    const outcome = await promptPwaInstall();
    trackEvent('pwa_install', { outcome });
    if (outcome === 'accepted') {
        pwaInstalled.value = true;
        pwaInstallable.value = false;
        pwaBannerOpen.value = false;
        return;
    }
    if (outcome === 'unavailable') {
        // Safari / blocked prompt — tip stays in More.
        pwaBannerOpen.value = false;
    }
}

function onOnline(): void {
    isOffline.value = false;
}

function onOffline(): void {
    isOffline.value = true;
}

onMounted(() => {
    const savedLocale = localStorage.getItem('fuelpk.locale') as Locale | null;
    applyLocale(savedLocale === 'ur' ? 'ur' : 'en');

    const savedTheme = localStorage.getItem('fuelpk.theme');
    const pref: ThemePref =
        savedTheme === 'dark' || savedTheme === 'system' || savedTheme === 'light'
            ? savedTheme
            : 'light';
    applyThemePref(pref);
    systemThemeMql = window.matchMedia('(prefers-color-scheme: dark)');
    systemThemeMql.addEventListener('change', onSystemThemeChange);

    alertsEnabled.value = localStorage.getItem('fuelpk.alerts') === '1';
    if (alertsEnabled.value && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        // Existing alert users get migrated to server push on next visit.
        void subscribeWebPush();
    }

    loadVehicles();
    loadSelectedVehicle();
    loadRetentionPrefs();
    syncCalcFromVehicle();
    if (countryCode.value !== (props.default_country || 'PK')) {
        void loadCountryCatalog(countryCode.value).then(() => {
            if (!cities.value.some((c) => c.id === city.value)) {
                city.value = defaultCityForCountry();
            }
        });
    }
    try {
        sideNavCollapsed.value = localStorage.getItem('fuelpk.sideNavCollapsed') === '1';
    } catch {
        sideNavCollapsed.value = false;
    }
    // Deep-link /price-history?range=d7
    try {
        const params = new URLSearchParams(window.location.search);
        const range = resolveHistoryRange(params.get('range'));
        historyRange.value = range;
        if (params.get('tab') === 'history' || window.location.pathname.includes('price-history')) {
            tab.value = 'history';
        }
    } catch {
        /* ignore */
    }

    saveOfflineCache();
    checkPriceAlerts();
    void fetchRecentNotifications().then(() => saveOfflineCache());

    isOffline.value = !navigator.onLine;
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);

    pwaInstalled.value = isStandalone();
    try {
        pwaBannerDismissed.value = sessionStorage.getItem('fuelpk.installBannerDismissed') === '1';
    } catch {
        pwaBannerDismissed.value = false;
    }
    pwaInstallable.value = canPromptInstall();
    stopPwaInstallListen = onPwaInstallAvailable((can) => {
        pwaInstallable.value = can;
        if (can && !pwaInstalled.value && !pwaBannerDismissed.value) {
            pwaBannerOpen.value = true;
        }
        if (!can) pwaBannerOpen.value = false;
    });
    stopPwaInstalledListen = onPwaInstalled(() => {
        pwaInstalled.value = true;
        pwaInstallable.value = false;
        pwaBannerOpen.value = false;
    });

    loadStoredAuth();
    // Prefetch GIS early so the first click is not waiting on the script download.
    if (googleLoginEnabled.value && !authUser.value) {
        void loadGoogleScript();
    }
    void nextTick(() => setupGoogleButton());

    // Default: pick user's current location (city + nearby pumps).
    void initUserLocation();
    initTheme();

    const themeObserver = new MutationObserver(() => {
        const attr = document.documentElement.getAttribute('data-theme');
        if (attr && ['dark', 'light', 'emerald', 'blue', 'sunset', 'purple'].includes(attr)) {
            resolvedTheme.value = attr as ThemeResolved;
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    void nextTick(() => {
        requestAnimationFrame(() => {
            homeBooting.value = false;
        });
    });
});

watch(city, (next, prev) => {
    if (next) {
        pushRecentCity(next);
        persistCityId(next);
    }
    if (prev === undefined || next === prev) return;
    trackEvent('city_changed', { city_id: next });
});

watch(fuelId, (next, prev) => {
    if (prev === undefined || next === prev) return;
    trackEvent('fuel_type_changed', { fuel_type: next });
});

watch([tab, googleLoginEnabled, authUser, resolvedTheme], () => {
    if (
        (tab.value === 'home' || tab.value === 'more')
        && googleLoginEnabled.value
        && !authUser.value
    ) {
        void nextTick(() => setupGoogleButton());
    }
});

onUnmounted(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    systemThemeMql?.removeEventListener('change', onSystemThemeChange);
    systemThemeMql = null;
    stopPwaInstallListen?.();
    stopPwaInstalledListen?.();
    stopPwaInstallListen = null;
    stopPwaInstalledListen = null;
    try {
        window.google?.accounts?.id?.cancel();
    } catch {
        /* ignore */
    }
});
</script>

<template>
    <div class="app-root">
    <NewsTicker :items="fuel_news" />
    <div
        class="app-container"
        :data-theme="resolvedTheme"
        :data-locale="locale"
        :lang="locale === 'ur' ? 'ur' : 'en'"
        :dir="locale === 'ur' ? 'rtl' : 'ltr'"
    >
        <div class="app-shell" :data-theme="resolvedTheme" :dir="locale === 'ur' ? 'rtl' : 'ltr'">
            <AppSidebar
                :side-nav-collapsed="sideNavCollapsed"
                :open-section="openSection"
                :tab="tab"
                :msg="msg"
                @toggle-side-nav="toggleSideNav"
                @toggle-nav-section="toggleNavSection"
                @update:tab="t => tab = t"
            />

            <div class="app-main">
            <div class="sticky-top-stack">
                <div class="app-header" :class="{ compact: tab !== 'home' }">
                    <div class="brand-icon mobile-only-brand">⛽</div>
                    <div class="grow">
                        <p class="brand-kicker">{{ msg('appName') }}</p>
                        <h1>{{ tab === 'home' ? msg('seoHomeH1') : msg('appName') }}</h1>
                        <p v-if="tab === 'home'">{{ msg('tagline') }}</p>
                    </div>
                </div>
                <div v-if="showCityTools" class="sticky-tools" aria-label="Country and city filters">
                    <div class="sticky-tools-search sticky-tools-country-city">
                        <SearchSelect
                            v-if="countryOptions.length > 1"
                            :model-value="countryCode"
                            :options="countryOptions"
                            :placeholder="msg('searchCountry')"
                            :empty-label="msg('noMatches')"
                            icon="🌐"
                            @update:model-value="applyCountrySelection"
                            @search="trackEvent('search_used', { field: 'country' })"
                        />
                        <SearchSelect
                            :model-value="city"
                            :options="cityOptions"
                            :placeholder="msg('searchCity')"
                            :empty-label="msg('noMatches')"
                            icon="📍"
                            @update:model-value="applyCitySelection"
                            @search="trackEvent('search_used', { field: 'city' })"
                        />
                        <ThemeSwitcher />
                    </div>
                    <div
                        v-if="tab === 'compare' && popularFuels.length"
                        class="quick-chips fuel-chips"
                        role="list"
                        :aria-label="msg('fuelLabel')"
                    >
                        <button
                            v-for="f in popularFuels"
                            :key="f.id"
                            type="button"
                            class="chip"
                            role="listitem"
                            :class="{ active: fuelId === f.id }"
                            @click="selectPopularFuel(f.id)"
                        >
                            {{ f.id === 'hsd' ? msg('dieselShort') : f.id === 'lpg' ? msg('lpgShort') : f.name.split(' ')[0] }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="pwaBannerOpen && pwaInstallable && !pwaInstalled"
                class="pwa-banner"
                role="dialog"
                aria-modal="false"
                :aria-label="msg('installBannerTitle')"
            >
                <div class="pwa-banner-text">
                    <strong>{{ msg('installBannerTitle') }}</strong>
                    <p>{{ msg('installBannerBody') }}</p>
                </div>
                <div class="pwa-banner-actions">
                    <button type="button" class="action-btn" @click="dismissInstallBanner">
                        {{ msg('installLater') }}
                    </button>
                    <button type="button" class="primary-btn pwa-install-btn" @click="installPwa">
                        {{ msg('installNow') }}
                    </button>
                </div>
            </div>

            <div v-if="isOffline" class="offline-banner" role="status">
                {{ msg('offline') }}
            </div>

            <div class="content">
                <!-- HOME -->
                <div v-if="tab === 'home'" class="pane home-pane">
                    <div class="pane-header home-pane-header">
                        <div class="home-title-block">
                            <h2>{{ msg('todayPrices') }}</h2>
                            <p class="home-meta">
                                <span>{{ msg('effective') }} {{ effectiveLabel }}</span>
                                <span v-if="last_sync?.finished_at">
                                    · {{ msg('updated') }} {{ last_sync.finished_at }}
                                </span>
                                <span v-if="pricesStale" class="stale-badge">{{ msg('pricesStale') }}</span>
                            </p>
                        </div>
                        <div class="home-header-actions">
                            <div
                                v-if="!authUser && googleLoginEnabled"
                                class="home-signin-compact"
                                :aria-label="msg('signIn')"
                            >
                                <div ref="googleBtnHomeEl" class="google-btn-host google-btn-home-compact" />
                            </div>
                        </div>
                    </div>
                    <p v-if="authBusy && tab === 'home'" class="muted auth-status">{{ msg('signingIn') }}</p>
                    <p v-if="authError && tab === 'home'" class="auth-error">{{ authError }}</p>

                    <section v-if="showHomeSkeleton" class="home-section" aria-busy="true" aria-label="Loading prices">
                        <div class="skeleton-line skeleton-title" />
                        <div class="home-primary-scroll">
                            <div class="home-primary-track skeleton-track">
                                <div v-for="n in 4" :key="n" class="skeleton-card" />
                            </div>
                        </div>
                    </section>

                    <section v-else-if="homePrimaryCards.length" class="home-section" aria-label="Main fuels">
                        <h3 class="home-section-title">{{ msg('mainFuels') }}</h3>
                        <div class="home-primary-scroll">
                            <div class="company-grid home-primary-grid home-primary-track">
                                <div class="company-card date-card home-fuel-card" aria-label="Price effective date">
                                    <div class="company-top">
                                        <span class="company-mark" aria-hidden="true">📅</span>
                                        <span>{{ msg('effective') }}</span>
                                    </div>
                                    <div class="company-price date-card-value">{{ effectiveLabel }}</div>
                                    <div v-if="last_sync?.finished_at" class="company-change flat">
                                        {{ msg('updated') }} {{ last_sync.finished_at }}
                                    </div>
                                    <div v-if="pricesStale" class="company-trend flat">
                                        <span class="stale-badge">{{ msg('pricesStale') }}</span>
                                    </div>
                                </div>
                                <div
                                    v-for="card in homePrimaryCards"
                                    :key="card.id"
                                    class="company-card home-fuel-card is-primary"
                                    :style="fuelCardStyle(card.id)"
                                >
                                    <div class="company-top">
                                        <span class="company-mark" aria-hidden="true">{{ card.mark }}</span>
                                        <span>{{ card.name }}</span>
                                    </div>
                                    <div class="company-price">
                                        <span class="company-currency">{{ msg('currencyRs') }}</span>
                                        <span class="company-amount">{{ homePrice(card.id).toFixed(2) }}</span>
                                        <span class="company-unit">/{{ priceUnitWord(card.unit) }}</span>
                                    </div>
                                    <div
                                        class="company-change"
                                        :class="changeMeta(card.id).noPrior || changeMeta(card.id).flat ? 'flat' : changeMeta(card.id).up ? 'up' : 'down'"
                                    >
                                        <span class="price-mood" aria-hidden="true">{{ changeMoodEmoji(changeMeta(card.id)) }}</span>
                                        <span v-if="changeMeta(card.id).noPrior || changeMeta(card.id).flat" class="delta-status">
                                            — {{ changeStatusLabel(changeMeta(card.id)) }}
                                        </span>
                                        <span v-else class="delta-badge">
                                            {{ changeMeta(card.id).up ? '▲' : '▼' }}
                                            {{ changeMeta(card.id).up ? '+' : '' }}{{ changeMeta(card.id).change.toFixed(2) }}
                                        </span>
                                        <span
                                            v-if="trend15d(card.id) && !trend15d(card.id)!.flat"
                                            class="delta-15d"
                                            :class="trend15d(card.id)!.up ? 'up' : 'down'"
                                        >
                                            {{ msg('trend15d') }}
                                            {{ trend15d(card.id)!.up ? '▲' : '▼' }}
                                            {{ trend15d(card.id)!.diff.toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="home-section" aria-label="Specialty fuels">
                        <h3 v-if="homeSpecialtyCards.length" class="home-section-title">
                            {{ msg('specialtyFuels') }}
                        </h3>
                        <div class="company-grid home-specialty-grid">
                            <div
                                v-for="card in homeSpecialtyCards"
                                :key="card.id + card.name"
                                class="company-card home-fuel-card is-specialty"
                                :style="SPECIALTY_CARD_STYLE"
                            >
                                <div class="company-top">
                                    <span class="company-mark" aria-hidden="true">{{ card.mark }}</span>
                                    <span>{{ card.name }}</span>
                                </div>
                                <div class="company-price">
                                    <span class="company-currency">{{ msg('currencyRs') }}</span>
                                    <span class="company-amount">{{ homePrice(card.id).toFixed(2) }}</span>
                                    <span class="company-unit">/{{ priceUnitWord(card.unit) }}</span>
                                </div>
                                <div
                                    class="company-change"
                                    :class="changeMeta(card.id).noPrior || changeMeta(card.id).flat ? 'flat' : changeMeta(card.id).up ? 'up' : 'down'"
                                >
                                    <span class="price-mood" aria-hidden="true">{{ changeMoodEmoji(changeMeta(card.id)) }}</span>
                                    <span v-if="changeMeta(card.id).noPrior || changeMeta(card.id).flat" class="delta-status">
                                        — {{ changeStatusLabel(changeMeta(card.id)) }}
                                    </span>
                                    <span v-else class="delta-badge">
                                        {{ changeMeta(card.id).up ? '▲' : '▼' }}
                                        {{ changeMeta(card.id).up ? '+' : '' }}{{ changeMeta(card.id).change.toFixed(2) }}
                                    </span>
                                    <span
                                        v-if="trend15d(card.id) && !trend15d(card.id)!.flat"
                                        class="delta-15d"
                                        :class="trend15d(card.id)!.up ? 'up' : 'down'"
                                    >
                                        {{ msg('trend15d') }}
                                        {{ trend15d(card.id)!.up ? '▲' : '▼' }}
                                        {{ trend15d(card.id)!.diff.toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="company-card home-fuel-card home-share-card"
                                @click="sharePrices"
                            >
                                <div class="company-top">
                                    <span class="company-mark" aria-hidden="true">↗</span>
                                    <span>{{ msg('share') }}</span>
                                </div>
                                <div class="company-price date-card-value">{{ msg('shareCardLead') }}</div>
                                <div class="company-change flat">{{ msg('sharePricesSub') }}</div>
                            </button>
                        </div>
                    </section>

                    <section v-if="homeFaqs.length" class="home-faq" aria-label="FAQ">
                        <h2>{{ msg('faqTitle') }}</h2>
                        <div class="faq-grid">
                            <details
                                v-for="(item, i) in homeFaqs"
                                :key="i"
                                class="faq-details"
                                @toggle="onFaqToggle"
                            >
                                <summary>{{ item.question }}</summary>
                                <p>{{ item.answer }}</p>
                            </details>
                        </div>
                        <p class="seo-links">
                            <a href="/petrol-price-today">{{ msg('seoPetrolToday') }}</a>
                            ·
                            <a href="/diesel-price-today">{{ msg('seoDieselToday') }}</a>
                            ·
                            <a href="/lpg-price-today">{{ msg('seoLpgToday') }}</a>
                            ·
                            <a href="/prices">{{ msg('seoCities') }}</a>
                            ·
                            <a href="/faq">{{ msg('faqTitle') }}</a>
                        </p>
                    </section>
                </div>

                <!-- COMPARE -->
                <div v-else-if="tab === 'compare'" class="pane">
                    <div class="pane-header">
                        <div>
                            <h2>{{ msg('compareTitle') }}</h2>
                            <p class="muted">{{ msg('compareSub') }}</p>
                        </div>
                        <div class="filter-row">
                            <SearchSelect
                                v-model="fuelId"
                                :options="fuelOptions"
                                :placeholder="msg('searchFuel')"
                                :empty-label="msg('noMatches')"
                                icon="⛽"
                            />
                        </div>
                    </div>

                    <label class="search-field">
                        <span>🏢</span>
                        <input
                            v-model="compareCompanyQuery"
                            type="search"
                            :placeholder="msg('searchCompany')"
                            autocomplete="off"
                            @focus="trackEvent('search_used', { field: 'company' })"
                        />
                    </label>

                    <div class="company-grid">
                        <div
                            v-for="c in compareRows"
                            :key="c.id"
                            class="company-card"
                            :class="{ best: isBestComparePrice(c.price) }"
                            :style="companyCardStyle(c.id)"
                        >
                            <div class="company-top">
                                <span class="company-mark" aria-hidden="true">{{ c.short?.slice(0, 2) || 'CO' }}</span>
                                <span>{{ c.name }}</span>
                            </div>
                            <div class="company-price">
                                <span class="company-currency">{{ msg('currencyRs') }}</span>
                                <span class="company-amount">{{ c.price.toFixed(2) }}</span>
                                <span class="company-unit">/{{ priceUnitWord(selectedFuel?.unit) }}</span>
                            </div>
                            <div
                                class="company-change"
                                :class="priceChangeMeta(c.price, c.previous).noPrior || priceChangeMeta(c.price, c.previous).flat ? 'flat' : priceChangeMeta(c.price, c.previous).up ? 'up' : 'down'"
                            >
                                <span
                                    class="price-mood"
                                    aria-hidden="true"
                                >{{ changeMoodEmoji(priceChangeMeta(c.price, c.previous)) }}</span>
                                <span
                                    v-if="priceChangeMeta(c.price, c.previous).noPrior || priceChangeMeta(c.price, c.previous).flat"
                                    class="delta-status"
                                >
                                    — {{ changeStatusLabel(priceChangeMeta(c.price, c.previous)) }}
                                </span>
                                <span v-else class="delta-badge">
                                    {{ priceChangeMeta(c.price, c.previous).up ? '▲' : '▼' }}
                                    {{ priceChangeMeta(c.price, c.previous).up ? '+' : '' }}{{ priceChangeMeta(c.price, c.previous).change.toFixed(2) }}
                                </span>
                            </div>
                            <span v-if="isBestComparePrice(c.price)" class="best-badge">{{ msg('bestPrice') }}</span>
                        </div>
                        <p v-if="!compareRows.length" class="muted">{{ msg('noCompanyMatch') }}</p>
                    </div>
                </div>

                <!-- HISTORY -->
                <div v-else-if="tab === 'history'" class="pane history-pane">
                    <div class="pane-header history-head">
                        <div>
                            <h2>{{ msg('historyTitle') }}</h2>
                            <p class="muted">{{ msg('historySub') }}</p>
                        </div>
                    </div>

                    <div class="history-toolbar">
                        <div class="history-toolbar-row">
                            <span class="history-label">{{ msg('pickPeriod') }}</span>
                            <div class="range-toggle">
                                <button
                                    v-for="r in (['d7', 'd30', 'd180', 'd365'] as const)"
                                    :key="r"
                                    type="button"
                                    :class="{ active: historyRange === r }"
                                    @click="setHistoryRange(r)"
                                >
                                    {{ msg(r) }}
                                </button>
                            </div>
                            <button type="button" class="chip history-share-btn" @click="shareHistoryChart">
                                {{ msg('shareChart') }}
                            </button>
                            <p class="muted history-period-hint">{{ historyPeriodHint }}</p>
                            <p v-if="chartSeries.ids.length" class="muted history-pct-row">
                                <span v-for="id in chartSeries.ids" :key="`pct-${id}`" class="history-pct">
                                    {{ shortFuelLabel(id) }}
                                    <template v-if="historyPctChange[id] !== null && historyPctChange[id] !== undefined">
                                        {{ (historyPctChange[id] as number) > 0 ? '+' : '' }}{{ historyPctChange[id] }}%
                                    </template>
                                    <template v-else>—</template>
                                </span>
                            </p>
                        </div>

                        <div class="history-toolbar-row">
                            <span class="history-label">{{ msg('chartLayout') }}</span>
                            <div class="range-toggle history-layout-toggle">
                                <button
                                    type="button"
                                    :class="{ active: historyLayout === 'line' }"
                                    @click="historyLayout = 'line'"
                                >
                                    {{ msg('chartLine') }}
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: historyLayout === 'area' }"
                                    @click="historyLayout = 'area'"
                                >
                                    {{ msg('chartArea') }}
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: historyLayout === 'bars' }"
                                    @click="historyLayout = 'bars'"
                                >
                                    {{ msg('chartBars') }}
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: historyLayout === 'cards' }"
                                    @click="historyLayout = 'cards'"
                                >
                                    {{ msg('chartCards') }}
                                </button>
                            </div>
                        </div>

                        <div class="history-toolbar-row history-fuel-row">
                            <div class="range-toggle history-fuel-toggle" role="radiogroup" :aria-label="msg('allFuels')">
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="historyFuelFocus === 'all'"
                                    :class="{ active: historyFuelFocus === 'all' }"
                                    @click="setHistoryFuelFocus('all')"
                                >
                                    {{ msg('allFuels') }}
                                </button>
                                <button
                                    v-for="id in chartSeries.available"
                                    :key="id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="historyFuelFocus === id"
                                    :class="{ active: historyFuelFocus === id }"
                                    @click="setHistoryFuelFocus(id)"
                                >
                                    {{ shortFuelLabel(id) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cards layout: one sparkline card per fuel -->
                    <div v-if="historyLayout === 'cards'" class="history-cards">
                        <article v-for="s in fuelStats" :key="s.id" class="history-fuel-card">
                            <header>
                                <div>
                                    <strong>{{ s.name }}</strong>
                                    <p class="muted">
                                        {{ msg('chartLatest') }}
                                        {{ s.latest?.toFixed(2) ?? '—' }}
                                    </p>
                                </div>
                                <span
                                    class="history-change-pill"
                                    :class="{
                                        up: (s.change ?? 0) > 0,
                                        down: (s.change ?? 0) < 0,
                                        flat: !s.change,
                                    }"
                                >
                                    {{
                                        s.change === null
                                            ? '—'
                                            : `${s.change > 0 ? '+' : ''}${s.change.toFixed(2)}`
                                    }}
                                </span>
                            </header>
                            <svg viewBox="0 0 120 40" class="history-spark" aria-hidden="true">
                                <path
                                    v-if="s.spark"
                                    :d="s.spark"
                                    fill="none"
                                    :stroke="s.color"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-if="s.latest !== null && s.spark"
                                    :cx="120"
                                    :cy="8"
                                    r="2.5"
                                    :fill="s.color"
                                />
                            </svg>
                            <footer>
                                <span>{{ msg('chartHigh') }} {{ s.high?.toFixed(2) ?? '—' }}</span>
                                <span>{{ msg('chartLow') }} {{ s.low?.toFixed(2) ?? '—' }}</span>
                            </footer>
                        </article>
                        <p v-if="!fuelStats.length" class="muted chart-empty">
                            {{
                                historyFuelFocus !== 'all'
                                    && chartSeries.available.includes(historyFuelFocus)
                                    && !chartSeries.ids.length
                                    ? msg('historyInsufficient')
                                    : msg('noHistoryData')
                            }}
                        </p>
                    </div>

                    <!-- Line / Area / Bars: single main chart (remounts on layout change) -->
                    <div v-else class="chart-card">
                        <div v-if="homeBooting" class="skeleton-chart" aria-busy="true" />
                        <svg
                            v-else-if="chartSeries.rows.length && chartSeries.ids.length"
                            :key="`main-chart-${historyLayout}-${historyRange}-${historyFuelFocus}`"
                            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
                            class="chart-svg"
                            role="img"
                            :aria-label="msg('historyTitle')"
                            @mousemove="onChartMove"
                            @mouseleave="onChartLeave"
                            @touchstart.passive="onChartMove"
                            @touchmove.passive="onChartMove"
                            @touchend="onChartLeave"
                        >
                            <defs>
                                <linearGradient
                                    v-for="id in chartSeries.ids"
                                    :id="`grad-${historyLayout}-${id}`"
                                    :key="`grad-${historyLayout}-${id}`"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop offset="0%" :stop-color="FUEL_COLOR[id] || '#F2A63F'" stop-opacity="0.45" />
                                    <stop offset="100%" :stop-color="FUEL_COLOR[id] || '#F2A63F'" stop-opacity="0.04" />
                                </linearGradient>
                            </defs>

                            <rect
                                :x="CHART_PAD.left"
                                :y="CHART_PAD.top"
                                :width="CHART_INNER_W"
                                :height="CHART_INNER_H"
                                class="chart-plot-bg"
                            />

                            <g class="chart-grid">
                                <line
                                    v-for="tick in chartYTicks"
                                    :key="`gy-${tick.label}`"
                                    :x1="CHART_PAD.left"
                                    :x2="CHART_PAD.left + CHART_INNER_W"
                                    :y1="tick.y"
                                    :y2="tick.y"
                                />
                            </g>

                            <g class="chart-y-axis">
                                <text
                                    v-for="tick in chartYTicks"
                                    :key="`yl-${tick.label}`"
                                    :x="CHART_PAD.left - 8"
                                    :y="tick.y + 4"
                                    text-anchor="end"
                                >
                                    {{ tick.label }}
                                </text>
                            </g>

                            <g class="chart-x-axis">
                                <text
                                    v-for="(lab, i) in chartXLabels"
                                    :key="`xl-${i}`"
                                    :x="lab.x"
                                    :y="CHART_H - 10"
                                    text-anchor="middle"
                                >
                                    {{ lab.label }}
                                </text>
                            </g>

                            <g v-if="historyLayout === 'area'">
                                <path
                                    v-for="id in chartSeries.ids"
                                    :key="`area-${id}`"
                                    :d="chartAreaPath(id)"
                                    :fill="`url(#grad-${historyLayout}-${id})`"
                                    stroke="none"
                                />
                                <path
                                    v-for="id in chartSeries.ids"
                                    :key="`area-line-${id}`"
                                    :d="chartLinePath(id)"
                                    fill="none"
                                    :stroke="FUEL_COLOR[id] || '#F2A63F'"
                                    stroke-width="2"
                                    stroke-linejoin="round"
                                    stroke-linecap="round"
                                />
                            </g>

                            <g v-else-if="historyLayout === 'line'">
                                <path
                                    v-for="id in chartSeries.ids"
                                    :key="`line-${id}`"
                                    :d="chartLinePath(id)"
                                    fill="none"
                                    :stroke="FUEL_COLOR[id] || '#F2A63F'"
                                    stroke-width="2.8"
                                    stroke-linejoin="round"
                                    stroke-linecap="round"
                                />
                                <template v-for="id in chartSeries.ids" :key="`dots-${id}`">
                                    <circle
                                        v-for="(pt, pi) in chartPoints(id)"
                                        :key="`dot-${id}-${pi}`"
                                        :cx="pt.x"
                                        :cy="pt.y"
                                        r="3.2"
                                        :fill="FUEL_COLOR[id] || '#F2A63F'"
                                        stroke="var(--bg-panel)"
                                        stroke-width="1.2"
                                    />
                                </template>
                            </g>

                            <g v-else-if="historyLayout === 'bars'">
                                <rect
                                    v-for="(bar, i) in chartBars"
                                    :key="`bar-${i}`"
                                    :x="bar.x"
                                    :y="bar.y"
                                    :width="bar.w"
                                    :height="bar.h"
                                    :fill="bar.color"
                                    rx="3"
                                >
                                    <title>{{ bar.label }} · {{ bar.value.toFixed(2) }}</title>
                                </rect>
                            </g>

                            <g v-if="chartHoverData" class="chart-hover">
                                <line
                                    :x1="chartHoverData.x"
                                    :x2="chartHoverData.x"
                                    :y1="CHART_PAD.top"
                                    :y2="CHART_PAD.top + CHART_INNER_H"
                                    class="chart-hover-line"
                                />
                                <circle
                                    v-for="e in chartHoverData.entries"
                                    :key="`hv-${e.id}`"
                                    :cx="chartHoverData.x"
                                    :cy="chartY(e.value)"
                                    r="5"
                                    :fill="e.color"
                                    stroke="var(--bg-panel)"
                                    stroke-width="1.8"
                                />
                            </g>
                        </svg>

                        <div v-if="chartHoverData" class="chart-tooltip" :style="chartTooltipStyle" aria-hidden="true">
                            <strong>{{ chartHoverData.date }}</strong>
                            <div v-for="e in chartHoverData.entries" :key="`tt-${e.id}`" class="chart-tooltip-row">
                                <i :style="{ background: e.color }" />
                                <span>{{ e.label }}</span>
                                <b>Rs {{ e.value.toFixed(2) }}</b>
                            </div>
                        </div>

                        <div
                            v-if="!homeBooting && chartSeries.ids.length && chartSeries.rows.length"
                            class="legend"
                        >
                            <span v-for="id in chartSeries.ids" :key="id" class="legend-item">
                                <i :style="{ background: FUEL_COLOR[id] || '#F2A63F' }" />
                                {{ fuelLabel(id) }}
                            </span>
                            <span class="legend-layout-tag">{{ msg(
                                historyLayout === 'area'
                                    ? 'chartArea'
                                    : historyLayout === 'bars'
                                      ? 'chartBars'
                                      : 'chartLine',
                            ) }}</span>
                        </div>
                        <p v-else-if="!homeBooting" class="muted chart-empty">
                            {{
                                historyFuelFocus !== 'all'
                                    && chartSeries.available.includes(historyFuelFocus)
                                    && !chartSeries.ids.length
                                    ? msg('historyInsufficient')
                                    : msg('noHistoryData')
                            }}
                        </p>
                    </div>
                </div>

                <!-- STATIONS -->
                <div v-else-if="tab === 'stations'" class="pane">
                    <div class="pane-header">
                        <div>
                            <h2>{{ msg('stationsTitle') }}</h2>
                            <p class="muted">
                                {{
                                    useNearby && nearbyStations.length
                                        ? msg('stationsNearbySub')
                                        : msg('stationsSub')
                                }}
                            </p>
                        </div>
                        <div class="filter-row">
                            <SearchSelect
                                v-model="stationCompany"
                                :options="companyOptions"
                                :placeholder="msg('searchCompany')"
                                :empty-label="msg('noMatches')"
                                :empty-option-label="msg('allCompanies')"
                                allow-empty
                                icon="🏢"
                            />
                        </div>
                    </div>

                    <div class="quick-chips">
                        <button
                            type="button"
                            class="chip"
                            :class="{ active: useNearby }"
                            @click="useNearby = true; loadNearbyStations(true)"
                        >
                            📍 {{ msg('useMyLocation') }}
                        </button>
                        <button
                            type="button"
                            class="chip"
                            :class="{ active: !useNearby }"
                            @click="applyCitySelection(city)"
                        >
                            🏙 {{ msg('cityStations') }}
                        </button>
                    </div>

                    <label class="search-field">
                        <span>🧭</span>
                        <input
                            v-model="stationQuery"
                            type="search"
                            :placeholder="msg('searchLocation')"
                            autocomplete="off"
                        />
                    </label>

                    <p v-if="nearbyLoading" class="muted">{{ msg('locating') }}</p>
                    <p v-else-if="nearbyError" class="muted warn-line">{{ nearbyError }}</p>

                    <div v-if="filteredStations.length" class="station-list">
                        <article
                            v-for="s in filteredStations"
                            :key="s.id"
                            class="station-card"
                            :style="companyCardStyle(s.company_id)"
                        >
                            <div class="station-top">
                                <strong>{{ s.name }}</strong>
                                <span v-if="s.distance_km != null" class="badge-24h">{{ s.distance_km.toFixed(1) }} {{ msg('unitKm') }}</span>
                                <span v-else-if="s.is_open_24h" class="badge-24h">{{ msg('open24') }}</span>
                            </div>
                            <p class="station-meta">
                                <span class="company-pill">{{ companyLabel(s.company) || '—' }}</span>
                                <template v-if="s.is_open_now === true"> · {{ msg('openNow') }}</template>
                                <template v-else-if="s.is_open_now === false"> · {{ msg('closedNow') }}</template>
                            </p>
                            <p class="station-address">{{ s.address }}</p>
                            <div class="station-actions">
                                <a
                                    v-if="s.maps_url"
                                    class="link-btn"
                                    :href="s.maps_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    🧭 {{ msg('directions') }}
                                </a>
                                <a
                                    v-if="s.maps_url"
                                    class="link-btn secondary"
                                    :href="s.maps_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    🗺 {{ msg('openMap') }}
                                </a>
                            </div>
                        </article>
                    </div>
                    <p v-else-if="!nearbyLoading && !nearbyError" class="muted">{{ msg('noStations') }}</p>
                </div>

                <!-- CALCULATORS -->
                <div v-else-if="tab === 'calc'" class="pane">
                    <div class="pane-header">
                        <div>
                            <h2>{{ msg('calculators') }}</h2>
                            <p class="muted">
                                {{ msg('using') }}
                                {{ fuel_types.find((f) => f.id === calcFuelId)?.name || msg('fuelLabel') }}
                                {{ msg('atPrice') }} {{ msg('currencyRs') }} {{ calcPrice.toFixed(2) }}
                            </p>
                        </div>
                        <div v-if="vehicles.length" class="city-picker">
                            <span>🚗</span>
                            <select v-model="selectedVehicleId">
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.name }}</option>
                            </select>
                        </div>
                    </div>

                    <p v-if="!vehicles.length" class="muted">{{ msg('garageSub') }}</p>
                    <p v-else-if="selectedVehicle" class="muted calc-vehicle-hint">
                        {{ selectedVehicle.name }} · {{ selectedVehicle.mileage }} {{ msg('unitKmPerL') }} —
                        {{ msg('mileageFromVehicle') }}
                    </p>

                    <div class="range-toggle calc-toggle">
                        <button type="button" :class="{ active: calc === 'fuel' }" @click="calc = 'fuel'">
                            {{ msg('fuelCost') }}
                        </button>
                        <button type="button" :class="{ active: calc === 'tank' }" @click="calc = 'tank'">
                            {{ msg('tankFill') }}
                        </button>
                        <button type="button" :class="{ active: calc === 'trip' }" @click="calc = 'trip'">
                            {{ msg('tripCost') }}
                        </button>
                    </div>

                    <div v-if="calc === 'fuel'" class="calc-grid">
                        <div class="calc-inputs">
                            <label>{{ msg('distance') }}<input v-model.number="distance" type="number" min="0" step="any" /></label>
                            <label>
                                {{ msg('mileage') }}
                                <input v-model.number="mileage" type="number" min="1" max="200" step="any" />
                            </label>
                        </div>
                        <div class="calc-outputs">
                            <div class="out-row"><span>{{ msg('fuelRequired') }}</span><b>{{ litresNeeded.toFixed(2) }} {{ msg('unitL') }}</b></div>
                            <div class="out-row"><span>{{ msg('totalCost') }}</span><b>{{ msg('currencyRs') }} {{ fuelCost.toFixed(0) }}</b></div>
                            <div class="out-row"><span>{{ msg('costPerKm') }}</span><b>{{ msg('currencyRs') }} {{ costPerKm.toFixed(2) }}</b></div>
                        </div>
                    </div>

                    <div v-else-if="calc === 'tank'" class="calc-grid">
                        <div class="calc-inputs">
                            <label>{{ msg('tankCap') }}<input v-model.number="tankCapacity" type="number" /></label>
                            <label>{{ msg('fuelLevel') }}<input v-model.number="currentLevel" type="number" min="0" max="100" /></label>
                        </div>
                        <div class="calc-outputs">
                            <div class="out-row"><span>{{ msg('litresToFill') }}</span><b>{{ litresToFill.toFixed(2) }} {{ msg('unitL') }}</b></div>
                            <div class="out-row"><span>{{ msg('amountPayable') }}</span><b>{{ msg('currencyRs') }} {{ fillCost.toFixed(0) }}</b></div>
                        </div>
                    </div>

                    <div v-else class="calc-grid">
                        <div class="calc-inputs">
                            <label>{{ msg('tripDist') }}<input v-model.number="tripDistance" type="number" min="0" step="any" /></label>
                            <label>
                                {{ msg('mileage') }}
                                <input v-model.number="mileage" type="number" min="1" max="200" step="any" />
                            </label>
                        </div>
                        <div class="calc-outputs">
                            <div class="out-row"><span>{{ msg('fuelOneWay') }}</span><b>{{ tripLitres.toFixed(2) }} {{ msg('unitL') }}</b></div>
                            <div class="out-row"><span>{{ msg('oneWay') }}</span><b>{{ msg('currencyRs') }} {{ oneWayCost.toFixed(0) }}</b></div>
                            <div class="out-row"><span>{{ msg('roundTrip') }}</span><b>{{ msg('currencyRs') }} {{ roundTripCost.toFixed(0) }}</b></div>
                        </div>
                    </div>
                </div>

                <!-- GARAGE -->
                <div v-else-if="tab === 'garage'" class="pane">
                    <div class="pane-header">
                        <div>
                            <h2>{{ msg('garageTitle') }}</h2>
                            <p class="muted">{{ msg('garageSub') }}</p>
                        </div>
                    </div>

                    <div class="vehicle-list">
                        <article v-for="v in vehicles" :key="v.id" class="vehicle-card">
                            <div class="vehicle-top">
                                <strong>{{ v.name }}</strong>
                                <span class="vehicle-fuel">{{ v.fuel }}</span>
                            </div>
                            <p class="vehicle-meta">
                                {{ v.mileage }} {{ msg('unitKmPerL') }} · {{ v.tank }} {{ msg('unitL') }} {{ msg('tankSuffix') }}
                            </p>
                            <div class="vehicle-actions">
                                <button type="button" class="action-btn" @click="useInCalc(v.id)">
                                    {{ msg('useInCalc') }}
                                </button>
                                <button type="button" class="action-btn danger" @click="deleteVehicle(v.id)">
                                    {{ msg('delete') }}
                                </button>
                            </div>
                        </article>
                    </div>

                    <form class="vehicle-form" @submit.prevent="addVehicle">
                        <h3>{{ msg('addVehicle') }}</h3>
                        <label>{{ msg('nickname') }}<input v-model="newVehicleName" type="text" required /></label>
                        <div class="form-row">
                            <label>
                                {{ msg('mileage') }}
                                <input v-model.number="newVehicleMileage" type="number" min="1" max="200" step="any" required />
                            </label>
                            <label>{{ msg('tankCap') }}<input v-model.number="newVehicleTank" type="number" min="1" step="1" /></label>
                        </div>
                        <label>
                            {{ msg('fuelLabel') }}
                            <select v-model="newVehicleFuel">
                                <option v-for="f in fuel_types" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                        </label>
                        <button type="submit" class="primary-btn">{{ msg('save') }}</button>
                    </form>
                </div>

                <!-- MORE -->
                <div v-else class="pane">
                    <div class="pane-header">
                        <div>
                            <h2>{{ msg('moreTitle') }}</h2>
                        </div>
                    </div>

                    <div class="settings-list">
                        <button type="button" class="setting-row setting-row-btn" @click="tab = 'garage'">
                            <div class="setting-row-copy">
                                <strong>🚗 {{ msg('garageTitle') }}</strong>
                                <p class="muted">{{ msg('garageSub') }}</p>
                            </div>
                            <span class="action-btn">{{ msg('garage') }}</span>
                        </button>

                        <a class="setting-row setting-row-btn" href="/guides">
                            <div class="setting-row-copy">
                                <strong>📰 {{ msg('guides') }}</strong>
                                <p class="muted">Fuel news, tips and calculators</p>
                            </div>
                            <span class="action-btn">{{ msg('guides') }}</span>
                        </a>

                        <div class="setting-row">
                            <div class="setting-row-copy">
                                <strong>{{ msg('favoriteCity') }}</strong>
                                <p class="muted">
                                    {{
                                        cities.find((c) => c.id === favoriteCityId)?.name
                                            || msg('saveFavorite')
                                    }}
                                </p>
                            </div>
                            <button type="button" class="action-btn" @click="toggleFavoriteCity">
                                {{ favoriteCityId === city ? msg('clearFavorite') : msg('saveFavorite') }}
                            </button>
                        </div>

                        <div class="setting-row">
                            <div class="setting-row-copy">
                                <strong>{{ msg('favoriteFuel') }}</strong>
                                <p class="muted">{{ fuel_types.find((f) => f.id === favoriteFuelId)?.name || favoriteFuelId }}</p>
                            </div>
                            <select
                                class="inline-select"
                                :value="favoriteFuelId"
                                @change="setFavoriteFuel(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="f in fuel_types" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                        </div>

                        <div v-if="recentCities.length" class="setting-row">
                            <div class="setting-row-copy">
                                <strong>{{ msg('recentCities') }}</strong>
                                <div class="quick-chips" style="margin-top: 8px">
                                    <button
                                        v-for="id in recentCities"
                                        :key="id"
                                        type="button"
                                        class="chip"
                                        :class="{ active: city === id }"
                                        @click="applyCitySelection(id); tab = 'home'"
                                    >
                                        {{ cities.find((c) => c.id === id)?.name || id }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="recentCalcs.length" class="setting-row">
                            <div class="setting-row-copy">
                                <strong>{{ msg('recentCalcs') }}</strong>
                                <div class="quick-chips" style="margin-top: 8px">
                                    <button
                                        v-for="mode in recentCalcs"
                                        :key="mode"
                                        type="button"
                                        class="chip"
                                        @click="openRecentCalc(mode)"
                                    >
                                        {{ mode }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="authUser" class="setting-row auth-row">
                            <div class="auth-user">
                                <img
                                    v-if="authUser.avatar_url"
                                    class="auth-avatar"
                                    :src="authUser.avatar_url"
                                    alt=""
                                    width="40"
                                    height="40"
                                    referrerpolicy="no-referrer"
                                />
                                <div class="setting-row-copy">
                                    <strong>{{ authUser.name || msg('signedIn') }}</strong>
                                    <p class="muted">{{ authUser.email || msg('googleAccount') }}</p>
                                </div>
                            </div>
                            <button type="button" class="action-btn danger" :disabled="authBusy" @click="logoutAuth">
                                {{ msg('signOut') }}
                            </button>
                        </div>
                        <div v-else-if="googleLoginEnabled" class="auth-login-block">
                            <div class="setting-row-copy">
                                <strong>{{ msg('signIn') }}</strong>
                                <p class="muted">{{ msg('googleLoginHint') }}</p>
                            </div>
                            <div ref="googleBtnMoreEl" class="google-btn-host" />
                            <p v-if="authBusy" class="muted auth-status">{{ msg('signingIn') }}</p>
                            <p v-if="authError && tab === 'more'" class="auth-error">{{ authError }}</p>
                        </div>

                        <div class="lang-block">
                            <div class="setting-row-copy">
                                <strong>{{ msg('language') }}</strong>
                                <p class="muted">{{ msg('languageHint') }}</p>
                            </div>
                            <div class="lang-picker" role="radiogroup" :aria-label="msg('language')">
                                <button
                                    type="button"
                                    class="lang-option"
                                    role="radio"
                                    :aria-checked="locale === 'en'"
                                    :class="{ active: locale === 'en' }"
                                    @click="applyLocale('en')"
                                >
                                    <strong>{{ msg('english') }}</strong>
                                    <small>{{ msg('layoutLtr') }}</small>
                                </button>
                                <button
                                    type="button"
                                    class="lang-option"
                                    role="radio"
                                    :aria-checked="locale === 'ur'"
                                    :class="{ active: locale === 'ur' }"
                                    @click="applyLocale('ur')"
                                >
                                    <strong>{{ msg('urdu') }}</strong>
                                    <small>{{ msg('layoutRtl') }}</small>
                                </button>
                            </div>
                        </div>

                        <button type="button" class="setting-row setting-row-btn" @click="openThemePicker">
                            <div class="setting-row-copy">
                                <strong>{{ msg('theme') }}</strong>
                                <p class="muted">{{ themePrefLabel }}</p>
                            </div>
                            <span class="action-btn">{{ msg('themeManager') }}</span>
                        </button>

                        <div class="setting-row">
                            <div>
                                <strong>{{ msg('alerts') }}</strong>
                                <p class="muted">{{ alertsEnabled ? msg('alertsOn') : msg('alertsOff') }}</p>
                            </div>
                            <button
                                v-if="!alertsEnabled"
                                type="button"
                                class="action-btn"
                                @click="enableAlerts"
                            >
                                {{ msg('enableAlerts') }}
                            </button>
                            <button v-else type="button" class="action-btn danger" @click="disableAlerts">
                                {{ msg('alertsOffBtn') }}
                            </button>
                        </div>

                        <div v-if="pwaInstalled" class="setting-row">
                            <div>
                                <strong>{{ msg('installApp') }}</strong>
                                <p class="muted">{{ msg('installedOk') }}</p>
                            </div>
                        </div>
                        <button
                            v-else-if="pwaInstallable"
                            type="button"
                            class="setting-row setting-row-btn"
                            @click="installPwa"
                        >
                            <div>
                                <strong>{{ msg('installApp') }}</strong>
                                <p class="muted">{{ msg('installAppSub') }}</p>
                            </div>
                            <span class="action-btn">{{ msg('installNow') }}</span>
                        </button>
                        <div v-else class="setting-row">
                            <div>
                                <strong>{{ msg('installApp') }}</strong>
                                <p class="muted">{{ msg('installHint') }}</p>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-row-copy">
                                <strong>{{ msg('whatsappShare') }}</strong>
                                <p class="muted">{{ msg('sharePricesSub') }}</p>
                            </div>
                            <button type="button" class="action-btn wa" @click="sharePrices">
                                {{ msg('share') }}
                            </button>
                        </div>

                        <div class="setting-row">
                            <div>
                                <strong>{{ msg('seoPages') }}</strong>
                                <p class="muted">{{ msg('seoPagesSub') }}</p>
                            </div>
                            <div class="legal-actions">
                                <a class="action-btn" href="/petrol-price-today">{{ msg('seoPetrolToday') }}</a>
                                <a class="action-btn" href="/prices">{{ msg('seoCities') }}</a>
                                <a class="action-btn" href="/faq">{{ msg('faqTitle') }}</a>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div>
                                <strong>{{ msg('legalLinks') }}</strong>
                                <p class="muted">{{ msg('privacy') }} · {{ msg('terms') }} · About · Contact</p>
                            </div>
                            <div class="legal-actions">
                                <a class="action-btn" href="/about">About</a>
                                <a class="action-btn" href="/contact" @click="trackEvent('contact_click', { method: 'page_link' })">Contact</a>
                                <a class="action-btn" href="/privacy">{{ msg('privacy') }}</a>
                                <a class="action-btn" href="/terms">{{ msg('terms') }}</a>
                                <a class="action-btn" href="/disclaimer">Disclaimer</a>
                                <a class="action-btn" href="/editorial-policy">Editorial</a>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div>
                                <strong>{{ msg('androidApp') }}</strong>
                                <p class="muted">{{ msg('androidAppSub') }}</p>
                            </div>
                            <a
                                class="action-btn"
                                href="/downloads/fuel-prices-pk.apk"
                                download
                                @click="trackEvent('app_download_click', { placement: 'settings' })"
                            >
                                {{ msg('androidAppBtn') }}
                            </a>
                        </div>

                        <div class="setting-row">
                            <div>
                                <strong>{{ msg('adminLogin') }}</strong>
                                <p class="muted">{{ msg('adminLoginSub') }}</p>
                            </div>
                            <a class="action-btn" href="/admin/login">{{ msg('adminLogin') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="bottom-nav" :aria-label="msg('navMain')">
                <button type="button" :class="{ active: tab === 'home' }" @click="tab = 'home'">
                    <span>⛽</span>
                    <small>{{ msg('home') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'compare' }" @click="tab = 'compare'">
                    <span>🏢</span>
                    <small>{{ msg('compare') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'stations' }" @click="tab = 'stations'">
                    <span>📍</span>
                    <small>{{ msg('stations') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'garage' }" @click="tab = 'garage'">
                    <span>🚗</span>
                    <small>{{ msg('garage') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'calc' }" @click="tab = 'calc'">
                    <span>🧮</span>
                    <small>{{ msg('calc') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'history' }" @click="tab = 'history'">
                    <span>📈</span>
                    <small>{{ msg('history') }}</small>
                </button>
                <button type="button" :class="{ active: tab === 'more' }" @click="tab = 'more'">
                    <span>⋯</span>
                    <small>{{ msg('more') }}</small>
                </button>
            </nav>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="themePickerOpen"
                class="theme-modal-backdrop"
                :data-theme="resolvedTheme"
                :data-locale="locale"
                :lang="locale === 'ur' ? 'ur' : 'en'"
                :dir="locale === 'ur' ? 'rtl' : 'ltr'"
                role="presentation"
                @click.self="closeThemePicker"
            >
                <div
                    class="theme-modal"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="msg('themeManager')"
                >
                    <div class="theme-modal-head">
                        <div class="theme-modal-head-copy">
                            <strong>{{ msg('themeManager') }}</strong>
                            <p class="muted">{{ msg('themeHint') }}</p>
                        </div>
                        <button type="button" class="theme-modal-close" @click="closeThemePicker" :aria-label="msg('close')">
                            ✕
                        </button>
                    </div>
                    <div class="theme-options" role="radiogroup" :aria-label="msg('theme')" style="display:flex; flex-direction:column; gap:20px;">
                        <div>
                            <div style="font-weight:600; margin-bottom:10px; color:var(--text-hi); display:flex; align-items:center; gap:6px;">
                                <span>🖥️</span> <span>Mode</span>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button
                                    type="button"
                                    style="flex:1; padding:12px; border-radius:12px; border:2px solid transparent; background:var(--bg-panel-alt); color:var(--text-hi); font-weight:600; display:flex; align-items:center; justify-content:center; gap:8px; transition:0.2s;"
                                    :style="themePref === 'dark' ? 'border-color:var(--amber); background:var(--bg-panel);' : ''"
                                    @click="applyThemePref('dark')"
                                >
                                    <span>🌙</span> <span>Dark</span>
                                </button>
                                <button
                                    type="button"
                                    style="flex:1; padding:12px; border-radius:12px; border:2px solid transparent; background:var(--bg-panel-alt); color:var(--text-hi); font-weight:600; display:flex; align-items:center; justify-content:center; gap:8px; transition:0.2s;"
                                    :style="themePref === 'light' ? 'border-color:var(--amber); background:var(--bg-panel);' : ''"
                                    @click="applyThemePref('light')"
                                >
                                    <span>☀️</span> <span>Light</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <div style="font-weight:600; margin-bottom:10px; color:var(--text-hi); display:flex; align-items:center; gap:6px;">
                                <span>🎨</span> <span>Accent Color</span>
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px;">
                                <button
                                    v-for="a in accentList"
                                    :key="a.id"
                                    type="button"
                                    style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding:10px 4px; border-radius:12px; border:2px solid transparent; background:var(--bg-panel-alt); transition:0.2s;"
                                    :style="currentAccent === a.id ? 'border-color:var(--amber); background:var(--bg-panel);' : ''"
                                    @click="applyAccentPref(a.id)"
                                    :title="a.name"
                                >
                                    <span :style="{ backgroundColor: a.color, width:'28px', height:'28px', borderRadius:'50%', display:'block', boxShadow:'0 2px 8px rgba(0,0,0,0.2)' }" />
                                    <span style="font-size:11px; color:var(--text-hi); font-weight:500;">{{ a.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="shareSheetOpen"
                class="theme-modal-backdrop"
                :data-theme="resolvedTheme"
                :data-locale="locale"
                :lang="locale === 'ur' ? 'ur' : 'en'"
                :dir="locale === 'ur' ? 'rtl' : 'ltr'"
                role="presentation"
                @click.self="closeShareSheet"
            >
                <div class="theme-modal" role="dialog" aria-modal="true" :aria-label="msg('shareVia')">
                    <div class="theme-modal-head">
                        <div class="theme-modal-head-copy">
                            <strong>{{ msg('shareVia') }}</strong>
                            <p class="muted">{{ msg('sharePricesSub') }}</p>
                        </div>
                        <button type="button" class="theme-modal-close" @click="closeShareSheet" :aria-label="msg('close')">
                            ✕
                        </button>
                    </div>
                    <div class="share-grid">
                        <button type="button" class="share-option" @click="shareVia('whatsapp')">
                            <span class="share-option-icon wa-icon" aria-hidden="true">✆</span>
                            <small>WhatsApp</small>
                        </button>
                        <button type="button" class="share-option" @click="shareVia('sms')">
                            <span class="share-option-icon" aria-hidden="true">💬</span>
                            <small>{{ msg('shareSms') }}</small>
                        </button>
                        <button type="button" class="share-option" @click="shareVia('email')">
                            <span class="share-option-icon" aria-hidden="true">✉️</span>
                            <small>{{ msg('shareEmail') }}</small>
                        </button>
                        <button type="button" class="share-option" @click="shareVia('telegram')">
                            <span class="share-option-icon" aria-hidden="true">✈️</span>
                            <small>Telegram</small>
                        </button>
                        <button type="button" class="share-option" @click="shareVia('facebook')">
                            <span class="share-option-icon" aria-hidden="true">📘</span>
                            <small>Facebook</small>
                        </button>
                        <button type="button" class="share-option" @click="shareVia('x')">
                            <span class="share-option-icon" aria-hidden="true">𝕏</span>
                            <small>X / Twitter</small>
                        </button>
                        <button type="button" class="share-option" @click="copyShareText">
                            <span class="share-option-icon" aria-hidden="true">📋</span>
                            <small>{{ shareCopied ? msg('shareCopied') : msg('shareCopy') }}</small>
                        </button>
                        <button
                            v-if="nativeShareAvailable"
                            type="button"
                            class="share-option"
                            @click="shareNative"
                        >
                            <span class="share-option-icon" aria-hidden="true">⋯</span>
                            <small>{{ msg('shareMore') }}</small>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
    </div>
</template>

<style scoped>
.app-root {
    display: flex;
    flex-direction: column;
    min-height: 100svh;
    width: 100%;
    background: #0b0f14;
}

.app-root :deep(.news-ticker-bar) {
    flex-shrink: 0;
    margin-bottom: 8px;
}

.app-container {
    /* Mobile: document scrolls (nested overflow:hidden breaks iOS touch). */
    flex: 1;
    min-height: 0;
    background: #0b0f14;
    display: flex;
    flex-direction: column;
    justify-content: stretch;
    transition: background 0.2s ease;
    overflow: visible;
    width: 100%;
}

.app-container[data-locale='ur'],
.app-container[data-locale='ur'] .app-shell,
.theme-modal-backdrop[data-locale='ur'],
:global(body.locale-ur) {
    font-family: 'Noto Nastaliq Urdu', 'IBM Plex Sans', sans-serif;
    line-height: 1.7;
}

.app-container[data-locale='ur'] .company-price,
.app-container[data-locale='ur'] .company-unit,
.app-container[data-locale='ur'] .company-mark,
.theme-modal-backdrop[data-locale='ur'] .company-price {
    font-family: 'IBM Plex Sans', sans-serif;
    line-height: 1.2;
}

/*
 * BASE SHELLS —
 * Accent color (--amber/--amber-dim) is applied
 * dynamically via JS (theme.ts applyAccent) and does NOT live here.
 * All other CSS variables (text, bg, borders) are set by these 2 rules.
 */

.app-shell {
    position: relative;
    z-index: 10;
    display: flex;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    background: var(--bg-deep);
    transition: background 0.3s ease;
    padding-bottom: env(safe-area-inset-bottom, 0px);
}

.app-frame {
    min-height: 0;
    flex: 1;
    background: var(--bg-deep);
    display: flex;
    justify-content: center;
    transition: background 0.2s ease;
    overflow: visible;
    width: 100%;
}

.app-shell {
    background: var(--bg-deep);
    color: var(--text-hi);
    font-family: 'IBM Plex Sans', sans-serif;
    width: 100%;
    max-width: 100%;
    min-height: 0;
    height: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    border-inline: 1px solid var(--border);
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    overflow: visible;
    box-sizing: border-box;
}

.app-main {
    flex: 1;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: visible;
    height: auto;
}

.bottom-nav {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background: var(--bg-panel);
    border-top: 1px solid var(--border);
    position: sticky;
    bottom: 0;
    z-index: 50;
    flex-shrink: 0;
}

.bottom-nav button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    min-width: 0;
    background: transparent;
    border: none;
    color: var(--text-mid);
    padding: 6px 0;
    gap: 2px;
    cursor: pointer;
}

.bottom-nav button:hover {
    color: var(--text-hi);
}

.bottom-nav button.active {
    color: var(--amber);
}

.bottom-nav button span {
    font-size: 16px;
    line-height: 1;
}

.bottom-nav button small {
    font-size: 9px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    text-align: center;
}

.sticky-tools {
    padding: 24px max(16px, env(safe-area-inset-right, 0px)) 10px max(16px, env(safe-area-inset-left, 0px));
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sticky-tools-search {
    width: 100%;
}

.sticky-tools-country-city {
    display: flex;
    gap: 8px;
}

.sticky-tools-country-city > * {
    flex: 1;
    min-width: 0;
}

.sticky-tools-country-city :deep(.theme-trigger-btn) {
    justify-content: space-between;
    box-sizing: border-box;
    height: 48px; /* Force symmetry with select inputs */
}

.sticky-tools-search :deep(.search-select) {
    width: 100%;
    height: 48px;
}

.fuel-chips {
    margin-bottom: 0;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 2px;
}

.fuel-chips::-webkit-scrollbar {
    display: none;
}

.fuel-chips .chip {
    flex: 0 0 auto;
    min-height: 36px;
    padding: 7px 12px;
    font-size: 12px;
}

.home-primary-scroll {
    margin: 0;
}

.home-primary-track.skeleton-track {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
}

.skeleton-line {
    height: 12px;
    border-radius: 6px;
    background: linear-gradient(
        90deg,
        var(--bg-panel-alt) 0%,
        color-mix(in srgb, var(--border) 55%, var(--bg-panel-alt)) 50%,
        var(--bg-panel-alt) 100%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.2s ease-in-out infinite;
}

.skeleton-title {
    width: 36%;
    margin-bottom: 10px;
}

.skeleton-card {
    flex: 0 0 min(78%, 260px);
    min-height: 118px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(
        90deg,
        var(--bg-panel) 0%,
        var(--bg-panel-alt) 50%,
        var(--bg-panel) 100%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.2s ease-in-out infinite;
}

.skeleton-chart {
    width: 100%;
    min-height: 220px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(
        90deg,
        var(--bg-panel) 0%,
        var(--bg-panel-alt) 50%,
        var(--bg-panel) 100%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.2s ease-in-out infinite;
}

@keyframes skeleton-shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

/* Phones: stack main fuels full-width (no clipped horizontal carousel). */
@media (max-width: 899px) {
    .home-primary-scroll {
        margin: 0;
        overflow: visible;
    }

    .home-primary-grid.home-primary-track,
    .home-primary-track.skeleton-track {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        width: 100%;
        min-width: 0;
    }

    .home-primary-track .home-fuel-card,
    .home-primary-track .skeleton-card {
        flex: none;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .home-specialty-grid {
        grid-template-columns: 1fr;
    }
}

.brand-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: radial-gradient(circle at 30% 30%, var(--amber), var(--amber-dim));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.app-header .grow {
    flex: 1;
    min-width: 0;
}

.app-header .brand-kicker {
    margin: 0 0 2px;
    font-family: 'Chakra Petch', sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    color: var(--amber);
}

.app-header h1 {
    font-family: 'Chakra Petch', sans-serif;
    font-size: 15px;
    margin: 0;
    letter-spacing: 0.2px;
    line-height: 1.25;
}

.app-header p {
    margin: 0;
    font-size: 11.5px;
    color: var(--text-mid);
}

.home-faq {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid var(--line);
}

.home-faq h2 {
    margin: 0 0 14px;
    font-size: 16px;
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (max-width: 768px) {
    .faq-grid {
        grid-template-columns: 1fr;
    }
}

.faq-details {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 8px 12px;
    margin-bottom: 8px;
    background: var(--panel);
}

.faq-details summary {
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    min-height: 44px;
    display: flex;
    align-items: center;
    list-style-position: outside;
}

.faq-details p {
    margin: 8px 0 4px;
    font-size: 12.5px;
    color: var(--text-mid);
    line-height: 1.45;
}

.seo-links {
    margin: 12px 0 0;
    font-size: 12px;
    color: var(--text-mid);
    line-height: 1.6;
}

.seo-links a {
    color: var(--amber);
    text-decoration: none;
}

.pane {
    padding: 14px max(12px, env(safe-area-inset-left, 0px)) 24px
        max(12px, env(safe-area-inset-right, 0px));
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
}

.pane-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.pane-header h2 {
    font-family: 'Chakra Petch', sans-serif;
    font-size: 18px;
    margin: 0 0 4px;
}

.muted {
    color: var(--text-mid);
    font-size: 12.5px;
    margin: 0;
}

.stale-badge {
    display: inline-block;
    margin-inline-start: 6px;
    padding: 1px 7px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 600;
    color: #7a4b00;
    background: #f5c451;
}

.quick-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 12px;
}

.chip {
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    color: var(--text-hi);
    font-size: 12px;
    padding: 10px 14px;
    min-height: 44px;
    border-radius: 20px;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

.chip:hover {
    border-color: var(--amber);
}

.chip.active {
    border-color: var(--amber);
    background: color-mix(in srgb, var(--amber) 18%, var(--bg-panel-alt));
    color: var(--amber);
}

.filter-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: flex-start;
}

.city-picker {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 12px;
    color: var(--text-mid);
    min-height: 44px;
    box-sizing: border-box;
    max-width: 100%;
}

.city-picker select {
    background: transparent;
    border: none;
    color: var(--text-hi);
    font-size: 16px;
    font-family: inherit;
    outline: none;
    max-width: min(220px, 100%);
    min-width: 0;
    flex: 1;
}

.city-picker select option {
    background: var(--bg-panel);
}

.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 12px;
    color: var(--text-mid);
    min-height: 44px;
    box-sizing: border-box;
}

.search-field input {
    flex: 1;
    min-width: 0;
    min-height: 24px;
    border: none;
    background: transparent;
    color: var(--text-hi);
    font-size: 16px;
    font-family: inherit;
    outline: none;
}

.search-field input::placeholder {
    color: var(--text-mid);
}

.company-change {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    margin-top: 8px;
    font-variant-numeric: tabular-nums;
}

.company-change.up {
    color: var(--up);
}

.company-change.down {
    color: var(--down);
}

.company-change.flat {
    color: var(--text-mid);
}

.company-trend {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
    opacity: 0.85;
}

.company-trend.up {
    color: var(--up);
}

.company-trend.down {
    color: var(--down);
}

.company-trend.flat {
    color: var(--text-mid);
}

.company-price.muted {
    color: var(--text-mid);
}

.hint-strip {
    margin-top: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-mid);
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
}

.company-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.company-card {
    --co-accent: #f2a63f;
    --co-soft: rgba(242, 166, 63, 0.12);
    background: linear-gradient(155deg, var(--co-soft), var(--bg-panel) 42%);
    border: 1px solid color-mix(in srgb, var(--co-accent) 45%, var(--border));
    border-inline-start: 3px solid var(--co-accent);
    border-radius: 14px;
    padding: 12px 14px;
    position: relative;
    min-width: 0;
    overflow: hidden;
}

.company-card.best {
    border-color: var(--down);
    box-shadow: 0 0 0 1px var(--down) inset;
}

.company-top {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: var(--text-mid);
    margin-bottom: 10px;
}

.company-mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 6px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--co-mark-ink, #ffffff);
    background: var(--co-accent);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--co-accent) 35%, transparent);
}

.company-price {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    column-gap: 0.22em;
    row-gap: 0.1em;
    font-family: inherit;
    font-size: clamp(18px, 5.2vw, 24px);
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.02em;
    color: var(--text-hi);
    min-width: 0;
}

.company-currency,
.company-unit {
    font-size: 0.55em;
    font-weight: 600;
    color: var(--text-mid);
    letter-spacing: 0.01em;
}

.company-amount {
    font-variant-numeric: tabular-nums;
}

.price-mood {
    font-size: 14px;
    line-height: 1;
    flex-shrink: 0;
    user-select: none;
}

.home-pane-header {
    align-items: flex-start;
    gap: 12px;
}

.home-title-block {
    min-width: 0;
    flex: 1;
}

.home-meta {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--text-mid);
    line-height: 1.45;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.home-header-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.home-signin-compact {
    display: flex;
    align-items: center;
}

.google-btn-home-compact {
    width: min(180px, 100%);
    min-width: 0;
    max-width: 100%;
}

.home-primary-grid,
.home-specialty-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.home-section {
    margin-top: 18px;
}

.home-section:first-of-type {
    margin-top: 4px;
}

.home-section-title {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-mid);
}

.home-fuel-card.is-primary .company-price {
    font-size: clamp(18px, 4.8vw, 24px);
}

.home-fuel-card.is-specialty {
    padding: 12px 14px;
}

.home-fuel-card.is-specialty .company-price {
    font-size: 20px;
}

.date-card {
    --co-accent: #5b8def;
    --co-soft: rgba(91, 141, 239, 0.14);
}

.date-card-value {
    font-size: 20px;
    letter-spacing: -0.02em;
}

.app-shell[data-theme='dark'] .date-card-value {
    color: #ffffff;
}

.home-share-card {
    --co-accent: #25d366;
    --co-soft: rgba(37, 211, 102, 0.14);
    width: 100%;
    text-align: start;
    cursor: pointer;
    font: inherit;
    color: inherit;
    appearance: none;
}

.home-share-card:hover {
    border-color: color-mix(in srgb, var(--co-accent) 65%, var(--border));
}

.home-share-card:focus-visible {
    outline: 2px solid var(--co-accent);
    outline-offset: 2px;
}

.company-change .delta-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    background: color-mix(in srgb, currentColor 12%, transparent);
}

.company-change .delta-status {
    font-size: 12px;
    font-weight: 500;
}

.calc-vehicle-hint {
    margin: -6px 0 12px;
    font-size: 12px;
}

.company-change .delta-15d {
    margin-inline-start: 2px;
    font-size: 11px;
    opacity: 0.9;
    font-variant-numeric: tabular-nums;
}

.company-change .delta-15d.up {
    color: var(--up);
}

.company-change .delta-15d.down {
    color: var(--down);
}

/* Dark theme: card body text matches white corner badges */
.app-shell[data-theme='dark'] .company-top {
    color: rgba(255, 255, 255, 0.78);
}

.app-shell[data-theme='dark'] .company-price {
    color: #ffffff;
}

.app-shell[data-theme='dark'] .company-currency,
.app-shell[data-theme='dark'] .company-unit,
.app-shell[data-theme='dark'] .company-change.flat {
    color: rgba(255, 255, 255, 0.55);
}

.app-shell[data-theme='dark'] .company-price.muted {
    color: rgba(255, 255, 255, 0.45);
}

.best-badge {
    position: absolute;
    top: 12px;
    inset-inline-end: 12px;
    background: var(--down);
    color: #08251a;
    font-size: 10px;
    font-weight: 600;
    padding: 3px 7px;
    border-radius: 20px;
}

.app-shell[data-theme='dark'] .best-badge {
    color: #ffffff;
}

.range-toggle {
    display: flex;
    gap: 4px;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 3px;
    width: fit-content;
    flex-wrap: wrap;
}

.range-toggle button {
    background: transparent;
    border: none;
    color: var(--text-mid);
    font-size: 12px;
    padding: 10px 12px;
    min-height: 40px;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    -webkit-tap-highlight-color: transparent;
}

.range-toggle button:focus-visible {
    outline: 2px solid var(--amber);
    outline-offset: 1px;
}

.range-toggle button.active {
    background: var(--amber);
    color: #1a1204;
    font-weight: 600;
}

.calc-toggle {
    margin-bottom: 18px;
}

.history-pane {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.history-head {
    margin-bottom: 0;
}

.history-toolbar {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px 14px;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.history-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
}

.history-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-mid);
    min-width: 3.5rem;
}

.history-period-hint {
    font-size: 12px;
}

.history-date-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.history-select {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-mid);
}

.history-select select {
    appearance: none;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-hi);
    font: inherit;
    font-size: 12.5px;
    padding: 6px 28px 6px 10px;
    background-image: linear-gradient(45deg, transparent 50%, var(--text-mid) 50%),
        linear-gradient(135deg, var(--text-mid) 50%, transparent 50%);
    background-position: calc(100% - 14px) 55%, calc(100% - 9px) 55%;
    background-size: 5px 5px, 5px 5px;
    background-repeat: no-repeat;
    cursor: pointer;
}

.history-layout-toggle {
    flex: 1 1 auto;
    min-width: 0;
}

.history-fuel-row {
    width: 100%;
}

.history-fuel-toggle {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    width: 100%;
    margin: 0;
    gap: 3px;
}

.history-fuel-toggle button {
    width: 100%;
    padding: 10px 6px;
    min-height: 44px;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

.history-stats {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.history-stat-card {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
}

.history-stat-card header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.history-stat-card header i {
    width: 9px;
    height: 9px;
    border-radius: 999px;
}

.history-stat-card header strong {
    font-size: 13px;
}

.history-stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 12px;
}

.history-stat-grid small {
    display: block;
    font-size: 10.5px;
    color: var(--text-mid);
    margin-bottom: 2px;
}

.history-stat-grid b {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 14px;
    font-weight: 600;
}

.history-stat-grid b.up {
    color: var(--up);
}

.history-stat-grid b.down {
    color: var(--down);
}

.history-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.history-fuel-card {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.history-fuel-card header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.history-fuel-card header strong {
    display: block;
    font-size: 14px;
}

.history-fuel-card header .muted {
    margin-top: 2px;
    font-size: 12px;
}

.history-change-pill {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--bg-panel-alt);
    color: var(--text-mid);
}

.history-change-pill.up {
    color: var(--up);
    background: color-mix(in srgb, var(--up) 14%, transparent);
}

.history-change-pill.down {
    color: var(--down);
    background: color-mix(in srgb, var(--down) 14%, transparent);
}

.history-spark {
    width: 100%;
    height: 48px;
    display: block;
}

.history-fuel-card footer {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 11.5px;
    color: var(--text-mid);
}

.chart-card {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 12px 12px;
    position: relative;
    overflow: visible;
    max-width: 100%;
}

.chart-hover-line {
    stroke: var(--text-mid);
    stroke-width: 1;
    stroke-dasharray: 4 4;
    opacity: 0.75;
}

.chart-tooltip {
    position: absolute;
    top: 18px;
    background: color-mix(in srgb, var(--bg-panel) 94%, var(--bg-deep));
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 11px;
    font-size: 12px;
    line-height: 1.5;
    color: var(--text-hi);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.28);
    pointer-events: none;
    z-index: 6;
    white-space: nowrap;
}

.chart-tooltip strong {
    display: block;
    font-size: 11.5px;
    color: var(--text-mid);
    margin-bottom: 3px;
}

.chart-tooltip-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.chart-tooltip-row i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.chart-tooltip-row b {
    margin-inline-start: auto;
    font-variant-numeric: tabular-nums;
}

.chart-svg {
    width: 100%;
    min-width: 0;
    height: auto;
    display: block;
    max-height: min(360px, 48svh);
}

.chart-plot-bg {
    fill: color-mix(in srgb, var(--bg-panel-alt) 55%, transparent);
    rx: 8;
}

.chart-grid line {
    stroke: var(--border);
    stroke-width: 1;
    stroke-dasharray: 3 4;
}

.chart-y-axis text,
.chart-x-axis text {
    fill: var(--text-mid);
    font-size: 11px;
    font-family: 'IBM Plex Sans', sans-serif;
}

.chart-empty {
    padding: 16px 12px;
    text-align: center;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 4px 0;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-mid);
}

.legend-item i {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
}

.legend-layout-tag {
    margin-inline-start: auto;
    font-size: 11px;
    font-weight: 600;
    color: var(--amber);
    background: color-mix(in srgb, var(--amber) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--amber) 35%, transparent);
    border-radius: 999px;
    padding: 3px 9px;
}

@media (min-width: 900px) {
    .history-toolbar-row {
        gap: 12px 16px;
    }

    .history-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .history-cards {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.station-list,
.vehicle-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 18px;
}

.station-card,
.vehicle-card {
    --co-accent: #f2a63f;
    --co-soft: rgba(242, 166, 63, 0.1);
    background: linear-gradient(155deg, var(--co-soft), var(--bg-panel) 48%);
    border: 1px solid color-mix(in srgb, var(--co-accent) 35%, var(--border));
    border-inline-start: 3px solid var(--co-accent);
    border-radius: 12px;
    padding: 14px 16px;
}

.company-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--co-accent);
    background: var(--co-soft);
    border: 1px solid color-mix(in srgb, var(--co-accent) 40%, transparent);
}

.station-top,
.vehicle-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.badge-24h {
    background: var(--down);
    color: #08251a;
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}

.station-meta,
.vehicle-meta,
.station-address {
    font-size: 12px;
    color: var(--text-mid);
    margin: 0 0 6px;
}

.station-actions,
.vehicle-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.link-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 13px;
    color: var(--amber);
    text-decoration: none;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    min-height: 44px;
    box-sizing: border-box;
}

.link-btn.secondary {
    color: var(--text-hi);
}

.vehicle-fuel {
    font-size: 11px;
    color: var(--amber);
    text-transform: uppercase;
}

.action-btn {
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    color: var(--text-hi);
    font-size: 13px;
    padding: 10px 14px;
    min-height: 44px;
    border-radius: 10px;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    -webkit-tap-highlight-color: transparent;
    box-sizing: border-box;
}

.action-btn:hover {
    border-color: var(--amber);
}

.action-btn:focus-visible {
    outline: 2px solid var(--amber);
    outline-offset: 2px;
}

.action-btn:active {
    transform: scale(0.98);
}

.action-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.action-btn.danger {
    color: var(--up);
}

.action-btn.wa {
    background: #1faf55;
    border-color: #1faf55;
    color: #fff;
}

.legal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
}

.vehicle-form {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vehicle-form h3 {
    margin: 0;
    font-family: 'Chakra Petch', sans-serif;
    font-size: 15px;
}

.vehicle-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    color: var(--text-mid);
}

.vehicle-form input,
.vehicle-form select {
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    color: var(--text-hi);
    font-size: 16px;
    font-family: inherit;
    outline: none;
    min-height: 44px;
    width: 100%;
    box-sizing: border-box;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.primary-btn {
    background: var(--amber);
    color: #1a1204;
    border: none;
    border-radius: 10px;
    padding: 12px 16px;
    min-height: 44px;
    font-weight: 600;
    font-size: 15px;
    font-family: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.primary-btn:active {
    transform: scale(0.98);
}

.primary-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.settings-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 18px;
}

.home-auth-card {
    margin-bottom: 12px;
}

.home-auth-signed {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
}

.home-auth-card-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.google-btn-home {
    width: 100%;
    min-width: 100%;
}

.auth-login-block {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
}

@media (min-width: 640px) {
    .home-auth-card-row {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .google-btn-home {
        width: auto;
        min-width: 240px;
        flex: 0 0 auto;
    }
}

.auth-user {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.auth-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--bg-panel-alt);
    flex-shrink: 0;
}

.google-btn-host {
    min-height: 44px;
    display: flex;
    justify-content: start;
}

.auth-status {
    margin: 0;
    font-size: 12.5px;
}

.auth-error {
    margin: 0;
    font-size: 12.5px;
    color: var(--up);
}

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
}

.setting-row > div,
.setting-row-copy {
    flex: 1;
    min-width: 0;
}

.setting-row strong {
    display: block;
    line-height: 1.25;
}

.setting-row .muted {
    margin: 4px 0 0;
    font-size: 12.5px;
    line-height: 1.35;
}

.setting-row .action-btn,
.setting-row > .action-btn {
    flex-shrink: 0;
    white-space: nowrap;
}

button.setting-row-btn {
    width: 100%;
    text-align: start;
    font: inherit;
    color: inherit;
    cursor: pointer;
}

button.setting-row-btn:hover {
    border-color: var(--amber);
}

.lang-block {
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
}

.lang-picker {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.lang-option {
    display: flex;
    flex-direction: column;
    align-items: start;
    gap: 2px;
    text-align: start;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--text-hi);
    font-family: inherit;
    cursor: pointer;
}

.lang-option small {
    color: var(--text-mid);
    font-size: 11px;
}

.lang-option.active {
    border-color: var(--amber);
    background: color-mix(in srgb, var(--amber) 14%, var(--bg-panel-alt));
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--amber) 40%, transparent);
}

.theme-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.theme-option {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) 22px;
    align-items: center;
    column-gap: 12px;
    width: 100%;
    text-align: start;
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
    color: var(--text-hi);
    font-family: inherit;
    cursor: pointer;
}

.pwa-banner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    background: color-mix(in srgb, var(--amber) 12%, var(--bg-panel));
}

.pwa-banner-text {
    flex: 1 1 180px;
    min-width: 0;
}

.pwa-banner-text strong {
    display: block;
    font-size: 14px;
    color: var(--text-hi);
}

.pwa-banner-text p {
    margin: 4px 0 0;
    font-size: 12.5px;
    color: var(--text-mid);
    line-height: 1.35;
}

.pwa-banner-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.pwa-install-btn {
    padding: 8px 14px;
    font-size: 13px;
}

.theme-modal-backdrop {
    --bg-deep: #10151c;
    --bg-panel: #1b232d;
    --bg-panel-alt: #212b37;
    --amber: #f2a63f;
    --text-hi: #f5f1e8;
    --text-mid: #9faab5;
    --border: #2a3441;
    --chip-on: color-mix(in srgb, var(--amber) 22%, var(--bg-panel));
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(8, 12, 18, 0.62);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: max(16px, env(safe-area-inset-top, 0px)) 16px
        max(16px, calc(72px + env(safe-area-inset-bottom, 0px)));
}

.theme-modal-backdrop[data-theme='light'] {
    --bg-deep: #f3f6fa;
    --bg-panel: #ffffff;
    --bg-panel-alt: #eef2f7;
    --amber: #c47a12;
    --text-hi: #15202b;
    --text-mid: #5b6775;
    --border: #d5dde8;
    --chip-on: color-mix(in srgb, var(--amber) 16%, #fff);
    background: rgba(15, 23, 32, 0.45);
}

.theme-modal {
    width: min(420px, 100%);
    max-height: min(85dvh, 640px);
    overflow-y: auto;
    background: var(--bg-panel);
    color: var(--text-hi);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
    animation: theme-sheet-in 0.18s ease-out;
}

.theme-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}

.theme-modal-head-copy {
    flex: 1;
    min-width: 0;
}

.theme-modal-head strong {
    display: block;
    font-size: 16px;
    line-height: 1.25;
    margin: 0 0 4px;
}

.theme-modal-head .muted {
    margin: 0;
    font-size: 12.5px;
    line-height: 1.35;
}

.theme-modal-close {
    flex-shrink: 0;
    border: 1px solid var(--border);
    background: var(--bg-panel-alt);
    color: var(--text-mid);
    width: 44px;
    height: 44px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
}

@keyframes theme-sheet-in {
    from {
        transform: translateY(12px);
        opacity: 0.6;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.theme-option-icon {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: color-mix(in srgb, var(--amber) 14%, var(--bg-panel));
    color: var(--amber);
    font-size: 16px;
    line-height: 1;
}

.theme-option-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.theme-option-copy strong {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.25;
    color: var(--text-hi);
}

.theme-option-copy small {
    font-size: 12px;
    line-height: 1.35;
    color: var(--text-mid);
}

.theme-option-check {
    width: 22px;
    text-align: center;
    color: var(--amber);
    font-size: 15px;
    font-weight: 700;
    line-height: 1;
}

.share-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    padding: 4px 2px 2px;
}

.share-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px 4px 10px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--bg-panel-alt);
    color: var(--text-hi);
    cursor: pointer;
    transition: border-color 0.15s ease, transform 0.1s ease;
}

.share-option:hover {
    border-color: var(--amber);
}

.share-option:active {
    transform: scale(0.96);
}

.share-option-icon {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: color-mix(in srgb, var(--amber) 12%, var(--bg-panel));
    font-size: 19px;
    line-height: 1;
}

.share-option-icon.wa-icon {
    background: rgb(37 211 102 / 0.15);
    color: #25d366;
    font-weight: 700;
}

.share-option small {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-mid);
    line-height: 1.2;
    text-align: center;
}

@media (max-width: 420px) {
    .share-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.theme-option.active {
    border-color: var(--amber);
    background: var(--chip-on);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--amber) 35%, transparent);
}

.calc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.calc-inputs {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.calc-inputs label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    color: var(--text-mid);
}

.calc-inputs input {
    background: var(--bg-panel-alt);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 12px;
    color: var(--text-hi);
    font-size: 16px;
    font-family: inherit;
    font-variant-numeric: tabular-nums;
    outline: none;
    min-height: 44px;
    width: 100%;
    box-sizing: border-box;
    -webkit-appearance: none;
    appearance: none;
}

.calc-inputs input:focus {
    border-color: var(--amber);
}

.calc-inputs input:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--amber) 55%, transparent);
    outline-offset: 1px;
}

.calc-outputs {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    height: fit-content;
}

.out-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: var(--text-mid);
    border-bottom: 1px dashed var(--border);
    padding-bottom: 10px;
}

.out-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.out-row b {
    color: var(--amber);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 15px;
}

@media (max-width: 640px) {
    .calc-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Laptop / PC — full-screen responsive */
@media (min-width: 900px) {
    .app-root {
        height: 100dvh;
        max-height: 100dvh;
        overflow: hidden;
    }

    .app-frame {
        align-items: stretch;
        padding: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background:
            radial-gradient(ellipse 70% 45% at 0% 0%, color-mix(in srgb, var(--amber) 15%, transparent), transparent 50%),
            radial-gradient(ellipse 50% 40% at 100% 100%, color-mix(in srgb, var(--amber) 8%, transparent), transparent 45%),
            var(--bg-deep);
    }

    .app-shell {
        max-width: none;
        width: 100%;
        min-height: 0;
        height: 100%;
        flex-direction: row;
        border: 0;
        border-radius: 0;
        overflow: hidden;
        box-shadow: none;
    }

    .app-main {
        min-height: 0;
        height: 100%;
        overflow: hidden;
    }

    .content {
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: contain;
    }

    .side-nav {
        display: flex;
        flex-direction: column;
        width: clamp(240px, 18vw, 280px);
        flex-shrink: 0;
        padding: 16px 12px;
        background: linear-gradient(180deg, var(--bg-panel), var(--bg-deep));
        border-inline-end: 1px solid var(--border);
        gap: 18px;
        transition: width 0.2s ease;
    }

    .side-nav.collapsed {
        width: 76px;
        padding-inline: 10px;
    }

    .side-brand {
        display: flex;
        gap: 10px;
        align-items: center;
        padding: 4px 8px 12px;
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .side-brand-text {
        min-width: 0;
        flex: 1;
    }

    .side-collapse-btn {
        margin-inline-start: auto;
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-panel-alt);
        color: var(--text-mid);
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
    }

    .side-collapse-btn:hover {
        color: var(--text-hi);
        border-color: color-mix(in srgb, var(--amber) 40%, var(--border));
    }

    .side-nav.collapsed .side-brand {
        flex-direction: column;
        gap: 8px;
        padding-inline: 0;
    }

    .side-nav.collapsed .side-brand-text,
    .side-nav.collapsed .side-link-label,
    .side-nav.collapsed .section-title,
    .side-nav.collapsed .chevron-svg,
    .side-nav.collapsed .side-foot {
        display: none;
    }

    .side-nav.collapsed .side-collapse-btn {
        margin-inline-start: 0;
    }

    .side-nav.collapsed .side-links button {
        justify-content: center;
        padding: 11px 8px;
        gap: 0;
    }

    .side-brand strong {
        display: block;
        font-family: 'Chakra Petch', sans-serif;
        font-size: 15px;
        letter-spacing: 0.2px;
    }

    .side-brand small {
        display: block;
        color: var(--text-mid);
        font-size: 11px;
        margin-top: 2px;
        line-height: 1.3;
    }

    .side-links {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
        overflow-x: hidden;
        overflow-y: auto;
        min-height: 0;
        padding-bottom: 12px;
    }

    .nav-section {
        margin-bottom: 0;
        flex-shrink: 0;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: border-color 0.2s ease, background 0.2s ease;
        overflow: visible;
    }

    .nav-section.is-expanded {
        border-color: rgba(242, 166, 63, 0.25);
        background: rgba(255, 255, 255, 0.03);
        overflow: visible;
    }

    .nav-section-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 10px 12px;
        background: rgba(255, 255, 255, 0.03);
        border: 0;
        border-left: 3px solid transparent;
        cursor: pointer;
        color: var(--text-hi, #e8eef5);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-sizing: border-box;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .section-title {
        white-space: nowrap;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .nav-section-bar:hover,
    .nav-section-bar.is-active {
        background: rgba(242, 166, 63, 0.12);
        color: var(--amber, #f2a63f);
        border-left-color: var(--amber, #f2a63f);
    }

    .chevron-svg {
        width: 14px;
        height: 14px;
        transition: transform 0.2s ease;
        flex-shrink: 0;
        margin-left: auto;
        color: currentColor;
    }

    .chevron-svg.rotate-90 {
        transform: rotate(90deg);
    }

    .nav-section-items {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 6px 8px 10px;
        overflow: visible;
    }

    .sticky-tools-country-city {
        gap: 10px;
    }

    @media (max-width: 640px) {
        .sticky-tools-country-city {
            gap: 6px;
        }
    }

    .side-links button,
    .side-links .side-nav-a {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        text-align: start;
        background: transparent;
        border: 0;
        color: var(--text-mid);
        font-family: inherit;
        font-size: 13.5px;
        padding: 10px 12px;
        border-radius: 10px;
        cursor: pointer;
        text-decoration: none;
        box-sizing: border-box;
        white-space: nowrap;
    }

    .side-link-label {
        white-space: nowrap;
    }

    .side-links button span,
    .side-links .side-nav-a span {
        width: 1.4em;
        text-align: center;
    }

    .side-links button:hover,
    .side-links .side-nav-a:hover {
        background: var(--bg-panel-alt);
        color: var(--text-hi);
    }

    .side-links button.active {
        background: color-mix(in srgb, var(--amber) 16%, var(--bg-panel-alt));
        color: var(--amber);
        font-weight: 600;
    }

    .side-links .highlight-nav-a {
        color: var(--amber);
        font-weight: 600;
    }

    .side-foot {
        margin: 0;
        padding: 8px;
        font-size: 10.5px;
        color: var(--text-mid);
        opacity: 0.75;
    }

    .bottom-nav {
        display: none;
    }

    .mobile-only-brand {
        display: none;
    }

    /* Brand already in side nav — drop the duplicate top header. */
    .app-header {
        display: none;
    }

    .pwa-banner {
        padding: 6px clamp(16px, 2.5vw, 28px);
        gap: 8px;
    }

    .pwa-banner-text p {
        display: none;
    }

    .pwa-banner-text strong {
        font-size: 13px;
    }

    .pane {
        padding: 12px clamp(16px, 2.5vw, 28px) 20px;
        max-width: none;
        width: 100%;
    }

    .pane-header {
        margin-bottom: 10px;
        align-items: center;
    }

    .pane-header h2 {
        font-size: clamp(18px, 1.35vw, 22px);
        margin: 0;
    }

    .hint-strip {
        margin-top: 12px;
        padding: 8px 12px;
        font-size: 12px;
    }

    .company-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .company-price {
        font-size: clamp(20px, 1.7vw, 26px);
    }

    .city-picker select {
        max-width: 220px;
    }

    .content {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 20px;
    }

    .calc-grid {
        grid-template-columns: 1.1fr 0.9fr;
        gap: 20px;
    }

    .side-nav {
        width: clamp(180px, 15vw, 220px);
    }

    .side-foot {
        display: none;
    }
}

@media (min-width: 1280px) {
    .company-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

/* ===== Premium mobile polish (phones → large phones / small tablets) ===== */
@media (max-width: 899px) {
    .app-header h1 {
        font-size: clamp(13px, 3.8vw, 15px);
    }

    .app-header.compact {
        padding-bottom: 6px;
    }

    .app-header.compact h1 {
        font-size: 14px;
        margin: 0;
    }

    .app-header.compact .brand-kicker {
        display: none;
    }

    .app-header p {
        font-size: 11px;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .app-header .brand-kicker {
        margin-bottom: 2px;
    }

    .pane {
        padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
    }

    .fuel-chips .chip {
        min-height: 44px;
        padding: 10px 14px;
    }

    .home-pane-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .home-header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .home-header-actions :deep(.search-select) {
        width: 100%;
        min-width: 0;
    }

    .home-signin-compact,
    .google-btn-home-compact {
        width: 100%;
        max-width: 100%;
    }

    .company-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .home-primary-grid,
    .home-specialty-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .company-top {
        font-size: 11.5px;
        gap: 6px;
        margin-bottom: 8px;
        align-items: flex-start;
    }

    .company-change {
        gap: 4px;
        flex-wrap: wrap;
    }

    .company-change .delta-badge,
    .company-change .delta-status,
    .company-change .delta-15d {
        font-size: 11px;
    }

    .history-cards,
    .history-stats {
        grid-template-columns: 1fr;
    }

    .history-toolbar-row {
        flex-direction: column;
        align-items: stretch;
    }

    .history-fuel-toggle {
        display: flex;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        flex-wrap: nowrap;
        gap: 6px;
        padding-bottom: 2px;
        grid-template-columns: none;
    }

    .history-fuel-toggle button {
        flex: 0 0 auto;
        width: auto;
        min-width: 4.75rem;
        min-height: 44px;
        padding: 10px 14px;
        white-space: nowrap;
        overflow: visible;
        text-overflow: unset;
    }

    .range-toggle {
        width: 100%;
        flex-wrap: wrap;
    }

    .range-toggle button {
        flex: 1 1 auto;
        min-height: 44px;
        padding: 10px 12px;
    }

    .filter-row {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .filter-row :deep(.search-select) {
        flex: 1 1 auto;
        width: 100%;
        min-width: 0;
    }

    .setting-row,
    .auth-row {
        flex-wrap: wrap;
        gap: 10px;
    }

    .action-btn,
    .chip {
        min-height: 44px;
        padding: 10px 14px;
    }

    .company-top span:not(.company-mark) {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: normal;
        line-height: 1.25;
        min-width: 0;
    }

    .theme-modal-backdrop {
        align-items: flex-end;
        justify-content: stretch;
        padding: 0;
        background: rgba(8, 12, 18, 0.55);
    }

    .theme-modal-backdrop[data-theme='light'] {
        background: rgba(15, 23, 32, 0.4);
    }

    .theme-modal {
        width: 100%;
        max-width: 100%;
        max-height: min(88dvh, 88svh);
        border-radius: 20px 20px 0 0;
        padding: 16px 16px calc(18px + env(safe-area-inset-bottom, 0px));
        animation: theme-sheet-up 0.22s ease-out;
    }

    .theme-modal-close {
        width: 44px;
        height: 44px;
        border-radius: 12px;
    }

    .share-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .share-option {
        min-height: 88px;
        padding: 12px 6px;
    }

    .pwa-banner {
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 12px;
    }

    .pwa-banner-actions {
        width: 100%;
        justify-content: stretch;
    }

    .pwa-banner-actions .action-btn,
    .pwa-banner-actions .primary-btn {
        flex: 1;
    }

    .station-card,
    .vehicle-card {
        padding: 12px 14px;
    }

    .vehicle-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .vehicle-actions .action-btn {
        flex: 1 1 auto;
        min-width: 120px;
    }

    .chart-tooltip {
        white-space: normal;
        max-width: min(220px, 70vw);
    }

    .legend {
        gap: 8px 12px;
    }

    .legend-layout-tag {
        margin-inline-start: 0;
    }
}

@media (max-width: 379px) {
    .company-grid,
    .home-primary-grid,
    .home-specialty-grid {
        grid-template-columns: 1fr;
    }

    .bottom-nav button {
        min-height: 52px;
        padding: 6px 1px;
    }

    .bottom-nav button small {
        font-size: 10px;
    }

    .share-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .date-card-value {
        font-size: 18px;
    }
}

@media (min-width: 600px) and (max-width: 899px) {
    .app-shell {
        max-width: min(640px, 100%);
    }

    .history-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .company-grid,
    .home-primary-grid,
    .home-specialty-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
}

@media (min-width: 768px) and (max-width: 899px) {
    .company-grid,
    .home-primary-grid,
    .home-specialty-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .history-cards,
    .history-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-height: 420px) and (orientation: landscape) {
    .app-header p,
    .app-header .brand-kicker {
        display: none;
    }

    .app-header {
        padding-top: max(8px, env(safe-area-inset-top, 0px));
        padding-bottom: 8px;
    }

    .app-header h1 {
        font-size: 13px;
    }

    .bottom-nav {
        padding-top: 4px;
        padding-bottom: max(4px, env(safe-area-inset-bottom, 0px));
    }

    .bottom-nav button {
        min-height: 44px;
        gap: 1px;
    }

    .theme-modal {
        max-height: 92dvh;
    }
}

@keyframes theme-sheet-up {
    from {
        transform: translateY(24px);
        opacity: 0.7;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .app-frame,
    .app-shell,
    .theme-modal,
    .bottom-nav button,
    .primary-btn,
    .action-btn,
    .share-option,
    .company-card {
        animation: none !important;
        transition: none !important;
    }

    .bottom-nav button:active,
    .primary-btn:active,
    .share-option:active {
        transform: none;
    }
}

@media (min-width: 900px) {
    .home-primary-grid,
    .home-specialty-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }
}

@media (min-width: 1600px) {
    .side-nav:not(.collapsed) {
        width: 280px;
    }

    .pane {
        padding-inline: 48px;
    }
}

.compulsory-auth-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(11, 15, 20, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.compulsory-auth-modal {
    background: var(--bg-panel);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 32px;
    text-align: center;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.compulsory-auth-modal h2 {
    margin: 0;
    font-size: 24px;
    color: var(--text-hi);
}

.compulsory-auth-modal p {
    margin: 0;
    font-size: 14px;
    color: var(--text-mid);
    line-height: 1.5;
}

#compulsory-google-btn {
    margin-top: 8px;
    min-height: 40px;
}
</style>

