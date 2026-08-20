<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->boolean('reminder_24h_enabled')->default(true)->after('reschedule_deadline_minutes');
            $table->boolean('reminder_2h_enabled')->default(false)->after('reminder_24h_enabled');
        });

        Schema::create('reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_log_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->string('reminder_type', 24);
            $table->string('status', 24)->default('queued');
            $table->dateTime('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'reminder_type']);
            $table->index(['business_id', 'status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['reminder_24h_enabled', 'reminder_2h_enabled']);
        });
    }
};
