# Employee app and the admin website

The employee app (`/home/rafael/JFTECH/jftech`, Flutter) talks to the same Laravel server as the admin website.
Run the migration first: `php artisan migrate` on local MySQL, or
`database/supabase/migrations/2026_10_17_nap_boxes_and_work_requests.sql` in the Supabase SQL Editor.

## The screens of the app

After login the app opens on the **Home**: the greeting, a banner and three cards, **Attendance**, **Inventory** and
**NAP Box Monitoring**. Each card opens its own page (with a back arrow). The bell at the top opens **My work requests**
(a number says how many need the employee; the **New request** button is there). The bottom bar has only two places, **Home**
and **Profile**. The Profile shows who is signed in and holds **My work requests**, **Transaction history**,
**Server settings** (the address of the server) and **Log out**.

## The flow

1. **Login** – `employee_login.php` returns a sign-in `token`. The app sends it as `Authorization: Bearer <token>` on
   every other call, so a request can only act as the employee who signed in (24 hours, or until log out).
2. **Attendance** – the employee scans the branch QR code (`scan_attendance.php`); `me.php` gives today's times. The
   overtime QR code starts overtime on the first scan and ends it on the second (`Overtime In` / `Overtime Out`); its
   minutes go to the payroll (`docs/PAYROLL.md`). The overtime code is the branch's own (made by the branch admin) and only
   works for that branch's employees. Run `2026_10_18_attendance_overtime.sql` and `2026_10_20_overtime_qr_per_branch.sql`
   (or `php artisan migrate`) first.
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

- A **branch admin** keeps the NAP boxes of their own branch (**Inventory → NAP Boxes**: add a box with its numbered ports, mark a
  port faulty, connect or free a client) and reviews the requests of their branch.
- The **super admin** sees every branch (with a branch filter) but cannot change NAP boxes or decide requests.

## NAP boxes and the inventory work as one

- A NAP box is stock. Receive NAP boxes in **Inventory** as an item with the category **NAP Box** (the category is always in the
  list). **Adding a NAP box** on the NAP Boxes page takes one unit of that item out of the branch's stock and writes a
  *NAP Box Installed* line in the stock history with the NAP box on it. Without a unit in stock a box cannot be added.
  Changing, archiving or restoring a box does not touch the stock.
- Every stock transaction now says **which employee** handled it and, when it belongs to a job, **which client and NAP box** it
  was for (Inventory → Stock Updates: *Employee* and *Client / NAP* columns). The materials a confirmed installation used are
  linked to its client and to the NAP box that was really used.
- A NAP box lists the transactions that belong to it (**Inventory used on NAP-xx**), also in the app's NAP box screen.
- The Inventory page counts the NAP boxes installed and in stock next to the other items.
- A NAP box can have a **photo** (added or changed in the NAP box form by the branch admin, and removable) so the technician
  recognises it on the pole. The browser shrinks the picture (longest side 1000 px, JPEG) and the server keeps it in the
  `nap_box_photos` table, because the hosting has no permanent disk. It shows on the open box and in the app's NAP box screen
  (tap it to see it full size). A branch only sees the photos of its own boxes. Migration
  `2026_10_19_000002_create_nap_box_photos_table.php` and `database/supabase/migrations/2026_10_19_nap_box_photos.sql`.
- NAP boxes are installed by the branch admin from the stock, never handed out to an employee, so `inventory_items.php` does not
  list them and neither an item-out request nor a completion report can use one.
- Database: Laravel migration `2026_10_19_000001_link_stock_movements_to_nap_and_clients.php` and
  `database/supabase/migrations/2026_10_19_inventory_nap_links.sql` (new columns of `inventory_stock_movements`).

## Employee app endpoints (`/backend/api/`)

| Endpoint | Purpose |
|---|---|
| `ping.php` | connection test, no sign in |
| `employee_login.php`, `employee_logout.php` | sign in (returns `token`) / out |
| `me.php` | the employee, today's attendance, request counts |
| `scan_attendance.php`, `attendance_history.php` | attendance |
| `inventory_items.php`, `inventory_request_create.php`, `inventory_requests_list.php` | stock of the employee's branch |
| `barangays.php`, `clients_search.php` | barangays and clients of the branch |
| `nap_boxes.php`, `nap_box.php` | NAP boxes with port counts / one box with its ports and the inventory used on it |
| `nap_box_photo.php` | the photo of a NAP box (the picture itself; `has_photo` and `photo_version` tell if there is one) |
| `work_requests.php`, `work_request.php` | the employee's requests |
| `work_request_create.php`, `work_request_update.php`, `work_request_cancel.php`, `work_request_complete.php` | send, correct, cancel, report |
| `transactions.php` | item out, item return, installation and repair history |

Every answer is JSON with `success`; a failure also has a `message` the app can show.
