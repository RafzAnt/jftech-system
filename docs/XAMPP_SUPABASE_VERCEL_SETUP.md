# XAMPP local + Supabase/Vercel production

Use one database connection at a time: MySQL from XAMPP locally, then Supabase PostgreSQL after deployment. Do not point both environments at the same data while testing.

## 1. Run locally with XAMPP

1. Start Apache and MySQL in XAMPP.
2. Create a MySQL database named `jftech_db` in phpMyAdmin.
3. Keep these values in local `.env`:

   ```env
   APP_ENV=local
   APP_URL=http://127.0.0.1:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=jftech_db
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=file
   CACHE_STORE=file
   ```

4. Run `php artisan migrate`, then `php artisan serve`.

## 2. Create Supabase production database

1. Create a Supabase project.
2. Open **SQL Editor**, paste the complete contents of `database/supabase/schema.sql`, then run it.
   An existing project created before the super admin / branch admin update needs
   `database/supabase/migrations/2026_10_15_super_admin_and_branch_admins.sql`
   run once in the same SQL Editor (safe to rerun).
3. Open **Connect** and copy the **Transaction pooler** host, user, port, and password. Do not guess these values.

## 3. Deploy to Vercel

1. Push this repository to GitHub and import it in Vercel.
2. Leave Root Directory as the repository root; Vercel uses `Dockerfile.vercel`.
3. Add these Vercel Production environment variables:

   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://YOUR-PROJECT.vercel.app
   APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate_--show
   LOG_CHANNEL=stderr

   DB_CONNECTION=pgsql
   DB_HOST=YOUR_TRANSACTION_POOLER_HOST
   DB_PORT=6543
   DB_DATABASE=postgres
   DB_USERNAME=postgres.YOUR_PROJECT_REF
   DB_PASSWORD=YOUR_SUPABASE_DATABASE_PASSWORD
   DB_SSLMODE=require

   SESSION_DRIVER=database
   CACHE_STORE=database
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   QUEUE_CONNECTION=sync
   RUN_MIGRATIONS=false
   ```

4. Deploy. Check Vercel logs, log in, reload the page, and confirm the session remains active.

## 4. Move data when ready

Export XAMPP tables to CSV and import parent tables before child tables: `users`, `barangays`, `internet_plans`, `employees`, and `inventory`; then `clients`, `bills`, `payments`, attendance, payroll, and histories. Preserve IDs so foreign keys still match. Take a database backup before the final cutover.
