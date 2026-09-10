<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - User Management</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/user_management.css') }}?{{ $userManagementCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">User Management</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Manage system user accounts</p>
            </div>
            @if ($verified)
            <button class="btn btn-sm btn-success" type="button" onclick="openModal('createUserModal')">+ Add User</button>
            @endif
        </div>

        @if (!$verified)
        <!-- Admin Verification -->
        <div class="card" style="max-width: 450px; margin: 2rem auto;">
            <div class="card-body" style="text-align: center;">
                <h3 style="margin-bottom: 0.5rem;">Admin Verification Required</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Please verify your admin credentials to access user management.</p>
                
                @if ($verifyError)
                <div class="alert alert-danger">{{ $verifyError }}</div>
                @endif
                
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="verify_admin" value="1">
                    <div class="form-group">
                        <input type="text" name="verify_username" class="form-input" placeholder="Admin Username" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="verify_password" class="form-input" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Verify & Continue</button>
                    <a href="dashboard.php" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">Cancel & Go Back</a>
                </form>
            </div>
        </div>
        @else

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Actions -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 0.5rem; width: min(100%, 400px); align-items: stretch;">
                <input type="text" name="search" class="form-input" placeholder="Search users..." value="{{ htmlspecialchars(request()->query('search') ?? '') }}" style="flex:1; min-width:0;">
                <button type="submit" class="btn btn-sm btn-primary" style="white-space:nowrap;">Search</button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">System Users</span>
                <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                    <div class="payslip-view-tabs">
                        <a href="user_management.php" class="payslip-view-tab {{ !$showArchived ? 'active' : '' }}">Active</a>
                        <a href="user_management.php?view=archived" class="payslip-view-tab {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
                    </div>
                    <span class="badge badge-info">{{ count($users) }} User(s)</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                            <tr>
                                <td>{{ $user['id'] }}</td>
                                <td><strong>{{ htmlspecialchars($user['username']) }}</strong></td>
                                <td>{{ htmlspecialchars($user['full_name']) }}</td>
                                <td>{{ $user['email'] }}</td>
                                <td><span class="badge badge-{{ $user['role'] === 'admin' ? 'warning' : 'info' }}">{{ ucfirst($user['role']) }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $user['status'] === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($user['status']) }}
                                    </span>
                                </td>
                                <td>{{ date('M d, Y', strtotime($user['created_at'])) }}</td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                        @if (!$showArchived)
                                        <button type="button" class="btn btn-sm btn-primary btn-icon" data-user="{{ json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" onclick="editUserFromButton(this)" title="Edit">Edit</button>
                                        <button class="btn btn-sm btn-warning btn-icon" onclick="resetPassword({{ $user['id'] }})" title="Reset Password">Reset</button>
                                        @endif
                                        @if ($showArchived)
                                            <form method="POST" action="" style="display: inline;">
            @csrf
                                                <input type="hidden" name="restore_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="btn btn-sm btn-success btn-icon" title="Restore">Restore</button>
                                            </form>
                                            @if ($user['id'] != auth()->id())
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirmDelete('Permanently delete this archived user? This cannot be undone.')">
            @csrf
                                                <input type="hidden" name="purge_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="btn btn-sm btn-danger btn-icon" title="Delete Permanently">Del</button>
                                            </form>
                                            @endif
                                        @else
                                            @if ($user['id'] != auth()->id())
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirmDelete('Archive this user?')">
            @csrf
                                                <input type="hidden" name="delete_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="btn btn-sm btn-danger">Archive</button>
                                            </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($users))
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                    {{ $showArchived ? 'No archived users.' : 'No users found.' }}
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if ($verified)
    <!-- Create User Modal -->
    <div class="modal-overlay" id="createUserModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Create New User</h3>
                </div>
                <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="create_user" value="1">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-input" placeholder="09123456789">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="createUserRole" class="form-select" onchange="toggleEmployeeProfile()">
                        <option value="employee">Employee</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="employeeProfileGroup">
                    <label class="form-label">Employee Profile *</label>
                    <select name="employee_id" id="employeeProfile" class="form-select">
                        <option value="">Select employee profile</option>
                        @foreach ($unlinkedEmployees ?? [] as $employee)
                        <option value="{{ $employee['id'] }}">{{ $employee['employee_code'] }} — {{ $employee['full_name'] }}</option>
                        @endforeach
                    </select>
                    <small style="color:var(--text-muted)">Create the employee profile first in Employees, then link the account here.</small>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Edit User</h3>
                </div>
                <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="editFullName" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="editPhone" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editRole" class="form-select">
                        <option value="employee">Employee</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal-overlay" id="resetPasswordModal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Reset Password</h3>
                </div>
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST" action="">
            @csrf
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
    function editUserFromButton(button) {
        try {
            editUser(JSON.parse(button.dataset.user));
        } catch (error) {
            console.error('Unable to open the user editor.', error);
        }
    }

    function editUser(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editFullName').value = user.full_name;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone;
        document.getElementById('editRole').value = user.role;
        document.getElementById('editStatus').value = user.status;
        openModal('editUserModal');
    }

    function resetPassword(userId) {
        document.getElementById('resetUserId').value = userId;
        openModal('resetPasswordModal');
    }
    function toggleEmployeeProfile() {
        const role = document.getElementById('createUserRole');
        const group = document.getElementById('employeeProfileGroup');
        const select = document.getElementById('employeeProfile');
        if (!role || !group || !select) return;
        const isEmployee = role.value === 'employee';
        group.style.display = isEmployee ? '' : 'none';
        select.required = isEmployee;
    }
    toggleEmployeeProfile();
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
