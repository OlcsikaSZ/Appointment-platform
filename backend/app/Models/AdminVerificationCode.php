<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminVerificationCode extends Model
{
    public const PURPOSE_OWNER_ACTIVATION = 'owner_activation';
    public const PURPOSE_EMAIL_CHANGE = 'email_change';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'user_id',
        'purpose',
        'email',
        'code_hash',
        'attempts',
        'expires_at',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
