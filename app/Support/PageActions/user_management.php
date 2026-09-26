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
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
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

function accountFullName(string $firstName, string $middleName, string $lastName): string {
    return implode(' ', array_filter([$firstName, $middleName, $lastName], fn ($part) => $part !== ''));
}

// CRUD Operations
if ($verified && $request->isMethod('post')) {
    try {
        if ($request->has('create_user')) {
            $username = trim((string) $request->input('username'));
            $plainPassword = (string) $request->input('password');
            $firstName = trim((string) $request->input('first_name'));
            $middleName = trim((string) $request->input('middle_name'));
            $lastName = trim((string) $request->input('last_name'));
            $fullName = accountFullName($firstName, $middleName, $lastName);
            $email = trim($request->input('email'));
            $phone = trim($request->input('phone'));
            $role = $request->input('role');
            $employeeId = (int) $request->input('employee_id');
            if (!in_array($role, ['admin', 'employee'], true)) throw new Exception('Select a valid account role.');
            if ($username === '' || $plainPassword === '') throw new Exception('Username and password are required.');

            if ($role === 'employee') {
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active' AND user_id IS NULL");
                $stmt->execute([$employeeId]);
                $employee = $stmt->fetch();
                if (!$employee) throw new Exception('Select an active employee without an existing account.');

                // Employee details come from the employee record, so an account cannot create a duplicate employee.
                $fullName = $employee['full_name'];
                $email = $employee['email'] ?? '';
                $phone = $employee['contact_number'] ?? '';
            } elseif ($firstName === '' || $lastName === '') {
                throw new Exception('First name and last name are required. Middle name is optional.');
            }
            if ($phone !== '' && ! preg_match('/^\d{11}$/', $phone)) throw new Exception('Phone number must contain exactly 11 digits.');

            $password = password_hash($plainPassword, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $password, $fullName, $email, $phone, $role]);
            $userId = $pdo->lastInsertId();

            if ($role === 'employee') {
                $stmt = $pdo->prepare('UPDATE employees SET user_id = ? WHERE id = ?');
                $stmt->execute([$userId, $employeeId]);
            }

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
            $role = $request->input('role');
            $status = $request->input('status');
            $employeePosition = trim((string) $request->input('employee_position'));
            if ($firstName === '' || $lastName === '') throw new Exception('First name and last name are required. Middle name is optional.');
            if ($role === 'employee' && ! in_array($employeePosition, $employeePositions, true)) throw new Exception('Select a valid employee position.');
            if ($phone !== '' && ! preg_match('/^\d{11}$/', $phone)) throw new Exception('Phone number must contain exactly 11 digits.');
            
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $phone, $role, $status, $userId]);
            if ($role === 'employee') {
                $stmt = $pdo->prepare("UPDATE employees SET full_name = ?, position = ?, contact_number = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$fullName, $employeePosition, $phone, $email, $userId]);
            }
            $success = 'User updated successfully!';
        }
        
        if ($request->has('reset_password')) {
            $userId = $request->input('user_id');
            $newPassword = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPassword, $userId]);
            $success = 'Password reset successfully!';
        }
        
        if ($request->has('delete_user')) {
            $userId = $request->input('user_id');
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
            $stmt = $pdo->prepare("UPDATE users SET archived = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            $success = 'User restored successfully!';
        }

        if ($request->has('purge_user')) {
            $userId = $request->input('user_id');
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
$showArchived = ($request->query('view') ?? '') === 'archived';
if ($verified) {
    $search = $request->query('search') ?? '';
    $archivedFilter = $showArchived ? 'AND archived = 1' : 'AND (archived = 0 OR archived IS NULL)';
    if ($search) {
        $searchTerm = "%$search%";
        $stmt = $pdo->prepare("SELECT users.*, employees.position AS employee_position FROM users LEFT JOIN employees ON employees.user_id = users.id WHERE (users.username LIKE ? OR users.full_name LIKE ? OR users.email LIKE ? OR employees.position LIKE ?) $archivedFilter ORDER BY users.created_at DESC");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->query("SELECT users.*, employees.position AS employee_position FROM users LEFT JOIN employees ON employees.user_id = users.id WHERE 1 $archivedFilter ORDER BY users.created_at DESC");
    }
    $users = $stmt->fetchAll();
    $archivedCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE archived = 1")->fetchColumn();

    $availableEmployees = $pdo->query("SELECT id, full_name, position FROM employees WHERE status = 'active' AND user_id IS NULL ORDER BY position, full_name")->fetchAll();
}
    return get_defined_vars();
};
