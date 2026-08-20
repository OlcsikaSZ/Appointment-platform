<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Services\BookingMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminEmailController extends Controller
{
    use AuthorizesBusinessAccess;

    public function __construct(
        private readonly BookingMailService $bookingMailService,
    ) {
    }

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    EmailLog::STATUS_PENDING,
                    EmailLog::STATUS_SENT,
                    EmailLog::STATUS_FAILED,
                    EmailLog::STATUS_SKIPPED,
                ]),
            ],

            'event_type' => [
                'nullable',
                Rule::in([
                    ...EmailSetting::EVENT_TYPES,
                    'email_test',
                ]),
            ],

            'recipient_type' => [
                'nullable',
                Rule::in(EmailSetting::RECIPIENT_TYPES),
            ],

            'q' => [
                'nullable',
                'string',
                'max:160',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([10, 20, 50, 100]),
            ],
        ]);

        $query = $business->emailLogs()
            ->with('booking:id,customer_name,customer_contact,customer_note,service_name,date,start_time,end_time,status,manage_token');

        $query
            ->when($validated['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($validated['event_type'] ?? null, fn ($builder, $eventType) => $builder->where('event_type', $eventType))
            ->when($validated['recipient_type'] ?? null, fn ($builder, $recipientType) => $builder->where('recipient_type', $recipientType))
            ->when($validated['q'] ?? null, function ($builder, $search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('recipient_email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('booking', function ($bookingQuery) use ($search): void {
                            $bookingQuery
                                ->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_contact', 'like', "%{$search}%")
                                ->orWhere('service_name', 'like', "%{$search}%");
                        });
                });
            });

        $base = $business->emailLogs();
        $total = (clone $base)->count();
        $pending = (clone $base)->where('status', EmailLog::STATUS_PENDING)->count();
        $sent = (clone $base)->where('status', EmailLog::STATUS_SENT)->count();
        $failed = (clone $base)->where('status', EmailLog::STATUS_FAILED)->count();

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        $paginator = $query
            ->latest('id')
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page,
            );

        return response()->json([
            'data' => $paginator->items(),

            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'has_more_pages' => $paginator->hasMorePages(),
            ],

            'stats' => [
                'total' => $total,
                'pending' => $pending,
                'sent' => $sent,
                'failed' => $failed,

                'success_rate' => $total > 0
                    ? round(($sent / $total) * 100, 1)
                    : 0,

                'last_sent_at' => (clone $base)
                    ->where('status', EmailLog::STATUS_SENT)
                    ->max('sent_at'),

                'last_failed_at' => (clone $base)
                    ->where('status', EmailLog::STATUS_FAILED)
                    ->max('created_at'),
            ],

            'system' => $this->systemInfo(),
        ]);
    }

    public function showSettings(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $business->loadMissing('emailSetting');

        return response()->json([
            'data' => EmailSetting::resolvedForBusiness($business),
            'defaults' => EmailSetting::defaults(),
            'system' => $this->systemInfo(),
        ]);
    }

    public function updateSettings(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'sender_name' => ['nullable', 'string', 'max:160'],
            'reply_to' => ['nullable', 'email:rfc', 'max:160'],
            'footer_text' => ['nullable', 'string', 'max:1200'],
            'templates' => ['required', 'array'],
            'templates.customer' => ['required', 'array'],
            'templates.admin' => ['required', 'array'],
            'templates.*.*.subject' => ['required', 'string', 'max:255'],
            'templates.*.*.intro' => ['required', 'string', 'max:1500'],
        ]);

        $settings = EmailSetting::normalize($validated);

        $record = $business->emailSetting()->updateOrCreate(
            ['business_id' => $business->id],
            ['settings' => $settings],
        );

        return response()->json([
            'data' => EmailSetting::normalize($record->settings ?? []),
        ]);
    }

    public function sendTest(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email:rfc', 'max:160'],
            'recipient_type' => ['required', Rule::in(EmailSetting::RECIPIENT_TYPES)],
            'event_type' => ['required', Rule::in(EmailSetting::EVENT_TYPES)],
        ]);

        $log = $this->bookingMailService->sendTest(
            business: $business,
            recipientEmail: $validated['recipient_email'],
            recipientType: $validated['recipient_type'],
            eventType: $validated['event_type'],
        );

        if (! $log) {
            return response()->json(['message' => 'A teszt email küldése nem naplózható. Ellenőrizd az email_logs táblát és a migrációkat.'], 500);
        }

        return response()->json([
            'data' => $log->load('booking:id,customer_name,service_name,date,start_time,end_time'),
            'message' => match ($log->status) {
                EmailLog::STATUS_SENT => 'Teszt email elküldve.',
                EmailLog::STATUS_PENDING => 'A teszt email várólistára került.',
                default => 'A teszt email küldése sikertelen volt.',
            },
        ], 201);
    }

    public function resend(Request $request, EmailLog $emailLog): JsonResponse
    {
        $this->authorizeBusinessId($request, $emailLog->business_id ? (int) $emailLog->business_id : null);

        $newLog = $this->bookingMailService->resendFromLog($emailLog);

        if (! $newLog) {
            return response()->json([
                'message' => 'Ez az email nem küldhető újra, mert hiányzik a foglalási vagy a naplózott email-adat.',
            ], 422);
        }

        return response()->json([
            'data' => $newLog->load('booking:id,customer_name,service_name,date,start_time,end_time'),
            'message' => match ($newLog->status) {
                EmailLog::STATUS_SENT => 'Az email újraküldése sikeres volt.',
                EmailLog::STATUS_PENDING => 'Az email újraküldése várólistára került.',
                default => 'Az újraküldés sikertelen volt.',
            },
        ], 201);
    }

    private function systemInfo(): array
    {
        $queueConnection = (string) config('queue.default');
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $oldestPendingAt = Schema::hasTable('jobs')
            ? DB::table('jobs')->min('created_at')
            : null;
        $oldestPendingAgeSeconds = $oldestPendingAt
            ? max(0, time() - (int) $oldestPendingAt)
            : null;

        return [
            'mailer' => (string) config('mail.default'),
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
            'public_url' => (string) config('appointment.public_url'),
            'queue_connection' => $queueConnection,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'oldest_pending_at' => $oldestPendingAt ? date(DATE_ATOM, (int) $oldestPendingAt) : null,
            'oldest_pending_age_seconds' => $oldestPendingAgeSeconds,
            'worker_warning' => $queueConnection !== 'sync'
                && $pendingJobs !== null
                && $pendingJobs > 0
                && $oldestPendingAgeSeconds !== null
                && $oldestPendingAgeSeconds >= 300,
        ];
    }
}
