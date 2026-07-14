<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSetting extends Model
{
    use HasFactory;

    public const EVENT_TYPES = [
        'booking_created',
        'booking_rescheduled',
        'booking_cancelled',
    ];

    public const RECIPIENT_TYPES = [
        'customer',
        'admin',
    ];

    protected $fillable = [
        'business_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function defaults(): array
    {
        return [
            'sender_name' => '',
            'reply_to' => '',
            'footer_text' => 'Ez egy automatikus értesítés. Kérdés esetén válaszolj erre az e-mailre, vagy vedd fel a kapcsolatot a vállalkozással.',
            'templates' => [
                'customer' => [
                    'booking_created' => [
                        'subject' => 'Foglalás visszaigazolása – {business_name}',
                        'intro' => 'Kedves {customer_name}! A foglalásodat sikeresen rögzítettük.',
                    ],
                    'booking_rescheduled' => [
                        'subject' => 'Időpont módosítva – {business_name}',
                        'intro' => 'Kedves {customer_name}! A foglalásod időpontját sikeresen módosítottuk.',
                    ],
                    'booking_cancelled' => [
                        'subject' => 'Foglalás lemondva – {business_name}',
                        'intro' => 'Kedves {customer_name}! A foglalásodat lemondtuk.',
                    ],
                ],
                'admin' => [
                    'booking_created' => [
                        'subject' => 'Új foglalás érkezett – {business_name}',
                        'intro' => 'Új foglalás érkezett. Vendég: {customer_name}, szolgáltatás: {service_name}.',
                    ],
                    'booking_rescheduled' => [
                        'subject' => 'Foglalás módosítva – {business_name}',
                        'intro' => 'Egy meglévő foglalást módosítottak. Vendég: {customer_name}, szolgáltatás: {service_name}.',
                    ],
                    'booking_cancelled' => [
                        'subject' => 'Foglalás lemondva – {business_name}',
                        'intro' => 'Egy foglalást lemondtak. Vendég: {customer_name}, szolgáltatás: {service_name}.',
                    ],
                ],
            ],
        ];
    }

    public static function resolvedForBusiness(Business $business): array
    {
        $saved = $business->emailSetting?->settings ?? [];

        return self::normalize($saved);
    }

    public static function normalize(array $settings): array
    {
        $defaults = self::defaults();
        $normalized = [
            'sender_name' => trim((string) ($settings['sender_name'] ?? $defaults['sender_name'])),
            'reply_to' => trim((string) ($settings['reply_to'] ?? $defaults['reply_to'])),
            'footer_text' => array_key_exists('footer_text', $settings)
                ? trim((string) ($settings['footer_text'] ?? ''))
                : $defaults['footer_text'],
            'templates' => [],
        ];

        foreach (self::RECIPIENT_TYPES as $recipientType) {
            $normalized['templates'][$recipientType] = [];

            foreach (self::EVENT_TYPES as $eventType) {
                $defaultTemplate = $defaults['templates'][$recipientType][$eventType];
                $provided = $settings['templates'][$recipientType][$eventType] ?? [];

                $subject = trim((string) ($provided['subject'] ?? $defaultTemplate['subject']));
                $intro = trim((string) ($provided['intro'] ?? $defaultTemplate['intro']));

                $normalized['templates'][$recipientType][$eventType] = [
                    'subject' => $subject !== '' ? $subject : $defaultTemplate['subject'],
                    'intro' => $intro !== '' ? $intro : $defaultTemplate['intro'],
                ];
            }
        }

        return $normalized;
    }
}
