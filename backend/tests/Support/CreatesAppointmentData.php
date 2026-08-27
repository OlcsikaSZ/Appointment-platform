<?php

namespace Tests\Support;

use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait CreatesAppointmentData
{
    protected function freezeAppointmentTime(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 08:00:00', 'Europe/Budapest'));
    }

    protected function unfreezeAppointmentTime(): void
    {
        CarbonImmutable::setTestNow();
    }

    protected function createBusiness(array $overrides = []): Business
    {
        return Business::create(array_merge([
            'name' => 'Teszt Vállalkozás',
            'slug' => 'test-business-'.Str::lower(Str::random(6)),
            'timezone' => 'Europe/Budapest',
            'email' => 'admin@example.test',
            'active' => true,
            'min_advance_minutes' => 0,
            'max_advance_days' => 90,
            'slot_interval_minutes' => 15,
            'cancellation_deadline_minutes' => 0,
            'reschedule_deadline_minutes' => 0,
            'manage_token_retention_days' => 30,
        ], $overrides));
    }

    protected function createService(Business $business, array $overrides = []): Service
    {
        return Service::create(array_merge([
            'business_id' => $business->id,
            'category' => 'Általános',
            'name' => 'Teszt szolgáltatás',
            'description' => 'Automatikus teszt szolgáltatás.',
            'duration_minutes' => 60,
            'buffer_minutes' => 0,
            'price_cents' => 1000000,
            'price_mode' => 'fixed',
            'active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    protected function addMondayHours(Business $business, string $start = '09:00', string $end = '17:00'): WorkingHour
    {
        return WorkingHour::create([
            'business_id' => $business->id,
            'weekday' => 1,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    protected function createBooking(Business $business, Service $service, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price_cents_snapshot' => $service->price_cents,
            'price_mode_snapshot' => $service->price_mode ?: 'fixed',
            'date' => '2026-08-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'busy_until' => '11:00',
            'customer_name' => 'Teszt Elek',
            'customer_contact' => 'customer@example.test',
            'customer_phone' => '+36 30 123 4567',
            'customer_note' => 'Teszt megjegyzés',
            'manage_token' => Str::random(64),
            'manage_token_expires_at' => CarbonImmutable::parse('2026-09-10 23:59:59', 'Europe/Budapest'),
            'status' => Booking::STATUS_BOOKED,
        ], $overrides));
    }

    protected function createAdmin(Business $business, array $overrides = []): User
    {
        return User::create(array_merge([
            'business_id' => $business->id,
            'name' => 'Admin Teszt',
            'email' => 'admin-'.$business->id.'@example.test',
            'password' => 'CorrectPassword123!',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ], $overrides));
    }

    protected function createBlock(Business $business, array $overrides = []): BlockedTime
    {
        return BlockedTime::create(array_merge([
            'business_id' => $business->id,
            'date' => '2026-08-10',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'reason' => 'Teszt blokk',
            'is_all_day' => false,
        ], $overrides));
    }

    protected function createEmailLog(Business $business, ?Booking $booking = null, array $overrides = []): EmailLog
    {
        return EmailLog::create(array_merge([
            'business_id' => $business->id,
            'booking_id' => $booking?->id,
            'event_type' => 'booking_created',
            'recipient_type' => 'customer',
            'recipient_email' => 'customer@example.test',
            'subject' => 'Teszt email',
            'status' => EmailLog::STATUS_PENDING,
            'payload' => [],
        ], $overrides));
    }

    protected function bookingPayload(Service $service, array $overrides = []): array
    {
        return array_merge([
            'service_id' => $service->id,
            'date' => '2026-08-10',
            'time' => '10:00',
            'customer_name' => 'Kovács Anna',
            'customer_contact' => 'anna@example.test',
            'customer_note' => 'Első alkalom.',
            'legal_accepted' => true,
        ], $overrides);
    }
}
