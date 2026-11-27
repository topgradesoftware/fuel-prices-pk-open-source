<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAudit extends Model
{
    protected $fillable = [
        'article_id',
        'content_event_id',
        'actor_type',
        'actor_id',
        'action',
        'before',
        'after',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'meta' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ContentEvent::class, 'content_event_id');
    }
}
