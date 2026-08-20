<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', 'author_name')) {
            return;
        }

        if (! Schema::hasColumn('reviews', 'author')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->string('author', 120)->nullable()->after('business_id');
            });
        }

        DB::table('reviews')
            ->where(function ($query): void {
                $query->whereNull('author')->orWhere('author', '');
            })
            ->update(['author' => DB::raw('author_name')]);

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('author_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews') || Schema::hasColumn('reviews', 'author_name')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('author_name', 120)->nullable()->after('author');
        });

        DB::table('reviews')->update(['author_name' => DB::raw('author')]);
    }
};
