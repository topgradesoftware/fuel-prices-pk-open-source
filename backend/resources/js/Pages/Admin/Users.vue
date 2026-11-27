<script setup lang="ts">
import { parseAdminPassword, parseAdminUser } from '@/lib/schemas';
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminAiLimitPopup from '../../Components/AdminAiLimitPopup.vue';

type UserRow = {
    id: number;
    name: string;
    email: string;
    auth_provider: string | null;
    is_guest: boolean;
    is_admin: boolean;
    is_owner: boolean;
    devices_count: number;
    created_at: string | null;
};

const props = defineProps<{
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { q: string; filter: string };
    stats: { total: number; admins: number; guests: number; google: number };
    auth_user: { name?: string; email?: string; is_owner?: boolean } | null;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string }) ?? {});

const search = ref(props.filters.q);
const filter = ref(props.filters.filter || 'all');

const filterTabs = [
    { key: 'all', label: 'All' },
    { key: 'admins', label: 'Admins' },
    { key: 'google', label: 'Google' },
    { key: 'guests', label: 'Guests' },
];

function applyFilters(pageNum = 1) {
    router.get(
        '/admin/users',
        { q: search.value || undefined, filter: filter.value !== 'all' ? filter.value : undefined, page: pageNum > 1 ? pageNum : undefined },
        { preserveState: true, preserveScroll: true },
    );
}

function setFilter(key: string) {
    filter.value = key;
    applyFilters();
}

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    is_admin: true,
});

const showCreate = ref(false);
const clientError = ref('');

function createUser() {
    clientError.value = '';
    const parsed = parseAdminUser({
        name: createForm.name,
        email: createForm.email,
        password: createForm.password,
        is_admin: createForm.is_admin,
    });
    if (!parsed.ok) {
        clientError.value = parsed.errors[0]?.message ?? 'Invalid input.';
        return;
    }
    createForm.name = parsed.data.name;
    createForm.email = parsed.data.email;
    createForm.password = parsed.data.password;
    createForm.is_admin = parsed.data.is_admin;
    createForm.post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
}

function toggleAdmin(user: UserRow) {
    const verb = user.is_admin ? 'Remove admin from' : 'Make admin:';
    if (!confirm(`${verb} ${user.email}?`)) return;
    router.post(`/admin/users/${user.id}/toggle-admin`, {}, { preserveScroll: true });
}

const resetTarget = ref<UserRow | null>(null);
const resetPasswordValue = ref('');

function submitReset() {
    if (!resetTarget.value) return;
    clientError.value = '';
    const parsed = parseAdminPassword({ password: resetPasswordValue.value });
    if (!parsed.ok) {
        clientError.value = parsed.errors[0]?.message ?? 'Invalid input.';
        return;
    }
    router.post(
        `/admin/users/${resetTarget.value.id}/reset-password`,
        { password: parsed.data.password },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetTarget.value = null;
                resetPasswordValue.value = '';
            },
        },
    );
}

function deleteUser(user: UserRow) {
    if (!confirm(`Delete ${user.email} permanently? Their devices are kept but unlinked.`)) return;
    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
}

function badge(user: UserRow): { label: string; cls: string } {
    if (user.is_owner) return { label: 'Owner', cls: 'owner' };
    if (user.is_admin) return { label: 'Admin', cls: 'admin' };
    if (user.is_guest) return { label: 'Guest', cls: 'guest' };
    return { label: user.auth_provider || 'user', cls: 'user' };
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
                <p class="eyebrow">User management</p>
                <h1>Users</h1>
                <p class="muted">{{ auth_user?.email }}</p>
            </div>
            <div class="actions">
                <a href="/admin/ops">← Ops</a>
                <button type="button" class="amber" @click="showCreate = !showCreate">
                    {{ showCreate ? 'Close' : '+ New admin user' }}
                </button>
                <button type="button" class="ghost" @click="logout">Sign out</button>
            </div>
        </header>

        <div v-if="flash.success" class="flash ok">{{ flash.success }}</div>
        <div v-if="flash.error" class="flash err">{{ flash.error }}</div>

        <section class="stats">
            <div class="stat"><strong>{{ stats.total }}</strong><span>Total</span></div>
            <div class="stat"><strong>{{ stats.admins }}</strong><span>Admins</span></div>
            <div class="stat"><strong>{{ stats.google }}</strong><span>Google</span></div>
            <div class="stat"><strong>{{ stats.guests }}</strong><span>Guests</span></div>
        </section>

        <article v-if="showCreate" class="card create">
            <h2>Create admin user</h2>
            <p class="muted">Password accounts only — these can sign in to this panel.</p>
            <form class="form-row" @submit.prevent="createUser">
                <label>
                    Name
                    <input v-model="createForm.name" type="text" required maxlength="120" />
                </label>
                <label>
                    Email
                    <input v-model="createForm.email" type="email" required />
                </label>
                <label>
                    Password (min 12 chars)
                    <input v-model="createForm.password" type="text" required minlength="12" autocomplete="new-password" />
                </label>
                <label class="check">
                    <input v-model="createForm.is_admin" type="checkbox" />
                    Admin access
                </label>
                <button type="submit" class="amber" :disabled="createForm.processing">Create</button>
            </form>
            <p v-if="createForm.errors.email" class="err-text">{{ createForm.errors.email }}</p>
            <p v-if="clientError" class="err-text">{{ clientError }}</p>
            <p v-if="createForm.errors.password" class="err-text">{{ createForm.errors.password }}</p>
        </article>

        <article class="card">
            <div class="toolbar">
                <div class="tabs">
                    <button
                        v-for="t in filterTabs"
                        :key="t.key"
                        type="button"
                        :class="['tab', { active: filter === t.key }]"
                        @click="setFilter(t.key)"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <form class="searchbox" @submit.prevent="applyFilters()">
                    <input v-model="search" type="search" placeholder="Search name or email…" />
                    <button type="submit" class="ghost">Search</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Provider</th>
                        <th>Devices</th>
                        <th>Joined</th>
                        <th class="right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users.data" :key="u.id">
                        <td>
                            <strong>{{ u.name }}</strong>
                            <span class="email">{{ u.email }}</span>
                        </td>
                        <td><span :class="['badge', badge(u).cls]">{{ badge(u).label }}</span></td>
                        <td>{{ u.auth_provider || '—' }}</td>
                        <td>{{ u.devices_count }}</td>
                        <td>{{ u.created_at?.slice(0, 10) || '—' }}</td>
                        <td class="right">
                            <template v-if="!u.is_owner">
                                <button
                                    v-if="u.auth_provider === 'password' || u.is_admin"
                                    type="button"
                                    :class="u.is_admin ? 'off' : 'on'"
                                    @click="toggleAdmin(u)"
                                >
                                    {{ u.is_admin ? 'Revoke admin' : 'Make admin' }}
                                </button>
                                <button
                                    v-if="u.auth_provider === 'password'"
                                    type="button"
                                    class="ghost"
                                    @click="resetTarget = u"
                                >
                                    Reset password
                                </button>
                                <button type="button" class="danger" @click="deleteUser(u)">Delete</button>
                            </template>
                            <span v-else class="muted">protected</span>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="6" class="empty">No users match.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="users.last_page > 1" class="pager">
                <button
                    type="button"
                    class="ghost"
                    :disabled="users.current_page <= 1"
                    @click="applyFilters(users.current_page - 1)"
                >
                    ← Prev
                </button>
                <span class="muted">Page {{ users.current_page }} / {{ users.last_page }} · {{ users.total }} users</span>
                <button
                    type="button"
                    class="ghost"
                    :disabled="users.current_page >= users.last_page"
                    @click="applyFilters(users.current_page + 1)"
                >
                    Next →
                </button>
            </div>
        </article>

        <div v-if="resetTarget" class="modal-backdrop" @click.self="resetTarget = null">
            <div class="modal">
                <h2>Reset password</h2>
                <p class="muted">{{ resetTarget.email }}</p>
                <form @submit.prevent="submitReset">
                    <label>
                        New password (min 12 chars)
                        <input v-model="resetPasswordValue" type="text" required minlength="12" autocomplete="new-password" />
                    </label>
                    <div class="modal-actions">
                        <button type="button" class="ghost" @click="resetTarget = null">Cancel</button>
                        <button type="submit" class="amber">Set password</button>
                    </div>
                </form>
            </div>
        </div>
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
button {
    border-radius: 8px;
    border: 1px solid #2a3441;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}
button:disabled {
    opacity: 0.4;
    cursor: default;
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
    background: rgb(242 166 63 / 0.15);
    color: #f2a63f;
}
.danger {
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
.flash.err {
    background: rgb(232 96 76 / 0.12);
    color: #e8604c;
}
.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}
.stat {
    background: #1b232d;
    border: 1px solid #2a3441;
    border-radius: 12px;
    padding: 12px 14px;
    display: grid;
}
.stat strong {
    font-size: 20px;
}
.stat span {
    color: #9faab5;
    font-size: 12px;
}
.card {
    background: #1b232d;
    border: 1px solid #2a3441;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 14px;
    overflow-x: auto;
}
.toolbar {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}
.tabs {
    display: flex;
    gap: 6px;
}
.tab {
    background: transparent;
    color: #9faab5;
}
.tab.active {
    background: #212b37;
    color: #f5f1e8;
    border-color: #f2a63f;
}
.searchbox {
    display: flex;
    gap: 6px;
}
input {
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
    font-size: 12.5px;
}
th,
td {
    text-align: left;
    padding: 9px 6px;
    border-bottom: 1px solid #2a3441;
    vertical-align: middle;
}
th {
    color: #9faab5;
    font-weight: 600;
}
td strong {
    display: block;
}
.email {
    color: #9faab5;
    font-size: 11.5px;
}
.right {
    text-align: right;
}
.right button {
    margin-inline-start: 6px;
}
.badge {
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.badge.owner {
    background: rgb(242 166 63 / 0.2);
    color: #f2a63f;
}
.badge.admin {
    background: rgb(79 190 141 / 0.15);
    color: #4fbe8d;
}
.badge.guest {
    background: rgb(159 170 181 / 0.15);
    color: #9faab5;
}
.badge.user {
    background: rgb(93 140 220 / 0.15);
    color: #7ba3e3;
}
.empty {
    text-align: center;
    color: #9faab5;
}
.pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
}
.create .form-row {
    display: grid;
    grid-template-columns: 1fr 1.2fr 1.2fr auto auto;
    gap: 10px;
    align-items: end;
    margin-top: 10px;
}
label {
    display: grid;
    gap: 5px;
    font-size: 12px;
    color: #9faab5;
}
label.check {
    grid-auto-flow: column;
    align-items: center;
    justify-content: start;
    gap: 8px;
    padding-bottom: 10px;
}
.err-text {
    color: #e8604c;
    font-size: 12px;
    margin: 8px 0 0;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgb(0 0 0 / 0.6);
    display: grid;
    place-items: center;
    z-index: 50;
}
.modal {
    background: #1b232d;
    border: 1px solid #2a3441;
    border-radius: 14px;
    padding: 20px;
    width: min(420px, 90vw);
}
.modal label {
    margin-top: 12px;
}
.modal input {
    width: 100%;
    box-sizing: border-box;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}
@media (max-width: 900px) {
    .create .form-row {
        grid-template-columns: 1fr;
        align-items: stretch;
    }
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
