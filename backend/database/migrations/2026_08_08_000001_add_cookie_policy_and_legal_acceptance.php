<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('businesses', 'cookie_policy')) {
                $table->longText('cookie_policy')->nullable()->after('imprint_text');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'legal_accepted_at')) {
                $table->timestamp('legal_accepted_at')->nullable()->after('anonymized_at');
            }

            if (! Schema::hasColumn('bookings', 'legal_text_hash')) {
                $table->char('legal_text_hash', 64)->nullable()->after('legal_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['legal_text_hash', 'legal_accepted_at'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('businesses', function (Blueprint $table): void {
            if (Schema::hasColumn('businesses', 'cookie_policy')) {
                $table->dropColumn('cookie_policy');
            }
        });
    }
};
