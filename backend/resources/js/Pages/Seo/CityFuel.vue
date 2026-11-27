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
        intro?: string;
        faq?: Array<{ question: string; answer: string }>;
    };
    fuel: { key: string; label: string; slug: string; prefix: string; hub: string };
    city: { name: string; slug: string; province?: string | null };
    prices: PriceRow[];
    last_updated?: string | null;
    daily_update?: { date: string; text: string } | null;
    related_cities: Array<{ name: string; slug: string }>;
    related_fuels: Array<{ label: string; href: string }>;
    related_guides?: Array<{ slug: string; title: string; excerpt?: string; href: string }>;
    app_name: string;
}>();
</script>

<template>
    <SeoHead
        :title="seoMeta.title"
        :description="seoMeta.description"
        :keywords="seoMeta.keywords"
        :path="seoMeta.path_key || `${fuel.prefix}/${city.slug}`"
        :robots="seoMeta.robots"
        :json-ld="seoMeta.json_ld"
    />
    <SeoLandingShell :app-name="app_name" :title="seoMeta.h1 || city.name" :lead="seoMeta.description">
        <p class="crumbs">
            <a :href="`/${fuel.hub}`">{{ fuel.label }} today</a>
            ·
            <a href="/prices">All cities</a>
            ·
            <a href="/guides">Guides</a>
            <template v-if="city.province"> · {{ city.province }}</template>
        </p>

        <p v-if="last_updated" class="updated">
            Last updated: <time :datetime="last_updated">{{ last_updated }}</time>
            · Rates are informational —
            <a href="/disclaimer">see disclaimer</a>
        </p>

        <p v-if="seoMeta.intro" class="intro">{{ seoMeta.intro }}</p>

        <section class="explain">
            <h2>How {{ fuel.label.toLowerCase() }} prices work in {{ city.name }}</h2>
            <p>
                Pakistan’s regulated retail {{ fuel.label.toLowerCase() }} follows OGRA’s pricing
                mechanism and federal notifications. Oil marketing companies (PSO, Shell, APL and
                others) publish pump rates against that framework. This page lists city-linked rows
                when our sync has them; otherwise you see national OGRA-linked figures so you still
                have a usable number for planning.
            </p>
            <p>
                Small board differences can still appear at individual stations. Always confirm the
                price on the pump display before you fill. For context beyond today’s table, read our
                <a href="/guides/why-petrol-prices-change-in-pakistan">pricing explainers</a>
                and
                <a href="/editorial-policy">editorial policy</a>.
            </p>
        </section>

        <section v-if="daily_update" class="daily-update">
            <h2>Update — {{ daily_update.date }}</h2>
            <p>{{ daily_update.text }}</p>
        </section>

        <section v-if="prices.length">
            <h2>OMC rates for {{ city.name }}</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Price</th>
                            <th>Coverage</th>
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
                            <td>Rs {{ row.price.toFixed(2) }} {{ row.unit || '' }}</td>
                            <td>{{ row.city }}</td>
                            <td>{{ row.effective_date || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="note">
                City-specific rows appear when available; otherwise national OGRA-linked rates are shown.
            </p>
        </section>
        <p v-else class="muted">
            No stored {{ fuel.label.toLowerCase() }} rows for {{ city.name }} yet — check the live app after the next
            sync.
        </p>

        <section>
            <h2>Trends &amp; planning tools</h2>
            <ul class="links">
                <li><a href="/price-history">{{ fuel.label }} price history</a> — recent direction of rates</li>
                <li><a href="/fuel-cost-calculator">Fuel cost calculator</a> — litres × today’s price</li>
                <li><a href="/trip-cost-calculator">Trip cost calculator</a> — one-way or round trip</li>
                <li><a href="/mileage-calculator">Mileage calculator</a> — track real km/L</li>
                <li><a href="/faq">Fuel price FAQ</a></li>
            </ul>
        </section>

        <section v-if="seoMeta.faq?.length">
            <h2>FAQ — {{ fuel.label }} in {{ city.name }}</h2>
            <dl class="faq">
                <template v-for="(item, i) in seoMeta.faq" :key="i">
                    <dt>{{ item.question }}</dt>
                    <dd>{{ item.answer }}</dd>
                </template>
            </dl>
        </section>

        <section v-if="related_guides?.length">
            <h2>Related guides</h2>
            <ul class="links">
                <li v-for="g in related_guides" :key="g.slug">
                    <a :href="g.href">{{ g.title }}</a>
                </li>
            </ul>
        </section>

        <section>
            <h2>Other fuels in {{ city.name }}</h2>
            <ul class="links">
                <li v-for="f in related_fuels" :key="f.href">
                    <a :href="f.href">{{ f.label }}</a>
                </li>
            </ul>
        </section>

        <section>
            <h2>Related cities</h2>
            <ul class="city-grid">
                <li v-for="c in related_cities" :key="c.slug">
                    <a :href="`/${fuel.prefix}/${c.slug}`">{{ c.name }}</a>
                </li>
            </ul>
        </section>
    </SeoLandingShell>
</template>

<style scoped>
.crumbs,
.note,
.muted,
.updated {
    color: #8b95a1;
    font-size: 13.5px;
    margin: 0 0 16px;
}
.updated a,
.explain a {
    color: #f2a63f;
}
.intro,
.explain p {
    margin: 0 0 14px;
    color: #c5ced8;
    font-size: 15px;
    line-height: 1.6;
}
h2 {
    margin: 24px 0 12px;
    font-size: 18px;
}
.daily-update {
    border: 1px solid rgba(242, 166, 63, 0.25);
    background: rgba(242, 166, 63, 0.06);
    border-radius: 10px;
    padding: 4px 16px 14px;
    margin-bottom: 16px;
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
.links {
    padding-left: 18px;
    margin: 0 0 16px;
    color: #c5ced8;
    line-height: 1.7;
}
.faq {
    margin: 0 0 16px;
}
.faq dt {
    margin: 14px 0 6px;
    font-weight: 600;
    color: #e8eef5;
    font-size: 14.5px;
}
.faq dd {
    margin: 0;
    color: #c5ced8;
    font-size: 14.5px;
    line-height: 1.6;
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
}
</style>
