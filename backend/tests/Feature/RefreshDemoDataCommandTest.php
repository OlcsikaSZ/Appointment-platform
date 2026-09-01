<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefreshDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_refresh_creates_expected_hairdresser_sample_data_and_can_be_rerun(): void
    {
        Business::query()->create([
            'name' => 'Régi demo',
            'slug' => 'default',
            'email' => 'old@example.test',
            'timezone' => 'Europe/Budapest',
            'active' => true,
        ]);

        $this->artisan('app:refresh-demo-data', [
            '--business' => 'default',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('businesses', [
            'slug' => 'default',
            'name' => 'Aranyvonal Hair Studio',
            'logo_text' => 'AH',
        ]);

        $businessId = (int) Business::query()->where('slug', 'default')->value('id');

        $this->assertSame(5, DB::table('services')->where('business_id', $businessId)->count());
        $this->assertSame(6, DB::table('working_hours')->where('business_id', $businessId)->count());
        $this->assertSame(8, DB::table('customer_profiles')->where('business_id', $businessId)->count());
        $this->assertSame(30, DB::table('bookings')->where('business_id', $businessId)->count());
        $this->assertSame(20, DB::table('bookings')->where('business_id', $businessId)->where('status', 'completed')->count());
        $this->assertSame(8, DB::table('bookings')->where('business_id', $businessId)->where('status', 'booked')->count());
        $this->assertSame(1, DB::table('bookings')->where('business_id', $businessId)->where('status', 'cancelled')->count());
        $this->assertSame(1, DB::table('bookings')->where('business_id', $businessId)->where('status', 'no_show')->count());
        $this->assertSame(4, DB::table('faqs')->where('business_id', $businessId)->count());
        $this->assertSame(5, DB::table('reviews')->where('business_id', $businessId)->count());
        $this->assertSame(1, DB::table('blocked_times')->where('business_id', $businessId)->count());

        $this->assertSame(
            8,
            DB::table('customer_profiles')
                ->where('business_id', $businessId)
                ->where('email', 'like', '%@demo.example')
                ->count()
        );

        // Újrafuttatva se duplázza a demo sorokat.
        $this->artisan('app:refresh-demo-data', [
            '--business' => 'default',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(5, DB::table('services')->where('business_id', $businessId)->count());
        $this->assertSame(8, DB::table('customer_profiles')->where('business_id', $businessId)->count());
        $this->assertSame(30, DB::table('bookings')->where('business_id', $businessId)->count());
    }
}
