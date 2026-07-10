<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ez a migráció szándékosan idempotens: kezeli azt is,
        // ha a korábbi 2026_07_09-es storefront migráció már létrehozott mezőket,
        // illetve azt is, ha az előző futás félbeszakadt.

        if (!Schema::hasColumn('businesses', 'hero_title')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('hero_title')->nullable()->after('tagline');
            });
        }

        if (!Schema::hasColumn('businesses', 'hero_text')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->text('hero_text')->nullable()->after('hero_title');
            });
        }

        if (!Schema::hasColumn('businesses', 'about_title')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('about_title')->nullable()->after('hero_text');
            });
        }

        if (!Schema::hasColumn('businesses', 'about_text')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->text('about_text')->nullable()->after('about_title');
            });
        }

        if (!Schema::hasColumn('businesses', 'phone')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('phone', 80)->nullable()->after('about_text');
            });
        }

        if (!Schema::hasColumn('businesses', 'email')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('email', 160)->nullable()->after('phone');
            });
        }

        if (!Schema::hasColumn('businesses', 'address')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('address')->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn('businesses', 'opening_hours')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->text('opening_hours')->nullable()->after('address');
            });
        }

        if (!Schema::hasColumn('businesses', 'google_maps_url')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->text('google_maps_url')->nullable()->after('opening_hours');
            });
        }

        if (!Schema::hasColumn('businesses', 'logo_path')) {
            Schema::table('businesses', function (Blueprint $table): void {
                $table->string('logo_path')->nullable()->after('google_maps_url');
            });
        }

        // Régi mezőnevek adatainak átemelése az új mezőkbe.
        if (Schema::hasColumn('businesses', 'opening_hours_text')) {
            DB::statement("UPDATE businesses SET opening_hours = opening_hours_text WHERE opening_hours IS NULL");
        }

        if (Schema::hasColumn('businesses', 'maps_url')) {
            DB::statement("UPDATE businesses SET google_maps_url = maps_url WHERE google_maps_url IS NULL");
        }

        DB::table('businesses')
            ->where('slug', 'demo')
            ->update(['slug' => 'default']);

        DB::table('businesses')
            ->where('slug', 'default')
            ->whereIn('name', ['Demo Vallalkozas', 'Demo Vállalkozás'])
            ->update([
                'name' => 'Aranyvonal Stúdió',
                'tagline' => 'Személyre szabott szolgáltatások, egyszerű online foglalással.',
                'hero_title' => 'Egyszerű foglalás. Megbízható szolgáltatás.',
                'hero_text' => 'Válaszd ki a neked megfelelő szolgáltatást és időpontot néhány kattintással. Gyors, átlátható és kényelmes.',
                'about_title' => 'Rólunk',
                'about_text' => 'Fontos számunkra, hogy már az első kattintástól egyszerű és átlátható legyen az ügyintézés. Válassz szolgáltatást, foglalj szabad időpontot, és mi gondoskodunk a többiről.',
                'phone' => '+36 30 123 4567',
                'email' => 'hello@aranyvonal.hu',
                'address' => '3525 Miskolc, Széchenyi utca 12.',
                'opening_hours' => "Hétfő–Péntek: 09:00–17:00\nSzombat: 09:00–13:00\nVasárnap: zárva",
                'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=Miskolc',
                'logo_text' => 'AS',
            ]);
    }

    public function down(): void
    {
        // Szándékosan konzervatív rollback: nem törlünk olyan mezőket,
        // amelyek egy korábbi storefront migrációból is származhatnak.
    }
};
