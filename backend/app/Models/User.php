<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'pending_email',
        'email_verified_at',
        'password',
        'password_changed_at',
        'business_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'password' => 'hashed',
    ];
}
