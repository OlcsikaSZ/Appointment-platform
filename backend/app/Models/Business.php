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
        'logo_thumbnail_path',
        'timezone',
        'min_advance_minutes',
        'max_advance_days',
        'slot_interval_minutes',
        'cancellation_deadline_minutes',
        'reschedule_deadline_minutes',
        'reminder_24h_enabled',
        'reminder_2h_enabled',
        'hide_prices',
        'booking_retention_days',
        'email_log_retention_days',
        'manage_token_retention_days',
        'privacy_policy',
        'terms_text',
        'imprint_text',
        'cookie_policy',
        'primary_color',
        'logo_text',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'hide_prices' => 'boolean',
        'min_advance_minutes' => 'integer',
        'max_advance_days' => 'integer',
        'slot_interval_minutes' => 'integer',
        'cancellation_deadline_minutes' => 'integer',
        'reschedule_deadline_minutes' => 'integer',
        'reminder_24h_enabled' => 'boolean',
        'reminder_2h_enabled' => 'boolean',
        'booking_retention_days' => 'integer',
        'email_log_retention_days' => 'integer',
        'manage_token_retention_days' => 'integer',
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

    public function reminderLogs(): HasMany { return $this->hasMany(ReminderLog::class); }
    public function customerProfiles(): HasMany { return $this->hasMany(CustomerProfile::class); }
    public function customerAccounts(): HasMany { return $this->hasMany(CustomerAccount::class); }
}
