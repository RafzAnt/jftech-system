<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Dashboard</title>
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
                <p class="dashboard-eyebrow">Operations overview</p>
                <h1 class="page-title">Business Dashboard</h1>
                <p class="dashboard-subtitle">Monitor customer activity, collections, and your team from one workspace.</p>
            </div>
            <div class="top-bar-actions">
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
                <div class="stat-value">PHP {{ number_format($stats['total_revenue'], 2) }}</div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-change positive">
                    <span>All time</span>
                </div>
            </a>
            <a href="bill_inquiry.php" class="stat-card stat-card-link" style="--stat-color: var(--warning)" aria-label="View pending bills">
                <div class="stat-value">{{ number_format($stats['pending_bills']) }}</div>
                <div class="stat-label">Pending Bills</div>
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
            <a href="user_management.php" class="stat-card stat-card-link" style="--stat-color: var(--accent-secondary)" aria-label="View system users">
                <div class="stat-value">{{ number_format($stats['total_users']) }}</div>
                <div class="stat-label">System Users</div>
                <div class="stat-change positive">
                    <span>Active accounts</span>
                </div>
            </a> 
            <a href="bill_inquiry.php" class="stat-card stat-card-link" style="--stat-color: var(--danger)" aria-label="View overdue bills">
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
                    <span class="card-title">Monthly Revenue</span>
                    <span class="badge badge-info">This Year</span>
                </div>
                <div class="card-body">
                    <div id="revenueChart" class="dashboard-chart" aria-live="polite"></div>
                </div>
            </div>
            <div class="card dashboard-chart-card">
                <div class="card-header dashboard-chart-header">
                    <span class="card-title">Clients by Barangay</span>
                    <span class="badge badge-success">Top 10</span>
                </div>
                <div class="card-body">
                    <div id="barangayChart" class="dashboard-chart" aria-live="polite"></div>
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
                    <a href="payment_history.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPayments as $payment)
                                <tr>
                                    <td>{{ htmlspecialchars($payment['client_name']) }}</td>
                                    <td style="color: var(--success); font-weight: 600;">PHP {{ number_format($payment['amount'], 2) }}</td>
                                    <td>{{ date('M d, Y', strtotime($payment['payment_date'])) }}</td>
                                    <td><span class="badge badge-info">{{ ucfirst($payment['payment_method']) }}</span></td>
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

            <!-- Today's Attendance -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Today's Attendance</span>
                    <a href="employees.php?tab=attendance" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Time In</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($todayAttendanceDetails as $att)
                                <tr>
                                    <td>{{ htmlspecialchars($att['employee_name']) }}</td>
                                    <td>{{ $att['morning_time_in'] ? date('h:i A', strtotime($att['morning_time_in'])) : '-' }}</td>
                                    <td>
                                        @if ($att['status_morning'] === 'present')
                                            <span class="badge badge-success">Present</span>
                                        @elseif ($att['status_morning'] === 'late')
                                            <span class="badge badge-warning">Late</span>
                                        @else
                                            <span class="badge badge-danger">Absent</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @if (empty($todayAttendanceDetails))
                                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No attendance records today</td></tr>
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
                <a href="login_history.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Login Time</th>
                                <th>Logged Out At</th>
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
                            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No login history</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    const monthlyRevenue = @json($monthlyRevenue);
    const barangayStats = @json($barangayStats);
    const chartColors = ['#00d4ff', '#00e676', '#ff1744', '#ffab00', '#e040fb', '#2979ff', '#76ff03', '#ff4081', '#18ffff', '#ff6d00'];

    function getBarangayColor(index, name, markerColor) {
        if (/^#[0-9a-f]{6}$/i.test(markerColor || '')) {
            return markerColor;
        }

        if (name) {
            const hash = [...name].reduce((sum, char) => sum + char.charCodeAt(0), 0);
            const hue = (hash * 67 + index * 137) % 360;
            return `hsl(${hue} 72% 58%)`;
        }

        return chartColors[index % chartColors.length];
    }

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

        const formatCurrency = value => `PHP ${Math.round(value).toLocaleString()}`;
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

    function renderBarangayChart() {
        const target = document.getElementById('barangayChart');
        const entries = barangayStats.filter(item => Number(item.client_count) > 0);
        if (!entries.length) {
            showEmptyChart(target, 'No client data yet', 'Register a client and assign a barangay to show this chart.');
            return;
        }
        
        const total = entries.reduce((sum, item) => sum + Number(item.client_count), 0);
        const circumference = 2 * Math.PI * 62;
        let offset = 0;
        const slices = entries.map((item, index) => {
            const color = getBarangayColor(index, item.barangay_name, item.marker_color);
            const length = Number(item.client_count) / total * circumference;
            const slice = `<circle cx="94" cy="94" r="62" fill="none" stroke="${color}" stroke-width="28" stroke-dasharray="${length} ${circumference - length}" stroke-dashoffset="${-offset}" transform="rotate(-90 94 94)"><title>${item.barangay_name}: ${item.client_count}</title></circle>`;
            offset += length;
            return slice;
        }).join('');
        const legend = entries.map((item, index) => `<li><span style="background:${getBarangayColor(index, item.barangay_name, item.marker_color)}"></span>${item.barangay_name} (${item.client_count})</li>`).join('');
        target.innerHTML = `<div class="barangay-chart"><svg class="donut-svg" viewBox="0 0 188 188" role="img" aria-label="Clients by barangay">${slices}<text x="94" y="90" text-anchor="middle" class="donut-total">${total}</text><text x="94" y="110" text-anchor="middle" class="donut-label">clients</text></svg><ul class="chart-legend">${legend}</ul></div>`;
    }

    renderRevenueChart();
    renderBarangayChart();
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
