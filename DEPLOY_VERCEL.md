# Deploy JFTECH to Vercel

This repository deploys Laravel as a Vercel container through
`Dockerfile.vercel`. Vercel detects that file automatically; do not set a
Vercel build command, output directory, or a `vercel.json` file for this app.

## Before importing the repository

1. Create a **remote MySQL-compatible database** that is reachable from the
   public internet. Local XAMPP/MySQL at `127.0.0.1` cannot be reached by
   Vercel.
2. Create a database and a database user with full access to that database.
   If the provider has an IP allow-list, allow Vercel or temporarily allow all
   IPs while completing the first deployment.
3. From a machine that can reach that database, run the migrations once:

   ```bash
   php artisan migrate --force
   ```

   This creates the application tables plus the production `cache` and
   `sessions` tables. Do not run `migrate:fresh` on production because it
   deletes existing data.

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

   DB_CONNECTION=mysql
   DB_HOST=YOUR_DATABASE_HOST
   DB_PORT=3306
   DB_DATABASE=YOUR_DATABASE_NAME
   DB_USERNAME=YOUR_DATABASE_USER
   DB_PASSWORD=YOUR_DATABASE_PASSWORD
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   FILESYSTEM_DISK=local
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   RUN_MIGRATIONS=true
   ```

   Generate `APP_KEY` locally with `php artisan key:generate --show`; use the
   same value on every deployment. Never paste `.env` into GitHub or Vercel.
5. Click **Deploy**. With `RUN_MIGRATIONS=true`, future pushes to the
   connected production branch automatically deploy and run only new Laravel
   migration files before the app starts. This requires a working remote MySQL
   connection.

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
