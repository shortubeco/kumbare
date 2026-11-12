<?php

namespace App\Models;

class MonthlyBranchCustomer extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'period' => 'date',
    ];
}
