<?php

namespace App\Console\Commands;

use App\Models\FuelPrice;
use App\Models\FuelType;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Data-quality watchdog: emails the owner when prices go stale, syncs keep
 * failing, sources disagree, or a parsed price jumps implausibly.
 */
class FuelWatchdogCommand extends Command
{
    protected $signature = 'fuel:watchdog';

    protected $description = 'Alert the owner about stale prices, failing syncs, or source disagreements';

    private const STALE_AFTER_HOURS = 48;

    private const SUSPICIOUS_JUMP_PCT = 15.0;

    public function handle(): int
    {
        $issues = [];

        foreach (['petrol', 'hsd'] as $slug) {
            $typeId = FuelType::query()->where('slug', $slug)->value('id');
            if ($typeId === null) {
                continue;
            }

            $latest = FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->whereNotNull('effective_date')
                ->max('effective_date');

            if ($latest === null || now()->diffInHours($latest) > self::STALE_AFTER_HOURS) {
                $issues[] = strtoupper($slug).' price is stale — latest effective date: '.($latest ?? 'none').'.';
            }

            $row = FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->whereNull('city_id')
                ->whereNotNull('previous_price')
                ->orderByDesc('effective_date')
                ->first();
            if ($row !== null && (float) $row->previous_price > 0) {
                $jump = abs((float) $row->price - (float) $row->previous_price) / (float) $row->previous_price * 100;
                if ($jump > self::SUSPICIOUS_JUMP_PCT) {
                    $issues[] = sprintf(
                        '%s jumped %.1f%% (Rs %.2f → Rs %.2f) — possible bad parse.',
                        strtoupper($slug),
                        $jump,
                        (float) $row->previous_price,
                        (float) $row->price,
                    );
                }
            }
        }

        $recent = SyncLog::query()->latest('id')->limit(3)->pluck('status');
        if ($recent->count() === 3 && $recent->every(fn ($s) => $s === 'failed')) {
            $issues[] = 'Last 3 fuel syncs FAILED — check sync logs.';
        }

        foreach ((array) Cache::get('fuel:source_disagreements', []) as $d) {
            $issues[] = sprintf(
                'Source disagreement on %s: %s vs %s (%.1f%% apart).',
                $d['key'] ?? '?',
                $d['a'] ?? '?',
                $d['b'] ?? '?',
                (float) ($d['diff_pct'] ?? 0),
            );
        }

        if ($issues === []) {
            $this->info('Watchdog: all clear.');

            return self::SUCCESS;
        }

        $this->warn('Watchdog issues: '.count($issues));
        foreach ($issues as $issue) {
            $this->line(' - '.$issue);
        }

        Log::error('fuel:watchdog issues', ['issues' => $issues]);
        $this->notifyOwner($issues);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $issues
     */
    private function notifyOwner(array $issues): void
    {
        $to = (string) env('WATCHDOG_ALERT_EMAIL', '');
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // At most one email per 12h per identical issue set.
        $key = 'fuel:watchdog:notified:'.md5(implode('|', $issues));
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, now()->addHours(12));

        try {
            Mail::raw(
                "Fuel Prices PK watchdog found problems:\n\n- ".implode("\n- ", $issues)
                    ."\n\nChecked at ".now()->toDateTimeString()." (server time).",
                function ($message) use ($to) {
                    $message->to($to)->subject('[Fuel Prices PK] Price data alert — '.now()->toDateString());
                },
            );
        } catch (\Throwable $e) {
            Log::warning('Watchdog email failed', ['error' => $e->getMessage()]);
        }
    }
}
