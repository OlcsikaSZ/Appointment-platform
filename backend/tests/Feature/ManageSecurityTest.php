<?php

namespace Tests\Feature;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class ManageSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeAppointmentTime();
    }

    protected function tearDown(): void
    {
        $this->unfreezeAppointmentTime();
        parent::tearDown();
    }

    public function test_unknown_manage_token_returns_not_found(): void
    {
        $this->getJson('/api/v1/bookings/'.str_repeat('x', 64))->assertNotFound();
    }

    public function test_expired_manage_token_returns_gone(): void
    {
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'manage_token_expires_at' => CarbonImmutable::parse('2026-08-04 23:59:59', 'Europe/Budapest'),
        ]);

        $this->getJson("/api/v1/bookings/{$booking->manage_token}")
            ->assertStatus(410)
            ->assertJsonPath('message', 'A foglalás kezelőlinkje lejárt. Vedd fel a kapcsolatot a szolgáltatóval.');
    }

    public function test_cancelled_booking_is_visible_but_cannot_be_modified(): void
    {
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, ['status' => Booking::STATUS_CANCELLED]);

        $this->getJson("/api/v1/bookings/{$booking->manage_token}")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('manage.can_cancel', false)
            ->assertJsonPath('manage.can_reschedule', false);

        $this->postJson("/api/v1/bookings/{$booking->manage_token}/reschedule", [
            'date' => '2026-08-10',
            'time' => '12:00',
        ])->assertStatus(409);
    }

    public function test_manage_payload_contains_the_business_brand(): void
    {
        $business = $this->createBusiness([
            'name' => 'Próba Szalon',
            'logo_path' => '/storage/businesses/logo.webp',
            'logo_thumbnail_path' => '/storage/businesses/logo-thumb.webp',
            'logo_text' => 'PS',
        ]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service);

        $this->getJson("/api/v1/bookings/{$booking->manage_token}")
            ->assertOk()
            ->assertJsonPath('business.name', 'Próba Szalon')
            ->assertJsonPath('business.logoUrl', '/storage/businesses/logo.webp')
            ->assertJsonPath('business.logoThumbnailUrl', '/storage/businesses/logo-thumb.webp')
            ->assertJsonPath('business.logoText', 'PS');
    }

    public function test_calendar_invite_is_served_as_a_real_ics_file(): void
    {
        $business = $this->createBusiness([
            'name' => 'Próba Szalon',
            'address' => '3525 Miskolc, Próba utca 1.',
            'timezone' => 'Europe/Budapest',
        ]);
        $service = $this->createService($business, ['name' => 'Konzultáció']);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-08-10',
            'start_time' => '10:00',
            'end_time' => '10:30',
        ]);

        $response = $this->get("/api/v1/bookings/{$booking->manage_token}/calendar.ics");

        $response->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=utf-8')
            ->assertHeader('content-disposition', 'attachment; filename="foglalas-2026-08-10.ics"');

        $content = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $content);
        $this->assertStringContainsString("DTSTART:20260810T080000Z\r\n", $content);
        $this->assertStringContainsString("DTEND:20260810T083000Z\r\n", $content);
        $this->assertStringContainsString("SUMMARY:Konzultáció\r\n", $content);
        $this->assertStringContainsString('URL:https://booking.example.test/manage?token=', str_replace("\r\n ", '', $content));

        foreach (array_filter(explode("\r\n", $content)) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_manage_links_can_be_invalidated_for_a_business(): void
    {
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service);

        $this->artisan('app:invalidate-manage-links', [
            '--business' => $business->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertTrue($booking->fresh()->manage_token_expires_at->isPast());
    }
}
