<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\DataRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class AdminSettingsAndRetentionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_update_booking_rules_and_legal_documents(): void
    {
        $business = $this->createBusiness();
        $admin = $this->createAdmin($business);
        Sanctum::actingAs($admin, ['admin']);

        $this->patchJson("/api/v1/admin/businesses/{$business->id}/settings", [
            'min_advance_minutes' => 120,
            'max_advance_days' => 60,
            'slot_interval_minutes' => 15,
            'cancellation_deadline_minutes' => 2880,
            'reschedule_deadline_minutes' => 1440,
            'timezone' => 'Europe/Budapest',
            'hide_prices' => true,
            'booking_retention_days' => 365,
            'email_log_retention_days' => 90,
            'manage_token_retention_days' => 14,
            'privacy_policy' => 'Adatkezelési szöveg',
            'terms_text' => 'Feltételek',
            'imprint_text' => 'Impresszum',
            'cookie_policy' => 'Süti- és technikai tárolási tájékoztató',
        ])->assertOk()->assertJsonPath('data.hide_prices', true);

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'min_advance_minutes' => 120,
            'hide_prices' => 1,
            'email_log_retention_days' => 90,
            'cookie_policy' => 'Süti- és technikai tárolási tájékoztató',
        ]);
    }

    public function test_retention_anonymizes_old_booking_and_expires_manage_token(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 08:00:00', 'Europe/Budapest'));
        $business = $this->createBusiness([
            'booking_retention_days' => 30,
            'manage_token_retention_days' => 7,
        ]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-01-01',
            'status' => Booking::STATUS_COMPLETED,
            'manage_token_expires_at' => CarbonImmutable::parse('2027-01-01'),
        ]);

        $log = $this->createEmailLog($business, $booking, ['payload' => ['data' => ['customer_name' => 'Teszt Elek']]]);

        app(DataRetentionService::class)->purgeBusiness($business);
        $booking->refresh();

        $this->assertSame('Törölt ügyfél', $booking->customer_name);
        $this->assertNotNull($booking->anonymized_at);
        $this->assertTrue($booking->manage_token_expires_at->isPast());
        $this->assertNull($log->fresh()->payload);
        $this->assertStringStartsWith('deleted+booking-', $log->fresh()->recipient_email);
        CarbonImmutable::setTestNow();
    }

    public function test_legal_document_formatting_is_sanitized_before_public_rendering(): void
    {
        $business = $this->createBusiness();
        $admin = $this->createAdmin($business);
        Sanctum::actingAs($admin, ['admin']);

        $payload = [
            'min_advance_minutes' => 60,
            'max_advance_days' => 90,
            'slot_interval_minutes' => 15,
            'cancellation_deadline_minutes' => 1440,
            'reschedule_deadline_minutes' => 1440,
            'timezone' => 'Europe/Budapest',
            'hide_prices' => false,
            'booking_retention_days' => 730,
            'email_log_retention_days' => 180,
            'manage_token_retention_days' => 30,
            'privacy_policy' => '<h2 onclick="alert(1)">Cím</h2><p><strong>Fontos</strong> <script>alert(1)</script></p>',
            'terms_text' => '',
            'imprint_text' => '',
            'cookie_policy' => '',
        ];

        $this->patchJson("/api/v1/admin/businesses/{$business->id}/settings", $payload)
            ->assertOk()
            ->assertJsonPath('data.privacy_policy', '<h2>Cím</h2><p><strong>Fontos</strong> alert(1)</p>');

        $this->getJson("/api/v1/businesses/{$business->slug}")
            ->assertOk()
            ->assertJsonPath('data.legal.privacyPolicy', '<h2>Cím</h2><p><strong>Fontos</strong> alert(1)</p>');
    }
}
