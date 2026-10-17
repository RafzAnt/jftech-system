<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\EmployeeApiController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PriceManagementController;
use App\Http\Controllers\InventoryRequestController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$cleanPages = [
    'dashboard' => 'dashboard',
    'clients_browse' => 'clients',
    'clients_archive' => 'archived-clients',
    'bill_inquiry' => 'client-accounts',
    'clients_register' => 'client-registration',
    'clients_update' => 'client-update',
    'user_management' => 'users',
    'login_history' => 'login-history',
    'employees' => 'employees',
    'payment_history' => 'payments',
    'payroll' => 'payroll',
    'print_inventory' => 'print-inventory',
    'print_client' => 'print-client',
    'print_payroll' => 'print-payroll',
    'settings' => 'settings',
    'print_clients' => 'print-clients',
    'inventory_requests' => 'inventory-requests',
    'work_requests' => 'work-requests',
    'nap_boxes' => 'nap-boxes',
    'reports' => 'reports',
    'print_monthly_reports' => 'print-monthly-reports',
    'print_employees' => 'print-employees',
    'print_payments' => 'print-payments',
    'print_bills' => 'print-bills',
    'print_attendance' => 'print-attendance',
    'print_reports' => 'print-reports',
    'print_payment' => 'print-payment',
];

$redirectWithQuery = static function (Request $request, string $url) {
    if ($query = $request->getQueryString()) {
        $url .= '?' . $query;
    }

    return redirect()->to($url, 301);
};

Route::get('/', [AuthController::class, 'create'])->name('login');
Route::get('/assets/jftech-logo.png', function () {
    $logo = resource_path('views/logo/jftect_logo.png');
    abort_unless(is_file($logo), 404);

    return response()->file($logo, [
        'Cache-Control' => 'public, max-age=604800, immutable',
    ]);
});
Route::post('/', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::prefix('backend/api')->middleware('throttle:api')->group(function () {
    Route::middleware(['auth', 'inactivity'])->group(function () {
        Route::get('get_pending_bills.php', [ApiController::class, 'pendingBills']);
        Route::get('get_client_billing.php', [ApiController::class, 'clientBilling']);
    });
    Route::get('ping.php', [EmployeeApiController::class, 'ping']);
    Route::post('employee_login.php', [ApiController::class, 'employeeLogin'])->middleware('throttle:login');
    Route::post('employee_logout.php', [ApiController::class, 'employeeLogout'])->middleware('throttle:api');

    // Everything else of the employee app needs the token that employee_login.php hands out.
    Route::middleware('employee.token')->group(function () {
        Route::get('me.php', [EmployeeApiController::class, 'me']);
        Route::post('scan_attendance.php', [ApiController::class, 'scanAttendance']);
        Route::get('attendance_history.php', [ApiController::class, 'attendanceHistory']);
        Route::get('inventory_items.php', [ApiController::class, 'inventoryItems']);
        Route::post('inventory_request_create.php', [ApiController::class, 'inventoryRequestCreate']);
        Route::get('inventory_requests_list.php', [ApiController::class, 'inventoryRequestsList']);
        Route::get('barangays.php', [EmployeeApiController::class, 'barangays']);
        Route::get('clients_search.php', [EmployeeApiController::class, 'clientsSearch']);
        Route::get('nap_boxes.php', [EmployeeApiController::class, 'napBoxes']);
        Route::get('nap_box.php', [EmployeeApiController::class, 'napBox']);
        Route::get('work_requests.php', [EmployeeApiController::class, 'workRequests']);
        Route::get('work_request.php', [EmployeeApiController::class, 'workRequest']);
        Route::post('work_request_create.php', [EmployeeApiController::class, 'workRequestCreate']);
        Route::post('work_request_update.php', [EmployeeApiController::class, 'workRequestUpdate']);
        Route::post('work_request_cancel.php', [EmployeeApiController::class, 'workRequestCancel']);
        Route::post('work_request_complete.php', [EmployeeApiController::class, 'workRequestComplete']);
        Route::get('transactions.php', [EmployeeApiController::class, 'transactions']);
    });
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/pages/logout.php', [AuthController::class, 'destroy'])->middleware('auth');

Route::middleware(['auth', 'inactivity', 'role:super_admin,admin'])->group(function () use ($cleanPages, $redirectWithQuery) {
    Route::get('/locations/balilihan-search', [LocationController::class, 'searchBalilihan'])->middleware('throttle:10,1')->name('locations.balilihan-search');
    Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    // Stock is kept per branch: the branch admin keeps their own branch's stock and the super admin looks at every
    // branch (the controllers enforce which branch and who may change what).
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/pages/inventory.php', fn (Request $request) => $redirectWithQuery($request, route('inventory.index')));
    Route::post('/pages/inventory.php', [InventoryController::class, 'store']);
    Route::get('/inventory.php', fn (Request $request) => $redirectWithQuery($request, route('inventory.index')));
    Route::post('/inventory.php', [InventoryController::class, 'store']);
    Route::post('/inventory/{inventory}/movement', [InventoryController::class, 'recordStockMovement'])->name('inventory.movement');
    Route::post('/inventory/{inventory}/archive', [InventoryController::class, 'archive'])->name('inventory.archive');
    Route::post('/inventory/{inventory}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory-requests/{inventoryRequest}/{decision}', [InventoryRequestController::class, 'decide'])
        ->whereIn('decision', ['approve', 'deny'])
        ->name('inventory-requests.decide');

    // Price Management: every branch sees the plans, only the super admin changes them.
    Route::get('/price-management', [PriceManagementController::class, 'index'])->name('price.management');
    Route::get('/pages/price_management.php', fn (Request $request) => $redirectWithQuery($request, route('price.management')));
    Route::get('/price_management.php', fn (Request $request) => $redirectWithQuery($request, route('price.management')));

    // The company-wide report exports and history are not split by branch yet, so only the super admin reaches them.
    Route::middleware('role:super_admin')->group(function () use ($redirectWithQuery) {
        Route::post('/price-management', [PriceManagementController::class, 'handlePost'])->name('price.management.store');
        Route::post('/pages/price_management.php', [PriceManagementController::class, 'handlePost']);
        Route::post('/price_management.php', [PriceManagementController::class, 'handlePost']);

        // Reports module — export, print and history actions.
        Route::get('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
        Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
        Route::get('/report-history', [ReportController::class, 'history'])->name('reports.history');
        Route::delete('/report-history/{report}', [ReportController::class, 'destroyHistory'])->name('reports.history.delete');
        Route::get('/pages/reports_export.php', fn (Request $request) => $redirectWithQuery($request, route('reports.export.csv')));
        Route::get('/pages/reports_excel.php', fn (Request $request) => $redirectWithQuery($request, route('reports.export.excel')));
        Route::get('/pages/report_history.php', fn (Request $request) => $redirectWithQuery($request, route('reports.history')));
        Route::delete('/pages/report_history/{report}', [ReportController::class, 'destroyHistory']);
    });

    Route::match(['get', 'post'], '/{page}', function (Request $request, string $page) use ($cleanPages) {
        $legacyPage = array_search($page, $cleanPages, true);
        abort_unless($legacyPage !== false, 404);

        return app(PageController::class)->show($request, $legacyPage);
    })->where('page', implode('|', array_values($cleanPages)))->name('pages.show');

    Route::match(['get', 'post'], '/pages/{page}.php', function (Request $request, string $page) use ($cleanPages, $redirectWithQuery) {
        abort_unless(isset($cleanPages[$page]), 404);

        if ($request->isMethod('get')) {
            return $redirectWithQuery($request, route('pages.show', ['page' => $cleanPages[$page]]));
        }

        return app(PageController::class)->show($request, $page);
    })->where('page', implode('|', array_keys($cleanPages)));

    Route::match(['get', 'post'], '/{page}.php', function (Request $request, string $page) use ($cleanPages, $redirectWithQuery) {
        abort_unless(isset($cleanPages[$page]), 404);

        if ($request->isMethod('get')) {
            return $redirectWithQuery($request, route('pages.show', ['page' => $cleanPages[$page]]));
        }

        return app(PageController::class)->show($request, $page);
    })->where('page', implode('|', array_keys($cleanPages)));
});
