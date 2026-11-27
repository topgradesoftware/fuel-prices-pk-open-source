<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'platform',
        'app_version',
        'os_version',
        'firebase_token',
        'locale',
        'timezone',
        'alerts_enabled',
        'last_seen_at',
        'device_secret',
        'device_public_key',
        'last_ip',
        'last_country',
        'last_login_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'alerts_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->alerts_enabled
            && filled($this->firebase_token);
    }
}
