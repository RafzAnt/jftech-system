<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InternetPlan extends Model {
    protected $fillable = ['plan_name', 'speed_mbps', 'monthly_price', 'installation_fee', 'description', 'status', 'archived', 'scheduled_monthly_price', 'scheduled_installation_fee', 'price_effective_on'];
    protected $casts = ['speed_mbps' => 'integer', 'monthly_price' => 'decimal:2', 'installation_fee' => 'decimal:2', 'scheduled_monthly_price' => 'decimal:2', 'scheduled_installation_fee' => 'decimal:2', 'price_effective_on' => 'date', 'archived' => 'boolean'];
    public function scopeActive($query) { return $query->where('status', 'active')->where('archived', 0); }
    public function clients() { return $this->hasMany(Client::class); }
}
