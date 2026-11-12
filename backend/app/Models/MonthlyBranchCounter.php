<?php

namespace App\Models;

class MonthlyBranchCounter extends Model
{
    protected $casts = [
        'period' => 'date',
        'unique_customers' => 'integer',
        'warned_threshold_1_at' => 'datetime',
        'warned_threshold_2_at' => 'datetime',
        'limit_reached_at' => 'datetime',
    ];
}
