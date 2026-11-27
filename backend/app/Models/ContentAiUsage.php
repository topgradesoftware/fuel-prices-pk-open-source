<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAiUsage extends Model
{
    protected $fillable = [
        'article_id',
        'content_event_id',
        'provider',
        'model',
        'event_type',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'period_month',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'estimated_cost_usd' => 'float',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
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
