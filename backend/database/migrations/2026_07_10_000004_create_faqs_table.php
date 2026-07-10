<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('question');
                $table->text('answer');
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['business_id', 'active', 'sort_order']);
            });
        }

        $businessId = DB::table('businesses')->where('slug', 'default')->value('id');

        if ($businessId && DB::table('faqs')->count() === 0) {
            DB::table('faqs')->insert([
                [
                    'business_id' => $businessId,
                    'question' => 'Hogyan tudok időpontot foglalni?',
                    'answer' => 'Válassz szolgáltatást, dátumot és szabad időpontot, majd add meg az elérhetőségeidet.',
                    'active' => true,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'business_id' => $businessId,
                    'question' => 'Módosíthatom vagy lemondhatom a foglalásomat?',
                    'answer' => 'Igen. A foglalás után kapott egyedi kezelőlinken módosíthatod vagy lemondhatod az időpontot.',
                    'active' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'business_id' => $businessId,
                    'question' => 'Hol találom a pontos elérhetőségeket?',
                    'answer' => 'A kapcsolat szekcióban megtalálod a telefonszámot, e-mail címet, címet és nyitvatartást.',
                    'active' => true,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Meglévő faqs táblát nem törlünk, hogy korábbi GYIK-adatok ne vesszenek el.
    }
};
