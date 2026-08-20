<?php

namespace Tests\Feature;

use App\Jobs\SendBookingEmailJob;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\ReminderLog;
use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class ReminderWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_24_hour_reminder_is_queued_once(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 10:00:00', 'Europe/Budapest'));
        Queue::fake([SendBookingEmailJob::class]);
        $business = $this->createBusiness(['reminder_24h_enabled' => true, 'reminder_2h_enabled' => false]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-08-06',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'busy_until' => '11:00',
        ]);

        $first = app(ReminderService::class)->dispatchDue($business);
        $second = app(ReminderService::class)->dispatchDue($business);

        $this->assertSame(1, $first['queued']);
        $this->assertSame(1, $second['duplicates']);
        $this->assertDatabaseCount('reminder_logs', 1);
        $this->assertDatabaseHas('reminder_logs', [
            'booking_id' => $booking->id,
            'reminder_type' => ReminderLog::TYPE_24H,
            'status' => ReminderLog::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'booking_id' => $booking->id,
            'event_type' => 'booking_reminder_24h',
            'recipient_type' => 'customer',
        ]);
        Queue::assertPushed(SendBookingEmailJob::class, 1);
    }

    public function test_optional_two_hour_reminder_respects_business_setting(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 08:00:00', 'Europe/Budapest'));
        Queue::fake([SendBookingEmailJob::class]);
        $business = $this->createBusiness(['reminder_24h_enabled' => false, 'reminder_2h_enabled' => false]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-08-05',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'busy_until' => '11:00',
        ]);

        app(ReminderService::class)->dispatchDue($business);
        $this->assertDatabaseMissing('reminder_logs', ['booking_id' => $booking->id]);

        $business->update(['reminder_2h_enabled' => true]);
        app(ReminderService::class)->dispatchDue($business->fresh());
        $this->assertDatabaseHas('reminder_logs', [
            'booking_id' => $booking->id,
            'reminder_type' => ReminderLog::TYPE_2H,
        ]);
    }

    public function test_cancelled_booking_never_enters_reminder_queue(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 10:00:00', 'Europe/Budapest'));
        Queue::fake([SendBookingEmailJob::class]);
        $business = $this->createBusiness(['reminder_24h_enabled' => true]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-08-06',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'busy_until' => '11:00',
            'status' => Booking::STATUS_CANCELLED,
        ]);

        app(ReminderService::class)->dispatchDue($business);

        $this->assertDatabaseMissing('reminder_logs', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('email_logs', ['event_type' => 'booking_reminder_24h']);
    }

    public function test_queued_reminder_is_skipped_if_booking_is_cancelled_before_worker_runs(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 10:00:00', 'Europe/Budapest'));
        Queue::fake([SendBookingEmailJob::class]);
        Mail::fake();
        $business = $this->createBusiness(['reminder_24h_enabled' => true]);
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, [
            'date' => '2026-08-06', 'start_time' => '10:00', 'end_time' => '11:00', 'busy_until' => '11:00',
        ]);
        app(ReminderService::class)->dispatchDue($business);
        $log = EmailLog::query()->where('event_type', 'booking_reminder_24h')->firstOrFail();

        $booking->update(['status' => Booking::STATUS_CANCELLED]);
        (new SendBookingEmailJob($log->id))->handle();

        $this->assertSame(EmailLog::STATUS_SKIPPED, $log->fresh()->status);
        $this->assertSame(ReminderLog::STATUS_SKIPPED, $booking->reminderLogs()->firstOrFail()->status);
        Mail::assertNothingSent();
    }
}
