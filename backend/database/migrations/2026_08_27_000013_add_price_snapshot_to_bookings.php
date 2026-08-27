<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'price_cents_snapshot')) {
                $table->unsignedInteger('price_cents_snapshot')->nullable()->after('service_name');
            }
            if (! Schema::hasColumn('bookings', 'price_mode_snapshot')) {
                $table->string('price_mode_snapshot', 32)->nullable()->after('price_cents_snapshot');
            }
        });

        // A korábbi foglalások történeti árát utólag nem lehet biztosan rekonstruálni.
        // Best-effort backfillként a migráció pillanatában érvényes szolgáltatásárat rögzítjük,
        // hogy ettől a ponttól a riportok többé ne változzanak későbbi ármódosításkor.
        DB::table('bookings')
            ->select(['id', 'service_id'])
            ->whereNotNull('service_id')
            ->orderBy('id')
            ->chunkById(200, function ($bookings): void {
                $serviceIds = collect($bookings)->pluck('service_id')->filter()->unique()->values();
                $services = DB::table('services')
                    ->whereIn('id', $serviceIds)
                    ->get(['id', 'price_cents', 'price_mode'])
                    ->keyBy('id');

                foreach ($bookings as $booking) {
                    $service = $services->get($booking->service_id);
                    if (! $service) {
                        continue;
                    }

                    DB::table('bookings')->where('id', $booking->id)->update([
                        'price_cents_snapshot' => $service->price_cents,
                        'price_mode_snapshot' => $service->price_mode ?: 'fixed',
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            foreach (['price_mode_snapshot', 'price_cents_snapshot'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
