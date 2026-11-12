<?php

namespace App\Models;

class Plan extends Model
{
    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
    ];
}
