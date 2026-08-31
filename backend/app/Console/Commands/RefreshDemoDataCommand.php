<?php

namespace App\Console\Commands;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class RefreshDemoDataCommand extends Command
{
    protected $signature = 'app:refresh-demo-data
        {--business=default : A demo vállalkozás technikai slugja}
        {--force : Megerősítés kihagyása; productionben is kötelező}';

    protected $description = 'A kijelölt demo vállalkozás tartalmát friss, fodrász-specifikus mintaadatokra cseréli.';

    public function handle(): int
    {
        $slug = trim((string) $this->option('business'));

        $business = Business::query()->where('slug', $slug)->first();
        if (! $business) {
            $this->error("Nem található vállalkozás ezzel a sluggal: {$slug}");

            return self::FAILURE;
        }

        if (app()->environment('production') && ! config('appointment.demo_data_allowed', false)) {
            $this->error('Production környezetben a demo adatgenerálás le van tiltva. Csak dedikált demo példányon engedélyezd a DEMO_DATA_ALLOWED=true beállítással.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('FIGYELEM: a parancs törli és újragenerálja a kijelölt vállalkozás foglalásait, ügyfélprofiljait, szolgáltatásait, értékeléseit és GYIK tartalmait.');

            if (! $this->confirm("Biztosan frissíted a(z) {$business->name} [{$business->slug}] demo adatait?")) {
                $this->line('Megszakítva.');

                return self::SUCCESS;
            }
        }

        try {
            $summary = DB::transaction(fn () => $this->refresh($business));
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('A demo adatgenerálás sikertelen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Aranyvonal Hair Studio demo adatok elkészültek.');
        $this->table(
            ['Elem', 'Darab'],
            [
                ['Szolgáltatás', $summary['services']],
                ['Munkaidő-sor', $summary['working_hours']],
                ['Ügyfélprofil', $summary['customers']],
                ['Foglalás', $summary['bookings']],
                ['Jövőbeli aktív foglalás', $summary['future_bookings']],
                ['GYIK', $summary['faqs']],
                ['Mintaértékelés', $summary['reviews']],
                ['Blokkolt idő', $summary['blocked_times']],
            ]
        );
        $this->line('A demo e-mail-címek a fenntartott .example tartományt használják, ezért nem valós személyekhez tartoznak.');
        $this->line('Az Aranyvonal demo logója és szolgáltatásillusztrációi a verziózott frontend assetekből töltődnek be.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function refresh(Business $business): array
    {
        $businessId = (int) $business->id;

        // A jelenlegi demo adatokat tisztán eltávolítjuk. Az admin usert és a belépési
        // adatokat szándékosan nem érintjük, így a helyi admin login megmarad.
        DB::table('reminder_logs')->where('business_id', $businessId)->delete();
        DB::table('email_logs')->where('business_id', $businessId)->delete();
        DB::table('bookings')->where('business_id', $businessId)->delete();
        DB::table('customer_verification_codes')->where('business_id', $businessId)->delete();
        DB::table('customer_accounts')->where('business_id', $businessId)->delete();
        DB::table('customer_profiles')->where('business_id', $businessId)->delete();
        DB::table('blocked_times')->where('business_id', $businessId)->delete();
        DB::table('reviews')->where('business_id', $businessId)->delete();
        DB::table('faqs')->where('business_id', $businessId)->delete();
        DB::table('services')->where('business_id', $businessId)->delete();
        DB::table('working_hours')->where('business_id', $businessId)->delete();

        // Local környezetben a korábbi, már nem létező foglalásokhoz kötődő queue
        // munkák ne fussanak le véletlenül a demo újragenerálása után.
        if (app()->environment(['local', 'testing'])) {
            if (Schema::hasTable('jobs')) {
                DB::table('jobs')->delete();
            }
            if (Schema::hasTable('failed_jobs')) {
                DB::table('failed_jobs')->delete();
            }
        }

        $business->forceFill([
            'name' => 'Aranyvonal Hair Studio',
            'tagline' => 'Stílus, ami hozzád igazodik.',
            'hero_title' => 'A friss hajad egy foglalással kezdődik.',
            'hero_text' => 'Válaszd ki a szolgáltatásodat és foglalj időpontot néhány kattintással. Gyors, átlátható és kényelmes.',
            'about_title' => 'Az Aranyvonal élmény',
            'about_text' => 'Az Aranyvonal Hair Studio egy fiktív bemutató szalon, amely megmutatja, hogyan működhet egy saját arculatú online időpontfoglaló rendszer. A szalon, a vendégadatok és a vélemények kizárólag demonstrációs célú minták.',
            'phone' => '+36 30 000 0000',
            'email' => 'demo@aranyvonal.example',
            'address' => 'Budapest, Minta utca 12. (bemutató cím)',
            'opening_hours' => "Hétfő–Péntek: 09:00–18:00\nSzombat: 09:00–14:00\nVasárnap: zárva",
            'google_maps_url' => null,
            'timezone' => 'Europe/Budapest',
            'min_advance_minutes' => 60,
            'max_advance_days' => 60,
            'slot_interval_minutes' => 15,
            'cancellation_deadline_minutes' => 1440,
            'reschedule_deadline_minutes' => 1440,
            'reminder_24h_enabled' => true,
            'reminder_2h_enabled' => true,
            'hide_prices' => false,
            'primary_color' => '#B58A4A',
            'logo_text' => 'AH',
            'logo_path' => 'assets/brand/aranyvonal/logo.svg',
            'logo_thumbnail_path' => 'assets/brand/aranyvonal/logo.svg',
            'active' => true,
        ])->save();

        $services = $this->createServices($businessId);
        $workingHours = $this->createWorkingHours($businessId);
        $customers = $this->createCustomers($businessId);
        $bookings = $this->createBookings($business, $services, $customers);
        $faqs = $this->createFaqs($businessId);
        $reviews = $this->createReviews($businessId);
        $blockedTimes = $this->createBlockedTimes($businessId, $business->timezone);

        $futureBookings = DB::table('bookings')
            ->where('business_id', $businessId)
            ->where('status', 'booked')
            ->whereDate('date', '>=', now($business->timezone)->toDateString())
            ->count();

        return [
            'services' => count($services),
            'working_hours' => $workingHours,
            'customers' => count($customers),
            'bookings' => $bookings,
            'future_bookings' => $futureBookings,
            'faqs' => $faqs,
            'reviews' => $reviews,
            'blocked_times' => $blockedTimes,
        ];
    }

    /**
     * @return array<string, array{id:int,name:string,duration:int,buffer:int,price:int}>
     */
    private function createServices(int $businessId): array
    {
        $rows = [
            'male_cut' => ['Hajvágás', 'Férfi hajvágás', 'Precíz hajvágás konzultációval és befejező formázással.', 30, 10, 550000, 'assets/brand/aranyvonal/services/male-cut.svg'],
            'female_cut' => ['Hajvágás', 'Női hajvágás', 'Személyre szabott hajvágás, amely illeszkedik az arcformádhoz és a stílusodhoz.', 60, 10, 890000, 'assets/brand/aranyvonal/services/female-cut.svg'],
            'wash_dry' => ['Formázás', 'Hajmosás + szárítás', 'Frissítő hajmosás és tartós, alkalomhoz illő beszárítás.', 45, 10, 690000, 'assets/brand/aranyvonal/services/wash-dry.svg'],
            'coloring' => ['Festés', 'Hajfestés', 'Teljes hajfestés konzultációval és professzionális befejezéssel.', 120, 15, 1890000, 'assets/brand/aranyvonal/services/coloring.svg'],
            'occasion' => ['Formázás', 'Alkalmi frizura', 'Elegáns frizura esküvőre, rendezvényre vagy különleges alkalomra.', 75, 15, 1290000, 'assets/brand/aranyvonal/services/occasion.svg'],
        ];

        $services = [];
        $hasLegacyImagePath = Schema::hasColumn('services', 'image_path');

        foreach ($rows as $key => [$category, $name, $description, $duration, $buffer, $price, $imageUrl]) {
            $serviceRow = [
                'business_id' => $businessId,
                'category' => $category,
                'name' => $name,
                'description' => $description,
                'image_url' => $imageUrl,
                'image_thumbnail_url' => $imageUrl,
                'duration_minutes' => $duration,
                'buffer_minutes' => $buffer,
                'price_cents' => $price,
                'price_mode' => 'fixed',
                'active' => 1,
                'sort_order' => count($services),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Régebbi, már migrált adatbázisokban még létezhet a legacy image_path
            // oszlop, friss telepítésnél viszont már nincs. Mindkét sémát támogatjuk.
            if ($hasLegacyImagePath) {
                $serviceRow['image_path'] = null;
            }

            $id = (int) DB::table('services')->insertGetId($serviceRow);

            $services[$key] = compact('id', 'name', 'duration', 'buffer', 'price');
        }

        return $services;
    }

    private function createWorkingHours(int $businessId): int
    {
        $rows = [];
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $rows[] = [
                'business_id' => $businessId,
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ];
        }
        $rows[] = [
            'business_id' => $businessId,
            'weekday' => 6,
            'start_time' => '09:00:00',
            'end_time' => '14:00:00',
        ];

        DB::table('working_hours')->insert($rows);

        return count($rows);
    }

    /**
     * @return array<string, array{id:int,name:string,email:string,phone:string}>
     */
    private function createCustomers(int $businessId): array
    {
        $rows = [
            'anna' => ['Kovács Anna', 'anna.kovacs@demo.example', '+36 30 000 0101', 'Visszatérő vendég. A vállig érő fazont kedveli.'],
            'bence' => ['Molnár Bence', 'bence.molnar@demo.example', '+36 30 000 0102', 'Rendszeresen férfi hajvágást foglal.'],
            'reka' => ['Tóth Réka', 'reka.toth@demo.example', '+36 30 000 0103', null],
            'petra' => ['Varga Petra', 'petra.varga@demo.example', '+36 30 000 0104', 'Alkalmi frizuránál természetes hatást kér.'],
            'dora' => ['Kiss Dóra', 'dora.kiss@demo.example', '+36 30 000 0105', null],
            'mark' => ['Szabó Márk', 'mark.szabo@demo.example', '+36 30 000 0106', null],
            'eszter' => ['Nagy Eszter', 'eszter.nagy@demo.example', '+36 30 000 0107', 'Festés előtt rövid konzultáció javasolt.'],
            'lilla' => ['Horváth Lilla', 'lilla.horvath@demo.example', '+36 30 000 0108', null],
        ];

        $customers = [];
        foreach ($rows as $key => [$name, $email, $phone, $note]) {
            $id = (int) DB::table('customer_profiles')->insertGetId([
                'business_id' => $businessId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'admin_note' => $note,
                'created_at' => now()->subMonths(2),
                'updated_at' => now(),
            ]);

            $customers[$key] = compact('id', 'name', 'email', 'phone');
        }

        return $customers;
    }

    /**
     * @param array<string, array{id:int,name:string,duration:int,buffer:int,price:int}> $services
     * @param array<string, array{id:int,name:string,email:string,phone:string}> $customers
     */
    private function createBookings(Business $business, array $services, array $customers): int
    {
        $timezone = $business->timezone ?: 'Europe/Budapest';
        $thisMonday = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $weekMinusThree = $thisMonday->subWeeks(3);
        $weekMinusTwo = $thisMonday->subWeeks(2);
        $nextMonday = $thisMonday->addWeek();

        // 22 múltbeli időpont: 20 completed + 1 cancelled + 1 no_show.
        $past = [
            [0, '09:00', 'female_cut', 'anna', 'completed', 'Vállig érő fazon, könnyű rétegezéssel.'],
            [0, '10:30', 'male_cut', 'bence', 'completed', null],
            [0, '13:00', 'coloring', 'eszter', 'completed', 'Meleg barna árnyalat.'],
            [1, '09:30', 'wash_dry', 'reka', 'completed', null],
            [1, '11:00', 'female_cut', 'dora', 'completed', null],
            [1, '14:00', 'occasion', 'petra', 'completed', 'Laza hullámok.'],
            [2, '10:00', 'male_cut', 'mark', 'completed', null],
            [2, '11:00', 'wash_dry', 'lilla', 'completed', null],
            [2, '13:30', 'female_cut', 'anna', 'completed', null],
            [3, '09:00', 'coloring', 'eszter', 'completed', null],
            [3, '12:00', 'female_cut', 'reka', 'completed', null],
            [4, '10:00', 'occasion', 'petra', 'completed', null],
            [4, '12:00', 'male_cut', 'bence', 'completed', null],
            [4, '14:00', 'wash_dry', 'dora', 'completed', null],
            [5, '09:00', 'male_cut', 'mark', 'completed', null],
            [5, '10:00', 'female_cut', 'lilla', 'completed', null],
        ];

        $pastTwo = [
            [0, '09:00', 'female_cut', 'anna', 'completed', null],
            [0, '11:00', 'male_cut', 'bence', 'completed', null],
            [0, '13:00', 'coloring', 'eszter', 'completed', 'Lenövésfrissítés és fényesítés.'],
            [1, '09:30', 'wash_dry', 'reka', 'completed', null],
            [1, '11:00', 'female_cut', 'dora', 'cancelled', 'A vendég előre jelezte a lemondást.'],
            [2, '10:00', 'occasion', 'petra', 'no_show', 'Minta no-show a statisztika és ügyféltörténet bemutatásához.'],
        ];

        $future = [
            [0, '09:00', 'female_cut', 'anna', 'booked', 'Vállig érő frissítés.'],
            [0, '10:30', 'male_cut', 'bence', 'booked', null],
            [0, '13:00', 'coloring', 'eszter', 'booked', 'Természetes barna árnyalat.'],
            [1, '09:30', 'wash_dry', 'reka', 'booked', null],
            [1, '11:00', 'female_cut', 'dora', 'booked', null],
            [1, '14:00', 'occasion', 'petra', 'booked', 'Esküvői vendégfrizura.'],
            [2, '10:00', 'male_cut', 'mark', 'booked', null],
            [2, '13:30', 'female_cut', 'lilla', 'booked', null],
        ];

        $count = 0;
        foreach ($past as $row) {
            $this->insertBooking($business, $weekMinusThree, $row, $services, $customers);
            $count++;
        }
        foreach ($pastTwo as $row) {
            $this->insertBooking($business, $weekMinusTwo, $row, $services, $customers);
            $count++;
        }
        foreach ($future as $row) {
            $this->insertBooking($business, $nextMonday, $row, $services, $customers);
            $count++;
        }

        if ($count !== 30) {
            throw new RuntimeException("A demo foglalásszám eltért a várt 30-tól: {$count}");
        }

        return $count;
    }

    /**
     * @param array{0:int,1:string,2:string,3:string,4:string,5:?string} $definition
     * @param array<string, array{id:int,name:string,duration:int,buffer:int,price:int}> $services
     * @param array<string, array{id:int,name:string,email:string,phone:string}> $customers
     */
    private function insertBooking(
        Business $business,
        CarbonImmutable $weekStart,
        array $definition,
        array $services,
        array $customers
    ): void {
        [$dayOffset, $start, $serviceKey, $customerKey, $status, $note] = $definition;
        $service = $services[$serviceKey];
        $customer = $customers[$customerKey];
        $date = $weekStart->addDays($dayOffset);
        $startAt = CarbonImmutable::parse($date->toDateString().' '.$start, $business->timezone);
        $endAt = $startAt->addMinutes($service['duration']);
        $busyUntil = $endAt->addMinutes($service['buffer']);
        $createdAt = $startAt->subDays(4)->setTime(10, 0);
        $isActive = $status === 'booked';

        DB::table('bookings')->insert([
            'business_id' => $business->id,
            'customer_profile_id' => $customer['id'],
            'customer_account_id' => null,
            'service_id' => $service['id'],
            'service_name' => $service['name'],
            'price_cents_snapshot' => $service['price'],
            'price_mode_snapshot' => 'fixed',
            'date' => $date->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'busy_until' => $busyUntil->format('H:i:s'),
            'customer_name' => $customer['name'],
            'customer_contact' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'customer_note' => $note,
            'manage_token' => Str::random(64),
            'manage_token_expires_at' => $isActive ? $date->endOfDay()->addDays(30)->utc() : null,
            'status' => $status,
            'active_slot_key' => $isActive ? $business->id.'|'.$date->toDateString().'|'.$startAt->format('H:i') : null,
            'cancelled_at' => $status === 'cancelled' ? $startAt->subDays(2)->utc() : null,
            'anonymized_at' => null,
            'legal_accepted_at' => $createdAt->utc(),
            'legal_text_hash' => hash('sha256', 'aranyvonal-demo-legal-v1'),
            'created_at' => $createdAt->utc(),
            'updated_at' => now('UTC'),
        ]);
    }

    private function createFaqs(int $businessId): int
    {
        $rows = [
            ['Hogyan tudok időpontot foglalni?', 'Válaszd ki a szolgáltatást, a megfelelő napot és egy szabad időpontot, majd add meg az elérhetőségeidet.'],
            ['Módosíthatom az időpontomat?', 'Igen. A foglalás után kapott egyedi kezelőlinken a határidőig új szabad időpontot választhatsz.'],
            ['Lemondhatom a foglalást?', 'Igen. A kezelőlinken a megadott lemondási határidőig online is lemondhatod az időpontot.'],
            ['Kapok emlékeztetőt?', 'Igen. A bemutató rendszer 24 órás és opcionális 2 órás e-mailes emlékeztetőt is tud küldeni.'],
        ];

        foreach ($rows as $sort => [$question, $answer]) {
            DB::table('faqs')->insert([
                'business_id' => $businessId,
                'question' => $question,
                'answer' => $answer,
                'active' => 1,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return count($rows);
    }

    private function createReviews(int $businessId): int
    {
        $rows = [
            ['Kovács Anna', 5, 'Gyors volt a foglalás, és telefonról is rögtön megtaláltam a megfelelő időpontot. (Mintaértékelés)'],
            ['Molnár Bence', 5, 'Átlátható a folyamat, külön jó, hogy a foglalást később is könnyű kezelni. (Mintaértékelés)'],
            ['Tóth Réka', 5, 'Pár kattintással megvolt az időpont, nem kellett üzenetekben egyeztetni. (Mintaértékelés)'],
            ['Varga Petra', 4, 'Egyszerű és mobilon is kényelmes foglalási felület. (Mintaértékelés)'],
            ['Nagy Eszter', 5, 'Az automatikus visszaigazolás és emlékeztető nagyon praktikus. (Mintaértékelés)'],
        ];

        foreach ($rows as $sort => [$author, $rating, $text]) {
            DB::table('reviews')->insert([
                'business_id' => $businessId,
                'author' => $author,
                'rating' => $rating,
                'source' => 'manual',
                'moderation_status' => 'approved',
                'submitter_email' => null,
                'submitted_at' => null,
                'legal_accepted_at' => null,
                'text' => $text,
                'active' => 1,
                'sort_order' => $sort,
                'created_at' => now()->subDays(10 - $sort),
                'updated_at' => now(),
            ]);
        }

        return count($rows);
    }

    private function createBlockedTimes(int $businessId, string $timezone): int
    {
        $nextMonday = CarbonImmutable::now($timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->addWeek();

        DB::table('blocked_times')->insert([
            'business_id' => $businessId,
            'date' => $nextMonday->addDays(3)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'reason' => 'Ebédszünet (minta)',
            'is_all_day' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }
}
