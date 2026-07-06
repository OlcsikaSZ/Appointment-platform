# Appointment Platform Vue

Rendezett XAMPP-os valtozat:

- `frontend/` - Vue 3 frontend, build es Node nelkul
- `backend/` - Laravel API
- `.htaccess` - a gyoker URL-t a frontendhez, az `/api` utvonalat a Laravelhez iranyitja

## Mappa XAMPP alatt

A teljes `appointment-platform` mappa legyen itt:

```text
E:\Progik\xampp\htdocs\appointment-platform
```

Belul igy nezzen ki:

```text
appointment-platform
├─ frontend
├─ backend
├─ .htaccess
└─ README.md
```

## Elso inditas

XAMPP-ban induljon az Apache es a MySQL.

phpMyAdminban hozd letre az adatbazist:

```text
appointment_platform
```

Majd PowerShellben:

```powershell
cd E:\Progik\xampp\htdocs\appointment-platform\backend
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

## Megnyitas

Foglalasi oldal:

```text
http://localhost/appointment-platform/
```

Admin:

```text
http://localhost/appointment-platform/admin
```

Demo belepes:

```text
admin@example.com
admin123
```

## Fejlesztes

A frontend itt van:

```text
frontend/index.php
frontend/views/main/index.php
frontend/views/main/index.js
frontend/views/main/styles.css
frontend/views/admin/index.php
frontend/views/admin/index.js
frontend/views/admin/styles.css
frontend/views/manage/index.php
frontend/views/manage/index.js
frontend/views/manage/styles.css
frontend/assets/shared.js
frontend/assets/styles.css
```

Az `frontend/index.php` a frontend router. O donti el, hogy a gyoker oldal, az admin vagy a foglalaskezelo nezet toltodjon be.

Mentes utan eleg frissiteni a bongeszot. Nem kell `npm start`, nem kell `ng serve`, nem kell kulon Vue dev server.

A backend itt van:

```text
backend/app
backend/routes
backend/database
```

Az API a bongeszobol ezen keresztul erheto el:

```text
http://localhost/appointment-platform/api/v1
```

## Ami ujdonsag ebben a verzioban

- **Foglalasi oldal**: harom lepeses folyamat (szolgaltatas -> datum/ido -> adatok), kategoria szurovel,
  napi datum-sav gyorsvalasztoval, delelott/delutan/este szerint csoportositott idopontokkal, es egy
  "jegy" stilusu osszegzo/visszaigazolo kartyaval. A sikeres foglalas utan letoltheto naptar (.ics) fajl
  es a kezelo link is megjelenik.
- **Admin felulet**: statisztika-kartyak (osszes / mai / aktiv / lemondott), szurheto es kereshetu
  foglalas-tablazat, gyors statuszvaltas (teljesitve / nem jott el / lemondva), valamint az idoszak-blokkok
  listazasa es torlese is (korabban csak letrehozni lehetett oket).
- **Kezelo oldal**: ugyanaz a "jegy" design, statusz jelzovel, ket lepeses (megerositest kero) lemondassal.
- **Backend**: uj vegpontok a blokkolt idoszakok listazasahoz es torlesehez, valamint szures (statusz, datum,
  nev/elerhetoseg szerinti kereses) az admin foglalas-listahoz. A demo szolgaltatasokhoz mintaarak is
  bekerultek, hogy a design realisztikusan mutasson uj adatbazison is.

Ha `php artisan migrate --seed`-et mar korabban lefuttattad, futtasd ujra `php artisan migrate:fresh --seed`-et,
hogy a friss mintaarak es minden tabla biztosan meglegyen.
