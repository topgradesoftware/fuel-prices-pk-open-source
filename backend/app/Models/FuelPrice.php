<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelPrice extends Model
{
    protected $fillable = [
        'country_id',
        'company_id',
        'city_id',
        'fuel_type_id',
        'price',
        'previous_price',
        'currency',
        'unit',
        'effective_date',
        'source',
        'source_url',
        'verified',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'effective_date' => 'date',
            'verified' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(FuelCompany::class, 'company_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class, 'fuel_type_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FuelPriceHistory::class);
    }
}
