# Library Management System

A Laravel 11 application for library members and staff. It includes a searchable public catalog, membership management, barcode-based circulation, reservations, fines, notifications, and administrative reports. The original job-application prototype is also retained at `/apply`.

See [IMPLEMENTED.md](IMPLEMENTED.md) for the full feature inventory.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel and the Web Push dependency (including cURL, OpenSSL, and mbstring).
- Composer
- SQLite or MySQL
- A browser; HTTPS is required for browser push outside localhost

## Local setup

From the project directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure `.env` for your database. For SQLite, create the database file if needed:

```powershell
New-Item -ItemType File -Force database\database.sqlite
```

Set `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite` in `.env`, then run:

```powershell
php artisan migrate
php artisan storage:link
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). With XAMPP, you can instead point Apache's document root to this project's `public` directory and configure `.env` to use your MySQL database.

Create or promote an administrator interactively:

```powershell
php artisan lms:make-admin
```

## Key pages

| Page | URL | Access |
|---|---|---|
| Catalog | `/` or `/catalog` | Public |
| Sign in | `/login` | Public |
| My profile, loans, reservations, notifications | `/profile`, `/my-loans`, `/my-reservations`, `/notifications` | Signed in |
| Members | `/members` | Signed in; administrators see management actions |
| Catalog management, categories, loans, reservations, reports, audit log, policies | `/admin/catalog`, `/admin/categories`, `/admin/loans`, `/admin/reservations`, `/admin/reports`, `/admin/audit-logs`, `/admin/library-policies` | Administrators |
| Job application form | `/apply` | Public |

## Test the library workflows

1. Sign in as an administrator and add a category at `/admin/categories`, then add a book at `/admin/catalog`. The book's **Copies** page shows generated barcodes.
2. Register a member from `/members/create`; sign out and sign in as that member to review the profile and membership.
3. As the administrator, issue a copy at `/admin/loans/create` using the member's library card number and a copy barcode. Check the member's `/my-loans` page, then return the copy from the admin loan list.
4. To test reservations, issue all copies of a title, then sign in as a member and reserve it from the catalog. Returning a copy allocates it to the next member, who can collect it before the two-day hold expires.
5. To test a late fine, return a loan after its due date; the member can see the fine and an administrator can record its simulated payment.
   Administrators can also adjust or waive an unpaid fine from the loan list; a reason is required and recorded.
6. Review operational reports and loan CSV export at `/admin/reports`, and fine reports/status filters/CSV export at `/admin/reports/fines`; review successful administrative changes at `/admin/audit-logs`.
7. Adjust circulation defaults at `/admin/library-policies`; changes apply to new transactions, not existing loans or reservation holds.

Run the automated tests with:

```powershell
php artisan test
```

Focused tests are in `tests/Feature`, including `CatalogManagementTest.php`, `LoanManagementTest.php`, `ReservationManagementTest.php`, `NotificationTest.php`, `PushNotificationTest.php`, `ProfilePhotoTest.php`, `ReportsTest.php`, and `AuditLogTest.php`.

## Notifications and background scheduling

- Email uses Laravel's mail configuration. The default `MAIL_MAILER=log` writes email content to the application log; configure SMTP or another transport for delivery.
- Password recovery is available from the sign-in page and also requires a working mail transport in production.
- Signed-in members and administrators can change passwords from **My Profile** after entering their current password.
- SMS is optional and disabled by default. Configure Twilio credentials, set `LIBRARY_SMS_ENABLED=true`, and have opted-in members save an E.164 phone number in their profile.
- Browser push is optional and disabled by default. Generate VAPID keys with `php artisan webpush:vapid-keys`; place them in `.env`, set `WEBPUSH_ENABLED=true`, and serve the app on HTTPS (localhost is allowed for development). Never expose `VAPID_PRIVATE_KEY`.
- Reservation holds are expired hourly, and due-soon/overdue loan reminders are sent daily at 9:00 AM by Laravel's scheduler. Keep this process running during local development:

```powershell
php artisan schedule:work
```

Uploaded book covers and profile photos are served from Laravel's public storage disk; `php artisan storage:link` creates the required public link.

## Project conventions

- Routes: `routes/web.php` and `routes/api.php`
- Controllers: `app/Http/Controllers`
- Models and migrations: `app/Models` and `database/migrations`
- Blade templates: `resources/views`
- Feature tests: `tests/Feature`

Do not commit `.env` or service credentials.
