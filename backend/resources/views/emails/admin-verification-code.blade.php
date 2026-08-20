<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>Admin ellenőrző kód</title></head>
<body style="margin:0;background:#f6f1e7;color:#17264b;font-family:Arial,sans-serif">
@php
    $heading = match ($purpose) {
        \App\Models\AdminVerificationCode::PURPOSE_OWNER_ACTIVATION => 'Aktiváld a tulajdonosi fiókodat',
        \App\Models\AdminVerificationCode::PURPOSE_EMAIL_CHANGE => 'Erősítsd meg az új e-mail-címedet',
        default => 'Állíts be új admin jelszót',
    };
@endphp
<div style="max-width:560px;margin:32px auto;background:#fff;padding:32px;border-radius:16px">
    <p style="color:#b97811;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{{ $business->name }}</p>
    <h1 style="font-size:24px">{{ $heading }}</h1>
    <p>Az egyszer használható ellenőrző kódod:</p>
    <p style="font-size:32px;font-weight:700;letter-spacing:.22em;padding:18px;background:#f6f1e7;border-radius:10px;text-align:center">{{ $code }}</p>
    <p>A kód {{ $validMinutes }} percig érvényes. Hibás próbálkozás nem használja fel a helyes kódot.</p>
    <p>Ha nem te kérted ezt a műveletet, ne add meg senkinek a kódot.</p>
</div>
</body>
</html>
