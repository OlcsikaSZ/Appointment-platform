<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'hero_title',
        'hero_text',
        'about_title',
        'about_text',
        'phone',
        'email',
        'address',
        'opening_hours',
        'google_maps_url',
        'logo_path',
        'timezone',
        'primary_color',
        'logo_text',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function blockedTimes(): HasMany
    {
        return $this->hasMany(BlockedTime::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function emailSetting(): HasOne
    {
        return $this->hasOne(EmailSetting::class);
    }
}
