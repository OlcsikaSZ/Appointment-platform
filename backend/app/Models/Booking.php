<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_BOOKED = 'booked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_BOOKED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    protected $fillable = [
        'business_id',
        'service_id',
        'service_name',
        'date',
        'start_time',
        'end_time',
        'busy_until',
        'customer_name',
        'customer_contact',
        'customer_note',
        'manage_token',
        'status',
        'active_slot_key',
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            if ($booking->status === self::STATUS_CANCELLED && ! $booking->cancelled_at) {
                $booking->cancelled_at = now();
            }

            if ($booking->status !== self::STATUS_CANCELLED && $booking->isDirty('status')) {
                $booking->cancelled_at = null;
            }

            $booking->active_slot_key = $booking->status === self::STATUS_BOOKED
                ? self::slotKey($booking->business_id, $booking->date, $booking->start_time)
                : null;
        });
    }

    public static function slotKey(int $businessId, mixed $date, string $startTime): string
    {
        $dateValue = is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d');

        return $businessId.'|'.$dateValue.'|'.substr($startTime, 0, 5);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
