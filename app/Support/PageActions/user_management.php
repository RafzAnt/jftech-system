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

// CRUD Operations
if ($verified && $request->isMethod('post')) {
    try {
        if ($request->has('create_user')) {
            $username = trim($request->input('username'));
            $password = password_hash($request->input('password'), PASSWORD_DEFAULT);
            $fullName = trim($request->input('full_name'));
            $email = trim($request->input('email'));
            $phone = trim($request->input('phone'));
            $role = $request->input('role');
            $employeeId = (int) $request->input('employee_id');
            $employeePosition = trim((string) $request->input('employee_position'));
            if ($role === 'employee') {
                if (! $employeeId) throw new Exception('Select the employee profile to link to this account.');
                if (! in_array($employeePosition, $employeePositions, true)) throw new Exception('Select a valid employee position.');
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND user_id IS NULL");
                $stmt->execute([$employeeId]);
                if (! $stmt->fetch()) throw new Exception('This employee profile is already linked to an account or does not exist.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $password, $fullName, $email, $phone, $role]);
            $userId = $pdo->lastInsertId();
            
            if ($role === 'employee') {
                $stmt = $pdo->prepare("UPDATE employees SET user_id = ?, position = ? WHERE id = ?");
                $stmt->execute([$userId, $employeePosition, $employeeId]);
            }
            
            $success = 'User created successfully!';
        }
        
        if ($request->has('update_user')) {
            $userId = $request->input('user_id');
            $fullName = trim($request->input('full_name'));
            $email = trim($request->input('email'));
            $phone = trim($request->input('phone'));
            $role = $request->input('role');
            $status = $request->input('status');
            $employeePosition = trim((string) $request->input('employee_position'));
            if ($role === 'employee' && ! in_array($employeePosition, $employeePositions, true)) throw new Exception('Select a valid employee position.');
            
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$fullName, $email, $phone, $role, $status, $userId]);
            if ($role === 'employee') {
                $stmt = $pdo->prepare("UPDATE employees SET position = ? WHERE user_id = ?");
                $stmt->execute([$employeePosition, $userId]);
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
    $unlinkedEmployees = $pdo->query("SELECT id, employee_code, full_name, position FROM employees WHERE user_id IS NULL AND status = 'active' ORDER BY full_name")->fetchAll();
}
    return get_defined_vars();
};
