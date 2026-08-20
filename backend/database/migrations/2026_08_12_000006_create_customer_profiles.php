<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'email']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'phone']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('customer_profile_id')
                ->nullable()
                ->after('business_id')
                ->constrained('customer_profiles')
                ->nullOnDelete();
        });

        DB::table('bookings')
            ->whereNull('anonymized_at')
            ->whereNotNull('customer_contact')
            ->orderBy('id')
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $email = mb_strtolower(trim((string) $booking->customer_contact));
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || str_contains($email, '@invalid.local')) {
                        continue;
                    }

                    $profileId = DB::table('customer_profiles')
                        ->where('business_id', $booking->business_id)
                        ->where('email', $email)
                        ->value('id');

                    if (! $profileId) {
                        $profileId = DB::table('customer_profiles')->insertGetId([
                            'business_id' => $booking->business_id,
                            'name' => $booking->customer_name,
                            'email' => $email,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('bookings')->where('id', $booking->id)->update([
                        'customer_profile_id' => $profileId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_profile_id');
        });
        Schema::dropIfExists('customer_profiles');
    }
};
