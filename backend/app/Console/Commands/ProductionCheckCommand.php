<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\ApplicationBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check
        {--business=default : Ellenőrzött vállalkozás slugja vagy azonosítója}
        {--strict : A tartalmi/üzemeltetési figyelmeztetések is hibát jelentsenek}';

    protected $description = 'Új ügyfél átadása előtti production readiness ellenőrzés titkok kiírása nélkül.';

    public function handle(ApplicationBackupService $backup): int
    {
        $errors = 0;
        $warnings = 0;
        $strict = (bool) $this->option('strict');

        $check = function (bool $ok, string $label, string $failure, bool $warningOnly = false) use (&$errors, &$warnings, $strict): void {
            if ($ok) {
                $this->components->info($label);
                return;
            }

            if ($warningOnly && ! $strict) {
                $warnings++;
                $this->components->warn($label.' — '.$failure);
                return;
            }

            $errors++;
            $this->components->error($label.' — '.$failure);
        };

        $this->info('Production readiness ellenőrzés');

        $check(app()->environment('production'), 'APP_ENV=production', 'Az alkalmazás nem production környezetben fut.');
        $check(config('app.debug') === false, 'APP_DEBUG=false', 'Élesben kapcsold ki a debug módot.');
        $check(trim((string) config('app.key')) !== '', 'APP_KEY beállítva', 'Hiányzik az APP_KEY.');

        foreach (['app.url' => 'APP_URL', 'appointment.public_url' => 'PUBLIC_APP_URL'] as $configKey => $label) {
            $url = trim((string) config($configKey));
            $check(str_starts_with($url, 'https://'), "{$label} HTTPS", 'A végleges URL-nek HTTPS címet kell használnia.');
        }

        $check((string) config('queue.default') === 'database', 'QUEUE_CONNECTION=database', 'Productionben a database queue az elvárt konfiguráció.');
        $check((string) config('mail.default') === 'smtp', 'MAIL_MAILER=smtp', 'Productionben valós SMTP mailer szükséges.');

        try {
            DB::connection()->getPdo();
            $check(true, 'Adatbázis kapcsolat', '');
        } catch (Throwable $exception) {
            $check(false, 'Adatbázis kapcsolat', $exception->getMessage());
        }

        $pendingMigrations = $this->pendingMigrations();
        $check($pendingMigrations === [], 'Migrációk naprakészek', $pendingMigrations ? 'Hiányzik: '.implode(', ', $pendingMigrations) : '');

        $businessKey = trim((string) $this->option('business'));
        $business = Business::query()
            ->where('slug', $businessKey)
            ->when(ctype_digit($businessKey), fn ($query) => $query->orWhereKey((int) $businessKey))
            ->first();
        $check((bool) $business, 'Vállalkozás rekord', 'A megadott business nem található.');

        if ($business) {
            $check($business->active, 'Vállalkozás aktív', 'A vállalkozás inaktív.');
            $check($business->workingHours()->exists(), 'Munkaidő beállítva', 'Nincs working_hours rekord.', true);
            $check($business->services()->where('active', true)->exists(), 'Legalább egy aktív szolgáltatás', 'Nincs foglalható szolgáltatás.', true);

            $ownerExists = User::query()
                ->where('business_id', $business->id)
                ->where('role', 'owner')
                ->whereNotNull('email_verified_at')
                ->exists();
            $check($ownerExists, 'Aktivált owner fiók', 'Nincs igazolt owner.');

            foreach ([
                'privacy_policy' => 'Adatkezelési tájékoztató',
                'terms_text' => 'Felhasználási/foglalási feltételek',
                'imprint_text' => 'Impresszum',
                'cookie_policy' => 'Süti/technikai tárolás tájékoztató',
            ] as $field => $label) {
                $check(filled($business->{$field}), $label, 'Nincs végleges tartalom.', true);
            }
        }

        $check(is_writable(storage_path()), 'Laravel storage írható', storage_path().' nem írható.');
        $check(is_writable(base_path('bootstrap/cache')), 'bootstrap/cache írható', base_path('bootstrap/cache').' nem írható.');

        $backupEnabled = (bool) config('backup.enabled');
        $check($backupEnabled, 'Automatikus backup engedélyezve', 'BACKUP_ENABLED=false.', true);
        if ($backupEnabled) {
            $path = (string) config('backup.path');
            $check($path !== '' && (is_dir($path) ? is_writable($path) : is_writable(dirname($path))), 'Backup cél elérhető', 'A BACKUP_PATH nem írható.', true);

            try {
                $latest = $backup->latestBackupPath();
                $check((bool) $latest, 'Legalább egy backup létezik', 'Még nincs kész mentés.', true);
                if ($latest) {
                    $backup->verify($latest);
                    $check(true, 'Legfrissebb backup integritás', '');
                    $ageHours = (time() - (int) filemtime($latest)) / 3600;
                    $check($ageHours <= 36, 'Legfrissebb backup friss', sprintf('A legfrissebb backup %.1f órás.', $ageHours), true);
                }
            } catch (Throwable $exception) {
                $check(false, 'Legfrissebb backup integritás', $exception->getMessage(), true);
            }
        }

        $this->newLine();
        if ($errors > 0) {
            $this->error("NO-GO: {$errors} hiba, {$warnings} figyelmeztetés.");
            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn("GO technikailag, de {$warnings} figyelmeztetés maradt. Átadás előtt nézd át őket.");
        } else {
            $this->info('GO: minden ellenőrzött production feltétel rendben.');
        }

        return self::SUCCESS;
    }

    private function pendingMigrations(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('migrations')) {
            return ['migrations tábla hiányzik'];
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->all();

        return array_values(array_diff($files, $ran));
    }
}
