# Deploy JFTECH to Vercel

This repository deploys Laravel as a Vercel container through
`Dockerfile.vercel`. Vercel detects that file automatically; do not set a
Vercel build command or output directory for this app.

## Before importing the repository

1. Create a Supabase project. Local XAMPP/MySQL at `127.0.0.1` cannot be
   reached by Vercel.
2. In Supabase, open **Connect** and copy the **Transaction pooler** database
   values. This connection type is appropriate for short-lived Vercel requests.
3. In Supabase SQL Editor, run the PostgreSQL schema supplied for this project
   before the first deployment. Do not run the current MySQL-only Laravel
   migrations against Supabase.
   For an existing project receiving the client branch/barangay and internet
   plan pricing updates, run
   `database/supabase/migrations/2026_10_10_client_branch_columns.sql` in SQL
   Editor. It is safe to rerun, preserves existing plan prices, and adds the
   approved plans only when their names are not already present.
   For an existing project receiving the **super admin and branch admins**
   update, also run
   `database/supabase/migrations/2026_10_15_super_admin_and_branch_admins.sql`
   in SQL Editor. It adds `users.branch_name`, lets `users.role` be
   `super_admin`, and turns the existing admin accounts into super admins. It is
   safe to rerun (it never promotes a branch admin) and deletes nothing. Run it
   before logging in to the updated app: an `admin` account that has no branch
   is refused at login.
   For per-branch stock, also run
   `database/supabase/migrations/2026_10_16_inventory_per_branch.sql`. It adds
   `inventory.branch_name` (existing items stay with Balilihan) and makes the
   item name unique per branch. It is safe to rerun and deletes nothing.
   For the **employee app (NAP boxes, installation and repair requests)**, also
   run `database/supabase/migrations/2026_10_17_nap_boxes_and_work_requests.sql`.
   It adds the NAP box, port, work request and app sign-in tables and the item
   out / return details of a stock request. It is safe to rerun and deletes
   nothing. Run it before the new app signs in: the app now needs the sign-in
   token that `employee_login.php` returns, so install the matching app version.
   For the **real payroll computation** (automatic and manual payroll from the daily rate and attendance), also run
   `database/supabase/migrations/2026_10_18_real_payroll.sql`. It lets `payroll.total_days_worked` take half days, adds
   the late/undertime minutes and the computation mode, and replaces the old fixed per-rate SSS/PhilHealth/Pag-IBIG
   settings with four working rules. It is safe to rerun and keeps every existing payroll record. See `docs/PAYROLL.md`.
   For **overtime in the attendance** (overtime QR code, overtime in/out, minutes to the payroll), also run
   `database/supabase/migrations/2026_10_18_attendance_overtime.sql`. It adds the overtime columns to `attendance` and the
   overtime QR time settings, is safe to rerun and deletes nothing.
   The weekly scheduler does not run on Vercel: use **Generate Automatic Payroll** on the Payroll page each week.
4. Continue using the normal Laravel migrations only for local XAMPP/MySQL:

   ```bash
   php artisan migrate --force
   ```

   Do not run `migrate:fresh` on either database when it contains real data.

## Vercel project settings

1. In Vercel, select **Add New → Project**, import this GitHub repository, and
   keep the repository root as the Root Directory.
2. Select the **Container** framework preset if Vercel asks. It should detect
   `Dockerfile.vercel` automatically.
3. Leave Build Command and Output Directory empty. The Dockerfile installs PHP
   dependencies and starts Laravel on Vercel's assigned `PORT`.
4. In **Settings → Environment Variables**, use **Add More** and add the
   values below one at a time for **Production only**. Do **not** use
   **Import .env**: it imports local XAMPP values such as `127.0.0.1`, which
   cannot work from Vercel.

   ```text
   APP_NAME=JFTECH
   APP_ENV=production
   APP_KEY=base64:GENERATE_A_NEW_KEY
   APP_DEBUG=false
   APP_URL=https://YOUR-PROJECT.vercel.app
   LOG_CHANNEL=stderr
   LOG_LEVEL=warning

   DB_CONNECTION=pgsql
   DB_HOST=YOUR_SUPABASE_TRANSACTION_POOLER_HOST
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.YOUR_PROJECT_REF
   DB_PASSWORD=YOUR_SUPABASE_DATABASE_PASSWORD
   DB_SSLMODE=require
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   FILESYSTEM_DISK=local
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   RUN_MIGRATIONS=false
   ```

   Generate `APP_KEY` locally with `php artisan key:generate --show`; use the
   same value on every deployment. Never paste `.env` into GitHub or Vercel.
5. Click **Deploy**. Keep `RUN_MIGRATIONS=false` because the existing Laravel
   migration files contain MySQL-specific SQL. Apply future PostgreSQL schema
   changes through Supabase SQL Editor or Supabase CLI first, then deploy.

## Reduce page loading time

The database connection shown in Supabase as `ap-southeast-2` is in Sydney.
Vercel uses Washington (`iad1`) by default, which adds unnecessary round-trip
time to every database query. After the first deployment, change the function
region:

1. In Vercel, open the **jftech-system** project, then **Settings → Functions**.
2. Open **Function Regions** and select **Sydney (`syd1`)** only.
3. Save, then redeploy the latest production deployment.

Vercel routes static CSS and JavaScript through its CDN automatically. The
first request after an idle period can still take longer while the container
starts, but placing the function beside Supabase removes the large
Washington-to-Sydney database delay for active requests.

## After deployment

1. Open the Vercel URL and confirm the login page loads.
2. Log in, reload the page, and open a second page. A valid login after reload
   confirms that database-backed sessions work across container instances.
3. In Vercel **Logs**, check that there is no database connection error. If
   there is one, recheck the host, port, credentials, SSL requirement, and the
   database provider's network allow-list.

## Important production limits

Vercel containers are stateless. Uploaded files stored on `local` disk and
file-based queues do not persist between instances. This application currently
uses synchronous queues and has no production upload storage configured. If
uploads are added later, configure an object store such as S3 and set
`FILESYSTEM_DISK=s3`.
