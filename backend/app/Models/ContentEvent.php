<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentEvent extends Model
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_PROCESSED = 'processed';

    protected $fillable = [
        'type',
        'severity',
        'fingerprint',
        'status',
        'payload',
        'skip_reason',
        'detected_at',
        'planned_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'detected_at' => 'datetime',
            'planned_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ContentJob::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ContentAudit::class);
    }
}
