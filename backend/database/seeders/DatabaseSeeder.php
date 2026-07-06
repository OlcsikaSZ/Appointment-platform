<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Vallalkozas',
                'tagline' => 'Altalanos online idopontfoglalo',
                'timezone' => 'Europe/Budapest',
                'primary_color' => '#107a5b',
                'logo_text' => 'DV',
            ]
        );

        $services = [
            ['Altalanos', 'Konzultacio', 'Rovid szemelyes vagy online egyeztetes.', 30, 10, 8000],
            ['Szolgaltatas', 'Alap szolgaltatas', 'Altalanos, kesobb testreszabhato szolgaltatas.', 45, 10, 12000],
            ['Szolgaltatas', 'Hosszabb szolgaltatas', 'Nagyobb idokeretet igenylo foglalas.', 90, 15, 22000],
            ['Helyszini', 'Helyszini idopont', 'Ugyfelnel vagy kulso helyszinen vegzett munka.', 60, 20, 18000],
        ];

        foreach ($services as $index => [$category, $name, $description, $duration, $buffer, $priceCents]) {
            Service::firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                [
                    'category' => $category,
                    'description' => $description,
                    'duration_minutes' => $duration,
                    'buffer_minutes' => $buffer,
                    'price_cents' => $priceCents,
                    'sort_order' => $index,
                ]
            );
        }

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WorkingHour::firstOrCreate([
                'business_id' => $business->id,
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        WorkingHour::firstOrCreate([
            'business_id' => $business->id,
            'weekday' => 6,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'business_id' => $business->id,
                'role' => 'owner',
                'password' => Hash::make('admin123'),
            ]
        );
    }
}
