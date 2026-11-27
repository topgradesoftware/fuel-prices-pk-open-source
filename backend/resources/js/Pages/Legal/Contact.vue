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
    <SeoHead page="contact" />
    <LegalShell title="Contact" :updated="updated" :app-name="app_name">
        <section>
            <h2>Get in touch</h2>
            <p>
                For product feedback, data corrections, privacy requests, partnership enquiries or
                press, email
                <a @click="trackEvent('contact_click', { method: 'email' })" :href="`mailto:${contact_email}`">{{ contact_email }}</a>.
                We aim to respond within 2–3 business days.
            </p>
        </section>

        <section>
            <h2>Publisher</h2>
            <p>
                {{ app_name }} is published by
                <a :href="company_url" rel="noopener noreferrer">{{ company_name }}</a>.
                Website:
                <a :href="company_url" rel="noopener noreferrer">{{ company_url }}</a>.
            </p>
        </section>

        <section>
            <h2>What to include</h2>
            <p>
                Helpful details speed up replies: your city, fuel type (petrol / HSD / LPG), the OMC
                name if relevant, a screenshot or link, and whether the issue is on the live app,
                a city landing page, or a guide article.
            </p>
        </section>

        <section>
            <h2>Policy pages</h2>
            <ul>
                <li><a href="/privacy">Privacy Policy</a></li>
                <li><a href="/terms">Terms of Service</a></li>
                <li><a href="/disclaimer">Disclaimer</a></li>
                <li><a href="/editorial-policy">Editorial Policy</a></li>
                <li><a href="/about">About Us</a></li>
            </ul>
        </section>
    </LegalShell>
</template>

<style scoped>
h2 {
    margin: 22px 0 8px;
    font-size: 17px;
}
p,
li {
    margin: 0;
    line-height: 1.6;
    color: #c5ced8;
    font-size: 14.5px;
}
ul {
    padding-left: 18px;
    margin: 0;
    line-height: 1.8;
}
a {
    color: #f2a63f;
}
</style>
