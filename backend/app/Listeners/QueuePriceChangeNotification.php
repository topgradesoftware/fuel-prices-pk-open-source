<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\FuelPriceUpdated;
use App\Models\FuelPrice;
use App\Services\Notifications\NotificationService;

class QueuePriceChangeNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handle(FuelPriceUpdated $event): void
    {
        $updated = (int) ($event->summary['updated'] ?? 0);
        if ($updated < 1) {
            return;
        }

        $this->notifications->createAndQueue(NotificationType::PriceChange, [
            'title' => 'Fuel prices changed',
            'body' => $this->headline() ?? "{$updated} price(s) changed. Open Fuel Prices PK for details.",
            'data' => [
                'updated' => $updated,
                'created' => (int) ($event->summary['created'] ?? 0),
            ],
        ]);
    }

    /**
     * Human message with the headline national prices, e.g.
     * "Petrol Rs 315.80 (▼0.35) · Diesel Rs 360.06 (▲5.71)".
     */
    private function headline(): ?string
    {
        $parts = [];
        foreach (['petrol' => 'Petrol', 'hsd' => 'Diesel'] as $slug => $label) {
            $row = FuelPrice::query()
                ->whereNull('city_id')
                ->whereHas('fuelType', fn ($q) => $q->where('slug', $slug))
                ->whereHas('company', fn ($q) => $q->where('slug', 'pso'))
                ->first();
            if ($row === null) {
                continue;
            }

            $piece = sprintf('%s Rs %.2f', $label, (float) $row->price);
            if ($row->previous_price !== null) {
                $delta = round((float) $row->price - (float) $row->previous_price, 2);
                if (abs($delta) >= 0.01) {
                    $piece .= sprintf(' (%s%.2f)', $delta > 0 ? '▲' : '▼', abs($delta));
                }
            }
            $parts[] = $piece;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
