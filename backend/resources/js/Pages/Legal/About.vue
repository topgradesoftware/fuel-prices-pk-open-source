<script setup lang="ts">
import { trackEvent } from '@/composables/useAnalytics';
import LegalShell from '@/Components/Legal/LegalShell.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';

defineProps<{
    updated: string;
    app_name: string;
    contact_email: string;
    company_name: string;
    company_url: string;
}>();
</script>

<template>
    <SeoHead page="about" />
    <LegalShell title="About Us" :updated="updated" :app-name="app_name">
        <section>
            <h2>Who we are</h2>
            <p>
                {{ app_name }} is a free Pakistan fuel-price utility built by
                <a :href="company_url" rel="noopener noreferrer">{{ company_name }}</a>.
                We help drivers, motorcycle riders, fleet operators and households check petrol,
                high-speed diesel (HSD) and LPG rates, compare oil marketing companies (OMCs),
                estimate trip and tank costs, and follow OGRA-linked updates without hunting through
                scattered notices.
            </p>
        </section>

        <section>
            <h2>What we publish</h2>
            <p>
                Live and historical price tables, city landings (Karachi, Lahore, Islamabad and more),
                OMC pages, calculators, FAQs and editorial guides. Price figures are aggregated from
                public OGRA publications, Petroleum Division confirmations and OMC feeds, then
                synced into our database for the web app and PWA.
            </p>
        </section>

        <section>
            <h2>Our mission</h2>
            <p>
                Make everyday fuel decisions clearer: show today’s rates next to last-updated times,
                explain how Pakistan’s pricing mechanism works in plain language, and give practical
                tools (fuel cost, trip cost, mileage, monthly budget) that stay useful after each
                OGRA revision.
            </p>
        </section>

        <section>
            <h2>Independence</h2>
            <p>
                {{ app_name }} is not affiliated with OGRA, the Petroleum Division, or any oil
                marketing company. We do not sell fuel. Editorial and product decisions are made by
                {{ company_name }} under our
                <a href="/editorial-policy">Editorial Policy</a>.
            </p>
        </section>

        <section>
            <h2>Contact</h2>
            <p>
                Reach us via the
                <a href="/contact">Contact</a>
                page or email
                <a @click="trackEvent('contact_click', { method: 'email' })" :href="`mailto:${contact_email}`">{{ contact_email }}</a>.
            </p>
        </section>
    </LegalShell>
</template>

<style scoped>
h2 {
    margin: 22px 0 8px;
    font-size: 17px;
}
p {
    margin: 0;
    line-height: 1.6;
    color: #c5ced8;
    font-size: 14.5px;
}
a {
    color: #f2a63f;
}
</style>
