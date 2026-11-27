<script setup lang="ts">
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

type PriceRow = {
    company?: string;
    company_slug?: string;
    city?: string;
    price: number;
    unit?: string;
    effective_date?: string | null;
};

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
    fuel: { key: string; label: string; slug: string; prefix: string; hub: string };
    prices: PriceRow[];
    daily_update?: { date: string; text: string } | null;
    cities: Array<{ name: string; slug: string; province?: string | null }>;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        :path="seoMeta.path_key || fuel.hub"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell :app-name="app_name" :title="seoMeta.h1 || fuel.label" :lead="seoMeta.description">
        <section v-if="daily_update" class="daily-update">
            <h2>Update — {{ daily_update.date }}</h2>
            <p>{{ daily_update.text }}</p>
        </section>

        <section v-if="prices.length">
            <h2>Latest {{ fuel.label }} listings</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>City</th>
                            <th>Price</th>
                            <th>As of</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in prices" :key="i">
                            <td>
                                <a v-if="row.company_slug" :href="`/omc/${row.company_slug}`">{{
                                    row.company
                                }}</a>
                                <span v-else>{{ row.company }}</span>
                            </td>
                            <td>{{ row.city }}</td>
                            <td>Rs {{ row.price.toFixed(2) }} {{ row.unit || '' }}</td>
                            <td>{{ row.effective_date || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p v-else class="muted">Prices update regularly from OMC / OGRA-linked feeds. Open the live app for the latest sync.</p>

        <section>
            <h2>{{ fuel.label }} price by city</h2>
            <ul class="city-grid">
                <li v-for="c in cities" :key="c.slug">
                    <a :href="`/${fuel.prefix}/${c.slug}`">{{ c.name }}</a>
                </li>
            </ul>
        </section>
    </SeoLandingShell>
</template>

<style scoped>
h2 {
    margin: 28px 0 12px;
    font-size: 18px;
}
.daily-update {
    border: 1px solid rgba(242, 166, 63, 0.25);
    background: rgba(242, 166, 63, 0.06);
    border-radius: 10px;
    padding: 4px 16px 14px;
}
.daily-update h2 {
    margin: 14px 0 8px;
    color: #f2a63f;
    font-size: 16px;
}
.daily-update p {
    margin: 0;
    color: #c5ced8;
    font-size: 14.5px;
    line-height: 1.6;
}
.muted {
    color: #8b95a1;
    font-size: 14px;
}
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    max-width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
th,
td {
    text-align: left;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
th {
    color: #8b95a1;
    font-weight: 600;
}
a {
    color: #f2a63f;
    text-decoration: none;
}
.city-grid {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px 12px;
}
.city-grid a {
    color: #c5ced8;
    font-size: 14px;
}
.city-grid a:hover {
    color: #f2a63f;
}
</style>
