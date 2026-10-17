# Employee app and the admin website

The employee app (`/home/rafael/JFTECH/jftech`, Flutter) talks to the same Laravel server as the admin website.
Run the migration first: `php artisan migrate` on local MySQL, or
`database/supabase/migrations/2026_10_17_nap_boxes_and_work_requests.sql` in the Supabase SQL Editor.

## The flow

1. **Login** – `employee_login.php` returns a sign-in `token`. The app sends it as `Authorization: Bearer <token>` on
   every other call, so a request can only act as the employee who signed in (24 hours, or until log out).
2. **Attendance** – the employee scans the branch QR code (`scan_attendance.php`); `me.php` gives today's times.
3. **Inventory** – *Item Out* and *Item Return* become stock requests (`inventory_request_create.php`, with
   `request_type`, condition, area used and person in charge). The branch admin approves them in **Inventory →
   Requests**; an approved item out leaves the stock, an accepted return goes back in.
4. **Work request** – installation or repair (`work_request_create.php`). Status **Pending Approval**; it shows in
   **Work Requests** on the website (a number in the sidebar says how many wait).
   - An installation names the client (existing, or the details of a new one), the barangay, the NAP box and an
     available port. The port is *reserved* for the request, not occupied.
   - A repair names an existing client, the location and the problem.
5. **Admin review** – *Approve*, *Return for correction* (the employee fixes it and sends it again) or *Reject*.
6. **The work** – after approval the employee does the job and sends the **completion report**
   (`work_request_complete.php`): the NAP box and port really used, notes, and the materials used / returned /
   damaged. Status **Work Completed**.
7. **Admin confirms** – the website checks the report and *Confirm completion* does everything at once:
   - the port becomes **Occupied** by the client (a repair leaves the port as it is);
   - a new client is saved (the admin picks the internet plan) with a first bill;
   - returned materials go back in stock; used and damaged ones are written in the stock history;
   - the request becomes **Confirmed** and shows in the employee's **Transaction History**.
   The admin can also *Return the report* when something is wrong.

## Who does what

- A **branch admin** keeps the NAP boxes of their own branch (**NAP Boxes**: add a box with its numbered ports, mark a
  port faulty, connect or free a client) and reviews the requests of their branch.
- The **super admin** sees every branch (with a branch filter) but cannot change NAP boxes or decide requests.

## Employee app endpoints (`/backend/api/`)

| Endpoint | Purpose |
|---|---|
| `ping.php` | connection test, no sign in |
| `employee_login.php`, `employee_logout.php` | sign in (returns `token`) / out |
| `me.php` | the employee, today's attendance, request counts |
| `scan_attendance.php`, `attendance_history.php` | attendance |
| `inventory_items.php`, `inventory_request_create.php`, `inventory_requests_list.php` | stock of the employee's branch |
| `barangays.php`, `clients_search.php` | barangays and clients of the branch |
| `nap_boxes.php`, `nap_box.php` | NAP boxes with port counts / one box with its ports |
| `work_requests.php`, `work_request.php` | the employee's requests |
| `work_request_create.php`, `work_request_update.php`, `work_request_cancel.php`, `work_request_complete.php` | send, correct, cancel, report |
| `transactions.php` | item out, item return, installation and repair history |

Every answer is JSON with `success`; a failure also has a `message` the app can show.
