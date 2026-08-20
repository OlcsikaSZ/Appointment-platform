<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class CustomerAccount extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'email', 'phone', 'password', 'role',
        'email_verified_at', 'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
}
