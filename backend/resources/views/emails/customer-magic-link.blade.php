<!doctype html>
<html lang="hu">
<body style="margin:0;background:#f6f2ea;color:#1c2541;font-family:Segoe UI,Arial,sans-serif">
  <div style="max-width:620px;margin:0 auto;padding:32px 18px">
    <div style="padding:28px;border-radius:18px;background:#fffdf9;border:1px solid #e2d9c6">
      <p style="margin:0 0 8px;color:#7a4b12;font-size:12px;font-weight:700;text-transform:uppercase">Ügyfélfiók</p>
      <h1 style="margin:0 0 16px;font-family:Georgia,serif;font-size:28px">Belépés a foglalásaidhoz</h1>
      <p>Kedves {{ $account->name }}!</p>
      <p>Az alábbi egyszer használható linkkel 15 percig beléphetsz a(z) {{ $account->business->name }} ügyfélfiókodba.</p>
      <p style="margin:24px 0">
        <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 20px;border-radius:999px;background:#1c2541;color:#fff;text-decoration:none;font-weight:700">Belépés a fiókomba</a>
      </p>
      <p style="color:#6b6558;font-size:13px">Ha nem te kérted a linket, hagyd figyelmen kívül ezt a levelet.</p>
    </div>
  </div>
</body>
</html>
