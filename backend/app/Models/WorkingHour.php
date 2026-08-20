<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingHour extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'weekday',
        'start_time',
        'end_time',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
