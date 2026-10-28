<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NAP boxes and their ports.
 *
 * A port is stored as available, occupied (a client is connected) or faulty.
 */
final class NapBoxes
{
    /** The picture of a box is kept as base64 text; the browser shrinks it first, so this is only a safety limit (bytes). */
    public const PHOTO_MAX_BYTES = 1_000_000;

    private const PHOTO_TYPES = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** NAP boxes are stock too: the inventory category of the NAP boxes a branch has not installed yet. */
    public const STOCK_CATEGORY = 'NAP Box';

    /** The barangays of a branch, spelled as the branch has them (from config/bohol_barangays.php). */
    public static function barangaysOf(string $branch): array
    {
        foreach ((array) config('bohol_barangays', []) as $name => $barangays) {
            if (strcasecmp((string) $name, trim($branch)) === 0) {
                return array_values((array) $barangays);
            }
        }

        return [];
    }

    /**
     * The boxes of a branch (every branch when null) with their port counts. $filter is all, available (has a
     * free port) or full.
     */
    public static function boxes(?string $branch, ?string $barangay = null, string $search = '', string $filter = 'all', bool $includeInactive = false, bool $archived = false): array
    {
        // null is every branch (the super admin), '' is no access.
        $boxes = DB::table('nap_boxes')
            ->when($branch === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch]))
            ->where('archived', $archived)
            ->when(! $includeInactive, fn ($q) => $q->where('status', 'active'))
            ->when($barangay !== null && $barangay !== '', fn ($q) => $q->whereRaw('LOWER(barangay_name) = LOWER(?)', [$barangay]))
            // Every word of the search must be found in the name, the location, the barangay or the branch. LOWER() on both sides:
            // LIKE ignores the case on MySQL but not on Postgres (Supabase).
            ->when($search !== '', function ($q) use ($search) {
                foreach (LocationFilter::words($search) as $word) {
                    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $word) . '%';
                    $q->where(function ($inner) use ($like) {
                        $inner->whereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                            ->orWhereRaw("LOWER(COALESCE(location, '')) LIKE LOWER(?)", [$like])
                            ->orWhereRaw('LOWER(barangay_name) LIKE LOWER(?)', [$like])
                            ->orWhereRaw('LOWER(branch_name) LIKE LOWER(?)', [$like]);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $result = self::withCounts($boxes->all());

        if ($filter === 'available') {
            $result = array_values(array_filter($result, fn (array $box) => $box['available_ports'] > 0));
        } elseif ($filter === 'full') {
            $result = array_values(array_filter($result, fn (array $box) => $box['available_ports'] === 0));
        }

        return $result;
    }

    /** One box (or null) with its ports, scoped to a branch when one is given. */
    public static function box(int $id, ?string $branch = null): ?array
    {
        $box = DB::table('nap_boxes')->where('id', $id)
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch]))
            ->first();
        if (! $box) {
            return null;
        }

        $result = self::withCounts([$box])[0];
        $result['ports'] = self::ports($id);
        $result['inventory_used'] = self::inventoryUsed($id);

        return $result;
    }

    /**
     * The stock transactions that belong to a NAP box: the unit taken from stock when it was installed. Newest first.
     * installations on its ports used. Newest first.
     */
    public static function inventoryUsed(int $boxId, int $limit = 50): array
    {
        if (! StockMovements::linked()) {
            return [];
        }

        $rows = DB::table('inventory_stock_movements as movement')
            ->join('inventory as item', 'item.id', '=', 'movement.inventory_id')
            ->leftJoin('employees as employee', 'employee.id', '=', 'movement.employee_id')
            ->leftJoin('clients as client', 'client.id', '=', 'movement.client_id')
            ->where('movement.nap_box_id', $boxId)
            ->orderByDesc('movement.created_at')->orderByDesc('movement.id')
            ->limit($limit)
            ->get(['movement.created_at', 'movement.movement_type', 'movement.quantity_change', 'movement.remarks', 'item.item_name', 'employee.full_name as employee_name', 'client.full_name as client_name', 'client.client_code']);

        return array_map(fn ($row): array => [
            'occurred_at' => (string) $row->created_at,
            'item_name' => $row->item_name,
            'type' => $row->movement_type,
            'type_label' => StockMovements::label($row->movement_type),
            'quantity_change' => (int) $row->quantity_change,
            'employee_name' => $row->employee_name,
            'client_name' => $row->client_name,
            'client_code' => $row->client_code,
            'remarks' => $row->remarks,
        ], $rows->all());
    }

    /** Whether an inventory row (object or array) is a NAP Box: those are installed by the branch admin, never handed out to an employee. */
    public static function isStockItem(object|array $item): bool
    {
        $category = is_array($item) ? ($item['category'] ?? '') : ($item->category ?? '');

        return strcasecmp(trim((string) $category), self::STOCK_CATEGORY) === 0;
    }

    /** The stock items of a branch that are NAP boxes (category "NAP Box"), still active, with how many are on the shelf. */
    public static function stockItems(?string $branch): array
    {
        return DB::table('inventory')
            ->whereRaw('LOWER(TRIM(category)) = LOWER(?)', [self::STOCK_CATEGORY])
            ->where('archived', 0)->where('status', 'active')
            ->when($branch === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(COALESCE(branch_name, \'\')) = LOWER(?)', [$branch]))
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'quantity', 'branch_name'])
            ->map(fn ($item): array => ['id' => (int) $item->id, 'item_name' => $item->item_name, 'quantity' => (int) $item->quantity, 'branch_name' => $item->branch_name])
            ->all();
    }

    /**
     * The active boxes of a barangay with their ports, for the pickers of Register New Client and of confirming an installation
     * report. $exceptInstallation is the report being confirmed: its own reserved port is shown as free to take.
     */
    public static function inBarangay(?string $branch, string $barangay, ?int $exceptInstallation = null): array
    {
        $boxes = self::boxes($branch, $barangay);
        usort($boxes, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));
        foreach ($boxes as &$box) {
            $box['ports'] = self::ports($box['id'], $exceptInstallation);
        }

        return $boxes;
    }

    /** The ports of a box, each with its status: available, occupied or faulty. */
    public static function ports(int $boxId, ?int $exceptInstallation = null): array
    {
        $rows = DB::table('nap_ports')
            ->leftJoin('clients', 'clients.id', '=', 'nap_ports.client_id')
            ->where('nap_ports.nap_box_id', $boxId)
            ->orderBy('nap_ports.port_number')
            ->get(['nap_ports.id', 'nap_ports.port_number', 'nap_ports.status', 'nap_ports.client_id', 'nap_ports.occupied_at', 'clients.full_name as client_name', 'clients.client_code']);

        $reserved = array_flip(self::reservedPortIds($boxId, $exceptInstallation));

        return array_map(fn ($row): array => [
            'id' => (int) $row->id,
            'port_number' => (int) $row->port_number,
            'status' => $row->status,
            // A free port that an employee has already used in an installation report the admin has not confirmed yet.
            'reserved' => $row->status === 'available' && isset($reserved[(int) $row->id]),
            'client_id' => $row->client_id ? (int) $row->client_id : null,
            'client_name' => $row->client_name,
            'client_code' => $row->client_code,
        ], $rows->all());
    }

    /**
     * The ports that an installation report waiting for the admin has taken. They stay available until the admin confirms,
     * but nobody else is offered them meanwhile. $exceptInstallation leaves out the report being confirmed.
     *
     * @return int[]
     */
    public static function reservedPortIds(?int $boxId = null, ?int $exceptInstallation = null): array
    {
        if (! Schema::hasTable('client_installations')) {
            return [];
        }

        return DB::table('client_installations')
            ->where('status', 'submitted')->whereNotNull('nap_port_id')
            ->when($boxId !== null, fn ($q) => $q->where('nap_box_id', $boxId))
            ->when($exceptInstallation !== null, fn ($q) => $q->where('id', '!=', $exceptInstallation))
            ->pluck('nap_port_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Add the occupied / faulty / available counts to boxes. */
    private static function withCounts(array $boxes): array
    {
        if ($boxes === []) {
            return [];
        }

        $ids = array_map(fn ($box) => (int) $box->id, $boxes);
        // Which boxes have a photo, and when it last changed (the page and the app add it to the picture's address so a new photo shows).
        $photos = Schema::hasTable('nap_box_photos')
            ? DB::table('nap_box_photos')->whereIn('nap_box_id', $ids)->pluck('updated_at', 'nap_box_id')->all()
            : [];
        $ports = DB::table('nap_ports')->whereIn('nap_box_id', $ids)->get(['id', 'nap_box_id', 'status']);
        $reserved = array_flip(self::reservedPortIds());
        $counts = [];
        foreach ($ports as $port) {
            $boxId = (int) $port->nap_box_id;
            $counts[$boxId] ??= ['occupied' => 0, 'faulty' => 0, 'available' => 0, 'reserved' => 0, 'rows' => 0];
            $counts[$boxId]['rows']++;
            if ($port->status === 'occupied') {
                $counts[$boxId]['occupied']++;
            } elseif ($port->status === 'faulty') {
                $counts[$boxId]['faulty']++;
            } elseif (isset($reserved[(int) $port->id])) {
                $counts[$boxId]['reserved']++;
            } else {
                $counts[$boxId]['available']++;
            }
        }

        return array_map(function ($box) use ($counts, $photos): array {
            $c = $counts[(int) $box->id] ?? ['occupied' => 0, 'faulty' => 0, 'available' => 0, 'reserved' => 0, 'rows' => 0];

            return [
                'id' => (int) $box->id,
                'name' => $box->name,
                'branch_name' => $box->branch_name,
                'barangay_name' => $box->barangay_name,
                'location' => $box->location,
                'status' => $box->status,
                'archived' => (bool) $box->archived,
                'total_ports' => (int) $box->total_ports,
                'occupied_ports' => $c['occupied'],
                'faulty_ports' => $c['faulty'],
                'reserved_ports' => $c['reserved'],
                'available_ports' => $c['available'],
                'latitude' => $box->latitude !== null ? (float) $box->latitude : null,
                'longitude' => $box->longitude !== null ? (float) $box->longitude : null,
                'coordinates' => Coordinates::format($box->latitude, $box->longitude),
                'map_url' => Coordinates::mapUrl($box->latitude, $box->longitude),
                'has_photo' => isset($photos[(int) $box->id]),
                'photo_version' => isset($photos[(int) $box->id]) ? (string) (strtotime((string) $photos[(int) $box->id]) ?: 1) : null,
            ];
        }, $boxes);
    }

    /** Create a box with its numbered ports, all available. $latitude and $longitude are where the box hangs. */
    public static function create(string $branch, string $name, string $barangay, ?string $location, int $totalPorts, ?float $latitude = null, ?float $longitude = null): int
    {
        return DB::transaction(function () use ($branch, $name, $barangay, $location, $totalPorts, $latitude, $longitude): int {
            $boxId = (int) DB::table('nap_boxes')->insertGetId([
                'name' => $name, 'branch_name' => $branch, 'barangay_name' => $barangay, 'location' => $location,
                'latitude' => $latitude, 'longitude' => $longitude,
                'total_ports' => $totalPorts, 'status' => 'active', 'archived' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            self::addPorts($boxId, 1, $totalPorts);

            return $boxId;
        });
    }

    /**
     * Install a new NAP box: it takes one unit of a "NAP Box" stock item of the branch out of the inventory (and says so in the stock
     * history, with the NAP box on it), then the box exists with its numbered ports. Without a unit in stock nothing happens.
     * $stockItemId may be left out when the branch has exactly one NAP Box stock item.
     */
    public static function installFromStock(string $branch, string $name, string $barangay, ?string $location, int $totalPorts, ?int $stockItemId, ?int $adminId, ?float $latitude = null, ?float $longitude = null): int
    {
        return DB::transaction(function () use ($branch, $name, $barangay, $location, $totalPorts, $stockItemId, $adminId, $latitude, $longitude): int {
            $choices = self::stockItems($branch);
            if ($stockItemId === null || $stockItemId === 0) {
                if (count($choices) !== 1) {
                    throw new DomainException($choices === []
                        ? 'There is no NAP Box in your stock. Add an item with the category "' . self::STOCK_CATEGORY . '" in Inventory first.'
                        : 'Choose which NAP Box to take from the stock.');
                }
                $stockItemId = $choices[0]['id'];
            }

            $item = DB::table('inventory')->where('id', $stockItemId)->lockForUpdate()->first();
            if (! $item || ! in_array((int) $item->id, array_column($choices, 'id'), true)) {
                throw new DomainException('Choose a NAP Box from the stock of your branch.');
            }
            if ((int) $item->quantity < 1) {
                throw new DomainException("There is no {$item->item_name} left in stock. Receive it in Inventory first.");
            }

            $boxId = self::create($branch, $name, $barangay, $location, $totalPorts, $latitude, $longitude);
            $balance = (int) $item->quantity - 1;
            DB::table('inventory')->where('id', $item->id)->update(['quantity' => $balance]);
            StockMovements::record((int) $item->id, -1, $balance, 'nap_installed', "{$name} installed at {$barangay}.", $adminId, ['nap_box_id' => $boxId]);

            return $boxId;
        });
    }

    /**
     * A picture sent as a data address ("data:image/jpeg;base64,..."), checked and split into [mime type, base64 text].
     * It must really be a JPEG, PNG or WebP picture and not larger than PHOTO_MAX_BYTES.
     *
     * @return array{0: string, 1: string}
     */
    public static function checkPhoto(string $dataUrl): array
    {
        $mime = null;
        $base64 = '';
        foreach (array_keys(self::PHOTO_TYPES) as $type) {
            $prefix = "data:{$type};base64,";
            if (str_starts_with($dataUrl, $prefix)) {
                $mime = $type;
                $base64 = substr($dataUrl, strlen($prefix));
                break;
            }
        }
        if ($mime === null) {
            throw new DomainException('The photo must be a JPEG, PNG or WebP picture.');
        }

        // Base64 is a third larger than the picture; refuse before decoding something huge.
        if (strlen($base64) > (int) (self::PHOTO_MAX_BYTES * 4 / 3) + 8) {
            throw new DomainException('The photo is too large. Choose a smaller picture.');
        }
        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '') {
            throw new DomainException('The photo could not be read. Choose the picture again.');
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ($info['mime'] ?? null) !== $mime || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            throw new DomainException('The photo must be a JPEG, PNG or WebP picture.');
        }

        return [$mime, $base64];
    }

    /** Keep (or replace) the photo of a box. Throws a DomainException when the picture is not acceptable. */
    public static function savePhoto(int $boxId, string $dataUrl): void
    {
        [$mime, $base64] = self::checkPhoto($dataUrl);
        $exists = DB::table('nap_box_photos')->where('nap_box_id', $boxId)->exists();
        if ($exists) {
            DB::table('nap_box_photos')->where('nap_box_id', $boxId)->update(['mime_type' => $mime, 'data' => $base64, 'updated_at' => now()]);

            return;
        }
        DB::table('nap_box_photos')->insert(['nap_box_id' => $boxId, 'mime_type' => $mime, 'data' => $base64, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function removePhoto(int $boxId): void
    {
        DB::table('nap_box_photos')->where('nap_box_id', $boxId)->delete();
    }

    /**
     * The picture of a box as an HTTP answer (404 when the box is not in the branch or has no photo). $branch is the branch the
     * viewer is limited to; null is every branch (the super admin), '' is none.
     */
    public static function photoResponse(int $boxId, ?string $branch): \Symfony\Component\HttpFoundation\Response
    {
        $photo = $branch === '' || ! Schema::hasTable('nap_box_photos') ? null : DB::table('nap_box_photos')
            ->join('nap_boxes', 'nap_boxes.id', '=', 'nap_box_photos.nap_box_id')
            ->where('nap_box_photos.nap_box_id', $boxId)
            ->when($branch !== null, fn ($q) => $q->whereRaw('LOWER(nap_boxes.branch_name) = LOWER(?)', [$branch]))
            ->first(['nap_box_photos.mime_type', 'nap_box_photos.data', 'nap_box_photos.updated_at']);
        abort_if(! $photo, 404);

        return response(base64_decode((string) $photo->data), 200, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Insert ports $from..$to for a box. */
    public static function addPorts(int $boxId, int $from, int $to): void
    {
        $rows = [];
        for ($number = $from; $number <= $to; $number++) {
            $rows[] = ['nap_box_id' => $boxId, 'port_number' => $number, 'status' => 'available', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($rows !== []) {
            DB::table('nap_ports')->insert($rows);
        }
    }
}
