<?php

namespace App\Services;

use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BookingMailService
{
    public function bookingCreated(Booking $booking): void
    {
        $this->sendForEvent($booking, 'booking_created');
    }

    public function bookingRescheduled(Booking $booking, array $previousSchedule): void
    {
        $this->sendForEvent($booking, 'booking_rescheduled', $previousSchedule);
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->sendForEvent($booking, 'booking_cancelled');
    }

    public function manageUrl(Booking $booking): string
    {
        return rtrim((string) config('appointment.public_url'), '/')
            .'/manage?token='.rawurlencode($booking->manage_token);
    }

    public function sendTest(
        Business $business,
        string $recipientEmail,
        string $recipientType,
        string $eventType,
    ): ?EmailLog {
        $booking = $business->bookings()
            ->with(['business.emailSetting', 'service'])
            ->latest('id')
            ->first();

        $data = $booking
            ? $this->buildBookingData($booking, $eventType === 'booking_rescheduled' ? [
                'date' => CarbonImmutable::parse($booking->date)->subDay()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '10:45',
            ] : null)
            : $this->sampleData($business, $eventType);

        return $this->deliver(
            business: $business,
            data: $data,
            eventType: $eventType,
            recipientType: $recipientType,
            recipientEmail: $recipientEmail,
            bookingId: null,
            logEventType: 'email_test',
        );
    }

    public function resendFromLog(EmailLog $sourceLog): ?EmailLog
    {
        $sourceLog->loadMissing(['business.emailSetting', 'booking.business.emailSetting', 'booking.service']);
        $business = $sourceLog->business ?: $sourceLog->booking?->business;

        if (! $business) {
            return null;
        }

        $payload = $sourceLog->payload ?? [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        $mailEventType = (string) ($payload['mail_event_type'] ?? '');

        if (! $data && $sourceLog->booking) {
            $mailEventType = in_array($sourceLog->event_type, EmailSetting::EVENT_TYPES, true)
                ? $sourceLog->event_type
                : 'booking_created';
            $data = $this->buildBookingData($sourceLog->booking);
        }

        if (! $data) {
            return null;
        }

        if (! in_array($mailEventType, EmailSetting::EVENT_TYPES, true)) {
            $mailEventType = 'booking_created';
        }

        return $this->deliver(
            business: $business,
            data: $data,
            eventType: $mailEventType,
            recipientType: in_array($sourceLog->recipient_type, EmailSetting::RECIPIENT_TYPES, true)
                ? $sourceLog->recipient_type
                : 'customer',
            recipientEmail: $sourceLog->recipient_email,
            bookingId: $sourceLog->booking_id,
            logEventType: $mailEventType,
            resentFromId: $sourceLog->id,
        );
    }

    private function sendForEvent(Booking $booking, string $eventType, ?array $previousSchedule = null): void
    {
        $booking->loadMissing(['business.emailSetting', 'service']);
        $business = $booking->business;

        if (! $business) {
            return;
        }

        $data = $this->buildBookingData($booking, $previousSchedule);
        $recipients = [
            ['type' => 'customer', 'email' => $booking->customer_contact],
        ];

        $adminEmail = $business->email
            ?: $business->users()->where('role', 'owner')->value('email');

        if ($adminEmail) {
            $recipients[] = ['type' => 'admin', 'email' => $adminEmail];
        }

        foreach ($recipients as $recipient) {
            $this->deliver(
                business: $business,
                data: $data,
                eventType: $eventType,
                recipientType: $recipient['type'],
                recipientEmail: $recipient['email'],
                bookingId: $booking->id,
                logEventType: $eventType,
            );
        }
    }

    private function deliver(
        Business $business,
        array $data,
        string $eventType,
        string $recipientType,
        string $recipientEmail,
        ?int $bookingId = null,
        ?string $logEventType = null,
        ?int $resentFromId = null,
    ): ?EmailLog {
        $settings = EmailSetting::resolvedForBusiness($business);
        $mail = new BookingNotificationMail(
            data: $data,
            eventType: $eventType,
            recipientType: $recipientType,
            settings: $settings,
        );

        $subject = $mail->subjectLine();
        $payload = [
            'mail_event_type' => $eventType,
            'data' => $data,
        ];

        try {
            Mail::to($recipientEmail)->send($mail);

            return $this->writeLog([
                'resent_from_id' => $resentFromId,
                'business_id' => $business->id,
                'booking_id' => $bookingId,
                'event_type' => $logEventType ?: $eventType,
                'recipient_type' => $recipientType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'status' => EmailLog::STATUS_SENT,
                'error_message' => null,
                'payload' => $payload,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $log = $this->writeLog([
                'resent_from_id' => $resentFromId,
                'business_id' => $business->id,
                'booking_id' => $bookingId,
                'event_type' => $logEventType ?: $eventType,
                'recipient_type' => $recipientType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'status' => EmailLog::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'payload' => $payload,
                'sent_at' => null,
            ]);

            report($exception);

            return $log;
        }
    }

    private function buildBookingData(Booking $booking, ?array $previousSchedule = null): array
    {
        $booking->loadMissing(['business.emailSetting', 'service']);

        return [
            'business_name' => $booking->business?->name ?: 'Időpontfoglalás',
            'business_email' => $booking->business?->email ?: '',
            'service_name' => $booking->service_name,
            'date' => $booking->date?->format('Y-m-d') ?: '',
            'date_formatted' => $booking->date?->format('Y. m. d.') ?: '',
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_contact,
            'customer_note' => $booking->customer_note,
            'manage_url' => $this->manageUrl($booking),
            'previous_schedule' => $previousSchedule ? [
                'date' => (string) ($previousSchedule['date'] ?? ''),
                'start_time' => substr((string) ($previousSchedule['start_time'] ?? ''), 0, 5),
                'end_time' => substr((string) ($previousSchedule['end_time'] ?? ''), 0, 5),
            ] : null,
        ];
    }

    private function sampleData(Business $business, string $eventType): array
    {
        $date = CarbonImmutable::now($business->timezone ?: config('app.timezone'))->addDay();

        return [
            'business_name' => $business->name ?: 'Az Ön Vállalkozása',
            'business_email' => $business->email ?: '',
            'service_name' => 'Minta szolgáltatás',
            'date' => $date->format('Y-m-d'),
            'date_formatted' => $date->format('Y. m. d.'),
            'start_time' => '10:00',
            'end_time' => '10:45',
            'customer_name' => 'Minta Vendég',
            'customer_email' => 'vendeg@example.com',
            'customer_note' => 'Ez egy teszt email, valódi foglalás nem jött létre.',
            'manage_url' => rtrim((string) config('appointment.public_url'), '/').'/manage?token=TESZT',
            'previous_schedule' => $eventType === 'booking_rescheduled' ? [
                'date' => $date->subDay()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '09:45',
            ] : null,
        ];
    }

    private function writeLog(array $payload): ?EmailLog
    {
        try {
            return EmailLog::create($payload);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
