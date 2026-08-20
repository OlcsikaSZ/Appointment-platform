<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CUSTOMER = 'customer';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_id',
        'author',
        'text',
        'rating',
        'source',
        'moderation_status',
        'submitter_email',
        'submitted_at',
        'legal_accepted_at',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'rating' => 'integer',
        'sort_order' => 'integer',
        'submitted_at' => 'datetime',
        'legal_accepted_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
