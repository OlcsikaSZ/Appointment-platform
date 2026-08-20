<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'email', 'phone', 'admin_note'];

    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
}
