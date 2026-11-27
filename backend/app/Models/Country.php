<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'code',
        'name',
        'currency',
        'currency_symbol',
        'unit_volume',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(FuelCompany::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FuelPrice::class);
    }
}
