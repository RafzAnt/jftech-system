<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternetPlanPriceHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'internet_plan_id', 'previous_monthly_price', 'new_monthly_price',
        'previous_installation_fee', 'new_installation_fee', 'change_type',
        'effective_on', 'reason', 'changed_by',
    ];

    protected $casts = [
        'previous_monthly_price' => 'decimal:2', 'new_monthly_price' => 'decimal:2',
        'previous_installation_fee' => 'decimal:2', 'new_installation_fee' => 'decimal:2',
        'effective_on' => 'date',
    ];
}
