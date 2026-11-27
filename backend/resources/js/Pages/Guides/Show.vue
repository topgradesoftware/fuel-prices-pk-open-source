<script setup lang="ts">
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

type Card = {
    slug: string;
    title: string;
    excerpt: string;
    category_label: string;
    reading_minutes: number;
};

const props = defineProps<{
    seoMeta: Record<string, unknown>;
    article: {
        slug: string;
        title: string;
        excerpt: string;
        category: string;
        category_label: string;
        tags: string[];
        reading_minutes: number;
        published_at?: string | null;
        updated_at?: string | null;
        author_name: string;
        body_html: string;
        faq: Array<{ question: string; answer: string }>;
        toc: Array<{ id: string; text: string }>;
    };
    related: Card[];
    app_name: string;
}>();

function shareNative(): void {
    const url = typeof window !== 'undefined' ? window.location.href : '';
    if (navigator.share) {
        void navigator.share({ title: props.article.title, text: props.article.excerpt, url });
        return;
    }
    void navigator.clipboard?.writeText(url);
}

function fmt(iso?: string | null): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return '';
    }
}
</script>

<template>
    <SeoHead
        :title="(seoMeta.title as string) || article.title"
        :description="(seoMeta.description as string) || article.excerpt"
        :keywords="(seoMeta.keywords as string) || ''"
        :path="`guides/${article.slug}`"
        :robots="(seoMeta.robots as string) || 'index, follow'"
        :json-ld="(seoMeta.json_ld as Array<Record<string, unknown>>) || []"
    />
    <SeoLandingShell :app-name="app_name" :title="article.title" :lead="article.excerpt">
        <div class="full-page-grid">
            <main class="article-main">
                <p class="meta">
                    <a href="/guides">Guides</a>
                    ·
                    <a :href="`/guides?category=${article.category}`">{{ article.category_label }}</a>
                    · {{ article.reading_minutes }} min read · {{ article.author_name }}
                    <template v-if="article.published_at"> · {{ fmt(article.published_at) }}</template>
                    <template v-if="article.updated_at"> · Updated {{ fmt(article.updated_at) }}</template>
                </p>

                <div class="share-row">
                    <button type="button" @click="shareNative">Share</button>
                    <a
                        :href="`https://wa.me/?text=${encodeURIComponent(article.title + ' ' + (typeof window !== 'undefined' ? window.location.href : ''))}`"
                        target="_blank"
                        rel="noopener"
                    >WhatsApp</a>
                    <a href="/?tab=more">Get price alerts</a>
                </div>

                <nav v-if="article.toc.length" class="toc" aria-label="Table of contents">
                    <strong>On this page</strong>
                    <ol>
                        <li v-for="item in article.toc" :key="item.id">
                            <a :href="`#${item.id}`">{{ item.text }}</a>
                        </li>
                    </ol>
                </nav>

                <article class="body" v-html="article.body_html" />

                <section v-if="article.faq?.length" class="faq-block">
                    <h2>FAQ</h2>
                    <details v-for="(item, i) in article.faq" :key="i">
                        <summary>{{ item.question }}</summary>
                        <p>{{ item.answer }}</p>
                    </details>
                </section>

                <p v-if="article.tags?.length" class="tags">
                    <a v-for="t in article.tags" :key="t" :href="`/guides?tag=${encodeURIComponent(t)}`">#{{ t }}</a>
                </p>
            </main>

            <aside class="article-sidebar">
                <div class="sidebar-card">
                    <h3>⚡ Quick Calculators</h3>
                    <ul class="sidebar-links">
                        <li><a href="/fuel-cost-calculator">⛽ Fuel Cost Calculator</a></li>
                        <li><a href="/trip-cost-calculator">🚗 Trip Cost Calculator</a></li>
                        <li><a href="/mileage-calculator">📊 Mileage Calculator</a></li>
                        <li><a href="/monthly-fuel-budget-calculator">📅 Monthly Budget Calculator</a></li>
                    </ul>
                </div>

                <div v-if="related.length" class="sidebar-card related-card">
                    <h3>📰 Related Articles</h3>
                    <ul class="related-list">
                        <li v-for="a in related" :key="a.slug">
                            <a :href="`/guides/${a.slug}`">{{ a.title }}</a>
                            <span class="sub-text">{{ a.category_label }} · {{ a.reading_minutes }} min</span>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-card cta-card">
                    <h3>🔔 Daily Price Alerts</h3>
                    <p>Get instant notifications whenever OGRA updates petrol, diesel, or LPG rates in Pakistan.</p>
                    <a href="/" class="btn-primary">Open Live App</a>
                </div>
            </aside>
        </div>
    </SeoLandingShell>
</template>

<style scoped>
.full-page-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 36px;
    align-items: start;
    margin-top: 18px;
}
@media (max-width: 960px) {
    .full-page-grid {
        grid-template-columns: 1fr;
    }
}
.article-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 24px;
}
.sidebar-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 20px;
}
.sidebar-card h3 {
    margin: 0 0 14px;
    font-size: 16px;
    color: #e8eef5;
    font-family: 'IBM Plex Sans', sans-serif;
    font-weight: 650;
}
.sidebar-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.sidebar-links a {
    color: #c5ced8;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s ease;
}
.sidebar-links a:hover {
    color: #f2a63f;
}
.related-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.related-list li {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.related-list a {
    color: #f2a63f;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    line-height: 1.4;
}
.related-list .sub-text {
    color: #8b95a1;
    font-size: 12px;
}
.cta-card {
    background: radial-gradient(circle at top left, rgba(242, 166, 63, 0.15), transparent 70%), rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(242, 166, 63, 0.3);
}
.cta-card p {
    color: #c5ced8;
    font-size: 13.5px;
    line-height: 1.5;
    margin: 0 0 14px;
}
.btn-primary {
    display: inline-block;
    background: #f2a63f;
    color: #0b0f14;
    font-weight: 700;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    text-align: center;
    width: 100%;
    box-sizing: border-box;
}
.meta {
    color: #8b95a1;
    font-size: 13.5px;
    margin: 0 0 14px;
}
.meta a {
    color: #f2a63f;
    text-decoration: none;
}
.share-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}
.share-row button,
.share-row a {
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: #e8eef5;
    border-radius: 999px;
    padding: 8px 14px;
    font: inherit;
    text-decoration: none;
    cursor: pointer;
}
.toc {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 18px;
    background: rgba(255, 255, 255, 0.03);
}
.toc strong {
    display: block;
    margin-bottom: 8px;
}
.toc ol {
    margin: 0;
    padding-left: 18px;
    color: #c5ced8;
    line-height: 1.6;
}
.toc a {
    color: #f2a63f;
    text-decoration: none;
}
.body :deep(h2) {
    margin: 28px 0 10px;
    font-size: 18px;
    scroll-margin-top: 80px;
}
.body :deep(p) {
    color: #c5ced8;
    line-height: 1.65;
    font-size: 15px;
}
.body :deep(a) {
    color: #f2a63f;
}
.faq-block {
    margin-top: 28px;
}
.faq-block details {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 8px;
}
.faq-block summary {
    cursor: pointer;
    font-weight: 600;
}
.faq-block p {
    color: #c5ced8;
    line-height: 1.55;
}
.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 20px;
}
.tags a {
    color: #8b95a1;
    text-decoration: none;
    font-size: 13px;
}
.related {
    margin-top: 28px;
}
.related ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.related li {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.related a {
    color: #f2a63f;
    text-decoration: none;
    font-weight: 600;
}
.related span {
    color: #8b95a1;
    font-size: 12.5px;
}
</style>
