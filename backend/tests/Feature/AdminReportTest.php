<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\SimpleXlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAppointmentData;
use Tests\TestCase;
use ZipArchive;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAppointmentData;

    public function test_monthly_statistics_and_excel_exports_use_selected_month_and_filters(): void
    {
        $business = $this->createBusiness(); $service = $this->createService($business); $admin = $this->createAdmin($business);
        $this->addMondayHours($business); $this->createBlock($business);
        $this->createBooking($business, $service, ['date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '10:00', 'busy_until' => '10:00']);
        $this->createBooking($business, $service, ['date' => '2026-08-17', 'status' => Booking::STATUS_COMPLETED]);
        $this->createBooking($business, $service, ['date' => '2026-08-24', 'status' => Booking::STATUS_CANCELLED]);
        $this->createBooking($business, $service, ['date' => '2026-08-31', 'status' => Booking::STATUS_NO_SHOW]);
        $this->createBooking($business, $service, ['date' => '2026-07-27']);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/businesses/{$business->id}/statistics?month=2026-08")
            ->assertOk()->assertJsonPath('data.total_bookings', 4)
            ->assertJsonPath('data.cancellation_rate', 25)
            ->assertJsonPath('data.no_show_rate', 25)
            ->assertJsonPath('data.estimated_revenue', 20000)
            ->assertJsonPath('data.comparison.total_bookings', 1);

        $this->get("/api/v1/admin/businesses/{$business->id}/exports/bookings?month=2026-08&status=completed")
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get("/api/v1/admin/businesses/{$business->id}/exports/statistics?month=2026-08")
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_excel_title_is_merged_across_every_table_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-title-test-');
        (new SimpleXlsxWriter())
            ->addSheet('Négy oszlop', [['Főcím'], ['A', 'B', 'C', 'D'], [1, 2, 3, 4]])
            ->addSheet('Három oszlop', [['Másik főcím'], ['A', 'B', 'C'], [1, 2, 3]])
            ->save($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $firstSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $secondSheet = $zip->getFromName('xl/worksheets/sheet2.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($firstSheet);
        $this->assertIsString($secondSheet);
        $this->assertIsString($styles);
        $this->assertStringContainsString('<mergeCell ref="A1:D1"/>', $firstSheet);
        $this->assertStringContainsString('<mergeCell ref="A1:C1"/>', $secondSheet);
        $this->assertStringContainsString('horizontal="center" vertical="center"', $styles);
    }

    public function test_statistics_keep_the_booking_time_price_after_service_price_changes(): void
    {
        $business = $this->createBusiness();
        $service = $this->createService($business, ['price_cents' => 1000000, 'price_mode' => 'fixed']);
        $admin = $this->createAdmin($business);
        $this->createBooking($business, $service, ['status' => Booking::STATUS_COMPLETED]);

        $service->update(['price_cents' => 2500000]);
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson("/api/v1/admin/businesses/{$business->id}/statistics?month=2026-08")
            ->assertOk()
            ->assertJsonPath('data.estimated_revenue', 10000);
    }

}
