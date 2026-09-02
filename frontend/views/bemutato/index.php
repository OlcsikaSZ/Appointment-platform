<?php
$showcaseScreenshots = [
    'home' => [
        'file' => '01-home.webp',
        'label' => 'Saját arculatú vállalkozói weboldal',
        'description' => 'A vendég már az első pillanatban a te márkáddal találkozik – nem egy közös piactérrel.',
    ],
    'services' => [
        'file' => '02-services.webp',
        'label' => 'Átlátható szolgáltatásválasztás',
        'description' => 'Ár, időtartam, részletek és vizuális megjelenés egy rendezett, könnyen átlátható felületen.',
    ],
    'booking' => [
        'file' => '03-booking.webp',
        'label' => 'Valóban szabad időpontok',
        'description' => 'A rendszer a munkaidő, foglalások, pufferidők és blokkolások alapján kínál fel időpontot.',
    ],
    'booking_mobile' => [
        'file' => '04-booking-mobile.webp',
        'label' => 'Telefonról is kényelmes',
        'description' => 'A foglalási folyamat mobilon is gyors, egyszerű és átlátható marad.',
    ],
    'admin' => [
        'file' => '05-admin-calendar.webp',
        'label' => 'Adminnaptár egy helyen',
        'description' => 'Foglalások, manuális időpontok, blokkolások, státuszok és keresés egyetlen kezelőfelületen.',
    ],
    'statistics' => [
        'file' => '06-statistics.webp',
        'label' => 'Statisztikák és üzleti áttekintés',
        'description' => 'Gyorsan látható a foglalásszám, lemondási arány, becsült bevétel és a legkeresettebb szolgáltatások.',
    ],
];

$screenshotBase = __DIR__.'/../../assets/sales/screenshots/';
foreach ($showcaseScreenshots as $key => $item) {
    $showcaseScreenshots[$key]['available'] = is_file($screenshotBase.$item['file']);
}

$hasScreenshots = count(array_filter($showcaseScreenshots, static fn (array $item): bool => $item['available'])) >= 4;
$demoUrl = route_url('main');
$contactEmail = 'olcsikaszbusiness@gmail.com';
$contactHref = 'mailto:'.$contactEmail.'?subject='.rawurlencode('DEMÓ – Olcsi Business időpontfoglaló');

// A videó felvétele után ide elég beilleszteni a YouTube embed URL-t,
// például: https://www.youtube-nocookie.com/embed/VIDEO_ID
// Üresen hagyva a videós blokk nem jelenik meg.
$videoEmbedUrl = 'https://www.youtube-nocookie.com/embed/N5YuxQ5z-B4?rel=0&playsinline=1';
$hasVideo = trim($videoEmbedUrl) !== '';

function sales_screenshot_src(array $item): string
{
    return asset('assets/sales/screenshots/'.$item['file']);
}
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Saját arculatú vállalkozói weboldal online időpontfoglalással, adminfelülettel, automatikus e-mailekkel és üzemeltetési háttérrel." />
  <meta name="theme-color" content="#191b1d" />
  <meta property="og:title" content="Olcsi Business – saját weboldal és online időpontfoglalás" />
  <meta property="og:description" content="Kevesebb üzenetváltás, egyszerűbb időpontkezelés és saját online megjelenés egy rendszerben." />
  <meta property="og:type" content="website" />
  <title>Olcsi Business – Online időpontfoglaló vállalkozóknak</title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body class="sales-page">
  <a class="skip-link" href="#sales-main">Ugrás a tartalomhoz</a>

  <header class="sales-header" id="top">
    <div class="sales-wrap sales-nav-wrap">
      <a class="sales-brand" href="<?= route_url('showcase') ?>" aria-label="Olcsi Business bemutató főoldal">
        <span class="sales-brand-mark" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect x="6" y="9" width="28" height="25" rx="6" stroke="currentColor" stroke-width="2.4"/>
            <path d="M12 6v7M28 6v7M7 16h26" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            <path d="M13 21h4v4h-4zM23 21h4v4h-4zM13 28h4v3h-4zM23 28h4v3h-4z" fill="currentColor"/>
          </svg>
        </span>
        <span>
          <strong>Olcsi Business</strong>
          <small>Saját weboldal + online időpontfoglalás</small>
        </span>
      </a>

      <nav class="sales-nav" aria-label="Bemutató navigáció">
        <?php if ($hasVideo): ?><a href="#video">Videó</a><?php endif; ?>
        <?php if ($hasScreenshots): ?><a href="#kepernyokepek">Képernyőképek</a><?php endif; ?>
        <a href="#hogyan-mukodik">Hogyan működik?</a>
        <a class="sales-nav-cta" href="<?= htmlspecialchars($demoUrl, ENT_QUOTES) ?>">Élő demo</a>
      </nav>
    </div>
  </header>

  <main id="sales-main">
    <section class="sales-hero">
      <div class="sales-wrap sales-hero-grid">
        <div class="sales-hero-copy" data-reveal>
          <div class="sales-demo-pill"><span></span> Működő demó: Aranyvonal Hair Studio</div>
          <p class="sales-kicker">Saját rendszer szolgáltató vállalkozásoknak</p>
          <h1>Ne üzenetekben szervezd az időpontjaidat.</h1>
          <p class="sales-hero-lead">Saját arculatú weboldal és online időpontfoglaló egyben. A vendégeid 0–24-ben foglalhatnak, te pedig egyetlen adminfelületen látod és kezeled az időpontokat.</p>

          <div class="sales-actions">
            <a class="sales-button sales-button-primary" href="<?= htmlspecialchars($demoUrl, ENT_QUOTES) ?>">
              Élő demo kipróbálása
              <span aria-hidden="true">→</span>
            </a>
            <a class="sales-button sales-button-secondary" href="<?= htmlspecialchars($contactHref, ENT_QUOTES) ?>">Írj egy „DEMÓ” üzenetet</a>
          </div>

          <ul class="sales-proof-list" aria-label="Fő előnyök">
            <li><span>✓</span> 0–24 online foglalás</li>
            <li><span>✓</span> automatikus visszaigazolások és emlékeztetők</li>
            <li><span>✓</span> saját weboldal és adminfelület</li>
          </ul>
        </div>

        <div class="sales-product-scene" aria-label="Az időpontfoglaló rendszer szemléltetése" data-reveal>
          <div class="sales-glow sales-glow-one"></div>
          <div class="sales-glow sales-glow-two"></div>

          <div class="sales-laptop">
            <div class="sales-laptop-topbar">
              <span></span><span></span><span></span>
              <small>Időpontfoglalás</small>
            </div>
            <div class="sales-laptop-content">
              <div class="sales-preview-heading">
                <span class="sales-preview-logo">A</span>
                <div><b>Aranyvonal Hair Studio</b><small>Foglalj pár kattintással</small></div>
              </div>
              <div class="sales-preview-title">Válassz egy szabad időpontot</div>
              <div class="sales-preview-layout">
                <div class="sales-preview-calendar">
                  <div class="sales-preview-month"><b>Szeptember</b><span>‹ &nbsp; ›</span></div>
                  <div class="sales-preview-week"><i>H</i><i>K</i><i>Sze</i><i>Cs</i><i>P</i><i>Szo</i><i>V</i></div>
                  <div class="sales-preview-days">
                    <?php for ($day = 1; $day <= 21; $day++): ?>
                      <span class="<?= in_array($day, [3, 8, 11, 17], true) ? 'available' : '' ?><?= $day === 11 ? ' selected' : '' ?>"><?= $day ?></span>
                    <?php endfor; ?>
                  </div>
                </div>
                <div class="sales-preview-times">
                  <button type="button" tabindex="-1">09:00</button><button type="button" tabindex="-1">09:45</button>
                  <button type="button" tabindex="-1" class="active">10:30</button><button type="button" tabindex="-1">13:15</button>
                  <button type="button" tabindex="-1">15:00</button><button type="button" tabindex="-1">16:30</button>
                </div>
              </div>
            </div>
          </div>

          <div class="sales-phone" aria-hidden="true">
            <div class="sales-phone-notch"></div>
            <div class="sales-phone-screen">
              <span class="sales-phone-logo">A</span>
              <b>Foglalás</b>
              <small>Női hajvágás</small>
              <div class="sales-phone-card"><span>11</span><small>szeptember</small></div>
              <div class="sales-phone-times"><i>09:45</i><i class="active">10:30</i><i>13:15</i></div>
              <span class="sales-phone-button">Tovább →</span>
            </div>
          </div>

          <div class="sales-floating-card sales-floating-card-one">
            <span class="sales-floating-icon">✓</span>
            <div><b>Foglalás rögzítve</b><small>Automatikus visszaigazolással</small></div>
          </div>
        </div>
      </div>
    </section>

    <section class="sales-value-strip" aria-label="Fő értékek">
      <div class="sales-wrap sales-value-grid">
        <div><strong>Kevesebb</strong><span>üzenetváltás és manuális egyeztetés</span></div>
        <div><strong>0–24</strong><span>foglalási lehetőség a vendégeidnek</span></div>
        <div><strong>Egy helyen</strong><span>naptár, ügyfelek és statisztikák</span></div>
        <div><strong>Saját arculat</strong><span>nem egy sablonos közös piactér</span></div>
      </div>
    </section>

    <section id="mit-kapsz" class="sales-section">
      <div class="sales-wrap">
        <div class="sales-section-heading" data-reveal>
          <p class="sales-kicker">Nem csak egy foglaló</p>
          <h2>Egy komplett vállalkozói online rendszer.</h2>
          <p>Az oldal megjelenésétől az időpontkezelésen át az automatikus értesítésekig egyetlen, vállalkozásodra szabható rendszerben.</p>
        </div>

        <div class="sales-feature-grid">
          <article class="sales-feature-card sales-feature-card-large" data-reveal>
            <span class="sales-feature-number">01</span>
            <h3>Saját weboldal és arculat</h3>
            <p>Saját név, logó, színek, bemutatkozás, szolgáltatások, árak, képek, kapcsolati adatok és GYIK.</p>
          </article>
          <article class="sales-feature-card" data-reveal>
            <span class="sales-feature-number">02</span>
            <h3>Online időpontfoglalás</h3>
            <p>A vendég csak a valóban elérhető időpontok közül választ.</p>
          </article>
          <article class="sales-feature-card" data-reveal>
            <span class="sales-feature-number">03</span>
            <h3>Adminnaptár</h3>
            <p>Foglalás, blokkolás, manuális időpont és státuszkezelés egy helyen.</p>
          </article>
          <article class="sales-feature-card" data-reveal>
            <span class="sales-feature-number">04</span>
            <h3>Automatikus e-mailek</h3>
            <p>Visszaigazolás, módosítás, lemondás és időpont-emlékeztetők.</p>
          </article>
          <article class="sales-feature-card sales-feature-card-dark" data-reveal>
            <span class="sales-feature-number">05</span>
            <h3>Ügyféltörténet és statisztikák</h3>
            <p>Átláthatóbb működés és kevesebb fejben tartandó információ.</p>
          </article>
          <article class="sales-feature-card" data-reveal>
            <span class="sales-feature-number">06</span>
            <h3>Technikai háttér</h3>
            <p>Beállítás, üzemeltetés, mentések, frissítések és support – nem neked kell szervert menedzselned.</p>
          </article>
        </div>
      </div>
    </section>

    <?php if ($hasScreenshots): ?>
      <section class="sales-section sales-screenshot-section" id="kepernyokepek">
        <div class="sales-wrap">
          <div class="sales-section-heading" data-reveal>
            <p class="sales-kicker">Valódi képernyőképek</p>
            <h2>Nem látványterv. Működő rendszer.</h2>
            <p>Az Aranyvonal Hair Studio demóban ugyanazt a folyamatot látod, amit később a saját vállalkozásod arculatára lehet szabni.</p>
          </div>

          <?php if ($showcaseScreenshots['home']['available']): ?>
            <article class="sales-showcase-row sales-showcase-row-hero" data-reveal>
              <div class="sales-showcase-copy">
                <span class="sales-story-index">01 / Saját megjelenés</span>
                <h3><?= htmlspecialchars($showcaseScreenshots['home']['label']) ?></h3>
                <p><?= htmlspecialchars($showcaseScreenshots['home']['description']) ?></p>
                <ul>
                  <li>saját név, logó és színvilág</li>
                  <li>mobilbarát, modern megjelenés</li>
                  <li>közvetlen foglalási CTA</li>
                </ul>
              </div>
              <a class="sales-browser-frame" href="<?= sales_screenshot_src($showcaseScreenshots['home']) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars($showcaseScreenshots['home']['label'], ENT_QUOTES) ?> megnyitása nagy méretben">
                <span class="sales-frame-bar" aria-hidden="true"><i></i><i></i><i></i><b>aranyvonal / foglalási oldal</b></span>
                <img src="<?= sales_screenshot_src($showcaseScreenshots['home']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['home']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
              </a>
            </article>
          <?php endif; ?>

          <div class="sales-showcase-pair">
            <?php if ($showcaseScreenshots['services']['available']): ?>
              <article class="sales-showcase-card" data-reveal>
                <div class="sales-showcase-card-copy">
                  <span class="sales-story-index">02 / Szolgáltatások</span>
                  <h3><?= htmlspecialchars($showcaseScreenshots['services']['label']) ?></h3>
                  <p><?= htmlspecialchars($showcaseScreenshots['services']['description']) ?></p>
                </div>
                <a class="sales-shot-frame sales-shot-frame-tall" href="<?= sales_screenshot_src($showcaseScreenshots['services']) ?>" target="_blank" rel="noopener">
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['services']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['services']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
              </article>
            <?php endif; ?>

            <?php if ($showcaseScreenshots['booking']['available']): ?>
              <article class="sales-showcase-card sales-showcase-card-dark" data-reveal>
                <div class="sales-showcase-card-copy">
                  <span class="sales-story-index">03 / Foglalás</span>
                  <h3><?= htmlspecialchars($showcaseScreenshots['booking']['label']) ?></h3>
                  <p><?= htmlspecialchars($showcaseScreenshots['booking']['description']) ?></p>
                </div>
                <a class="sales-shot-frame sales-shot-frame-tall" href="<?= sales_screenshot_src($showcaseScreenshots['booking']) ?>" target="_blank" rel="noopener">
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['booking']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['booking']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
              </article>
            <?php endif; ?>
          </div>

          <?php if ($showcaseScreenshots['booking_mobile']['available'] && $showcaseScreenshots['booking']['available']): ?>
            <article class="sales-mobile-showcase" data-reveal>
              <div class="sales-mobile-showcase-copy">
                <span class="sales-story-index">04 / Mobil</span>
                <h3>Ugyanaz az élmény telefonon is.</h3>
                <p>A vendégnek nem kell alkalmazást telepítenie. Megnyitja az oldalt, kiválasztja a szolgáltatást és lefoglalja a neki megfelelő szabad időpontot.</p>
                <div class="sales-mini-proof"><span>✓</span> reszponzív foglalás</div>
                <div class="sales-mini-proof"><span>✓</span> érintőképernyőre optimalizált vezérlés</div>
                <div class="sales-mini-proof"><span>✓</span> külön mobilalkalmazás nélkül</div>
              </div>
              <div class="sales-device-stage" aria-label="Desktop és mobil foglalási nézet">
                <a class="sales-device-desktop" href="<?= sales_screenshot_src($showcaseScreenshots['booking']) ?>" target="_blank" rel="noopener" aria-label="Desktop foglalási nézet megnyitása">
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['booking']) ?>" alt="Desktop időpontfoglalási nézet" loading="lazy" decoding="async" />
                </a>
                <a class="sales-device-phone" href="<?= sales_screenshot_src($showcaseScreenshots['booking_mobile']) ?>" target="_blank" rel="noopener" aria-label="Mobil foglalási nézet megnyitása">
                  <span class="sales-device-phone-notch" aria-hidden="true"></span>
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['booking_mobile']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['booking_mobile']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
              </div>
            </article>
          <?php endif; ?>

          <div class="sales-backoffice-heading" data-reveal>
            <div>
              <p class="sales-kicker">A háttérben sem lesz káosz</p>
              <h3>A vállalkozó oldaláról is átlátható.</h3>
            </div>
            <p>Naptár, státuszok, ügyfelek és üzleti mutatók egy helyen – azért, hogy ne fejben vagy üzenetek között kelljen összerakni a napot.</p>
          </div>

          <div class="sales-backoffice-grid">
            <?php if ($showcaseScreenshots['admin']['available']): ?>
              <figure class="sales-backoffice-card sales-backoffice-card-wide" data-reveal>
                <a href="<?= sales_screenshot_src($showcaseScreenshots['admin']) ?>" target="_blank" rel="noopener">
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['admin']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['admin']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
                <figcaption><strong><?= htmlspecialchars($showcaseScreenshots['admin']['label']) ?></strong><span><?= htmlspecialchars($showcaseScreenshots['admin']['description']) ?></span></figcaption>
              </figure>
            <?php endif; ?>

            <?php if ($showcaseScreenshots['statistics']['available']): ?>
              <figure class="sales-backoffice-card" data-reveal>
                <a href="<?= sales_screenshot_src($showcaseScreenshots['statistics']) ?>" target="_blank" rel="noopener">
                  <img src="<?= sales_screenshot_src($showcaseScreenshots['statistics']) ?>" alt="<?= htmlspecialchars($showcaseScreenshots['statistics']['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
                <figcaption><strong><?= htmlspecialchars($showcaseScreenshots['statistics']['label']) ?></strong><span><?= htmlspecialchars($showcaseScreenshots['statistics']['description']) ?></span></figcaption>
              </figure>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($hasVideo): ?>
      <section class="sales-section sales-video-section" id="video">
        <div class="sales-wrap sales-video-grid">
          <div class="sales-video-copy" data-reveal>
            <p class="sales-kicker">Kb. 1 perc</p>
            <h2>Nézd meg, hogyan jut el a vendég a szolgáltatástól a lefoglalt időpontig.</h2>
          </div>
          <div
            class="sales-video-frame sales-video-consent"
            data-video-url="<?= htmlspecialchars($videoEmbedUrl, ENT_QUOTES) ?>"
            data-reveal
          >
            <img
              class="sales-video-preview"
              src="<?= asset('assets/sales/screenshots/01-home.webp') ?>"
              alt=""
              loading="lazy"
              decoding="async"
            />
            <div class="sales-video-consent-content">
              <strong>Bemutató videó</strong>
              <button type="button" class="sales-video-load">Videó betöltése</button>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section id="hogyan-mukodik" class="sales-section sales-section-alt">
      <div class="sales-wrap">
        <div class="sales-section-heading sales-section-heading-left" data-reveal>
          <p class="sales-kicker">A vendég oldaláról egyszerű</p>
          <h2>Három lépés. Ennyi.</h2>
        </div>

        <div class="sales-steps">
          <article data-reveal>
            <span>1</span>
            <div><h3>Szolgáltatást választ</h3><p>Látja az időtartamot, az árat és a szolgáltatás részleteit.</p></div>
          </article>
          <article data-reveal>
            <span>2</span>
            <div><h3>Időpontot választ</h3><p>A rendszer csak a valóban szabad, szabályoknak megfelelő időpontokat kínálja fel.</p></div>
          </article>
          <article data-reveal>
            <span>3</span>
            <div><h3>Foglal – a rendszer pedig dolgozik</h3><p>Visszaigazolás, kezelőlink és emlékeztetők automatikusan mehetnek ki.</p></div>
          </article>
        </div>

        <div class="sales-owner-panel" data-reveal>
          <div>
            <p class="sales-kicker">A vállalkozó oldaláról átlátható</p>
            <h3>Te dolgozol. A rendszer rendezi az időpontokat.</h3>
            <p>Az adminfelületen látod a naptárat, az ügyféltörténetet, a státuszokat és a fontosabb statisztikákat. Ha telefonon érkezik egy foglalás, azt is fel tudod vinni.</p>
          </div>
          <div class="sales-owner-metrics" aria-label="A rendszer három fő működési előnye">
            <div><strong>0–24</strong><small>a vendégek akkor foglalnak, amikor nekik kényelmes</small></div>
            <div><strong>1 hely</strong><small>foglalások, ügyfelek és státuszok egy adminfelületen</small></div>
            <div><strong>Auto</strong><small>visszaigazolások és emlékeztetők automatizálhatók</small></div>
          </div>
        </div>
      </div>
    </section>

    <section class="sales-section sales-fit-section">
      <div class="sales-wrap sales-fit-grid" data-reveal>
        <div class="sales-fit-intro">
          <p class="sales-kicker">Kinek való?</p>
          <h2>Ha időpontokra dolgozol, jó eséllyel neked.</h2>
        </div>
        <div class="sales-audience-list" aria-label="Példák olyan vállalkozásokra, amelyeknek hasznos lehet a rendszer">
          <article>
            <span>01</span>
            <div><strong>Szépség &amp; megjelenés</strong><p>Fodrász · barber · körmös · kozmetikus</p></div>
          </article>
          <article>
            <span>02</span>
            <div><strong>Wellness &amp; mozgás</strong><p>Masszőr · személyi edző · egyéni szolgáltatások</p></div>
          </article>
          <article>
            <span>03</span>
            <div><strong>Tudás &amp; tanácsadás</strong><p>Oktató · magántanár · coach · tanácsadó</p></div>
          </article>
        </div>
      </div>
    </section>

    <section class="sales-partner-strip">
      <div class="sales-wrap sales-partner-card" data-reveal>
        <span class="sales-partner-badge">Referencia partner program</span>
        <div>
          <h2>Az első referenciahelyek kedvezményes bevezetéssel indulhatnak.</h2>
          <p>Ha szeretnéd a saját vállalkozásod arculatára szabva látni a rendszert, írj, és megnézzük, hogyan illeszthető a működésedhez.</p>
        </div>
        <a class="sales-button sales-button-secondary" href="<?= htmlspecialchars($contactHref, ENT_QUOTES) ?>">Érdekel a lehetőség</a>
      </div>
    </section>

    <section class="sales-final-cta">
      <div class="sales-wrap sales-final-card" data-reveal>
        <div>
          <p class="sales-kicker">Nézd meg valódi működés közben</p>
          <h2>Próbáld ki vendégként az élő demót.</h2>
        </div>
        <div class="sales-final-actions">
          <a class="sales-button sales-button-light" href="<?= htmlspecialchars($demoUrl, ENT_QUOTES) ?>">Élő demo megnyitása <span aria-hidden="true">→</span></a>
          <a class="sales-text-link" href="<?= htmlspecialchars($contactHref, ENT_QUOTES) ?>">Kapcsolat: <?= htmlspecialchars($contactEmail) ?></a>
        </div>
      </div>
    </section>
  </main>

  <footer class="sales-footer">
    <div class="sales-wrap">
      <a class="sales-brand sales-brand-footer" href="#top">
        <span class="sales-brand-mark" aria-hidden="true">OB</span>
        <span><strong>Olcsi Business</strong><small>Saját weboldal. Saját időpontfoglaló.</small></span>
      </a>
      <p>© <?= date('Y') ?> Olcsi Business. A bemutatóban szereplő vállalkozás- és ügyféladatok mintaként szolgálnak.</p>
    </div>
  </footer>

  <script src="<?= view_asset('index.js') ?>" defer></script>
</body>
</html>
