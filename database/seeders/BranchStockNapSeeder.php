<?php

namespace Database\Seeders;

use App\Support\NapBoxes;
use App\Support\StockMovements;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The stock room and the NAP boxes of every branch.
 *
 * - Every branch gets the usual fibre-to-the-home stock (ONU, router, drop cable, splitter, connectors, ...), sized by how many clients
 *   the branch has, with an opening-stock entry in the stock history and a restock of the fast-moving items. An item the branch
 *   already has (same name and category) is kept as it is. A few items sit below their reorder level so the low-stock flag shows.
 * - Every barangay of every branch gets a NAP box (NAP-<BRANCH>-<n>, 8 ports, 16 in a poblacion) hanging at the place of the barangay
 *   (database/seeders/data/barangay_coordinates.json, looked up on OpenStreetMap: near the barangay, not the exact pole). Each box
 *   is taken out of the branch's "NAP Box" stock with a "NAP Box Installed" entry, so the stock count and the NAP page agree.
 * - The clients of the barangay are connected to the ports of its box (a port shows who is plugged in), and a client without map
 *   coordinates gets a place a short walk from its box. Now and then a free port is marked faulty.
 *
 *   php artisan db:seed --class=BranchStockNapSeeder
 *
 * Running it again changes nothing: items and barangays that already have their rows are skipped.
 */
class BranchStockNapSeeder extends Seeder
{
    /** Where the stock room opened, in days before today (every box and every movement comes after it). */
    private const OPENING_DAYS_AGO = 75;

    /** Spare NAP boxes a branch keeps on the shelf besides the ones it hangs. */
    private const SPARE_BOXES = [4, 9];

    /** The centre of each branch's town, for a barangay the map did not know and as the last resort. */
    private const TOWNS = [
        'Albur' => [9.6104, 123.9569], 'Antequera' => [9.7897, 123.9014], 'Baclayon' => [9.6203, 123.9139],
        'Balilihan' => [9.7572, 123.9753], 'Batuan' => [9.7792, 124.1475], 'Bilar' => [9.7142, 124.1056],
        'Carmen' => [9.8197, 124.1953], 'Catigbian' => [9.8858, 124.0378], 'Clarin' => [9.9617, 124.0189],
        'Cortes' => [9.7522, 123.8683], 'Inabanga' => [10.0228, 124.0661], 'Loboc' => [9.6392, 124.0306],
        'Loon' => [9.8100, 123.7961], 'Maribojoc' => [9.7381, 123.8394], 'Sagbayan' => [9.9139, 124.0953],
        'Sevilla' => [9.7000, 124.0300], 'Sikatuna' => [9.6933, 123.9700], 'Tagbilaran' => [9.6472, 123.8533],
        'Tubigon' => [9.9546, 123.9617],
    ];

    private const LANDMARKS = [
        'beside the barangay hall', 'in front of the chapel', 'near the elementary school', 'by the barangay health center',
        'near the basketball court', 'at the plaza', 'near the public market', 'beside the multi-purpose hall', 'near the day care center',
    ];

    /**
     * name => [category, unit price, supplier, description, quantity base, quantity per client, reorder level]
     *
     * @var array<string, array{0: string, 1: float, 2: string, 3: string, 4: int, 5: float, 6: int}>
     */
    private const ITEMS = [
        'ONU' => ['Networking Equipment', 950, 'Bohol Fiber Supply', 'XPON ONU given to each new client', 14, 0.25, 10],
        'Router' => ['Networking Equipment', 700, 'VisNet Wholesale', 'Wireless router', 12, 0.2, 10],
        'Access Point' => ['Networking Equipment', 1800, 'VisNet Wholesale', 'Ceiling access point for shops and offices', 4, 0.04, 3],
        'Splitter' => ['Networking Equipment', 350, 'Bohol Fiber Supply', '1x8 PLC splitter', 10, 0.12, 6],
        'Fiber Cable' => ['Cabling', 9, 'Bohol Fiber Supply', 'FTTH drop cable, 1 core, sold per meter', 800, 14, 300],
        'Patch Cord' => ['Cabling', 50, 'Bohol Fiber Supply', 'Fiber optic patch cord', 60, 0.8, 20],
        'Pigtail' => ['Cabling', 25, 'Bohol Fiber Supply', 'Fiber pigtail connector', 60, 0.8, 20],
        'Fast Connector' => ['Accessories', 35, 'Bohol Fiber Supply', 'SC/APC fast connector', 80, 1.2, 30],
        'Splice Sleeve' => ['Accessories', 5, 'Bohol Fiber Supply', 'Fiber splice protection sleeve', 100, 1.5, 40],
        'Cable Tie' => ['Accessories', 2, 'Island Hardware', 'Plastic cable tie', 600, 4, 100],
        'Cable Clamp' => ['Accessories', 10, 'Island Hardware', 'Cable clamp holder', 300, 3, 60],
        'LAN Connector' => ['Accessories', 6, 'Island Hardware', 'RJ45 connector', 150, 1.5, 40],
        'UPS' => ['Power Equipment', 750, 'VisNet Wholesale', 'Uninterruptible power supply for the office and the OLT', 4, 0.03, 3],
    ];

    /** Items that are bought again during the month. */
    private const RESTOCKED = ['Fiber Cable', 'Patch Cord', 'Pigtail', 'Fast Connector', 'ONU'];

    /** @var array<string, array<string, array{lat: float, lng: float, precision: string}>> */
    private array $places = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('The sample stock and NAP boxes are only for a development or demo database.');
        }

        $file = database_path('seeders/data/barangay_coordinates.json');
        $this->places = is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];

        $branches = array_keys(config('bohol_branches', []));
        $superAdmin = (int) (DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id') ?? 0) ?: null;
        $admins = DB::table('users')->where('role', 'admin')->where('archived', 0)->orderBy('id')->pluck('id', 'branch_name')->all();
        $opening = Carbon::today()->subDays(self::OPENING_DAYS_AGO)->setTime(8, 30);

        $boxes = 0;
        $items = 0;
        foreach ($branches as $branchIndex => $branch) {
            $by = $admins[$branch] ?? $superAdmin;
            $clientCount = DB::table('clients')->where('branch_name', $branch)->count();

            $items += $this->stockRoom($branch, $clientCount, $by, $opening);
            $boxes += $this->napBoxes($branch, $branchIndex, $by, $opening);
        }

        $this->command?->info("Stock: {$items} item(s) added. NAP boxes: {$boxes} installed and their clients connected.");
    }

    // ---- stock ---------------------------------------------------------------------------------

    /** @return int items created */
    private function stockRoom(string $branch, int $clientCount, ?int $by, Carbon $opening): int
    {
        $prefix = strtoupper(substr($branch, 0, 3));
        $created = 0;
        $number = (int) DB::table('inventory')->where('branch_name', $branch)->count();

        foreach (self::ITEMS as $name => [$category, $price, $supplier, $description, $base, $perClient, $reorder]) {
            if (DB::table('inventory')->where('branch_name', $branch)->where('item_name', $name)->where('category', $category)->exists()) {
                continue;
            }
            $quantity = (int) round($base + $clientCount * $perClient);
            // One item in seven runs low, so the low-stock flag has something to show.
            if (crc32("low|{$branch}|{$name}") % 7 === 0) {
                $quantity = max(0, $reorder - 2 - (crc32("lowby|{$branch}|{$name}") % 3));
            }
            $id = $this->addItem($branch, $name, $category, $price, $supplier, $description, $quantity, $reorder, ++$number, $prefix, $by, $opening);

            if (in_array($name, self::RESTOCKED, true) && $quantity >= $reorder) {
                $extra = (int) round($quantity * 0.2);
                $when = $opening->copy()->addDays(30 + crc32("restock|{$branch}|{$name}") % 12)->setTime(10, 15);
                DB::table('inventory')->where('id', $id)->update(['quantity' => $quantity + $extra]);
                StockMovements::record($id, $extra, $quantity + $extra, 'stock_received', 'Restocked from the supplier.', $by, [], $when);
            }
            $created++;
        }

        return $created;
    }

    private function addItem(string $branch, string $name, string $category, float|int $price, string $supplier, string $description, int $quantity, int $reorder, int $number, string $prefix, ?int $by, Carbon $opening): int
    {
        $id = (int) DB::table('inventory')->insertGetId([
            'item_name' => $name, 'sku' => sprintf('SKU-%s-%03d', $prefix, $number), 'category' => $category, 'quantity' => $quantity,
            'unit_price' => $price, 'description' => $description, 'supplier' => $supplier, 'reorder_level' => $reorder,
            'status' => 'active', 'archived' => 0, 'branch_name' => $branch, 'created_at' => $opening,
        ]);
        StockMovements::record($id, $quantity, $quantity, 'opening_balance', 'Opening stock of the branch.', $by, [], $opening);

        return $id;
    }

    // ---- NAP boxes -----------------------------------------------------------------------------

    /** @return int boxes installed */
    private function napBoxes(string $branch, int $branchIndex, ?int $by, Carbon $opening): int
    {
        $prefix = strtoupper(substr($branch, 0, 3));
        $barangays = array_values((array) (config('bohol_barangays')[$branch] ?? []));
        $taken = DB::table('nap_boxes')->where('branch_name', $branch)->pluck('barangay_name')->map(fn ($name) => mb_strtolower($name))->all();

        // The boxes to hang, in the order they went up: the barangay's oldest client was installed after its box.
        $plans = [];
        foreach ($barangays as $index => $barangay) {
            if (in_array(mb_strtolower($barangay), $taken, true)) {
                continue;
            }
            $clients = DB::table('clients')->where('branch_name', $branch)->where('registered_barangay_name', $barangay)->where('archived', 0)
                ->whereNotIn('id', DB::table('nap_ports')->whereNotNull('client_id')->select('client_id'))->orderBy('id')->get();
            $firstClient = $clients->min('installation_date');
            $hung = $firstClient ? Carbon::parse($firstClient)->subDays(3 + $index % 4) : Carbon::today()->subDays(20 + $index % 15);
            $plans[] = ['barangay' => $barangay, 'index' => $index, 'clients' => $clients, 'at' => $hung->max($opening->copy()->addDays(5))->setTime(9, 0)];
        }
        if ($plans === []) {
            return 0;
        }
        usort($plans, fn (array $a, array $b) => [$a['at'], $a['index']] <=> [$b['at'], $b['index']]);

        $stockId = $this->napStockItem($branch, count($plans), $by, $opening);
        $balance = (int) DB::table('inventory')->where('id', $stockId)->value('quantity');

        foreach ($plans as $plan) {
            $barangay = $plan['barangay'];
            $index = $plan['index'];
            $ports = preg_match('/poblacion|centro/i', $barangay) ? 16 : 8;
            $name = sprintf('NAP-%s-%02d', $prefix, $index + 1);
            [$latitude, $longitude] = $this->placeOf($branch, $barangay, $index);
            $purok = 1 + crc32("purok|{$branch}|{$barangay}") % 7;
            $landmark = self::LANDMARKS[crc32("landmark|{$branch}|{$barangay}") % count(self::LANDMARKS)];
            $hung = $plan['at'];

            $boxId = (int) DB::table('nap_boxes')->insertGetId([
                'name' => $name, 'branch_name' => $branch, 'barangay_name' => $barangay, 'location' => "Purok {$purok}, {$landmark}",
                'latitude' => $latitude, 'longitude' => $longitude, 'total_ports' => $ports, 'status' => 'active', 'archived' => false,
                'created_at' => $hung, 'updated_at' => $hung,
            ]);

            $assigned = $this->connectClients($boxId, $ports, $plan['clients'], $branch, $barangay, $latitude, $longitude);
            $faulty = crc32("faulty|{$branch}|{$barangay}") % 9 === 0 ? $this->freePort($ports, $assigned, "{$branch}|{$barangay}") : null;
            $rows = [];
            for ($number = 1; $number <= $ports; $number++) {
                $client = $assigned[$number] ?? null;
                $rows[] = [
                    'nap_box_id' => $boxId, 'port_number' => $number, 'status' => $client ? 'occupied' : ($number === $faulty ? 'faulty' : 'available'),
                    'client_id' => $client ? $client->id : null,
                    'occupied_at' => $client && $client->installation_date ? Carbon::parse($client->installation_date)->setTime(10, 0) : null,
                    'created_at' => $hung, 'updated_at' => $hung,
                ];
            }
            DB::table('nap_ports')->insert($rows);

            $balance--;
            DB::table('inventory')->where('id', $stockId)->update(['quantity' => $balance]);
            StockMovements::record($stockId, -1, $balance, 'nap_installed', "{$name} installed at {$barangay}.", $by, ['nap_box_id' => $boxId], $hung);
        }

        return count($plans);
    }

    /** The branch's "NAP Box" stock item; created when missing, and topped up when it holds fewer boxes than are to be hung. */
    private function napStockItem(string $branch, int $toHang, ?int $by, Carbon $opening): int
    {
        $prefix = strtoupper(substr($branch, 0, 3));
        $item = DB::table('inventory')->where('branch_name', $branch)->where('category', NapBoxes::STOCK_CATEGORY)->where('archived', 0)->orderBy('id')->first();
        $spare = self::SPARE_BOXES[0] + crc32("spare|{$branch}") % (self::SPARE_BOXES[1] - self::SPARE_BOXES[0] + 1);

        if (! $item) {
            return $this->addItem(
                $branch, 'NAP Box', NapBoxes::STOCK_CATEGORY, 1450, 'Bohol Fiber Supply', 'Fiber NAP box with its splitter tray, for a pole or a wall',
                $toHang + $spare, 3, (int) DB::table('inventory')->where('branch_name', $branch)->count() + 1, $prefix, $by, $opening,
            );
        }
        if ((int) $item->quantity < $toHang) {
            $add = $toHang - (int) $item->quantity;
            DB::table('inventory')->where('id', $item->id)->update(['quantity' => $toHang]);
            StockMovements::record((int) $item->id, $add, $toHang, 'stock_received', 'NAP boxes received for the barangays.', $by, [], $opening->copy()->addDay());
        }

        return (int) $item->id;
    }

    /**
     * Plugs the clients of a barangay into ports (not always the first ones) and gives a client that has no map place one a short walk
     * from the box.
     *
     * @return array<int, object> port number => client
     */
    private function connectClients(int $boxId, int $ports, $clients, string $branch, string $barangay, float $latitude, float $longitude): array
    {
        $assigned = [];
        $start = crc32("port|{$branch}|{$barangay}") % $ports;
        foreach ($clients->values() as $position => $client) {
            if ($position >= $ports) {
                break; // the rest of the barangay would need a second box
            }
            $number = (($start + $position * 3) % $ports) + 1;
            while (isset($assigned[$number])) {
                $number = $number % $ports + 1;
            }
            $assigned[$number] = $client;

            if ($client->latitude === null && $client->longitude === null) {
                [$lat, $lng] = $this->offset($latitude, $longitude, 20 + crc32("away|{$client->id}") % 120, crc32("bearing|{$client->id}") % 360);
                DB::table('clients')->where('id', $client->id)->update(['latitude' => $lat, 'longitude' => $lng]);
            }
        }

        return $assigned;
    }

    private function freePort(int $ports, array $assigned, string $seed): ?int
    {
        $free = array_values(array_diff(range(1, $ports), array_keys($assigned)));

        return $free === [] ? null : $free[crc32("faultyport|{$seed}") % count($free)];
    }

    // ---- places --------------------------------------------------------------------------------

    /**
     * Where the box hangs: the barangay from the map when it is known. A barangay the map only knew as "the town" gets a spot near the
     * town centre, a little different for each, so the boxes do not sit on one point.
     *
     * @return array{0: float, 1: float}
     */
    private function placeOf(string $branch, string $barangay, int $index): array
    {
        $town = self::TOWNS[$branch] ?? [9.85, 124.1];
        $place = $this->places[$branch][$barangay] ?? null;

        if ($place && isset($place['lat'], $place['lng']) && ($place['precision'] ?? '') === 'barangay') {
            [$lat, $lng] = $this->offset((float) $place['lat'], (float) $place['lng'], 30 + crc32("pole|{$branch}|{$barangay}") % 90, crc32("polebearing|{$branch}|{$barangay}") % 360);
        } else {
            $centre = $place && isset($place['lat'], $place['lng']) ? [(float) $place['lat'], (float) $place['lng']] : $town;
            // A golden-angle spiral round the centre: 60 m for the first, growing slowly.
            [$lat, $lng] = $this->offset($centre[0], $centre[1], 60 + 45 * sqrt($index + 1), fmod($index * 137.508, 360));
        }

        return [round($lat, 7), round($lng, 7)];
    }

    /** A point $meters away from another one, at a bearing in degrees from north. */
    private function offset(float $latitude, float $longitude, float $meters, float $bearing): array
    {
        $radians = deg2rad($bearing);

        return [
            round($latitude + ($meters * cos($radians)) / 111_320, 7),
            round($longitude + ($meters * sin($radians)) / (111_320 * cos(deg2rad($latitude))), 7),
        ];
    }
}
