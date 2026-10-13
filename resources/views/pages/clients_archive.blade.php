<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Archived Clients</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/clients_browse.css') }}?{{ $clientsBrowseCssVersion }}">
</head>
<body>
    @include('layouts.sidebar')
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Archived Clients</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Restore or permanently delete archived clients.</p>
            </div>
            <a href="clients_browse.php" class="btn btn-success">Back to Clients</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Branch</th>
                                <th>Barangay</th>
                                <th>Installation Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($archivedClients as $client)
                            <tr>
                                <td><strong>{{ htmlspecialchars($client['full_name']) }}</strong></td>
                                <td>{{ $client['branch_name'] ?: 'N/A' }}</td>
                                <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                                <td>{{ $client['installation_date'] ? date('M d, Y', strtotime($client['installation_date'])) : '-' }}</td>
                                <td>
                                    <form method="post" style="display:inline;" action="/pages/clients_archive.php">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button class="btn btn-sm btn-primary" type="submit">Restore</button>
                                    </form>
                                    <form method="post" style="display:inline; margin-left:.5rem;" action="/pages/clients_archive.php" onsubmit="return confirm('Permanently delete this client and all related data?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete Permanently</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($archivedClients))
                            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No archived clients</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
