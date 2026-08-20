<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_admin_cannot_access_or_modify_another_business_records(): void
    {
        $ownBusiness = $this->createBusiness(['slug' => 'own-business']);
        $otherBusiness = $this->createBusiness(['slug' => 'other-business']);
        $ownAdmin = $this->createAdmin($ownBusiness);
        $otherService = $this->createService($otherBusiness);
        $otherBooking = $this->createBooking($otherBusiness, $otherService);
        $otherBlock = $this->createBlock($otherBusiness);
        $otherLog = $this->createEmailLog($otherBusiness, $otherBooking);

        Sanctum::actingAs($ownAdmin, ['admin']);

        $this->getJson("/api/v1/admin/businesses/{$otherBusiness->id}/bookings")->assertForbidden();
        $this->patchJson("/api/v1/admin/bookings/{$otherBooking->id}/status", ['status' => Booking::STATUS_COMPLETED])->assertForbidden();
        $this->deleteJson("/api/v1/admin/blocked-times/{$otherBlock->id}")->assertForbidden();
        $this->getJson("/api/v1/admin/businesses/{$otherBusiness->id}/services")->assertForbidden();
        $this->getJson("/api/v1/admin/businesses/{$otherBusiness->id}/email-logs")->assertForbidden();
        $this->postJson("/api/v1/admin/email-logs/{$otherLog->id}/resend")->assertForbidden();
    }

    public function test_customer_token_can_never_access_admin_routes(): void
    {
        $business = $this->createBusiness();
        $account = CustomerAccount::create([
            'business_id' => $business->id, 'name' => 'Kovács Anna', 'email' => 'anna@example.test',
            'password' => 'TitkosJelszo123', 'role' => 'user', 'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($account, ['user']);
        $this->getJson("/api/v1/admin/businesses/{$business->id}/bookings")->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertForbidden();
    }
}
