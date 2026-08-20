<?php

namespace Tests\Feature;

use App\Jobs\SendBookingEmailJob;
use App\Mail\BookingNotificationMail;
use App\Models\EmailLog;
use App\Services\BookingMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;

class EmailWorkflowTest extends TestCase
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

    public function test_booking_created_email_is_queued_and_logged_as_pending(): void
    {
        Queue::fake([SendBookingEmailJob::class]);
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service);

        app(BookingMailService::class)->bookingCreated($booking);

        $this->assertDatabaseCount('email_logs', 2);
        $this->assertDatabaseHas('email_logs', [
            'booking_id' => $booking->id,
            'event_type' => 'booking_created',
            'recipient_type' => 'customer',
            'status' => EmailLog::STATUS_PENDING,
        ]);
        Queue::assertPushed(SendBookingEmailJob::class, 2);
    }

    public function test_email_job_can_be_faked_and_marks_log_as_sent(): void
    {
        Queue::fake([SendBookingEmailJob::class]);
        Mail::fake();
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service);

        app(BookingMailService::class)->bookingCreated($booking);
        $log = EmailLog::query()->where('recipient_type', 'customer')->firstOrFail();

        (new SendBookingEmailJob($log->id))->handle();

        Mail::assertSent(BookingNotificationMail::class, fn (BookingNotificationMail $mail) => $mail->hasTo($log->recipient_email));
        $this->assertDatabaseHas('email_logs', [
            'id' => $log->id,
            'status' => EmailLog::STATUS_SENT,
            'attempt_count' => 1,
        ]);
    }

    public function test_reschedule_and_cancel_create_corresponding_email_logs(): void
    {
        Queue::fake([SendBookingEmailJob::class]);
        $business = $this->createBusiness(['slug' => 'email-events']);
        $service = $this->createService($business);
        $this->addMondayHours($business);
        $booking = $this->createBooking($business, $service);

        $this->postJson("/api/v1/bookings/{$booking->manage_token}/reschedule", [
            'date' => '2026-08-10',
            'time' => '12:00',
        ])->assertOk();

        $this->assertDatabaseHas('email_logs', [
            'booking_id' => $booking->id,
            'event_type' => 'booking_rescheduled',
            'recipient_type' => 'customer',
        ]);

        $this->postJson("/api/v1/bookings/{$booking->manage_token}/cancel")->assertOk();
        $this->assertDatabaseHas('email_logs', [
            'booking_id' => $booking->id,
            'event_type' => 'booking_cancelled',
            'recipient_type' => 'customer',
        ]);
    }

    public function test_permanently_failed_job_marks_email_log_as_failed(): void
    {
        $business = $this->createBusiness();
        $log = $this->createEmailLog($business);
        $job = new SendBookingEmailJob($log->id);

        $job->failed(new RuntimeException('SMTP unavailable'));

        $this->assertDatabaseHas('email_logs', [
            'id' => $log->id,
            'status' => EmailLog::STATUS_FAILED,
        ]);
        $this->assertStringContainsString('SMTP unavailable', (string) $log->fresh()->error_message);
    }

    public function test_manage_url_is_absolute(): void
    {
        config()->set('appointment.public_url', 'https://booking.example.test/app');
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, ['manage_token' => 'absolute-token']);

        $url = app(BookingMailService::class)->manageUrl($booking);

        $this->assertSame('https://booking.example.test/app/manage?token=absolute-token', $url);
    }

    public function test_customer_email_renders_manage_url_as_clickable_absolute_link(): void
    {
        config()->set('appointment.public_url', 'https://booking.example.test/app');
        $business = $this->createBusiness();
        $service = $this->createService($business);
        $booking = $this->createBooking($business, $service, ['manage_token' => 'mail-link-token']);
        $data = app(BookingMailService::class)->manageUrl($booking);
        $mail = new BookingNotificationMail(
            data: array_merge([
                'business_name' => $business->name,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_contact,
                'service_name' => $booking->service_name,
                'date' => '2026-08-10',
                'date_formatted' => '2026. 08. 10.',
                'start_time' => '10:00',
                'end_time' => '10:45',
                'customer_note' => null,
                'previous_schedule' => null,
            ], ['manage_url' => $data]),
            eventType: 'booking_created',
            recipientType: 'customer',
            settings: \App\Models\EmailSetting::resolvedForBusiness($business),
        );

        $html = $mail->render();

        $this->assertStringContainsString('href="'.$data.'"', $html);
        $this->assertStringContainsString('Kezelő link: '.$data, $html);
    }
}
