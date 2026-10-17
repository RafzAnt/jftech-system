<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign-in tokens of the employee app. The app signs in once with a username and password and then sends the
 * token with every request, so a request can only ever act as the employee who signed in. Only a hash of the
 * token is stored.
 */
final class EmployeeTokens
{
    public const LIFETIME_HOURS = 24;

    /** Create a token for an employee and return it (the only time it can be read). */
    public static function issue(int $employeeId, ?int $loginHistoryId = null): string
    {
        $token = Str::random(64);
        DB::table('employee_api_tokens')->insert([
            'employee_id' => $employeeId,
            'login_history_id' => $loginHistoryId,
            'token_hash' => self::hash($token),
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
            'created_at' => now(),
        ]);

        return $token;
    }

    /** Forget one token. */
    public static function revoke(string $token): void
    {
        DB::table('employee_api_tokens')->where('token_hash', self::hash($token))->delete();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
