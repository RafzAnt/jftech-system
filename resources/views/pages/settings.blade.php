<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - System Settings</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?{{ $settingsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">System Settings</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Configure system preferences</p>
            </div>
        </div>

        <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" title="Toggle theme">
            <span class="theme-toggle__icon" data-theme-icon></span>
            <span class="theme-toggle__label" data-theme-label>Dark Mode</span>
        </button>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Company Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">General Settings</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="settings[company_name]" class="form-input" value="{{ htmlspecialchars($settings['company_name']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Address</label>
                            <input type="text" name="settings[company_address]" class="form-input" value="{{ htmlspecialchars($settings['company_address']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="settings[company_contact]" class="form-input" value="{{ htmlspecialchars($settings['company_contact']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="settings[company_email]" class="form-input" value="{{ htmlspecialchars($settings['company_email']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Overdue Penalty (PHP)</label>
                            <input type="number" name="settings[penalty_rate]" class="form-input" value="{{ $settings['penalty_rate']['setting_value'] ?? '50' }}" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Currency</label>
                            <input type="text" name="settings[currency]" class="form-input" value="{{ $settings['currency']['setting_value'] ?? 'PHP' }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>

        <!-- QR Time Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Attendance QR Time Settings</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Morning QR Start Time</label>
                            <input type="time" name="settings[qr_morning_start]" class="form-input" value="{{ $settings['qr_morning_start']['setting_value'] ?? '08:00' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Morning QR End Time</label>
                            <input type="time" name="settings[qr_morning_end]" class="form-input" value="{{ $settings['qr_morning_end']['setting_value'] ?? '12:00' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Afternoon QR Start Time</label>
                            <input type="time" name="settings[qr_afternoon_start]" class="form-input" value="{{ $settings['qr_afternoon_start']['setting_value'] ?? '13:00' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Afternoon QR End Time</label>
                            <input type="time" name="settings[qr_afternoon_end]" class="form-input" value="{{ $settings['qr_afternoon_end']['setting_value'] ?? '17:00' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Late Threshold (minutes)</label>
                            <input type="number" name="settings[late_threshold_minutes]" class="form-input" value="{{ $settings['late_threshold_minutes']['setting_value'] ?? '30' }}" min="1" max="120">
                            <small style="color: var(--text-muted);">Status stays blank until this many minutes after the session starts. It becomes absent after the session ends when there is no time in or time out.</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Time Settings</button>
                </form>
            </div>
        </div>

    </div>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
