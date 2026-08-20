<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('blocked_times', 'is_all_day')) {
            Schema::table('blocked_times', function (Blueprint $table): void {
                $table->boolean('is_all_day')->default(false)->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('blocked_times', 'is_all_day')) {
            Schema::table('blocked_times', function (Blueprint $table): void {
                $table->dropColumn('is_all_day');
            });
        }
    }
};
