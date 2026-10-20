<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every stock transaction of the inventory is written here, so each one can say who handled it and, when it belongs to a job, which
 * client and NAP box it was for (the "Client / NAP" column of the Inventory transactions and the "Inventory used" list of a NAP box).
 */
final class StockMovements
{
    private const LABELS = [
        'opening_balance' => 'Opening Stock',
        'stock_received' => 'Stock Received',
        'stock_issued' => 'Stock Released',
        'stock_returned' => 'Stock Returned',
        'damaged' => 'Damaged or Lost',
        'stock_count' => 'Stock Counted',
        'employee_release' => 'Released to Employee',
        'job_used' => 'Used on Job',
        'job_damaged' => 'Damaged on Job',
        'nap_installed' => 'NAP Box Installed',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /** Whether the link columns exist (a project that has not run the 2026-10-19 migration yet only loses the links). */
    public static function linked(): bool
    {
        return Schema::hasColumn('inventory_stock_movements', 'nap_box_id');
    }

    /**
     * @param array{employee_id?: ?int, client_id?: ?int, nap_box_id?: ?int, work_request_id?: ?int} $links
     */
    public static function record(int $inventoryId, int $change, int $balance, string $type, ?string $remarks, ?int $performedBy, array $links = []): void
    {
        $row = [
            'inventory_id' => $inventoryId, 'quantity_change' => $change, 'balance_after' => $balance, 'movement_type' => $type,
            'remarks' => $remarks, 'performed_by' => $performedBy, 'created_at' => now(),
        ];
        if (self::linked()) {
            foreach (['employee_id', 'client_id', 'nap_box_id', 'work_request_id'] as $column) {
                $row[$column] = $links[$column] ?? null;
            }
        }

        DB::table('inventory_stock_movements')->insert($row);
    }

    /** The "Client / NAP" cell: "Ana Reyes (JFT-1) · NAP-01", whichever parts are known; null when none. */
    public static function target(?string $clientName, ?string $clientCode, ?string $napName): ?string
    {
        $client = $clientName ? trim($clientName . ($clientCode ? " ({$clientCode})" : '')) : null;
        $parts = array_values(array_filter([$client, $napName]));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
