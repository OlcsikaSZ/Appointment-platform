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

## 5/A. Teljesen friss DEMO adatbázis

Ezt kizárólag helyi fejlesztéshez vagy tiszta demókörnyezethez használd. Valódi új ügyfél telepítéséhez az 5/C pont szerinti `migrate` + `app:bootstrap-client` folyamat az ajánlott, seed nélkül.

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


## 5/C. Új ügyfél tiszta telepítése seed nélkül

Valódi új ügyfélnél ne a demó seedert használd. A cél az, hogy a telepítés ugyanabból a
forráskódból, kézi PHP/JS módosítás nélkül, kizárólag konfigurációval és ügyféladatokkal
elkészüljön.

1. Hozz létre egy teljesen üres adatbázist.
2. Állítsd be a végleges `DB_*` értékeket a `backend/.env` fájlban.
3. Töröld a korábbi config/cache állapotot, majd futtasd a migrációkat:

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status
```

4. Hozd létre az ügyfél vállalkozását mintaadatok nélkül:

```powershell
php artisan app:bootstrap-client `
  --name="Ügyfél Vállalkozása" `
  --email="ugyfel@example.hu" `
  --timezone=Europe/Budapest
```

A parancs alapértelmezett technikai slugja `default`. A jelenlegi telepítési modellben
egy ügyfél egy külön telepítést kap, ezért ezt érdemes megtartani: így nem kell
ügyfelenként frontend forráskódot átírni.

A bootstrap:

- létrehozza az aktív vállalkozást;
- alapból hétfő–péntek 09:00–17:00 munkaidőt hoz létre;
- nem hoz létre demo szolgáltatást;
- nem hoz létre demo véleményt;
- nem hoz létre mintaadmint.

Az alap munkaidő felülírható:

```powershell
php artisan app:bootstrap-client `
  --name="Ügyfél Vállalkozása" `
  --email="ugyfel@example.hu" `
  --timezone=Europe/Budapest `
  --work-start=08:00 `
  --work-end=16:00
```

Ha az induló munkaidőt sem szeretnéd automatikusan létrehozni:

```powershell
php artisan app:bootstrap-client `
  --name="Ügyfél Vállalkozása" `
  --email="ugyfel@example.hu" `
  --no-working-hours
```

Ha a `default` slug már létezik, a parancs szándékosan nem írja felül a meglévő
production adatot.

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

### Kiadás előtti teljes release gate

A projekt gyökeréből az ajánlott ellenőrzés:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\release-check.ps1
```

Ez egyben futtatja a repository hygiene ellenőrzést, a `git diff --check` vizsgálatot, a teljes backend PHPUnit tesztcsomagot warningokra is szigorúan, valamint az összes frontend/static smoke tesztet. Új kiadás vagy ügyféltelepítés előtt csak `Release check: PASS` eredménnyel folytasd. Tiszta clone esetén előtte szükséges a `composer install`.

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

Minden ügyfél telepítésénél legalább az alábbiak szükségesek:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `LOG_LEVEL=warning` vagy ennél szigorúbb production szint;
- HTTPS és végleges `APP_URL`, `FRONTEND_URL`, `PUBLIC_APP_URL`;
- egyedi `APP_KEY`;
- külön, minimális jogosultságú adatbázis-felhasználó;
- valós SMTP és külön alkalmazásjelszó;
- SPF, DKIM és DMARC;
- `QUEUE_CONNECTION=database`;
- percenként futó scheduler és queue worker;
- automatikus adatbázis- és média-backup;
- kipróbált restore eljárás;
- külső HTTP monitoring a főoldalra és a `/up` health endpointra;
- végleges, ügyfélspecifikus jogi dokumentumok.

Éles módosítás után:

```bash
php artisan optimize:clear
php artisan migrate --force
```

Production adatbázison `migrate:fresh`, `migrate:fresh --seed` vagy más destruktív
újrainicializálás nem használható.

## 14. Automatikus backup

Az automatikus backup telepítésenként konfigurálható, ezért ugyanaz a forráskód
minden ügyfélnél használható. Példa production `.env`:

```dotenv
BACKUP_ENABLED=true
BACKUP_PATH=/home/ACCOUNT/backups/example.hu
BACKUP_RETENTION_DAYS=14
BACKUP_INCLUDE_MEDIA=true
BACKUP_MYSQLDUMP_BINARY=/usr/bin/mysqldump
BACKUP_GZIP_BINARY=/usr/bin/gzip
BACKUP_TIMEOUT_SECONDS=300
```

A célkönyvtár legyen a publikus webrooton kívül és csak a tárhelyfiók számára írható/olvasható.

Kézi mentés:

```bash
php artisan app:backup
```

Integritásellenőrzés:

```bash
php artisan app:backup-verify
```

A mentés tartalmazza:

```text
backup-YYYYMMDD-HHMMSS/
├── database.sql.gz
├── manifest.json
└── media/
```

A backup szolgáltatás:

- MySQL/MariaDB dumpot készít;
- SHA-256 hash-t tárol és ellenőriz;
- gzip integritást ellenőriz;
- a feltöltött üzleti és szolgáltatásképeket is menti;
- alapból 14 napos retentiont alkalmaz;
- a MariaDB újabb `mysqldump` sandbox fejlécét normalizálja, hogy a dump régebbi kompatibilis klienssel is könnyebben visszaállítható legyen.

A Laravel scheduler minden nap 01:30-kor futtatja az `app:backup` parancsot, ha
`BACKUP_ENABLED=true`. Ellenőrzés:

```bash
php artisan schedule:list
```

A listában az `appointment-application-backup` feladatnak szerepelnie kell.

## 15. Shared hosting cron – minimális standard

Shared hostingon elegendő két általános cron feladat. Az útvonalat és a PHP binárist
a szolgáltatóhoz kell igazítani.

Scheduler, minden percben:

```bash
cd /web/example.hu/backend && /usr/bin/php8.3 artisan schedule:run >/dev/null 2>&1
```

Queue worker, minden percben:

```bash
cd /web/example.hu/backend && /usr/bin/php8.3 artisan queue:work database --queue=emails,default --stop-when-empty --tries=3 --timeout=120 >/dev/null 2>&1
```

A `--stop-when-empty` shared hostingon azért praktikus, mert a worker feldolgozza az
aktuális queue-t, majd leáll. A következő cronindítás már automatikusan a friss deployolt
kódot tölti be, ezért külön hosszú életű worker restart általában nem szükséges.

## 16. Backup visszaállítás és restore-próba

A backup csak akkor tekinthető késznek, ha legalább egyszer ténylegesen vissza is
állítottad egy külön tesztadatbázisba.

Ajánlott folyamat:

1. futtasd az `app:backup` és `app:backup-verify` parancsokat;
2. töltsd le a legfrissebb `database.sql.gz`, `manifest.json` és `media/` tartalmat egy nem Git által kezelt, privát helyi könyvtárba;
3. a `manifest.json` SHA-256 értékét hasonlítsd össze a letöltött `database.sql.gz` hashével;
4. csomagold ki a dumpot;
5. hozz létre egy külön, eldobható restore-test adatbázist;
6. importáld a dumpot;
7. ellenőrizd a táblákat és a fontos rekordszámokat.

A restore-próba soha ne az éles adatbázison történjen.

A restore után a letöltött production dumpot és másolt ügyféladatokat ne tartsd
feleslegesen a fejlesztői gépen, és soha ne commitold Gitbe.

## 17. Külső monitoring és health check

A Laravel health endpoint:

```text
https://example.hu/up
```

Minden ügyfélnél javasolt két külső HTTP monitor, például UptimeRobot vagy más
uptime szolgáltató használatával:

```text
Website: https://example.hu/
Health:  https://example.hu/up
```

Ajánlott induló intervallum: 5 perc, e-mailes hibajelzéssel. Az értesítést egyszer
teszteld is. A monitorozás nem alkalmazásfeature: minden új domainnél rövid
üzemeltetési setupként külön létre kell hozni.

## 18. Production readiness GO/NO-GO

Technikai ellenőrzés:

```bash
php artisan app:production-check --business=default
```

Végső ügyfélátadás előtt:

```bash
php artisan app:production-check --business=default --strict
```

A szigorú ellenőrzés többek között vizsgálja:

- production env és debug állapot;
- HTTPS URL-ek;
- APP_KEY;
- database queue és SMTP;
- adatbázis-kapcsolat és pending migrációk;
- vállalkozás, munkaidő és legalább egy aktív szolgáltatás;
- aktivált owner;
- jogi tartalmak;
- Laravel írható könyvtárai;
- backup engedélyezését, elérhetőségét, integritását és frissességét.

Ügyfélátadás csak akkor történjen, ha a `--strict` ellenőrzés GO eredményt ad.

## 19. Standard új ügyfél telepítési folyamat

A cél, hogy új ügyfélnél ne kelljen PHP/JS forráskódot módosítani.

1. Domain, DNS, HTTPS és tárhely létrehozása.
2. Repository klónozása/deployolása.
3. `composer install`.
4. `backend/.env` létrehozása `.env.example` alapján.
5. Egyedi `APP_KEY`, URL-ek, adatbázis, SMTP és backup beállítása.
6. Üres production adatbázis létrehozása.
7. `php artisan migrate --force`.
8. Ügyfél létrehozása `app:bootstrap-client` paranccsal, alapból `default` sluggal.
9. Owner létrehozása:

```bash
php artisan app:create-owner --business=default --name="Tulajdonos Neve" --email="owner@example.hu"
```

10. Queue futtatása, aktiválókód kézbesítésének és owner belépésének ellenőrzése.
11. A vállalkozás szolgáltatásainak, munkaidejének, arculatának és tartalmainak beállítása az adminból.
12. Végleges jogi szövegek kitöltése az ügyfél adataival.
13. Scheduler + queue cron létrehozása.
14. `BACKUP_ENABLED=true`, majd `app:backup` és `app:backup-verify`.
15. Külső monitor létrehozása `/` és `/up` URL-re.
16. Teljes foglalási smoke test: új foglalás, e-mail, manage link, módosítás/lemondás.
17. `app:production-check --business=default --strict`.
18. Csak GO után ügyfélátadás.

## 20. Deployment és release folyamat

Kiadás előtt a projekt gyökerében:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\release-check.ps1
```

Ezután commit és push:

```powershell
git status
git add .
git commit -m "Describe the release"
git push origin main
```

A production deploy script SSH célja paraméterezhető:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\scripts\deploy-production.ps1 `
  -SshTarget sajat-ssh-alias `
  -RemoteCommand "~/deploy-production.sh"
```

A szerveroldali deploy parancs/tárhelyút továbbra is hostingfüggő. A deploynak legalább
a friss release átvételét, `composer install`-t, `php artisan migrate --force` futtatást,
cache ürítést és egy production API/health ellenőrzést kell tartalmaznia.

## 21. Mit kell ügyfelenként egyedileg megadni?

A platformkód közös. Egy új megrendelésnél tipikusan csak ezek változnak:

- domain, DNS és HTTPS;
- tárhelyút, PHP bináris és SSH/cron környezet;
- `.env`: URL-ek, DB, SMTP és backup path;
- vállalkozás neve, kapcsolati adatai és owner;
- szolgáltatások, árak, időtartamok és munkaidő;
- logó, képek, színek és weboldalszövegek;
- végleges jogi dokumentumok;
- monitoring értesítési címzettjei.

Ha az ügyfél igénye ezekből nem lép ki, új funkció fejlesztése nem szükséges.
