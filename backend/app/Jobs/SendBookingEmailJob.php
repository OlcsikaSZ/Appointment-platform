<?php

namespace App\Jobs;

use App\Mail\BookingNotificationMail;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\Booking;
use App\Models\ReminderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendBookingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 90;

    public function __construct(public readonly int $emailLogId)
    {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $log = EmailLog::query()->with(['business.emailSetting', 'booking'])->find($this->emailLogId);

        if (! $log || $log->status === EmailLog::STATUS_SENT) {
            return;
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventType = (string) ($payload['mail_event_type'] ?? 'booking_created');
        $isReminder = in_array($eventType, ['booking_reminder_24h', 'booking_reminder_2h'], true);
        if ($isReminder && (! $log->booking || $log->booking->status !== Booking::STATUS_BOOKED)) {
            $message = 'A foglalás már nem aktív, ezért az emlékeztető kimaradt.';
            $log->update([
                'status' => EmailLog::STATUS_SKIPPED,
                'error_message' => $message,
                'failed_at' => null,
            ]);
            ReminderLog::query()->where('email_log_id', $log->id)->update([
                'status' => ReminderLog::STATUS_SKIPPED,
                'skipped_at' => now(),
                'error_message' => $message,
            ]);
            return;
        }
        $recipientType = in_array($log->recipient_type, EmailSetting::RECIPIENT_TYPES, true)
            ? $log->recipient_type
            : 'customer';
        $settings = is_array($payload['settings'] ?? null)
            ? EmailSetting::normalize($payload['settings'])
            : ($log->business ? EmailSetting::resolvedForBusiness($log->business) : EmailSetting::defaults());

        $log->update([
            'status' => EmailLog::STATUS_PENDING,
            'attempt_count' => (int) $log->attempt_count + 1,
            'last_attempt_at' => now(),
            'error_message' => null,
            'failed_at' => null,
        ]);

        try {
            Mail::to($log->recipient_email)->send(new BookingNotificationMail(
                data: $data,
                eventType: $eventType,
                recipientType: $recipientType,
                settings: $settings,
            ));

            $log->update([
                'status' => EmailLog::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);
            ReminderLog::query()->where('email_log_id', $log->id)->update([
                'status' => ReminderLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => EmailLog::STATUS_PENDING,
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        EmailLog::query()->whereKey($this->emailLogId)->update([
            'status' => EmailLog::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($exception?->getMessage() ?: 'Az email job véglegesen sikertelen volt.', 0, 4000),
        ]);
        ReminderLog::query()->where('email_log_id', $this->emailLogId)->update([
            'status' => ReminderLog::STATUS_FAILED,
            'error_message' => mb_substr($exception?->getMessage() ?: 'Az emlékeztető email véglegesen sikertelen volt.', 0, 4000),
        ]);
    }
}
