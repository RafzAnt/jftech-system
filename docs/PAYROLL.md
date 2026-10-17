# Payroll

Payroll is worked out from each employee's **daily rate** and their **attendance**, and the SSS, PhilHealth and Pag-IBIG
shares come from the contribution tables. It can be made **automatically** or **by hand**; both use the same arithmetic
(`app/Support/PayrollCalculator.php`), so the numbers always agree.

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
* **Overtime** comes from the attendance. It is a third session next to the morning and the afternoon: the employee scans
  the **overtime QR code** to start it and again to end it, or the admin enters the overtime in/out by hand in *Manual
  Attendance*. The minutes between the two go to the payroll (a week with only overtime still gets a payroll). A time out
  after 17:00 on the afternoon session is *not* overtime any more. See "Overtime in the attendance" below.
* **Undertime** is typed in minutes (the clock does not track it) and is deducted like late.
* Each payroll deducts the contributions once: the monthly share divided by the payrolls in a month (4). In a month with
  five Sundays that is five deductions; change *Payrolls in a month* in the settings if that is not wanted.
* Net pay is gross pay (basic + overtime + the typed earnings) minus all deductions, and never below 0.

The rules (24 days, 4 payrolls, 8 hours, 1.25 overtime) are in **System Settings → Payroll Computation**, together with a
table of the contributions for the usual daily rates so the numbers can be checked. The tables themselves (SSS brackets,
PhilHealth rate, Pag-IBIG ceiling) are in `PayrollCalculator::sss()`, `philhealth()` and `pagibig()`; update those three
methods when the law changes them.

## Overtime in the attendance

* **Overtime QR code** (Employees → Attendance): the super admin generates it on the days overtime is approved, like the
  morning and afternoon codes. It works between the *Overtime QR Start / End Time* of **System Settings** (17:00–22:00 by
  default). In the app the first scan is **Overtime In**, the second **Overtime Out**; a third scan, or a second scan in the
  same minute, is refused. The Attendance screen of the app shows the running overtime and the history lists it.
* **Manual Attendance** has an *Overtime in / out* block (and "No overtime" to remove it). Out must be after in and the day
  holds at most 12 hours of overtime. A branch admin enters it for the technicians of their branch only.
* The Attendance Records table and the printed attendance show the overtime and its minutes.

## Automatic

* **Generate Automatic Payroll** (Payroll page, pick any date of the week): one *processed* payroll for every employee the
  account may pay who has attendance in that Monday–Sunday week and no payroll for it yet.
* `php artisan payroll:generate-weekly` does the same for everybody; it is scheduled for Sunday 23:55 when the server
  runs `php artisan schedule:run` every minute. Vercel has no scheduler, so use the button there.
* **+ New Payroll → Automatic** works out one employee and period on the form, shows how each number was computed, and
  recomputes from the attendance every time it is saved. Only the undertime minutes and the extra earnings and deductions
  (holiday pay, allowances, cash advance, loans, ...) are typed.

## Manual

**+ New Payroll → Manual** lets the admin type the days, the late minutes, the overtime hours, and every amount. The form
starts from the standard computation; an amount left empty keeps it. Changing the days, minutes or hours recomputes the
amount that follows from them, which can then be typed over.

Who pays whom is unchanged: a branch admin generates payroll for the technicians of their branch, the super admin only for
the Admin employees. One payroll per employee and period (an archived one does not count).

## Records from before

Payroll made before this update keeps its numbers. Its *late*, *undertime* and *absent* amounts were stored but never
deducted from the net pay, so its payslip does not list them under the deductions (new payslips do, and carry an
AUTOMATIC / MANUAL badge). Editing an old record switches it to manual.
