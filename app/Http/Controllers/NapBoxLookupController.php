<?php

namespace App\Http\Controllers;

use App\Support\BranchScope;
use App\Support\NapBoxes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The NAP boxes of one barangay with their ports, for the pickers of the admin pages (Register New Client, confirming an
 * installation report). A branch admin always gets their own branch, whatever the address bar says.
 */
class NapBoxLookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $barangay = trim((string) $request->query('barangay', ''));
        if ($barangay === '') {
            return response()->json(['boxes' => []]);
        }

        $branch = BranchScope::resolve($request->query('branch'));
        $except = (int) $request->query('except', 0) ?: null;

        return response()->json(['boxes' => NapBoxes::inBarangay($branch, $barangay, $except)]);
    }
}
