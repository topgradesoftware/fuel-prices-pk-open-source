<?php

namespace App\Services;

class PriceBreakdownService
{
    /**
     * Calculate the deterministic components of a price change between two dates.
     * Requires granular data: import_parity_price, freight, distribution_margin, petroleum_levy, gst, exchange_rate.
     */
    public function calculateDifference(array $oldPrice, array $newPrice): array
    {
        // Calculate the total net change
        $totalChange = ($newPrice['retail_price'] ?? 0) - ($oldPrice['retail_price'] ?? 0);

        // Calculate individual component contributions to the change
        $breakdown = [
            'import_parity_price' => ($newPrice['import_parity_price'] ?? 0) - ($oldPrice['import_parity_price'] ?? 0),
            'freight' => ($newPrice['freight'] ?? 0) - ($oldPrice['freight'] ?? 0),
            'distribution_margin' => ($newPrice['distribution_margin'] ?? 0) - ($oldPrice['distribution_margin'] ?? 0),
            'petroleum_levy' => ($newPrice['petroleum_levy'] ?? 0) - ($oldPrice['petroleum_levy'] ?? 0),
            'gst' => ($newPrice['gst'] ?? 0) - ($oldPrice['gst'] ?? 0),
        ];

        // Ensure the components roughly sum up to the total change (sanity check)
        $componentSum = array_sum($breakdown);
        $unexplained = $totalChange - $componentSum;

        return [
            'old_date' => $oldPrice['effective_date'] ?? null,
            'new_date' => $newPrice['effective_date'] ?? null,
            'total_change' => round($totalChange, 2),
            'components' => array_map(fn($v) => round($v, 2), $breakdown),
            'unexplained_variance' => round($unexplained, 2),
            'disclaimer' => 'Calculated deterministically based on OGRA public formula build-ups.'
        ];
    }
}
