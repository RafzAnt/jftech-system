<?php

use App\Support\BranchScope;
use App\Support\WorkRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Installation and repair requests from the employee app. The branch admin reviews the requests of their own
 * branch (approve, return for correction, reject) and confirms the finished work; the super admin looks at every
 * branch and can pick one.
 */
return function (Request $request): array {
    $themeCssVersion = @filemtime(public_path('css/jftech-theme.css')) ?: 1;
    $workRequestsCssVersion = @filemtime(public_path('css/work_requests.css')) ?: 1;

    $success = '';
    $error = '';
    $canReview = (bool) auth()->user()?->isBranchAdmin();
    $branch = BranchScope::resolve($request->query('branch'));
    $branches = array_keys(config('bohol_branches', []));

    // The barangays to pick from: those of the branch (every branch's when the super admin looks at all of them).
    $barangaySuggestions = $branch !== null && $branch !== ''
        ? \App\Support\NapBoxes::barangaysOf($branch)
        : array_values(array_unique(array_merge(...array_map(fn ($name) => \App\Support\NapBoxes::barangaysOf($name), $branches))));
    sort($barangaySuggestions);
    $barangay = '';
    foreach ($barangaySuggestions as $suggestion) {
        if (strcasecmp($suggestion, trim((string) $request->query('barangay', ''))) === 0) {
            $barangay = $suggestion;
            break;
        }
    }

    $tabs = [
        'pending' => ['label' => 'Pending Approval', 'statuses' => ['pending_approval']],
        'returned' => ['label' => 'Returned', 'statuses' => ['returned']],
        'approved' => ['label' => 'Approved', 'statuses' => ['approved']],
        'awaiting' => ['label' => 'Awaiting Confirmation', 'statuses' => ['work_completed']],
        'confirmed' => ['label' => 'Confirmed', 'statuses' => ['confirmed']],
        'closed' => ['label' => 'Rejected / Cancelled', 'statuses' => ['rejected', 'cancelled']],
        'all' => ['label' => 'All', 'statuses' => null],
    ];
    $tab = (string) $request->query('status', 'pending');
    if (! isset($tabs[$tab])) {
        $tab = 'pending';
    }

    if ($request->isMethod('post')) {
        try {
            if (! $canReview) {
                throw new DomainException('Only a branch admin reviews the requests of their branch.');
            }
            $id = (int) $request->input('request_id');
            // The request must belong to the admin's own branch.
            abort_unless(WorkRequestService::detail($id, null, $branch), 404);
            $adminId = (int) auth()->id();
            $remarks = (string) $request->input('remarks', '');
  
            switch ((string) $request->input('action')) {
                case 'approve':
                    WorkRequestService::approve($id, $adminId, $remarks);
                    $success = 'The request was approved. The employee can now do the work.';
                    break;
                case 'return':
                    WorkRequestService::returnForCorrection($id, $adminId, $remarks);
                    $success = 'The request was returned to the employee for correction.';
                    break;
                case 'reject':
                    WorkRequestService::reject($id, $adminId, $remarks);
                    $success = 'The request was rejected.';
                    break;
                case 'return_completion':
                    WorkRequestService::returnCompletion($id, $adminId, $remarks);
                    $success = 'The report was returned to the employee for correction.';
                    break;
                case 'confirm':
                    WorkRequestService::confirm($id, $adminId, [
                        'internet_plan_id' => $request->input('internet_plan_id'),
                        'email' => $request->input('email'),
                        'installation_date' => $request->input('installation_date'),
                    ], $remarks);
                    $success = 'The work was confirmed. The port, the client and the stock have been updated.';
                    break;
                default:
                    throw new DomainException('Unknown action.');
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }
    }

    $requests = WorkRequestService::listing(null, $branch, $tabs[$tab]['statuses'], 100, $barangay);
    $counts = WorkRequestService::counts(null, $branch, $barangay);
    $tabCounts = [];
    foreach ($tabs as $key => $definition) {
        $tabCounts[$key] = $definition['statuses'] === null ? array_sum($counts) : array_sum(array_map(fn ($status) => $counts[$status] ?? 0, $definition['statuses']));
    }

    // Everything a review shows, including the materials, is read once for the requests on this page.
    $details = [];
    foreach ($requests as $row) {
        $details[$row['id']] = WorkRequestService::detail($row['id'], null, $branch);
    }
    $plans = DB::table('internet_plans')->where('status', 'active')->where('archived', 0)->orderBy('monthly_price')->get(['id', 'plan_name', 'monthly_price'])->all();

    return get_defined_vars();
};
