<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReminderController extends Controller
{
    use AuthorizesBusinessAccess;

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $business->reminderLogs()
                ->with('booking:id,customer_name,customer_contact,service_name,date,start_time,status')
                ->latest('id')
                ->limit(100)
                ->get(),
            'stats' => [
                'queued' => $business->reminderLogs()->where('status', 'queued')->count(),
                'sent' => $business->reminderLogs()->where('status', 'sent')->count(),
                'skipped' => $business->reminderLogs()->where('status', 'skipped')->count(),
                'failed' => $business->reminderLogs()->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function dispatchNow(Request $request, Business $business, ReminderService $service): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        return response()->json([
            'data' => $service->dispatchDue($business),
            'message' => 'Az esedékes emlékeztetők ellenőrzése befejeződött.',
        ]);
    }
}
