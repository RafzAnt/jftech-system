<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse { return Auth::check() ? redirect('/pages/dashboard.php') : view('auth.login'); }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $user = User::where('username', $data['username'])->where('status', 'active')->first();
        $ok = $user && str_starts_with($user->password, '$2y$') && Hash::check($data['password'], $user->password);
        $history = ['user_id' => $ok ? $user->id : null, 'username' => $data['username'], 'full_name' => $ok ? $user->full_name : '', 'role' => $ok ? $user->role : '', 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => $ok ? 'success' : 'failed'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('login_history', 'error_message')) $history['error_message'] = $ok ? null : 'Invalid username or password.';
        $historyRecord = LoginHistory::create($history);
        if (! $ok) return back()->withInput()->withErrors(['username' => 'Invalid username or password.']);
        Auth::login($user); $request->session()->regenerate();
        $request->session()->put(['user_id' => $user->id, 'username' => $user->username, 'full_name' => $user->full_name, 'role' => $user->role, 'login_history_id' => $historyRecord->id, 'last_activity_at' => time()]);
        return redirect('/pages/dashboard.php');
    }
    public function destroy(Request $request): RedirectResponse
    {
        $historyId = $request->session()->get('login_history_id');
        if ($historyId) {
            // Only close the history row created for this browser session.
            // This prevents an admin logout from changing an older login row.
            LoginHistory::where('id', $historyId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'success')
                ->whereNull('logout_time')
                ->update(['logout_time' => now()]);
        }
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect('/');
    }
}
