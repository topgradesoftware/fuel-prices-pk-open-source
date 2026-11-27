<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentStatistic extends Model
{
    protected $fillable = [
        'government_period_id',
        'metric_name',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
        ];
    }
}
