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

## 5/A. Create a fresh database

Use this option for a new installation or a clean demo environment.

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

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS and final application URLs;
- unique `APP_KEY`, database, and SMTP credentials;
- a database user with minimum required privileges;
- a supervised queue worker and a scheduler running every minute;
- database and uploaded-image backups;
- log rotation, storage monitoring, and a tested restore procedure;
- final legal documents and e-mail DNS configuration.
