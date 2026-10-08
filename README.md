# JFTECH WiFi Billing & Management System

JFTECH is a Laravel application for managing clients, internet plans, billing, payments, employees, attendance, payroll, inventory, reports, and system settings.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Open the URL shown by Laravel, normally `http://127.0.0.1:8000`.
