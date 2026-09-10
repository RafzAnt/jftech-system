<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['archived' => 'boolean', 'unit_price' => 'decimal:2'];
}
