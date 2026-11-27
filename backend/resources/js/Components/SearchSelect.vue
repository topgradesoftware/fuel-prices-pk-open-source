<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export type SearchOption = {
    id: string;
    label: string;
    sub?: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: SearchOption[];
        placeholder?: string;
        emptyLabel?: string;
        icon?: string;
        allowEmpty?: boolean;
        emptyOptionLabel?: string;
    }>(),
    {
        placeholder: 'Search…',
        emptyLabel: 'No matches',
        icon: '🔎',
        allowEmpty: false,
        emptyOptionLabel: 'All',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    search: [];
}>();

const open = ref(false);
const query = ref('');
const root = ref<HTMLElement | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) ?? null);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(
        (o) =>
            o.label.toLowerCase().includes(q) ||
            (o.sub && o.sub.toLowerCase().includes(q)) ||
            o.id.toLowerCase().includes(q),
    );
});

watch(open, async (isOpen) => {
    if (isOpen) {
        emit('search');
        query.value = '';
        await nextTick();
        inputEl.value?.focus();
    }
});

function pick(id: string): void {
    emit('update:modelValue', id);
    open.value = false;
    query.value = '';
}

function onDocClick(e: MouseEvent): void {
    if (!root.value?.contains(e.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div ref="root" class="search-select" :class="{ open }">
        <button type="button" class="search-select-trigger" @click.stop="open = !open">
            <span class="icon">{{ icon }}</span>
            <span class="value">
                <template v-if="selected">{{ selected.label }}</template>
                <template v-else-if="allowEmpty && !modelValue">{{ emptyOptionLabel }}</template>
                <template v-else>{{ placeholder }}</template>
            </span>
            <span class="chev">▾</span>
        </button>

        <div v-if="open" class="search-select-panel" @click.stop>
            <input
                ref="inputEl"
                v-model="query"
                type="search"
                class="search-select-input"
                :placeholder="placeholder"
                autocomplete="off"
            />
            <ul class="search-select-list">
                <li v-if="allowEmpty">
                    <button
                        type="button"
                        :class="{ active: !modelValue }"
                        @click="pick('')"
                    >
                        {{ emptyOptionLabel }}
                    </button>
                </li>
                <li v-for="opt in filtered" :key="opt.id">
                    <button
                        type="button"
                        :class="{ active: opt.id === modelValue }"
                        @click="pick(opt.id)"
                    >
                        <span>{{ opt.label }}</span>
                        <small v-if="opt.sub">{{ opt.sub }}</small>
                    </button>
                </li>
                <li v-if="!filtered.length" class="empty">{{ emptyLabel }}</li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.search-select {
    position: relative;
    min-width: 0;
    width: 100%;
    max-width: 100%;
}

.search-select-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    min-height: 44px;
    background: var(--bg-panel-alt, #151d26);
    border: 1px solid var(--border, #2a3542);
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--text-hi, #e8eef5);
    font-size: 14px;
    font-family: inherit;
    cursor: pointer;
    text-align: start;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

.search-select-trigger:focus-visible {
    outline: 2px solid var(--amber, #f2a63f);
    outline-offset: 2px;
}

.search-select.open .search-select-trigger,
.search-select-trigger:hover {
    border-color: var(--amber, #f2a63f);
}

.icon {
    opacity: 0.8;
}

.value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chev {
    opacity: 0.55;
    font-size: 11px;
}

.search-select-panel {
    position: absolute;
    z-index: 40;
    top: calc(100% + 4px);
    inset-inline-start: 0;
    inset-inline-end: 0;
    width: 100%;
    min-width: 100%;
    max-width: min(100vw - 24px, 360px);
    background: var(--bg-panel, #101820);
    border: 1px solid var(--border, #2a3542);
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    overflow: hidden;
}

.search-select-input {
    width: 100%;
    box-sizing: border-box;
    border: none;
    border-bottom: 1px solid var(--border, #2a3542);
    background: var(--bg-panel-alt, #151d26);
    color: var(--text-hi, #e8eef5);
    padding: 12px 14px;
    min-height: 44px;
    font-size: 16px;
    font-family: inherit;
    outline: none;
}

.search-select-list {
    list-style: none;
    margin: 0;
    padding: 4px;
    max-height: min(280px, 45vh);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.search-select-list button {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    width: 100%;
    border: none;
    background: transparent;
    color: var(--text-hi, #e8eef5);
    padding: 12px 12px;
    min-height: 44px;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    cursor: pointer;
    text-align: start;
    box-sizing: border-box;
}

.search-select-list button small {
    color: var(--text-mid, #8b95a1);
    font-size: 11px;
}

.search-select-list button:hover,
.search-select-list button.active {
    background: rgba(242, 166, 63, 0.12);
}

.search-select-list .empty {
    padding: 12px;
    color: var(--text-mid, #8b95a1);
    font-size: 12.5px;
}
</style>
