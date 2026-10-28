<?php

use App\Support\BranchScope;
use App\Support\ClientRegistration;
use App\Support\Installations;
use App\Support\NapBoxes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Installation reports from the employee app. After an employee installs a client they report the client's details, the NAP box
 * and port, the coordinates and the items they bring back. The branch admin checks the report, may edit any detail and confirms
 * it: only then the client is added, the port connected and the stock updated. The super admin only looks (with a branch filter).
 */
return function (Request $request): array {
    $themeCssVersion = @filemtime(public_path('css/jftech-theme.css')) ?: 1;
    $success = '';
    $error = '';
    $openReport = 0;

    $filterStatus = (string) $request->query('status', 'submitted');
    abort_unless(in_array($filterStatus, ['all', 'submitted', 'confirmed', 'rejected'], true), 404);

    $canDecide = (bool) auth()->user()?->isBranchAdmin();
    $ownBranch = BranchScope::current();
    $branch = BranchScope::resolve($request->query('branch'));

    if ($request->isMethod('post')) {
        try {
            if (! $canDecide) {
                throw new DomainException('Only a branch admin confirms the installation reports of their branch.');
            }
            $id = (int) $request->input('installation_id');
            $openReport = $id;
            $decision = (string) $request->input('decision');

            if ($decision === 'confirm') {
                $clientId = Installations::confirm($id, [
                    'full_name' => $request->input('full_name'), 'contact_number' => $request->input('contact_number'),
                    'email' => $request->input('email'), 'barangay_name' => $request->input('barangay_name'),
                    'exact_address' => $request->input('exact_address'), 'installation_date' => $request->input('installation_date'),
                    'internet_plan_id' => $request->input('internet_plan_id'), 'payment_mode' => $request->input('payment_mode'),
                    'ip_address' => $request->input('ip_address'), 'facebook_account' => $request->input('facebook_account'),
                    'coordinates' => $request->input('coordinates'), 'nap_port_id' => $request->input('nap_port_id'),
                    'accepted' => (array) $request->input('accepted', []), 'admin_remarks' => $request->input('admin_remarks'),
                ], (int) auth()->id(), (string) $ownBranch);
                $client = DB::table('clients')->where('id', $clientId)->first(['full_name', 'client_code']);
                $success = "{$client->full_name} ({$client->client_code}) was added as a client. The NAP port is connected and the inventory is updated.";
                $openReport = 0;
            } elseif ($decision === 'reject') {
                Installations::reject($id, (string) $request->input('admin_remarks'), (int) auth()->id(), (string) $ownBranch);
                $success = 'The report was rejected. The employee can report again.';
                $openReport = 0;
            } else {
                throw new DomainException('Unknown decision.');
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }
    }

    $reports = Installations::reports($branch, null, $filterStatus);

    // A refused form is shown again with what the admin had typed.
    if ($openReport) {
        $typed = [
            'client_name' => 'full_name', 'contact_number' => 'contact_number', 'email' => 'email', 'barangay_name' => 'barangay_name',
            'exact_address' => 'exact_address', 'installation_date' => 'installation_date', 'internet_plan_id' => 'internet_plan_id',
            'payment_mode' => 'payment_mode', 'ip_address' => 'ip_address', 'facebook_account' => 'facebook_account',
            'coordinates' => 'coordinates', 'nap_port_id' => 'nap_port_id',
        ];
        foreach ($reports as $index => $report) {
            if ($report['id'] === $openReport) {
                foreach ($typed as $field => $input) {
                    if ($request->has($input)) {
                        $reports[$index][$field] = $request->input($input);
                    }
                }
                $reports[$index]['admin_remarks'] = $request->input('admin_remarks');
            }
        }
    }

    return [
        'themeCssVersion' => $themeCssVersion,
        'installationsCssVersion' => @filemtime(public_path('css/installations.css')) ?: 1,
        'napPickerCssVersion' => @filemtime(public_path('css/nap_picker.css')) ?: 1,
        'napPickerJsVersion' => @filemtime(public_path('js/nap_picker.js')) ?: 1,
        'filterStatus' => $filterStatus,
        'reports' => $reports,
        'counts' => Installations::counts($branch),
        'branch' => $branch,
        'inventoryBranches' => array_keys(config('bohol_branches', [])),
        'canDecide' => $canDecide,
        'openReport' => $openReport,
        'plans' => DB::table('internet_plans')->where('status', 'active')->where('archived', 0)->orderBy('monthly_price')->get(['id', 'plan_name', 'speed_mbps', 'monthly_price'])->all(),
        'paymentModes' => ClientRegistration::PAYMENT_MODES,
        'branchBarangays' => $ownBranch ? NapBoxes::barangaysOf($ownBranch) : [],
        'success' => $success,
        'error' => $error,
    ];
};
