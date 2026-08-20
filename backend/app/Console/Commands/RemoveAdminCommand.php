<?php

namespace App\Console\Commands;

use App\Mail\AdminSecurityNotificationMail;
use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RemoveAdminCommand extends Command
{
    protected $signature = 'app:remove-admin
        {--business=default : A vállalkozás slugja vagy azonosítója}
        {--email= : A megszüntetendő adminfiók e-mail-címe}
        {--force : Megerősítő kérdés kihagyása}';

    protected $description = 'Régi adminfiók biztonságos eltávolítása egy igazolt owner megléte után.';

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

        $owner = User::query()->where('business_id', $business->id)
            ->where('role', 'owner')->whereNotNull('email_verified_at')->first();
        if (! $owner) {
            $this->error('Előbb aktiválj legalább egy igazolt owner fiókot.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Eltávolítandó admin e-mail-címe'))));
        $admin = User::query()->where('business_id', $business->id)
            ->where('email', $email)->where('role', 'admin')->first();
        if (! $admin) {
            $this->error('Ehhez a vállalkozáshoz nem található ilyen adminfiók. Owner ezzel a paranccsal nem törölhető.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Biztosan eltávolítod a(z) {$email} adminfiókot?")) {
            $this->warn('A művelet megszakítva.');

            return self::FAILURE;
        }

        $adminName = $admin->name;
        DB::transaction(function () use ($admin): void {
            $admin->tokens()->delete();
            $admin->delete();
        });

        Mail::to($email)->queue(new AdminSecurityNotificationMail(
            $business,
            'Adminfiók megszüntetve',
            ['A vállalkozáshoz tartozó adminhozzáférésedet visszavonták.'],
        ));
        Mail::to($owner->email)->queue(new AdminSecurityNotificationMail(
            $business,
            'Adminfiók eltávolítva',
            ["Eltávolított fiók: {$adminName} ({$email})"],
        ));

        $this->info("A(z) {$email} adminfiók és minden munkamenete megszűnt.");

        return self::SUCCESS;
    }
}
