<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $casts = [
        'reward_rate' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'rating_avg' => 'decimal:1',
        'rating_count' => 'integer',
        'popularity_score' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BranchHour::class)->orderBy('weekday');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BranchPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function manager(): HasOne
    {
        return $this->hasOne(PanelUser::class)
            ->where('role', PanelUser::ROLE_BRANCH_MANAGER)
            ->where('status', 'active');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Herkese açık listelerde görünebilen şubeler: aktif şube + onaylı işletme. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('branches.status', 'active')
            ->whereHas('business', fn (Builder $q) => $q->where('status', 'approved'));
    }

    public function isOperational(): bool
    {
        return $this->status === 'active' && $this->business?->status === 'approved';
    }

    public function displayName(): string
    {
        return $this->business->name.' – '.$this->name;
    }
}
