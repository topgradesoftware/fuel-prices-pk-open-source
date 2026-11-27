<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelCompany extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'short_name',
        'slug',
        'logo',
        'website',
        'customer_service',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FuelPrice::class, 'company_id');
    }
}
