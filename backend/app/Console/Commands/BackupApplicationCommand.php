<?php

namespace App\Console\Commands;

use App\Services\ApplicationBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupApplicationCommand extends Command
{
    protected $signature = 'app:backup
        {--force : Futtatás akkor is, ha BACKUP_ENABLED=false}
        {--database-only : Kizárólag az adatbázis mentése}
        {--no-prune : A retention takarítás kihagyása}
        {--path= : Egyszeri célkönyvtár felülbírálás}';

    protected $description = 'Hordozható production mentés készítése MySQL/MariaDB adatbázisról és feltöltött képekről.';

    public function handle(ApplicationBackupService $backup): int
    {
        if (! (bool) config('backup.enabled') && ! $this->option('force')) {
            $this->warn('A backup ki van kapcsolva. Állítsd BACKUP_ENABLED=true értékre, vagy használd a --force opciót.');

            return self::FAILURE;
        }

        try {
            $result = $backup->create(
                rootPath: $this->option('path') ? (string) $this->option('path') : null,
                includeMedia: ! (bool) $this->option('database-only'),
                prune: ! (bool) $this->option('no-prune'),
            );
        } catch (Throwable $exception) {
            $this->error('Backup sikertelen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup elkészült és integritásellenőrzése sikeres.');
        $this->line('Hely: '.$result['path']);
        $this->line('Adatbázis: '.$this->humanBytes((int) $result['database_bytes']));
        $this->line(sprintf(
            'Média: %d fájl, %s',
            (int) $result['media_files'],
            $this->humanBytes((int) $result['media_bytes']),
        ));
        if ($result['deleted']) {
            $this->line('Retention miatt törölt régi mentések: '.count($result['deleted']));
        }

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KiB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', ' ').' MiB';
    }
}
