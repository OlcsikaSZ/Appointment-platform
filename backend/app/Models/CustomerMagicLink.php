<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMagicLink extends Model
{
    use HasFactory;

    protected $fillable = ['customer_account_id', 'token_hash', 'expires_at', 'used_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
