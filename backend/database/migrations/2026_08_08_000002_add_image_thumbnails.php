<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'image_thumbnail_url')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->text('image_thumbnail_url')->nullable()->after('image_url');
            });
        }

        if (! Schema::hasColumn('businesses', 'logo_thumbnail_path')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('logo_thumbnail_path')->nullable()->after('logo_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'image_thumbnail_url')) {
            Schema::table('services', fn (Blueprint $table) => $table->dropColumn('image_thumbnail_url'));
        }

        if (Schema::hasColumn('businesses', 'logo_thumbnail_path')) {
            Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('logo_thumbnail_path'));
        }
    }
};
