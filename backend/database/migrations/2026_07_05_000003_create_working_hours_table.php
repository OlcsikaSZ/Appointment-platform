<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');

            $table->index(['business_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
