<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerAccount;
use App\Services\CustomerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class CustomerHistoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_booking_builds_searchable_customer_history_with_no_show_count_and_note(): void
    {
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'customer_name' => 'Kovács Anna',
            'customer_contact' => 'Anna@Example.test',
            'customer_phone' => '+36 30 555 0101',
            'status' => Booking::STATUS_NO_SHOW,
        ]);
        app(CustomerProfileService::class)->syncBooking($booking);
        $profile = $booking->fresh()->customerProfile;
        $admin = $this->createAdmin($business);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/businesses/{$business->id}/customers?q=555")
            ->assertOk()
            ->assertJsonPath('data.0.email', 'anna@example.test')
            ->assertJsonPath('data.0.no_show_count', 1);

        $this->patchJson("/api/v1/admin/customers/{$profile->id}", [
            'phone' => '+36 30 555 0101',
            'admin_note' => 'Érkezés előtt telefonos egyeztetés szükséges.',
        ])->assertOk();

        $this->getJson("/api/v1/admin/customers/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.admin_note', 'Érkezés előtt telefonos egyeztetés szükséges.')
            ->assertJsonPath('bookings.0.id', $booking->id);
    }

    public function test_admin_cannot_open_another_business_customer_history(): void
    {
        $ownBusiness = $this->createBusiness();
        $otherBusiness = $this->createBusiness();
        $service = $this->createService($otherBusiness);
        $booking = $this->createBooking($otherBusiness, $service);
        app(CustomerProfileService::class)->syncBooking($booking);
        $admin = $this->createAdmin($ownBusiness);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/v1/admin/customers/'.$booking->fresh()->customer_profile_id)->assertForbidden();
    }

    public function test_registered_customer_without_booking_appears_in_customer_list(): void
    {
        $business = $this->createBusiness();
        $account = CustomerAccount::query()->create([
            'business_id' => $business->id,
            'name' => 'Fiókos Flóra',
            'email' => 'flora@example.test',
            'password' => 'Biztonsagos123',
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        app(CustomerProfileService::class)->attachHistoricalBookings($account);

        $admin = $this->createAdmin($business);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/businesses/{$business->id}/customers?q=flora")
            ->assertOk()
            ->assertJsonPath('data.0.email', 'flora@example.test')
            ->assertJsonPath('data.0.bookings_count', 0)
            ->assertJsonPath('data.0.registered_account', true);
    }
}
