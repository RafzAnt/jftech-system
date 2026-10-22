<?php

namespace App\Http\Middleware;

use App\Models\LoginHistory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceInactivityTimeout
{
    // The sidebar script reads this too, so the browser and the server always count the same five minutes.
    public const TIMEOUT_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('last_activity_at', 0);
        if ($lastActivity > 0 && (time() - $lastActivity) >= self::TIMEOUT_SECONDS) {
            $historyId = $request->session()->get('login_history_id');
            $userId = Auth::id();

            if ($historyId && $userId) {
                LoginHistory::where('id', $historyId)
                    ->where('user_id', $userId)
                    ->where('status', 'success')
                    ->whereNull('logout_time')
                    ->update(['logout_time' => now()]);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->is('backend/api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Your session expired because of inactivity. Please log in again.'], 401);
            }

            return redirect('/')->with('status', 'You were logged out after 5 minutes of inactivity.');
        }

        $request->session()->put('last_activity_at', time());

        return $next($request);
    }
}
