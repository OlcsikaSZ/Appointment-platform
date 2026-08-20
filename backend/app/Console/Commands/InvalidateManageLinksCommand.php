<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Business;
use Illuminate\Console\Command;

class InvalidateManageLinksCommand extends Command
{
    protected $signature = 'app:invalidate-manage-links
        {--business=default : A vállalkozás slugja vagy azonosítója}
        {--force : Megerősítő kérdés kihagyása}';

    protected $description = 'A vállalkozás még érvényes foglaláskezelő linkjeinek azonnali lejáratása.';

    public function handle(): int
    {
        $businessKey = trim((string) $this->option('business'));
        $business = Business::query()
            ->where('slug', $businessKey)
            ->when(ctype_digit($businessKey), fn ($query) => $query->orWhereKey((int) $businessKey))
            ->first();

        if (! $business) {
            $this->error('A megadott vállalkozás nem található.');

            return self::FAILURE;
        }

        $query = Booking::query()
            ->where('business_id', $business->id)
            ->where(function ($query): void {
                $query->whereNull('manage_token_expires_at')
                    ->orWhere('manage_token_expires_at', '>', now());
            });

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Nincs érvényes foglaláskezelő link.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Biztosan lejáratod a(z) {$business->name} mind a(z) {$count} kezelőlinkjét?"
        )) {
            $this->warn('A művelet megszakítva.');

            return self::FAILURE;
        }

        $expired = $query->update(['manage_token_expires_at' => now()->subSecond()]);
        $this->info("Lejáratott foglaláskezelő linkek: {$expired}.");

        return self::SUCCESS;
    }
}
