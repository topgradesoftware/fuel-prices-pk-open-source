<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FuelPriceUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{updated: int, created: int, fetched: int, message?: string}  $summary
     */
    public function __construct(
        public readonly array $summary,
    ) {}
}
