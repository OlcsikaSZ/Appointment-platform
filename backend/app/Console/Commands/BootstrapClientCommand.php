<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\WorkingHour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BootstrapClientCommand extends Command
{
    protected $signature = 'app:bootstrap-client
        {--slug=default : A vállalkozás technikai slugja}
        {--name= : Vállalkozás neve}
        {--email= : Kapcsolati/admin értesítési e-mail}
        {--timezone=Europe/Budapest : IANA időzóna}
        {--work-start=09:00 : Alap hétköznapi nyitás}
        {--work-end=17:00 : Alap hétköznapi zárás}
        {--no-working-hours : Ne hozzon létre alap H-P munkaidőt}';

    protected $description = 'Tiszta production adatbázisban minimális ügyfél-vállalkozás létrehozása mintaadatok nélkül.';

    public function handle(): int
    {
        $slug = Str::slug(trim((string) $this->option('slug')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Vállalkozás neve')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Kapcsolati e-mail'))));
        $timezone = trim((string) $this->option('timezone'));
        $workStart = trim((string) $this->option('work-start'));
        $workEnd = trim((string) $this->option('work-end'));

        $validator = Validator::make(compact('slug', 'name', 'email', 'timezone', 'workStart', 'workEnd'), [
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'timezone' => ['required', 'timezone'],
            'workStart' => ['required', 'date_format:H:i'],
            'workEnd' => ['required', 'date_format:H:i', 'after:workStart'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (Business::query()->where('slug', $slug)->exists()) {
            $this->error("A(z) {$slug} sluggal már létezik vállalkozás. A bootstrap parancs szándékosan nem ír felül meglévő production adatot.");

            return self::FAILURE;
        }

        $logoText = collect(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY))
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $logoText = $logoText !== '' ? $logoText : 'IP';

        $business = DB::transaction(function () use ($slug, $name, $email, $timezone, $workStart, $workEnd, $logoText): Business {
            $business = Business::query()->create([
                'name' => $name,
                'slug' => $slug,
                'email' => $email,
                'timezone' => $timezone,
                'logo_text' => $logoText,
                'active' => true,
            ]);

            if (! $this->option('no-working-hours')) {
                foreach ([1, 2, 3, 4, 5] as $weekday) {
                    WorkingHour::query()->create([
                        'business_id' => $business->id,
                        'weekday' => $weekday,
                        'start_time' => $workStart,
                        'end_time' => $workEnd,
                    ]);
                }
            }

            return $business;
        });

        $this->info("Vállalkozás létrehozva: {$business->name} [{$business->slug}]");
        $this->line('Nincs demo szolgáltatás, review vagy mintaadmin létrehozva.');
        $this->newLine();
        $this->line('Következő lépés az owner létrehozása:');
        $this->line("php artisan app:create-owner --business={$business->slug} --name=\"Vevő neve\" --email=\"{$email}\"");

        return self::SUCCESS;
    }
}
