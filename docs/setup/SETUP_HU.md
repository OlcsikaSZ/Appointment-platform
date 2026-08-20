# Appointment Platform – telepítési útmutató (Windows + XAMPP)

**Magyar** | [English](SETUP_EN.md)

Ez az útmutató egy teljesen új telepítést mutat be a repository klónozásától a működő helyi alkalmazásig. A példák Windows PowerShellt és XAMPP-ot használnak.

## 1. Mi készül el telepítéskor?

A forráskód szándékosan nem tartalmaz gép- vagy környezetfüggő adatokat:

- valódi `backend/.env` fájlt és hitelesítő adatokat;
- Composer által generált `backend/vendor` könyvtárat;
- adatbázist, adatbázis-dumpot vagy valós ügyféladatot;
- logokat, cache-eket, sessionöket és fordított nézeteket;
- feltöltött vállalkozáslogókat és szolgáltatásképeket.

Ezek nem hiányzó forráskódfájlok. A függőségek és a helyi konfiguráció telepítéskor jönnek létre, az adatokat és feltöltött képeket pedig szükség esetén külön mentésből kell visszaállítani.

## 2. Rendszerkövetelmények

- Git;
- XAMPP PHP 8.2 vagy újabb verzióval;
- Composer 2, az XAMPP PHP-jához kapcsolva;
- Apache `mod_rewrite` és engedélyezett `.htaccess`;
- MySQL vagy kompatibilis MariaDB;
- Node.js 18+ csak a frontendtesztekhez.

Szükséges PHP-bővítmények:

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=zip
```

Ellenőrzés PowerShellben:

```powershell
git --version
php -v
composer --version
php -m | Select-String 'pdo_mysql|mbstring|openssl|fileinfo|gd|zip'
```

Ha a `php` parancs nem található, használd az XAMPP PHP teljes elérési útját, például:

```powershell
& 'C:\xampp\php\php.exe' -v
```

## 3. Klónozás és backendtelepítés

Az alábbi példa a projektet a `C:\xampp\htdocs\appointment-platform` mappába klónozza:

```powershell
Set-Location C:\xampp\htdocs
git clone https://github.com/OlcsikaSZ/Appointment-platform.git appointment-platform
Set-Location .\appointment-platform\backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Ha a XAMPP máshol található, cseréld ki a példákban szereplő `C:\xampp` útvonalat. A `composer install` a verziókezelt `composer.lock` alapján hozza létre a `vendor` könyvtárat.

## 4. A helyi `.env` beállítása

Nyisd meg a következő fájlt:

```text
C:\xampp\htdocs\appointment-platform\backend\.env
```

Alap helyi XAMPP-konfiguráció:

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

BUSINESS_SEED_EMAIL=ertesites@sajatdomain.hu
ADMIN_SEED_EMAIL=admin@example.test
ADMIN_SEED_PASSWORD=IDE_IRJ_EGY_EROS_HELYI_JELSZOT
```

Az alap XAMPP általában `root` adatbázis-felhasználót és üres jelszót használ. Ha a saját MySQL-beállításod eltér, a `DB_*` értékeket ahhoz igazítsd. Éles környezetben külön, minimális jogosultságú adatbázis-felhasználó szükséges.

A valódi `.env` kizárólag a helyi vagy szerverkörnyezetben maradjon; ne commitold és ne oszd meg.

## 5/A. Teljesen friss adatbázis

Ezt használd új telepítéshez vagy tiszta demókörnyezethez.

1. Indítsd el az Apache és MySQL modulokat a XAMPP vezérlőpultján.
2. Nyisd meg a `http://localhost/phpmyadmin/` oldalt.
3. Hozd létre az `appointment_platform` adatbázist `utf8mb4_unicode_ci` illesztéssel.
4. Ellenőrizd, hogy a helyi `.env` fájlban a `BUSINESS_SEED_EMAIL` érvényes e-mail-címet tartalmaz.
5. Futtasd:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan migrate --seed
php artisan migrate:status
```

A seeder létrehozza a mintavállalkozást, szolgáltatásokat, munkaidőt, GYIK-et és – kizárólag akkor, ha az `ADMIN_SEED_PASSWORD` ki van töltve – a helyi mintaadmint. Beégetett adminjelszó nincs.

Az admin új foglalásokról a `BUSINESS_SEED_EMAIL` címen kap értesítést. Ez az érték az első seedeléskor bekerül a vállalkozás adatai közé; később az adminfelület Weboldal részén módosítható. A `.env` utólagos átírása önmagában nem módosítja a már létrehozott vállalkozást.

Az `ADMIN_SEED_EMAIL` ettől különálló érték: ez a mintaadmin belépési címe. A `MAIL_FROM_ADDRESS` pedig az SMTP technikai feladója.

## 5/B. Meglévő adatok visszaállítása

Ha már rendelkezel korábbi adatbázis-mentéssel:

1. hozz létre egy üres adatbázist;
2. importáld a dumpot phpMyAdminnal;
3. állítsd be a megfelelő `DB_*` értékeket a helyi `.env` fájlban;
4. futtasd:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan migrate
php artisan migrate:status
```

Meglévő adatoknál ne használd a `migrate:fresh --seed` parancsot, mert minden táblát és adatot töröl.

Másolt tesztkörnyezetben ajánlott törölni a régi munkameneteket, ellenőrzőkódokat és queue-feladatokat:

```sql
TRUNCATE TABLE personal_access_tokens;
TRUNCATE TABLE admin_verification_codes;
TRUNCATE TABLE customer_verification_codes;
TRUNCATE TABLE jobs;
TRUNCATE TABLE failed_jobs;
TRUNCATE TABLE job_batches;
```

Korábbi foglaláskezelő linkek lejáratása:

```powershell
php artisan app:invalidate-manage-links --business=default
```

## 6. Feltöltött képek visszaállítása

Az adatbázis csak a fájlok útvonalát tárolja. A hozzá tartozó logókat és szolgáltatásképeket külön mentésből kell visszamásolni:

```text
backend/storage/app/public/businesses/
backend/storage/app/public/services/
```

A képeket az adatbázissal azonos mentési időpontról állítsd vissza. Ha új telepítést készítesz, ezt a lépést kihagyhatod, és később az adminfelületen tölthetsz fel képeket.

## 7. Az alkalmazás megnyitása

Az Apache és MySQL fusson a XAMPP-ban. Külön `php artisan serve`, Vue fejlesztői szerver vagy npm build nem szükséges.

```text
Foglalási oldal: http://localhost/appointment-platform/
Ügyfélfiók:      http://localhost/appointment-platform/fiokom
Admin:           http://localhost/appointment-platform/admin
API-próba:       http://localhost/appointment-platform/api/v1/businesses/default
```

Ha más mappanéven klónoztad a projektet, az URL-eket és a három URL-változót ennek megfelelően módosítsd.

## 8. Queue worker és scheduler

Fejlesztés közben együtt indíthatók:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
.\scripts\start-workers.bat
```

Külön parancsok:

```powershell
php artisan queue:work database --queue=emails,default --sleep=3 --tries=5 --backoff=60 --timeout=90
php artisan schedule:work
```

A queue worker küldi az e-maileket. A scheduler indítja többek között az emlékeztetőket és az adatmegőrzési feladatokat. A két folyamatot `.env`-módosítás után újra kell indítani.

## 9. Owner- és adminfiók

Helyi fejlesztéshez az `.env`-ben megadott seed admin használható. Éles tulajdonosi fiók létrehozása:

```powershell
php artisan app:create-owner --business=default --name="Tulajdonos Neve" --email="owner@example.com"
```

Az aktiválókód kiküldéséhez működő SMTP és futó queue worker szükséges. A régi admin csak a sikeres owneraktiválás és belépési próba után távolítható el:

```powershell
php artisan app:remove-admin --business=default --email="regi-admin@example.com"
```

## 10. Tesztelés

Backend:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform\backend
php artisan optimize:clear
php artisan test
```

A backendtesztek memóriabeli SQLite-adatbázist használnak, ezért nem módosítják a helyi MySQL-adatokat.

Frontend a projekt gyökeréből:

```powershell
Set-Location C:\xampp\htdocs\appointment-platform
Get-ChildItem .\frontend\tests\*.mjs | ForEach-Object {
    node $_.FullName
}
```

## 11. Valódi SMTP beállítása

Első helyi indításnál a `MAIL_MAILER=log` biztonságos: a levelek a `backend/storage/logs/laravel.log` fájlba kerülnek.

Valódi küldéshez kizárólag a helyi vagy production `.env` fájlban állítsd be:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=mailer@example.com
MAIL_PASSWORD=KULON_ALKALMAZASJELSZO
MAIL_FROM_ADDRESS=mailer@example.com
```

Ezután:

```powershell
php artisan optimize:clear
```

Majd indítsd újra a queue workert. Éles domainnél SPF, DKIM és DMARC beállítás is szükséges.

## 12. Gyakori hibák

### `Access denied for user ... using password: NO`

A `.env` adatbázis-felhasználója vagy jelszava hibás. Alap XAMPP esetén jellemzően:

```dotenv
DB_USERNAME=root
DB_PASSWORD=
```

Ezután futtasd a `php artisan optimize:clear` és `php artisan migrate:status` parancsokat.

### 404 vagy fájllista jelenik meg

Ellenőrizd a `mod_rewrite`, `AllowOverride All`, `.htaccess` és a projekt `htdocs` alatti helyét.

### 500-as hiba az első megnyitáskor

```powershell
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate
```

A részletes hiba helyi környezetben a `backend/storage/logs/laravel.log` fájlban található.

### Nem érkezik e-mail

`MAIL_MAILER=log` esetén ez elvárt. SMTP-nél ellenőrizd a queue workert és futtasd:

```powershell
php artisan queue:failed
```

### Nem jelennek meg a képek

Az adatbázis-import önmagában nem tartalmazza a képfájlokat. Állítsd vissza őket a 6. pont szerint.

## 13. Éles környezet minimuma

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS és végleges URL-ek;
- egyedi `APP_KEY`, adatbázis- és SMTP-hitelesítő adatok;
- minimális jogosultságú adatbázis-felhasználó;
- felügyelt queue worker és percenkénti scheduler;
- adatbázis- és képfájlmentés;
- logrotáció, tárhelyfigyelés és kipróbált visszaállítás;
- végleges jogi dokumentumok és levelezési DNS-beállítások.
