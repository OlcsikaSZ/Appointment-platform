<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('services', 'image_url')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->text('image_url')->nullable()->after('description');
            });
        }

        if (Schema::hasColumn('services', 'image_path')) {
            DB::statement("UPDATE services SET image_url = image_path WHERE image_url IS NULL");
        }
    }

    public function down(): void
    {
        // Nem töröljük automatikusan, hogy meglévő kép-adat ne vesszen el.
    }
};
