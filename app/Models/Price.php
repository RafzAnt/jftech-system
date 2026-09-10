<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $table = 'prices';
    protected $guarded = [];
    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'product_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceHistory::class, 'price_id');
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceAuditLog::class, 'price_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString());
        });
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'scheduled' => 'info',
            'draft' => 'warning',
            'inactive' => 'secondary',
            'expired' => 'danger',
            default => 'secondary',
        };
    }

    public static function calculateFinalPrice(float $basePrice, string $discountType, float $discountValue): float
    {
        return match ($discountType) {
            'percentage' => round($basePrice * (100 - $discountValue) / 100, 2),
            'fixed' => round(max(0, $basePrice - $discountValue), 2),
            default => round($basePrice, 2),
        };
    }

    public function discountLabel(): string
    {
        return match ($this->discount_type) {
            'percentage' => $this->discount_value . '% OFF',
            'fixed' => '₱' . number_format((float) $this->discount_value, 2) . ' OFF',
            default => 'No Discount',
        };
    }

    public function isConflicting(int $productId, ?int $excludeId = null): bool
    {
        $query = self::where('product_id', $productId)
            ->where('status', 'active')
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->where('effective_from', '<=', now()->toDateString());

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
