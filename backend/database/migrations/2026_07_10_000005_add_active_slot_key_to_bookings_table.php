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
                $table->string('active_slot_key', 191)->nullable()->after('status');
            });
        }

        // Csak az aktív (booked) foglalás foglalhat le egy időpontkulcsot.
        DB::table('bookings')
            ->where('status', '!=', 'booked')
            ->update(['active_slot_key' => null]);

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

        $duplicate = DB::table('bookings')
            ->select('active_slot_key', DB::raw('COUNT(*) AS total'))
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

        if (! $this->indexExists(self::INDEX_NAME)) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique('active_slot_key', self::INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'active_slot_key')) {
            if ($this->indexExists(self::INDEX_NAME)) {
                Schema::table('bookings', function (Blueprint $table): void {
                    $table->dropUnique(self::INDEX_NAME);
                });
            }

            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropColumn('active_slot_key');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $databaseName = DB::connection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $databaseName)
            ->where('table_name', 'bookings')
            ->where('index_name', $indexName)
            ->exists();
    }
};
