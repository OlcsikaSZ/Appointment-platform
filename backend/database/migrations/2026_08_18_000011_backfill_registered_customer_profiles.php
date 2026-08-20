<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_accounts')
            ->whereNotNull('email_verified_at')
            ->orderBy('id')
            ->chunkById(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $profile = DB::table('customer_profiles')
                        ->where('business_id', $account->business_id)
                        ->where('email', $account->email);

                    if ($profile->exists()) {
                        $profile->update([
                            'name' => $account->name,
                            'phone' => $account->phone,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('customer_profiles')->insert([
                            'business_id' => $account->business_id,
                            'name' => $account->name,
                            'email' => $account->email,
                            'phone' => $account->phone,
                            'created_at' => $account->created_at ?? now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // A profilok foglalási előzményekhez is kapcsolódhatnak, ezért visszafelé nem törlünk adatot.
    }
};
