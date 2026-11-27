<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelPriceHistory extends Model
{
    protected $fillable = [
        'fuel_price_id',
        'previous_price',
        'new_price',
        'change_amount',
        'change_percentage',
        'effective_at',
        'source',
        'source_url',
        'notification_ref',
    ];

    protected function casts(): array
    {
        return [
            'previous_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'change_percentage' => 'decimal:4',
            'effective_at' => 'datetime',
        ];
    }

    public function fuelPrice(): BelongsTo
    {
        return $this->belongsTo(FuelPrice::class);
    }
}
