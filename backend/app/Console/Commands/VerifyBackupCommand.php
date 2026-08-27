<?php

namespace App\Console\Commands;

use App\Services\ApplicationBackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyBackupCommand extends Command
{
    protected $signature = 'app:backup-verify {path? : Backup könyvtár; üresen a legfrissebb mentés}';

    protected $description = 'Backup manifest, SHA-256 és gzip integritás ellenőrzése.';

    public function handle(ApplicationBackupService $backup): int
    {
        try {
            $result = $backup->verify($this->argument('path') ?: null);
        } catch (Throwable $exception) {
            $this->error('Backup ellenőrzés sikertelen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup integritás: OK');
        $this->line('Hely: '.$result['path']);
        $this->line('Létrehozva: '.($result['created_at'] ?: 'ismeretlen'));
        $this->line('Médiafájlok: '.$result['media_files']);

        return self::SUCCESS;
    }
}
