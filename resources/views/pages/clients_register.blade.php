<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Register Client</title>
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
                <h1 class="page-title">Register New Client</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Add a new WiFi client to the system</p>
            </div>
            <a href="clients_browse.php" class="btn btn-secondary">Browse Clients</a>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <form method="POST" action="" class="card">
            @csrf
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" placeholder="Juan" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name <span style="color: var(--text-muted);">(Optional)</span></label>
                        <input type="text" name="middle_name" class="form-input" placeholder="Santos">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" placeholder="Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact_number" class="form-input" placeholder="09123456789" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" placeholder="juan@email.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">WiFi Plan *</label>
                        <select name="internet_plan_id" class="form-select" id="wifiPlan" required>
                            <option value="">Select Plan</option>
                            @foreach ($plans as $plan)
                            <option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" data-installation-fee="{{ $plan['installation_fee'] }}">
                                {{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Rate (PHP) *</label>
                        <input type="number" name="monthly_rate" class="form-input" id="monthlyRate" placeholder="500.00" step="0.01" readonly required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation Fee (PHP) *</label>
                        <input type="number" class="form-input" id="installationFee" step="0.01" readonly required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation Date *</label>
                        <input type="date" name="installation_date" class="form-input" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="section-title">Address Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Barangay *</label>
                        <select name="barangay_id" class="form-select" id="barangaySelect" required>
                            <option value="">Select Barangay</option>
                            @foreach ($barangays as $barangay)
                            <option value="{{ $barangay['id'] }}">
                                {{ $barangay['barangay_name'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Exact Address *</label>
                        <input type="text" name="exact_address" class="form-input" placeholder="Purok 1, Sitio..." required>
                    </div>
                </div>


                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Client</button>
                </div>
            </div>
        </form>
    </div>

    <script>
    // WiFi plan handler
    document.getElementById('wifiPlan').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        document.getElementById('monthlyRate').value = option.dataset.price || 0;
        document.getElementById('installationFee').value = option.dataset.installationFee || 0;
    });

    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
