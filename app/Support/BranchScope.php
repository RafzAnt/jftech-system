<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Branch access rules.
 *
 * A super admin sees every branch. A branch admin only sees records that belong
 * to users.branch_name. Any other account (or an admin without a branch) sees
 * nothing, so a missing branch can never widen access.
 *
 * The branch is passed around as: null = every branch, '' = no access, any other
 * string = that branch. The SQL helpers return [fragment, params] to append to a
 * WHERE clause that already has a condition, e.g. "... WHERE TRUE" . $sql.
 */
final class BranchScope
{
    /** The branch the signed-in user is limited to; null when unrestricted. */
    public static function current(?User $user = null): ?string
    {
        $user ??= Auth::user();

        if ($user?->isSuperAdmin()) {
            return null;
        }
        if ($user?->isBranchAdmin()) {
            return trim((string) $user->branch_name);
        }

        return '';
    }

    public static function isRestricted(?User $user = null): bool
    {
        return self::current($user) !== null;
    }

    /**
     * The branch to show: a restricted user always gets their own; a super admin
     * gets the requested branch when it is a real one, otherwise every branch.
     */
    public static function resolve(?string $requested, ?User $user = null): ?string
    {
        $own = self::current($user);
        if ($own !== null) {
            return $own;
        }

        foreach (array_keys(config('bohol_branches', [])) as $branch) {
            if (strcasecmp((string) $requested, $branch) === 0) {
                return $branch;
            }
        }

        return null;
    }

    /** Clients of the branch: $alias.branch_name. */
    public static function clients(?string $branch, string $alias = 'c'): array
    {
        return self::column($branch, "{$alias}.branch_name");
    }

    /** Employees of the branch: $alias.branch_name. */
    public static function employees(?string $branch, string $alias = 'e'): array
    {
        return self::column($branch, "{$alias}.branch_name");
    }

    /** User accounts of the branch: $alias.branch_name. */
    public static function users(?string $branch, string $alias = 'u'): array
    {
        return self::column($branch, "{$alias}.branch_name");
    }

    /** Rows that point at a client (bills, payments): the client must be in the branch. */
    public static function viaClient(?string $branch, string $clientIdColumn): array
    {
        return self::via($branch, 'clients', $clientIdColumn);
    }

    /** Rows that point at an employee (attendance, payroll): the employee must be in the branch. */
    public static function viaEmployee(?string $branch, string $employeeIdColumn): array
    {
        return self::via($branch, 'employees', $employeeIdColumn);
    }

    /**
     * Attendance rows belong to the branch recorded on the day (the employee's branch when none was
     * recorded), so a scan stays with the branch it was made in even if the employee moves later.
     */
    public static function attendance(?string $branch, string $alias = 'a'): array
    {
        if ($branch === null) {
            return ['', []];
        }
        if ($branch === '') {
            return [' AND 1 = 0', []];
        }

        return [" AND LOWER(COALESCE(NULLIF({$alias}.branch_name, ''), (SELECT se.branch_name FROM employees se WHERE se.id = {$alias}.employee_id), '')) = LOWER(?)", [$branch]];
    }

    public static function canAccessClient(int|string|null $clientId, ?User $user = null): bool
    {
        return self::canAccess('clients', $clientId, $user);
    }

    public static function canAccessEmployee(int|string|null $employeeId, ?User $user = null): bool
    {
        return self::canAccess('employees', $employeeId, $user);
    }

    public static function canAccessBranch(?string $branchName, ?User $user = null): bool
    {
        $own = self::current($user);

        return $own === null || ($own !== '' && strcasecmp($own, trim((string) $branchName)) === 0);
    }

    public static function abortUnlessClient(int|string|null $clientId): void
    {
        abort_unless(self::canAccessClient($clientId), 404);
    }

    public static function abortUnlessEmployee(int|string|null $employeeId): void
    {
        abort_unless(self::canAccessEmployee($employeeId), 404);
    }

    private static function column(?string $branch, string $column): array
    {
        if ($branch === null) {
            return ['', []];
        }
        if ($branch === '') {
            return [' AND 1 = 0', []];
        }

        return [" AND LOWER(COALESCE({$column}, '')) = LOWER(?)", [$branch]];
    }

    private static function via(?string $branch, string $table, string $idColumn): array
    {
        if ($branch === null) {
            return ['', []];
        }
        if ($branch === '') {
            return [' AND 1 = 0', []];
        }

        return [" AND EXISTS (SELECT 1 FROM {$table} scope_{$table} WHERE scope_{$table}.id = {$idColumn} AND LOWER(COALESCE(scope_{$table}.branch_name, '')) = LOWER(?))", [$branch]];
    }

    private static function canAccess(string $table, int|string|null $id, ?User $user): bool
    {
        $branch = self::current($user);
        if ($branch === '' || $id === null || $id === '') {
            return false;
        }

        $query = DB::table($table)->where('id', $id);
        if ($branch !== null) {
            $query->whereRaw("LOWER(COALESCE(branch_name, '')) = LOWER(?)", [$branch]);
        }

        return $query->exists();
    }
}
