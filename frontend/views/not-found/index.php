<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>404 — Az oldal nem található</title>
  <link id="business-favicon" rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <main class="not-found-shell">
    <section class="panel not-found-card">
      <p class="eyebrow">404 · Nem található</p>
      <h1>Ez az oldal nincs itt.</h1>
      <p class="lead">Lehet, hogy az URL hibás, vagy az oldal időközben elköltözött.</p>
      <a class="button primary" href="<?= route_url('main') ?>">Vissza a főoldalra</a>
    </section>
  </main>
</body>
</html>
