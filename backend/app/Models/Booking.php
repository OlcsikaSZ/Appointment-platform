<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

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
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
