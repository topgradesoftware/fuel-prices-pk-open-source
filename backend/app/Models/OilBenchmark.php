<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OilBenchmark extends Model
{
    protected $fillable = [
        'benchmark_type',
        'price',
        'date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'date' => 'date',
        ];
    }
}
