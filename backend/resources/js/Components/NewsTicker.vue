<script setup lang="ts">
import { computed } from 'vue';

type TickerItem = {
    id: number;
    title: string;
    slug: string;
    url?: string;
    published_at?: string | null;
};

const FALLBACK_ITEMS: TickerItem[] = [
    {
        id: 1,
        title: 'OGRA Fuel Prices Today: Petrol, Diesel & LPG Latest Rates Updated',
        slug: 'ogra-fuel-prices-today',
        url: '/guides?category=fuel-news',
    },
    {
        id: 2,
        title: 'Pakistan Fuel Price Alert: Track PSO, Shell & Total Parco Station Rates',
        slug: 'pakistan-fuel-price-alert',
        url: '/guides?category=fuel-news',
    },
    {
        id: 3,
        title: 'Monthly Fuel Budget Calculator: Estimate Your Driving Expenses',
        slug: 'fuel-budget-calculator-guide',
        url: '/monthly-fuel-budget-calculator',
    },
];

const props = withDefaults(
    defineProps<{
        items?: TickerItem[];
    }>(),
    {
        items: () => [],
    },
);

const displayItems = computed(() => {
    // Parent often passes [] when the DB has no published guides — still show
    // the ticker with fallback headlines so the bar stays visible on the home app.
    const source = props.items.length > 0 ? props.items : FALLBACK_ITEMS;

    return [...source, ...source];
});
</script>

<template>
    <div class="news-ticker-bar" role="region" aria-label="Fuel news">
        <div class="ticker-wrapper">
            <div class="ticker-content">
                <a
                    v-for="(item, index) in displayItems"
                    :key="`${item.id}-${index}`"
                    :href="item.url || `/guides/${item.slug}`"
                    class="ticker-item"
                >
                    <span class="ticker-bullet">•</span>
                    <span class="ticker-title">{{ item.title }}</span>
                    <span v-if="item.published_at" class="ticker-date">({{ item.published_at }})</span>
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped>
.news-ticker-bar {
    display: flex;
    align-items: center;
    background: linear-gradient(90deg, #161e29 0%, #10161f 100%);
    border-bottom: 1px solid rgba(242, 166, 63, 0.25);
    height: 36px;
    overflow: hidden;
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
    font-size: 13px;
    position: relative;
    z-index: 2;
    width: 100%;
    flex-shrink: 0;
    scrollbar-width: none;
}

.news-ticker-bar::-webkit-scrollbar {
    display: none;
}

.ticker-wrapper {
    flex: 1;
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    min-width: 0;
    scrollbar-width: none;
}

.ticker-wrapper::-webkit-scrollbar {
    display: none;
}

.ticker-content {
    display: flex;
    align-items: center;
    white-space: nowrap;
    animation: ticker-scroll 35s linear infinite;
    will-change: transform;
}

.ticker-wrapper:hover .ticker-content {
    animation-play-state: paused;
}

.ticker-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #e8eef5;
    text-decoration: none;
    padding: 0 20px;
    transition: color 0.2s ease;
}

.ticker-item:hover {
    color: #f2a63f;
}

.ticker-bullet {
    color: #f2a63f;
    font-size: 14px;
}

.ticker-title {
    font-weight: 500;
}

.ticker-date {
    color: #94a3b8;
    font-size: 11px;
}

@keyframes ticker-scroll {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}
</style>
