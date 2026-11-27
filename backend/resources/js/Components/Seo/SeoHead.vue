<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import JsonLd from '@/Components/Seo/JsonLd.vue';
import { resolveSeoMeta } from '@/lib/seo';

const props = defineProps<{
    page?: string;
    title?: string;
    description?: string;
    keywords?: string;
    path?: string;
    robots?: string;
    jsonLd?: Record<string, unknown> | Array<Record<string, unknown>> | null;
}>();

const inertiaPage = usePage();

const meta = computed(() =>
    resolveSeoMeta(inertiaPage.props.seo as Parameters<typeof resolveSeoMeta>[0], {
        page: props.page ?? '',
        title: props.title ?? '',
        description: props.description ?? '',
        keywords: props.keywords ?? '',
        path: props.path ?? '',
        robots: props.robots ?? '',
    }),
);

const jsonLdBlocks = computed(() => {
    if (props.jsonLd) return props.jsonLd;
    if (meta.value.json_ld?.length) return meta.value.json_ld as Array<Record<string, unknown>>;
    if (props.page === 'home') {
        return (meta.value.homeJsonLd ?? []) as Array<Record<string, unknown>>;
    }
    return [];
});
</script>

<template>
    <Head :title="meta.title">
        <meta head-key="description" name="description" :content="meta.description" />
        <meta v-if="meta.keywords" head-key="keywords" name="keywords" :content="meta.keywords" />
        <meta v-if="meta.robots" head-key="robots" name="robots" :content="meta.robots" />
        <link v-if="meta.canonical" head-key="canonical" rel="canonical" :href="meta.canonical" />
        <link
            v-if="meta.canonical"
            head-key="hreflang"
            rel="alternate"
            :hreflang="meta.hreflang || 'en-GB'"
            :href="meta.canonical"
        />
        <link
            v-if="meta.canonical"
            head-key="hreflang-default"
            rel="alternate"
            hreflang="x-default"
            :href="meta.canonical"
        />
        <meta head-key="geo.region" name="geo.region" content="PK" />
        <meta head-key="geo.placename" name="geo.placename" content="Pakistan" />
        <meta head-key="author" name="author" content="TopGrade Software" />
        <meta head-key="application-name" name="application-name" :content="meta.site_name" />
        <meta head-key="theme-color" name="theme-color" content="#f2a63f" />
        <meta head-key="apple-mobile-web-app-title" name="apple-mobile-web-app-title" :content="meta.site_name" />

        <meta head-key="og:type" property="og:type" :content="meta.og_type || 'website'" />
        <meta head-key="og:site_name" property="og:site_name" :content="meta.site_name" />
        <meta head-key="og:title" property="og:title" :content="meta.title" />
        <meta head-key="og:description" property="og:description" :content="meta.description" />
        <meta head-key="og:url" property="og:url" :content="meta.canonical" />
        <meta head-key="og:locale" property="og:locale" content="en_GB" />
        <meta head-key="og:image" property="og:image" :content="String(meta.og_image.url || '')" />
        <meta
            head-key="og:image:width"
            property="og:image:width"
            :content="String(meta.og_image.width || 1200)"
        />
        <meta
            head-key="og:image:height"
            property="og:image:height"
            :content="String(meta.og_image.height || 630)"
        />
        <meta
            head-key="og:image:type"
            property="og:image:type"
            :content="String(meta.og_image.type || 'image/png')"
        />
        <meta
            head-key="og:image:alt"
            property="og:image:alt"
            :content="String(meta.og_image.alt || meta.site_name)"
        />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="meta.title" />
        <meta head-key="twitter:description" name="twitter:description" :content="meta.description" />
        <meta head-key="twitter:image" name="twitter:image" :content="String(meta.og_image.url || '')" />

        <slot />
    </Head>
    <JsonLd v-if="jsonLdBlocks?.length" :data="jsonLdBlocks" />
</template>
