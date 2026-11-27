<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);

function isActive(path: string) {
    return currentUrl.value.startsWith(path);
}
</script>

<template>
    <div class="timeline-layout">
        <header class="timeline-header">
            <h1>Economic Timeline & Historical Data</h1>
            <p class="subtitle">Pakistan's macroeconomic history verified against official sources.</p>
            
            <nav class="timeline-nav">
                <Link 
                    href="/fuel-history" 
                    class="nav-link" 
                    :class="{ active: isActive('/fuel-history') }"
                >
                    Fuel History
                </Link>
                <Link 
                    href="/economic-indicators" 
                    class="nav-link" 
                    :class="{ active: isActive('/economic-indicators') }"
                >
                    Economic Indicators
                </Link>
                <Link 
                    href="/government-timeline" 
                    class="nav-link" 
                    :class="{ active: isActive('/government-timeline') }"
                >
                    Government Timeline
                </Link>
                <Link 
                    href="/global-markets" 
                    class="nav-link" 
                    :class="{ active: isActive('/global-markets') }"
                >
                    Global Markets
                </Link>
            </nav>
            
            <div class="correlation-disclaimer">
                <strong>Important:</strong> Data is presented exactly as published by official sources (OGRA, SBP, PBS). 
                <span class="badge verified">Verified</span> means cross-checked with primary notifications. 
                Any correlations shown are purely mathematical and do NOT imply causation.
            </div>
        </header>

        <main class="timeline-content">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.timeline-layout {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

.timeline-header {
    margin-bottom: 32px;
    text-align: center;
}

.timeline-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-color, #111827);
}

.subtitle {
    color: var(--text-muted, #6b7280);
    margin-bottom: 24px;
}

.timeline-nav {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    padding-bottom: 16px;
}

.nav-link {
    padding: 8px 16px;
    text-decoration: none;
    color: var(--text-muted, #6b7280);
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background-color: var(--hover-bg, #f3f4f6);
    color: var(--text-color, #111827);
}

.nav-link.active {
    background-color: var(--primary-color, #2563eb);
    color: white;
}

.timeline-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.correlation-disclaimer {
    margin-top: 16px;
    padding: 12px;
    background-color: #fffbeb;
    border-left: 4px solid #f59e0b;
    color: #92400e;
    font-size: 0.875rem;
    text-align: left;
    border-radius: 4px;
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.badge.verified {
    background-color: #d1fae5;
    color: #065f46;
}

.badge.estimated {
    background-color: #fef3c7;
    color: #92400e;
}

.badge.pending {
    background-color: #e0e7ff;
    color: #3730a3;
}
</style>
