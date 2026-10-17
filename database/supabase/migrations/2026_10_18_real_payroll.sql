-- Real payroll: the pay follows from the daily rate and the attendance, and the SSS, PhilHealth and Pag-IBIG shares from the
-- contribution tables. Run this once in the Supabase SQL Editor; running it again changes nothing.
--
--  * total_days_worked takes decimals (a session is half a day)
--  * late_minutes / undertime_minutes: the minutes behind the late and undertime amounts
--  * computation_mode: 'auto' or 'manual'. Records from before this change stay empty, which tells the payslip that
--    their late and undertime were never deducted.
--  * four working rules replace the old fixed weekly amounts for the daily rates 500, 580 and 600

begin;

alter table public.payroll alter column total_days_worked type numeric(5,2) using total_days_worked::numeric(5,2);
alter table public.payroll alter column total_days_worked set default 0;

alter table public.payroll
    add column if not exists late_minutes integer not null default 0,
    add column if not exists undertime_minutes integer not null default 0,
    add column if not exists computation_mode varchar(10);

insert into public.system_settings (setting_key, setting_value, setting_label) values
    ('payroll_days_per_month', '24', 'Working days in a month'),
    ('payroll_weeks_per_month', '4', 'Payrolls in a month (contributions are divided by this)'),
    ('payroll_hours_per_day', '8', 'Working hours in a day'),
    ('payroll_overtime_rate', '1.25', 'Overtime pay multiplier')
on conflict (setting_key) do nothing;

delete from public.system_settings where setting_key in (
    'payroll_500_sss', 'payroll_500_philhealth', 'payroll_500_pagibig',
    'payroll_580_sss', 'payroll_580_philhealth', 'payroll_580_pagibig',
    'payroll_600_sss', 'payroll_600_philhealth', 'payroll_600_pagibig'
);

commit;
