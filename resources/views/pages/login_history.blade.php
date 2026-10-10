<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Login History</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/login_history.css') }}?{{ $loginHistoryCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Login History</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">List of logins to user accounts</p>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card" style="padding: 1rem; --stat-color: var(--accent);">
                <div class="stat-value" style="font-size: 1.25rem;">{{ number_format($totalLogins) }}</div>
                <div class="stat-label">Total Logins</div>
            </div>
            <div class="stat-card" style="padding: 1rem; --stat-color: var(--success);">
                <div class="stat-value" style="font-size: 1.25rem; color: var(--success);">{{ number_format($successfulLogins) }}</div>
                <div class="stat-label">Successful Logins</div>
            </div>
            <div class="stat-card" style="padding: 1rem; --stat-color: var(--danger);">
                <div class="stat-value" style="font-size: 1.25rem; color: var(--danger);">{{ number_format($failedLogins) }}</div>
                <div class="stat-label">Failed Logins</div>
            </div>
            <div class="stat-card" style="padding: 1rem; --stat-color: var(--info);">
                <div class="stat-value" style="font-size: 1.25rem; color: var(--accent);">{{ number_format($activeToday) }}</div>
                <div class="stat-label">Active in the Last 24 Hours</div>
            </div>
        </div>
        
        <!-- Search -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body">
                <form method="GET" action="" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <input type="text" name="search_user" class="form-input" placeholder="Search by username or name..." value="{{ htmlspecialchars($searchUser) }}" style="max-width: 400px;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    @if ($searchUser)
                    <a href="login_history.php" class="btn btn-secondary">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Login History Table -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span class="card-title">Login History</span>
                <span class="badge badge-info">{{ count($loginHistory) }} Records</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
<thead>
                             <tr>
                                 <th>User</th>
                                 <th>Username</th>
                                 <th>Role</th>
                                 <th>Login Time</th>
                                 <th>Logout Time</th>
                                 <th>Status</th>
                             </tr>
                         </thead>
                         <tbody>
                             @foreach ($loginHistory as $log)
                             <tr>
                                 <td><strong>{{ htmlspecialchars($log['full_name'] ?: 'Unknown') }}</strong></td>
                                 <td>{{ htmlspecialchars($log['username']) }}</td>
                                 <td><span class="badge badge-info">{{ ucfirst($log['role'] ?: 'N/A') }}</span></td>
                                 <td>{{ date('M d, Y h:i A', strtotime($log['login_time'])) }}</td>
                                 <td>
                                     @if ($log['logout_time'])
                                         {{ date('M d, Y h:i A', strtotime($log['logout_time'])) }}
                                     @else
                                         &mdash;
                                     @endif
                                 </td>
                                 <td>
                                     @if ($log['status'] === 'success')
                                         <span class="badge badge-success">Success</span>
                                     @else
                                         <span class="badge badge-danger">Failed</span>
                                     @endif
                                 </td>
                             </tr>
                            @endforeach
                            @if (empty($loginHistory))
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No login records found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
