<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('businesses', 'min_advance_minutes')) {
                $table->unsignedInteger('min_advance_minutes')->default(60)->after('timezone');
            }
            if (! Schema::hasColumn('businesses', 'max_advance_days')) {
                $table->unsignedSmallInteger('max_advance_days')->default(90)->after('min_advance_minutes');
            }
            if (! Schema::hasColumn('businesses', 'slot_interval_minutes')) {
                $table->unsignedSmallInteger('slot_interval_minutes')->default(15)->after('max_advance_days');
            }
            if (! Schema::hasColumn('businesses', 'cancellation_deadline_minutes')) {
                $table->unsignedInteger('cancellation_deadline_minutes')->default(1440)->after('slot_interval_minutes');
            }
            if (! Schema::hasColumn('businesses', 'reschedule_deadline_minutes')) {
                $table->unsignedInteger('reschedule_deadline_minutes')->default(1440)->after('cancellation_deadline_minutes');
            }
            if (! Schema::hasColumn('businesses', 'hide_prices')) {
                $table->boolean('hide_prices')->default(false)->after('reschedule_deadline_minutes');
            }
            if (! Schema::hasColumn('businesses', 'booking_retention_days')) {
                $table->unsignedSmallInteger('booking_retention_days')->default(730)->after('hide_prices');
            }
            if (! Schema::hasColumn('businesses', 'email_log_retention_days')) {
                $table->unsignedSmallInteger('email_log_retention_days')->default(180)->after('booking_retention_days');
            }
            if (! Schema::hasColumn('businesses', 'manage_token_retention_days')) {
                $table->unsignedSmallInteger('manage_token_retention_days')->default(30)->after('email_log_retention_days');
            }
            if (! Schema::hasColumn('businesses', 'privacy_policy')) {
                $table->longText('privacy_policy')->nullable()->after('manage_token_retention_days');
            }
            if (! Schema::hasColumn('businesses', 'terms_text')) {
                $table->longText('terms_text')->nullable()->after('privacy_policy');
            }
            if (! Schema::hasColumn('businesses', 'imprint_text')) {
                $table->longText('imprint_text')->nullable()->after('terms_text');
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'price_mode')) {
                $table->string('price_mode', 32)->default('fixed')->after('price_cents');
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'manage_token_expires_at')) {
                $table->timestamp('manage_token_expires_at')->nullable()->after('manage_token');
                $table->index('manage_token_expires_at');
            }
            if (! Schema::hasColumn('bookings', 'anonymized_at')) {
                $table->timestamp('anonymized_at')->nullable()->after('cancelled_at');
            }
        });

        Schema::table('email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_logs', 'attempt_count')) {
                $table->unsignedSmallInteger('attempt_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('email_logs', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable()->after('attempt_count');
            }
            if (! Schema::hasColumn('email_logs', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table): void {
            foreach (['failed_at', 'last_attempt_at', 'attempt_count'] as $column) {
                if (Schema::hasColumn('email_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'manage_token_expires_at')) {
                $table->dropIndex(['manage_token_expires_at']);
                $table->dropColumn('manage_token_expires_at');
            }
            if (Schema::hasColumn('bookings', 'anonymized_at')) {
                $table->dropColumn('anonymized_at');
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'price_mode')) {
                $table->dropColumn('price_mode');
            }
        });

        Schema::table('businesses', function (Blueprint $table): void {
            foreach ([
                'imprint_text', 'terms_text', 'privacy_policy', 'manage_token_retention_days',
                'email_log_retention_days', 'booking_retention_days', 'hide_prices',
                'reschedule_deadline_minutes', 'cancellation_deadline_minutes',
                'slot_interval_minutes', 'max_advance_days', 'min_advance_minutes',
            ] as $column) {
                if (Schema::hasColumn('businesses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
