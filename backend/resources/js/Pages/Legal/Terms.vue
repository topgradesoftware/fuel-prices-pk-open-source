<script setup lang="ts">
import { trackEvent } from '@/composables/useAnalytics';
import LegalShell from '@/Components/Legal/LegalShell.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';

defineProps<{
    updated: string;
    app_name: string;
    contact_email: string;
}>();
</script>

<template>
    <SeoHead page="terms" />
    <LegalShell title="Terms of Service" :updated="updated" :app-name="app_name">
        <section>
            <h2>Acceptance</h2>
            <p>
                By using {{ app_name }} (web app, PWA or related pages) you agree to these terms. If
                you do not agree, do not use the service. Also read our
                <a href="/disclaimer">Disclaimer</a>
                and
                <a href="/privacy">Privacy Policy</a>.
            </p>
        </section>

        <section>
            <h2>Informational prices only</h2>
            <p>
                Prices, history and calculators are for convenience. Official petroleum product
                prices are notified by relevant authorities and oil marketing companies. Always
                confirm at the pump. We are not liable for decisions made solely on this data.
            </p>
        </section>

        <section>
            <h2>Acceptable use</h2>
            <p>
                Do not abuse the API, attempt unauthorized access, scrape aggressively, or use the
                service for unlawful purposes. We may rate-limit or suspend access to protect the
                platform.
            </p>
        </section>

        <section>
            <h2>Accounts &amp; devices</h2>
            <p>
                Guest and signed-in sessions may be revoked. You are responsible for activity on your
                devices. Push notifications require OS permission and may be delayed or unavailable.
            </p>
        </section>

        <section>
            <h2>Availability</h2>
            <p>
                We aim for reliable uptime but do not guarantee uninterrupted service. Upstream price
                providers may be temporarily unavailable; cached or last-known prices may be shown.
            </p>
        </section>

        <section>
            <h2>Advertising</h2>
            <p>
                The service may display third-party advertisements. Ad networks are responsible for
                their own creative and targeting subject to applicable policies. Editorial price
                tables are not controlled by advertisers.
            </p>
        </section>

        <section>
            <h2>Changes</h2>
            <p>
                We may update these terms. Continued use after changes constitutes acceptance.
                Material updates will change the “Last updated” date above.
            </p>
        </section>

        <section>
            <h2>Contact</h2>
            <p>
                Support:
                <a @click="trackEvent('contact_click', { method: 'email' })" :href="`mailto:${contact_email}`">{{ contact_email }}</a>
                ·
                <a href="/contact">Contact page</a>
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
