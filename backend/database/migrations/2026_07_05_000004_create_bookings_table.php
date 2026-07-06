<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('busy_until');
            $table->string('customer_name');
            $table->string('customer_contact');
            $table->text('customer_note')->nullable();
            $table->string('manage_token', 80)->unique();
            $table->string('status', 32)->default('booked');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'date', 'status']);
            $table->index(['service_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
