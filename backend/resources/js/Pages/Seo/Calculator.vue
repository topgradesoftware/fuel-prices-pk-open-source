<script setup lang="ts">
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

defineProps<{
    seoMeta: {
        title?: string;
        description?: string;
        keywords?: string;
        robots?: string;
        json_ld?: Array<Record<string, unknown>>;
        path_key?: string;
        h1?: string;
        faq?: Array<{ question: string; answer: string }>;
    };
    mode: string;
    app_path: string;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        :path="seoMeta.path_key || 'fuel-calculator'"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell :app-name="app_name" :title="seoMeta.h1 || 'Fuel calculator'" :lead="seoMeta.description">
        <p class="cta-row">
            <a class="cta" :href="app_path">Open live calculator</a>
            <a href="/fuel-calculator">All calculator modes</a>
        </p>

        <section v-if="seoMeta.faq?.length">
            <h2>FAQ</h2>
            <details v-for="(item, i) in seoMeta.faq" :key="i" class="faq">
                <summary>{{ item.question }}</summary>
                <p>{{ item.answer }}</p>
            </details>
        </section>

        <section>
            <h2>Related tools</h2>
            <ul class="links">
                <li><a href="/fuel-cost-calculator">Fuel cost calculator</a></li>
                <li><a href="/trip-cost-calculator">Trip cost calculator</a></li>
                <li><a href="/mileage-calculator">Mileage calculator</a></li>
                <li><a href="/monthly-fuel-budget-calculator">Monthly fuel budget</a></li>
                <li><a href="/price-history">Price history charts</a></li>
                <li><a href="/petrol-price-today">Petrol price today</a></li>
                <li><a href="/diesel-price-today">Diesel price today</a></li>
                <li><a href="/faq">Fuel price FAQ</a></li>
            </ul>
        </section>
    </SeoLandingShell>
</template>

<style scoped>
.cta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 18px;
    align-items: center;
    margin: 0 0 20px;
}
.cta {
    display: inline-flex;
    align-items: center;
    padding: 10px 16px;
    border-radius: 10px;
    background: #f2a63f;
    color: #1a1204 !important;
    font-weight: 700;
    font-size: 14px;
}
a {
    color: #f2a63f;
    text-decoration: none;
}
h2 {
    margin: 24px 0 12px;
    font-size: 18px;
}
.faq {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 8px;
    background: rgba(255, 255, 255, 0.02);
}
.faq summary {
    cursor: pointer;
    font-weight: 600;
    color: #e8eef5;
}
.faq p {
    margin: 10px 0 4px;
    color: #c5ced8;
    line-height: 1.55;
    font-size: 14.5px;
}
.links {
    padding-left: 18px;
    margin: 0;
    color: #c5ced8;
    line-height: 1.7;
}
</style>
