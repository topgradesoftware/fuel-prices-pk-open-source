<script setup lang="ts">
import { parseAdminLogin } from '@/lib/schemas';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const props = defineProps<{
    demo_login?: boolean;
    prefill?: { email?: string; password?: string };
}>();

const form = useForm({
    email: props.prefill?.email ?? '',
    password: props.prefill?.password ?? '',
});

const clientError = ref('');
const errors = computed(() => (page.props.errors as Record<string, string>) || {});

function submit() {
    clientError.value = '';
    const parsed = parseAdminLogin({ email: form.email, password: form.password });
    if (!parsed.ok) {
        clientError.value = parsed.errors[0]?.message ?? 'Invalid input.';
        return;
    }
    form.email = parsed.data.email;
    form.password = parsed.data.password;
    form.post('/admin/login');
}
</script>

<template>
    <div class="wrap">
        <form class="card" @submit.prevent="submit">
            <p class="eyebrow">TopGrade Oil Price</p>
            <h1>Admin login</h1>
            <p class="muted">Ops panel for sync, companies, and manual prices.</p>
            <p v-if="demo_login" class="demo">Local only: admin@oilprice.test / password</p>

            <label>
                Email
                <input v-model="form.email" type="email" required autocomplete="username" />
            </label>
            <label>
                Password
                <input v-model="form.password" type="password" required autocomplete="current-password" />
            </label>

            <p v-if="clientError" class="err">{{ clientError }}</p>
            <p v-if="errors.email" class="err">{{ errors.email }}</p>

            <button type="submit" :disabled="form.processing">Sign in</button>
            <a href="/" class="back">← Back to public app</a>
        </form>
    </div>
</template>

<style scoped>
.wrap {
    min-height: 100dvh;
    min-height: 100svh;
    display: grid;
    place-items: center;
    background: #0b0f14;
    padding: max(24px, env(safe-area-inset-top, 0px)) max(16px, env(safe-area-inset-right, 0px))
        max(24px, env(safe-area-inset-bottom, 0px)) max(16px, env(safe-area-inset-left, 0px));
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
    box-sizing: border-box;
    overflow-x: clip;
}
.card {
    width: min(420px, 100%);
    background: #1b232d;
    border: 1px solid #2a3441;
    border-radius: 14px;
    padding: 24px;
    color: #f5f1e8;
    display: grid;
    gap: 12px;
    box-sizing: border-box;
}
.eyebrow {
    margin: 0;
    color: #f2a63f;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
h1 {
    margin: 0;
    font-size: 22px;
}
.muted {
    margin: 0 0 8px;
    color: #9faab5;
    font-size: 13px;
}
.demo {
    margin: 0;
    font-size: 12px;
    color: #f2a63f;
    background: rgba(242, 166, 63, 0.12);
    border: 1px solid rgba(242, 166, 63, 0.35);
    border-radius: 8px;
    padding: 8px 10px;
}
label {
    display: grid;
    gap: 6px;
    font-size: 12.5px;
    color: #9faab5;
}
input {
    background: #212b37;
    border: 1px solid #2a3441;
    border-radius: 10px;
    color: #f5f1e8;
    padding: 12px;
    font-size: 16px;
    font-family: inherit;
    min-height: 44px;
    width: 100%;
    box-sizing: border-box;
}
button {
    margin-top: 4px;
    background: #f2a63f;
    color: #1a1204;
    border: 0;
    border-radius: 10px;
    padding: 12px;
    min-height: 44px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}
button:disabled {
    opacity: 0.6;
    cursor: wait;
}
.err {
    margin: 0;
    color: #e8604c;
    font-size: 12.5px;
}
.back {
    color: #9faab5;
    font-size: 13px;
    text-align: center;
    text-decoration: none;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
