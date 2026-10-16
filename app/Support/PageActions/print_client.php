<?php

return function (\Illuminate\Http\Request $request): array {
    $clientId = (int) $request->query('id');
    abort_unless($clientId > 0, 404);
    \App\Support\BranchScope::abortUnlessClient($clientId);

    $client = \Illuminate\Support\Facades\DB::table('clients as c')
        ->leftJoin('barangays as b', 'c.barangay_id', '=', 'b.id')
        ->select('c.*')
        ->selectRaw('COALESCE(c.registered_barangay_name, b.barangay_name) AS barangay_name')
        ->where('c.id', $clientId)
        ->first();

    abort_unless($client, 404);

    return compact('client');
};
