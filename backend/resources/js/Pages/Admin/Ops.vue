<script setup lang="ts">
import { parseManualPrice } from '@/lib/schemas';
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminAiLimitPopup from '../../Components/AdminAiLimitPopup.vue';

type Company = {
    id: number;
    name: string;
    short_name: string;
    slug: string;
    is_active: boolean;
    priority: number;
};

type FuelType = { id: number; name: string; slug: string; unit: string };

type SyncLog = {
    id: number;
    provider: string;
    status: string;
    message: string | null;
    fetched: number;
    created: number;
    updated: number;
    finished_at: string | null;
};

const props = defineProps<{
    companies: Company[];
    fuel_types: FuelType[];
    sync_logs: SyncLog[];
    auth_user: { name?: string; email?: string; is_owner?: boolean } | null;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string }) ?? {});
const companySearch = ref('');
const fuelSearch = ref('');

const filteredCompanies = computed(() => {
    const q = companySearch.value.trim().toLowerCase();
    if (!q) return props.companies;
    return props.companies.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.short_name.toLowerCase().includes(q) ||
            c.slug.toLowerCase().includes(q),
    );
});

const filteredFuelTypes = computed(() => {
    const q = fuelSearch.value.trim().toLowerCase();
    if (!q) return props.fuel_types;
    return props.fuel_types.filter(
        (f) => f.name.toLowerCase().includes(q) || f.slug.toLowerCase().includes(q),
    );
});

const form = useForm({
    company_id: props.companies[0]?.id ?? 0,
    fuel_type_id: props.fuel_types[0]?.id ?? 0,
    price: 310.71,
    unit: 'Rs/L',
});

function syncNow() {
    router.post('/admin/ops/sync');
}

function toggle(company: Company) {
    router.post(`/admin/ops/companies/${company.id}/toggle`);
}

const clientError = ref('');

function saveManual() {
    clientError.value = '';
    const parsed = parseManualPrice({
        company_id: form.company_id,
        fuel_type_id: form.fuel_type_id,
        price: form.price,
        unit: form.unit,
    });
    if (!parsed.ok) {
        clientError.value = parsed.errors[0]?.message ?? 'Invalid input.';
        return;
    }
    form.company_id = parsed.data.company_id;
    form.fuel_type_id = parsed.data.fuel_type_id;
    form.price = parsed.data.price;
    form.unit = parsed.data.unit ?? 'Rs/L';
    form.post('/admin/ops/prices', { preserveScroll: true });
}

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="wrap">
        <AdminAiLimitPopup />
        <header class="top">
            <div>
                <p class="eyebrow">Admin ops</p>
                <h1>Oil Price control</h1>
                <p class="muted">{{ auth_user?.email }}</p>
            </div>
            <div class="actions">
                <a href="/">← Public app</a>
                <a href="/admin/automation">Automation</a>
                <a v-if="auth_user?.is_owner" href="/admin/users">Users</a>
                <button type="button" class="amber" @click="syncNow">Sync + history</button>
                <button type="button" class="ghost" @click="logout">Sign out</button>
            </div>
        </header>

        <div v-if="flash.success" class="flash ok">{{ flash.success }}</div>
        <div v-if="flash.error" class="flash err">{{ flash.error }}</div>

        <section class="grid">
            <article class="card">
                <h2>Companies</h2>
                <p class="muted">Toggle visibility in Compare / API.</p>
                <input
                    v-model="companySearch"
                    class="search"
                    type="search"
                    placeholder="Search company…"
                    autocomplete="off"
                />
                <ul>
                    <li v-for="c in filteredCompanies" :key="c.id">
                        <div>
                            <strong>{{ c.short_name }}</strong>
                            <span>{{ c.name }}</span>
                        </div>
                        <button type="button" :class="c.is_active ? 'on' : 'off'" @click="toggle(c)">
                            {{ c.is_active ? 'Active' : 'Hidden' }}
                        </button>
                    </li>
                    <li v-if="!filteredCompanies.length" class="empty">No companies match.</li>
                </ul>
            </article>

            <article class="card">
                <h2>Manual price</h2>
                <p class="muted">Fallback when a source is missing / wrong.</p>
                <form class="form" @submit.prevent="saveManual">
                    <label>
                        Search company
                        <input v-model="companySearch" type="search" placeholder="Filter company list…" />
                    </label>
                    <label>
                        Company
                        <select v-model.number="form.company_id">
                            <option v-for="c in filteredCompanies" :key="c.id" :value="c.id">{{ c.short_name }}</option>
                        </select>
                    </label>
                    <label>
                        Search fuel
                        <input v-model="fuelSearch" type="search" placeholder="Filter fuel list…" />
                    </label>
                    <label>
                        Fuel
                        <select v-model.number="form.fuel_type_id">
                            <option v-for="f in filteredFuelTypes" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>
                    </label>
                    <label>
                        Price
                        <input v-model.number="form.price" type="number" step="0.01" min="1" required />
                    </label>
                    <label>
                        Unit
                        <input v-model="form.unit" type="text" />
                    </label>
                    <p v-if="clientError" class="err">{{ clientError }}</p>
                    <button type="submit" class="amber" :disabled="form.processing">Save manual price</button>
                </form>
            </article>
        </section>

        <article class="card wide">
            <h2>Sync logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Status</th>
                        <th>Fetched</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in sync_logs" :key="log.id">
                        <td>{{ log.finished_at || '—' }}</td>
                        <td>{{ log.status }}</td>
                        <td>{{ log.fetched }}</td>
                        <td>{{ log.created }}</td>
                        <td>{{ log.updated }}</td>
                        <td>{{ log.message }}</td>
                    </tr>
                </tbody>
            </table>
        </article>
    </div>
</template>

<style scoped>
.wrap {
    min-height: 100vh;
    background: #0b0f14;
    color: #f5f1e8;
    padding: 24px;
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
}
.top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.eyebrow {
    margin: 0;
    color: #f2a63f;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
h1,
h2 {
    margin: 4px 0;
}
.muted {
    color: #9faab5;
    font-size: 13px;
}
.actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.actions a {
    color: #9faab5;
    text-decoration: none;
    font-size: 13px;
}
button,
.amber,
.ghost,
.on,
.off {
    border-radius: 8px;
    border: 1px solid #2a3441;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}
.amber {
    background: #f2a63f;
    color: #1a1204;
    border-color: #f2a63f;
}
.ghost {
    background: transparent;
    color: #f5f1e8;
}
.on {
    background: rgb(79 190 141 / 0.15);
    color: #4fbe8d;
}
.off {
    background: rgb(232 96 76 / 0.12);
    color: #e8604c;
}
.flash {
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 14px;
    font-size: 13px;
}
.flash.ok {
    background: rgb(79 190 141 / 0.12);
    color: #4fbe8d;
}
.err {
    color: #b42318;
    font-size: 0.9rem;
    margin: 0.5rem 0 0;
}
.flash.err {
    background: rgb(232 96 76 / 0.12);
    color: #e8604c;
}
.grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}
.card {
    background: #1b232d;
    border: 1px solid #2a3441;
    border-radius: 14px;
    padding: 16px;
}
.card.wide {
    overflow-x: auto;
}
.search {
    width: 100%;
    margin-top: 10px;
    box-sizing: border-box;
}
ul {
    list-style: none;
    padding: 0;
    margin: 12px 0 0;
    display: grid;
    gap: 8px;
    max-height: 420px;
    overflow-y: auto;
}
li {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    padding: 10px;
    border: 1px solid #2a3441;
    border-radius: 10px;
    background: #212b37;
}
li.empty {
    color: #9faab5;
    justify-content: center;
}
li strong {
    display: block;
}
li span {
    color: #9faab5;
    font-size: 12px;
}
.form {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}
label {
    display: grid;
    gap: 5px;
    font-size: 12px;
    color: #9faab5;
}
input,
select {
    background: #10151c;
    border: 1px solid #2a3441;
    border-radius: 8px;
    padding: 9px 10px;
    color: #f5f1e8;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 12px;
}
th,
td {
    text-align: left;
    padding: 8px 6px;
    border-bottom: 1px solid #2a3441;
    vertical-align: top;
}
th {
    color: #9faab5;
    font-weight: 600;
}
@media (max-width: 860px) {
    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
