<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

type AiLimitAlert = {
    show: boolean;
    title: string;
    message: string;
    reason: string;
    level: string;
};

const page = usePage();
const dismissed = ref(false);

const alert = computed(() => {
    const value = page.props.ai_limit_alert as AiLimitAlert | null | undefined;
    if (!value?.show) return null;
    return value;
});

function storageKey(reason: string): string {
    return `ai-limit-popup:${reason}:${new Date().toISOString().slice(0, 10)}`;
}

watch(
    alert,
    (value) => {
        if (!value) {
            dismissed.value = false;
            return;
        }
        try {
            dismissed.value = sessionStorage.getItem(storageKey(value.reason)) === '1';
        } catch {
            dismissed.value = false;
        }
    },
    { immediate: true },
);

const visible = computed(() => Boolean(alert.value) && !dismissed.value);

function dismiss() {
    if (!alert.value) return;
    try {
        sessionStorage.setItem(storageKey(alert.value.reason), '1');
    } catch {
        // ignore storage errors
    }
    dismissed.value = true;
}
</script>

<template>
    <div v-if="visible && alert" class="backdrop" role="alertdialog" aria-modal="true" aria-labelledby="ai-limit-title" @click.self="dismiss">
        <div class="modal" :data-level="alert.level">
            <p class="eyebrow">AI usage alert</p>
            <h2 id="ai-limit-title">{{ alert.title }}</h2>
            <p class="body">{{ alert.message }}</p>
            <ul class="hints">
                <li>Open <a href="/admin/automation">Automation</a> for budget / daily usage</li>
                <li>Google AI Studio → Usage / Rate Limit for free-tier quota</li>
            </ul>
            <div class="actions">
                <a class="link" href="/admin/automation">View AI budget</a>
                <button type="button" class="primary" @click="dismiss">Got it</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgb(8 12 18 / 0.72);
}
.modal {
    width: min(440px, 100%);
    background: #1b232d;
    border: 1px solid #c45c5c;
    border-radius: 14px;
    padding: 1.25rem 1.35rem;
    box-shadow: 0 18px 48px rgb(0 0 0 / 0.45);
}
.eyebrow {
    margin: 0;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #f0a0a0;
    font-weight: 700;
}
h2 {
    margin: 0.35rem 0 0.65rem;
    font-size: 1.2rem;
    color: #ffe8e8;
}
.body {
    margin: 0;
    color: #d5dde6;
    font-size: 0.92rem;
    line-height: 1.45;
}
.hints {
    margin: 0.9rem 0 0;
    padding-left: 1.1rem;
    color: #9aa8b5;
    font-size: 0.82rem;
    display: grid;
    gap: 0.35rem;
}
.hints a {
    color: #f2a63f;
}
.actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.15rem;
}
.link {
    color: #f2a63f;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
}
.primary {
    border: 0;
    border-radius: 8px;
    background: #f2a63f;
    color: #1a1208;
    font-weight: 700;
    padding: 0.5rem 0.95rem;
    cursor: pointer;
}
</style>
