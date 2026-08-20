<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'bookings_active_slot_key_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'active_slot_key')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->string('active_slot_key', 191)
                    ->nullable()
                    ->after('status');
            });
        }

        // Csak az aktív foglalások rendelkezhetnek slot-kulccsal.
        DB::table('bookings')
            ->where('status', '!=', 'booked')
            ->update([
                'active_slot_key' => null,
            ]);

        /*
         * MySQL alatt használhatjuk a gyors natív SQL-t.
         * PHPUnit tesztnél SQLite fut, ezért ott hordozható megoldást használunk.
         */
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE bookings
                SET active_slot_key = CONCAT(
                    business_id,
                    '|',
                    DATE_FORMAT(`date`, '%Y-%m-%d'),
                    '|',
                    LEFT(start_time, 5)
                )
                WHERE status = 'booked'
            SQL);
        } else {
            DB::table('bookings')
                ->where('status', 'booked')
                ->orderBy('id')
                ->get([
                    'id',
                    'business_id',
                    'date',
                    'start_time',
                ])
                ->each(function (object $booking): void {
                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update([
                            'active_slot_key' =>
                                $booking->business_id
                                .'|'.substr((string) $booking->date, 0, 10)
                                .'|'.substr((string) $booking->start_time, 0, 5),
                        ]);
                });
        }

        // Ellenőrizzük, hogy nincs-e már duplikált aktív slot.
        $duplicate = DB::table('bookings')
            ->select(
                'active_slot_key',
                DB::raw('COUNT(*) AS total')
            )
            ->whereNotNull('active_slot_key')
            ->groupBy('active_slot_key')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(
                'Nem hozható létre az egyedi időpont-index, mert már van legalább két aktív foglalás ugyanarra a kezdési időpontra: '
                .$duplicate->active_slot_key
            );
        }

        if (! $this->indexExists()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique(
                    'active_slot_key',
                    self::INDEX_NAME
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookings', 'active_slot_key')) {
            return;
        }

        if ($this->indexExists()) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX_NAME);
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('active_slot_key');
        });
    }

    private function indexExists(): bool
    {
        /*
         * MySQL és SQLite különböző módon tárolja
         * az indexek metaadatait.
         */
        if (DB::connection()->getDriverName() === 'mysql') {
            $databaseName = DB::connection()->getDatabaseName();

            return DB::table('information_schema.statistics')
                ->where('table_schema', $databaseName)
                ->where('table_name', 'bookings')
                ->where('index_name', self::INDEX_NAME)
                ->exists();
        }

        return collect(Schema::getIndexes('bookings'))
            ->contains(
                fn (array $index): bool =>
                    ($index['name'] ?? null) === self::INDEX_NAME
            );
    }
};