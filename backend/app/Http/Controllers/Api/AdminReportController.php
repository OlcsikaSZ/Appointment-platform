<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBusinessAccess;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Services\AdminStatisticsService;
use App\Services\SimpleXlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminReportController extends Controller
{
    use AuthorizesBusinessAccess;

    public function __construct(private readonly AdminStatisticsService $statistics) {}

    public function statistics(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $month = $this->validatedMonth($request, $business);
        return response()->json(['data' => $this->statistics->forMonth($business, $month)]);
    }

    public function exportBookings(Request $request, Business $business): BinaryFileResponse
    {
        $this->authorizeBusiness($request, $business);
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('business_id', $business->id)],
            'status' => ['nullable', Rule::in(Booking::STATUSES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $month = CarbonImmutable::createFromFormat('!Y-m', $validated['month'], $business->timezone);
        $query = $business->bookings()->with('service:id,name,price_cents')
            ->whereBetween('date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($validated['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['date'] ?? null, fn ($q, $date) => $q->whereDate('date', $date))
            ->orderBy('date')->orderBy('start_time');
        $bookings = $query->get();
        $statusLabels = ['booked' => 'Foglalt', 'completed' => 'Teljesítve', 'cancelled' => 'Lemondva', 'no_show' => 'Nem jelent meg'];
        $rows = [["Foglalások – {$business->name} – {$validated['month']}"], [
            'Azonosító', 'Dátum', 'Nap', 'Kezdés', 'Befejezés', 'Szolgáltatás', 'Ügyfél neve',
            'E-mail', 'Telefon', 'Státusz', 'Ár (Ft)', 'Megjegyzés', 'Rögzítve',
        ]];
        foreach ($bookings as $booking) {
            $rows[] = [
                $booking->id, $booking->date->format('Y-m-d'), ucfirst($booking->date->locale('hu')->translatedFormat('l')),
                substr((string) $booking->start_time, 0, 5), substr((string) $booking->end_time, 0, 5),
                $booking->service_name, $booking->customer_name, $booking->customer_contact,
                $booking->customer_phone ?: '', $statusLabels[$booking->status] ?? $booking->status,
                round(((int) ($booking->service?->price_cents ?? 0)) / 100, 2), $booking->customer_note ?: '',
                $booking->created_at?->timezone($business->timezone)->format('Y-m-d H:i'),
            ];
        }
        $daily = [["Napi összesítő – {$validated['month']}"], ['Dátum', 'Foglalások', 'Foglalt', 'Teljesítve', 'Lemondva', 'Nem jelent meg', 'Becsült bevétel (Ft)']];
        foreach ($bookings->groupBy(fn ($booking) => $booking->date->format('Y-m-d')) as $date => $items) {
            $daily[] = [$date, $items->count(), $items->where('status', 'booked')->count(), $items->where('status', 'completed')->count(),
                $items->where('status', 'cancelled')->count(), $items->where('status', 'no_show')->count(),
                round($items->whereIn('status', ['booked', 'completed'])->sum(fn ($item) => ((int) ($item->service?->price_cents ?? 0)) / 100), 2)];
        }
        $writer = (new SimpleXlsxWriter())
            ->addSheet('Foglalások', $rows, [12, 14, 15, 10, 12, 25, 22, 28, 18, 17, 14, 34, 18])
            ->addSheet('Napi összesítő', $daily, [14, 14, 12, 14, 14, 18, 22], [2, 7]);
        return $this->download($writer, 'foglalasok-'.$validated['month'].'.xlsx');
    }

    public function exportStatistics(Request $request, Business $business): BinaryFileResponse
    {
        $this->authorizeBusiness($request, $business);
        $month = $this->validatedMonth($request, $business);
        $data = $this->statistics->forMonth($business, $month);
        $summary = [["Havi statisztika – {$business->name} – {$month}"], ['Mutató', 'Érték', 'Előző hónap'],
            ['Foglalások száma', $data['total_bookings'], $data['comparison']['total_bookings']],
            ['Lemondási arány (%)', $data['cancellation_rate'], ''], ['No-show arány (%)', $data['no_show_rate'], ''],
            ['Becsült bevétel (Ft)', $data['estimated_revenue'], $data['comparison']['estimated_revenue']],
            ['Idősáv-kihasználtság (%)', $data['utilization_rate'], $data['comparison']['utilization_rate']],
            ['Elérhető idő (perc)', $data['available_minutes'], ''], ['Foglalt idő (perc)', $data['occupied_minutes'], '']];
        $daily = [["Napi adatok – {$month}"], ['Dátum', 'Nap', 'Összes', 'Foglalt', 'Teljesítve', 'Lemondva', 'Nem jelent meg', 'Bevétel (Ft)']];
        foreach ($data['daily'] as $day) $daily[] = array_values($day);
        $top = [["Top szolgáltatások – {$month}"], ['Szolgáltatás', 'Foglalások', 'Becsült bevétel (Ft)']];
        foreach ($data['top_services'] as $service) $top[] = [$service['name'], $service['bookings'], $service['revenue']];
        $writer = (new SimpleXlsxWriter())
            ->addSheet('Havi összesítő', $summary, [30, 22, 22], [2, 3])
            ->addSheet('Napi adatok', $daily, [14, 16, 12, 12, 14, 14, 18, 18], [3, 8])
            ->addSheet('Top szolgáltatások', $top, [32, 16, 24], [2, 3]);
        return $this->download($writer, 'statisztikak-'.$month.'.xlsx');
    }

    private function validatedMonth(Request $request, Business $business): string
    {
        return $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];
    }

    private function download(SimpleXlsxWriter $writer, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'appointment-xlsx-');
        $writer->save($path);
        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }
}
