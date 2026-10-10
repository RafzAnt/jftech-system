<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request, string $page): View|RedirectResponse
    {
        if ($page === 'clients_register' && $request->isMethod('get')) {
            return redirect()->route('pages.show', ['page' => 'clients'])->with('open_register_modal', true);
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
