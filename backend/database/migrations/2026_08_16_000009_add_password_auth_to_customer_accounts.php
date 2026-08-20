<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('email');
            $table->string('role', 20)->default('user')->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('email_verified_at');
            $table->rememberToken();
        });

        Schema::create('customer_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('name', 120)->nullable();
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();
            $table->string('password_hash')->nullable();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['business_id', 'email', 'purpose'], 'verification_lookup');
        });

        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
        Schema::dropIfExists('customer_magic_links');
    }

    public function down(): void
    {
        Schema::create('customer_magic_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::dropIfExists('customer_verification_codes');
        Schema::table('customer_accounts', function (Blueprint $table): void {
            $table->dropColumn(['password', 'role', 'password_changed_at', 'remember_token']);
        });
    }
};
