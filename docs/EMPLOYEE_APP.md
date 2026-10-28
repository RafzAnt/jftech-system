# Employee app and the admin website

The employee app (`/home/rafael/JFTECH/jftech`, Flutter) talks to the same Laravel server as the admin website.
Run the migrations first: `php artisan migrate` on local MySQL, or the files of `database/supabase/migrations/` in the Supabase SQL
Editor (`2026_10_17_nap_boxes_and_work_requests.sql`, and for installing clients `2026_10_28_client_installations_and_coordinates.sql`).

## The screens of the app

After login the app opens on the **Home**: the greeting, a banner and three cards, **Attendance**, **Inventory** and
**NAP Box Monitoring**. Each card opens its own page (with a back arrow). **Inventory** has three tabs: *Item Out*, *Item Return*
and *Installation* (the report after installing a client, see below). The bottom bar has only two places, **Home**
and **Profile**. The Profile shows who is signed in and holds **Transaction history**,
**Server settings** (the address of the server) and **Log out**.

The installation and repair **work requests** (the bell on the Home, **My work requests** in the Profile and **Work Requests**
in the website's sidebar) were removed on 2026-10-22. Their tables (`work_requests`, `work_request_materials`) and the old
rows are still in the database; nothing reads or writes them any more.

## The flow

1. **Login** – `employee_login.php` returns a sign-in `token`. The app sends it as `Authorization: Bearer <token>` on
   every other call, so a request can only act as the employee who signed in (24 hours, or until log out).
2. **Attendance** – the employee scans the branch QR code (`scan_attendance.php`); `me.php` gives today's times. There is no
   overtime QR code: the afternoon code works until the last scan of the day (22:00 by default), and the scan after the end of
   the afternoon is the time out of the day, so the time after 17:00 is the **overtime** (the answer of the scan carries
   `overtime_minutes`, `overtime_counted` and `overtime_minimum_minutes`). A day of overtime is paid only from 1 hour; its
   minutes go to the payroll (`docs/PAYROLL.md`).
   A session's first scan is its **Time In** and the next one its **Time Out** (the answer says which). The hours of the day are worked out when
   the time out is saved, and leaving a session more than 5 minutes before it ends is **undertime** (shown in the app's history and in the
   website's Attendance table; the payroll still takes undertime minutes as typed, see `docs/PAYROLL.md`). A second scan within a minute of the
   time in is refused (it is almost always a double scan).
3. **Inventory** – *Item Out* and *Item Return* become stock requests (`inventory_request_create.php`, with
   `request_type`, condition, area used and person in charge). The branch admin approves them in **Inventory →
   Requests**; an approved item out leaves the stock, an accepted return goes back in.

The Inventory page also lists **My requests** (the last five, with who approved or denied each and when; *See all* opens the
Transaction history), and **Attendance → Attendance history** opens the last 30 days with the totals (days worked, hours, late,
undertime, overtime, absent) at the top. Both come from the server, so what the employee did is there on any phone they sign in on.

## Sample data for every branch (2026-10-28)

`php artisan db:seed --class=BranchFieldDataSeeder` fills a development or demo database (it refuses to run in production) with
four parts, each of which skips what is already there, so running it again changes nothing:

1. `BranchStaffAttendanceSeeder` - **a technician in every barangay** of every branch with an app account (employee codes
   `EMP-DEMO-<BRANCH>-<n>`, the address names the barangay, so searching a barangay on the Employees page finds who works there;
   username = the full name without spaces, e.g. `junjunzamoras`; all share the password `Jftech@2026`, or `DEMO_STAFF_PASSWORD`
   from `.env`), and for every active employee the attendance of the weeks **up to one week before today** (never today's date;
   `BranchStaffAttendanceSeeder::window()`), Monday to Saturday: complete days, late arrivals, early leaving (undertime), half
   days, absences and overtime on the days each branch approves it. Every employee has at least one late day, one undertime day and one
   overtime day. Times fall inside the QR windows and every record points at a QR code that exists (morning and afternoon codes are
   shared, overtime codes belong to a branch). A day the admin entered by hand, or that has a real scan, is never touched; what an
   earlier run made after the window is removed.
2. `BranchStockNapSeeder` - each branch's stock room (ONU, router, splitter, drop cable, connectors, ... with opening stock and a
   restock in the stock history; a few items below the reorder level), a NAP box in every barangay (`NAP-<BRANCH>-<n>`, 8 ports, 16
   in a poblacion, taken out of the "NAP Box" stock) hanging at the barangay's place from `database/seeders/data/barangay_coordinates.json`
   (OpenStreetMap; **near the barangay, not the exact pole**, and the town centre where the map did not know the barangay), and the
   clients of each barangay plugged into its ports. Replace a box's coordinates with the real GPS reading when it is installed.
3. `EmployeeInventoryRequestsSeeder` - about 45 in a hundred technicians filed three to six item-out and item-return requests, decided
   by the branch admin (the super admin where a branch has none yet): most approved (the stock goes down, or back up for a return; when
   the shelf is short the admin orders more first), some waiting, some denied.
4. `BranchPayrollSeeder` - the weekly payroll of that attendance for every Monday-to-Sunday week that is complete inside it, made by
   the same code as `payroll:generate-weekly` (days worked, late and undertime by the minute, absences, overtime, and the SSS, PhilHealth
   and Pag-IBIG shares), so every payslip already carries its deductions. The older weeks are *paid*, the latest one *processed*. The
   week the attendance only reaches part of is left for the admin to generate on the Payroll page.

The Payroll page shows the newest 300 payslips and says how many there are in all; the search box narrows the list on the server.

To remove the staff: delete the employees whose code starts with `EMP-DEMO-` (their attendance, requests and payroll go with them),
then their user accounts.

## Installing a client (2026-10-28)

The order the user asked for: first add every NAP box to the inventory (with its Google coordinates), then add the existing
clients and connect them to their NAP box, then use the app.

1. **Items out** – the technician requests what they take (*Inventory → Item Out*, used for *Installation*). The branch admin
   approves it in **Inventory → Requests**; the stock is released, but the job stays *pending*: the request shows
   *Installation pending: no report yet*, and the app's Inventory card says *N to report*.
2. **Installation report** – after installing, the technician opens *Inventory → Installation → Report an installation* and fills in:
   the items taken (the approved installation requests, ticked), the **barangay**, the address, the **Google coordinates**, the
   **NAP box and port** (the boxes of the barangay; a free port can be picked, the others say who is plugged in), and the client:
   name, contact number, plan, **MOP** (Cash or GCash), IP address and Facebook account. Items left over are added with *Return item*
   (item, quantity and condition). The report is saved as *Waiting for admin*; the port is **reserved** for it (nobody else is offered
   it) but the client does not exist yet and the stock does not change.
3. **Admin confirms** – **Inventory → Installations** (a number in the sidebar says how many wait). The branch admin sees the whole
   report, can **edit any detail** (including the port) and the quantity of each returned item that goes back in stock, and presses
   **Confirm & Add Client**: the client is added (first bill included), the port is connected, the returned items go back in stock
   (*Stock Returned*), what was used is written against the client and NAP box (*Used on Job*, no change of quantity), and the
   report becomes *Confirmed*. **Reject** (with a reason) adds nothing, frees the port and the items, and the technician can report again.
   The super admin only looks (branch filter).
4. **Register Client on the website** (Clients → Register New Client) uses the same rules: after the barangay is chosen the NAP boxes
   of that barangay appear with their ports (free, in use by whom, faulty, reserved); a free port can be picked (optional). It also asks
   for the mode of payment, IP address, Facebook account and the Google coordinates (required). Without a port the client is saved
   as before and can be connected later on the NAP Boxes page.

**Google coordinates** can be typed as `9.6541, 124.1234` or pasted from Google Maps (the numbers of the press-and-hold menu, or the
address of the map page). They must lie inside the Philippines, so a swapped pair is refused. They are required for a NAP box and for a
new client; clients registered earlier have none until they are edited (**Clients → Update**).

Database: `2026_10_28_000001_create_client_installations_and_coordinates.php` (and the Supabase SQL of the same name): coordinates
on `nap_boxes` and `clients`, `payment_mode` / `ip_address` / `facebook_account` on `clients`, the tables `client_installations` and
`client_installation_returns`, and `inventory_requests.installation_id`. Code: `App\Support\Installations` (the flow),
`App\Support\ClientRegistration` (checks and saves a client, used by both the website form and the confirmation),
`App\Support\Coordinates`, `InstallationApiController`, `NapBoxLookupController`, `public/js/nap_picker.js`.

## Who does what

- A **branch admin** keeps the NAP boxes of their own branch (**Inventory → NAP Boxes**: add a box with its numbered ports and its
  Google coordinates, mark a port faulty, connect or free a client) and confirms the installation reports of their technicians.
- The **super admin** sees every branch (with a branch filter) but cannot change NAP boxes.

## NAP boxes and the inventory work as one

- A NAP box is stock. Receive NAP boxes in **Inventory** as an item with the category **NAP Box** (the category is always in the
  list). **Adding a NAP box** on the NAP Boxes page takes one unit of that item out of the branch's stock and writes a
  *NAP Box Installed* line in the stock history with the NAP box on it. Without a unit in stock a box cannot be added.
  Changing, archiving or restoring a box does not touch the stock.
- Every stock transaction now says **which employee** handled it and, when it belongs to a job, **which client and NAP box** it
  was for (Inventory → Stock Updates: *Employee* and *Client / NAP* columns).
- A NAP box lists the transactions that belong to it (**Inventory used on NAP-xx**), also in the app's NAP box screen.
- The Inventory page counts the NAP boxes installed and in stock next to the other items.
- A NAP box can have a **photo** (added or changed in the NAP box form by the branch admin, and removable) so the technician
  recognises it on the pole. The browser shrinks the picture (longest side 1000 px, JPEG) and the server keeps it in the
  `nap_box_photos` table, because the hosting has no permanent disk. It shows on the open box and in the app's NAP box screen
  (tap it to see it full size). A branch only sees the photos of its own boxes. Migration
  `2026_10_19_000002_create_nap_box_photos_table.php` and `database/supabase/migrations/2026_10_19_nap_box_photos.sql`.
- NAP boxes are installed by the branch admin from the stock, never handed out to an employee, so `inventory_items.php` does not
  list them and an item-out request cannot use one.
- Database: Laravel migration `2026_10_19_000001_link_stock_movements_to_nap_and_clients.php` and
  `database/supabase/migrations/2026_10_19_inventory_nap_links.sql` (new columns of `inventory_stock_movements`).

## Employee app endpoints (`/backend/api/`)

| Endpoint | Purpose |
|---|---|
| `ping.php` | connection test, no sign in |
| `employee_login.php`, `employee_logout.php` | sign in (returns `token`) / out |
| `me.php` | the employee, today's attendance, pending stock requests, approved installations that wait for a report |
| `scan_attendance.php`, `attendance_history.php` | attendance; the history is the last 30 days with `late_minutes`, `undertime_minutes`, `total_hours` per day and a `summary` of those days |
| `inventory_items.php`, `inventory_request_create.php`, `inventory_requests_list.php` | stock of the employee's branch |
| `nap_boxes.php`, `nap_box.php` | NAP boxes with port counts and coordinates / one box with its ports (`reserved` when an unconfirmed report took it) and the inventory used on it |
| `nap_box_photo.php` | the photo of a NAP box (the picture itself; `has_photo` and `photo_version` tell if there is one) |
| `installation_options.php` | what the installation report offers: barangays of the branch, plans, modes of payment, and the approved items that wait for a report |
| `installation_create.php` | send the installation report (client, coordinates, `nap_port_id`, `request_ids`, `returns`) |
| `installations.php` | the employee's own reports with their status |
| `transactions.php` | item out, item return and installation history (`type=all\|inventory\|installation`); an item request also says `decided_at` and `decided_by` |

Every answer is JSON with `success`; a failure also has a `message` the app can show.
