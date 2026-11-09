# Implemented So Far

## Project overview

This is a Laravel 11 / PHP 8.2+ web application. It contains the original job application prototype and the first Library Management System module: Member & Membership Management.

## Member and membership management

- Members and administrators sign in with email and password; passwords are hashed by Laravel. Signed-in users can sign out and update their own name, email, phone number, and address.
- Members and administrators can request a password reset by email and set a new password using a time-limited, single-use token.
- Signed-in users can change their password from their profile after verifying their current password.
- Members and administrators can optionally upload, replace, or remove a JPG, PNG, or WebP profile photo (up to 5 MB); the photo appears on their profile and in the authenticated navigation.
- Administrators can be provisioned from the command line with `php artisan lms:make-admin`. This prompts for credentials and can promote an existing account.
- Administrators can register members with a unique email and CNIC/ID, contact details, membership type (Student, Faculty, or Public), status, and expiry date. Each new member receives a generated library card number.
- The member directory is searchable and paginated. Administrators can see member contact details and filter by membership type and status; other signed-in members only see names and membership types.
- Administrators can edit member records, activate or suspend memberships, and renew memberships with a new expiry date. Expired status is derived from the expiry date.
- Role checks prevent members from using administrator-only member-management routes.

## Book and catalog management

- Public catalog browsing is available at `/` and `/catalog`, including guests.
- Search supports title, author, ISBN, and publisher, with live results and an autocomplete dropdown for matching active-catalog books; filters support active category, publication year, and availability. Results are paginated and book details include bibliographic data and copy counts.
- When adding a book, administrators can search Open Library for bibliographic suggestions and populate the form; details remain editable and must be reviewed before saving. Library inventory and copies remain managed locally.
- Administrators can create and edit books with unique ISBN, title, author(s), publisher, year, edition, category, and total copies. Each physical copy receives a unique generated barcode and an availability status; administrators can review copies and barcodes and mark copies available, unavailable, or lost.
- Administrators can optionally upload JPG, PNG, or WebP book covers (up to 5 MB); covers display in catalog listings and details and can be replaced or removed.
- Copy totals are adjusted without overwriting copies currently unavailable, and a total cannot be reduced below the unavailable-copy count. Removed available copies are retired so loan history is retained. A book with unavailable copies or loan history cannot be removed.
- Administrators can create and edit categories and deactivate them. Inactive categories remain associated with their books, but those books are hidden from public catalog pages.
- Category and book management routes require the administrator role.

## Circulation

- Administrators issue an available copy using a member's library card number and the copy barcode. Issuing requires an active, unexpired membership and enforces the configurable per-member active-loan limit (default five).
- Loans default to a 14-day due period. Administrators can search and filter loan history, and record a return; returned copies become available again and update the catalog count.
- A scheduled daily job sends a due-soon alert two days before a loan is due by default and one overdue reminder after its due date. Reminder delivery is recorded to prevent duplicate alerts when the job is rerun.
- Members can view their own active and returned loans and renew eligible loans once by default, for the configured loan period. Overdue, returned, already-renewed, or membership-ineligible loans cannot be renewed. Members cannot renew another member's loan.
- A returned loan that is late incurs a default PKR 50 fine per overdue calendar day, with no cap. Members with unpaid fines cannot renew loans. Administrators can record a simulated full payment; fine records show payment status and who recorded it.
- Administrators can adjust or waive an unpaid fine with a required reason. The previous and new amount, action, reason, administrator, and timestamp are retained in a fine-adjustment ledger, and the member is notified.
- Members with active memberships can reserve an unavailable book, with up to five active reservations each by default and one active reservation per title. Reservations are served first-in, first-out; a returned copy is held for the next eligible member for two days by default. The member can see the reserved barcode and pickup deadline, and can cancel a waiting or ready reservation. Expired holds are processed on demand during reservation, catalog, or circulation activity and by an hourly scheduled command.
- Administrators can adjust loan duration, active-loan and reservation limits, renewals, per-day overdue fines, pickup hold length, and due-soon reminder lead time from **Policies**. New policies affect future transactions and reminders; they do not rewrite existing due dates or holds.

## In-app notifications

- Members receive in-app and email notifications when a reservation copy is ready, a pickup hold expires, an overdue fine is assessed, or a simulated fine payment is recorded. Email delivery uses Laravel's configured mailer; the default `log` mailer writes messages to the application log until an SMTP or other delivery transport is configured.
- Members can opt in to SMS alerts from their profile. SMS uses a configured Twilio account and is disabled by default; opted-in phone numbers must use international E.164 format.
- Members can enable browser push notifications from their profile. Subscriptions are per browser, obsolete endpoints are removed, and VAPID keys are generated with `php artisan webpush:vapid-keys`; push is disabled until configured.
- The authenticated navigation displays an unread count. The notification inbox supports marking an item read while opening its related page, or marking all notifications read.
- In-app notifications are stored in the Laravel database notifications table.

## Audit logging

- Successful state-changing requests made by authenticated users are recorded with the actor, route action, HTTP method, timestamp, and route-bound record when available.
- Administrators can review a paginated audit log. Submitted form values and other sensitive request data are not stored.

## Dashboards

- Administrators have a statistics dashboard with current book, member, copy, loan, reservation, and outstanding-fine totals, a 14-day loan activity chart, and recent loan status.
- Members have a personal dashboard with their active and overdue loans, reservation pickup status, unpaid fine balance, membership details, and links to their account activity. Members are sent to their dashboard after sign-in.

## Operational reports

- Administrators can filter reports by loan issue-date range, with a default view of the last 30 days.
- Summary metrics show loans issued and returned in the period, current active and overdue loans, available copies, waiting and ready reservations, outstanding unpaid fines, and fines paid in the selected period.
- Reports include loans issued per day, most-borrowed books, and most-active members for the selected date range.
- Administrators can generate a fine report filtered by assessment date, status, and member, showing collected, outstanding, and waived totals with daily trends; filtered fine records can be exported as a formula-safe CSV.
- Administrators can export loan activity for the selected range as a streamed CSV file. Text values that could be interpreted as spreadsheet formulas are escaped.
- Reports and CSV exports are restricted to administrators.
- Copy inventory and loan history are stored separately from catalog metadata. Existing aggregate copy counts are migrated into individually barcoded copy records while preserving their available/unavailable counts.

## Job application flow

- Visiting `/` redirects to the application form at `/apply`.
- The form collects an applicant's name and contact number, a job ID and title, company, location, gender, expected salary, and terminal degree.
- Locations are limited to Lahore, Islamabad, Karachi, Peshawar, and Sargodha. Gender is limited to Male or Female.
- Server-side validation requires every field, applies length limits, checks the allowed location and gender values, and requires a unique job ID.
- Valid submissions are saved to the `Job_Application` table and redirected to `/success`, which displays the submitted job ID.
- The form displays validation errors, retains submitted values after validation failures, and includes reset and submit controls.

## JSON API

`POST /api/applications` accepts the same application fields and validation rules. Successful submissions are saved and return HTTP `201` with a success message and the saved record.

## Data model

The `Job_Application` table uses `Job_ID` (a string) as its primary key and stores the job and applicant fields above, along with Laravel timestamps. The `JobApplication` model maps to this table and specifies the non-incrementing string primary key.

## Interface and tooling

The form and success page use Blade templates. The shared layout uses Bootstrap 5 and Bootstrap Icons from CDNs, with custom inline styles. Vite is configured for frontend assets; Composer defines Laravel application scripts and the PHP test runner.

## Deployment notes

Public cover images and profile photos use Laravel's public storage disk; run `php artisan storage:link` to expose them from the web server. Configure a real mail transport in `.env` for password reset links to reach users; the default log mailer is suitable only for local testing. Keep Laravel's scheduler running to process reservation holds and loan reminders.
