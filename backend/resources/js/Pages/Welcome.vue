<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <main class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
        <div class="max-w-lg w-full rounded-xl bg-white shadow-sm border border-gray-200 p-8 text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Oil Price Calculator</h1>
            <p class="mt-3 text-gray-600">{{ message ?? 'Inertia + Vue 3 + TypeScript is ready.' }}</p>
        </div>
    </main>
</template>
