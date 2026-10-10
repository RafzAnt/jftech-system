<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - User Accounts</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/user_management.css') }}?{{ $userManagementCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">User Accounts</h1>
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
                <h3 style="margin-bottom: 0.5rem;">Admin Check Required</h3>
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
                <span class="card-title">User Accounts</span>
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
                                <th>User No.</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Position</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th aria-label="User menu"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                            <tr>
                                <td>{{ $user['id'] }}</td>
                                <td><strong>{{ htmlspecialchars($user['username']) }}</strong></td>
                                <td>{{ htmlspecialchars($user['full_name']) }}</td>
                                <td>{{ $user['employee_position'] ?: '—' }}</td>
                                <td>{{ $user['email'] }}</td>
                                <td><span class="badge badge-{{ $user['role'] === 'admin' ? 'warning' : 'info' }}">{{ ucfirst($user['role']) }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $user['status'] === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($user['status']) }}
                                    </span>
                                </td>
                                <td>{{ date('M d, Y', strtotime($user['created_at'])) }}</td>
                                <td class="user-menu-cell">
                                    <div class="user-action-menu">
                                        <button class="user-action-menu-btn" type="button" aria-label="Actions for {{ htmlspecialchars($user['username']) }}" aria-expanded="false" onclick="toggleUserActionMenu(this)">&#8942;</button>
                                        <div class="user-action-menu-dropdown">
                                        @if (!$showArchived)
                                        <button type="button" class="user-menu-item" data-user="{{ json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" onclick="editUserFromButton(this); closeUserActionMenus()">Edit</button>
                                        <button class="user-menu-item" type="button" onclick="resetPassword({{ $user['id'] }}); closeUserActionMenus()">Reset Password</button>
                                        @endif
                                        @if ($showArchived)
                                            <form method="POST" action="">
            @csrf
                                                <input type="hidden" name="restore_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="user-menu-item">Restore</button>
                                            </form>
                                            @if ($user['id'] != auth()->id())
                                            <form method="POST" action="" onsubmit="return confirmDelete('Permanently delete this archived user? This cannot be undone.')">
            @csrf
                                                <input type="hidden" name="purge_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="user-menu-item user-menu-item-danger">Delete Permanently</button>
                                            </form>
                                            @endif
                                        @else
                                            @if ($user['id'] != auth()->id())
                                            <form method="POST" action="" onsubmit="return confirmDelete('Archive this user?')">
            @csrf
                                                <input type="hidden" name="delete_user" value="1">
                                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                                <button type="submit" class="user-menu-item user-menu-item-danger">Archive</button>
                                            </form>
                                            @endif
                                        @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($users))
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
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
        <div class="modal-content modal-medium create-user-modal-content">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Create New User</h3>
                </div>
                <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="" class="create-user-form">
            @csrf
                <input type="hidden" name="create_user" value="1">
                <div id="createEmployeeGroup" class="form-group">
                    <label class="form-label">Employee to Create an Account For *</label>
                    <select name="employee_id" id="createEmployeeId" class="form-select" onchange="showSelectedEmployeeName()">
                        <option value="">Select an employee</option>
                        @forelse (collect($availableEmployees)->groupBy(fn ($employee) => $employee['position'] ?: 'No position') as $position => $employeesByPosition)
                            <optgroup label="{{ $position }}">
                                @foreach ($employeesByPosition as $employee)
                                <option value="{{ $employee['id'] }}" data-name="{{ htmlspecialchars($employee['full_name']) }}">{{ htmlspecialchars($employee['full_name']) }}</option>
                                @endforeach
                            </optgroup>
                        @empty
                            <option value="" disabled>No active employees without an account</option>
                        @endforelse
                    </select>
                    <small class="form-help">Employees are listed by position. Their employee record will be linked to this account.</small>
                    <input type="text" id="selectedEmployeeName" class="form-input" style="margin-top:.5rem;" placeholder="Selected employee name" readonly>
                </div>
                <div id="createManualNameGroups" class="form-grid" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="createFirstName" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name <span style="color: var(--text-muted);">(Optional)</span></label>
                        <input type="text" name="middle_name" id="createMiddleName" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="createLastName" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="phone" class="form-input" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="createUserRole" class="form-select" onchange="toggleCreatePosition()">
                        <option value="employee">Employee</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                </div>
                <div class="create-user-actions" style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
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
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name <span style="color: var(--text-muted);">(Optional)</span></label>
                        <input type="text" name="middle_name" id="editMiddleName" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="editLastName" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="editEmail" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="phone" id="editPhone" class="form-input" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editRole" class="form-select">
                        <option value="employee">Employee</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="editPositionGroup">
                    <label class="form-label">Position *</label>
                    <select name="employee_position" id="editEmployeePosition" class="form-select">
                        <option value="">Select position</option>
                        @foreach ($employeePositions as $position)
                        <option value="{{ $position }}">{{ $position }}</option>
                        @endforeach
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
        const nameParts = String(user.full_name || '').trim().split(/\s+/);
        document.getElementById('editFirstName').value = nameParts.shift() || '';
        document.getElementById('editLastName').value = nameParts.length ? nameParts.pop() : '';
        document.getElementById('editMiddleName').value = nameParts.join(' ');
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone;
        document.getElementById('editRole').value = user.role;
        document.getElementById('editStatus').value = user.status;
        document.getElementById('editEmployeePosition').value = user.employee_position || '';
        toggleEditPosition();
        openModal('editUserModal');
    }

    function resetPassword(userId) {
        document.getElementById('resetUserId').value = userId;
        openModal('resetPasswordModal');
    }
    function closeUserActionMenus() {
        document.querySelectorAll('.user-action-menu-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
            if (menu._userMenuPlaceholder) {
                menu.removeAttribute('style');
                menu._userMenuPlaceholder.replaceWith(menu);
                delete menu._userMenuPlaceholder;
            }
        });
        document.querySelectorAll('.user-action-menu-btn[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
    function toggleUserActionMenu(button) {
        const menu = button.nextElementSibling;
        const willOpen = !menu.classList.contains('show');
        closeUserActionMenus();
        if (!willOpen) return;
        const placeholder = document.createComment('user action menu');
        menu.parentNode.replaceChild(placeholder, menu);
        menu._userMenuPlaceholder = placeholder;
        document.body.appendChild(menu);
        menu.style.cssText = 'position:fixed;visibility:hidden;z-index:1000;right:auto;bottom:auto';
        menu.classList.add('show');
        const buttonRect = button.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const top = buttonRect.top >= menuRect.height + 8 ? buttonRect.top - menuRect.height - 6 : Math.min(window.innerHeight - menuRect.height - 8, buttonRect.bottom + 6);
        const left = Math.max(8, Math.min(window.innerWidth - menuRect.width - 8, buttonRect.right - menuRect.width));
        menu.style.top = Math.max(8, top) + 'px';
        menu.style.left = left + 'px';
        menu.style.visibility = 'visible';
        button.setAttribute('aria-expanded', 'true');
    }
    document.addEventListener('click', event => {
        if (!event.target.closest('.user-action-menu') && !event.target.closest('.user-action-menu-dropdown')) closeUserActionMenus();
    });
    function showSelectedEmployeeName() {
        const select = document.getElementById('createEmployeeId');
        const display = document.getElementById('selectedEmployeeName');
        display.value = select?.selectedOptions[0]?.dataset.name || '';
    }
    function toggleCreatePosition() {
        const role = document.getElementById('createUserRole');
        const employeeGroup = document.getElementById('createEmployeeGroup');
        const employee = document.getElementById('createEmployeeId');
        const nameGroups = document.getElementById('createManualNameGroups');
        const firstName = document.getElementById('createFirstName');
        const lastName = document.getElementById('createLastName');
        if (!role || !employeeGroup || !employee || !nameGroups || !firstName || !lastName) return;
        const isEmployee = role.value === 'employee';
        employeeGroup.style.display = isEmployee ? '' : 'none';
        employee.required = isEmployee;
        nameGroups.style.display = isEmployee ? 'none' : '';
        firstName.required = !isEmployee;
        lastName.required = !isEmployee;
        if (!isEmployee) showSelectedEmployeeName();
    }
    function toggleEditPosition() {
        const role = document.getElementById('editRole');
        const group = document.getElementById('editPositionGroup');
        const position = document.getElementById('editEmployeePosition');
        if (!role || !group || !position) return;
        const isEmployee = role.value === 'employee';
        group.style.display = isEmployee ? '' : 'none';
        position.required = isEmployee;
    }
    document.getElementById('editRole').addEventListener('change', toggleEditPosition);
    toggleCreatePosition();
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
