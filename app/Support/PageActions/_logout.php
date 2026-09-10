<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
session_start(); session_destroy(); echo 'ok';
    return get_defined_vars();
};
