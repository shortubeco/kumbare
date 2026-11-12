<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Profesyonel paket dönemi. Kayıt yoksa şube Ücretsiz pakettedir. */
class Subscription extends Model
{
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now);
    }
}
