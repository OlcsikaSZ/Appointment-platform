<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\ReminderLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

class ReminderService
{
    public function __construct(private readonly BookingMailService $mailService)
    {
    }

    public function dispatchDue(?Business $onlyBusiness = null): array
    {
        $stats = ['businesses' => 0, 'queued' => 0, 'duplicates' => 0, 'skipped' => 0];
        $query = Business::query()->where('active', true);
        if ($onlyBusiness) $query->whereKey($onlyBusiness->id);

        $query->each(function (Business $business) use (&$stats): void {
            $stats['businesses']++;
            $timezone = $business->timezone ?: config('app.timezone');
            $now = CarbonImmutable::now($timezone);
            $types = [];
            if ($business->reminder_24h_enabled) $types[ReminderLog::TYPE_24H] = 1440;
            if ($business->reminder_2h_enabled) $types[ReminderLog::TYPE_2H] = 120;
            if ($types === []) return;

            $business->bookings()
                ->with(['business.emailSetting', 'service'])
                ->where('status', Booking::STATUS_BOOKED)
                ->whereBetween('date', [$now->toDateString(), $now->addDays(2)->toDateString()])
                ->chunkById(100, function ($bookings) use ($business, $timezone, $now, $types, &$stats): void {
                    foreach ($bookings as $booking) {
                        $appointment = CarbonImmutable::parse(
                            $booking->date->format('Y-m-d').' '.substr((string) $booking->start_time, 0, 5),
                            $timezone,
                        );
                        if (! $appointment->isFuture()) {
                            continue;
                        }

                        foreach ($types as $type => $minutes) {
                            $scheduledFor = $appointment->subMinutes($minutes);
                            if ($now->lessThan($scheduledFor)) continue;

                            try {
                                $log = ReminderLog::query()->firstOrCreate(
                                    ['booking_id' => $booking->id, 'reminder_type' => $type],
                                    [
                                        'business_id' => $business->id,
                                        'status' => ReminderLog::STATUS_QUEUED,
                                        'scheduled_for' => $scheduledFor->utc(),
                                    ],
                                );
                            } catch (UniqueConstraintViolationException) {
                                $stats['duplicates']++;
                                continue;
                            }

                            if (! $log->wasRecentlyCreated) {
                                $stats['duplicates']++;
                                continue;
                            }

                            $emailLog = $this->mailService->bookingReminder($booking->fresh(), $type);
                            if (! $emailLog) {
                                $log->update([
                                    'status' => ReminderLog::STATUS_FAILED,
                                    'error_message' => 'Az emlékeztető email nem volt naplózható.',
                                ]);
                                $stats['skipped']++;
                                continue;
                            }

                            $log->update(['email_log_id' => $emailLog->id]);
                            $stats['queued']++;
                        }
                    }
                });
        });

        return $stats;
    }
}
