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
    <SeoHead page="disclaimer" />
    <LegalShell title="Disclaimer" :updated="updated" :app-name="app_name">
        <section>
            <h2>Informational use only</h2>
            <p>
                {{ app_name }} provides fuel price tables, calculators, maps of nearby stations and
                editorial guides for general information. Figures may lag official OGRA or OMC
                notifications by minutes to hours. Always confirm the pump display or official notice
                before you buy fuel.
            </p>
        </section>

        <section>
            <h2>No professional advice</h2>
            <p>
                Content on this site is not financial, legal, tax or investment advice. Trip and tank
                cost estimates depend on the inputs you enter (distance, mileage, tank size) and on
                the last synced rates — results are approximate.
            </p>
        </section>

        <section>
            <h2>Third-party sources</h2>
            <p>
                We aggregate publicly available price data and may use Google Places (server-side)
                for nearby station discovery. We do not control third-party websites, pumps or OMCs
                and are not responsible for their accuracy, availability or conduct.
            </p>
        </section>

        <section>
            <h2>Liability</h2>
            <p>
                To the fullest extent permitted by law, {{ company_name }} and {{ app_name }} are not
                liable for losses arising from reliance on displayed prices, calculator outputs,
                station listings or guide articles. See also our
                <a href="/terms">Terms of Service</a>.
            </p>
        </section>

        <section>
            <h2>Corrections</h2>
            <p>
                Spot an error?
                <a href="/contact">Contact us</a>
                with the city, fuel and expected figure. We investigate material mistakes under our
                <a href="/editorial-policy">Editorial Policy</a>.
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
