<?php

use App\Support\BranchScope;
use App\Support\NapBoxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NAP boxes and their ports. A branch admin keeps the boxes of their own branch (add, change, archive, and what is
 * connected to each port); the super admin looks at every branch and can pick one. A NAP box is also stock: a new box is taken
 * from the "NAP Box" items of the inventory, and the inventory shows what was used on each box.
 */
return function (Request $request): array {
    $themeCssVersion = @filemtime(public_path('css/jftech-theme.css')) ?: 1;
    $napBoxesCssVersion = @filemtime(public_path('css/nap_boxes.css')) ?: 1;

    $success = '';
    $error = '';
    $user = auth()->user();
    $canManage = (bool) $user?->isBranchAdmin();
    $ownBranch = BranchScope::current();
    $branch = BranchScope::resolve($request->query('branch'));
    $branches = array_keys(config('bohol_branches', []));
    $branchBarangays = $ownBranch ? NapBoxes::barangaysOf($ownBranch) : [];

    $showArchived = $request->query('view') === 'archived';
    $filterBarangay = trim((string) $request->query('barangay', ''));
    $search = trim((string) $request->query('q', ''));
    $selectedBoxId = (int) $request->query('box', 0);

    // The branch admin's own box (404 for any other branch's box), or null.
    $ownBox = static function (int $id) use ($ownBranch): ?object {
        return DB::table('nap_boxes')->where('id', $id)->whereRaw('LOWER(branch_name) = LOWER(?)', [(string) $ownBranch])->first();
    };

    if ($request->isMethod('post')) {
        try {
            if (! $canManage) {
                throw new DomainException('Only a branch admin keeps the NAP boxes of their branch.');
            }
            $action = (string) $request->input('action');

            if ($action === 'save_box') {
                $boxId = (int) $request->input('box_id');
                $box = $boxId ? $ownBox($boxId) : null;
                if ($boxId && ! $box) {
                    abort(404);
                }

                $name = trim((string) $request->input('name'));
                $location = trim((string) $request->input('location')) ?: null;
                $totalPorts = (int) $request->input('total_ports');
                $status = (string) $request->input('status', 'active');
                $barangay = trim((string) $request->input('barangay'));
                foreach ($branchBarangays as $option) {
                    if (strcasecmp($option, $barangay) === 0) {
                        $barangay = $option;
                        break;
                    }
                }

                if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
                    throw new DomainException('Enter the NAP box name, for example NAP-01.');
                }
                if ($barangay === '' || ($branchBarangays !== [] && ! in_array($barangay, $branchBarangays, true))) {
                    throw new DomainException("Choose a barangay of {$ownBranch}.");
                }
                if ($totalPorts < 1 || $totalPorts > 96) {
                    throw new DomainException('A NAP box has 1 to 96 ports.');
                }
                if (! in_array($status, ['active', 'inactive'], true)) {
                    $status = 'active';
                }
                // The photo comes as a picture the browser has already shrunk (a data address); it is checked before anything is saved.
                $photoData = trim((string) $request->input('photo_data', ''));
                if ($photoData !== '') {
                    NapBoxes::checkPhoto($photoData);
                }
                $duplicate = DB::table('nap_boxes')->whereRaw('LOWER(branch_name) = LOWER(?)', [$ownBranch])->whereRaw('LOWER(name) = LOWER(?)', [$name])
                    ->when($boxId, fn ($q) => $q->where('id', '!=', $boxId))->exists();
                if ($duplicate) {
                    throw new DomainException("{$name} already exists in {$ownBranch}.");
                }

                DB::transaction(function () use ($box, $boxId, $ownBranch, $name, $barangay, $location, $totalPorts, $status, $request, $photoData): void {
                    if (! $box) {
                        // A new box is installed from the stock: it takes one NAP Box out of the branch's inventory.
                        $newBoxId = NapBoxes::installFromStock($ownBranch, $name, $barangay, $location, $totalPorts, (int) $request->input('stock_item_id') ?: null, auth()->id());
                        if ($photoData !== '') {
                            NapBoxes::savePhoto($newBoxId, $photoData);
                        }

                        return;
                    }

                    $current = (int) $box->total_ports;
                    if ($totalPorts < $current) {
                        // Only the highest-numbered ports can go, and only while nobody uses them.
                        $reserved = NapBoxes::reservedPorts($ownBranch);
                        $doomed = DB::table('nap_ports')->where('nap_box_id', $boxId)->where('port_number', '>', $totalPorts)->get();
                        foreach ($doomed as $port) {
                            if ($port->status === 'occupied' || isset($reserved[(int) $port->id])) {
                                throw new DomainException("Port {$port->port_number} is in use, so the box cannot shrink to {$totalPorts} ports.");
                            }
                        }
                        DB::table('nap_ports')->where('nap_box_id', $boxId)->where('port_number', '>', $totalPorts)->delete();
                    } elseif ($totalPorts > $current) {
                        NapBoxes::addPorts($boxId, $current + 1, $totalPorts);
                    }
                    DB::table('nap_boxes')->where('id', $boxId)->update([
                        'name' => $name, 'barangay_name' => $barangay, 'location' => $location, 'total_ports' => $totalPorts, 'status' => $status, 'updated_at' => now(),
                    ]);
                    if ($photoData !== '') {
                        NapBoxes::savePhoto($boxId, $photoData);
                    } elseif ($request->boolean('remove_photo')) {
                        NapBoxes::removePhoto($boxId);
                    }
                });
                $success = $box ? "{$name} was updated." : "{$name} was added with {$totalPorts} ports. One NAP Box was taken from your inventory.";
            } elseif (in_array($action, ['archive_box', 'restore_box'], true)) {
                $box = $ownBox((int) $request->input('box_id')) ?? abort(404);
                if ($action === 'archive_box') {
                    $reservedIds = array_keys(NapBoxes::reservedPorts($ownBranch));
                    $busy = DB::table('nap_ports')->where('nap_box_id', $box->id)->where(function ($q) use ($reservedIds) {
                        $q->where('status', 'occupied')->orWhereIn('id', $reservedIds ?: [0]);
                    })->exists();
                    if ($busy) {
                        throw new DomainException("{$box->name} still has connected or reserved ports, so it cannot be archived.");
                    }
                }
                DB::table('nap_boxes')->where('id', $box->id)->update(['archived' => $action === 'archive_box', 'updated_at' => now()]);
                $success = $action === 'archive_box' ? "{$box->name} was archived." : "{$box->name} was restored.";
            } elseif ($action === 'port') {
                $port = DB::table('nap_ports')->join('nap_boxes', 'nap_boxes.id', '=', 'nap_ports.nap_box_id')
                    ->where('nap_ports.id', (int) $request->input('port_id'))->whereRaw('LOWER(nap_boxes.branch_name) = LOWER(?)', [$ownBranch])
                    ->first(['nap_ports.*', 'nap_boxes.name as box_name']) ?? abort(404);
                $selectedBoxId = (int) $port->nap_box_id;
                $label = "{$port->box_name} port {$port->port_number}";
                $reserved = NapBoxes::reservedPorts($ownBranch);
                $do = (string) $request->input('do');

                DB::transaction(function () use ($do, $port, $label, $reserved, $request, $ownBranch, &$success): void {
                    $locked = DB::table('nap_ports')->where('id', $port->id)->lockForUpdate()->first();
                    if ($do === 'faulty') {
                        if ($locked->status !== 'available' || isset($reserved[(int) $port->id])) {
                            throw new DomainException("{$label} is in use and cannot be marked faulty.");
                        }
                        DB::table('nap_ports')->where('id', $port->id)->update(['status' => 'faulty', 'updated_at' => now()]);
                        $success = "{$label} is marked faulty.";
                    } elseif ($do === 'available') {
                        if ($locked->status !== 'faulty') {
                            throw new DomainException("{$label} is not faulty.");
                        }
                        DB::table('nap_ports')->where('id', $port->id)->update(['status' => 'available', 'updated_at' => now()]);
                        $success = "{$label} is available again.";
                    } elseif ($do === 'free') {
                        if ($locked->status !== 'occupied') {
                            throw new DomainException("{$label} is not connected to a client.");
                        }
                        DB::table('nap_ports')->where('id', $port->id)->update(['status' => 'available', 'client_id' => null, 'occupied_at' => null, 'updated_at' => now()]);
                        $success = "{$label} is free again.";
                    } elseif ($do === 'assign') {
                        if ($locked->status !== 'available' || isset($reserved[(int) $port->id])) {
                            throw new DomainException("{$label} is not available.");
                        }
                        // The client is picked by typing, so the label is "Name — CODE".
                        $typed = trim((string) $request->input('client_label'));
                        $code = trim((string) preg_replace('/^.*\s—\s/u', '', $typed));
                        $client = $code === '' ? null : DB::table('clients')->where('client_code', $code)->whereRaw('LOWER(branch_name) = LOWER(?)', [$ownBranch])->where('archived', 0)->first();
                        if (! $client) {
                            throw new DomainException('Choose a client from the suggestions.');
                        }
                        if (DB::table('nap_ports')->where('client_id', $client->id)->exists()) {
                            throw new DomainException("{$client->full_name} is already connected to a NAP port.");
                        }
                        DB::table('nap_ports')->where('id', $port->id)->update(['status' => 'occupied', 'client_id' => $client->id, 'occupied_at' => now(), 'updated_at' => now()]);
                        $success = "{$client->full_name} is now connected to {$label}.";
                    } else {
                        throw new DomainException('Unknown port action.');
                    }
                });
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }
    }

    // The list of boxes (with counts) and the stats above it.
    $boxes = NapBoxes::boxes($branch, $filterBarangay ?: null, $search, 'all', true, $showArchived);
    $stats = [
        'boxes' => count($boxes),
        'ports' => array_sum(array_column($boxes, 'total_ports')),
        'occupied' => array_sum(array_column($boxes, 'occupied_ports')),
        'available' => array_sum(array_column($boxes, 'available_ports')),
    ];

    // The box whose ports are open below the list.
    $selectedBox = $selectedBoxId ? NapBoxes::box($selectedBoxId, $branch) : null;
    $assignableClients = [];
    if ($selectedBox && $canManage && empty($selectedBox['archived'])) {
        $assignableClients = DB::table('clients')
            ->whereRaw('LOWER(branch_name) = LOWER(?)', [$ownBranch])->where('archived', 0)
            ->whereNotIn('id', DB::table('nap_ports')->whereNotNull('client_id')->select('client_id'))
            ->orderBy('full_name')->limit(500)
            ->get(['full_name', 'client_code'])->map(fn ($client) => $client->full_name . ' — ' . $client->client_code)->all();
    }
    $barangaySuggestions = $branchBarangays !== [] ? $branchBarangays : array_values(array_unique(array_column($boxes, 'barangay_name')));

    // The NAP Box stock a new box is taken from (the branch admin's own branch), and the boxes that are still in stock.
    $napStockItems = $canManage ? NapBoxes::stockItems($ownBranch) : [];
    $napInStock = array_sum(array_column(NapBoxes::stockItems($branch), 'quantity'));

    return get_defined_vars();
};
