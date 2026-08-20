<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVerificationCode extends Model
{
    public const PURPOSE_REGISTRATION = 'registration';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'business_id', 'purpose', 'name', 'email', 'phone', 'password_hash',
        'code_hash', 'attempts', 'expires_at',
    ];

    protected $hidden = ['password_hash', 'code_hash'];

    protected $casts = ['attempts' => 'integer', 'expires_at' => 'datetime'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
