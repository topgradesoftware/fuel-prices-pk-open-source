<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineEvent extends Model
{
    protected $fillable = [
        'date',
        'category',
        'description',
        'source',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
