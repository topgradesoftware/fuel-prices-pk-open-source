<?php

return [

    'enabled' => (bool) env('CONTENT_AUTOMATION_ENABLED', true),

    'pipeline_mode' => env('CONTENT_PIPELINE_MODE', 'inline'),

    'thresholds' => [
        'significant_increase' => (float) env('CONTENT_THRESHOLD_INCREASE', 2.0),
        'significant_decrease' => (float) env('CONTENT_THRESHOLD_DECREASE', 2.0),
        'minor_noise' => (float) env('CONTENT_THRESHOLD_NOISE', 0.25),
        'watched_fuels' => ['petrol', 'hsd', 'lpg', 'hobc', 'kerosene'],
    ],

    /*
    | Global editorial defaults (per-type rules below take precedence).
    */
    'review_required' => (bool) env('CONTENT_REVIEW_REQUIRED', true),
    'auto_submit_review' => (bool) env('CONTENT_AUTO_SUBMIT_REVIEW', true),
    'auto_publish' => (bool) env('CONTENT_AUTO_PUBLISH', false),
    'max_articles_per_sync' => (int) env('CONTENT_MAX_ARTICLES_PER_SYNC', 2),
    'dedupe_hours' => (int) env('CONTENT_DEDUPE_HOURS', 20),

    /*
    |--------------------------------------------------------------------------
    | AI provider + budget manager
    |--------------------------------------------------------------------------
    | Daily price posts stay on templates ($0). Weekly/monthly/guides may use AI
    | until the monthly budget or daily request cap is hit, then fall back.
    */
    'ai' => [
        // gemini | openai_compatible | template
        'provider' => env('CONTENT_AI_PROVIDER', env('CONTENT_PROVIDER', 'template')),
        'fallback' => env('CONTENT_AI_FALLBACK', 'template'),
        // gemini-3.5-flash-lite: reliable JSON + low latency for weekly/monthly.
        // Full gemini-3.5-flash can hang/think too long on free tiers; 2.5 is blocked for some new keys.
        'model' => env('CONTENT_AI_MODEL') ?: (
            in_array(env('CONTENT_AI_PROVIDER', 'template'), ['gemini'], true)
                ? 'gemini-3.5-flash-lite'
                : 'gpt-4o-mini'
        ),
        'api_key' => env('CONTENT_AI_API_KEY', env('OPENAI_API_KEY', env('GEMINI_API_KEY'))),
        'base_url' => rtrim((string) env('CONTENT_AI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'gemini_base_url' => rtrim((string) env('CONTENT_AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'timeout' => (int) env('CONTENT_AI_TIMEOUT', 90),
        'monthly_budget_usd' => (float) env('CONTENT_AI_MONTHLY_BUDGET_USD', 10),
        // Keep this unspent so the month cannot be drained to $0.
        'reserve_usd' => (float) env('CONTENT_AI_RESERVE_USD', 2),
        'daily_limit' => (int) env('CONTENT_AI_DAILY_LIMIT', 3),
        'est_cost_per_request_usd' => (float) env('CONTENT_AI_EST_COST_PER_REQUEST_USD', 0.05),
        // Reject a single request if estimated cost exceeds this.
        'max_cost_per_request_usd' => (float) env('CONTENT_AI_MAX_COST_PER_REQUEST_USD', 0.25),
        // Low-priority (guide refresh) only when remaining >= this.
        'plenty_usd' => (float) env('CONTENT_AI_PLENTY_USD', 5),
        'healthy_usd' => (float) env('CONTENT_AI_HEALTHY_USD', 5),
        'low_usd' => (float) env('CONTENT_AI_LOW_USD', 3),
        'input_usd_per_million' => (float) env('CONTENT_AI_INPUT_USD_PER_M', 0.15),
        'output_usd_per_million' => (float) env('CONTENT_AI_OUTPUT_USD_PER_M', 0.60),
        'prompt_version' => 'v1',
        'template_version' => 'v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per content-type automation rules
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'fuel_price_increase' => [
            'label' => 'Daily price increase',
            'use_ai' => false,
            'ai_priority' => 'none',
            'review_required' => (bool) env('CONTENT_DAILY_REVIEW_REQUIRED', false),
            'auto_publish' => (bool) env('CONTENT_DAILY_AUTO_PUBLISH', true),
        ],
        'fuel_price_decrease' => [
            'label' => 'Daily price decrease',
            'use_ai' => false,
            'ai_priority' => 'none',
            'review_required' => (bool) env('CONTENT_DAILY_REVIEW_REQUIRED', false),
            'auto_publish' => (bool) env('CONTENT_DAILY_AUTO_PUBLISH', true),
        ],
        'government_notification' => [
            'label' => 'Government notification',
            'use_ai' => false,
            'ai_priority' => 'none',
            'review_required' => (bool) env('CONTENT_GOV_REVIEW_REQUIRED', false),
            'auto_publish' => (bool) env('CONTENT_GOV_AUTO_PUBLISH', true),
        ],
        'weekly_summary' => [
            'label' => 'Weekly summary',
            'use_ai' => true,
            'ai_priority' => 'high',
            'review_required' => (bool) env('CONTENT_WEEKLY_REVIEW_REQUIRED', true),
            'auto_publish' => (bool) env('CONTENT_WEEKLY_AUTO_PUBLISH', false),
        ],
        'monthly_summary' => [
            'label' => 'Monthly summary',
            'use_ai' => true,
            'ai_priority' => 'high',
            'review_required' => (bool) env('CONTENT_MONTHLY_REVIEW_REQUIRED', true),
            'auto_publish' => (bool) env('CONTENT_MONTHLY_AUTO_PUBLISH', false),
        ],
        'market_analysis' => [
            'label' => 'Market analysis',
            'use_ai' => true,
            'ai_priority' => 'medium',
            'review_required' => true,
            'auto_publish' => false,
        ],
        'guide_update' => [
            'label' => 'New evergreen guide',
            'use_ai' => true,
            'ai_priority' => 'medium',
            'review_required' => true,
            'auto_publish' => false,
        ],
        'guide_refresh' => [
            'label' => 'Guide refresh',
            'use_ai' => true,
            'ai_priority' => 'low',
            'review_required' => true,
            'auto_publish' => false,
        ],
        'opec_event' => [
            'label' => 'OPEC event',
            'use_ai' => true,
            'ai_priority' => 'medium',
            'review_required' => true,
            'auto_publish' => false,
        ],
        'default' => [
            'label' => 'Default',
            'use_ai' => false,
            'ai_priority' => 'none',
            'review_required' => true,
            'auto_publish' => false,
        ],
    ],

    'linking' => [
        'max_links' => (int) env('CONTENT_MAX_INTERNAL_LINKS', 6),
        'min_score' => 0.25,
    ],

    'distribution' => [
        'channels' => [
            'website' => ['enabled' => true],
            'rss' => ['enabled' => true],
            'news_sitemap' => ['enabled' => true],
            'indexnow' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_INDEXNOW', true)],
            'whatsapp' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_WHATSAPP', false)],
            'telegram' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_TELEGRAM', false)],
            'facebook' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_FACEBOOK', false)],
            'x' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_X', false)],
            'linkedin' => ['enabled' => (bool) env('CONTENT_DISTRIBUTE_LINKEDIN', false)],
        ],
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],
        'whatsapp' => [
            'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
        ],
    ],

    'refresh' => [
        'stale_days' => (int) env('CONTENT_STALE_DAYS', 45),
        'min_score_to_queue' => (int) env('CONTENT_REFRESH_MIN_SCORE', 40),
        'daily_limit' => (int) env('CONTENT_REFRESH_DAILY_LIMIT', 3),
    ],

    'event_types' => [
        'fuel_price_increase' => ['category' => 'petrol-prices', 'tone' => 'urgent_clear'],
        'fuel_price_decrease' => ['category' => 'petrol-prices', 'tone' => 'relief_clear'],
        'government_notification' => ['category' => 'government', 'tone' => 'formal'],
        'weekly_summary' => ['category' => 'fuel-news', 'tone' => 'analytical'],
        'monthly_summary' => ['category' => 'oil-market', 'tone' => 'analytical'],
        'market_analysis' => ['category' => 'oil-market', 'tone' => 'analytical'],
        'guide_update' => ['category' => 'guides', 'tone' => 'helpful'],
        'opec_event' => ['category' => 'oil-market', 'tone' => 'analytical'],
    ],
];
