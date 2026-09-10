<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceAuditLog extends Model
{
    protected $table = 'price_audit_logs';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function price(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Price::class, 'price_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'product_id');
    }

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
