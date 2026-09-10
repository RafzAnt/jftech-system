<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Inventory Requests</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/inventory_requests.css') }}?{{ $inventoryRequestsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Inventory Requests</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Approve technician inventory requests</p>
            </div>
            @if ($pendingCount > 0)
            <span class="badge badge-warning">{{ $pendingCount }} Pending</span>
            @endif
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <div class="filter-tabs">
            <a href="inventory_requests.php?status=all" class="filter-tab {{ $filterStatus === 'all' ? 'active' : '' }}">All</a>
            <a href="inventory_requests.php?status=pending" class="filter-tab {{ $filterStatus === 'pending' ? 'active' : '' }}">
                Pending @if ($pendingCount > 0)({{ $pendingCount }})@endif
            </a>
            <a href="inventory_requests.php?status=approved" class="filter-tab {{ $filterStatus === 'approved' ? 'active' : '' }}">Approved</a>
            <a href="inventory_requests.php?status=denied" class="filter-tab {{ $filterStatus === 'denied' ? 'active' : '' }}">Denied</a>
        </div>

        <div class="card">
            <div class="card-body" style="padding: 0;">
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Comments / Area</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Processed By</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $req)
                            @php
$statusClass = 'status-' . $req['status'];
                            $processedBy = '';
                            if ($req['status'] !== 'pending') {
                                $processedBy = $req['approved_by_full_name'] ?: $req['approved_by_name'] ?: 'System';
                            }
                            
@endphp
                            <tr>
                                <td>#{{ $req['id'] }}</td>
                                <td><strong>{{ htmlspecialchars($req['employee_name']) }}</strong></td>
                                <td>{{ htmlspecialchars($req['item_name']) }}</td>
                                <td>{{ $req['quantity'] }}</td>
                                <td style="max-width: 250px; padding: 1rem;">
                                    @if ($req['comments'])
                                        <span style="color: var(--text-secondary); font-size: 0.9rem;">{{ nl2br(htmlspecialchars($req['comments'])) }}</span>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                                <td><span class="status-badge {{ $statusClass }}">{{ ucfirst($req['status']) }}</span></td>
                                <td>{{ date('M d, Y h:i A', strtotime($req['created_at'])) }}</td>
                                <td style="font-size: 0.85rem; color: var(--text-secondary);">{{ $processedBy ?: '-' }}</td>
                                <td class="actions-column">
                                    @if ($req['status'] === 'pending')
                                    <div class="request-actions">
                                        <form method="POST" action="{{ route('inventory-requests.decide', ['inventoryRequest' => $req['id'], 'decision' => 'approve']) }}" onsubmit="return confirm('Approve this request and release stock?');">
            @csrf
                                            <button type="submit" class="btn btn-sm btn-success request-action-btn">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory-requests.decide', ['inventoryRequest' => $req['id'], 'decision' => 'deny']) }}" onsubmit="return confirm('Deny this request?');">
            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger request-action-btn">Deny</button>
                                        </form>
                                    </div>
                                    @else
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">Processed</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($requests))
                            <tr><td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No requests found</td></tr>
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
