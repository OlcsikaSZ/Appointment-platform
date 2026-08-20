<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Rules\PersonName;
use App\Services\BookingDayLockService;
use App\Services\BookingMailService;
use App\Services\BookingRuleService;
use App\Services\DataRetentionService;
use App\Services\SlotService;
use App\Services\CustomerProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBookingController extends Controller
{
    use AuthorizesBusinessAccess;

    public function __construct(
        private readonly SlotService $slotService,
        private readonly BookingMailService $bookingMailService,
        private readonly BookingDayLockService $bookingDayLockService,
        private readonly BookingRuleService $bookingRuleService,
        private readonly DataRetentionService $dataRetentionService,
        private readonly CustomerProfileService $customerProfileService,
    ) {
    }

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Booking::STATUSES)],
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
                ->limit(500)
                ->get(),
        ]);
    }

    public function summary(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $today = CarbonImmutable::now($business->timezone)->toDateString();

        return response()->json([
            'data' => [
                'total' => $business->bookings()->count(),
                'active' => $business->bookings()->where('status', Booking::STATUS_BOOKED)->count(),
                'today' => $business->bookings()->whereDate('date', $today)->where('status', Booking::STATUS_BOOKED)->count(),
                'cancelled' => $business->bookings()->where('status', Booking::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function today(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $today = CarbonImmutable::now($business->timezone)->toDateString();

        return response()->json([
            'data' => $business->bookings()
                ->with('service')
                ->whereDate('date', $today)
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    public function calendar(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        $start = CarbonImmutable::parse($validated['start'], $business->timezone)->startOfDay();
        $end = CarbonImmutable::parse($validated['end'], $business->timezone)->startOfDay();

        abort_if($start->greaterThan($end), 422, 'A kezdő dátum nem lehet későbbi a záró dátumnál.');
        abort_if($start->diffInDays($end) > 41, 422, 'Legfeljebb 42 nap kérhető le egyszerre.');

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

    public function availabilityCalendar(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d'],
        ]);

        $start = CarbonImmutable::parse($validated['start'], $business->timezone)->startOfDay();
        $end = CarbonImmutable::parse($validated['end'], $business->timezone)->startOfDay();
        abort_if($start->greaterThan($end), 422, 'A kezdő dátum nem lehet későbbi a záró dátumnál.');
        abort_if($start->diffInDays($end) > 62, 422, 'Legfeljebb 63 nap kérhető le egyszerre.');

        $service = Service::where('business_id', $business->id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        return response()->json([
            'data' => $this->slotService->availabilitySummaryForRange(
                $business,
                $service,
                $validated['start'],
                $validated['end'],
            ),
        ]);
    }

    /**
     * Egyetlen nap teljes admin-naptár adata: foglalások, blokkok és nyitvatartási sávok.
     */
    public function day(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $day = CarbonImmutable::parse($validated['date'], $business->timezone)->startOfDay();
        $weekday = (int) $day->dayOfWeek;

        return response()->json([
            'data' => [
                'date' => $validated['date'],
                'bookings' => $business->bookings()
                    ->with('service')
                    ->whereDate('date', $validated['date'])
                    ->orderBy('start_time')
                    ->get(),
                'blocks' => $business->blockedTimes()
                    ->whereDate('date', $validated['date'])
                    ->orderBy('start_time')
                    ->get(),
                'workingHours' => $business->workingHours()
                    ->where('weekday', $weekday)
                    ->orderBy('start_time')
                    ->get(['start_time', 'end_time']),
            ],
        ]);
    }

    public function slots(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

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
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', new PersonName()],
            'customer_contact' => ['required', 'string', 'email:rfc', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_note' => ['nullable', 'string', 'min:3', 'max:800'],
        ]);

        $service = Service::where('business_id', $business->id)->where('active', true)->findOrFail($validated['service_id']);

        $createdBooking = null;

        $response = $this->bookingDayLockService->run($business, $validated['date'], function () use ($business, $service, $validated, &$createdBooking): JsonResponse {
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
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'customer_note' => $validated['customer_note'] ?? null,
                    'manage_token' => Str::random(64),
                    'manage_token_expires_at' => $this->bookingRuleService->manageTokenExpiresAt($business, $validated['date']),
                    'status' => Booking::STATUS_BOOKED,
                ]);
            } catch (QueryException) {
                return response()->json(['message' => 'Ez az időpont közben betelt.'], 409);
            }

            $createdBooking = $this->customerProfileService->syncBooking(
                $booking->fresh(['business', 'service'])
            );

            return response()->json(['data' => $createdBooking], 201);
        });

        if ($createdBooking) {
            $this->bookingMailService->bookingCreated($createdBooking);
        }

        return $response;
    }

    /**
     * Többnapos, részleges vagy egész napos blokkolás.
     * Aktív foglalás-ütközésnél először megerősítést kér a frontendtől.
     */
    public function block(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'start_date' => ['nullable', 'required_without:date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'date' => ['nullable', 'required_without:start_date', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:160'],
            'all_day' => ['sometimes', 'boolean'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $startDate = $validated['start_date'] ?? $validated['date'];
        $endDate = $validated['end_date'] ?? $startDate;
        $start = CarbonImmutable::parse($startDate, $business->timezone)->startOfDay();
        $end = CarbonImmutable::parse($endDate, $business->timezone)->startOfDay();
        $allDay = (bool) ($validated['all_day'] ?? false);
        $force = (bool) ($validated['force'] ?? false);

        abort_if($start->greaterThan($end), 422, 'A záró dátum nem lehet korábbi a kezdő dátumnál.');
        abort_if($start->diffInDays($end) > 366, 422, 'Egyszerre legfeljebb 367 nap blokkolható.');

        $startTime = $allDay ? '00:00' : ($validated['start_time'] ?? null);
        $endTime = $allDay ? '23:59' : ($validated['end_time'] ?? null);

        abort_if(! $allDay && (! $startTime || ! $endTime), 422, 'Részleges blokkolásnál a kezdés és a befejezés kötelező.');
        abort_if(! $allDay && $startTime >= $endTime, 422, 'A blokkolás vége későbbi legyen a kezdésnél.');

        $lockDates = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $lockDates[] = $cursor->format('Y-m-d');
        }

        return $this->bookingDayLockService->run($business, $lockDates, function () use (
            $business,
            $startDate,
            $endDate,
            $start,
            $end,
            $startTime,
            $endTime,
            $validated,
            $allDay,
            $force,
        ): JsonResponse {
            $conflicts = $business->bookings()
                ->where('status', Booking::STATUS_BOOKED)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('start_time', '<', $endTime)
                ->where('busy_until', '>', $startTime)
                ->orderBy('date')
                ->orderBy('start_time')
                ->get([
                    'id',
                    'date',
                    'start_time',
                    'end_time',
                    'customer_name',
                    'service_name',
                ]);

            if ($conflicts->isNotEmpty() && ! $force) {
                return response()->json([
                    'message' => "Ebben az időszakban {$conflicts->count()} meglévő aktív foglalás található. Biztosan folytatod?",
                    'requires_confirmation' => true,
                    'conflict_count' => $conflicts->count(),
                    'conflicts' => $conflicts->take(20)->values(),
                ], 409);
            }

            $items = collect();

            for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
                $items->push(BlockedTime::create([
                    'business_id' => $business->id,
                    'date' => $cursor->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'reason' => $validated['reason'] ?? null,
                    'is_all_day' => $allDay,
                ]));
            }

            return response()->json([
                'data' => $items,
                'count' => $items->count(),
                'conflict_count' => $conflicts->count(),
            ], 201);
        });
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeBusinessId($request, (int) $booking->business_id);
        $originalStatus = $booking->status;

        $validated = $request->validate([
            'status' => ['required', Rule::in(Booking::STATUSES)],
        ]);

        if ($validated['status'] === Booking::STATUS_BOOKED && $booking->status !== Booking::STATUS_BOOKED) {
            return $this->bookingDayLockService->run($booking->business, $booking->date->format('Y-m-d'), function () use ($booking, $validated): JsonResponse {
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
        $fresh = $booking->fresh(['business', 'service']);

        if ($validated['status'] === Booking::STATUS_CANCELLED && $originalStatus !== Booking::STATUS_CANCELLED) {
            $this->bookingMailService->bookingCancelled($fresh);
        }

        return response()->json(['data' => $fresh]);
    }

    public function emailLogs(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $business->emailLogs()
                ->with('booking:id,customer_name,service_name,date,start_time')
                ->latest('id')
                ->limit(200)
                ->get(),
        ]);
    }

    public function blockedTimes(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $oldestDate = CarbonImmutable::now($business->timezone)->subDays(7)->toDateString();

        return response()->json([
            'data' => $business->blockedTimes()
                ->whereDate('date', '>=', $oldestDate)
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(500)
                ->get(),
        ]);
    }

    public function destroyBlock(Request $request, BlockedTime $blockedTime): JsonResponse
    {
        $this->authorizeBusinessId($request, (int) $blockedTime->business_id);
        $blockedTime->delete();

        return response()->json(['message' => 'Blokk törölve.']);
    }


    public function anonymize(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeBusinessId($request, (int) $booking->business_id);
        $booking->loadMissing('business', 'service');

        $appointmentIsPast = CarbonImmutable::parse(
            $booking->date->format('Y-m-d').' '.substr((string) $booking->end_time, 0, 5),
            $booking->business->timezone,
        )->isPast();

        if ($booking->status === Booking::STATUS_BOOKED && ! $appointmentIsPast) {
            return response()->json([
                'message' => 'Aktív, jövőbeli foglalás személyes adatai csak a foglalás lemondása után törölhetők.',
            ], 409);
        }

        $fresh = $this->dataRetentionService->anonymizeBooking($booking);

        return response()->json([
            'data' => $fresh->load('service'),
            'message' => 'A foglalás személyes adatai visszafordíthatatlanul anonimizálva lettek.',
        ]);
    }


}
