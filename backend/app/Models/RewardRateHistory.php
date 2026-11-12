<?php

namespace App\Models;

class RewardRateHistory extends Model
{
    protected $table = 'reward_rate_history';

    const UPDATED_AT = null;

    protected $casts = [
        'old_rate' => 'decimal:2',
        'new_rate' => 'decimal:2',
    ];

    public function changer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PanelUser::class, 'changed_by');
    }
}
