<?php

namespace App\Http\Middleware;

use App\Support\EmployeeTokens;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in a request of the employee app by its bearer token. The employee is then available as
 * $request->attributes->get('employee'); nothing the app sends can name a different employee.
 */
class AuthenticateEmployeeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->unauthenticated();
        }

        $record = DB::table('employee_api_tokens')->where('token_hash', EmployeeTokens::hash($token))->first();
        if (! $record || ($record->expires_at && now()->greaterThan($record->expires_at))) {
            return $this->unauthenticated();
        }

        // The employee and their account must still be active.
        $employee = DB::table('employees')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->where('employees.id', $record->employee_id)
            ->where('employees.status', 'active')
            ->where(fn ($query) => $query->whereNull('users.id')->orWhere(fn ($account) => $account->where('users.status', 'active')->where('users.archived', 0)))
            ->first(['employees.*']);
        if (! $employee) {
            return $this->unauthenticated();
        }

        if (! $record->last_used_at || now()->diffInMinutes($record->last_used_at, true) >= 5) {
            DB::table('employee_api_tokens')->where('id', $record->id)->update(['last_used_at' => now()]);
        }

        $request->attributes->set('employee', $employee);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['success' => false, 'code' => 'unauthenticated', 'message' => 'Your session has ended. Please sign in again.'], 401);
    }
}
