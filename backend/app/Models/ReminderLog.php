<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    public const TYPE_24H = '24h';
    public const TYPE_2H = '2h';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_id', 'booking_id', 'email_log_id', 'reminder_type', 'status',
        'scheduled_for', 'sent_at', 'skipped_at', 'error_message',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function emailLog(): BelongsTo { return $this->belongsTo(EmailLog::class); }
}
