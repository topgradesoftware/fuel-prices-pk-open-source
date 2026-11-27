<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicIndicator extends Model
{
    protected $fillable = [
        'indicator_type',
        'value',
        'period',
        'date',
        'source',
        'source_url',
        'is_revised',
        'revision_of_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'date' => 'date',
            'is_revised' => 'boolean',
        ];
    }
}
