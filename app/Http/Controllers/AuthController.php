<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceInactivityTimeout;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $user = User::where('username', $data['username'])->first();
        // An account that was switched off or archived is treated like one that does not exist.
        $passwordOk = $user && $user->isActive() && str_starts_with($user->password, '$2y$') && Hash::check($data['password'], $user->password);
        // A branch admin without a branch would see nothing, so say why instead of showing empty pages.
        $noBranch = $passwordOk && $user->isBranchAdmin() && trim((string) $user->branch_name) === '';
        // The employee app accounts have no page here: letting them in only ends on a "403 Forbidden" page.
        $notAdmin = $passwordOk && ! $user->isSuperAdmin() && ! $user->isBranchAdmin();
        $ok = $passwordOk && ! $noBranch && ! $notAdmin;
        $failure = match (true) {
            $noBranch => 'This admin account has no branch assigned. Ask the super admin to assign one.',
            $notAdmin => 'This account is for the employee app. It cannot log in here.',
            default => 'Invalid username or password.',
        };
        $history = ['user_id' => $ok ? $user->id : null, 'username' => $data['username'], 'full_name' => $ok ? $user->full_name : '', 'role' => $ok ? $user->role : '', 'ip_address' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'status' => $ok ? 'success' : 'failed'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('login_history', 'error_message')) $history['error_message'] = $ok ? null : $failure;
        $historyRecord = LoginHistory::create($history);
        // Keep the username for retyping, never the password; a wrong username/password marks both fields red.
        if (! $ok) return back()->withInput($request->only('username'))->with('invalid_credentials', ! $passwordOk)->withErrors(['username' => $failure]);
        Auth::login($user); $request->session()->regenerate();
        $request->session()->put(['user_id' => $user->id, 'username' => $user->username, 'full_name' => $user->full_name, 'role' => $user->role, 'login_history_id' => $historyRecord->id, 'last_activity_at' => time()]);
        return redirect('/pages/dashboard.php');
    }
    /**
     * Heartbeat from the browser. Typing in a form or filtering a table never reaches the server, so without
     * this the server would think a busy user was idle and log them out on the next request. The browser says
     * how many seconds ago the user last did anything, so the server's clock matches theirs.
     */
    public function keepAlive(Request $request): Response
    {
        $idle = min(max((int) $request->input('idle', 0), 0), EnforceInactivityTimeout::TIMEOUT_SECONDS - 1);
        $request->session()->put('last_activity_at', time() - $idle);

        return response()->noContent();
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
