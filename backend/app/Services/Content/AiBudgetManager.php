<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentAiUsage;
use App\Models\ContentEvent;
use Illuminate\Support\Facades\Log;

/**
 * Hard monthly/daily AI spend caps, reserve floor, priorities, and cost protection.
 */
class AiBudgetManager
{
    public const PRIORITY_NONE = 'none';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public function periodMonth(?\DateTimeInterface $at = null): string
    {
        return ($at ? \Carbon\Carbon::instance($at) : now())->format('Y-m');
    }

    public function monthlyBudgetUsd(): float
    {
        return max(0.0, (float) config('content.ai.monthly_budget_usd', 10));
    }

    public function reserveUsd(): float
    {
        return max(0.0, (float) config('content.ai.reserve_usd', 2));
    }

    public function dailyLimit(): int
    {
        return max(0, (int) config('content.ai.daily_limit', 3));
    }

    public function estimatePerRequestUsd(): float
    {
        return max(0.0, (float) config('content.ai.est_cost_per_request_usd', 0.05));
    }

    public function maxCostPerRequestUsd(): float
    {
        return max(0.0, (float) config('content.ai.max_cost_per_request_usd', 0.25));
    }

    public function plentyUsd(): float
    {
        return max(0.0, (float) config('content.ai.plenty_usd', 5));
    }

    public function healthyUsd(): float
    {
        return max(0.0, (float) config('content.ai.healthy_usd', 5));
    }

    public function lowUsd(): float
    {
        return max(0.0, (float) config('content.ai.low_usd', 3));
    }

    public function usedThisMonthUsd(?string $month = null): float
    {
        $month ??= $this->periodMonth();

        return (float) ContentAiUsage::query()
            ->where('period_month', $month)
            ->where('status', 'success')
            ->sum('estimated_cost_usd');
    }

    public function requestsToday(): int
    {
        return (int) ContentAiUsage::query()
            ->where('status', 'success')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function requestsThisMonth(?string $month = null): int
    {
        $month ??= $this->periodMonth();

        return (int) ContentAiUsage::query()
            ->where('period_month', $month)
            ->where('status', 'success')
            ->count();
    }

    public function remainingUsd(?string $month = null): float
    {
        return max(0.0, round($this->monthlyBudgetUsd() - $this->usedThisMonthUsd($month), 4));
    }

    /**
     * Spendable budget above the end-of-month reserve.
     */
    public function spendableUsd(?string $month = null): float
    {
        return max(0.0, round($this->remainingUsd($month) - $this->reserveUsd(), 4));
    }

    /**
     * @return array{allowed: bool, reason?: string, priority?: string, estimated_cost_usd?: float}
     */
    public function canUseAi(?string $priority = self::PRIORITY_MEDIUM, ?float $estimatedCostUsd = null): array
    {
        $priority = strtolower((string) ($priority ?: self::PRIORITY_MEDIUM));
        $estimated = $estimatedCostUsd ?? $this->estimatePerRequestUsd();

        if ($priority === self::PRIORITY_NONE) {
            return ['allowed' => false, 'reason' => 'priority_none', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        $provider = (string) config('content.ai.provider', 'template');
        if (! in_array($provider, ['openai_compatible', 'openai', 'gemini'], true)) {
            return ['allowed' => false, 'reason' => 'provider_is_template', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        $apiKey = (string) config('content.ai.api_key');
        if ($apiKey === '') {
            return ['allowed' => false, 'reason' => 'api_key_missing', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        if ($this->monthlyBudgetUsd() <= 0) {
            return ['allowed' => false, 'reason' => 'monthly_budget_zero', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        $remaining = $this->remainingUsd();
        $reserve = $this->reserveUsd();

        // Reserve floor: keep $2 (default) unspent for the rest of the month.
        if ($remaining <= $reserve) {
            return ['allowed' => false, 'reason' => 'reserve_floor', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        if ($this->spendableUsd() < $estimated) {
            return ['allowed' => false, 'reason' => 'monthly_budget_exhausted', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        // Cost protection — reject unexpectedly expensive single requests.
        $maxCost = $this->maxCostPerRequestUsd();
        if ($maxCost > 0 && $estimated > $maxCost) {
            return ['allowed' => false, 'reason' => 'request_over_cost_limit', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        $daily = $this->dailyLimit();
        if ($daily > 0 && $this->requestsToday() >= $daily) {
            return ['allowed' => false, 'reason' => 'daily_limit_reached', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        // Low-priority (guide refresh): only when plenty of budget remains.
        if ($priority === self::PRIORITY_LOW && $remaining < $this->plentyUsd()) {
            return ['allowed' => false, 'reason' => 'priority_low_needs_plenty', 'priority' => $priority, 'estimated_cost_usd' => $estimated];
        }

        return [
            'allowed' => true,
            'priority' => $priority,
            'estimated_cost_usd' => $estimated,
        ];
    }

    /**
     * Traffic-light status for the admin dashboard.
     *
     * @return array{level: string, label: string, message: string}
     */
    public function statusBanner(?array $gate = null): array
    {
        $gate ??= $this->canUseAi(self::PRIORITY_HIGH);
        $remaining = $this->remainingUsd();

        if (! ($gate['allowed'] ?? false)) {
            $reason = $gate['reason'] ?? 'unavailable';

            return [
                'level' => 'red',
                'label' => 'AI disabled',
                'message' => 'Using template fallback — '.$this->humanReason($reason).'.',
            ];
        }

        if ($remaining < $this->lowUsd()) {
            return [
                'level' => 'yellow',
                'label' => 'AI budget low',
                'message' => sprintf('Less than $%.2f remaining (reserve $%.2f protected).', $this->lowUsd(), $this->reserveUsd()),
            ];
        }

        if ($remaining > $this->healthyUsd()) {
            return [
                'level' => 'green',
                'label' => 'AI budget healthy',
                'message' => sprintf('More than $%.2f remaining this month.', $this->healthyUsd()),
            ];
        }

        return [
            'level' => 'yellow',
            'label' => 'AI budget watch',
            'message' => sprintf('$%.2f remaining — approaching low threshold.', $remaining),
        ];
    }

    private function humanReason(string $reason): string
    {
        return match ($reason) {
            'reserve_floor' => 'monthly reserve floor reached',
            'monthly_budget_exhausted' => 'spendable budget exhausted',
            'daily_limit_reached' => 'daily AI request limit reached',
            'api_key_missing' => 'API key not configured',
            'provider_is_template' => 'provider set to template',
            'request_over_cost_limit' => 'estimated request cost over limit',
            'priority_low_needs_plenty' => 'low-priority work needs more budget headroom',
            'priority_none' => 'content type never uses AI',
            default => $reason,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $status,
        ?Article $article = null,
        ?ContentEvent $event = null,
        string $provider = 'openai_compatible',
        ?string $model = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        ?float $costUsd = null,
        array $meta = [],
    ): ContentAiUsage {
        $total = max(0, $promptTokens + $completionTokens);
        $estimated = $costUsd;
        if ($estimated === null) {
            $estimated = $status === 'success'
                ? $this->estimateCostFromTokens($promptTokens, $completionTokens)
                : 0.0;
        }

        $row = ContentAiUsage::query()->create([
            'article_id' => $article?->id,
            'content_event_id' => $event?->id ?? $article?->content_event_id,
            'provider' => $provider,
            'model' => $model ?? config('content.ai.model'),
            'event_type' => $article?->event_type ?? $event?->type,
            'status' => $status,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $total,
            'estimated_cost_usd' => round($estimated, 6),
            'period_month' => $this->periodMonth(),
            'meta' => $meta,
        ]);

        if (in_array($status, ['blocked_budget', 'fallback'], true)) {
            Log::info('content.ai.budget gate', [
                'status' => $status,
                'reason' => $meta['reason'] ?? null,
                'remaining' => $this->remainingUsd(),
                'reserve' => $this->reserveUsd(),
            ]);
        }

        return $row;
    }

    public function estimateCostFromTokens(int $promptTokens, int $completionTokens): float
    {
        if ($promptTokens <= 0 && $completionTokens <= 0) {
            return $this->estimatePerRequestUsd();
        }

        $inPerM = (float) config('content.ai.input_usd_per_million', 0.15);
        $outPerM = (float) config('content.ai.output_usd_per_million', 0.60);

        return round(($promptTokens / 1_000_000) * $inPerM + ($completionTokens / 1_000_000) * $outPerM, 6);
    }

    /**
     * Admin popup payload when AI is blocked or Gemini free-tier/quota failed recently.
     *
     * @return array{show: bool, title: string, message: string, reason: string, level: string}|null
     */
    public function adminLimitAlert(): ?array
    {
        $gate = $this->canUseAi(self::PRIORITY_HIGH);
        if (! ($gate['allowed'] ?? false)) {
            $reason = (string) ($gate['reason'] ?? 'unavailable');

            return [
                'show' => true,
                'level' => 'red',
                'reason' => $reason,
                'title' => match ($reason) {
                    'daily_limit_reached' => 'Daily AI limit reached',
                    'monthly_budget_exhausted', 'reserve_floor' => 'Monthly AI budget limit reached',
                    'api_key_missing' => 'AI API key missing',
                    default => 'AI limit reached',
                },
                'message' => 'Gemini drafts are paused — '.$this->humanReason($reason)
                    .'. New automated articles will use the template fallback until the limit resets. Daily fuel posts are unaffected.',
            ];
        }

        $recentQuota = ContentAiUsage::query()
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('status', ['fallback', 'blocked_budget'])
            ->latest('id')
            ->limit(8)
            ->get(['meta', 'status']);

        foreach ($recentQuota as $row) {
            $metaReason = strtolower((string) data_get($row->meta, 'reason', ''));
            if ($metaReason === '' || ! preg_match('/429|quota|resource.?exhausted|rate.?limit|exceeded/i', $metaReason)) {
                continue;
            }

            return [
                'show' => true,
                'level' => 'red',
                'reason' => 'provider_quota',
                'title' => 'Gemini free-tier / API quota reached',
                'message' => 'Google rejected recent AI requests (quota or rate limit). The site fell back to templates. Check AI Studio → Usage / Rate Limit, or set up billing later. Daily fuel posts still publish normally.',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $month = $this->periodMonth();
        $budget = $this->monthlyBudgetUsd();
        $used = $this->usedThisMonthUsd($month);
        $remaining = max(0.0, round($budget - $used, 4));
        $requests = $this->requestsThisMonth($month);
        $today = $this->requestsToday();
        $dailyLimit = $this->dailyLimit();
        $gate = $this->canUseAi(self::PRIORITY_HIGH);
        $banner = $this->statusBanner($gate);
        $fallbacks = (int) ContentAiUsage::query()
            ->where('period_month', $month)
            ->whereIn('status', ['fallback', 'blocked_budget'])
            ->count();

        $daysLeft = max(1, (int) now()->endOfMonth()->diffInDays(now()) + 1);
        $burnPerDay = $used / max(1, (int) now()->day);
        $projected = round($burnPerDay * now()->daysInMonth, 2);

        return [
            'period_month' => $month,
            'monthly_budget_usd' => $budget,
            'reserve_usd' => $this->reserveUsd(),
            'spendable_usd' => $this->spendableUsd($month),
            'used_usd' => round($used, 4),
            'remaining_usd' => $remaining,
            'used_percent' => $budget > 0 ? round(min(100, ($used / $budget) * 100), 1) : 0.0,
            'requests_this_month' => $requests,
            'requests_today' => $today,
            'daily_limit' => $dailyLimit,
            'daily_remaining' => $dailyLimit > 0 ? max(0, $dailyLimit - $today) : null,
            'fallback_count' => $fallbacks,
            'ai_allowed' => $gate['allowed'],
            'ai_block_reason' => $gate['reason'] ?? null,
            'status' => $banner,
            'provider' => config('content.ai.provider'),
            'fallback_provider' => config('content.ai.fallback', 'template'),
            'est_cost_per_request_usd' => $this->estimatePerRequestUsd(),
            'max_cost_per_request_usd' => $this->maxCostPerRequestUsd(),
            'plenty_usd' => $this->plentyUsd(),
            'estimated_next_bill_usd' => min($budget, max($used, $projected)),
            'projected_month_usd' => $projected,
            'days_left_in_month' => $daysLeft,
        ];
    }
}
