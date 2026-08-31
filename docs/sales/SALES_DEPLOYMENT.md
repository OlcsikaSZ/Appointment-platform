# Értékesítési oldal, Git és production szabályok

## `/bemutato`

A `/bemutato` egy publikus értékesítési landing oldal. Forráskódja a repository része, ezért:

- **GitHubra kerül**;
- a normál production deploy részeként **felkerül az `olcsikaszbusiness.hu` szerverre**;
- nem csak localhoston használjuk.

Production célútvonal:

```text
https://olcsikaszbusiness.hu/bemutato
```

Az oldal elsődleges feladata, hogy elmagyarázza a terméket, majd az élő demóra vagy kapcsolatfelvételre vezesse az érdeklődőt.

## Mi menjen Gitbe?

Igen:

- `/bemutato` PHP/CSS/JS forráskód;
- értékesítési dokumentáció;
- később a biztonságos demo-seeder/parancs forráskódja;
- végleges, optimalizált, személyes adatot nem tartalmazó marketing screenshotok;
- később a stabil QR-kód képfájlja, ha már végleges a cél URL.

Nem:

- `.env`;
- adatbázis-jelszó vagy SMTP-jelszó;
- SQL dump;
- production adatbázis;
- valódi ügyféladat;
- nyers, nagy méretű screenshot-források;
- videó master / vágóprojekt;
- backup ZIP-ek;
- feltöltött ügyfélképek és logók, ha azok runtime adatok.

## Screenshotok

A weboldalon használt, optimalizált képek helye:

```text
frontend/assets/sales/screenshots/
```

Ezek Gitbe és productionre is mehetnek, ha kizárólag mintaadatot tartalmaznak.

A nyers screenshotok maradjanak lokálisan vagy külön privát felhőtárhelyen.

## Demo adatbázis

A demo-adatokat **először lokális adatbázison** készítjük el és ellenőrizzük.

A demo-seeder / demo-frissítő parancs kódja Gitbe kerülhet, a létrejövő adatok viszont nem kerülnek Gitbe.

Ha a lokális ellenőrzés rendben van, akkor:

1. production adatbázis-mentés készül;
2. meggyőződünk róla, hogy a céladatbázis tényleg a publikus DEMO példányhoz tartozik, nem valódi ügyfélhez;
3. csak ezután futtatjuk a demo-adatfrissítést productionön.

Valódi ügyfél adatbázisán demo-seedert nem futtatunk.

## QR-kód

A végleges QR-kód célja:

```text
https://olcsikaszbusiness.hu/bemutato
```

Nem közvetlenül a demóra mutat. Így a nyomtatott QR később is használható akkor is, ha az élő demo címe megváltozik.

A `/bemutato` oldalról külön gomb vezet az élő demóra.

## Videó

A kész videó master fájlja nem szükséges Gitben.

Javasolt folyamat:

1. master videó lokálisan / privát felhőben;
2. kész videó publikálása YouTube-on vagy megfelelő videóplatformon;
3. a `/bemutato` oldalba beágyazott vagy linkelt verzió;
4. külön natív feltöltés Facebookra / Instagramra, ha hirdetésként is használjuk.

## Rövid döntési tábla

| Elem | Git | Production | Local / privát |
|---|---:|---:|---:|
| `/bemutato` forrás | ✓ | ✓ | ✓ |
| Demo-seeder forrás | ✓ | ✓ | ✓ |
| Demo DB adatok | ✗ | csak demo DB-ben | ✓ először |
| SQL dump | ✗ | ✗ | ✓ |
| Optimalizált marketing screenshot | ✓ | ✓ | ✓ |
| Nyers screenshot | ✗ | ✗ | ✓ |
| QR-kód végleges PNG/SVG | ✓ | ✓ | ✓ |
| Videó master | ✗ | ✗ | ✓ |
| Publikált videó | ✗ fájlként | embed/link | ✓ master |
