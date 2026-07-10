<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Services\SlotService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicBookingController extends Controller
{
    public function __construct(private readonly SlotService $slotService)
    {
    }

    public function business(Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        return response()->json([
            'data' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'tagline' => $business->tagline,
                'heroTitle' => $business->hero_title,
                'heroText' => $business->hero_text,
                'aboutTitle' => $business->about_title,
                'aboutText' => $business->about_text,
                'phone' => $business->phone,
                'email' => $business->email,
                'address' => $business->address,
                'openingHours' => $business->opening_hours,
                'googleMapsUrl' => $business->google_maps_url,
                'logoUrl' => $business->logo_path,
                'timezone' => $business->timezone,
                'primaryColor' => $business->primary_color,
                'logoText' => $business->logo_text,
                'reviews' => $business->reviews()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'author', 'text', 'rating']),
                'faqs' => $business->faqs()
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'question', 'answer']),
            ],
        ]);
    }

    public function services(Business $business): JsonResponse
    {
        abort_unless($business->active, 404);

        return response()->json([
            'data' => $business->services()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function slots(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->slotsFor($business, $service, $validated['date']),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('business_id', $business->id)->where('active', true))],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_contact' => ['required', 'string', 'max:160'],
            'customer_note' => ['nullable', 'string', 'max:800'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        return $this->withBookingDateLock($business, $validated['date'], function () use ($business, $service, $validated): JsonResponse {
            return DB::transaction(function () use ($business, $service, $validated): JsonResponse {
                $slot = collect($this->slotService->slotsFor($business, $service, $validated['date']))
                    ->firstWhere('time', $validated['time']);

                if (! $slot) {
                    return response()->json(['message' => 'A kiválasztott időpont már nem elérhető.'], 409);
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
                } catch (QueryException $exception) {
                    if ($this->isDuplicateActiveSlot($exception)) {
                        return response()->json(['message' => 'A kiválasztott időpont közben foglalttá vált.'], 409);
                    }

                    throw $exception;
                }

                return response()->json([
                    'data' => $booking,
                    'manageUrl' => './manage?token='.$booking->manage_token,
                ], 201);
            });
        });
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json(['data' => $booking->load('business', 'service')]);
    }

    public function manageSlots(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        return response()->json([
            'data' => $this->slotService->slotsFor(
                $booking->business,
                $booking->service,
                $validated['date'],
                $booking->id
            ),
        ]);
    }

    public function cancel(Booking $booking): JsonResponse
    {
        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
        ]);

        return response()->json(['data' => $booking->fresh()]);
    }

    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        if ($booking->status !== Booking::STATUS_BOOKED) {
            return response()->json(['message' => 'Ez a foglalás már nem aktív.'], 409);
        }

        $business = $booking->business;
        $service = $booking->service;

        return $this->withBookingDateLock($business, $validated['date'], function () use ($booking, $business, $service, $validated): JsonResponse {
            return DB::transaction(function () use ($booking, $business, $service, $validated): JsonResponse {
                $slot = collect($this->slotService->slotsFor($business, $service, $validated['date'], $booking->id))
                    ->firstWhere('time', $validated['time']);

                if (! $slot) {
                    return response()->json(['message' => 'A kiválasztott időpont már nem elérhető.'], 409);
                }

                try {
                    $booking->update([
                        'date' => $validated['date'],
                        'start_time' => $slot['time'],
                        'end_time' => $slot['endTime'],
                        'busy_until' => $slot['busyUntil'],
                        'status' => Booking::STATUS_BOOKED,
                    ]);
                } catch (QueryException $exception) {
                    if ($this->isDuplicateActiveSlot($exception)) {
                        return response()->json(['message' => 'A kiválasztott időpont közben foglalttá vált.'], 409);
                    }

                    throw $exception;
                }

                return response()->json(['data' => $booking->fresh()]);
            });
        });
    }

    private function withBookingDateLock(Business $business, string $date, callable $callback): JsonResponse
    {
        $lockName = 'appointment-booking-'.$business->id.'-'.$date;
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            return response()->json(['message' => 'Az időpontfoglalás most foglalt. Próbáld újra pár másodperc múlva.'], 423);
        }

        try {
            return $callback();
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    private function isDuplicateActiveSlot(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'bookings_active_slot_key_unique')
            || str_contains($exception->getMessage(), 'active_slot_key')
            || str_contains((string) $exception->getCode(), '23000');
    }
}
