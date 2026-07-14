<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_settings')) {
            Schema::create('email_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
                $table->longText('settings')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('email_logs')) {
            if (! Schema::hasColumn('email_logs', 'payload')) {
                Schema::table('email_logs', function (Blueprint $table): void {
                    $table->longText('payload')->nullable()->after('error_message');
                });
            }

            if (! Schema::hasColumn('email_logs', 'resent_from_id')) {
                Schema::table('email_logs', function (Blueprint $table): void {
                    $table->unsignedBigInteger('resent_from_id')->nullable()->after('id');
                    $table->index('resent_from_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_logs')) {
            if (Schema::hasColumn('email_logs', 'resent_from_id')) {
                Schema::table('email_logs', function (Blueprint $table): void {
                    $table->dropIndex(['resent_from_id']);
                    $table->dropColumn('resent_from_id');
                });
            }

            if (Schema::hasColumn('email_logs', 'payload')) {
                Schema::table('email_logs', function (Blueprint $table): void {
                    $table->dropColumn('payload');
                });
            }
        }

        Schema::dropIfExists('email_settings');
    }
};
