<?php

namespace App\Http\Controllers;

use App\Support\Installations;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The installation report of the employee app (see App\Support\Installations). Every route is behind the employee token,
 * so the employee is always the one who signed in and the report always belongs to their branch.
 */
class InstallationApiController extends Controller
{
    /** What the form offers: barangays of the branch, plans, modes of payment, and the items the employee took. */
    public function options(Request $request): JsonResponse
    {
        return response()->json(['success' => true] + Installations::options($this->employee($request)));
    }

    /** Where a barangay of the employee's branch is, so the coordinates of the report fill in by themselves. */
    public function location(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json(['success' => true] + LocationController::place((string) $employee->branch_name, (string) $request->query('barangay', '')));
    }

    /** The reports of the employee, newest first, with their status. */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json(['success' => true, 'data' => Installations::reports((string) $employee->branch_name, (int) $employee->id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        if (trim((string) $employee->branch_name) === '') {
            return response()->json(['success' => false, 'message' => 'Your account has no branch yet. Ask your admin.'], 422);
        }

        $input = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'max:100'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'exact_address' => ['required', 'string', 'max:255'],
            'internet_plan_id' => ['required', 'integer'],
            'payment_mode' => ['required', 'string', 'max:20'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'facebook_account' => ['nullable', 'string', 'max:150'],
            'coordinates' => ['required', 'string', 'max:300'],
            'installation_date' => ['nullable', 'date_format:Y-m-d'],
            'nap_port_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'request_ids' => ['nullable', 'array', 'max:50'],
            'request_ids.*' => ['integer'],
            'returns' => ['nullable', 'array', 'max:50'],
            'returns.*.inventory_id' => ['required', 'integer'],
            'returns.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'returns.*.item_condition' => ['nullable', 'string', 'max:30'],
        ]);
        $input['installation_date'] = $input['installation_date'] ?? now()->toDateString();

        try {
            $id = Installations::submit($employee, $input);
        } catch (DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Installation report submitted. Your admin will check it and add the client.',
            'id' => $id,
        ], 201);
    }

    private function employee(Request $request): object
    {
        return $request->attributes->get('employee');
    }
}
