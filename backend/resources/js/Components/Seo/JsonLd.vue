<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: Record<string, unknown> | Array<Record<string, unknown>>;
}>();

const items = computed(() => (Array.isArray(props.data) ? props.data : [props.data]));
</script>

<template>
    <component
        :is="'script'"
        v-for="(item, index) in items"
        :key="index"
        type="application/ld+json"
        :head-key="`jsonld-${index}`"
    >
        {{ JSON.stringify(item) }}
    </component>
</template>
