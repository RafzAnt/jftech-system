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

    /** Stock items of the branch: $alias.branch_name. */
    public static function inventory(?string $branch, string $alias = 'i'): array
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

    /** Rows that point at a stock item (stock movements): the item must be in the branch. */
    public static function viaInventory(?string $branch, string $inventoryIdColumn): array
    {
        return self::via($branch, 'inventory', $inventoryIdColumn);
    }

    /** Rows that point at a user account (login history): the account must be in the branch. */
    public static function viaUser(?string $branch, string $userIdColumn): array
    {
        return self::via($branch, 'users', $userIdColumn);
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

    public static function canAccessInventory(int|string|null $inventoryId, ?User $user = null): bool
    {
        return self::canAccess('inventory', $inventoryId, $user);
    }

    public static function canAccessUser(int|string|null $userId, ?User $user = null): bool
    {
        return self::canAccess('users', $userId, $user);
    }

    public static function canAccessBranch(?string $branchName, ?User $user = null): bool
    {
        $own = self::current($user);

        return $own === null || ($own !== '' && strcasecmp($own, trim((string) $branchName)) === 0);
    }

    /** The Admin position is the employee record of a branch admin. */
    public static function isAdminPosition(?string $position): bool
    {
        return strcasecmp(trim((string) $position), 'Admin') === 0;
    }

    /**
     * Who may generate, edit or archive payroll for an employee with this position. A branch admin runs
     * the technicians of their branch; the super admin runs the Admin employees (the branch admins), so
     * nobody generates their own pay. Branch access is checked separately.
     */
    public static function canManagePayrollFor(?string $position, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user?->isSuperAdmin()) {
            return self::isAdminPosition($position);
        }

        return $user?->isBranchAdmin() === true && ! self::isAdminPosition($position);
    }

    /**
     * Who may record attendance by hand (or mark absent) and edit the employee record. The super admin
     * can correct anyone; a branch admin cannot touch the Admin employees, so they cannot change their
     * own attendance or daily rate. Branch access is checked separately.
     */
    public static function canManageEmployee(?string $position, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user?->isSuperAdmin()) {
            return true;
        }

        return $user?->isBranchAdmin() === true && ! self::isAdminPosition($position);
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
