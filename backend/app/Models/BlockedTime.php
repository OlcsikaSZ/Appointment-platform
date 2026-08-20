<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'is_all_day',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_all_day' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
