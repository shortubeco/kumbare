<?php

namespace App\Models;

class BranchHour extends Model
{
    protected $casts = [
        'weekday' => 'integer',
        'is_closed' => 'boolean',
    ];
}
