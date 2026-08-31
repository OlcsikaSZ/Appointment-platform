# Aranyvonal Hair Studio – demo adatok

## Cél

A publikus értékesítési demóhoz egy életszerű, de teljesen fiktív fodrász-vállalkozás adatkészlete készüljön. A mintaadatok nem valódi ügyféladatok.

## Parancs

A repo `backend` mappájából:

```powershell
php artisan app:refresh-demo-data --business=default --force
```

A parancs a `default` slugú vállalkozást **Aranyvonal Hair Studio** demóra alakítja át.

## Mit módosít?

A kiválasztott vállalkozásnál újragenerálja:

- vállalkozás publikus adatait és arculati alapszínt;
- 5 fodrász szolgáltatást;
- hétfő–szombat munkaidőt;
- 8 fiktív ügyfélprofilt;
- 30 foglalást;
  - 20 teljesített;
  - 8 jövőbeli aktív;
  - 1 lemondott;
  - 1 no-show;
- 4 GYIK elemet;
- 5 egyértelműen mintaértékelésként jelölt véleményt;
- 1 jövőbeli blokkolt időt.

A foglalási dátumok a futtatás időpontjához igazodnak, így később újrafuttatva is friss demó készül.

## Mit NEM módosít?

- az admin felhasználót és jelszavát;
- az admin belépéshez tartozó beállításokat;
- a migrációkat;
- a jogi dokumentumok szövegét;
- valódi képfájlokat.

A szolgáltatásképeket külön kell feltölteni, miután a demó adatai rendben vannak.

## Biztonság

A parancs romboló művelet a kiválasztott vállalkozás demo adataira nézve: törli a korábbi foglalásokat, ügyfélprofilokat, szolgáltatásokat, értékeléseket és GYIK elemeket.

Production környezetben alapból le van tiltva. Dedikált demo példányon csak tudatosan engedélyezhető:

```env
DEMO_DATA_ALLOWED=true
```

Valódi ügyfél production példányán ez **mindig maradjon false**.

## Ellenőrzés futtatás után

```powershell
php artisan tinker
```

Majd például:

```php
App\Models\Business::where('slug', 'default')->first(['name', 'tagline', 'primary_color']);
App\Models\Service::where('business_id', 1)->count();
App\Models\Booking::where('business_id', 1)->count();
App\Models\CustomerProfile::where('business_id', 1)->count();
```

Elvárt darabszámok: 5 szolgáltatás, 30 foglalás, 8 ügyfélprofil.
