<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(($legalDocument['title'] ?? 'Jogi dokumentum').' — Időpontfoglalás', ENT_QUOTES, 'UTF-8') ?></title>
  <link id="business-favicon" rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <a class="skip-link" href="#main-content">Ugrás a tartalomhoz</a>
  <div
    id="legalApp"
    class="page"
    data-legal-field="<?= htmlspecialchars($legalDocument['field'] ?? 'privacyPolicy', ENT_QUOTES, 'UTF-8') ?>"
    data-legal-title="<?= htmlspecialchars($legalDocument['title'] ?? 'Jogi dokumentum', ENT_QUOTES, 'UTF-8') ?>"
    data-legal-eyebrow="<?= htmlspecialchars($legalDocument['eyebrow'] ?? 'Jogi dokumentum', ENT_QUOTES, 'UTF-8') ?>"
    data-main-url="<?= htmlspecialchars(route_url('main'), ENT_QUOTES, 'UTF-8') ?>"
    v-cloak
  >
    <header class="topbar legal-topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark"><img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" /><template v-else>{{ business.logoText || monogram(business.name) || 'IP' }}</template></span>
        <span><strong>{{ business.name || 'Időpontfoglalás' }}</strong><small>Jogi információk</small></span>
      </a>
      <nav><a href="<?= route_url('main') ?>" @click.prevent="goBack">Vissza</a></nav>
    </header>

    <main id="main-content" class="legal-shell" tabindex="-1">
      <section class="panel legal-card">
        <p class="eyebrow">{{ eyebrow }}</p>
        <h1>{{ title }}</h1>
        <p v-if="loading" class="lead">Dokumentum betöltése…</p>
        <div v-else-if="content" class="legal-content" v-html="content"></div>
        <div v-else class="notice legal-empty">
          Ez a dokumentum még nincs kitöltve. A szolgáltató az adminfelületen tudja megadni a végleges szöveget.
        </div>
      </section>
    </main>

    <div class="toast-stack" aria-live="polite" aria-atomic="false">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" :role="toast.kind === 'error' ? 'alert' : 'status'" @click="toasts.dismiss(toast.id)">{{ toast.message }}</div>
    </div>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
