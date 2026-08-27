<?php

namespace Tests\Feature;

use App\Jobs\SendBookingEmailJob;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class BookingCoreTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeAppointmentTime();
        Queue::fake([SendBookingEmailJob::class]);
    }

    protected function tearDown(): void
    {
        $this->unfreezeAppointmentTime();
        parent::tearDown();
    }

    public function test_new_booking_is_created_successfully(): void
    {
        $business = $this->createBusiness(['slug' => 'booking-success']);
        $service = $this->createService($business);
        $this->addMondayHours($business);

        $response = $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service),
        );

        $response->assertCreated()
            ->assertJsonPath('data.status', Booking::STATUS_BOOKED)
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('manageUrl', 'https://booking.example.test/manage?token='.$response->json('data.manage_token'));

        $this->assertDatabaseHas('bookings', [
            'business_id' => $business->id,
            'date' => '2026-08-10',
            'start_time' => '10:00',
            'status' => Booking::STATUS_BOOKED,
        ]);

        $booking = Booking::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame((int) $service->price_cents, (int) $booking->price_cents_snapshot);
        $this->assertSame($service->price_mode, $booking->price_mode_snapshot);
        $this->assertNotNull($booking->legal_accepted_at);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $booking->legal_text_hash);
    }


    public function test_public_booking_rate_limit_is_enforced(): void
    {
        $business = $this->createBusiness(['slug' => 'booking-rate-limit']);
        $service = $this->createService($business);

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->postJson("/api/v1/businesses/{$business->slug}/bookings", [])
                ->assertUnprocessable();
        }

        $this->postJson("/api/v1/businesses/{$business->slug}/bookings", [])
            ->assertTooManyRequests();
    }

    public function test_public_booking_requires_legal_acceptance(): void
    {
        $business = $this->createBusiness(['slug' => 'legal-acceptance']);
        $service = $this->createService($business);
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['legal_accepted' => false]),
        )->assertUnprocessable()->assertJsonValidationErrors('legal_accepted');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_same_start_cannot_be_booked_twice(): void
    {
        $business = $this->createBusiness(['slug' => 'duplicate-start']);
        $service = $this->createService($business);
        $this->addMondayHours($business);

        $payload = $this->bookingPayload($service);
        $this->postJson("/api/v1/businesses/{$business->slug}/bookings", $payload)->assertCreated();
        $this->postJson("/api/v1/businesses/{$business->slug}/bookings", $payload)->assertStatus(409);

        $this->assertSame(1, Booking::query()->where('business_id', $business->id)->count());
    }

    public function test_overlapping_booking_with_different_start_is_rejected(): void
    {
        $business = $this->createBusiness(['slug' => 'overlap-start']);
        $service = $this->createService($business, ['duration_minutes' => 90]);
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '10:00']),
        )->assertCreated();

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '10:15', 'customer_contact' => 'other@example.test']),
        )->assertStatus(409);
    }

    public function test_service_buffer_blocks_following_slot(): void
    {
        $business = $this->createBusiness(['slug' => 'buffer-test']);
        $service = $this->createService($business, ['duration_minutes' => 30, 'buffer_minutes' => 30]);
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '10:00']),
        )->assertCreated();

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '10:30', 'customer_contact' => 'blocked@example.test']),
        )->assertStatus(409);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '11:00', 'customer_contact' => 'free@example.test']),
        )->assertCreated();
    }

    public function test_cancellation_releases_the_slot(): void
    {
        $business = $this->createBusiness(['slug' => 'cancel-release']);
        $service = $this->createService($business);
        $this->addMondayHours($business);

        $booking = $this->createBooking($business, $service);

        $this->postJson("/api/v1/bookings/{$booking->manage_token}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CANCELLED);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['customer_contact' => 'replacement@example.test']),
        )->assertCreated();
    }

    public function test_reschedule_releases_old_slot_and_reserves_new_slot(): void
    {
        $business = $this->createBusiness(['slug' => 'reschedule-release']);
        $service = $this->createService($business);
        $this->addMondayHours($business);
        $booking = $this->createBooking($business, $service);

        $this->postJson("/api/v1/bookings/{$booking->manage_token}/reschedule", [
            'date' => '2026-08-10',
            'time' => '12:00',
        ])->assertOk()->assertJsonPath('data.start_time', '12:00');

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '10:00', 'customer_contact' => 'old-slot@example.test']),
        )->assertCreated();

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['time' => '12:00', 'customer_contact' => 'new-slot@example.test']),
        )->assertStatus(409);
    }

    public function test_inactive_service_cannot_be_booked(): void
    {
        $business = $this->createBusiness(['slug' => 'inactive-service']);
        $service = $this->createService($business, ['active' => false]);
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service),
        )->assertStatus(422);
    }

    public function test_inactive_business_cannot_be_booked(): void
    {
        $business = $this->createBusiness(['slug' => 'inactive-business', 'active' => false]);
        $service = $this->createService($business);
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service),
        )->assertNotFound();
    }

    public function test_past_slot_cannot_be_booked(): void
    {
        $business = $this->createBusiness(['slug' => 'past-slot']);
        $service = $this->createService($business);
        // 2026-08-03 hétfő, de a tesztidő 2026-08-05.
        $this->addMondayHours($business);

        $this->postJson(
            "/api/v1/businesses/{$business->slug}/bookings",
            $this->bookingPayload($service, ['date' => '2026-08-03']),
        )->assertStatus(409);
    }
}
