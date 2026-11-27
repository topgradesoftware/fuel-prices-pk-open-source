<script setup lang="ts">
import NewsTicker from '@/Components/NewsTicker.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import { ref, onMounted } from 'vue';

const sideNavCollapsed = ref(false);
const openSection = ref('');
const currentPath = ref('');

onMounted(() => {
    openSection.value = 'help'; // Legal pages are under Help & Info
    currentPath.value = window.location.pathname;
});

function msg(key: string) { return key; } // Dummy msg fallback

defineProps<{
    title: string;
    updated: string;
    appName: string;
}>();
</script>

<template>
    <div class="legal-root">
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
            <a href="/" class="back-link">← Back to app</a>
            <nav class="cat-row" aria-label="Trust pages">
                <a href="/about" class="chip" :class="{ active: currentPath === '/about' }">About</a>
                <a href="/contact" class="chip" :class="{ active: currentPath === '/contact' }">Contact</a>
                <a href="/privacy" class="chip" :class="{ active: currentPath === '/privacy' }">Privacy</a>
                <a href="/terms" class="chip" :class="{ active: currentPath === '/terms' }">Terms</a>
                <a href="/disclaimer" class="chip" :class="{ active: currentPath === '/disclaimer' }">Disclaimer</a>
                <a href="/editorial-policy" class="chip" :class="{ active: currentPath === '/editorial-policy' }">Editorial</a>
            </nav>
            <h1>{{ title }}</h1>
            <p class="muted">{{ appName }} · Last updated {{ updated }}</p>
        </header>
        <main>
            <slot />
        </main>
        <footer class="legal-foot">
            <a href="/guides">Guides</a>
            ·
            <a href="/faq">FAQ</a>
            ·
            <a href="/petrol-price-today">Petrol today</a>
            ·
            <a href="/privacy">Privacy</a>
            ·
            <a href="/terms">Terms</a>
        </footer>
            </div>
        </div>
    </div>
    </div>
</template>

<style scoped>
.legal-root {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    width: 100%;
    background: var(--bg-deep);
}

.legal-root :deep(.news-ticker-bar) {
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
    .legal-root {
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
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
    max-width: 720px;
    box-sizing: border-box;
}

.back-link {
    color: #f2a63f;
    text-decoration: none;
    font-size: 13px;
    display: inline-block;
    margin-bottom: 8px;
}

.cat-row {
    display: flex;
    gap: 8px;
    margin: 12px 0 24px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
}
.cat-row::-webkit-scrollbar {
    display: none;
}
.chip {
    background: var(--bg-panel);
    border: 1px solid var(--border);
    color: var(--text-hi);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
}
.chip.active {
    background: rgba(242, 166, 63, 0.15);
    border-color: #f2a63f;
    color: #f2a63f;
}
.chip:hover:not(.active) {
    background: var(--border);
}
h1 {
    margin: 12px 0 4px;
    font-size: clamp(22px, 6vw, 28px);
    font-family: 'IBM Plex Serif', Georgia, serif;
}
.muted {
    color: var(--text-mid);
    font-size: 13px;
    margin: 0 0 8px;
}
.legal-foot {
    margin-top: 36px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    color: var(--text-mid);
    font-size: 13px;
}
.legal-foot a {
    color: #f2a63f;
    text-decoration: none;
}
</style>
