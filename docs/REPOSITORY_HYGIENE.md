# Repository- és secret-higiénia

## Mi kerülhet Gitbe?

Forráskód, migrációk, seederek, tesztek, dokumentáció, `composer.lock`, biztonságos `.env.example` fájlok és az üres runtime könyvtárakat megőrző `.gitkeep` fájlok.

A Laravel migrációk az adatbázisséma elsődleges forrásai. A `backend/database/schema_mysql.sql` csak dokumentált séma-segédlet; valós adatokat nem tartalmazhat.

## Mi nem kerülhet Gitbe?

- valódi `.env`, `APP_KEY`, API-kulcs, SMTP- vagy adatbázis-jelszó;
- `backend/vendor`, PHPUnit cache vagy más generált függőség;
- Laravel log, session, cache, fordított view és bootstrap cache;
- SQL-dump, lokális adatbázis vagy kiadási ZIP;
- feltöltött logó, szolgáltatáskép vagy más ügyfélfájl;
- személyes adatot, jelszóhash-t, kezelőlinket vagy hozzáférési tokent tartalmazó export.

## Feltöltött képek

A képek runtime adatok:

- `backend/storage/app/public/businesses/`;
- `backend/storage/app/public/services/`.

Git csak a `.gitkeep` fájlokat tartja meg. A képeket az adatbázissal azonos mentési időpontról, külön hozzáférésvédett backupba kell menteni. Az árva fájlok a `php artisan images:cleanup` paranccsal takaríthatók.

## Public repository létrehozása régi privát projektből

Egy fájl törlése a legújabb commitból nem törli azt a korábbi commitokból. Ha a repository valaha valódi `.env`-et vagy más titkot tartalmazott, két biztonságos út van:

1. a megtisztított mappából új repository és új Git-előzmény létrehozása; vagy
2. minden titok rotálása, Gitleaks history scan, majd ellenőrzött `git filter-repo`/BFG history-újraírás.

Az első megoldás egyszerűbb és kisebb hibakockázatú. Régi history átírása után koordinált force push és minden régi klón újraklónozása szükséges.

## Kötelező rotáció

Ha egy titok valaha commitba, megosztott ZIP-be vagy nyilvános hibajegybe került, nem elég törölni. A szolgáltatónál vissza kell vonni és új értéket kell létrehozni. Az új érték kizárólag a helyi/production `.env` fájlban maradhat.

Konfigurációcsere után:

```powershell
php artisan optimize:clear
```

A queue és scheduler folyamatokat is újra kell indítani.

## Adatbázis és tokenek átadása

Nyilvános repositoryhoz ne adj SQL-dumpot. Tesztpéldány másolásakor ajánlott a munkamenetek, ellenőrzőkódok és régi queue-feladatok törlése. Megosztott foglaláskezelő linkek lejáratása:

```powershell
php artisan app:invalidate-manage-links --business=default
```

## Ellenőrzés

Aktuális fájlok:

```powershell
.\scripts\verify-repository-hygiene.ps1
git status --short
```

Teljes Git-előzmény Gitleaksszel:

```powershell
.\scripts\scan-git-history.ps1
```

A `git status` és `git ls-files` nem mutathat valódi `.env`-et, vendort, runtime fájlt, feltöltött képet, dumpot vagy ZIP-et.
