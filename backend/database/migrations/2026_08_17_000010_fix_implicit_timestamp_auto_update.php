<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A MariaDB 10.4 (peldaul a XAMPP csomagban) automatikusan
        // DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP szabalyokat
        // tehet a tabla elso, nem nullable TIMESTAMP oszlopara. Ezek az
        // oszlopok idopontot tarolnak, de soha nem frissulhetnek automatikusan.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('customer_verification_codes')
            && Schema::hasColumn('customer_verification_codes', 'expires_at')) {
            DB::statement(
                'ALTER TABLE customer_verification_codes MODIFY expires_at DATETIME NOT NULL'
            );
        }

        if (Schema::hasTable('reminder_logs')
            && Schema::hasColumn('reminder_logs', 'scheduled_for')) {
            DB::statement(
                'ALTER TABLE reminder_logs MODIFY scheduled_for DATETIME NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('customer_verification_codes')
            && Schema::hasColumn('customer_verification_codes', 'expires_at')) {
            DB::statement(
                "ALTER TABLE customer_verification_codes MODIFY expires_at TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:01'"
            );
        }

        if (Schema::hasTable('reminder_logs')
            && Schema::hasColumn('reminder_logs', 'scheduled_for')) {
            DB::statement(
                "ALTER TABLE reminder_logs MODIFY scheduled_for TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:01'"
            );
        }
    }
};
