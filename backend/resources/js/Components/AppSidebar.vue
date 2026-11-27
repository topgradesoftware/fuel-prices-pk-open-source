<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    sideNavCollapsed: boolean;
    openSection: string;
    tab?: string;
    msg: (key: string) => string;
}>();

const emit = defineEmits(['toggleSideNav', 'toggleNavSection', 'update:tab']);

function toggleSideNav() { emit('toggleSideNav'); }
function toggleNavSection(section: string) { emit('toggleNavSection', section); }
function setTab(t: string) { 
    if (page.component === 'Public/App') {
        emit('update:tab', t); 
    } else {
        router.visit(t === 'home' ? '/' : `/?tab=${t}`);
    }
}

const page = usePage();
const currentUrl = computed(() => page.url);

function isActive(url: string) {
    return currentUrl.value === url || currentUrl.value.startsWith(url + '?');
}
</script>
<template>
            <aside
                class="side-nav"
                :class="{ collapsed: sideNavCollapsed }"
                :aria-label="msg('navDesktop')"
            >
                <div class="side-brand">
                    <div class="brand-icon">⛽</div>
                    <div class="side-brand-text">
                        <strong>{{ msg('appName') }}</strong>
                        <small>{{ msg('tagline') }}</small>
                    </div>
                    <button
                        type="button"
                        class="side-collapse-btn"
                        :aria-label="sideNavCollapsed ? msg('expandSidebar') : msg('collapseSidebar')"
                        :title="sideNavCollapsed ? msg('expandSidebar') : msg('collapseSidebar')"
                        @click="toggleSideNav"
                    >
                        {{ sideNavCollapsed ? '»' : '«' }}
                    </button>
                </div>
                <nav class="side-links">
                    <!-- Section: Main App -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'main' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'main' }" @click="toggleNavSection('main')">
                            <span class="section-title">MAIN APP</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'main' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'main'" class="nav-section-items">
                            <button type="button" :class="{ active: tab === 'home' }" @click="setTab('home')">
                                <span>⛽</span> <span class="side-link-label">{{ msg('home') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'compare' }" @click="setTab('compare')">
                                <span>🏢</span> <span class="side-link-label">{{ msg('compare') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'stations' }" @click="setTab('stations')">
                                <span>📍</span> <span class="side-link-label">{{ msg('stations') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'garage' }" @click="setTab('garage')">
                                <span>🚗</span> <span class="side-link-label">{{ msg('garage') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'calc' }" @click="setTab('calc')">
                                <span>🧮</span> <span class="side-link-label">{{ msg('calc') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'history' }" @click="setTab('history')">
                                <span>📈</span> <span class="side-link-label">{{ msg('history') }}</span>
                            </button>
                            <button type="button" :class="{ active: tab === 'more' }" @click="setTab('more')">
                                <span>⋯</span> <span class="side-link-label">{{ msg('more') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Section: Economic Timeline -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'timeline' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'timeline' }" @click="toggleNavSection('timeline')">
                            <span class="section-title">ECONOMIC TIMELINE</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'timeline' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'timeline'" class="nav-section-items">
                            <Link href="/fuel-history" class="side-nav-a" :class="{ 'highlight-nav-a': isActive('/fuel-history') || isActive('/economic-indicators') || isActive('/government-timeline') || isActive('/global-markets') }">
                                <span>📈</span> <span class="side-link-label">Historical Data</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Section: News & Guides -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'news' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'news' }" @click="toggleNavSection('news')">
                            <span class="section-title">NEWS & GUIDES</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'news' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'news'" class="nav-section-items">
                            <Link href="/guides?category=fuel-news" class="side-nav-a highlight-nav-a">
                                <span>📰</span> <span class="side-link-label">Fuel News</span>
                            </Link>
                            <Link href="/guides" class="side-nav-a">
                                <span>📚</span> <span class="side-link-label">Guides Hub</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Section: Prices & Cities -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'prices' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'prices' }" @click="toggleNavSection('prices')">
                            <span class="section-title">PRICES & CITIES</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'prices' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'prices'" class="nav-section-items">
                            <Link href="/petrol-price-today" class="side-nav-a">
                                <span>⚡</span> <span class="side-link-label">Petrol Today</span>
                            </Link>
                            <Link href="/diesel-price-today" class="side-nav-a">
                                <span>🚛</span> <span class="side-link-label">Diesel Today</span>
                            </Link>
                            <Link href="/lpg-price-today" class="side-nav-a">
                                <span>🔥</span> <span class="side-link-label">LPG Today</span>
                            </Link>
                            <Link href="/prices" class="side-nav-a">
                                <span>🏙️</span> <span class="side-link-label">All Cities</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Section: Calculators -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'calculators' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'calculators' }" @click="toggleNavSection('calculators')">
                            <span class="section-title">CALCULATORS</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'calculators' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'calculators'" class="nav-section-items">
                            <Link href="/fuel-cost-calculator" class="side-nav-a">
                                <span>⛽</span> <span class="side-link-label">Fuel Cost</span>
                            </Link>
                            <Link href="/trip-cost-calculator" class="side-nav-a">
                                <span>🚗</span> <span class="side-link-label">Trip Cost</span>
                            </Link>
                            <Link href="/mileage-calculator" class="side-nav-a">
                                <span>📊</span> <span class="side-link-label">Mileage</span>
                            </Link>
                            <Link href="/monthly-fuel-budget-calculator" class="side-nav-a">
                                <span>📅</span> <span class="side-link-label">Fuel Budget</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Section: Help & Info -->
                    <div class="nav-section" :class="{ 'is-expanded': openSection === 'help' }">
                        <button type="button" class="nav-section-bar" :class="{ 'is-active': openSection === 'help' }" @click="toggleNavSection('help')">
                            <span class="section-title">HELP & INFO</span>
                            <svg class="chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="openSection === 'help' ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'" />
                            </svg>
                        </button>
                        <div v-show="openSection === 'help'" class="nav-section-items">
                            <Link href="/faq" class="side-nav-a">
                                <span>❓</span> <span class="side-link-label">FAQ</span>
                            </Link>
                            <Link href="/about" class="side-nav-a">
                                <span>ℹ️</span> <span class="side-link-label">About Us</span>
                            </Link>
                            <Link href="/contact" class="side-nav-a" @click="trackEvent('contact_click', { method: 'page_link' })">
                                <span>✉️</span> <span class="side-link-label">Contact Us</span>
                            </Link>
                        </div>
                    </div>
                </nav>
                <p class="side-foot" style="margin-bottom: 4px;">
                    Software by <a href="https://topgradesoftware.com" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: underline;">Top Grade Software</a>
                </p>
                <p class="side-foot">{{ msg('devicesHint') }}</p>
            </aside>
</template>
<style scoped>
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
        border-radius: 10px;
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
    @media (max-width: 899px) {
        .side-nav {
            display: none !important;
        }
    }
</style>