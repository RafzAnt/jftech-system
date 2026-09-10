# Deploying JFTECH to InfinityFree

1. In the InfinityFree control panel, create a MySQL database and copy its exact
   host, database name, username, and password.
2. Open `backend/includes/database.production.php` and set those four values.
3. In InfinityFree phpMyAdmin, select the newly created database first, then
   import `jftech_database.sql`. Do not import it from the server root without
   selecting the database.
4. Upload the contents of this folder (not the outer `jftech-system` folder)
   into `/htdocs`. The deployed API path must be:
   `https://YOUR-DOMAIN/backend/api/employee_login.php`
5. Enable SSL for the domain before releasing the Android app. The app must use
   HTTPS, not HTTP.
6. Test the API URL in a browser. A GET request must return JSON, for example:
   `{"success":false,"message":"Invalid request method"}`. If it returns an
   HTML hosting page, fix the domain/SSL/account status before testing the app.

## Existing databases

If the database was created with an older schema, run this once in phpMyAdmin:

```sql
ALTER TABLE login_history MODIFY user_id INT NULL;
ALTER TABLE login_history ADD COLUMN error_message VARCHAR(255) NULL;
```

If the second statement says the column already exists, it is safe to ignore.
