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
                <p style="color: var(--text-muted); font-size: 0.85rem;">{{ $canEditSettings ? 'Configure system preferences' : 'System preferences for every branch. Only the super admin can change them.' }}</p>
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
                    <fieldset{{ $canEditSettings ? '' : ' disabled' }} style="border:0;padding:0;margin:0;min-width:0;">
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
                    @if($canEditSettings)<div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Settings</button></div>@endif
                </fieldset></form>
            </div>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Payroll Computation</span>
            </div>
            <div class="card-body">
                <p style="margin:0 0 1rem;color:var(--text-muted);font-size:.85rem;">Pay is worked out from each employee’s daily rate and attendance. Late is deducted and overtime is added by the minute from the hourly rate (daily rate ÷ working hours). SSS, PhilHealth and Pag-IBIG are the employee’s monthly share from the contribution tables, divided by the payrolls in a month. The admin reviews each payroll and marks it paid when the pay is released.</p>
                <form method="POST" action="" class="settings-form">
                    <fieldset{{ $canEditSettings ? '' : ' disabled' }} style="border:0;padding:0;margin:0;min-width:0;">
                    @csrf
                    <input type="hidden" name="update_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Working days in a month</label><input type="number" name="settings[payroll_days_per_month]" class="form-input" step="1" min="1" max="31" value="{{ $settings['payroll_days_per_month']['setting_value'] ?? '24' }}"><small style="color:var(--text-muted);">Monthly salary = daily rate × this.</small></div>
                        <div class="form-group"><label class="form-label">Payrolls in a month</label><input type="number" name="settings[payroll_weeks_per_month]" class="form-input" step="1" min="1" max="5" value="{{ $settings['payroll_weeks_per_month']['setting_value'] ?? '4' }}"><small style="color:var(--text-muted);">Each contribution is the monthly share ÷ this.</small></div>
                        <div class="form-group"><label class="form-label">Working hours in a day</label><input type="number" name="settings[payroll_hours_per_day]" class="form-input" step="0.5" min="1" max="24" value="{{ $settings['payroll_hours_per_day']['setting_value'] ?? '8' }}"><small style="color:var(--text-muted);">Hourly rate = daily rate ÷ this.</small></div>
                        <div class="form-group"><label class="form-label">Overtime multiplier</label><input type="number" name="settings[payroll_overtime_rate]" class="form-input" step="0.01" min="1" max="3" value="{{ $settings['payroll_overtime_rate']['setting_value'] ?? '1.25' }}"><small style="color:var(--text-muted);">Overtime pay = hourly rate × this × hours.</small></div>
                    </div>
                    @if($canEditSettings)<div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Payroll Rules</button></div>@endif
                </fieldset></form>

                <h4 style="margin:1.5rem 0 .5rem;font-size:.9rem;">Contributions by daily rate (employee share)</h4>
                <p style="margin:0 0 .75rem;color:var(--text-muted);font-size:.8rem;">SSS: 5% of the monthly salary credit (salary rounded to the nearest ₱500, from ₱5,000 to ₱35,000). PhilHealth: 5% of the monthly salary (from ₱10,000 to ₱100,000), shared equally. Pag-IBIG: 2% of the salary up to ₱10,000.</p>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Daily Rate</th><th>Monthly Salary</th>
                                <th>SSS / month</th><th>PhilHealth / month</th><th>Pag-IBIG / month</th>
                                <th>SSS / payroll</th><th>PhilHealth / payroll</th><th>Pag-IBIG / payroll</th><th>Total / payroll</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payrollSamples as $sample)
                            <tr>
                                <td>₱{{ number_format($sample['rate'], 2) }}</td>
                                <td>₱{{ number_format($sample['monthly_salary'], 2) }}</td>
                                <td>₱{{ number_format($sample['sss']['employee'], 2) }}</td>
                                <td>₱{{ number_format($sample['philhealth']['employee'], 2) }}</td>
                                <td>₱{{ number_format($sample['pagibig']['employee'], 2) }}</td>
                                <td>₱{{ number_format($sample['weekly']['sss'], 2) }}</td>
                                <td>₱{{ number_format($sample['weekly']['philhealth'], 2) }}</td>
                                <td>₱{{ number_format($sample['weekly']['pagibig'], 2) }}</td>
                                <td><strong>₱{{ number_format($sample['weekly']['sss'] + $sample['weekly']['philhealth'] + $sample['weekly']['pagibig'], 2) }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- QR Time Settings -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Attendance QR Time Settings</span>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="settings-form">
                    <fieldset{{ $canEditSettings ? '' : ' disabled' }} style="border:0;padding:0;margin:0;min-width:0;">
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
                            <label class="form-label" for="overtimeStartTime">Overtime QR Start Time</label>
                            <div class="time-input-control">
                                <input type="time" id="overtimeStartTime" name="settings[qr_overtime_start]" class="form-input" value="{{ substr($settings['qr_overtime_start']['setting_value'] ?? '17:00', 0, 5) }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('overtimeStartTime')" aria-label="Choose overtime QR start time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="overtimeEndTime">Overtime QR End Time</label>
                            <div class="time-input-control">
                                <input type="time" id="overtimeEndTime" name="settings[qr_overtime_end]" class="form-input" value="{{ substr($settings['qr_overtime_end']['setting_value'] ?? '22:00', 0, 5) }}">
                                <button type="button" class="time-picker-button" onclick="openTimePicker('overtimeEndTime')" aria-label="Choose overtime QR end time"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg></button>
                            </div>
                            <small style="color: var(--text-muted);">Employees can scan the overtime QR code between these times, once to start the overtime and once to end it.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mark as Late After (minutes)</label>
                            <input type="number" name="settings[late_threshold_minutes]" class="form-input" value="{{ $settings['late_threshold_minutes']['setting_value'] ?? '30' }}" min="1" max="120">
                            <small style="color: var(--text-muted);">Status stays blank until this many minutes after the session starts. It becomes absent after the session ends when there is no time in or time out.</small>
                        </div>
                    </div>
                    @if($canEditSettings)<div class="settings-form-actions"><button type="submit" class="btn btn-primary">Save Time Settings</button></div>@endif
                </fieldset></form>
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
