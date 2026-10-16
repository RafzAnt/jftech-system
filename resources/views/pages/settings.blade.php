<!DOCTYPE html>
<html lang="en-PH" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Business Settings</title>
    <script>
        try {
            var savedTheme = localStorage.getItem('jftech-theme');
            document.documentElement.setAttribute('data-theme', savedTheme === 'light' ? 'light' : 'dark');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?{{ $settingsCssVersion }}">
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Business Settings</h1>
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
                <form method="POST" action="" class="settings-form">
            @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="settings[company_name]" class="form-input" value="{{ htmlspecialchars($settings['company_name']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Business Address</label>
                            <input type="text" name="settings[company_address]" class="form-input" value="{{ htmlspecialchars($settings['company_address']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="settings[company_contact]" class="form-input" value="{{ htmlspecialchars($settings['company_contact']['setting_value'] ?? '') }}" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="settings[company_email]" class="form-input" value="{{ htmlspecialchars($settings['company_email']['setting_value'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Currency</label>
                            <input type="text" name="settings[currency]" class="form-input" value="{{ $settings['currency']['setting_value'] ?? 'PHP' }}">
                            <small style="color:var(--text-muted);">Use PHP for the Philippine peso.</small>
                        </div>
                    </div>
                    <div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Settings</button></div>
                </form>
            </div>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Weekly Payroll Deductions</span>
            </div>
            <div class="card-body">
                            <p style="margin:0 0 1rem;color:var(--text-muted);font-size:.85rem;">These amounts are filled in automatically using each employee’s saved daily rate. Payroll is prepared every Sunday at 11:55 PM (Philippine time) after the work week ends. The admin reviews it and marks it paid when the pay is released.</p>
                <form method="POST" action="" class="settings-form">
                    @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        @foreach ([500 => 'Technician', 580 => 'Technician II', 600 => 'Admin'] as $rate => $role)
                        <div class="form-group" style="grid-column:1/-1;margin-bottom:0;"><strong>₱{{ $rate }}/day — {{ $role }}</strong></div>
                        <div class="form-group"><label class="form-label">Pag-IBIG / HDMF</label><input type="number" name="settings[payroll_{{ $rate }}_pagibig]" class="form-input" step="0.01" min="0" value="{{ $settings['payroll_' . $rate . '_pagibig']['setting_value'] ?? '0.00' }}"></div>
                        <div class="form-group"><label class="form-label">SSS</label><input type="number" name="settings[payroll_{{ $rate }}_sss]" class="form-input" step="0.01" min="0" value="{{ $settings['payroll_' . $rate . '_sss']['setting_value'] ?? '0.00' }}"></div>
                        <div class="form-group"><label class="form-label">PhilHealth</label><input type="number" name="settings[payroll_{{ $rate }}_philhealth]" class="form-input" step="0.01" min="0" value="{{ $settings['payroll_' . $rate . '_philhealth']['setting_value'] ?? '0.00' }}"></div>
                        @endforeach
                    </div>
                    <div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Payroll Deductions</button></div>
                </form>
            </div>
        </div>

        <!-- QR Time Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Attendance QR Time Settings</span>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="settings-form">
            @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="morningStartTime">Morning QR Start Time</label>
                            <div class="time-input-control">
                                <input type="time" id="morningStartTime" name="settings[qr_morning_start]" class="form-input" value="{{ $settings['qr_morning_start']['setting_value'] ?? '08:00' }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('morningStartTime')" aria-label="Choose morning QR start time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="morningEndTime">Morning QR End Time</label>
                            <div class="time-input-control">
                                <input type="time" id="morningEndTime" name="settings[qr_morning_end]" class="form-input" value="{{ $settings['qr_morning_end']['setting_value'] ?? '12:00' }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('morningEndTime')" aria-label="Choose morning QR end time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="afternoonStartTime">Afternoon QR Start Time</label>
                            <div class="time-input-control">
                                <input type="time" id="afternoonStartTime" name="settings[qr_afternoon_start]" class="form-input" value="{{ $settings['qr_afternoon_start']['setting_value'] ?? '13:00' }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('afternoonStartTime')" aria-label="Choose afternoon QR start time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="afternoonEndTime">Afternoon QR End Time</label>
                            <div class="time-input-control">
                                <input type="time" id="afternoonEndTime" name="settings[qr_afternoon_end]" class="form-input" value="{{ $settings['qr_afternoon_end']['setting_value'] ?? '17:00' }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('afternoonEndTime')" aria-label="Choose afternoon QR end time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mark as Late After (minutes)</label>
                            <input type="number" name="settings[late_threshold_minutes]" class="form-input" value="{{ $settings['late_threshold_minutes']['setting_value'] ?? '30' }}" min="1" max="120">
                            <small style="color: var(--text-muted);">Status stays blank until this many minutes after the session starts. It becomes absent after the session ends when there is no time in or time out.</small>
                        </div>
                    </div>
                    <div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Time Settings</button></div>
                </form>
            </div>
        </div>

    </div>
    <div class="modal-overlay" id="timePickerModal" onclick="if (event.target === this) closeTimePicker()">
        <div class="modal-content modal-small" role="dialog" aria-modal="true" aria-labelledby="timePickerTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="timePickerTitle">Choose Time</h3>
                <button type="button" class="modal-close" onclick="closeTimePicker()" aria-label="Close time picker">&times;</button>
            </div>
            <div class="time-picker-selectors">
                <div class="form-group">
                    <label class="form-label" for="timePickerHour">Hour</label>
                    <select class="form-select" id="timePickerHour"></select>
                </div>
                <span class="time-picker-separator">:</span>
                <div class="form-group">
                    <label class="form-label" for="timePickerMinute">Minute</label>
                    <select class="form-select" id="timePickerMinute"></select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="timePickerPeriod">Period</label>
                    <select class="form-select" id="timePickerPeriod"><option value="AM">AM</option><option value="PM">PM</option></select>
                </div>
            </div>
            <div class="time-picker-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTimePicker()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyTimePicker()">Set Time</button>
            </div>
        </div>
    </div>
    <script>
    let activeTimeInput = null;

    const timePickerHour = document.getElementById('timePickerHour');
    const timePickerMinute = document.getElementById('timePickerMinute');
    const timePickerPeriod = document.getElementById('timePickerPeriod');
    for (let number = 1; number <= 12; number += 1) {
        const value = String(number).padStart(2, '0');
        timePickerHour.add(new Option(value, value));
    }
    for (let number = 0; number < 60; number += 1) {
        const value = String(number).padStart(2, '0');
        timePickerMinute.add(new Option(value, value));
    }

    function openTimePicker(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        activeTimeInput = input;
        const [hourValue = '00', minute = '00'] = input.value.split(':');
        const hour24 = Number(hourValue);
        timePickerPeriod.value = hour24 >= 12 ? 'PM' : 'AM';
        timePickerHour.value = String(hour24 % 12 || 12).padStart(2, '0');
        timePickerMinute.value = minute;
        document.getElementById('timePickerModal').classList.add('active');
        timePickerHour.focus();
    }

    function applyTimePicker() {
        if (activeTimeInput) {
            let hour24 = Number(timePickerHour.value) % 12;
            if (timePickerPeriod.value === 'PM') hour24 += 12;
            activeTimeInput.value = `${String(hour24).padStart(2, '0')}:${timePickerMinute.value}`;
            activeTimeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeTimePicker();
    }

    function closeTimePicker() {
        document.getElementById('timePickerModal').classList.remove('active');
        activeTimeInput = null;
    }
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
