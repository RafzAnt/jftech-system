<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Reports History</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ @filemtime(public_path('css/jftech-theme.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?{{ @filemtime(public_path('css/reports.css')) ?: 1 }}">
</head>
<body>
    <div class="grid-overlay"></div>

    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Report History</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">List of reports prepared in the system</p>
            </div>
            <a href="/pages/reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>

        @if (session('msg'))
        <div class="alert alert-success">{{ htmlspecialchars(session('msg')) }}</div>
        @endif

        <div class="card">
            <div class="card-header">
                <span class="card-title">Report List</span>
                <span class="badge badge-info">{{ number_format(count($history)) }} entries</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Report Name</th>
                                <th>Type</th>
                                <th>Date Range</th>
                                <th>Prepared By</th>
                                <th>Records</th>
                                <th>Date Prepared</th>
                                <th style="width: 14%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $row)
                            <tr>
                                <td><strong>{{ htmlspecialchars($row->report_name) }}</strong></td>
                                <td><span class="badge badge-info">{{ ucfirst($row->report_type) }}</span></td>
                                <td>
                                    @if ($row->date_from || $row->date_to)
                                        {{ $row->date_from ? date('M d, Y', strtotime($row->date_from)) : '—' }} to {{ $row->date_to ? date('M d, Y', strtotime($row->date_to)) : '—' }}
                                    @else
                                        <span style="color: var(--text-muted);">All dates</span>
                                    @endif
                                </td>
                                <td>{{ htmlspecialchars($row->generated_by_name ?: 'System') }}</td>
                                <td>{{ number_format($row->record_count) }}</td>
                                <td>{{ date('M d, Y h:i A', strtotime($row->generated_at)) }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/pages/report_history/' . $row->id) }}" onsubmit="return confirm('Delete this report history entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($history))
                            <tr><td colspan="7" style="text-align:center; color: var(--text-muted);">No report history yet.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/jftech-main.js') }}?{{ @filemtime(public_path('js/jftech-main.js')) ?: 1 }}"></script>
</body>
</html>
