<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_times', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_times');
    }
};
