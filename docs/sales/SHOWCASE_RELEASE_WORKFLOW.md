# Bemutató oldal – fejlesztési és release workflow

## Alapelv

A félkész bemutató oldal **nem kerül a `main` branchre és nem kerül productionre**.
A munkát a `feature/sales-showcase` branchen lehet biztonságosan GitHubra pusholni, így több gép között is folytatható anélkül, hogy a publikus oldal változna.

## 1. Munkahelyi gép – checkpoint

A végleges screenshotos bemutatóoldal ellenőrzése után:

```powershell
git status
git add frontend/index.php `
        frontend/views/bemutato `
        frontend/assets/sales/screenshots `
        frontend/tests/sales-showcase-smoke.mjs `
        docs/sales
git commit -m "Finalize sales showcase before demo video"
git push -u origin feature/sales-showcase
```

Ez **nem release**, csak távoli fejlesztési checkpoint. A `main` változatlan marad.

> Ne használd ehhez a Google Drive-os ZIP-másolást, ha a Git branch elérhető. A branch megőrzi a fájlokat, a módosítási történetet és az összehasonlíthatóságot is.

## 2. Otthoni gép – folytatás

```powershell
git fetch origin
git switch feature/sales-showcase
git pull --ff-only
```

Ezután felvehető a bemutató videó.

## 3. Videó hozzáadása

A videó master fájl **nem kerül Gitre** és nem kerül a Rackhost tárhelyre.

Javasolt:

1. videó feltöltése YouTube-ra (nem listázott vagy publikus),
2. privacy-enhanced embed URL használata,
3. `frontend/views/bemutato/index.php` fájlban:

```php
$videoEmbedUrl = 'https://www.youtube-nocookie.com/embed/VIDEO_ID';
```

Üres értéknél a videós szekció automatikusan rejtve marad.

## 4. QR-kód

A végleges QR célja:

```text
https://olcsikaszbusiness.hu/
```

A QR publikus, optimalizált PNG változata mehet Gitre. A nagy forrásfájl vagy szerkesztőprojekt maradjon a `marketing-source/` alatt.

Az élő Aranyvonal demo címe: `https://olcsikaszbusiness.hu/demo`. A korábbi `/bemutato` URL csak kompatibilitási átirányításként marad meg.

## 5. Release gate

A `main` merge előtt:

- minden szöveg végleges,
- 6 screenshot megjelenik,
- videó betöltődik desktopon és mobilon,
- minden CTA működik,
- nincs `localhost` vagy fejlesztői adat,
- a QR az Olcsi Business főoldalára (`/`) mutat,
- frontend smoke tesztek PASS,
- backend tesztek PASS,
- repository hygiene PASS,
- `.env`, SQL dump, nyers screenshot és videó nincs stagingben.

## 6. Végleges release

A release gate után a feature branch kerüljön a `main` branchre. Csak ekkor készüljön a következő valódi release tag (az aktuális tag után következő verzió).

Ezután:

1. `main` push GitHubra,
2. production deploy a meglévő deploy scripttel,
3. Rackhost ellenőrzés,
4. QR végső teszt éles telefonról.
