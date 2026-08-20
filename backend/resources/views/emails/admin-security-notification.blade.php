<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body style="margin:0;background:#f6f1e7;color:#17264b;font-family:Arial,sans-serif">
<div style="max-width:560px;margin:32px auto;background:#fff;padding:32px;border-radius:16px">
    <p style="color:#b97811;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{{ $business->name }}</p>
    <h1 style="font-size:24px">{{ $title }}</h1>
    @foreach ($lines as $line)
        <p style="line-height:1.6">{{ $line }}</p>
    @endforeach
    <p style="margin-top:24px;color:#6b6558;font-size:13px">Ha nem te végezted ezt a módosítást, azonnal állíts be új jelszót.</p>
</div>
</body>
</html>
