<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Update Client</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}?{{ $formsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Update Client</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Search and update client information</p>
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Search Section -->
        @if (!$client)
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Search Client</span>
            </div>
            <div class="card-body">
                <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label">Search by name, code, or contact number</label>
                        <input type="text" name="search" class="form-input" placeholder="Enter search term..." value="{{ htmlspecialchars(request()->query('search') ?? '') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>

                @if (request()->query('search') ?? '')
                <div class="section-title">Search Results</div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Barangay</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($searchResults as $result)
                            <tr>
                                <td><strong>{{ $result['client_code'] }}</strong></td>
                                <td>{{ htmlspecialchars($result['full_name']) }}</td>
                                <td>{{ $result['barangay_name'] ?: 'N/A' }}</td>
                                <td>{{ $result['contact_number'] }}</td>
                                <td>
                                    <span class="badge badge-{{ $result['status'] === 'active' ? 'success' : ($result['status'] === 'inactive' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($result['status']) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="?id={{ $result['id'] }}" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($searchResults))
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No clients found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Update Form -->
        @if ($client)
        <div class="card">
            <div class="card-header">
                <span class="card-title">Edit Client: {{ htmlspecialchars($client['full_name']) }}</span>
                <span class="badge badge-info">{{ $client['client_code'] }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                    <input type="hidden" name="update_client" value="1">

                    @php
                        $clientNameParts = preg_split('/\s+/', trim($client['full_name'])) ?: [];
                        $clientFirstName = array_shift($clientNameParts) ?? '';
                        $clientLastName = count($clientNameParts) ? array_pop($clientNameParts) : '';
                        $clientMiddleName = implode(' ', $clientNameParts);
                    @endphp

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-input" value="{{ htmlspecialchars($clientFirstName) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name <span style="color: var(--text-muted);">(Optional)</span></label>
                            <input type="text" name="middle_name" class="form-input" value="{{ htmlspecialchars($clientMiddleName) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-input" value="{{ htmlspecialchars($clientLastName) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number *</label>
                            <input type="text" name="contact_number" class="form-input" value="{{ htmlspecialchars($client['contact_number']) }}" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" value="{{ htmlspecialchars($client['email'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">WiFi Plan *</label>
                            <select name="internet_plan_id" class="form-select" id="wifiPlan" required>
                                @foreach ($plans as $plan)
                                <option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" {{ ($client['internet_plan_id'] ?? null) == $plan['id'] ? 'selected' : '' }}>
                                    {{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo{{ $plan['status'] !== 'active' ? ' — Inactive' : '' }}
                                </option>
                                @endforeach
                            </select>
                            <small style="display:block;margin-top:.4rem;color:var(--text-muted);">Changing the plan uses its current price. Saving other client details keeps the recorded installation price.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Rate (PHP)</label>
                            <input type="number" name="monthly_rate" class="form-input" id="monthlyRate" value="{{ $client['monthly_rate'] }}" step="0.01" readonly>
                        </div>
                    </div>

                    <div class="section-title">Address Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" class="form-select" id="barangaySelect">
                                <option value="">Select Barangay</option>
                                @foreach ($barangays as $barangay)
                                <option value="{{ $barangay['id'] }}" {{ $client['barangay_id'] == $barangay['id'] ? 'selected' : '' }}>
                                    {{ $barangay['barangay_name'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Exact Address</label>
                            <input type="text" name="exact_address" class="form-input" value="{{ htmlspecialchars($client['exact_address'] ?? '') }}">
                        </div>
                    </div>


                    <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                        <a href="clients_update.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    @if ($client)
    <script>
    document.getElementById("wifiPlan").addEventListener("change", function () {
        document.getElementById("monthlyRate").value = this.options[this.selectedIndex].dataset.price || 0;
    });
    </script>
    @endif
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
