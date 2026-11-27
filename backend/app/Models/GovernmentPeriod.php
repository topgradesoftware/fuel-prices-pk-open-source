<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernmentPeriod extends Model
{
    protected $fillable = [
        'government_name',
        'pm_name',
        'start_date',
        'end_date',
        'type',
        'source',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
