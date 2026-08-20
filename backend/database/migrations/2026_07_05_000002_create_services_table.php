<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('Altalanos');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(10);
            $table->unsignedInteger('price_cents')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
