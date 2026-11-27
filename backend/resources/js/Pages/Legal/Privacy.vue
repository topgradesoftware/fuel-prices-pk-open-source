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
    <SeoHead page="privacy" />
    <LegalShell title="Privacy Policy" :updated="updated" :app-name="app_name">
        <section>
            <h2>What we collect</h2>
            <p>
                We may collect device identifiers, optional account details (guest name or Google
                sign-in), push notification tokens, language preference, and selected city so we can
                show fuel prices and send price-change alerts. Approximate location is used only when
                you enable nearby stations.
            </p>
        </section>

        <section>
            <h2>How we use data</h2>
            <p>
                Data is used to operate the app: display prices, sync devices for notifications,
                improve reliability, measure aggregate traffic, and prevent abuse. We do not sell
                personal data.
            </p>
        </section>

        <section>
            <h2>Cookies, analytics &amp; advertising</h2>
            <p>
                We may use first-party storage and analytics (for example Google Analytics) to
                understand which pages are useful. If we enable display advertising such as Google
                AdSense, third-party cookies or similar technologies may be used by Google and its
                partners to serve and measure ads according to their policies. You can control
                cookies in your browser and visit Google’s ad settings for personalised ads.
            </p>
        </section>

        <section>
            <h2>Price data sources</h2>
            <p>
                Fuel prices are aggregated from third-party public feeds and may be adjusted manually
                by operators. Figures are informational and may lag official notifications — see our
                <a href="/disclaimer">Disclaimer</a>.
            </p>
        </section>

        <section>
            <h2>Storage &amp; security</h2>
            <p>
                Data is stored on our servers and, for offline use, on your device (local cache).
                Access is protected with industry-standard practices including HTTPS in production.
            </p>
        </section>

        <section>
            <h2>Your choices</h2>
            <p>
                You can use guest mode, disable browser or OS notifications, clear app storage, or
                request account/device deletion by contacting us.
            </p>
        </section>

        <section>
            <h2>Contact</h2>
            <p>
                Privacy questions:
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
