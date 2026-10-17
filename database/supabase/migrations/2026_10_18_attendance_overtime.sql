-- Overtime in the attendance: a third session with its own QR code. The employee scans the overtime QR code (or the admin
-- enters it by hand) when the extra work starts and ends, and the minutes go to the payroll.
-- Run this once in the Supabase SQL Editor; running it again changes nothing.

begin;

alter table public.attendance
    add column if not exists overtime_time_in time,
    add column if not exists overtime_time_out time,
    add column if not exists overtime_minutes integer not null default 0;

-- qr_codes.qr_type is a plain varchar here, so the new 'overtime' type needs no change to the table.
insert into public.system_settings (setting_key, setting_value, setting_label) values
    ('qr_overtime_start', '17:00:00', 'Overtime QR Start Time'),
    ('qr_overtime_end', '22:00:00', 'Overtime QR End Time')
on conflict (setting_key) do nothing;

commit;
