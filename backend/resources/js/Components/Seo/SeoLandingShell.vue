<script setup lang="ts">
import NewsTicker from '@/Components/NewsTicker.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import { ref, onMounted } from 'vue';
import { trackEvent } from '@/composables/useAnalytics';

const sideNavCollapsed = ref(false);
const openSection = ref('');

onMounted(() => {
    const path = window.location.pathname;
    if (path.includes('guides')) openSection.value = 'news';
    else if (path.includes('price')) openSection.value = 'prices';
    else if (path.includes('calculator')) openSection.value = 'calculators';
    else if (path.includes('faq') || path.includes('about') || path.includes('contact')) openSection.value = 'help';
});

function msg(key: string) { return key; } // Dummy msg fallback since SEO pages don't load full i18n


defineProps<{
    appName: string;
    title: string;
    lead?: string;
}>();
</script>

<template>
    <div class="seo-root">
    <NewsTicker />
    <div class="seo-app-frame">
        <AppSidebar
            :side-nav-collapsed="sideNavCollapsed"
            :open-section="openSection"
            :msg="msg"
            @toggle-side-nav="() => sideNavCollapsed = !sideNavCollapsed"
            @toggle-nav-section="(s: string) => openSection = openSection === s ? '' : s"
        />
        <div class="seo-app-main">
            <div class="seo-page">
                <header class="seo-top">
            <h1>{{ title }}</h1>
            <p v-if="lead" class="lead">{{ lead }}</p>
        </header>
        <main>
            <slot />
        </main>
        <footer class="seo-foot">
            <a href="/">Open live app</a>
            ·
            <a href="/guides">Guides</a>
            ·
            <a href="/faq">FAQ</a>
            ·
            <a href="/about">About</a>
            ·
            <a href="/contact" @click="trackEvent('contact_click', { method: 'page_link' })">Contact</a>
            ·
            <a href="/privacy">Privacy</a>
            ·
            <a href="/terms">Terms</a>
            ·
            <a href="/disclaimer">Disclaimer</a>
            ·
            <a href="/editorial-policy">Editorial</a>
        </footer>
            </div>
        </div>
    </div>
    </div>
</template>

<style scoped>
.seo-root {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    width: 100%;
    background: var(--bg-deep);
}

.seo-root :deep(.news-ticker-bar) {
    flex-shrink: 0;
    margin-bottom: 8px;
}

.seo-app-frame {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    background: var(--bg-deep);
}

@media (min-width: 900px) {
    .seo-root {
        height: 100vh;
        max-height: 100vh;
        overflow: hidden;
    }

    .seo-app-frame {
        flex-direction: row;
        height: auto;
        overflow: hidden;
    }
}

.seo-app-main {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background:
        radial-gradient(1200px 500px at 10% -10%, rgba(242, 166, 63, 0.12), transparent 55%),
        linear-gradient(180deg, var(--bg-panel) 0%, var(--bg-deep) 40%, var(--bg-deep) 100%);
}

.seo-page {
    color: var(--text-hi);
    padding: 24px 32px 64px;
    font-family: 'IBM Plex Sans', 'Segoe UI', sans-serif;
    max-width: 1360px;
    margin: 0 auto;
    box-sizing: border-box;
}
.seo-top a {
    color: #f2a63f;
    text-decoration: none;
    font-size: 13px;
}
h1 {
    margin: 10px 0 8px;
    font-size: clamp(1.55rem, 3vw, 2rem);
    line-height: 1.2;
    font-family: 'IBM Plex Serif', Georgia, serif;
    font-weight: 650;
}
.lead {
    margin: 0;
    color: var(--text-mid);
    line-height: 1.55;
    font-size: 15px;
}
.seo-foot {
    margin-top: 36px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    color: var(--text-mid);
    font-size: 13px;
}
.seo-foot a {
    color: #f2a63f;
    text-decoration: none;
}
</style>
