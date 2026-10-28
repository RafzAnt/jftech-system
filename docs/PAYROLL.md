# Payroll

Payroll is worked out from each employee's **daily rate** and their **attendance**, and the SSS, PhilHealth and Pag-IBIG
shares come from the contribution tables. The payroll form fills everything in by itself and every box can be typed over;
the form and the weekly command use the same arithmetic (`app/Support/PayrollCalculator.php`), so the numbers always agree.

Run the updates first: `php artisan migrate` on local MySQL, or in the Supabase SQL Editor
`database/supabase/migrations/2026_10_18_real_payroll.sql` and `database/supabase/migrations/2026_10_18_attendance_overtime.sql`
(both safe to rerun, they keep every existing record).

## The rules

| | Rule | Example (daily rate 500) |
|---|---|---|
| Monthly salary | daily rate × 24 days | 12,000 |
| Hourly rate | daily rate ÷ 8 | 62.50 |
| Basic salary | days worked × daily rate (a session with a time in is half a day) | 5 days = 2,500 |
| Late | hourly rate × minutes late ÷ 60 | 1 hour = 62.50, 30 minutes = 31.25 |
| Overtime | hourly rate × 1.25 × overtime hours | 1 hour = 78.13 |
| SSS | employee share of the monthly salary credit ÷ 4 | 600 ÷ 4 = 150 |
| PhilHealth | monthly salary × 5% ÷ 2 (employee half) ÷ 4 | 12,000 × 5% = 600 → 300 ÷ 4 = 75 |
| Pag-IBIG | 2% of the salary up to 10,000 (200) ÷ 4 | 200 ÷ 4 = 50 |

* **SSS**: the salary credit is the monthly salary rounded to the nearest 500, from 5,000 to 35,000. The employee pays 5%.
  The employer pays 10% plus the EC contribution (10, or 30 from a credit of 15,000), so 1,210 for a credit of 12,000.
  The employer's share is shown in the computation notes but never deducted.
* **PhilHealth**: 5% of the monthly salary counted from 10,000 to 100,000, shared equally with the employer.
* **Pag-IBIG**: employee and employer each pay 2% of up to 10,000, so 200 at most (1% for the employee up to 1,500 a month).
* **Late** counts the minutes from the start of the session to the time in, for a session the attendance marked *late*
  (after the "Mark as late after" grace time of the QR settings). A session that was not attended is not paid, so absences
  are only shown on the payslip ("Absent") and never deducted a second time.
* **Overtime** comes from the attendance, and it has **no QR code of its own**. The afternoon QR code works until the *last
  scan of the day* (22:00 by default), and the employee who works past the end of the afternoon (17:00) scans out with the
  same code when they finish: the time between 17:00 and that scan is the overtime (`afternoon time out` is kept at 17:00).
  The admin can also enter the overtime in/out by hand in *Manual Attendance*. The minutes go to the payroll (a week with only
  overtime still gets a payroll). **A day counts only when its overtime reaches 1 hour** (`AttendanceTime::MIN_OVERTIME_MINUTES`):
  59 minutes are recorded in the attendance but not paid, 60 or more are paid by the minute (75 minutes = 75, not 60). The
  admin can still type overtime
  hours over the standard in the payroll form. A time out after 17:00 on the afternoon session is *not* overtime any more.
  See "Overtime in the attendance" below.
* **Undertime** comes from the attendance too: a session whose time out is more than 5 minutes before its end (12:00 for the
  morning, 17:00 for the afternoon) is short by the minutes between the two, and the payroll deducts them like late (hourly rate
  x minutes / 60). The Attendance table and the employee app's history show the same *Undertime N min*. The form fills the minutes
  in and they can be typed over, like every other box.
* Each payroll deducts the contributions once: the monthly share divided by the payrolls in a month (4). In a month with
  five Sundays that is five deductions; change *Payrolls in a month* in the settings if that is not wanted.
* Net pay is gross pay (basic + overtime + the typed earnings) minus all deductions, and never below 0.

The rules (24 days, 4 payrolls, 8 hours, 1.25 overtime) are in **System Settings → Payroll Computation**, together with a
table of the contributions for the usual daily rates so the numbers can be checked. The tables themselves (SSS brackets,
PhilHealth rate, Pag-IBIG ceiling) are in `PayrollCalculator::sss()`, `philhealth()` and `pagibig()`; update those three
methods when the law changes them.

## Overtime in the attendance

* **How it is recorded** (Employees → Attendance, and the app): the afternoon QR code is made by the super admin, as before, but
  it now works from the afternoon start until the *Last Scan of the Day* of **System Settings** (22:00 by default; it is never
  earlier than the end of the afternoon). A scan after the end of the afternoon is always the **time out** of the day: the
  session ends at its usual time and the minutes up to the scan are saved as the day's overtime (`overtime_time_in` = the end
  of the afternoon, `overtime_time_out` = the scan, `overtime_minutes`). Nobody can *time in* for the afternoon after it has
  ended (the admin records that day by hand). The **last scan wins**: someone who scanned out on time and stays can scan again
  later, and the overtime follows the later scan. Overtime under an hour is saved too, but the answer of the app says it is not
  paid, and the Attendance Records mark it "not paid". The branch admin looks at the Overtime column before the weekly payroll
  is made (Sunday 23:55) and removes a wrong one with *Manual Attendance → No overtime*. No migration is needed; the overtime
  QR codes of the earlier system are refused by the app.
* **Manual Attendance** has an *Overtime in / out* block (and "No overtime" to remove it). Out must be after in and the day
  holds at most 12 hours of overtime. A branch admin enters it for the technicians of their branch only.
* The Attendance Records table and the printed attendance show the overtime and its minutes.

## The payroll form

There is no choice between automatic and manual. In **+ New Payroll** the admin picks an employee (type a name or code, then
click it) and the period, and the form fills in the days worked, the late minutes, the overtime hours, every amount and the
SSS, PhilHealth and Pag-IBIG straight away, with a note under *How it was worked out* for each number. Every box can then be
typed over, and what is typed wins when the payroll is saved. A box left empty keeps the standard computation. Changing the
days, minutes or hours recomputes the amount that follows from them, which can be typed over again. The earnings and
deductions that have no formula (holiday pay, allowances, cash advance, loans, ...) are typed.

The SSS, PhilHealth and Pag-IBIG boxes each show their **Standard** amount underneath, marked "changed" when it was typed over.
**Recalculate from attendance** loads the standard numbers again (what was typed over is replaced), for example after the
attendance was corrected. Saving an edited payroll keeps its boxes as they are; it does not recompute from the attendance
unless the boxes are emptied or Recalculate was pressed. When an old payroll (made before the real computation) is edited, its
contributions that are 0 are filled in with the standard amounts; type 0 on purpose for an employee who is not covered.

## Weekly command

`php artisan payroll:generate-weekly` creates one *processed* payroll for every employee who has attendance in that
Monday–Sunday week and no payroll for it yet. It is scheduled for Sunday 23:55 when the server runs
`php artisan schedule:run` every minute. Those payrolls carry an AUTOMATIC badge on the payslip; a payroll made on the form
has none.

Who pays whom is unchanged: a branch admin generates payroll for the technicians of their branch, the super admin only for
the Admin employees. One payroll per employee and period (an archived one does not count).

## Records from before

Payroll made before this update keeps its numbers. Its *late*, *undertime* and *absent* amounts were stored but never
deducted from the net pay, so its payslip does not list them under the deductions (new payslips do). Saving an old record
on the form keeps its boxes as they are.
