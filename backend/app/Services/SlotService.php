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
        $ranges = $business->workingHours()
            ->where('weekday', (int) $day->dayOfWeek)
            ->orderBy('start_time')
            ->get();
        [$minAllowed, $maxAllowed] = $this->bookingWindow($business);

        return $this->buildSlots(
            business: $business,
            service: $service,
            date: $date,
            ranges: $ranges,
            busy: $this->busyIntervals($business, $date, $excludeBookingId),
            minAllowed: $minAllowed,
            maxAllowed: $maxAllowed,
        );
    }

    public function availabilitySummaryForRange(
        Business $business,
        Service $service,
        string $startDate,
        string $endDate,
        ?int $excludeBookingId = null,
    ): array {
        $start = CarbonImmutable::parse($startDate, $business->timezone)->startOfDay();
        $end = CarbonImmutable::parse($endDate, $business->timezone)->startOfDay();
        [$minAllowed, $maxAllowed] = $this->bookingWindow($business);

        $workingHours = $business->workingHours()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy('weekday');

        $bookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', Booking::STATUS_BOOKED)
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get()
            ->groupBy(fn (Booking $booking) => $booking->date->format('Y-m-d'));

        $blocks = BlockedTime::query()
            ->where('business_id', $business->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn (BlockedTime $block) => $block->date->format('Y-m-d'));

        $summary = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->format('Y-m-d');
            /** @var Collection<int, mixed> $ranges */
            $ranges = $workingHours->get((int) $cursor->dayOfWeek, collect());
            $busy = $this->busyIntervalsFromCollections(
                business: $business,
                date: $date,
                bookings: $bookings->get($date, collect()),
                blocks: $blocks->get($date, collect()),
            );

            $slots = $this->buildSlots(
                business: $business,
                service: $service,
                date: $date,
                ranges: $ranges,
                busy: $busy,
                minAllowed: $minAllowed,
                maxAllowed: $maxAllowed,
            );

            $hasWorkingHours = $ranges->isNotEmpty();

            $summary[] = [
                'date' => $date,
                'available_count' => count($slots),
                'has_working_hours' => $hasWorkingHours,
                'is_fully_booked' => $hasWorkingHours && count($slots) === 0,
                'first_available_time' => $slots[0]['time'] ?? null,
                'outside_booking_window' => $cursor->endOfDay()->lessThan($minAllowed)
                    || $cursor->startOfDay()->greaterThan($maxAllowed),
            ];
        }

        return $summary;
    }

    /**
     * @param Collection<int, mixed> $ranges
     * @param Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}> $busy
     */
    private function buildSlots(
        Business $business,
        Service $service,
        string $date,
        Collection $ranges,
        Collection $busy,
        CarbonImmutable $minAllowed,
        CarbonImmutable $maxAllowed,
    ): array {
        $stepMinutes = max(5, (int) ($business->slot_interval_minutes ?: 15));
        $slots = [];

        foreach ($ranges as $range) {
            $cursor = $this->dateTime($date, $range->start_time, $business->timezone);
            $rangeEnd = $this->dateTime($date, $range->end_time, $business->timezone);

            while ($cursor->addMinutes($service->duration_minutes)->lessThanOrEqualTo($rangeEnd)) {
                $visibleEnd = $cursor->addMinutes($service->duration_minutes);
                $busyUntil = $visibleEnd->addMinutes($service->buffer_minutes);

                if (
                    $cursor->greaterThanOrEqualTo($minAllowed)
                    && $cursor->lessThanOrEqualTo($maxAllowed)
                    && ! $this->overlapsBusy($cursor, $busyUntil, $busy)
                ) {
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

    private function bookingWindow(Business $business): array
    {
        $now = CarbonImmutable::now($business->timezone);
        $minAllowed = $now->addMinutes(max(0, (int) ($business->min_advance_minutes ?? 60)));
        $maxAllowed = $now->addDays(max(1, (int) ($business->max_advance_days ?? 90)))->endOfDay();

        return [$minAllowed, $maxAllowed];
    }

    private function busyIntervals(Business $business, string $date, ?int $excludeBookingId = null): Collection
    {
        $bookings = Booking::query()
            ->where('business_id', $business->id)
            ->whereDate('date', $date)
            ->where('status', Booking::STATUS_BOOKED)
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get();

        $blocks = BlockedTime::query()
            ->where('business_id', $business->id)
            ->whereDate('date', $date)
            ->get();

        return $this->busyIntervalsFromCollections($business, $date, $bookings, $blocks);
    }

    private function busyIntervalsFromCollections(
        Business $business,
        string $date,
        Collection $bookings,
        Collection $blocks,
    ): Collection {
        $bookingIntervals = $bookings->map(fn (Booking $booking) => [
            'start' => $this->dateTime($date, $booking->start_time, $business->timezone),
            'end' => $this->dateTime($date, $booking->busy_until, $business->timezone),
        ]);

        $blockIntervals = $blocks->map(fn (BlockedTime $block) => [
            'start' => $this->dateTime($date, $block->start_time, $business->timezone),
            'end' => $this->dateTime($date, $block->end_time, $business->timezone),
        ]);

        return $bookingIntervals->concat($blockIntervals)->values();
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
