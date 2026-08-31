# Olcsi Business – értékesítési screenshotok

A `/bemutato` oldal az itt található optimalizált WebP képeket használja.

## Végleges fájlok

- `01-home.webp` – Aranyvonal Hair Studio főoldal / hero
- `02-services.webp` – szolgáltatásválasztás
- `03-booking.webp` – desktop időpontválasztás konkrét szabad időpontokkal
- `04-booking-mobile.webp` – mobil időpontválasztás
- `05-admin-calendar.webp` – admin havi naptár minta foglalásokkal
- `06-statistics.webp` – statisztikai nézet

Ezek a fájlok publikus webes assetek, ezért **Gitre és productionre is mehetnek**.

A nagyobb, nyers PNG forrásképek maradjanak a repository-n kívüli / gitignore-olt `marketing-source/screenshots/` könyvtárban.

## Videó

A bemutatóoldal videóhelye már elő van készítve. A videó elkészülte után a
`frontend/views/bemutato/index.php` fájlban a `$videoEmbedUrl` változóhoz kell
beilleszteni a YouTube embed URL-t. Üres értéknél a teljes videós szekció rejtve marad.

Példa:

```php
$videoEmbedUrl = 'https://www.youtube-nocookie.com/embed/VIDEO_ID';
```
