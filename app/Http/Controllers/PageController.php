<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Pages that are not split by branch, so only the super admin may open them. */
    private const SUPER_ADMIN_PAGES = [
        'user_management', 'settings', 'login_history',
        'inventory_requests', 'print_inventory', 'print_reports',
    ];

    public function show(Request $request, string $page): View|RedirectResponse
    {
        abort_if(in_array($page, self::SUPER_ADMIN_PAGES, true) && ! $request->user()?->isSuperAdmin(), 403);

        if ($page === 'clients_register' && $request->isMethod('get')) {
            $redirect = redirect()->route('pages.show', ['page' => 'clients']);

            return $request->user()?->canRegisterClients() ? $redirect->with('open_register_modal', true) : $redirect;
        }

        abort_unless(view()->exists("pages.$page"), 404);
        $action = app_path("Support/PageActions/{$page}.php");
        abort_unless(is_file($action), 404);
        $data = require $action;
        $data = $data($request);
        $data['currentPage'] ??= $page;
        $data['mainJsVersion'] ??= @filemtime(public_path('js/jftech-main.js')) ?: 1;

        return view("pages.$page", $data);
    }
}
