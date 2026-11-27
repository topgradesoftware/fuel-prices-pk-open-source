<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminAiLimitPopup from '../../Components/AdminAiLimitPopup.vue';

type StatMap = Record<string, number>;

type ArticleRow = {
    id: number;
    slug: string;
    title: string;
    status: string;
    event_type: string | null;
    ai_provider: string | null;
    freshness_score: number | null;
    published_at: string | null;
    updated_at: string | null;
    attention: boolean;
};

type EventItem = {
    id: number;
    type: string;
    severity: string;
    status: string;
    skip_reason: string | null;
    detected_at: string | null;
};

type DistItem = {
    id: number;
    channel: string;
    status: string;
    article: string | null;
    slug: string | null;
    last_error: string | null;
    sent_at: string | null;
};

type FailedJob = {
    id: number;
    article_id: number | null;
    stage: string;
    error: string | null;
    finished_at: string | null;
};

type AuditItem = {
    id: number;
    action: string;
    article_id: number | null;
    actor_type: string;
    created_at: string | null;
};

type AiBudget = {
    period_month: string;
    monthly_budget_usd: number;
    reserve_usd: number;
    spendable_usd: number;
    used_usd: number;
    remaining_usd: number;
    used_percent: number;
    requests_this_month: number;
    requests_today: number;
    daily_limit: number;
    daily_remaining: number | null;
    fallback_count: number;
    ai_allowed: boolean;
    ai_block_reason: string | null;
    status: { level: string; label: string; message: string };
    provider: string;
    fallback_provider: string;
    est_cost_per_request_usd: number;
    max_cost_per_request_usd: number;
    plenty_usd: number;
    estimated_next_bill_usd: number;
    projected_month_usd: number;
    days_left_in_month: number;
};

type RuleCard = {
    type: string;
    label: string;
    use_ai: boolean;
    review_required: boolean;
    auto_publish: boolean;
    ai_priority: string;
};

const FILTERS = [
    { key: 'pending_review', label: 'Pending Review' },
    { key: 'requires_attention', label: 'Requires Attention' },
    { key: 'failed', label: 'Failed' },
    { key: 'published', label: 'Published' },
    { key: 'archived', label: 'Archived' },
    { key: 'ai_generated', label: 'AI Generated' },
    { key: 'template_generated', label: 'Template Generated' },
    { key: 'all', label: 'All automated' },
] as const;

const props = defineProps<{
    stats: StatMap;
    articles: ArticleRow[];
    filter: string;
    filter_counts: Record<string, number>;
    events: EventItem[];
    distributions: DistItem[];
    failed_jobs: FailedJob[];
    audits: AuditItem[];
    ai_budget: AiBudget;
    automation_rules: RuleCard[];
    config: {
        enabled: boolean;
        auto_publish: boolean;
        review_required: boolean;
        pipeline_mode: string;
        ai_provider: string;
        ai_fallback: string;
        prompt_version: string;
        template_version: string;
        daily_auto_publish: boolean;
    };
    auth_user: { name?: string; email?: string; is_owner?: boolean } | null;
}>();

const budgetBarWidth = computed(() => `${Math.min(100, props.ai_budget.used_percent)}%`);

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string }) ?? {});

function setFilter(key: string) {
    router.get('/admin/automation', { filter: key }, { preserveState: true, replace: true });
}

function detect() {
    router.post('/admin/automation/detect');
}

function publish(id: number) {
    router.post(`/admin/automation/articles/${id}/publish`);
}

function archive(id: number) {
    router.post(`/admin/automation/articles/${id}/archive`);
}

function regenerate(id: number) {
    router.post(`/admin/automation/articles/${id}/regenerate`);
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
                <p class="eyebrow">Content automation</p>
                <h1>Publishing control center</h1>
                <p class="muted">
                    {{ auth_user?.email }} · mode {{ config.pipeline_mode }} · AI {{ config.ai_provider }} → fallback
                    {{ config.ai_fallback }} · daily auto-publish
                    {{ config.daily_auto_publish ? 'ON' : 'OFF' }}
                    <span v-if="!config.enabled"> · DISABLED</span>
                </p>
            </div>
            <div class="actions">
                <a href="/admin/ops">Ops</a>
                <a href="/guides">Guides</a>
                <button type="button" class="amber" @click="detect">Detect + plan now</button>
                <button type="button" class="ghost" @click="logout">Sign out</button>
            </div>
        </header>

        <div v-if="flash.success" class="flash ok">{{ flash.success }}</div>
        <div v-if="flash.error" class="flash err">{{ flash.error }}</div>

        <div
            class="status-banner"
            :class="ai_budget.status?.level || 'red'"
            role="status"
        >
            <span class="dot" aria-hidden="true">{{
                ai_budget.status?.level === 'green' ? '🟢' : ai_budget.status?.level === 'yellow' ? '🟡' : '🔴'
            }}</span>
            <div>
                <strong>{{ ai_budget.status?.label || 'AI status unknown' }}</strong>
                <p>{{ ai_budget.status?.message }}</p>
            </div>
        </div>

        <section class="budget card">
            <div class="budget-head">
                <div>
                    <h2>AI Budget Manager</h2>
                    <p class="muted">
                        {{ ai_budget.period_month }} · reserve ${{ ai_budget.reserve_usd.toFixed(2) }} protected · max
                        ${{ ai_budget.max_cost_per_request_usd.toFixed(2) }}/request · low-priority needs ≥${{
                            ai_budget.plenty_usd.toFixed(0)
                        }}
                    </p>
                </div>
            </div>
            <div class="bar" aria-hidden="true">
                <span :style="{ width: budgetBarWidth }"></span>
            </div>
            <div class="budget-grid">
                <div>
                    <strong>${{ ai_budget.monthly_budget_usd.toFixed(2) }}</strong>
                    <span>Monthly budget</span>
                </div>
                <div>
                    <strong>${{ ai_budget.used_usd.toFixed(4) }}</strong>
                    <span>Used this month</span>
                </div>
                <div>
                    <strong>${{ ai_budget.remaining_usd.toFixed(4) }}</strong>
                    <span>Remaining</span>
                </div>
                <div>
                    <strong>${{ ai_budget.spendable_usd.toFixed(4) }}</strong>
                    <span>Spendable (after reserve)</span>
                </div>
                <div>
                    <strong>{{ ai_budget.requests_this_month }}</strong>
                    <span>AI requests</span>
                </div>
                <div>
                    <strong>{{ ai_budget.requests_today }}/{{ ai_budget.daily_limit }}</strong>
                    <span>Today</span>
                </div>
                <div>
                    <strong>${{ ai_budget.estimated_next_bill_usd.toFixed(2) }}</strong>
                    <span>Est. next bill</span>
                </div>
                <div>
                    <strong>{{ ai_budget.fallback_count }}</strong>
                    <span>Fallbacks / blocked</span>
                </div>
            </div>
        </section>

        <section class="rules card">
            <h2>Automation rules (priority order)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Content type</th>
                        <th>Priority</th>
                        <th>AI</th>
                        <th>Review</th>
                        <th>Auto publish</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in automation_rules" :key="r.type">
                        <td>{{ r.label }}</td>
                        <td class="prio" :data-p="r.ai_priority">{{ r.ai_priority }}</td>
                        <td>{{ r.use_ai ? 'Yes' : 'No' }}</td>
                        <td>{{ r.review_required ? 'Yes' : 'No' }}</td>
                        <td>{{ r.auto_publish ? 'Yes' : 'No' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="stats">
            <div class="stat">
                <strong>{{ stats.articles_generated }}</strong>
                <span>Generated</span>
            </div>
            <div class="stat">
                <strong>{{ stats.articles_approved }}</strong>
                <span>Approved</span>
            </div>
            <div class="stat">
                <strong>{{ stats.approval_rate }}%</strong>
                <span>Approval rate</span>
            </div>
            <div class="stat">
                <strong>{{ stats.avg_review_hours }}h</strong>
                <span>Avg review time</span>
            </div>
            <div class="stat">
                <strong>{{ stats.avg_publish_minutes }}m</strong>
                <span>Publish latency</span>
            </div>
            <div class="stat">
                <strong>{{ stats.distribution_success_rate }}%</strong>
                <span>Dist. success</span>
            </div>
            <div class="stat">
                <strong>{{ stats.ai_failures }}</strong>
                <span>AI failures</span>
            </div>
            <div class="stat">
                <strong>{{ stats.template_fallback_count }}</strong>
                <span>Template fallbacks</span>
            </div>
            <div class="stat">
                <strong>{{ stats.drafts_awaiting_review }}</strong>
                <span>Awaiting review</span>
            </div>
            <div class="stat">
                <strong>{{ stats.published_today }}</strong>
                <span>Published today</span>
            </div>
            <div class="stat">
                <strong>{{ stats.indexnow_sent_today }}</strong>
                <span>IndexNow today</span>
            </div>
            <div class="stat">
                <strong>{{ stats.avg_pipeline_seconds }}s</strong>
                <span>Draft latency</span>
            </div>
        </section>

        <nav class="filters" aria-label="Article filters">
            <button
                v-for="f in FILTERS"
                :key="f.key"
                type="button"
                :class="['chip', filter === f.key ? 'active' : '']"
                @click="setFilter(f.key)"
            >
                {{ f.label }}
                <em>{{ filter_counts[f.key] ?? 0 }}</em>
            </button>
        </nav>

        <section class="grid">
            <article class="card wide">
                <h2>Articles · {{ filter.replace(/_/g, ' ') }}</h2>
                <p class="muted">Approve only after a quick editorial pass. Auto-publish is off.</p>
                <ul>
                    <li v-for="a in articles" :key="a.id" :class="{ attention: a.attention }">
                        <div>
                            <strong>#{{ a.id }} · {{ a.status }} <span v-if="a.attention">· attention</span></strong>
                            <span>{{ a.title }}</span>
                            <small>
                                {{ a.event_type || '—' }} · {{ a.ai_provider || '—' }}
                                <template v-if="a.published_at"> · {{ a.published_at }}</template>
                                <template v-else> · {{ a.updated_at }}</template>
                            </small>
                        </div>
                        <div class="row-actions">
                            <a v-if="a.status === 'published'" :href="`/guides/${a.slug}`" target="_blank" rel="noopener"
                                >View</a
                            >
                            <button
                                v-if="a.status !== 'published' && a.status !== 'archived'"
                                type="button"
                                class="on"
                                @click="publish(a.id)"
                            >
                                Publish
                            </button>
                            <button type="button" class="ghost" @click="regenerate(a.id)">Regen</button>
                            <button
                                v-if="a.status !== 'archived'"
                                type="button"
                                class="off"
                                @click="archive(a.id)"
                            >
                                Archive
                            </button>
                        </div>
                    </li>
                    <li v-if="!articles.length" class="empty">Nothing in this filter.</li>
                </ul>
            </article>

            <article class="card">
                <h2>Change events</h2>
                <ul>
                    <li v-for="e in events" :key="e.id">
                        <div>
                            <strong>{{ e.type }}</strong>
                            <span>{{ e.status }} · {{ e.severity }}</span>
                            <small
                                >{{ e.detected_at }}
                                <template v-if="e.skip_reason">· {{ e.skip_reason }}</template></small
                            >
                        </div>
                    </li>
                    <li v-if="!events.length" class="empty">No events yet.</li>
                </ul>
            </article>

            <article class="card">
                <h2>Distribution</h2>
                <ul>
                    <li v-for="d in distributions" :key="d.id">
                        <div>
                            <strong>{{ d.channel }} · {{ d.status }}</strong>
                            <span>{{ d.article || '—' }}</span>
                            <small v-if="d.last_error">{{ d.last_error }}</small>
                            <small v-else>{{ d.sent_at || 'queued' }}</small>
                        </div>
                    </li>
                    <li v-if="!distributions.length" class="empty">Nothing distributed yet.</li>
                </ul>
            </article>

            <article class="card">
                <h2>Failed stages</h2>
                <ul>
                    <li v-for="j in failed_jobs" :key="j.id">
                        <div>
                            <strong>#{{ j.article_id }} · {{ j.stage }}</strong>
                            <span>{{ j.error }}</span>
                            <small>{{ j.finished_at }}</small>
                        </div>
                    </li>
                    <li v-if="!failed_jobs.length" class="empty">No failures.</li>
                </ul>
            </article>

            <article class="card">
                <h2>Audit log</h2>
                <ul>
                    <li v-for="a in audits" :key="a.id">
                        <div>
                            <strong>{{ a.action }}</strong>
                            <span>article {{ a.article_id ?? '—' }} · {{ a.actor_type }}</span>
                            <small>{{ a.created_at }}</small>
                        </div>
                    </li>
                    <li v-if="!audits.length" class="empty">No audits yet.</li>
                </ul>
            </article>
        </section>
    </div>
</template>

<style scoped>
.wrap {
    min-height: 100vh;
    padding: 1.25rem clamp(1rem, 3vw, 2rem) 3rem;
    background: linear-gradient(165deg, #0f1720 0%, #1a2430 45%, #121a22 100%);
    color: #e8eef4;
    font-family: 'Segoe UI', system-ui, sans-serif;
}
.top {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.25rem;
}
.eyebrow {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.72rem;
    color: #f2a63f;
}
h1 {
    margin: 0.2rem 0;
    font-size: clamp(1.4rem, 3vw, 1.9rem);
}
.muted {
    color: #9aa8b5;
    margin: 0;
    font-size: 0.9rem;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.actions a {
    color: #c5d0da;
    text-decoration: none;
    padding: 0.45rem 0.7rem;
}
button {
    border: 0;
    border-radius: 8px;
    padding: 0.5rem 0.85rem;
    cursor: pointer;
    font-weight: 600;
}
.amber {
    background: #f2a63f;
    color: #1a1208;
}
.ghost {
    background: transparent;
    color: #c5d0da;
    border: 1px solid #3a4654;
}
.on {
    background: #1f6f4a;
    color: #eafff4;
}
.off {
    background: #5a3030;
    color: #ffdede;
}
.flash {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.flash.ok {
    background: #163528;
    color: #b6f0d0;
}
.flash.err {
    background: #3a1c1c;
    color: #ffc9c9;
}
.status-banner {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.9rem 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    border: 1px solid transparent;
}
.status-banner p {
    margin: 0.2rem 0 0;
    font-size: 0.88rem;
    opacity: 0.9;
}
.status-banner.green {
    background: rgba(46, 125, 80, 0.22);
    border-color: #2e7d50;
}
.status-banner.yellow {
    background: rgba(242, 166, 63, 0.14);
    border-color: #f2a63f;
}
.status-banner.red {
    background: rgba(160, 50, 50, 0.22);
    border-color: #c45c5c;
}
.status-banner .dot {
    font-size: 1.1rem;
    line-height: 1.4;
}
.budget,
.rules {
    margin-bottom: 1rem;
}
.prio {
    text-transform: uppercase;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.prio[data-p='high'] {
    color: #8be0b0;
}
.prio[data-p='medium'] {
    color: #f2a63f;
}
.prio[data-p='low'] {
    color: #9aa8b5;
}
.prio[data-p='none'] {
    color: #6b7785;
}
.budget-head {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-start;
}
.ok-text {
    color: #8be0b0;
}
.warn-text {
    color: #f2a63f;
}
.bar {
    height: 8px;
    background: #24303c;
    border-radius: 999px;
    overflow: hidden;
    margin: 0.85rem 0 1rem;
}
.bar span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #f2a63f, #e07a2f);
}
.budget-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.65rem;
}
.budget-grid strong {
    display: block;
    font-size: 1.1rem;
}
.budget-grid span {
    color: #9aa8b5;
    font-size: 0.75rem;
}
.rules table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.75rem;
    font-size: 0.85rem;
}
.rules th,
.rules td {
    text-align: left;
    padding: 0.45rem 0.35rem;
    border-top: 1px solid #24303c;
}
.rules th {
    color: #9aa8b5;
    font-weight: 600;
}
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.stat {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid #2a3542;
    border-radius: 10px;
    padding: 0.85rem;
}
.stat strong {
    display: block;
    font-size: 1.2rem;
}
.stat span {
    color: #9aa8b5;
    font-size: 0.75rem;
}
.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-bottom: 1.1rem;
}
.chip {
    background: #1a2430;
    color: #c5d0da;
    border: 1px solid #2a3542;
    border-radius: 999px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
}
.chip.active {
    background: #f2a63f;
    color: #1a1208;
    border-color: #f2a63f;
}
.chip em {
    font-style: normal;
    opacity: 0.75;
    margin-left: 0.35rem;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}
.card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid #2a3542;
    border-radius: 12px;
    padding: 1rem 1.1rem;
}
.card.wide {
    grid-column: 1 / -1;
}
.card h2 {
    margin: 0 0 0.25rem;
    font-size: 1.05rem;
    text-transform: capitalize;
}
.card ul {
    list-style: none;
    margin: 0.75rem 0 0;
    padding: 0;
    display: grid;
    gap: 0.65rem;
}
.card li {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0;
    border-top: 1px solid #24303c;
}
.card li:first-child {
    border-top: 0;
}
.card li.attention {
    background: rgba(242, 166, 63, 0.06);
    margin: 0 -0.5rem;
    padding: 0.65rem 0.5rem;
    border-radius: 8px;
}
.card li strong {
    display: block;
    font-size: 0.9rem;
}
.card li span,
.card li small {
    display: block;
    color: #9aa8b5;
    font-size: 0.8rem;
    margin-top: 0.15rem;
}
.card a {
    color: #f2a63f;
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 600;
}
.row-actions {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: stretch;
}
.row-actions button {
    font-size: 0.75rem;
    padding: 0.35rem 0.55rem;
    white-space: nowrap;
}
.empty {
    color: #9aa8b5;
    font-size: 0.85rem;
}
</style>
