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
    };
    cities: Array<{ name: string; slug: string; province?: string | null }>;
    fuels: Array<{ key: string; label: string; prefix: string; hub: string }>;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        path="prices"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell :app-name="app_name" :title="seoMeta.h1 || 'Prices by city'" :lead="seoMeta.description">
        <section class="hubs">
            <a v-for="f in fuels" :key="f.key" class="hub-card" :href="`/${f.hub}`">
                {{ f.label }} price today
            </a>
        </section>
        <section>
            <h2>Cities</h2>
            <ul class="city-grid">
                <li v-for="c in cities" :key="c.slug">
                    <a :href="`/petrol-price/${c.slug}`">{{ c.name }}</a>
                    <span v-if="c.province" class="prov">{{ c.province }}</span>
                </li>
            </ul>
        </section>
    </SeoLandingShell>
</template>

<style scoped>
.hubs {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    margin-bottom: 10px;
}
.hub-card {
    display: block;
    padding: 14px 16px;
    border-radius: 10px;
    background: rgba(242, 166, 63, 0.1);
    border: 1px solid rgba(242, 166, 63, 0.28);
    color: #f2a63f;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}
h2 {
    margin: 28px 0 12px;
    font-size: 18px;
}
.city-grid {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px 14px;
}
.city-grid a {
    color: #e8eef5;
    text-decoration: none;
    font-size: 14px;
}
.city-grid a:hover {
    color: #f2a63f;
}
.prov {
    display: block;
    color: #8b95a1;
    font-size: 11px;
}
</style>
