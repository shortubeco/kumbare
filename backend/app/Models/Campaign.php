<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_RATE_OVERRIDE = 'rate_override';

    protected $casts = [
        'reward_rate' => 'decimal:2',
        'new_customers_only' => 'boolean',
        'birthday_only' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Durumu aktif ve şu an tarih aralığı içinde olan kampanyalar. */
    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query->where('campaigns.status', 'active')
            ->where('campaigns.starts_at', '<=', $now)
            ->where('campaigns.ends_at', '>', $now);
    }

    /** PostgreSQL SMALLINT[] değerini ("{1,2,3}") PHP dizisine çevirir. */
    public function weekdayList(): array
    {
        $raw = $this->attributes['weekdays'] ?? null;
        if ($raw === null || $raw === '' || $raw === '{}') {
            return [];
        }

        return array_map('intval', explode(',', trim($raw, '{}')));
    }

    public function setWeekdayList(?array $days): void
    {
        $this->attributes['weekdays'] = $days ? '{'.implode(',', array_map('intval', $days)).'}' : null;
    }
}
