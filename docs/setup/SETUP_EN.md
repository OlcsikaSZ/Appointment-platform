# Appointment Platform – installation guide (Windows + XAMPP)

[Magyar](SETUP_HU.md) | **English**

This guide covers a completely new installation, from cloning the repository to running the application locally. The examples use Windows PowerShell and XAMPP.

## 1. What is created during installation?

The source repository intentionally excludes machine-specific and environment-specific data:

- the real `backend/.env` file and credentials;
- the Composer-generated `backend/vendor` directory;
- databases, database dumps, and real customer data;
- logs, caches, sessions, and compiled views;
- uploaded business logos and service images.

These are not missing source files. Dependencies and local configuration are created during installation. Existing data and uploaded images must be restored from a separate backup when required.

## 2. System requirements

- Git;
- XAMPP with PHP 8.2 or newer;
- Composer 2 configured to use the XAMPP PHP installation;
- Apache `mod_rewrite` with `.htaccess` enabled;
- MySQL or a compatible MariaDB version;
- Node.js 18+ only for frontend tests.

Required PHP extensions:

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=zip
```

Check the installed tools in PowerShell:

```powershell
git --version
php -v
composer --version
php -m | Select-String 'pdo_mysql|mbstring|openssl|fileinfo|gd|zip'
```

If the `php` command is not available, use the full path to the XAMPP PHP executable, for example:

```powershell
& 'C:\xampp\php\php.exe' -v
```

## 3. Clone the project and install the backend

The following example clones the project into `C:\xampp\htdocs\appointment-platform`:

```powershell
Set-Location C:\xampp\htdocs
git clone https://github.com/OlcsikaSZ/Appointment-platform.git appointment-platform
Set-Location .\appointment-platform\backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

If XAMPP is installed elsewhere, replace `C:\xampp` in the examples with the correct path. `composer install` creates the `vendor` directory from the committed `composer.lock` file.

## 4. Configure the local `.env` file

Open:

```text
C:\xampp\htdocs\appointment-platform\backend\.env
```

Basic local XAMPP configuration:

```dotenv
APP_NAME="Appointment API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/appointment-platform
FRONTEND_URL=http://localhost/appointment-platform
PUBLIC_APP_URL=http://localhost/appointment-platform
APP_TIMEZONE=Europe/Budapest

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=appointment_platform
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
CORS_ALLOWED_ORIGINS=http://localhost,http://127.0.0.1

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@example.test"
MAIL_FROM_NAME="${APP_NAME}"

BUSINESS_SEED_EMAIL=notifications@example.com
ADMIN_SEED_EMAIL=admin@example.test
ADMIN_SEED_PASSWORD=ENTER_A_STRONG_LOCAL_PASSWORD
```

A default XAMPP installation usually uses the `root` database user with an empty password. If your MySQL configuration differs, update the `DB_*` values accordingly. Production environments must use a dedicated database account with minimum required privileges.

Keep the real `.env` file only in the local or server environment. Never commit or share it.

## 5/A. Create a fresh DEMO database

Use this only for local development or a clean demo environment. For a real new customer installation, use the seed-free `migrate` + `app:bootstrap-client` workflow in section 5/C.

1. Start Apache and MySQL in the XAMPP control panel.
2. Open `http://localhost/phpmyadmin/`.
3. Create the `appointment_platform` database with `utf8mb4_unicode_ci` collation.
4. Confirm that `BUSINESS_SEED_EMAIL` contains a valid e-mail address in the local `.env` file.
5. Run:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan migrate --seed
php artisan migrate:status
```

The seeder creates the sample business, services, working hours, FAQ entries, and—only when `ADMIN_SEED_PASSWORD` is set—a local sample administrator. There is no hard-coded administrator password.

New-booking notifications for administrators are sent to `BUSINESS_SEED_EMAIL`. The first seed stores this value as the business e-mail address; it can be changed later in the Website section of the administration interface. Changing `.env` afterwards does not update an existing business automatically.

`ADMIN_SEED_EMAIL` is separate: it is the sample administrator's login address. `MAIL_FROM_ADDRESS` is the technical SMTP sender.

## 5/B. Restore existing data

If you already have a database backup:

1. create an empty database;
2. import the dump with phpMyAdmin;
3. configure the corresponding `DB_*` values in the local `.env` file;
4. run:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan migrate
php artisan migrate:status
```

Do not use `migrate:fresh --seed` with existing data: it deletes every table and record.

For a copied test environment, clearing old sessions, verification codes, and queued jobs is recommended:

```sql
TRUNCATE TABLE personal_access_tokens;
TRUNCATE TABLE admin_verification_codes;
TRUNCATE TABLE customer_verification_codes;
TRUNCATE TABLE jobs;
TRUNCATE TABLE failed_jobs;
TRUNCATE TABLE job_batches;
```

Invalidate earlier booking-management links with:

```powershell
php artisan app:invalidate-manage-links --business=default
```


## 5/C. Bootstrap a clean new customer without demo seed data

For a real customer installation, do not use the demo seeder. The target is to deploy the
same source code without manual PHP/JS edits and configure only environment-specific and
customer-specific data.

1. Create a completely empty database.
2. Configure the final `DB_*` values in `backend/.env`.
3. Clear stale configuration and run migrations:

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status
```

4. Create the customer business without sample data:

```powershell
php artisan app:bootstrap-client `
  --name="Customer Business" `
  --email="customer@example.com" `
  --timezone=Europe/Budapest
```

The default technical slug is `default`. In the current deployment model one customer gets
one separate installation, so keeping `default` avoids per-customer frontend source edits.

The bootstrap command:

- creates the active business;
- creates Monday–Friday 09:00–17:00 working hours by default;
- creates no demo services;
- creates no demo reviews;
- creates no sample administrator.

Override the default working hours when required:

```powershell
php artisan app:bootstrap-client `
  --name="Customer Business" `
  --email="customer@example.com" `
  --timezone=Europe/Budapest `
  --work-start=08:00 `
  --work-end=16:00
```

Or skip initial working hours completely:

```powershell
php artisan app:bootstrap-client `
  --name="Customer Business" `
  --email="customer@example.com" `
  --no-working-hours
```

If the `default` slug already exists, the command intentionally refuses to overwrite
existing production data.

## 6. Restore uploaded images

The database stores only file paths. Restore the corresponding business logos and service images from a separate backup:

```text
backend/storage/app/public/businesses/
backend/storage/app/public/services/
```

Restore images from the same backup point as the database. For a new installation, skip this step and upload images later through the administration interface.

## 7. Open the application

Apache and MySQL must be running in XAMPP. No separate `php artisan serve`, Vue development server, or npm build is required.

```text
Booking page:    http://localhost/appointment-platform/
Customer account: http://localhost/appointment-platform/fiokom
Administration: http://localhost/appointment-platform/admin
API check:       http://localhost/appointment-platform/api/v1/businesses/default
```

If you cloned the project under a different directory name, update these URLs and the three URL variables accordingly.

## 8. Queue worker and scheduler

During development, both can be started together:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
.\scripts\start-workers.bat
```

Separate commands:

```powershell
php artisan queue:work database --queue=emails,default --sleep=3 --tries=5 --backoff=60 --timeout=90
php artisan schedule:work
```

The queue worker delivers e-mails. The scheduler runs reminders, retention tasks, and other scheduled operations. Restart both processes after changing `.env`.

## 9. Owner and administrator accounts

For local development, use the seeded administrator configured in `.env`. Create a production owner account with:

```powershell
php artisan app:create-owner --business=default --name="Owner Name" --email="owner@example.com"
```

A working SMTP configuration and queue worker are required to deliver the activation code. Remove a legacy administrator only after successful owner activation and a verified login:

```powershell
php artisan app:remove-admin --business=default --email="old-admin@example.com"
```

## 10. Testing

Backend:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan test
```

Backend tests use an in-memory SQLite database and do not modify local MySQL data.

### Full pre-release gate

Run this from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\release-check.ps1
```

It runs repository hygiene checks, `git diff --check`, the full backend PHPUnit suite with warnings treated as failures, and all frontend/static smoke tests. Continue with a release or customer deployment only after `Release check: PASS`. A clean clone requires `composer install` first.

Run the frontend tests from the project root:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform
Get-ChildItem .\frontend\tests\*.mjs | ForEach-Object {
    node $_.FullName
}
```

## 11. Configure real SMTP delivery

For the first local run, `MAIL_MAILER=log` is safe: messages are written to `backend/storage/logs/laravel.log`.

For real delivery, configure only the local or production `.env` file:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=mailer@example.com
MAIL_PASSWORD=SEPARATE_APPLICATION_PASSWORD
MAIL_FROM_ADDRESS=mailer@example.com
```

Then run:

```powershell
php artisan optimize:clear
```

Restart the queue worker afterwards. A production domain also requires SPF, DKIM, and DMARC configuration.

## 12. Troubleshooting

### `Access denied for user ... using password: NO`

The database user or password in `.env` is incorrect. A default XAMPP installation typically uses:

```dotenv
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan optimize:clear` and `php artisan migrate:status`.

### A 404 response or directory listing appears

Check `mod_rewrite`, `AllowOverride All`, the root `.htaccess`, and confirm that the project is inside `htdocs`.

### A 500 error appears on the first request

```powershell
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate
```

In a local environment, detailed errors are stored in `backend/storage/logs/laravel.log`.

### E-mails are not delivered

This is expected with `MAIL_MAILER=log`. With SMTP enabled, check the queue worker and run:

```powershell
php artisan queue:failed
```

### Images are missing

A database import does not contain image files. Restore them as described in section 6.

## 13. Minimum production configuration

Every customer installation requires at least:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `LOG_LEVEL=warning` or a stricter production level;
- HTTPS and final `APP_URL`, `FRONTEND_URL`, `PUBLIC_APP_URL`;
- a unique `APP_KEY`;
- a dedicated least-privilege database user;
- real SMTP credentials with a separate application password;
- SPF, DKIM and DMARC;
- `QUEUE_CONNECTION=database`;
- a scheduler and queue worker triggered every minute;
- automatic database and uploaded-media backups;
- a tested restore procedure;
- external HTTP monitoring for the public site and `/up`;
- final customer-specific legal documents.

After production changes:

```bash
php artisan optimize:clear
php artisan migrate --force
```

Never use `migrate:fresh`, `migrate:fresh --seed`, or another destructive database
reinitialization command on production data.

## 14. Automatic backups

Backups are configured per installation, so the same source code can be reused for every
customer. Example production `.env`:

```dotenv
BACKUP_ENABLED=true
BACKUP_PATH=/home/ACCOUNT/backups/example.com
BACKUP_RETENTION_DAYS=14
BACKUP_INCLUDE_MEDIA=true
BACKUP_MYSQLDUMP_BINARY=/usr/bin/mysqldump
BACKUP_GZIP_BINARY=/usr/bin/gzip
BACKUP_TIMEOUT_SECONDS=300
```

Store backups outside the public document root and restrict access to the hosting account.

Manual backup:

```bash
php artisan app:backup
```

Integrity verification:

```bash
php artisan app:backup-verify
```

A backup contains:

```text
backup-YYYYMMDD-HHMMSS/
├── database.sql.gz
├── manifest.json
└── media/
```

The backup service:

- creates a MySQL/MariaDB dump;
- stores and verifies a SHA-256 hash;
- verifies gzip integrity;
- includes uploaded business/service images;
- applies 14-day retention by default;
- normalizes the newer MariaDB `mysqldump` sandbox header for easier restore with older compatible clients.

The Laravel scheduler runs `app:backup` every day at 01:30 when `BACKUP_ENABLED=true`.
Verify it with:

```bash
php artisan schedule:list
```

The `appointment-application-backup` task must be present.

## 15. Shared hosting cron – minimum standard

On shared hosting, two generic cron entries are sufficient. Adjust the path and PHP binary
to the provider.

Scheduler, every minute:

```bash
cd /web/example.com/backend && /usr/bin/php8.3 artisan schedule:run >/dev/null 2>&1
```

Queue worker, every minute:

```bash
cd /web/example.com/backend && /usr/bin/php8.3 artisan queue:work database --queue=emails,default --stop-when-empty --tries=3 --timeout=120 >/dev/null 2>&1
```

`--stop-when-empty` is well suited to shared hosting: the worker processes the current
queue and exits, so the next cron invocation automatically loads the newly deployed code.

## 16. Restore test

A backup is not considered complete until it has been restored successfully at least once
into a separate test database.

Recommended process:

1. run `app:backup` and `app:backup-verify`;
2. download the latest `database.sql.gz`, `manifest.json`, and `media/` into a private,
   non-Git local directory;
3. compare the downloaded database SHA-256 hash with the manifest;
4. decompress the SQL dump;
5. create a separate disposable restore-test database;
6. import the dump;
7. verify tables and important record counts.

Never perform a restore test against the live production database. Delete unnecessary
local copies of real production data after the test and never commit them to Git.

## 17. External monitoring and health check

Laravel health endpoint:

```text
https://example.com/up
```

For every customer, create two external HTTP monitors (for example UptimeRobot or another
uptime service):

```text
Website: https://example.com/
Health:  https://example.com/up
```

A five-minute interval with e-mail alerts is a practical starting point. Send a test alert
once. Monitoring is an operational setup item per domain, not an application feature.

## 18. Production readiness GO/NO-GO

Technical check:

```bash
php artisan app:production-check --business=default
```

Before final customer handover:

```bash
php artisan app:production-check --business=default --strict
```

The strict check covers, among other items:

- production environment and debug state;
- HTTPS URLs and APP_KEY;
- database queue and SMTP;
- database connectivity and pending migrations;
- business, working hours and at least one active service;
- an activated owner;
- final legal content;
- writable Laravel runtime directories;
- backup availability, integrity and freshness.

Handover should happen only after the strict check reports GO.

## 19. Standard new-customer installation flow

The target is zero manual PHP/JS source edits for a normal new customer.

1. Create domain, DNS, HTTPS and hosting.
2. Clone/deploy the repository.
3. Run `composer install`.
4. Create `backend/.env` from `.env.example`.
5. Configure unique APP_KEY, URLs, database, SMTP and backup settings.
6. Create an empty production database.
7. Run `php artisan migrate --force`.
8. Run `app:bootstrap-client` using the default `default` slug.
9. Create the owner:

```bash
php artisan app:create-owner --business=default --name="Owner Name" --email="owner@example.com"
```

10. Process the queue, confirm activation-code delivery, and verify owner login.
11. Configure services, working hours, branding and public content through the admin UI.
12. Fill in final legal text with the customer's real data.
13. Create scheduler and queue cron jobs.
14. Enable backup, then run `app:backup` and `app:backup-verify`.
15. Create external monitors for `/` and `/up`.
16. Run a full booking smoke test: booking, e-mail, manage link, reschedule/cancel.
17. Run `app:production-check --business=default --strict`.
18. Hand over only after GO.

## 20. Deployment and release flow

Before release, from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\release-check.ps1
```

Then commit and push:

```powershell
git status
git add .
git commit -m "Describe the release"
git push origin main
```

The production deploy script supports a configurable SSH target:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\deploy-production.ps1 `
  -SshTarget customer-ssh-alias `
  -RemoteCommand "~/deploy-production.sh"
```

The server-side deployment command/path remains hosting-specific. At minimum it should
apply the intended release, run `composer install`, `php artisan migrate --force`, clear
Laravel caches, and perform a production API/health check.

## 21. What remains customer-specific?

The platform source code is shared. A normal new order usually changes only:

- domain, DNS and HTTPS;
- hosting path, PHP binary and SSH/cron environment;
- `.env` URLs, database, SMTP and backup path;
- business identity/contact data and owner;
- services, prices, durations and working hours;
- logo, images, colors and page copy;
- final legal documents;
- monitoring alert recipients.

If the customer's requirements stay within this model, no new feature development should
be required.
