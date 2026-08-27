<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\CustomerVerificationCode;
use App\Models\AdminVerificationCode;
use App\Services\DataRetentionService;
use App\Services\ImageOptimizationService;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('data:purge-retention', function (DataRetentionService $service): void {
    $result = $service->purgeAll();
    $this->info(sprintf(
        'Kész. Vállalkozások: %d, anonimizált foglalások: %d, törölt email logok: %d, lejáratott kezelőlinkek: %d.',
        $result['businesses'],
        $result['bookings_anonymized'],
        $result['email_logs_deleted'],
        $result['tokens_expired'],
    ));
})->purpose('Adatmegőrzési szabályok alkalmazása.');

Artisan::command('images:cleanup', function (ImageOptimizationService $service): void {
    $serviceReferences = Service::query()
        ->get(['image_url', 'image_thumbnail_url'])
        ->flatMap(fn (Service $item) => [$item->image_url, $item->image_thumbnail_url])
        ->all();
    $businessReferences = Business::query()
        ->get(['logo_path', 'logo_thumbnail_path'])
        ->flatMap(fn (Business $item) => [$item->logo_path, $item->logo_thumbnail_path])
        ->all();

    $serviceResult = $service->cleanupUnused('services', $serviceReferences);
    $businessResult = $service->cleanupUnused('businesses', $businessReferences);

    $this->info(sprintf(
        'Kész. Ellenőrzött képek: %d, törölt árva fájlok: %d, megtartott fájlok: %d.',
        $serviceResult['scanned'] + $businessResult['scanned'],
        $serviceResult['deleted'] + $businessResult['deleted'],
        $serviceResult['kept'] + $businessResult['kept'],
    ));
})->purpose('Nem hivatkozott, legalább 24 órás képfájlok törlése.');

Artisan::command('reminders:dispatch', function (ReminderService $service): void {
    $result = $service->dispatchDue();
    $this->info(sprintf(
        'Kész. Vállalkozások: %d, sorba állítva: %d, duplikációk: %d, kihagyva: %d.',
        $result['businesses'],
        $result['queued'],
        $result['duplicates'],
        $result['skipped'],
    ));
})->purpose('Esedékes 24 és 2 órás foglalási emlékeztetők sorba állítása.');

Schedule::command('app:backup')
    ->dailyAt('01:30')
    ->name('appointment-application-backup')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('backup.enabled'));

Schedule::call(fn () => app(DataRetentionService::class)->purgeAll())
    ->dailyAt('02:30')
    ->name('appointment-data-retention')
    ->withoutOverlapping();

Schedule::command('images:cleanup')
    ->weeklyOn(7, '03:10')
    ->name('appointment-image-cleanup')
    ->withoutOverlapping();

Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->name('appointment-reminders')
    ->withoutOverlapping();

Schedule::call(fn () => CustomerVerificationCode::query()->where('expires_at', '<', now())->delete())
    ->hourly()
    ->name('customer-verification-code-cleanup')
    ->withoutOverlapping();

Schedule::call(fn () => AdminVerificationCode::query()->where('expires_at', '<', now())->delete())
    ->hourly()
    ->name('admin-verification-code-cleanup')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:30')
    ->name('sanctum-expired-token-cleanup')
    ->withoutOverlapping();
