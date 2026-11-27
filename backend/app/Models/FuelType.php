<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'unit',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FuelPrice::class, 'fuel_type_id');
    }
}
