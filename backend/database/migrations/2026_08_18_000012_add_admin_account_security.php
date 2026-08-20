<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pending_email')->nullable()->unique()->after('email');
            $table->timestamp('password_changed_at')->nullable()->after('email_verified_at');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('name');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        Schema::create('admin_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('email', 160);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'email', 'purpose'], 'admin_verification_lookup');
        });

        // A frissítés előtti adminok eddig ellenőrzés nélkül működtek. Ne zárjuk ki
        // őket az első migráció után; az új owner fiókok már aktiválatlanul jönnek létre.
        DB::table('users')
            ->whereIn('role', ['admin', 'owner'])
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_verification_codes');

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_pending_email_unique');
            $table->dropColumn(['pending_email', 'password_changed_at']);
        });
    }
};
