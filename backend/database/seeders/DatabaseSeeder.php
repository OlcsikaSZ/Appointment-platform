<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Faq;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $businessEmail = trim((string) config('appointment.business_seed_email', ''));

        if (! filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'A BUSINESS_SEED_EMAIL nincs beállítva vagy nem érvényes e-mail-cím. Töltsd ki a backend/.env fájlban, majd futtasd újra a seedelést.'
            );
        }

        $business = Business::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Aranyvonal Stúdió',
                'tagline' => 'Személyre szabott szolgáltatások, egyszerű online foglalással.',
                'hero_title' => 'Egyszerű foglalás. Megbízható szolgáltatás.',
                'hero_text' => 'Válaszd ki a neked megfelelő szolgáltatást és időpontot néhány kattintással. Gyors, átlátható és kényelmes.',
                'about_title' => 'Rólunk',
                'about_text' => 'Fontos számunkra, hogy már az első kattintástól egyszerű és átlátható legyen az ügyintézés. Válassz szolgáltatást, foglalj szabad időpontot, és mi gondoskodunk a többiről.',
                'phone' => '+36 30 123 4567',
                'email' => $businessEmail,
                'address' => '3525 Miskolc, Széchenyi utca 12.',
                'opening_hours' => "Hétfő–Péntek: 09:00–17:00\nSzombat: 09:00–13:00\nVasárnap: zárva",
                'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=Miskolc',
                'timezone' => 'Europe/Budapest',
                'primary_color' => '#107a5b',
                'logo_text' => 'AS',
            ]
        );

        if (blank($business->email)) {
            $business->forceFill(['email' => $businessEmail])->save();
        }

        $services = [
            ['Általános', 'Konzultáció', 'Rövid személyes vagy online egyeztetés.', 30, 10, 800000],
            ['Szolgáltatás', 'Alap szolgáltatás', 'Általános, később testreszabható szolgáltatás.', 45, 10, 1200000],
            ['Szolgáltatás', 'Hosszabb szolgáltatás', 'Nagyobb időkeretet igénylő foglalás.', 90, 15, 2200000],
            ['Helyszíni', 'Helyszíni időpont', 'Ügyfélnél vagy külső helyszínen végzett munka.', 60, 20, 1800000],
        ];

        foreach ($services as $index => [$category, $name, $description, $duration, $buffer, $priceCents]) {
            Service::firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'category' => $category,
                    'description' => $description,
                    'duration_minutes' => $duration,
                    'buffer_minutes' => $buffer,
                    'price_cents' => $priceCents,
                    'sort_order' => $index,
                ]
            );
        }

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WorkingHour::firstOrCreate([
                'business_id' => $business->id,
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        WorkingHour::firstOrCreate([
            'business_id' => $business->id,
            'weekday' => 6,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        if (! app()->environment('production')) {
            $reviews = [
                ['Minta vélemény', 'Ez egy helykitöltő vendégvélemény. Az admin felületen saját, valódi értékelésre cserélhető.', 5, 1],
                ['Minta vendég', 'Gyors, átlátható foglalás és kellemes ügyfélélmény – ezt a szöveget is szabadon módosíthatod.', 5, 2],
            ];

            foreach ($reviews as [$author, $text, $rating, $sortOrder]) {
                Review::firstOrCreate(
                    ['business_id' => $business->id, 'author' => $author, 'text' => $text],
                    ['rating' => $rating, 'active' => true, 'sort_order' => $sortOrder]
                );
            }
        }

        $faqs = [
            ['Hogyan tudok időpontot foglalni?', 'Válassz szolgáltatást, dátumot és szabad időpontot, majd add meg az elérhetőségeidet.'],
            ['Módosíthatom vagy lemondhatom a foglalásomat?', 'Igen. A foglalás után kapott egyedi kezelőlinken módosíthatod vagy lemondhatod az időpontot.'],
            ['Hol találom a pontos elérhetőségeket?', 'A kapcsolat szekcióban megtalálod a telefonszámot, e-mail címet, címet és nyitvatartást.'],
        ];

        foreach ($faqs as $index => [$question, $answer]) {
            Faq::firstOrCreate(
                ['business_id' => $business->id, 'question' => $question],
                ['answer' => $answer, 'active' => true, 'sort_order' => $index + 1]
            );
        }

        $adminEmail = (string) config('appointment.admin_seed_email', 'admin@example.test');
        $adminPassword = (string) config('appointment.admin_seed_password', '');

        if (app()->environment(['local', 'testing']) && $adminPassword !== '') {
            User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Admin',
                    'business_id' => $business->id,
                    'role' => 'admin',
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ]
            );
        } elseif (app()->environment(['local', 'testing']) && $this->command) {
            $this->command->warn('Nem készült fejlesztői admin: az ADMIN_SEED_PASSWORD nincs beállítva.');
        } elseif ($this->command) {
            $this->command->warn('Éles környezetben a seeder nem készít adminfiókot. Használd: php artisan app:create-owner');
        }
    }
}
