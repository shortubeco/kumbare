<?php

namespace App\Models;

class ReviewReport extends Model
{
    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
