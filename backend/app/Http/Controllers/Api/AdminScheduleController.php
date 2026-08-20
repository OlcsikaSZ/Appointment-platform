<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminScheduleController extends Controller
{
    use AuthorizesBusinessAccess;

    private const DAY_LABELS = [
        1 => 'Hétfő',
        2 => 'Kedd',
        3 => 'Szerda',
        4 => 'Csütörtök',
        5 => 'Péntek',
        6 => 'Szombat',
        0 => 'Vasárnap',
    ];

    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $this->weekPayload($business),
            'public_opening_hours' => $business->opening_hours,
        ]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.closed' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_enabled' => ['required', 'boolean'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
            'sync_public_text' => ['sometimes', 'boolean'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $days = collect($validated['days'])->keyBy('weekday');
        $errors = [];

        foreach (array_keys(self::DAY_LABELS) as $weekday) {
            $day = $days->get($weekday);
            if (! $day) {
                $errors["days.{$weekday}"][] = 'Minden napot meg kell adni.';
                continue;
            }

            if ($day['closed']) {
                continue;
            }

            if (empty($day['start_time']) || empty($day['end_time'])) {
                $errors["days.{$weekday}.start_time"][] = 'Nyitott napnál a kezdés és a befejezés kötelező.';
                continue;
            }

            if ($day['start_time'] >= $day['end_time']) {
                $errors["days.{$weekday}.end_time"][] = 'A munkaidő vége későbbi legyen a kezdésnél.';
            }

            if ($day['break_enabled']) {
                if (empty($day['break_start']) || empty($day['break_end'])) {
                    $errors["days.{$weekday}.break_start"][] = 'Bekapcsolt szünetnél a szünet kezdete és vége kötelező.';
                    continue;
                }

                if (! ($day['start_time'] < $day['break_start']
                    && $day['break_start'] < $day['break_end']
                    && $day['break_end'] < $day['end_time'])) {
                    $errors["days.{$weekday}.break_start"][] = 'A szünetnek teljes egészében a munkaidőn belül kell lennie.';
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $desiredRanges = $this->desiredRanges($days);
        $conflicts = $business->bookings()
            ->where('status', Booking::STATUS_BOOKED)
            ->whereDate('date', '>=', CarbonImmutable::now($business->timezone)->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (Booking $booking) use ($desiredRanges): bool {
                $weekday = (int) $booking->date->dayOfWeek;
                $ranges = $desiredRanges[$weekday] ?? [];
                $start = substr($booking->start_time, 0, 5);
                $busyUntil = substr($booking->busy_until, 0, 5);

                return ! collect($ranges)->contains(
                    fn (array $range) => $start >= $range['start_time'] && $busyUntil <= $range['end_time']
                );
            })
            ->values();

        if ($conflicts->isNotEmpty() && ! ($validated['force'] ?? false)) {
            return response()->json([
                'message' => "Az új munkaidő {$conflicts->count()} meglévő aktív foglalást munkaidőn kívülre helyezne. Biztosan folytatod?",
                'requires_confirmation' => true,
                'conflict_count' => $conflicts->count(),
                'conflicts' => $conflicts->take(20)->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'date' => $booking->date->format('Y-m-d'),
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'customer_name' => $booking->customer_name,
                    'service_name' => $booking->service_name,
                ])->values(),
            ], 409);
        }

        DB::transaction(function () use ($business, $days, $validated): void {
            $business->workingHours()->delete();

            foreach (array_keys(self::DAY_LABELS) as $weekday) {
                $day = $days->get($weekday);
                if ($day['closed']) {
                    continue;
                }

                if ($day['break_enabled']) {
                    WorkingHour::create([
                        'business_id' => $business->id,
                        'weekday' => $weekday,
                        'start_time' => $day['start_time'],
                        'end_time' => $day['break_start'],
                    ]);
                    WorkingHour::create([
                        'business_id' => $business->id,
                        'weekday' => $weekday,
                        'start_time' => $day['break_end'],
                        'end_time' => $day['end_time'],
                    ]);
                } else {
                    WorkingHour::create([
                        'business_id' => $business->id,
                        'weekday' => $weekday,
                        'start_time' => $day['start_time'],
                        'end_time' => $day['end_time'],
                    ]);
                }
            }

            if ($validated['sync_public_text'] ?? true) {
                $business->update([
                    'opening_hours' => $this->publicOpeningHoursText($days),
                ]);
            }
        });

        return response()->json([
            'data' => $this->weekPayload($business->fresh()),
            'public_opening_hours' => $business->fresh()->opening_hours,
        ]);
    }

    private function weekPayload(Business $business): array
    {
        $grouped = $business->workingHours()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy('weekday');

        $days = [];
        foreach (self::DAY_LABELS as $weekday => $label) {
            $ranges = $grouped->get($weekday, collect())->values();
            $first = $ranges->first();
            $last = $ranges->last();
            $breakEnabled = $ranges->count() >= 2
                && substr($first->end_time, 0, 5) < substr($last->start_time, 0, 5);

            $days[] = [
                'weekday' => $weekday,
                'label' => $label,
                'closed' => $ranges->isEmpty(),
                'start_time' => $first ? substr($first->start_time, 0, 5) : '09:00',
                'end_time' => $last ? substr($last->end_time, 0, 5) : '17:00',
                'break_enabled' => $breakEnabled,
                'break_start' => $breakEnabled ? substr($first->end_time, 0, 5) : '12:00',
                'break_end' => $breakEnabled ? substr($last->start_time, 0, 5) : '13:00',
            ];
        }

        return $days;
    }

    private function desiredRanges($days): array
    {
        $result = [];

        foreach (array_keys(self::DAY_LABELS) as $weekday) {
            $day = $days->get($weekday);
            if ($day['closed']) {
                $result[$weekday] = [];
                continue;
            }

            $result[$weekday] = $day['break_enabled']
                ? [
                    ['start_time' => $day['start_time'], 'end_time' => $day['break_start']],
                    ['start_time' => $day['break_end'], 'end_time' => $day['end_time']],
                ]
                : [['start_time' => $day['start_time'], 'end_time' => $day['end_time']]];
        }

        return $result;
    }

    private function publicOpeningHoursText($days): string
    {
        return collect(self::DAY_LABELS)
            ->map(function (string $label, int $weekday) use ($days): string {
                $day = $days->get($weekday);
                if ($day['closed']) {
                    return "{$label}: zárva";
                }

                $ranges = $day['break_enabled']
                    ? "{$day['start_time']}–{$day['break_start']}, {$day['break_end']}–{$day['end_time']}"
                    : "{$day['start_time']}–{$day['end_time']}";

                return "{$label}: {$ranges}";
            })
            ->implode("\n");
    }
}
