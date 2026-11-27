<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const CATEGORIES = [
        'fuel-news' => 'Fuel News',
        'petrol-prices' => 'Petrol Prices',
        'diesel' => 'Diesel',
        'lpg' => 'LPG',
        'hobc' => 'HOBC',
        'government' => 'Government Notifications',
        'oil-market' => 'Oil Market',
        'fuel-saving' => 'Fuel Saving Tips',
        'calculators' => 'Calculators',
        'guides' => 'Guides',
    ];

    protected $fillable = [
        'slug',
        'status',
        'title',
        'excerpt',
        'body',
        'category',
        'event_type',
        'content_event_id',
        'tags',
        'faq',
        'seo_payload',
        'link_recommendations',
        'author_name',
        'prompt_version',
        'template_version',
        'ai_provider',
        'ai_model',
        'meta_title',
        'meta_description',
        'og_image_path',
        'social_images',
        'reading_minutes',
        'is_featured',
        'is_trending',
        'view_count',
        'freshness_score',
        'last_refreshed_at',
        'human_edited_at',
        'approved_at',
        'approved_by',
        'source_fingerprint',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'faq' => 'array',
            'seo_payload' => 'array',
            'link_recommendations' => 'array',
            'social_images' => 'array',
            'is_featured' => 'boolean',
            'is_trending' => 'boolean',
            'published_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'human_edited_at' => 'datetime',
            'approved_at' => 'datetime',
            'reading_minutes' => 'integer',
            'view_count' => 'integer',
            'freshness_score' => 'integer',
        ];
    }

    public function contentEvent(): BelongsTo
    {
        return $this->belongsTo(ContentEvent::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ContentDistribution::class);
    }

    public function contentJobs(): HasMany
    {
        return $this->hasMany(ContentJob::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ContentAudit::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? Str::headline($this->category);
    }

    public function urlPath(): string
    {
        return '/guides/'.$this->slug;
    }

    /**
     * @return list<array{id:string,text:string}>
     */
    public function toc(): array
    {
        $cached = $this->seo_payload['toc'] ?? null;
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        if (! preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', (string) $this->body, $matches)) {
            return [];
        }

        $items = [];
        foreach ($matches[1] as $i => $raw) {
            $text = trim(html_entity_decode(strip_tags($raw)));
            if ($text === '') {
                continue;
            }
            $items[] = [
                'id' => 'section-'.($i + 1),
                'text' => $text,
            ];
        }

        return $items;
    }

    public function bodyWithAnchors(): string
    {
        $i = 0;

        return (string) preg_replace_callback(
            '/<h2([^>]*)>/i',
            static function ($m) use (&$i) {
                $i++;
                $attrs = $m[1];
                if (preg_match('/\bid\s*=/i', $attrs)) {
                    return $m[0];
                }

                return '<h2 id="section-'.$i.'"'.$attrs.'>';
            },
            (string) $this->body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCardArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'tags' => $this->tags ?? [],
            'reading_minutes' => $this->reading_minutes,
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'is_featured' => $this->is_featured,
            'is_trending' => $this->is_trending,
            'status' => $this->status,
            'event_type' => $this->event_type,
        ];
    }
}
