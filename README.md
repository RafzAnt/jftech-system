# JFTECH WiFi Billing & Management System

JFTECH is a role-protected web system for managing internet clients, plans and pricing, billing and payments, employees and attendance, inventory, payroll, and operational reports.

## Capstone-ready capabilities

- Client registration with a preserved plan-and-rate snapshot for accurate historical billing.
- Controlled internet-plan pricing: immediate or scheduled rate changes, archived plans, and price history.
- Billing, payment history, client inquiry, and printable records.
- Employee records, QR attendance, payroll, login history, and role-based administrator access.
- Inventory stock tracking with low-stock visibility and an auditable movement ledger.
- Employee inventory requests: approval locks the request and stock rows in one transaction, prevents duplicate releases and negative stock, and records the administrator responsible.
- Filterable reports with CSV/Excel-compatible exports and report history.
- CSRF protection, login/API rate limiting, role checks, secure session regeneration, and browser security headers.

## Local setup

Requirements: PHP 8.3+, Composer, Node.js, and MySQL/MariaDB.

1. Copy `.env.example` to `.env` and configure the `DB_*` values for a new MySQL database.
2. Run `composer install`, `php artisan key:generate`, and `php artisan migrate --force`.
3. Run `npm install` then `npm run build`.
4. Run `php artisan serve` and open the URL shown by Laravel.

For production, set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS, and a database account with only the permissions the application needs.

## Verification checklist

Before your defense, demonstrate these flows with sample data:

1. Register a client, then show the generated bill and client inquiry.
2. Schedule a plan-rate change and explain that existing client rates remain preserved.
3. Submit and approve an inventory request; show the reduced stock and movement record.
4. Create an attendance scan and show its employee/payroll impact.
5. Export a filtered report and explain administrator-only access and audit history.

Run automated checks with `php artisan test`.
