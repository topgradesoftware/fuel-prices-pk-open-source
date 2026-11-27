<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { AccentColors } from '../Theme/OilPriceThemes';
import { applyTheme, getStoredBase, getStoredAccent } from '../Utils/theme';

const open        = ref(false);
const currentBase   = ref<'dark' | 'light'>('dark');
const currentAccent = ref('amber');

const baseModes = [
    { key: 'dark'  as const, name: 'Dark',  icon: '🌙' },
    { key: 'light' as const, name: 'Light', icon: '☀️' },
];

const accentList = Object.entries(AccentColors).map(([id, def]) => ({
    id,
    name: def.name,
    icon: def.icon,
    color: def.dark['--amber'],
}));

function selectBase(base: 'dark' | 'light') {
    currentBase.value = base;
    applyTheme(base, currentAccent.value);
}

function selectAccent(accent: string) {
    currentAccent.value = accent;
    applyTheme(currentBase.value, accent);
}

function toggle() {
    open.value = !open.value;
}

function onDocClick(e: MouseEvent) {
    if (!(e.target as HTMLElement).closest('.theme-switcher-root')) {
        open.value = false;
    }
}

onMounted(() => {
    currentBase.value   = getStoredBase();
    currentAccent.value = getStoredAccent();
    applyTheme(currentBase.value, currentAccent.value);
    window.addEventListener('click', onDocClick);
});

onUnmounted(() => {
    window.removeEventListener('click', onDocClick);
});
</script>

<template>
    <div class="theme-switcher-root">
        <button
            type="button"
            class="theme-trigger-btn"
            :class="{ active: open }"
            aria-label="Change color theme"
            @click.stop="toggle"
        >
            <span class="icon">🎨</span>
            <span class="label">Theme</span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-show="open" class="theme-dropdown-panel" @click.stop>

            <!-- ── Base Mode Toggle ── -->
            <div class="section-label">🖥️ Mode</div>
            <div class="base-toggle">
                <button
                    v-for="mode in baseModes"
                    :key="mode.key"
                    type="button"
                    class="base-btn"
                    :class="{ active: currentBase === mode.key }"
                    @click="selectBase(mode.key)"
                >
                    <span>{{ mode.icon }}</span>
                    <span>{{ mode.name }}</span>
                </button>
            </div>

            <!-- ── Accent Color Grid ── -->
            <div class="section-label" style="margin-top:12px;">🎨 Accent Color</div>
            <div class="accent-grid">
                <button
                    v-for="a in accentList"
                    :key="a.id"
                    type="button"
                    class="accent-card"
                    :class="{ selected: currentAccent === a.id }"
                    :title="a.name"
                    @click="selectAccent(a.id)"
                >
                    <span class="swatch" :style="{ backgroundColor: a.color }" />
                    <span class="accent-name">{{ a.icon }} {{ a.name }}</span>
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.theme-switcher-root {
    position: relative;
    display: inline-block;
    z-index: 120;
}

/* ── Trigger button ── */
.theme-trigger-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 12px;
    background: var(--bg-panel-alt, rgba(255,255,255,0.05));
    border: 1px solid var(--border, rgba(255,255,255,0.12));
    border-radius: 10px;
    color: var(--text-hi, #e8eef5);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.theme-trigger-btn:hover,
.theme-trigger-btn.active {
    background: color-mix(in srgb, var(--amber) 15%, var(--bg-panel));
    border-color: var(--amber);
    color: var(--amber);
}
.theme-trigger-btn .icon { font-size: 14px; }
.theme-trigger-btn .chevron {
    width: 14px;
    height: 14px;
    transition: transform 0.2s ease;
    opacity: 0.7;
}

/* ── Dropdown panel ── */
.theme-dropdown-panel {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    width: 280px;
    max-height: min(65vh, 480px);
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--bg-panel, #161e29);
    border: 1px solid var(--border, rgba(255,255,255,0.15));
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.5);
    padding: 12px;
    z-index: 200;
    backdrop-filter: blur(16px);
}

/* ── Section label ── */
.section-label {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: var(--text-mid, #8b95a1);
    margin-bottom: 7px;
    padding: 0 2px;
}

/* ── Base mode toggle ── */
.base-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}
.base-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px;
    border-radius: 9px;
    border: 1px solid var(--border, rgba(255,255,255,0.1));
    background: var(--bg-panel-alt, rgba(255,255,255,0.04));
    color: var(--text-mid, #8b95a1);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.base-btn:hover {
    border-color: var(--amber);
    color: var(--text-hi, #f5f1e8);
}
.base-btn.active {
    background: color-mix(in srgb, var(--amber) 20%, var(--bg-panel-alt));
    border-color: var(--amber);
    color: var(--amber);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--amber) 25%, transparent);
}

/* ── Accent grid ── */
.accent-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 5px;
}
.accent-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    padding: 7px;
    background: var(--bg-panel-alt, rgba(255,255,255,0.03));
    border: 1px solid var(--border, rgba(255,255,255,0.08));
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    min-width: 0;
}
.accent-card:hover {
    background: color-mix(in srgb, var(--amber) 10%, var(--bg-panel-alt));
    border-color: var(--amber);
}
.accent-card.selected {
    background: color-mix(in srgb, var(--amber) 20%, var(--bg-panel-alt));
    border-color: var(--amber);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--amber) 28%, transparent);
}
.swatch {
    width: 100%;
    height: 5px;
    border-radius: 999px;
}
.accent-name {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-hi, #e8eef5);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}
</style>
