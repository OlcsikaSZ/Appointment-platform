<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('author', 120);
                $table->text('text');
                $table->unsignedTinyInteger('rating')->default(5);
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['business_id', 'active', 'sort_order']);
            });
        } else {
            if (!Schema::hasColumn('reviews', 'author')) {
                Schema::table('reviews', function (Blueprint $table): void {
                    $table->string('author', 120)->nullable()->after('business_id');
                });
            }

            if (Schema::hasColumn('reviews', 'author_name')) {
                DB::statement("UPDATE reviews SET author = author_name WHERE author IS NULL");
            }
        }

        $businessId = DB::table('businesses')->where('slug', 'default')->value('id');

        if ($businessId && DB::table('reviews')->count() === 0) {
            DB::table('reviews')->insert([
                [
                    'business_id' => $businessId,
                    'author' => 'Minta vélemény',
                    'text' => 'Ez egy helykitöltő vendégvélemény. Az admin felületen saját, valódi értékelésre cserélhető.',
                    'rating' => 5,
                    'active' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'business_id' => $businessId,
                    'author' => 'Minta vendég',
                    'text' => 'Gyors, átlátható foglalás és kellemes ügyfélélmény – ezt a szöveget is szabadon módosíthatod.',
                    'rating' => 5,
                    'active' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Meglévő reviews táblát nem törlünk, hogy korábbi értékelések ne vesszenek el.
    }
};
