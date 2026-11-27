<script setup lang="ts">
import SeoHead from '@/Components/Seo/SeoHead.vue';
import SeoLandingShell from '@/Components/Seo/SeoLandingShell.vue';

type PriceRow = {
    company?: string;
    fuel?: string;
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
    company: { name?: string | null; short_name?: string | null; slug: string };
    prices: PriceRow[];
    fuels: Array<{ label: string; hub: string }>;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        :path="seoMeta.path_key || `omc/${company.slug}`"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell
        :app-name="app_name"
        :title="seoMeta.h1 || company.short_name || company.name || 'OMC'"
        :lead="seoMeta.description"
    >
        <section v-if="prices.length">
            <h2>Latest prices</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fuel</th>
                            <th>City</th>
                            <th>Price</th>
                            <th>As of</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in prices" :key="i">
                            <td>{{ row.fuel }}</td>
                            <td>{{ row.city }}</td>
                            <td>Rs {{ row.price.toFixed(2) }} {{ row.unit || '' }}</td>
                            <td>{{ row.effective_date || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <p v-else class="muted">No stored rows for this OMC yet.</p>
        <section>
            <h2>National hubs</h2>
            <ul>
                <li v-for="f in fuels" :key="f.hub">
                    <a :href="`/${f.hub}`">{{ f.label }} price today</a>
                </li>
            </ul>
        </section>
    </SeoLandingShell>
</template>

<style scoped>
h2 {
    margin: 24px 0 12px;
    font-size: 18px;
}
.muted {
    color: #8b95a1;
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
}
a {
    color: #f2a63f;
    text-decoration: none;
}
ul {
    padding-left: 18px;
    color: #c5ced8;
}
</style>
