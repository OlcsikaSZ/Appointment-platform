<?php

return [
    'public_url' => env('PUBLIC_APP_URL', env('APP_URL', 'http://localhost')),

    // Három nap inaktivitás után a következő admin API-kérés visszavonja a tokent.
    'admin_idle_timeout_minutes' => (int) env('ADMIN_IDLE_TIMEOUT_MINUTES', 4320),

    // Az inaktivitási szabály mellett legyen abszolút felső korlát is.
    'admin_token_lifetime_minutes' => (int) env('ADMIN_TOKEN_LIFETIME_MINUTES', 43200),

    'admin_verification_code_minutes' => (int) env('ADMIN_VERIFICATION_CODE_MINUTES', 15),
    'admin_verification_max_attempts' => (int) env('ADMIN_VERIFICATION_MAX_ATTEMPTS', 5),
    'admin_verification_active_codes' => (int) env('ADMIN_VERIFICATION_ACTIVE_CODES', 3),
    'admin_verification_attempt_grace_minutes' => (int) env('ADMIN_VERIFICATION_ATTEMPT_GRACE_MINUTES', 5),

    'customer_token_lifetime_minutes' => (int) env('CUSTOMER_TOKEN_LIFETIME_MINUTES', 10080),
    'customer_verification_code_minutes' => (int) env('CUSTOMER_VERIFICATION_CODE_MINUTES', 15),
    'customer_verification_max_attempts' => (int) env('CUSTOMER_VERIFICATION_MAX_ATTEMPTS', 5),
    'customer_verification_active_codes' => (int) env('CUSTOMER_VERIFICATION_ACTIVE_CODES', 3),
    'customer_verification_attempt_grace_minutes' => (int) env('CUSTOMER_VERIFICATION_ATTEMPT_GRACE_MINUTES', 5),

    // Az első seedeléskor létrehozott vállalkozás kapcsolati és értesítési címe.
    'business_seed_email' => env('BUSINESS_SEED_EMAIL'),

    // Kizárólag local/testing mintaadminhoz. Éles owner: php artisan app:create-owner
    'admin_seed_email' => env('ADMIN_SEED_EMAIL', 'admin@example.test'),
    'admin_seed_password' => env('ADMIN_SEED_PASSWORD'),

    // Demo adatgenerálás productionben alapból tiltva. Csak dedikált demo példányon kapcsold be.
    'demo_data_allowed' => filter_var(env('DEMO_DATA_ALLOWED', false), FILTER_VALIDATE_BOOL),
];
