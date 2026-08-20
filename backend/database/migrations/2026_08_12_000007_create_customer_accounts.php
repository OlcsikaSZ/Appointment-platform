<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'email']);
        });

        Schema::create('customer_magic_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')
                ->nullable()
                ->after('customer_profile_id')
                ->constrained('customer_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_account_id');
        });
        Schema::dropIfExists('customer_magic_links');
        Schema::dropIfExists('customer_accounts');
    }
};
