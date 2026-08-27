<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'customer_profile_id',
        'customer_account_id',
        'service_id',
        'service_name',
        'price_cents_snapshot',
        'price_mode_snapshot',
        'date',
        'start_time',
        'end_time',
        'busy_until',
        'customer_name',
        'customer_contact',
        'customer_phone',
        'customer_note',
        'manage_token',
        'manage_token_expires_at',
        'status',
        'active_slot_key',
        'cancelled_at',
        'anonymized_at',
        'legal_accepted_at',
        'legal_text_hash',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
        'manage_token_expires_at' => 'datetime',
        'anonymized_at' => 'datetime',
        'legal_accepted_at' => 'datetime',
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

    public function reportPriceMode(): string
    {
        return (string) ($this->price_mode_snapshot ?: $this->service?->price_mode ?: 'fixed');
    }

    public function reportPriceCents(): ?int
    {
        $value = $this->price_cents_snapshot;

        if ($value === null) {
            $value = $this->service?->price_cents;
        }

        return $value === null ? null : (int) $value;
    }

    public function estimatedRevenueCents(): int
    {
        if ($this->reportPriceMode() !== 'fixed') {
            return 0;
        }

        return (int) ($this->reportPriceCents() ?? 0);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function reminderLogs(): HasMany { return $this->hasMany(ReminderLog::class); }
    public function customerProfile(): BelongsTo { return $this->belongsTo(CustomerProfile::class); }
    public function customerAccount(): BelongsTo { return $this->belongsTo(CustomerAccount::class); }
}
