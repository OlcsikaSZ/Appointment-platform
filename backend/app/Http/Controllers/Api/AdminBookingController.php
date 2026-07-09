<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBookingController extends Controller
{
    public function __construct(private readonly SlotService $slotService)
    {
    }

    public function index(Request $request, Business $business): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['booked', 'cancelled', 'completed', 'no_show'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $business->bookings()
                ->with('service')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('date', $date))
                ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                    fn ($inner) => $inner
                        ->where('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_contact', 'like', "%{$q}%")
                        ->orWhere('customer_note', 'like', "%{$q}%")
                ))
                ->orderByDesc('date')
                ->orderByDesc('start_time')
                ->limit(300)
                ->get(),
        ]);
    }

    public function summary(Business $business): JsonResponse
    {
        return response()->json([
            'data' => [
                'total' => $business->bookings()->count(),
                'active' => $business->bookings()->where('status', Booking::STATUS_BOOKED)->count(),
                'today' => $business->bookings()->whereDate('date', today())->where('status', Booking::STATUS_BOOKED)->count(),
                'cancelled' => $business->bookings()->where('status', Booking::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function today(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->bookings()
                ->with('service')
                ->whereDate('date', today())
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    public function calendar(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        $start = CarbonImmutable::parse($validated['start'])->startOfDay();
        $end = CarbonImmutable::parse($validated['end'])->startOfDay();

        abort_if($start->diffInDays($end) > 31, 422, 'Legfeljebb 31 nap kérhető le egyszerre.');

        return response()->json([
            'data' => $business->bookings()
                ->with('service')
                ->whereBetween('date', [$validated['start'], $validated['end']])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get(),
            'blocks' => $business->blockedTimes()
                ->whereBetween('date', [$validated['start'], $validated['end']])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    public function slots(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::where('business_id', $business->id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->slotsFor($business, $service, $validated['date']),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_contact' => ['required', 'string', 'max:160'],
            'customer_note' => ['nullable', 'string', 'max:800'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        return $this->withDayLock($business, $validated['date'], function () use ($business, $service, $validated): JsonResponse {
            $slot = collect($this->slotService->slotsFor($business, $service, $validated['date']))
                ->firstWhere('time', $validated['time']);

            if (! $slot) {
                return response()->json(['message' => 'Ez az időpont már nem elérhető.'], 409);
            }

            try {
                $booking = Booking::create([
                    'business_id' => $business->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'date' => $validated['date'],
                    'start_time' => $slot['time'],
                    'end_time' => $slot['endTime'],
                    'busy_until' => $slot['busyUntil'],
                    'customer_name' => $validated['customer_name'],
                    'customer_contact' => $validated['customer_contact'],
                    'customer_note' => $validated['customer_note'] ?? null,
                    'manage_token' => Str::random(64),
                    'status' => Booking::STATUS_BOOKED,
                ]);
            } catch (QueryException) {
                return response()->json(['message' => 'Ez az időpont közben betelt.'], 409);
            }

            return response()->json(['data' => $booking->fresh('service')], 201);
        });
    }

    public function block(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        $block = BlockedTime::create([
            ...$validated,
            'business_id' => $business->id,
        ]);

        return response()->json(['data' => $block], 201);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Booking::STATUSES)],
        ]);

        if ($validated['status'] === Booking::STATUS_BOOKED && $booking->status !== Booking::STATUS_BOOKED) {
            return $this->withDayLock($booking->business, $booking->date->format('Y-m-d'), function () use ($booking, $validated): JsonResponse {
                $available = collect($this->slotService->slotsFor($booking->business, $booking->service, $booking->date->format('Y-m-d'), $booking->id))
                    ->contains(fn ($slot) => $slot['time'] === substr($booking->start_time, 0, 5));

                if (! $available) {
                    return response()->json(['message' => 'Ezt az időpontot nem lehet visszaaktiválni, mert már foglalt vagy blokkolt.'], 409);
                }

                $booking->update(['status' => $validated['status'], 'cancelled_at' => null]);

                return response()->json(['data' => $booking->fresh('service')]);
            });
        }

        $payload = ['status' => $validated['status']];
        if ($validated['status'] === Booking::STATUS_CANCELLED) {
            $payload['cancelled_at'] = now();
        }

        $booking->update($payload);

        return response()->json(['data' => $booking->fresh('service')]);
    }

    public function blockedTimes(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->blockedTimes()
                ->whereDate('date', '>=', today()->subDays(7))
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(150)
                ->get(),
        ]);
    }

    public function destroyBlock(BlockedTime $blockedTime): JsonResponse
    {
        $blockedTime->delete();

        return response()->json(['message' => 'Blokk torolve.']);
    }

    private function withDayLock(Business $business, string $date, callable $callback): JsonResponse
    {
        $lockName = "booking:{$business->id}:{$date}";
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if (! $lock || (int) $lock->acquired !== 1) {
            return response()->json(['message' => 'Most túl sok foglalási művelet fut erre a napra, próbáld újra pár másodperc múlva.'], 409);
        }

        try {
            return DB::transaction(fn () => $callback());
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
