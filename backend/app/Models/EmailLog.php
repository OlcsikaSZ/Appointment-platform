<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'resent_from_id',
        'business_id',
        'booking_id',
        'event_type',
        'recipient_type',
        'recipient_email',
        'subject',
        'status',
        'attempt_count',
        'last_attempt_at',
        'error_message',
        'payload',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
