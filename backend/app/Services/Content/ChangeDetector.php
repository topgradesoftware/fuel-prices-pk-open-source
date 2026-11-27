<?php

namespace App\Services\Content;

use App\Models\ContentEvent;
use App\Models\FuelPrice;
use App\Models\FuelType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Detects meaningful price / market events after a sync.
 * Noise-level updates never create content events.
 */
class ChangeDetector
{
    public function __construct(
        private readonly ContentAuditLogger $audits,
    ) {}

    /**
     * @param  array<string, mixed>  $syncSummary
     * @return list<ContentEvent>
     */
    public function detectFromSync(array $syncSummary = []): array
    {
        if (! config('content.enabled')) {
            return [];
        }

        $watched = config('content.thresholds.watched_fuels', ['petrol', 'hsd']);
        $increaseMin = (float) config('content.thresholds.significant_increase', 2.0);
        $decreaseMin = (float) config('content.thresholds.significant_decrease', 2.0);
        $noise = (float) config('content.thresholds.minor_noise', 0.25);
        $dedupeHours = (int) config('content.dedupe_hours', 20);

        $moves = $this->collectNationalMoves($watched);
        $events = [];

        $significant = $moves->filter(function (array $row) use ($increaseMin, $decreaseMin, $noise) {
            $delta = (float) $row['delta'];
            if (abs($delta) < $noise) {
                return false;
            }

            return $delta >= $increaseMin || $delta <= -$decreaseMin;
        });

        if ($significant->isEmpty()) {
            Log::info('content.change_detector: no significant moves', [
                'moves' => $moves->count(),
                'updated' => $syncSummary['updated'] ?? null,
            ]);

            return [];
        }

        $netDelta = (float) $significant->sum('delta');
        $primary = $significant->sortByDesc(fn (array $r) => abs((float) $r['delta']))->first();
        $type = $netDelta >= 0 ? 'fuel_price_increase' : 'fuel_price_decrease';
        $severity = abs((float) $primary['delta']) >= max($increaseMin, $decreaseMin) * 2
            ? 'high'
            : 'normal';

        $day = now()->toDateString();
        $fingerprint = hash('sha256', $type.'|'.$day.'|'.collect($significant)->map(
            fn (array $r) => $r['fuel'].':'.round((float) $r['delta'], 2)
        )->sort()->implode('|'));

        if ($this->isDuplicate($fingerprint, $dedupeHours)) {
            return [];
        }

        $event = ContentEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'status' => ContentEvent::STATUS_DETECTED,
            'payload' => [
                'sync' => [
                    'updated' => $syncSummary['updated'] ?? 0,
                    'created' => $syncSummary['created'] ?? 0,
                    'message' => $syncSummary['message'] ?? null,
                ],
                'moves' => $significant->values()->all(),
                'primary_fuel' => $primary['fuel'],
                'primary_delta' => $primary['delta'],
                'primary_price' => $primary['price'],
                'previous_price' => $primary['previous'],
                'net_delta' => round($netDelta, 2),
                'effective_date' => $primary['effective_date'] ?? $day,
            ],
            'detected_at' => now(),
        ]);

        $this->audits->log('event_detected', event: $event, after: [
            'type' => $type,
            'severity' => $severity,
            'moves' => $significant->count(),
        ]);

        $events[] = $event;

        return $events;
    }

    /**
     * Manual / scheduled event factory (weekly, monthly, government, OPEC…).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordScheduledEvent(string $type, array $payload = [], string $severity = 'normal'): ?ContentEvent
    {
        $day = now()->format($type === 'monthly_summary' ? 'Y-m' : 'Y-W');
        $fingerprint = hash('sha256', $type.'|'.$day);

        if ($this->isDuplicate($fingerprint, 1)) {
            return null;
        }

        $event = ContentEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'status' => ContentEvent::STATUS_DETECTED,
            'payload' => $payload + ['period' => $day],
            'detected_at' => now(),
        ]);

        $this->audits->log('event_detected', event: $event, after: ['type' => $type, 'scheduled' => true]);

        return $event;
    }

    private function isDuplicate(string $fingerprint, int $withinHours): bool
    {
        return ContentEvent::query()
            ->where('fingerprint', $fingerprint)
            ->where('detected_at', '>=', now()->subHours(max(1, $withinHours)))
            ->exists();
    }

    /**
     * @param  list<string>  $watched
     * @return Collection<int, array{fuel:string,price:float,previous:float,delta:float,effective_date:?string}>
     */
    private function collectNationalMoves(array $watched): Collection
    {
        $out = collect();

        foreach ($watched as $slug) {
            $typeId = FuelType::query()->where('slug', $slug)->value('id');
            if ($typeId === null) {
                continue;
            }

            $current = FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->where('price', '>', 0)
                ->whereNull('city_id')
                ->whereHas('company', fn ($q) => $q->where('slug', 'pso'))
                ->orderByDesc('effective_date')
                ->first()
                ?? FuelPrice::query()
                    ->where('fuel_type_id', $typeId)
                    ->where('price', '>', 0)
                    ->whereNull('city_id')
                    ->orderByDesc('effective_date')
                    ->first();

            if ($current === null) {
                continue;
            }

            $price = (float) $current->price;
            $prev = $current->previous_price !== null
                ? (float) $current->previous_price
                : null;

            if ($prev === null || $prev <= 0) {
                continue;
            }

            $delta = round($price - $prev, 2);

            $out->push([
                'fuel' => $slug,
                'price' => $price,
                'previous' => $prev,
                'delta' => $delta,
                'effective_date' => optional($current->effective_date)?->toDateString()
                    ?? Carbon::now()->toDateString(),
            ]);
        }

        return $out;
    }
}
