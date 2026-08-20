<?php

namespace App\Console\Commands;

use App\Models\AdminVerificationCode;
use App\Models\Business;
use App\Models\User;
use App\Rules\PersonName;
use App\Services\AdminVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateOwnerCommand extends Command
{
    protected $signature = 'app:create-owner
        {--business=default : A vállalkozás slugja vagy azonosítója}
        {--name= : A tulajdonos neve}
        {--email= : A tulajdonos e-mail-címe}';

    protected $description = 'Egyszeri, e-mailben aktiválható owner fiók létrehozása.';

    public function handle(AdminVerificationService $verification): int
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

        $name = trim((string) ($this->option('name') ?: $this->ask('Tulajdonos neve')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Tulajdonos e-mail-címe'))));
        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', new PersonName()],
                'email' => ['required', 'email:rfc', 'max:160'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $owner = User::query()
            ->where('business_id', $business->id)
            ->where('role', 'owner')
            ->first();

        if ($owner?->email_verified_at) {
            $this->error('Ehhez a vállalkozáshoz már tartozik aktív owner fiók.');

            return self::FAILURE;
        }

        if ($owner && $owner->email !== $email) {
            $this->error('Már van aktiválásra váró owner fiók másik e-mail-címmel.');

            return self::FAILURE;
        }

        $emailOwner = User::query()->where('email', $email)->first();
        if ($emailOwner && (! $owner || $emailOwner->id !== $owner->id)) {
            $this->error('Ez az e-mail-cím már másik adminfiókhoz tartozik.');

            return self::FAILURE;
        }

        $owner ??= User::query()->create([
            'business_id' => $business->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'role' => 'owner',
        ]);

        if ($owner->name !== $name) {
            $owner->update(['name' => $name]);
        }

        $minutes = $verification->issue(
            $owner->loadMissing('business'),
            AdminVerificationCode::PURPOSE_OWNER_ACTIVATION,
            $email,
        );

        $this->info("Az owner fiók aktiválókódját elküldtük a(z) {$email} címre.");
        $this->line("A kód {$minutes} percig érvényes. Ha adatbázis queue-t használsz, fusson a queue worker.");

        return self::SUCCESS;
    }
}
