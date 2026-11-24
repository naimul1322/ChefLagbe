# ChefLagbe - Beginner-friendly Laravel 12 project

A web-only Bangladesh-focused marketplace based on the supplied ChefLagbe HTML/CSS, logo, photographs and reference screenshots. It uses the orange, cream and navy identity, shared Blade layout, plain JavaScript and responsive CSS. This is a development-stage source package, not an already deployed or production-certified application.

## What is included
- Public pages: Home, About, Services, Chefs, Contact, Login, Signup; additional chef profile, Booking, Payment, Admin Login and Admin Dashboard; separate customer/chef dashboard features and password-recovery pages.
- Laravel MVC, MySQL migrations, models, REST endpoints, Sanctum API tokens and same-origin session authentication, role checks, account status checks, pricing/availability service, payment simulation, reviews, contacts, uploads and administrative reporting.
- Original local images optimized as WebP and the original logo; sample chef photos are explicitly illustrative. Team member cards use initials instead of real photos. Demo seed data is fictional. The offline preview uses in-memory sample data and resets on reload.
- Basic PHPUnit feature test source, API collection, schema diagram and Windows instructions.
- Chart.js and SweetAlert2 are progressive CDN enhancements. If those CDN scripts are unavailable, native charts and notifications remain usable. Icons are embedded SVGs for offline reliability; Bootstrap is not required.

## Simplified version
The original design and core features are retained. Common CSS is in `base.css`, PHP and JavaScript use readable formatting, and recent booking activity comes from the bookings table rather than a separate log table.

## Requirements (Windows)
PHP 8.4, Composer 2.x, MySQL Server 8.0.44, MySQL Workbench 8.0.44 (or compatible MySQL 8 for development), Git, VS Code. Enable PHP extensions required by Laravel/Composer: `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl`, `dom`, `xml`, `ctype`, `tokenizer`, `zip`, `intl` as appropriate. Run `php -v`, `php -m`, `composer --version` and verify the MySQL service is running. No Node.js, npm, React or Python runtime is required.

## Installation
1. Extract `ChefLagbe_Simple.zip`. Open the `ChefLagbe_Simple` folder in VS Code and launch its integrated terminal. All following commands run in that folder (the one containing `artisan`).
2. Run `composer install` to download Laravel 12, Sanctum and the development dependencies. Composer needs network access. This package intentionally excludes `vendor/`; no lockfile was generated in the offline build environment. Save the generated `composer.lock` to Git once you have verified the resolved dependencies.
3. In PowerShell, run `Copy-Item .env.example .env` (in CMD: `copy .env.example .env`). This creates your private local configuration. Run `php artisan key:generate` to generate Laravel's encryption key.
4. Open MySQL Workbench and connect to your local MySQL instance. Execute these two SQL statements to create empty databases, not tables:

```sql
CREATE DATABASE cheflagbe_simple CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cheflagbe_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. In `.env`, set `DB_DATABASE=cheflagbe_simple`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, and your local MySQL username/password. Do not commit `.env`. Use a dedicated DB account with appropriate rights in a shared/deployed environment.
6. Run `php artisan config:clear` to clear cached config, then `php artisan migrate --seed` to create the tables and fictional sample accounts, chefs, bookings and reviews. `php artisan migrate:status` lists migration status. `php artisan storage:link` exposes only the public uploads disk; verification files remain in `storage/app/private`. On Windows, run VS Code with suitable symlink privileges if required.
7. Run `php artisan serve --host=127.0.0.1 --port=8000` and open `http://127.0.0.1:8000`. For consistent first-party Sanctum cookies, use either `localhost:8000` or `127.0.0.1:8000` consistently and configure `APP_URL` to match the hostname used. The sample `.env` includes both stateful domains.
8. To check reminders manually, run `php artisan cheflagbe:reminders`. To run the scheduler locally, use `php artisan schedule:work` in a separate terminal. The log mailer is enabled for development (no email is sent); configure real SMTP via environment variables and retest before deploying. Email verification is provided, but this development version does not require a verified email for booking.

## Development-only demo accounts
All passwords: `DemoPass123!`. Never use these accounts in production.

| Role | Email | Page |
|---|---|---|
| Customer | customer1@cheflagbe.test | /login |
| Chef | chef1@cheflagbe.test | /login |
| Admin | admin@cheflagbe.test | /admin/login |

Seeders create 1 admin, 5 customers, 10 verified fictional chefs, 6 services, 20 bookings and 15 reviews. These are not real service providers, reviews, platform usage or revenue. The developer may need to reseed a **disposable** database to see pristine samples; `migrate:fresh --seed` deletes existing database tables and must never be used on a valuable database.

## Offline preview
Open the separate `ChefLagbe_Simple_Preview.html` in a desktop browser. Its data is fictional and held in memory; refresh resets all changes. The role switch lets you inspect Guest, Customer, Chef and Admin screens. Sample file uploads, real authentication, persistence and email require the Laravel server. This HTML is not a substitute for installing the backend and is not a live hosted URL.

## Database tables
The project has seven business tables: `users`, `chefs`, `services`, `bookings`, `payments`, `reviews`, `contacts`. Laravel also needs `password_reset_tokens`, `personal_access_tokens` and its automatically created `migrations` tracking table. Roles are stored in `users.user_type`; weekly availability is stored in `chefs.availability`. No separate role, permission, availability, activity log, session, cache or queue tables are needed. Sessions and cache use files; queue uses sync.

For a clean install, create a new empty database (for example `cheflagbe_simple`) and set `DB_DATABASE` accordingly. Do not run `migrate:fresh` on an existing V2 database with valuable data: the original migration has been simplified for fresh installations, not for in-place upgrades.

## Booking and security
- Backend prices: hourly price × whole-hour duration, plus 10% platform fee and 15% illustrative tax. Server calculation is authoritative; no client total is trusted. Service/package guide prices are illustrative, not a separate price formula.
- Pending requests require successful simulated payment and chef/admin confirmation. Confirmation locks the chef row and checks weekly hours and overlapping confirmed bookings; overlapping pending requests are cancelled and successful demo payments are marked refunded. Payment also rechecks current slot availability. Overnight bookings are not supported. Customer cancellation requires at least 48 hours. Cancelled successful demo payments are marked refunded. Completion is allowed after the scheduled end. Reviews are restricted to owned completed bookings, one per booking.
- Payments are simulation only, never accept card numbers, CVV, PIN or OTP; no real bKash/Nagad/Rocket/card integration. 
- First-party web users use cookie sessions with CSRF; Postman login returns a scoped-to-user Sanctum bearer token when `device_name` is provided (24-hour token expiry). Passwords hashed, protected role middleware, login/contact rate limiting, validation, escaped dynamic HTML, uploads limited to 5 MB and private verification storage. Never use real verification documents or personal information in the demo.
- Account and chef file uploads: allow JPEG/PNG/WebP images, PDF/JPEG/PNG verification documents. Documents are downloaded only through admin API.

## Test instructions
Create the separate `cheflagbe_test` DB as above; provide DB connection credentials through `.env` or `.env.testing`. Run `php artisan config:clear`, `vendor\bin\phpunit` (Windows). PHPUnit uses `phpunit.xml` with a separate DB name enforced in `Tests/TestCase.php`. **RefreshDatabase may delete test database data.** The included test code covers sample listing, auth, logout, role checks, server totals, payment retries/idempotence, cancellation, booking ownership, review uniqueness, overlaps and contact submission. Backend tests were NOT run in the delivery environment because Composer and MySQL were unavailable. They must be run and failures corrected locally before deployment. 

## Files and architecture
`routes/web.php`: Blade page routes and authenticated dashboards. `routes/api.php`: endpoints and middleware. `app/Http/Controllers/`: request validation, role-specific operations, JSON. `app/Services/BookingService.php`: backend price, availability, transaction and state rules. `app/Models/`: relationships and public representations. `database/migrations/`: schema, indexes and constraints. `database/seeders/`: fictional data. `resources/views/`: shared page markup. `public/css/`: original CSS adapted to local images and shared responsive styles. `public/js/app.js`: Fetch API, validation, interactions and dashboards. `config/`: database, mail, Sanctum, session and related configuration. `docs/`: API documentation, ER diagram and Postman collection.

## Deployment checklist
Provision PHP 8.4/MySQL hosting; install resolved Composer dependencies; run migrations (never production demo seed); use a restricted DB account and create a secure administrator account through a controlled onboarding procedure; use HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `SESSION_SECURE_COOKIE=true`, and correct Sanctum stateful domains. Set the document root to `public/`, protect storage/config, enforce backups, validate MIME and scan verification uploads, review authorization/concurrency and load tests, configure mail/scheduler, monitor logs, finalize real legal policies and verified business contact details, and obtain appropriate permissions for any third-party photos. Use reviewed payment gateway integrations only if later implementing actual transactions. Do not expose this demo directly to the internet as production software.

## Verification
See `docs/BUILD_STATUS.md` for the checks actually run for this version. Install Composer dependencies and test with MySQL before deploying. The separate offline HTML is a simulation, not a real hosted website.

## Where to start reading the code
1. `routes/web.php` and `resources/views/pages/` show which page is loaded.
2. `routes/api.php` maps Fetch requests to controllers.
3. `AuthController.php` handles accounts; `MarketplaceController.php` handles chef search and contact messages.
4. `BookingController.php` calls `BookingService.php` to validate available time, calculate price, and update booking status.
5. `database/migrations/` creates the tables; `app/Models/` defines relationships.
6. `public/css/base.css` contains common styles; page CSS contains only its own rules. `public/js/app.js` is the shared JavaScript with section comments.

The offline preview is delivered separately; no demo-only API file is needed in the Laravel runtime.
