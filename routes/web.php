<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PriceManagementController;
use App\Http\Controllers\InventoryRequestController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

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
    Route::get('inventory_items.php', [ApiController::class, 'inventoryItems']);
    Route::get('attendance_history.php', [ApiController::class, 'attendanceHistory']);
    Route::post('employee_login.php', [ApiController::class, 'employeeLogin'])->middleware('throttle:login');
        Route::post('employee_logout.php', [ApiController::class, 'employeeLogout'])->middleware('throttle:api');
    Route::post('inventory_request_create.php', [ApiController::class, 'inventoryRequestCreate']);
    Route::get('inventory_requests_list.php', [ApiController::class, 'inventoryRequestsList']);
    Route::post('scan_attendance.php', [ApiController::class, 'scanAttendance']);
});
Route::post('/pages/logout.php', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'inactivity', 'role:admin'])->group(function () {
    Route::get('/locations/balilihan-search', [LocationController::class, 'searchBalilihan'])->middleware('throttle:10,1')->name('locations.balilihan-search');
    Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::get('/pages/inventory.php', [InventoryController::class, 'index']);
    Route::post('/pages/inventory.php', [InventoryController::class, 'store']);
    Route::post('/inventory/{inventory}/archive', [InventoryController::class, 'archive'])->name('inventory.archive');
    Route::post('/inventory/{inventory}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory-requests/{inventoryRequest}/{decision}', [InventoryRequestController::class, 'decide'])
        ->whereIn('decision', ['approve', 'deny'])
        ->name('inventory-requests.decide');

    // Price Management module — admin-only Internet Plan pricing.
    Route::get('/pages/price_management.php', [PriceManagementController::class, 'index'])->name('price.management');
    Route::post('/pages/price_management.php', [PriceManagementController::class, 'handlePost']);

    // Reports module — export, print and history actions.
    Route::get('/pages/reports_export.php', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
    Route::get('/pages/reports_excel.php', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('/pages/report_history.php', [ReportController::class, 'history'])->name('reports.history');
    Route::delete('/pages/report_history/{report}', [ReportController::class, 'destroyHistory'])->name('reports.history.delete');

    Route::match(['get', 'post'], '/pages/{page}.php', [PageController::class, 'show'])
        ->where('page', 'dashboard|clients_browse|clients_archive|bill_inquiry|clients_register|clients_update|user_management|inventory|login_history|employees|payment_history|payroll|print_inventory|print_client|print_payroll|settings|print_clients|inventory_requests|reports|print_monthly_reports|print_employees|print_payments|print_bills|print_attendance|print_reports|print_payment');
});
