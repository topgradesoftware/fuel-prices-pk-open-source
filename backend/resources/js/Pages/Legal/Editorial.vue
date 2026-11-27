<script setup lang="ts">
import { trackEvent } from '@/composables/useAnalytics';
import LegalShell from '@/Components/Legal/LegalShell.vue';
import SeoHead from '@/Components/Seo/SeoHead.vue';

defineProps<{
    updated: string;
    app_name: string;
    contact_email: string;
    company_name: string;
}>();
</script>

<template>
    <SeoHead page="editorial-policy" />
    <LegalShell title="Editorial Policy" :updated="updated" :app-name="app_name">
        <section>
            <h2>Purpose</h2>
            <p>
                This policy explains how {{ app_name }} creates, reviews and updates guides and
                explainers about Pakistan fuel prices. Our goal is accurate, useful, non-sensational
                information for everyday drivers — not clickbait.
            </p>
        </section>

        <section>
            <h2>Sources</h2>
            <p>
                Price-sensitive claims are tied to OGRA publications, Petroleum Division or
                well-known OMC pages and reputable Pakistani business reporting. Guides cite the type
                of source in plain language. Live tables show an effective or sync date when
                available.
            </p>
        </section>

        <section>
            <h2>Authorship &amp; review</h2>
            <p>
                Articles are written or supervised by {{ company_name }} editors and may use
                assisted drafting tools. Material that could affect purchasing decisions (rate
                changes, levy explanations, safety tips) is human-reviewed before publication where
                our automation pipeline requires review. Authors are attributed on guide pages.
            </p>
        </section>

        <section>
            <h2>Corrections &amp; updates</h2>
            <p>
                We correct factual errors promptly and update evergreen guides when OGRA’s mechanism
                or market practice changes. Significant corrections may be noted in the article body.
                Email
                <a @click="trackEvent('contact_click', { method: 'email' })" :href="`mailto:${contact_email}`">{{ contact_email }}</a>
                to request a review.
            </p>
        </section>

        <section>
            <h2>Independence &amp; advertising</h2>
            <p>
                Editorial content is not sold to OMCs. If we introduce display advertising (for
                example Google AdSense), ads will be labelled and will not change our price tables or
                guide conclusions. Sponsored content, if any, will be clearly disclosed.
            </p>
        </section>

        <section>
            <h2>Related policies</h2>
            <ul>
                <li><a href="/about">About Us</a></li>
                <li><a href="/disclaimer">Disclaimer</a></li>
                <li><a href="/privacy">Privacy Policy</a></li>
                <li><a href="/terms">Terms of Service</a></li>
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
