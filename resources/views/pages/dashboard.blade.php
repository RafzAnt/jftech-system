<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Business Overview</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?{{ $dashboardCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar dashboard-topbar">
            <div>
                <p class="dashboard-eyebrow">Business overview</p>
                <h1 class="page-title">Business Overview</h1>
                <p class="dashboard-subtitle">View client activity, payments received, and your team in one place.</p>
            </div>
            <div class="top-bar-actions">
                @if (auth()->user()?->isSuperAdmin())
                <form method="GET" action="" class="dashboard-branch-form">
                    <label class="form-label" for="dashboardBranch" style="margin:0;">Branch</label>
                    <select name="branch" id="dashboardBranch" class="form-select" onchange="this.form.submit()" style="width:auto;min-width:170px;">
                        <option value="">All branches</option>
                        @foreach ($dashboardBranches as $dashboardBranch)
                        <option value="{{ $dashboardBranch }}" {{ $branch === $dashboardBranch ? 'selected' : '' }}>{{ $dashboardBranch }}</option>
                        @endforeach
                    </select>
                </form>
                @else
                <span class="badge badge-info">{{ $branchScopeLabel ?: 'No branch assigned' }} branch</span>
                @endif
                <span class="dashboard-date">{{ date('D, M j, Y') }}</span>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if (null !== request()->query('msg'))
        <div class="alert alert-success">
            {{ htmlspecialchars(request()->query('msg')) }}
        </div>
        @endif

        <!-- Stats Grid -->
        <div class="stats-grid">
            <a href="clients_browse.php" class="stat-card stat-card-link" style="--stat-color: var(--accent)" aria-label="View clients">
                <div class="stat-value">{{ number_format($stats['total_clients']) }}</div>
                <div class="stat-label">Total Clients</div>
                <div class="stat-change positive">
                    <span>{{ $stats['active_clients'] }} Active</span>
                </div>
            </a>
            <a href="payment_history.php" class="stat-card stat-card-link" style="--stat-color: var(--success)" aria-label="View payment history">
                <div class="stat-value">₱{{ number_format($stats['total_revenue'], 2) }}</div>
                <div class="stat-label">Total Payments Received</div>
                <div class="stat-change positive">
                    <span>All time</span>
                </div>
            </a>
            <a href="bill_inquiry.php" class="stat-card stat-card-link" style="--stat-color: var(--warning)" aria-label="View clients with pending bills">
                <div class="stat-value">{{ number_format($stats['clients_with_pending_bills']) }}</div>
                <div class="stat-label">Clients With Pending Bills</div>
                <div class="stat-change negative">
                    <span>{{ $stats['overdue_bills'] }} Overdue</span>
                </div>
            </a>
            <a href="employees.php?tab=details" class="stat-card stat-card-link" style="--stat-color: var(--info)" aria-label="View employees">
                <div class="stat-value">{{ number_format($stats['total_employees']) }}</div>
                <div class="stat-label">Employees</div>
                <div class="stat-change positive">
                    <span>{{ $stats['today_attendance'] }} Present Today</span>
                </div>
            </a>
            {{-- User Accounts is a super-admin page, so a branch admin gets the figure without the link. --}}
            @php($canOpenUsers = auth()->user()?->isSuperAdmin())
            @php($userCardTag = $canOpenUsers ? 'a' : 'div')
            <{{ $userCardTag }} class="stat-card {{ $canOpenUsers ? 'stat-card-link' : '' }}" style="--stat-color: var(--accent-secondary)" @if ($canOpenUsers) href="user_management.php" aria-label="View user accounts" @endif>
                <div class="stat-value">{{ number_format($stats['total_users']) }}</div>
                <div class="stat-label">User Accounts</div>
                <div class="stat-change positive">
                    <span>Active accounts</span>
                </div>
            </{{ $userCardTag }}>
            <a href="bill_inquiry.php?overdue=1" class="stat-card stat-card-link" style="--stat-color: var(--danger)" aria-label="View clients with overdue bills">
                <div class="stat-value">{{ number_format($stats['overdue_bills']) }}</div>
                <div class="stat-label">Overdue Bills</div>
                <div class="stat-change negative">
                    <span>Needs attention</span>
                </div>
            </a>
        </div>

        <!-- Charts Row -->
        <div class="row-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card dashboard-chart-card">
                <div class="card-header dashboard-chart-header">
                    <span class="card-title">Monthly Payments Received</span>
                    <span class="badge badge-info">This Year</span>
                </div>
                <div class="card-body">
                    <div id="revenueChart" class="dashboard-chart" aria-live="polite"></div>
                </div>
            </div>
            <div class="card dashboard-chart-card">
                <div class="card-header dashboard-chart-header">
                    <span class="card-title">Client Account Status</span>
                    <span class="badge badge-success">Live</span>
                </div>
                <div class="card-body">
                    <div id="clientStatusChart" class="dashboard-chart" aria-live="polite"></div>
                </div>
            </div>
        </div>

        <!-- Outstanding Balances widget removed -->

        <!-- Recent Activity Row -->
        <div class="row-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Recent Payments -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Recent Payments</span>
                    <a href="payment_history.php" class="btn btn-sm btn-secondary btn-view-all">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPayments as $payment)
                                <tr>
                                    <td>{{ htmlspecialchars($payment['client_name']) }}</td>
                                    <td style="color: var(--success); font-weight: 600;">₱{{ number_format($payment['amount'], 2) }}</td>
                                    <td>{{ date('M d, Y', strtotime($payment['payment_date'])) }}</td>
                                    <td><span class="badge badge-info">{{ str_replace('_', ' ', strtoupper($payment['payment_method'])) }}</span></td>
                                </tr>
                                @endforeach
                                @if (empty($recentPayments))
                                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No recent payments</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Billing Priorities -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Bills to Follow Up</span>
                    <a href="bill_inquiry.php" class="btn btn-sm btn-secondary btn-view-all">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($billingPriorities as $bill)
                                <tr>
                                    <td>{{ htmlspecialchars($bill['client_name']) }}</td>
                                    <td>{{ date('M d, Y', strtotime($bill['due_date'])) }}</td>
                                    <td style="font-weight: 600;">₱{{ number_format($bill['amount'], 2) }}</td>
                                    <td>
                                        @if ($bill['status'] === 'overdue')
                                            <span class="badge badge-danger">Overdue</span>
                                        @else
                                            <span class="badge badge-warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @if (empty($billingPriorities))
                                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No billing priorities right now</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Login Activity -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Recent Login Activity</span>
                @if (auth()->user()?->isSuperAdmin())
                <a href="login_history.php" class="btn btn-sm btn-secondary btn-view-all">View All</a>
                @endif
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLogins as $login)
                            <tr>
                                <td>{{ htmlspecialchars($login['full_name'] ?: $login['username']) }}</td>
                                <td>{{ ucfirst($login['role']) }}</td>
                                <td>{{ date('M d, h:i A', strtotime($login['login_time'])) }}</td>
                                <td>
                                    @if ($login['logout_time'])
                                        {{ date('M d, h:i A', strtotime($login['logout_time'])) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>
                                    @if ($login['status'] === 'success')
                                        <span class="badge badge-success">Success</span>
                                    @else
                                        <span class="badge badge-danger">Failed</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($recentLogins))
                            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No login records</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    const monthlyRevenue = @json($monthlyRevenue);
    const clientStatusStats = @json($clientStatusStats);
    const clientStatusColors = {
        active: '#22c55e',
        inactive: '#94a3b8',
        suspended: '#ef4444',
        disconnected: '#ef4444',
    };

    function showEmptyChart(element, title, detail) {
        element.innerHTML = `<div class="chart-empty"><strong>${title}</strong><span>${detail}</span></div>`;
    }

    function createSmoothPath(points) {
        if (!points.length) return '';
        if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

        let path = `M ${points[0].x} ${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
            const current = points[i];
            const next = points[i + 1];
            const controlX = (current.x + next.x) / 2;
            path += ` C ${controlX} ${current.y}, ${controlX} ${next.y}, ${next.x} ${next.y}`;
        }
        return path;
    }

    function renderRevenueChart() {
        const target = document.getElementById('revenueChart');
        const values = monthlyRevenue.map(month => {
            const value = Number(month.total);
            return Number.isFinite(value) && value > 0 ? value : 0;
        });
        if (!values.some(value => value > 0)) {
            showEmptyChart(target, 'No revenue data yet', 'Mark a bill as paid to show monthly revenue.');
            return;
        }

        const left = 54, right = 20, top = 18, chartWidth = 620, height = 172;
        const width = chartWidth - left - right;
        const bottom = top + height;
        const rawMax = Math.max(...values);
        const magnitude = 10 ** Math.floor(Math.log10(rawMax));
        const max = Math.ceil(rawMax / magnitude) * magnitude;
        const points = values.map((value, index) => {
            const x = left + (width * index / (values.length - 1));
            const y = bottom - (value / max * height);
            return { x, y, value };
        });

        const formatCurrency = value => `₱${Math.round(value).toLocaleString()}`;
        const maxY = rawMax;
        const grid = [0, 0.25, 0.5, 0.75, 1].map(fraction => {
            const y = bottom - (height * fraction);
            const tickValue = Math.round(max * fraction);
            return `<line x1="${left}" y1="${y}" x2="${left + width}" y2="${y}" class="chart-grid"/><text x="${left - 10}" y="${y + 4}" class="chart-axis" text-anchor="end">₱${tickValue.toLocaleString()}</text>`;
        }).join('');

        const labels = monthlyRevenue.map((month, index) => `<text x="${points[index].x}" y="${bottom + 25}" class="chart-axis" text-anchor="middle">${month.label}</text>`).join('');
        const linePath = createSmoothPath(points);
        const areaPath = `${linePath} L ${points[points.length - 1].x} ${bottom} L ${points[0].x} ${bottom} Z`;

        const dots = points.map((point, index) => {
            const isPeak = point.value === maxY && point.value > 0;
            const dotClass = isPeak ? 'chart-dot chart-dot--peak' : 'chart-dot';
            const radius = isPeak ? 5.5 : 4;
            return `<circle cx="${point.x}" cy="${point.y}" r="${radius}" class="${dotClass}" data-value="${point.value}"><title>${monthlyRevenue[index].label}: ${formatCurrency(point.value)}</title></circle>`;
        }).join('');

        target.innerHTML = `
            <svg class="chart-svg" viewBox="0 0 620 240" role="img" aria-label="Monthly revenue for the current year">
                <defs>
                    <linearGradient id="revenueGradient" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="#9b5cff" stop-opacity="0.42"/>
                        <stop offset="100%" stop-color="#6c3bc1" stop-opacity="0.03"/>
                    </linearGradient>
                </defs>
                ${grid}
                <path d="${areaPath}" class="chart-area" fill="url(#revenueGradient)"/>
                <path d="${linePath}" class="chart-line"/>
                ${dots}
                ${labels}
            </svg>
        `;
    }

    function renderClientStatusChart() {
        const target = document.getElementById('clientStatusChart');
        const entries = clientStatusStats.filter(item => Number(item.client_count) > 0);
        if (!entries.length) {
            showEmptyChart(target, 'No client data yet', 'Register a client to show account status here.');
            return;
        }
        
        const total = entries.reduce((sum, item) => sum + Number(item.client_count), 0);
        const circumference = 2 * Math.PI * 62;
        let offset = 0;
        const slices = entries.map((item, index) => {
            const status = String(item.status || '').trim().toLowerCase();
            const color = clientStatusColors[status] || ['#60a5fa', '#a78bfa', '#f472b6'][index % 3];
            const length = Number(item.client_count) / total * circumference;
            const visibleLength = Math.max(length - 4, 0);
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            const slice = `<circle class="donut-slice" cx="94" cy="94" r="62" fill="none" stroke="${color}" stroke-width="28" stroke-dasharray="${visibleLength} ${circumference - visibleLength}" stroke-dashoffset="${-offset}" transform="rotate(-90 94 94)"><title>${label}: ${item.client_count}</title></circle>`;
            offset += length;
            return slice;
        }).join('');
        const legend = entries.map((item, index) => {
            const status = String(item.status || '').trim().toLowerCase();
            const color = clientStatusColors[status] || ['#60a5fa', '#a78bfa', '#f472b6'][index % 3];
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            const percentage = Math.round(Number(item.client_count) / total * 100);
            return `<li style="--status-color:${color}"><span></span><span class="status-name">${label}</span><strong>${item.client_count}<small>${percentage}%</small></strong></li>`;
        }).join('');
        target.innerHTML = `<div class="client-status-chart"><div class="donut-wrap"><svg class="donut-svg" viewBox="0 0 188 188" role="img" aria-label="Clients by account status"><circle class="donut-track" cx="94" cy="94" r="62" fill="none" stroke-width="28"/>${slices}<text x="94" y="88" text-anchor="middle" class="donut-total">${total}</text><text x="94" y="110" text-anchor="middle" class="donut-label">TOTAL CLIENTS</text></svg></div><ul class="chart-legend">${legend}</ul></div>`;
    }

    renderRevenueChart();
    renderClientStatusChart();
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
