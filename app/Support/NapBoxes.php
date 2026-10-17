<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * NAP boxes and their ports.
 *
 * A port is stored as available, occupied (a client is connected) or faulty. "Reserved" is not stored: a port is
 * reserved while an installation request that proposes (or reported) it is still open, so it frees itself the
 * moment that request is cancelled, rejected or confirmed.
 */
final class NapBoxes
{
    /** Installation requests in these states hold the port they propose. */
    public const OPEN_REQUEST_STATUSES = ['pending_approval', 'returned', 'approved', 'work_completed'];

    /**
     * Ports held by an open installation request, as [port id => request code].
     * $exceptRequestId lets a request keep the port it already holds.
     */
    public static function reservedPorts(?string $branch = null, ?int $exceptRequestId = null): array
    {
        $query = DB::table('work_requests')
            ->where('work_type', 'installation')
            ->whereIn('status', self::OPEN_REQUEST_STATUSES)
            ->when($branch !== null && $branch !== '', fn ($q) => $q->whereRaw('LOWER(branch_name) = LOWER(?)', [$branch]))
            ->when($exceptRequestId, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->get(['request_code', 'status', 'nap_port_id', 'actual_nap_port_id']);

        $reserved = [];
        foreach ($query as $row) {
            // Once the work is reported, the port that was really used is the one that matters.
            $portId = $row->status === 'work_completed' && $row->actual_nap_port_id ? $row->actual_nap_port_id : $row->nap_port_id;
            if ($portId) {
                $reserved[(int) $portId] = $row->request_code;
            }
        }

        return $reserved;
    }

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
            // LOWER() on both sides: LIKE ignores the case on MySQL but not on Postgres (Supabase).
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $inner->whereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                    ->orWhereRaw("LOWER(COALESCE(location, '')) LIKE LOWER(?)", [$like])
                    ->orWhereRaw('LOWER(barangay_name) LIKE LOWER(?)', [$like]);
            }))
            ->orderBy('barangay_name')
            ->orderBy('name')
            ->get();

        $result = self::withCounts($boxes->all(), self::reservedPorts($branch === '' ? null : $branch));

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

        $reserved = self::reservedPorts($box->branch_name);
        $result = self::withCounts([$box], $reserved)[0];
        $result['ports'] = self::ports($id, $reserved);

        return $result;
    }

    /** The ports of a box, each with its display status: available, occupied, reserved or faulty. */
    public static function ports(int $boxId, array $reserved): array
    {
        $rows = DB::table('nap_ports')
            ->leftJoin('clients', 'clients.id', '=', 'nap_ports.client_id')
            ->where('nap_ports.nap_box_id', $boxId)
            ->orderBy('nap_ports.port_number')
            ->get(['nap_ports.id', 'nap_ports.port_number', 'nap_ports.status', 'nap_ports.client_id', 'nap_ports.occupied_at', 'clients.full_name as client_name', 'clients.client_code']);

        return array_map(function ($row) use ($reserved): array {
            $status = $row->status;
            if ($status === 'available' && isset($reserved[(int) $row->id])) {
                $status = 'reserved';
            }

            return [
                'id' => (int) $row->id,
                'port_number' => (int) $row->port_number,
                'status' => $status,
                'client_id' => $row->client_id ? (int) $row->client_id : null,
                'client_name' => $row->client_name,
                'client_code' => $row->client_code,
                'reserved_by' => $status === 'reserved' ? $reserved[(int) $row->id] : null,
            ];
        }, $rows->all());
    }

    /**
     * Why a port cannot be used for an installation, or null when it can.
     * $exceptRequestId is the request that is asking, so it does not clash with the port it already holds.
     */
    public static function portProblem(int $portId, int $boxId, string $branch, ?int $exceptRequestId = null, ?int $clientId = null): ?string
    {
        $port = DB::table('nap_ports')
            ->join('nap_boxes', 'nap_boxes.id', '=', 'nap_ports.nap_box_id')
            ->where('nap_ports.id', $portId)
            ->where('nap_ports.nap_box_id', $boxId)
            ->first(['nap_ports.id', 'nap_ports.status', 'nap_ports.client_id', 'nap_ports.port_number', 'nap_boxes.name', 'nap_boxes.branch_name', 'nap_boxes.status as box_status', 'nap_boxes.archived']);

        if (! $port || strcasecmp(trim((string) $port->branch_name), trim($branch)) !== 0 || $port->archived || $port->box_status !== 'active') {
            return 'Choose a port of an active NAP box in your branch.';
        }
        if ($port->status === 'faulty') {
            return "{$port->name} port {$port->port_number} is marked faulty.";
        }
        if ($port->status === 'occupied' && ! ($clientId && (int) $port->client_id === $clientId)) {
            return "{$port->name} port {$port->port_number} is already occupied.";
        }
        $reserved = self::reservedPorts($branch, $exceptRequestId);
        if (isset($reserved[$portId])) {
            return "{$port->name} port {$port->port_number} is already reserved by request {$reserved[$portId]}.";
        }

        return null;
    }

    /** Add the occupied / reserved / faulty / available counts to boxes. */
    private static function withCounts(array $boxes, array $reserved): array
    {
        if ($boxes === []) {
            return [];
        }

        $ids = array_map(fn ($box) => (int) $box->id, $boxes);
        $ports = DB::table('nap_ports')->whereIn('nap_box_id', $ids)->get(['id', 'nap_box_id', 'status']);
        $counts = [];
        foreach ($ports as $port) {
            $boxId = (int) $port->nap_box_id;
            $counts[$boxId] ??= ['occupied' => 0, 'faulty' => 0, 'reserved' => 0, 'available' => 0, 'rows' => 0];
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

        return array_map(function ($box) use ($counts): array {
            $c = $counts[(int) $box->id] ?? ['occupied' => 0, 'faulty' => 0, 'reserved' => 0, 'available' => 0, 'rows' => 0];

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
                'reserved_ports' => $c['reserved'],
                'faulty_ports' => $c['faulty'],
                'available_ports' => $c['available'],
            ];
        }, $boxes);
    }

    /** Create a box with its numbered ports, all available. */
    public static function create(string $branch, string $name, string $barangay, ?string $location, int $totalPorts): int
    {
        return DB::transaction(function () use ($branch, $name, $barangay, $location, $totalPorts): int {
            $boxId = (int) DB::table('nap_boxes')->insertGetId([
                'name' => $name, 'branch_name' => $branch, 'barangay_name' => $barangay, 'location' => $location,
                'total_ports' => $totalPorts, 'status' => 'active', 'archived' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            self::addPorts($boxId, 1, $totalPorts);

            return $boxId;
        });
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
