<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $businessId = DB::table('businesses')->where('slug', 'default')->value('id');

        if (! $businessId) {
            return;
        }

        // A felület forintban kér be árat, a price_cents mező viszont századforint-alapú.
        // A korai mintaadatoknál ezért csak a négy ismert demoárat javítjuk,
        // felhasználó által felvett szolgáltatást nem módosítunk.
        $prices = [
            'Konzultacio' => [8000, 800000],
            'Konzultáció' => [8000, 800000],
            'Alap szolgaltatas' => [12000, 1200000],
            'Alap szolgáltatás' => [12000, 1200000],
            'Hosszabb szolgaltatas' => [22000, 2200000],
            'Hosszabb szolgáltatás' => [22000, 2200000],
            'Helyszini idopont' => [18000, 1800000],
            'Helyszíni időpont' => [18000, 1800000],
        ];

        foreach ($prices as $name => [$oldValue, $newValue]) {
            DB::table('services')
                ->where('business_id', $businessId)
                ->where('name', $name)
                ->where('price_cents', $oldValue)
                ->update(['price_cents' => $newValue]);
        }
    }

    public function down(): void
    {
        // Nem vonjuk vissza automatikusan, mert közben az admin módosíthatta az árakat.
    }
};
