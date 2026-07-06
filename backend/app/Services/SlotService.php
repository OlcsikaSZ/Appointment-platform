<?php

namespace App\Services;

use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SlotService
{
    public function slotsFor(Business $business, Service $service, string $date, ?int $excludeBookingId = null): array
    {
        $day = CarbonImmutable::parse($date, $business->timezone)->startOfDay();
        $weekday = (int) $day->dayOfWeek;
        $stepMinutes = 15;
        $minAllowed = CarbonImmutable::now($business->timezone)->addMinutes(60);

        $busy = $this->busyIntervals($business, $date, $excludeBookingId);
        $slots = [];

        foreach ($business->workingHours()->where('weekday', $weekday)->get() as $range) {
            $cursor = $this->dateTime($date, $range->start_time, $business->timezone);
            $rangeEnd = $this->dateTime($date, $range->end_time, $business->timezone);

            while ($cursor->copy()->addMinutes($service->duration_minutes)->lessThanOrEqualTo($rangeEnd)) {
                $visibleEnd = $cursor->addMinutes($service->duration_minutes);
                $busyUntil = $visibleEnd->addMinutes($service->buffer_minutes);

                if ($cursor->greaterThanOrEqualTo($minAllowed) && ! $this->overlapsBusy($cursor, $busyUntil, $busy)) {
                    $slots[] = [
                        'date' => $date,
                        'time' => $cursor->format('H:i'),
                        'label' => $cursor->format('H:i'),
                        'endTime' => $visibleEnd->format('H:i'),
                        'busyUntil' => $busyUntil->format('H:i'),
                    ];
                }

                $cursor = $cursor->addMinutes($stepMinutes);
            }
        }

        return $slots;
    }

    private function busyIntervals(Business $business, string $date, ?int $excludeBookingId = null): Collection
    {
        $bookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereDate('date', $date)
            ->where('status', 'booked')
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get()
            ->map(fn (Booking $booking) => [
                'start' => $this->dateTime($date, $booking->start_time, $business->timezone),
                'end' => $this->dateTime($date, $booking->busy_until, $business->timezone),
            ]);

        $blocks = BlockedTime::query()
            ->where('business_id', $business->id)
            ->whereDate('date', $date)
            ->get()
            ->map(fn (BlockedTime $block) => [
                'start' => $this->dateTime($date, $block->start_time, $business->timezone),
                'end' => $this->dateTime($date, $block->end_time, $business->timezone),
            ]);

        return $bookings->concat($blocks)->values();
    }

    private function overlapsBusy(CarbonImmutable $start, CarbonImmutable $end, Collection $busy): bool
    {
        return $busy->contains(fn (array $item) => $start->lessThan($item['end']) && $item['start']->lessThan($end));
    }

    private function dateTime(string $date, string $time, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' '.substr($time, 0, 5), $timezone);
    }
}
