<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AdminStatisticsService
{
    public function forMonth(Business $business, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $business->timezone)->startOfMonth();
        $end = $start->endOfMonth();
        $current = $this->calculate($business, $start, $end);
        $previousStart = $start->subMonthNoOverflow()->startOfMonth();
        $previous = $this->calculate($business, $previousStart, $previousStart->endOfMonth(), false);

        $current['comparison'] = [
            'month' => $previousStart->format('Y-m'),
            'total_bookings' => $previous['total_bookings'],
            'estimated_revenue' => $previous['estimated_revenue'],
            'utilization_rate' => $previous['utilization_rate'],
            'booking_change_percent' => $this->change($current['total_bookings'], $previous['total_bookings']),
            'revenue_change_percent' => $this->change($current['estimated_revenue'], $previous['estimated_revenue']),
        ];
        return $current;
    }

    private function calculate(Business $business, CarbonImmutable $start, CarbonImmutable $end, bool $details = true): array
    {
        $bookings = $business->bookings()->with('service:id,name,price_cents,price_mode')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->orderBy('start_time')->get();
        $total = $bookings->count();
        $statusCounts = collect(Booking::STATUSES)->mapWithKeys(
            fn (string $status) => [$status => $bookings->where('status', $status)->count()]
        )->all();
        $revenueBookings = $bookings->whereIn('status', [Booking::STATUS_BOOKED, Booking::STATUS_COMPLETED]);
        $revenue = round($revenueBookings->sum(fn (Booking $booking) => $booking->estimatedRevenueCents() / 100), 2);
        [$availableMinutes, $occupiedMinutes] = $this->capacity($business, $start, $end, $bookings);

        $topServices = $bookings->where('status', '!=', Booking::STATUS_CANCELLED)
            ->groupBy(fn (Booking $booking) => $booking->service_name ?: $booking->service?->name ?: 'Törölt szolgáltatás')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'bookings' => $items->count(),
                'revenue' => round($items->whereIn('status', [Booking::STATUS_BOOKED, Booking::STATUS_COMPLETED])
                    ->sum(fn (Booking $booking) => $booking->estimatedRevenueCents() / 100), 2),
            ])->sortByDesc('bookings')->values()->take(10)->all();

        return [
            'month' => $start->format('Y-m'),
            'label' => ucfirst($start->locale('hu')->translatedFormat('Y. F')),
            'total_bookings' => $total,
            'status_counts' => $statusCounts,
            'cancellation_rate' => $total ? round($statusCounts[Booking::STATUS_CANCELLED] / $total * 100, 1) : 0.0,
            'no_show_rate' => $total ? round($statusCounts[Booking::STATUS_NO_SHOW] / $total * 100, 1) : 0.0,
            'estimated_revenue' => $revenue,
            'available_minutes' => $availableMinutes,
            'occupied_minutes' => $occupiedMinutes,
            'utilization_rate' => $availableMinutes ? round(min(100, $occupiedMinutes / $availableMinutes * 100), 1) : 0.0,
            'top_services' => $topServices,
            'daily' => $details ? $this->dailyRows($start, $end, $bookings) : [],
        ];
    }

    private function dailyRows(CarbonImmutable $start, CarbonImmutable $end, Collection $bookings): array
    {
        $rows = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $items = $bookings->filter(fn (Booking $booking) => $booking->date->toDateString() === $day->toDateString());
            $rows[] = [
                'date' => $day->toDateString(),
                'day' => ucfirst($day->locale('hu')->translatedFormat('l')),
                'total' => $items->count(),
                'booked' => $items->where('status', Booking::STATUS_BOOKED)->count(),
                'completed' => $items->where('status', Booking::STATUS_COMPLETED)->count(),
                'cancelled' => $items->where('status', Booking::STATUS_CANCELLED)->count(),
                'no_show' => $items->where('status', Booking::STATUS_NO_SHOW)->count(),
                'revenue' => round($items->whereIn('status', [Booking::STATUS_BOOKED, Booking::STATUS_COMPLETED])
                    ->sum(fn (Booking $booking) => $booking->estimatedRevenueCents() / 100), 2),
            ];
        }
        return $rows;
    }

    private function capacity(Business $business, CarbonImmutable $start, CarbonImmutable $end, Collection $bookings): array
    {
        $hours = $business->workingHours()->get()->groupBy('weekday');
        $blocks = $business->blockedTimes()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->groupBy(
            fn ($block) => $block->date->toDateString()
        );
        $available = 0;
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            foreach ($hours->get($day->dayOfWeek, collect()) as $window) {
                $windowStart = $this->minutes((string) $window->start_time);
                $windowEnd = $this->minutes((string) $window->end_time);
                $minutes = max(0, $windowEnd - $windowStart);
                $blockedIntervals = [];
                foreach ($blocks->get($day->toDateString(), collect()) as $block) {
                    if ($block->is_all_day) { $minutes = 0; break; }
                    $from = max($windowStart, $this->minutes((string) $block->start_time));
                    $to = min($windowEnd, $this->minutes((string) $block->end_time));
                    if ($to > $from) $blockedIntervals[] = [$from, $to];
                }
                usort($blockedIntervals, fn (array $a, array $b) => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($blockedIntervals as $interval) {
                    $last = array_key_last($merged);
                    if ($last === null || $interval[0] > $merged[$last][1]) $merged[] = $interval;
                    else $merged[$last][1] = max($merged[$last][1], $interval[1]);
                }
                $minutes = max(0, $minutes - array_sum(array_map(fn (array $interval) => $interval[1] - $interval[0], $merged)));
                $available += $minutes;
            }
        }
        $occupied = $bookings->whereIn('status', [Booking::STATUS_BOOKED, Booking::STATUS_COMPLETED, Booking::STATUS_NO_SHOW])
            ->sum(fn (Booking $booking) => max(0, $this->minutes((string) ($booking->busy_until ?: $booking->end_time))
                - $this->minutes((string) $booking->start_time)));
        return [$available, (int) $occupied];
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        return $hours * 60 + $minutes;
    }

    private function change(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) return (float) $current === 0.0 ? 0.0 : null;
        return round(($current - $previous) / $previous * 100, 1);
    }
}
