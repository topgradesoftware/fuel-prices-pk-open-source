<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

type Card = {
    slug: string;
    title: string;
    excerpt: string;
    category: string;
    category_label: string;
    tags: string[];
    reading_minutes: number;
    published_at?: string | null;
    is_featured?: boolean;
    is_trending?: boolean;
};

type Paginated = {
    data: Card[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const props = defineProps<{
    seoMeta: Record<string, unknown>;
    articles: Paginated;
    categories: Record<string, string>;
    filters: { category: string | null; q: string; tag: string };
    featured: Card[];
    trending: Card[];
    popular: Card[];
    app_name: string;
}>();

const q = ref(props.filters.q || '');

watch(
    () => props.filters.q,
    (v) => {
        q.value = v || '';
    },
);

function applySearch(): void {
    router.get(
        '/guides',
        {
            q: q.value || undefined,
            category: props.filters.category || undefined,
            tag: props.filters.tag || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function setCategory(cat: string | null): void {
    router.get(
        '/guides',
        { category: cat || undefined, q: q.value || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <SeoHead
        :title="(seoMeta.title as string) || 'Guides'"
        :description="(seoMeta.description as string) || ''"
        :keywords="(seoMeta.keywords as string) || ''"
        path="guides"
        :robots="(seoMeta.robots as string) || 'index, follow'"
        :json-ld="(seoMeta.json_ld as Array<Record<string, unknown>>) || []"
    />
    <SeoLandingShell
        :app-name="app_name"
        :title="(seoMeta.h1 as string) || 'Fuel guides & news'"
        :lead="(seoMeta.description as string) || ''"
    >
        <form class="search-row" @submit.prevent="applySearch">
            <input v-model="q" type="search" placeholder="Search guides…" aria-label="Search guides" />
            <button type="submit">Search</button>
            <a class="rss" href="/guides/rss.xml">RSS</a>
        </form>

        <div class="cat-row">
            <button type="button" class="chip" :class="{ active: !filters.category }" @click="setCategory(null)">
                All
            </button>
            <button
                v-for="(label, key) in categories"
                :key="key"
                type="button"
                class="chip"
                :class="{ active: filters.category === key }"
                @click="setCategory(key)"
            >
                {{ label }}
            </button>
        </div>

        <div class="layout">
            <div class="main">
                <section v-if="featured.length && !filters.category && !filters.q" class="block">
                    <h2>Featured</h2>
                    <div class="cards">
                        <a v-for="a in featured" :key="a.slug" class="card" :href="`/guides/${a.slug}`">
                            <span class="badge">{{ a.category_label }}</span>
                            <strong>{{ a.title }}</strong>
                            <p>{{ a.excerpt }}</p>
                            <small>{{ a.reading_minutes }} min read</small>
                        </a>
                    </div>
                </section>

                <section class="block">
                    <h2>{{ filters.q ? 'Results' : 'Latest' }}</h2>
                    <div class="cards">
                        <a v-for="a in articles.data" :key="a.slug" class="card" :href="`/guides/${a.slug}`">
                            <span class="badge">{{ a.category_label }}</span>
                            <strong>{{ a.title }}</strong>
                            <p>{{ a.excerpt }}</p>
                            <small>{{ a.reading_minutes }} min read</small>
                        </a>
                    </div>
                    <p v-if="!articles.data.length" class="muted">No articles match this filter.</p>
                    <nav v-if="articles.links?.length" class="pager" aria-label="Pagination">
                        <a
                            v-for="(l, i) in articles.links"
                            :key="i"
                            :href="l.url || undefined"
                            :class="{ active: l.active, disabled: !l.url }"
                            v-html="l.label"
                        />
                    </nav>
                </section>
            </div>

            <aside class="side">
                <section class="side-block">
                    <h3>Trending</h3>
                    <ul>
                        <li v-for="a in trending" :key="a.slug">
                            <a :href="`/guides/${a.slug}`">{{ a.title }}</a>
                        </li>
                    </ul>
                </section>
                <section class="side-block">
                    <h3>Popular</h3>
                    <ul>
                        <li v-for="a in popular" :key="a.slug">
                            <a :href="`/guides/${a.slug}`">{{ a.title }}</a>
                        </li>
                    </ul>
                </section>
                <section class="side-block cta">
                    <h3>Price alerts</h3>
                    <p>Get notified when petrol or diesel changes.</p>
                    <a href="/?tab=more">Enable in the app</a>
                </section>
            </aside>
        </div>
    </SeoLandingShell>
</template>

<style scoped>
.search-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}
.search-row input {
    flex: 1;
    min-width: 180px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(0, 0, 0, 0.25);
    color: #e8eef5;
}
.search-row button,
.chip {
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: #e8eef5;
    border-radius: 999px;
    padding: 8px 14px;
    cursor: pointer;
    font: inherit;
}
.search-row button {
    background: #f2a63f;
    color: #1a1204;
    font-weight: 700;
    border: none;
    border-radius: 10px;
}
.rss {
    align-self: center;
    color: #f2a63f;
    font-size: 13px;
}
.cat-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
}
.chip.active {
    border-color: #f2a63f;
    color: #f2a63f;
}
.layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px;
    gap: 22px;
}
@media (max-width: 800px) {
    .layout {
        grid-template-columns: 1fr;
    }
}
.block h2,
.side-block h3 {
    margin: 0 0 12px;
    font-size: 16px;
}
.cards {
    display: grid;
    gap: 12px;
}
.card {
    display: block;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
    text-decoration: none;
    color: inherit;
}
.card strong {
    display: block;
    margin: 6px 0;
    color: #f5f1e8;
}
.card p {
    margin: 0 0 8px;
    color: #c5ced8;
    font-size: 14px;
    line-height: 1.5;
}
.badge {
    font-size: 11px;
    color: #f2a63f;
}
.muted {
    color: #8b95a1;
}
.pager {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 16px;
}
.pager a {
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #c5ced8;
    text-decoration: none;
    font-size: 13px;
}
.pager a.active {
    border-color: #f2a63f;
    color: #f2a63f;
}
.pager a.disabled {
    opacity: 0.4;
    pointer-events: none;
}
.side-block {
    margin-bottom: 18px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.side-block ul {
    margin: 0;
    padding-left: 16px;
    color: #c5ced8;
    line-height: 1.6;
}
.side-block a {
    color: #f2a63f;
    text-decoration: none;
}
.cta p {
    margin: 0 0 8px;
    color: #c5ced8;
    font-size: 13.5px;
}
</style>
