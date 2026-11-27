<script setup lang="ts">
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

defineProps<{
    seoMeta: {
        title?: string;
        description?: string;
        keywords?: string;
        canonical?: string;
        robots?: string;
        json_ld?: Array<Record<string, unknown>>;
        path_key?: string;
        h1?: string;
    };
    faqs: Array<{ question?: string; answer?: string }>;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        :path="seoMeta.path_key || 'faq'"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell :app-name="app_name" :title="seoMeta.h1 || 'FAQ'" :lead="seoMeta.description">
        <div class="faq-grid">
            <section v-for="(item, i) in faqs" :key="i" class="faq-item">
                <h2>{{ item.question }}</h2>
                <p>{{ item.answer }}</p>
            </section>
        </div>
        <p class="cta">
            Check live rates:
            <a href="/petrol-price-today">petrol</a>,
            <a href="/diesel-price-today">diesel</a>,
            <a href="/lpg-price-today">LPG</a>,
            the
            <a href="/fuel-calculator">fuel calculator</a>,
            <a href="/price-history">price history</a>,
            or the
            <a href="/">Fuel Prices PK app</a>.
        </p>
    </SeoLandingShell>
</template>

<style scoped>
.faq-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 24px;
}
@media (max-width: 768px) {
    .faq-grid {
        grid-template-columns: 1fr;
    }
}
.faq-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 18px 20px;
}
h2 {
    margin: 0 0 8px;
    font-size: 16px;
    color: #fff;
}
p {
    margin: 0;
    color: #c5ced8;
    line-height: 1.55;
    font-size: 14.5px;
}
.cta {
    margin-top: 28px;
}
a {
    color: #f2a63f;
}
</style>
