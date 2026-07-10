<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $business->services()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $this->validatedService($request);

        $service = $business->services()->create($validated);

        return response()->json(['data' => $service], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $this->validatedService($request, true);

        $service->update($validated);

        return response()->json(['data' => $service->fresh()]);
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->bookings()->exists()) {
            $service->update(['active' => false]);
            return response()->json(['message' => 'A szolgáltatáshoz már van foglalás, ezért törlés helyett inaktívra állítottuk.', 'data' => $service->fresh()]);
        }

        $service->delete();

        return response()->json(['message' => 'Szolgáltatás törölve.']);
    }

    public function reorder(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        foreach ($validated['items'] as $item) {
            Service::where('business_id', $business->id)
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return $this->index($business);
    }

    private function validatedService(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'category' => [$partial ? 'sometimes' : 'nullable', 'string', 'max:80'],
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => [$required, 'integer', 'min:5', 'max:1440'],
            'buffer_minutes' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:240'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:999999900'],
            'active' => [$partial ? 'sometimes' : 'nullable', 'boolean'],
            'sort_order' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }
}
