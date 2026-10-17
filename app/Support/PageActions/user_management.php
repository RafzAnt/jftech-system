<?php
return function (\Illuminate\Http\Request $request): array {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

/**
 * JFTECH WiFi Billing & Management System
 * User Management Module (H) - Create, Edit, Reset Password, Delete
 */

$themeCssVersion = filemtime(public_path('css/jftech-theme.css'));
$userManagementCssVersion = filemtime(public_path('css/user_management.css'));

// Admin Verification
$verified = false;
$verifyError = '';

if (!session()->has('user_verified')) {
    if ($request->isMethod('post') && $request->has('verify_admin')) {
        $username = trim($request->input('verify_username'));
        $password = trim($request->input('verify_password'));
        
        // Every admin confirms their own password before managing accounts.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role IN ('super_admin', 'admin') AND status = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && (int) $admin['id'] === (int) auth()->id() && password_verify($password, $admin['password'])) {
            session()->put('user_verified', true);
            $verified = true;
        } else {
            $verifyError = 'Invalid admin credentials.';
        }
    }
} else {
    $verified = true;
}

$success = '';
$error = '';
$employeePositions = ['Technician', 'Technician I', 'Technician II', 'Master Technician', 'Intern'];
// super_admin sees every branch; admin (shown as Branch Admin) is limited to one branch.
$accountRoles = ['super_admin', 'admin', 'employee'];
$branches = array_keys(config('bohol_branches', []));
// The branch is typed, so accept any capitalisation of a real branch name.
$canonicalBranch = static function (string $typed) use ($branches): string {
    foreach ($branches as $branch) {
        if (strcasecmp($branch, $typed) === 0) return $branch;
    }
    return $typed;
};

// The super admin manages every account. A branch admin only manages the technician (employee) accounts of
// their own branch, so Admin accounts and other branches stay out of reach.
$scopeBranch = \App\Support\BranchScope::resolve($request->query('branch'));
$isBranchAdminView = \App\Support\BranchScope::isRestricted();
$canChangeRoles = ! $isBranchAdminView;
[$accountScope, $accountScopeParams] = \App\Support\BranchScope::users($scopeBranch, 'users');
$manageableAccount = static function ($userId) use ($pdo): ?array {
    $stmt = $pdo->prepare('SELECT id, role, branch_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $account = $stmt->fetch();
    if (! $account) {
        return null;
    }
    if (! \App\Support\BranchScope::isRestricted()) {
        return $account;
    }

    return $account['role'] === 'employee' && \App\Support\BranchScope::canAccessBranch($account['branch_name']) ? $account : null;
};

if (! function_exists('accountRoleForPosition')) {
    /** The Admin position gets a branch admin account; every other position gets an employee (mobile app) account. */
    function accountRoleForPosition(string $position): string {
        return $position === 'Admin' ? 'admin' : 'employee';
    }
}

if (! function_exists('accountFullName')) {
    function accountFullName(string $firstName, string $middleName, string $lastName): string {
        return implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
    }
}

// CRUD Operations
if ($verified && $request->isMethod('post')) {
    try {
        if ($request->has('create_user')) {
            // Only the account is created here. The person's name, contact details, position and branch
            // already live on the employee record (Employees page), so they are copied from it.
            $username = trim((string) $request->input('username'));
            $plainPassword = (string) $request->input('password');
            $employeeId = (int) $request->input('employee_id');
            if ($username === '' || $plainPassword === '') throw new Exception('Username and password are required.');

            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active' AND user_id IS NULL");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch();
            if ($employee && $isBranchAdminView && !\App\Support\BranchScope::canAccessBranch($employee['branch_name'])) $employee = false;
            if (!$employee) throw new Exception('Select an active employee without an existing account.');
            if (!\App\Support\BranchScope::canManageEmployee($employee['position'])) throw new Exception('Accounts for Admin employees are created by the super admin.');

            // The Admin position gets a branch admin account for that employee's branch; everyone else a mobile app account.
            $role = accountRoleForPosition((string) $employee['position']);
            $branchName = $canonicalBranch(trim((string) ($employee['branch_name'] ?? '')));
            if ($role === 'admin' && !in_array($branchName, $branches, true)) throw new Exception('Set a valid branch for this employee in Employees before creating an admin account.');
            $fullName = $employee['full_name'];
            $email = $employee['email'] ?? '';
            $phone = $employee['contact_number'] ?? '';
            if ($phone !== '' && ! preg_match('/^\d{11}$/', $phone)) throw new Exception('Mobile number must contain exactly 11 digits.');

            $password = password_hash($plainPassword, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, branch_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $password, $fullName, $email, $phone, $role, $branchName === '' ? null : $branchName]);
            $userId = $pdo->lastInsertId();

            $stmt = $pdo->prepare('UPDATE employees SET user_id = ? WHERE id = ?');
            $stmt->execute([$userId, $employeeId]);

            $pdo->commit();
            $success = 'User created successfully!';
        }
        
        if ($request->has('update_user')) {
            $userId = $request->input('user_id');
            $firstName = trim((string) $request->input('first_name'));
            $middleName = trim((string) $request->input('middle_name'));
            $lastName = trim((string) $request->input('last_name'));
            $fullName = accountFullName($firstName, $middleName, $lastName);
            $email = trim($request->input('email'));
            $phone = trim($request->input('phone'));
            $role = $isBranchAdminView ? 'employee' : $request->input('role');
            $status = $request->input('status');
            $employeePosition = trim((string) $request->input('employee_position'));
            $branchName = $canonicalBranch(trim((string) $request->input('branch_name')));
            if (!in_array($role, $accountRoles, true)) throw new Exception('Select a valid account role.');
            if (!in_array($status, ['active', 'inactive'], true)) throw new Exception('Select a valid account status.');
            if ($role === 'admin' && !in_array($branchName, $branches, true)) throw new Exception('Select the branch this admin will manage.');
            if ($firstName === '' || $lastName === '') throw new Exception('First name and last name are required. Middle name is optional.');
            if ($role === 'employee' && ! in_array($employeePosition, $employeePositions, true)) throw new Exception('Select a valid employee position.');
            if ($phone !== '' && ! preg_match('/^\d{11}$/', $phone)) throw new Exception('Mobile number must contain exactly 11 digits.');
            
            // Never lock the system out: the signed-in super admin cannot demote or deactivate themselves,
            // so at least one active super admin always remains.
            $current = $pdo->prepare("SELECT role, status, branch_name FROM users WHERE id = ?");
            $current->execute([$userId]);
            $existingUser = $current->fetch();
            if (!$existingUser || !$manageableAccount($userId)) throw new Exception('User not found.');
            if ($userId == auth()->id() && ($role !== 'super_admin' || $status !== 'active')) {
                throw new Exception('You cannot change your own Super Admin role or status.');
            }
            // Employee accounts keep the branch of their employee record; super admins have none.
            $accountBranch = $role === 'admin' ? $branchName : ($role === 'employee' ? ($existingUser['branch_name'] ?: null) : null);

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, branch_name = ?, status = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $phone, $role, $accountBranch, $status, $userId]);
            if ($role === 'employee') {
                $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, position = ?, contact_number = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$fullName, $employeePosition, $phone, $email, $userId]);
            }
            $success = 'User updated successfully!';
        }
        
        if ($request->has('reset_password')) {
            $userId = $request->input('user_id');
            if (!$manageableAccount($userId)) throw new Exception('User not found.');
            $newPassword = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPassword, $userId]);
            $success = 'Password reset successfully!';
        }
        
        if ($request->has('delete_user')) {
            $userId = $request->input('user_id');
            if (!$manageableAccount($userId)) throw new Exception('User not found.');
            // Prevent archiving own account
            if ($userId == auth()->id()) {
                $error = 'You cannot archive your own account!';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET archived = 1 WHERE id = ?");
                $stmt->execute([$userId]);
                $success = 'User archived successfully!';
            }
        }

        if ($request->has('restore_user')) {
            $userId = $request->input('user_id');
            if (!$manageableAccount($userId)) throw new Exception('User not found.');
            $stmt = $pdo->prepare("UPDATE users SET archived = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            $success = 'User restored successfully!';
        }

        if ($request->has('purge_user')) {
            $userId = $request->input('user_id');
            if (!$manageableAccount($userId)) throw new Exception('User not found.');
            // Prevent purging own account
            if ($userId == auth()->id()) {
                $error = 'You cannot delete your own account!';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND archived = 1");
                $stmt->execute([$userId]);
                $success = 'User deleted permanently!';
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all users
$searchQuery = '';
$users = [];
$availableEmployees = [];
$employeeChoices = [];
$showArchived = ($request->query('view') ?? '') === 'archived';
if ($verified) {
    $search = $request->query('search') ?? '';
    $archivedFilter = $showArchived ? 'AND users.archived = 1' : 'AND (users.archived = 0 OR users.archived IS NULL)';
    // A branch admin only lists the technician accounts of their branch.
    $roleFilter = $isBranchAdminView ? " AND users.role = 'employee'" : '';
    $scopeFilter = $accountScope . $roleFilter;
    if ($search) {
        $searchTerm = "%$search%";
        $stmt = $pdo->prepare("SELECT users.*, employees.position AS employee_position FROM users LEFT JOIN employees ON employees.user_id = users.id WHERE (users.username LIKE ? OR users.full_name LIKE ? OR users.email LIKE ? OR employees.position LIKE ?) $archivedFilter $scopeFilter ORDER BY users.created_at DESC");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, ...$accountScopeParams]);
    } else {
        $stmt = $pdo->prepare("SELECT users.*, employees.position AS employee_position FROM users LEFT JOIN employees ON employees.user_id = users.id WHERE TRUE $archivedFilter $scopeFilter ORDER BY users.created_at DESC");
        $stmt->execute($accountScopeParams);
    }
    $users = $stmt->fetchAll();
    $archivedStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE users.archived = 1 $scopeFilter");
    $archivedStmt->execute($accountScopeParams);
    $archivedCount = (int) $archivedStmt->fetchColumn();

    [$employeeScope, $employeeScopeParams] = \App\Support\BranchScope::employees($scopeBranch, 'employees');
    $availableStmt = $pdo->prepare("SELECT id, full_name, position, branch_name FROM employees WHERE status = 'active' AND user_id IS NULL $employeeScope ORDER BY position, full_name");
    $availableStmt->execute($employeeScopeParams);
    $availableEmployees = $availableStmt->fetchAll();
    // A branch admin creates accounts for technicians only; the Admin employees get theirs from the super admin.
    if ($isBranchAdminView) {
        $availableEmployees = array_values(array_filter($availableEmployees, static fn (array $e): bool => \App\Support\BranchScope::canManageEmployee($e['position'])));
    }

    // What the Create Account box suggests: "Name — Position · Branch", plus the role the account will get.
    $labelCounts = array_count_values(array_map(static fn (array $e): string => $e['full_name'] . '|' . $e['position'] . '|' . $e['branch_name'], $availableEmployees));
    $employeeChoices = array_map(static function (array $e) use ($labelCounts): array {
        $position = $e['position'] ?: 'No position';
        $branch = $e['branch_name'] ?: 'No branch';
        $label = $e['full_name'] . ' — ' . $position . ' · ' . $branch;
        if ($labelCounts[$e['full_name'] . '|' . $e['position'] . '|' . $e['branch_name']] > 1) {
            $label .= ' #' . $e['id'];
        }
        $role = accountRoleForPosition((string) $e['position']);

        return [
            'id' => (int) $e['id'],
            'label' => $label,
            'name' => $e['full_name'],
            'position' => $position,
            'branch' => $branch,
            'roleLabel' => $role === 'admin' ? 'Branch Admin (manages ' . $branch . ')' : 'Employee (mobile app)',
        ];
    }, $availableEmployees);
}
    return get_defined_vars();
};
