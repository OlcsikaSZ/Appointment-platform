<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingDayLockService
{
    /**
     * Ugyanazt a MySQL named lockot használja minden, egy adott nap foglalhatóságát
     * módosító művelethez. Több nap esetén rendezve kérjük le a lockokat, így
     * elkerülhető a kölcsönös várakozásból kialakuló holtpont.
     *
     * @param string|array<int, string> $dates
     */
    public function run(
        Business|int $business,
        string|array $dates,
        callable $callback,
        int $timeoutSeconds = 10,
    ): mixed {
        $businessId = $business instanceof Business ? (int) $business->id : (int) $business;
        $dateList = collect(is_array($dates) ? $dates : [$dates])
            ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
            ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
            ->unique()
            ->sort()
            ->values();

        if ($dateList->isEmpty()) {
            return DB::transaction(fn () => $callback());
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return DB::transaction(fn () => $callback());
        }

        $acquiredLocks = [];

        try {
            foreach ($dateList as $date) {
                $lockName = $this->lockName($businessId, $date);
                $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $timeoutSeconds]);

                if ((int) ($result->acquired ?? 0) !== 1) {
                    throw new HttpException(
                        423,
                        'Erre a napra most másik foglalási művelet fut. Próbáld újra néhány másodperc múlva.'
                    );
                }

                $acquiredLocks[] = $lockName;
            }

            return DB::transaction(fn () => $callback());
        } finally {
            foreach (array_reverse($acquiredLocks) as $lockName) {
                DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    private function lockName(int $businessId, string $date): string
    {
        return "appointment-day-{$businessId}-{$date}";
    }
}
