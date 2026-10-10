<?php

namespace App\Support;

use App\Models\Price;
use App\Models\PriceHistory;
use App\Models\PriceAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PriceService
{
    public function ensureTablesExist(): void
    {
        $pdo = DB::connection()->getPdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS prices (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id INT NOT NULL,
                base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                discount_type ENUM('none','percentage','fixed') NOT NULL DEFAULT 'none',
                discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'PHP',
                effective_from DATE NOT NULL,
                effective_until DATE,
                status ENUM('draft','active','scheduled','inactive','expired') NOT NULL DEFAULT 'draft',
                created_by INT,
                updated_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS price_histories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id INT NOT NULL,
                price_id INT,
                previous_price DECIMAL(10,2),
                new_price DECIMAL(10,2),
                change_amount DECIMAL(10,2),
                change_percentage DECIMAL(8,2),
                previous_discount_type VARCHAR(20),
                previous_discount_value DECIMAL(10,2),
                new_discount_type VARCHAR(20),
                new_discount_value DECIMAL(10,2),
                reason TEXT,
                changed_by INT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
                FOREIGN KEY (price_id) REFERENCES prices(id) ON DELETE SET NULL,
                FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS price_audit_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                price_id INT,
                product_id INT,
                admin_id INT,
                action VARCHAR(50) NOT NULL,
                old_value TEXT,
                new_value TEXT,
                reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (price_id) REFERENCES prices(id) ON DELETE SET NULL,
                FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE SET NULL,
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $this->backfillSkuColumn($pdo);
    }

    protected function backfillSkuColumn($pdo): void
    {
        $colCheck = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'sku'");
        if (! $colCheck || ! $colCheck->fetch()) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN sku VARCHAR(100) AFTER item_name");
        }

        $rows = DB::table('inventory')->whereNull('sku')->get();
        foreach ($rows as $row) {
            DB::table('inventory')
                ->where('id', $row->id)
                ->update(['sku' => 'SKU-' . str_pad($row->id, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function processStatusTransitions(): void
    {
        $today = now()->toDateString();

        Price::where('status', 'scheduled')
            ->where('effective_from', '<=', $today)
            ->update(['status' => 'active']);

        Price::where('status', 'active')
            ->whereNotNull('effective_until')
            ->where('effective_until', '<', $today)
            ->update(['status' => 'expired']);
    }

    public function getDashboardStats(): array
    {
        $this->processStatusTransitions();

        $today = now()->toDateString();

        return [
            'total_products' => DB::table('inventory')->where('archived', 0)->count(),
            'products_with_prices' => Price::whereIn('status', ['active', 'scheduled', 'draft', 'inactive', 'expired'])
                ->distinct('product_id')->count('product_id'),
            'products_without_prices' => DB::table('inventory')
                ->where('archived', 0)
                ->whereNotIn('id', function ($q) {
                    $q->select('product_id')->from('prices');
                })
                ->count(),
            'active_prices' => Price::where('status', 'active')->count(),
            'products_with_discounts' => Price::where('discount_type', '!=', 'none')
                ->where('discount_value', '>', 0)
                ->whereIn('status', ['active', 'scheduled'])
                ->distinct('product_id')
                ->count('product_id'),
            'scheduled_prices' => Price::where('status', 'scheduled')->count(),
            'expired_prices' => Price::where('status', 'expired')->count(),
        ];
    }

    public function getRecentPriceChanges(int $limit = 10): array
    {
        return PriceHistory::query()
            ->join('inventory', 'price_histories.product_id', '=', 'inventory.id')
            ->leftJoin('users as admin', 'price_histories.changed_by', '=', 'admin.id')
            ->select([
                'price_histories.*',
                'inventory.item_name as product_name',
                'inventory.sku',
                'admin.full_name as admin_name',
            ])
            ->orderByDesc('price_histories.changed_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getUpcomingPriceChanges(int $limit = 10): array
    {
        $today = now()->toDateString();

        return Price::query()
            ->join('inventory', 'prices.product_id', '=', 'inventory.id')
            ->where('prices.status', 'scheduled')
            ->where('prices.effective_from', '>', $today)
            ->select([
                'prices.*',
                'inventory.item_name as product_name',
                'inventory.sku',
            ])
            ->orderBy('prices.effective_from')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function searchProducts(string $term): array
    {
        $term = trim($term);
        $query = DB::table('inventory')
            ->where('archived', 0)
            ->where(function ($q) use ($term) {
                $q->where('item_name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', 'LIKE', "%{$term}%")
                    ->orWhere('id', '=', $term);
            });

        return $query->orderBy('item_name')->limit(50)->get()->map(fn ($item) => (array) $item)->toArray();
    }

    public function getProductForPrice(int $productId): ?array
    {
        $product = DB::table('inventory')
            ->where('id', $productId)
            ->where('archived', 0)
            ->first();

        if (! $product) return null;

        $price = Price::where('product_id', $productId)
            ->whereIn('status', ['active', 'scheduled'])
            ->notExpired()
            ->orderByDesc('effective_from')
            ->first();

        return [
            'product' => (array) $product,
            'current_price' => $price ? $price->toArray() : null,
        ];
    }

    public function getAllPrices(array $filters): array
    {
        $query = Price::query()
            ->join('inventory', 'prices.product_id', '=', 'inventory.id')
            ->select([
                'prices.*',
                'inventory.item_name as product_name',
                'inventory.sku',
                'inventory.category',
            ]);

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('inventory.item_name', 'LIKE', "%{$term}%")
                    ->orWhere('inventory.sku', 'LIKE', "%{$term}%")
                    ->orWhere('inventory.id', '=', $term);
            });
        }

        if (! empty($filters['category'])) {
            $query->where('inventory.category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('prices.status', $filters['status']);
        }

        if (! empty($filters['has_discount'])) {
            if ($filters['has_discount'] === 'yes') {
                $query->where('prices.discount_type', '!=', 'none')
                    ->where('prices.discount_value', '>', 0);
            } elseif ($filters['has_discount'] === 'no') {
                $query->where(function ($q) {
                    $q->where('prices.discount_type', 'none')
                        ->orWhere('prices.discount_value', 0);
                });
            }
        }

        if (! empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('prices.final_price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('prices.final_price', '<=', $filters['max_price']);
        }

        if (! empty($filters['effective_from'])) {
            $query->where('prices.effective_from', '>=', $filters['effective_from']);
        }

        if (! empty($filters['effective_until'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('prices.effective_until')
                    ->orWhere('prices.effective_until', '<=', $filters['effective_until']);
            });
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['sort_dir'] ?? 'desc';
        $sortMap = [
            'product_name' => 'inventory.item_name',
            'sku' => 'inventory.sku',
            'category' => 'inventory.category',
            'base_price' => 'prices.base_price',
            'final_price' => 'prices.final_price',
            'effective_from' => 'prices.effective_from',
            'effective_until' => 'prices.effective_until',
            'status' => 'prices.status',
            'created_at' => 'prices.created_at',
        ];
        $sortColumn = $sortMap[$sortField] ?? 'prices.created_at';
        $query->orderByRaw($sortColumn . ' ' . ($sortOrder === 'asc' ? 'ASC' : 'DESC'));

        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $query->count();
        $prices = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

        return [
            'data' => $prices,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function getCategories(): array
    {
        return array_values(array_unique(
            array_filter(array_column(DB::table('inventory')->where('archived', 0)->pluck('category')->toArray(), 0))
        ));
    }

    public function getCurrency(): string
    {
        return DB::table('system_settings')->where('setting_key', 'currency')->value('setting_value') ?: 'PHP';
    }

    public function validatePrice(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['product_id'])) {
            $errors[] = 'Product is required.';
        }

        $basePrice = (float) ($data['base_price'] ?? 0);
        if ($basePrice < 0) {
            $errors[] = 'Price cannot be negative.';
        }

        if (empty($data['product_id'])) {
            return $errors;
        }

        if ($excludeId === null) {
            $hasConflict = Price::where('product_id', $data['product_id'])
                ->whereIn('status', ['active', 'scheduled'])
                ->where(function ($q) use ($data) {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $data['effective_from'] ?? now()->toDateString());
                })
                ->where('effective_from', '<=', $data['effective_from'] ?? now()->toDateString())
                ->exists();

            if ($hasConflict) {
                $errors[] = 'This product already has an active price.';
            }
        }

        if (empty($data['effective_from'])) {
            $errors[] = 'Effective From is required.';
        }

        if (! empty($data['effective_from']) && ! empty($data['effective_until'])) {
            if ($data['effective_until'] < $data['effective_from']) {
                $errors[] = 'Effective Until must be later than Effective From.';
            }
        }

        $discountType = $data['discount_type'] ?? 'none';
        $discountValue = (float) ($data['discount_value'] ?? 0);

        if ($discountValue < 0) {
            $errors[] = 'Discount cannot be negative.';
        }

        if ($discountType === 'percentage') {
            if ($discountValue > 100) {
                $errors[] = 'Percentage discount must be between 0 and 100.';
            }
        }

        if ($discountType === 'fixed') {
            if ($discountValue > $basePrice) {
                $errors[] = 'Discount cannot exceed the product price.';
            }
        }

        $finalPrice = Price::calculateFinalPrice($basePrice, $discountType, $discountValue);
        if ($finalPrice < 0) {
            $errors[] = 'Final price cannot be negative.';
        }

        return $errors;
    }

    public function recordHistory(Price $price, float $previousPrice, float $newPrice, string $reason, ?User $admin, string $action = 'change_price', ?array $oldDiscount = null, ?array $newDiscount = null): PriceHistory
    {
        $diff = $newPrice - $previousPrice;
        $percentage = $previousPrice > 0 ? round(($diff / $previousPrice) * 100, 2) : 0;

        return PriceHistory::create([
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'previous_price' => $previousPrice,
            'new_price' => $newPrice,
            'change_amount' => $diff,
            'change_percentage' => $percentage,
            'previous_discount_type' => $oldDiscount['type'] ?? $price->discount_type,
            'previous_discount_value' => $oldDiscount['value'] ?? $price->discount_value,
            'new_discount_type' => $newDiscount['type'] ?? $price->discount_type,
            'new_discount_value' => $newDiscount['value'] ?? $price->discount_value,
            'reason' => $reason,
            'changed_by' => $admin?->id,
            'changed_at' => now(),
        ]);
    }

    public function createAuditLog(?Price $price, string $action, $oldValue, $newValue, ?string $reason, ?User $admin): void
    {
        PriceAuditLog::create([
            'price_id' => $price?->id,
            'product_id' => $price?->product_id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'old_value' => is_array($oldValue) ? json_encode($oldValue) : (string) $oldValue,
            'new_value' => is_array($newValue) ? json_encode($newValue) : (string) $newValue,
            'reason' => $reason,
        ]);
    }

    public function bulkUpdatePreview(array $productIds, string $operation, $value): array
    {
        $prices = Price::whereIn('product_id', $productIds)
            ->whereIn('status', ['active', 'scheduled'])
            ->get();

        $preview = [];
        foreach ($prices as $price) {
            $currentFinal = (float) $price->final_price;
            $newBase = match ($operation) {
                'increase_percentage' => round($price->base_price * (100 + $value) / 100, 2),
                'decrease_percentage' => round($price->base_price * (100 - $value) / 100, 2),
                'increase_fixed' => round($price->base_price + $value, 2),
                'decrease_fixed' => round(max(0, $price->base_price - $value), 2),
                'set_price' => round($value, 2),
                default => $price->base_price,
            };

            $newFinal = Price::calculateFinalPrice($newBase, $price->discount_type, (float) $price->discount_value);

            $preview[] = [
                'product_id' => $price->product_id,
                'product_name' => $price->product->item_name ?? 'Unknown',
                'sku' => $price->product->sku ?? 'N/A',
                'current_price' => $currentFinal,
                'new_price' => $newFinal,
            ];
        }

        return $preview;
    }

    public function applyBulkUpdate(array $productIds, string $operation, $value, string $reason, ?User $admin): int
    {
        $count = 0;
        $prices = Price::whereIn('product_id', $productIds)
            ->whereIn('status', ['active', 'scheduled'])
            ->lockForUpdate()
            ->get();

        foreach ($prices as $price) {
            $oldBasePrice = (float) $price->base_price;
            $newBasePrice = match ($operation) {
                'increase_percentage' => round($price->base_price * (100 + $value) / 100, 2),
                'decrease_percentage' => round($price->base_price * (100 - $value) / 100, 2),
                'increase_fixed' => round($price->base_price + $value, 2),
                'decrease_fixed' => round(max(0, $price->base_price - $value), 2),
                'set_price' => round($value, 2),
                default => $price->base_price,
            };

            $newFinalPrice = Price::calculateFinalPrice($newBasePrice, $price->discount_type, (float) $price->discount_value);

            $oldData = [
                'base_price' => $oldBasePrice,
                'final_price' => (float) $price->final_price,
                'discount_type' => $price->discount_type,
                'discount_value' => (float) $price->discount_value,
            ];

            $price->update([
                'base_price' => $newBasePrice,
                'final_price' => $newFinalPrice,
                'updated_by' => $admin?->id,
            ]);

            $this->recordHistory(
                $price,
                $oldData['final_price'],
                $newFinalPrice,
                $reason,
                $admin,
                'bulk_update',
                ['type' => $oldData['discount_type'], 'value' => $oldData['discount_value']],
                ['type' => $price->discount_type, 'value' => (float) $price->discount_value]
            );

            $this->createAuditLog(
                $price,
                'bulk_price_update',
                $oldData,
                ['base_price' => $newBasePrice, 'final_price' => $newFinalPrice],
                $reason,
                $admin
            );

            $count++;
        }

        return $count;
    }

    public function formatCurrency(float $amount, string $currency = null): string
    {
        $currency = $currency ?? $this->getCurrency();
        return $currency . ' ' . number_format($amount, 2);
    }
}
