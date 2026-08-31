<?php
$showcaseScreenshots = [
    [
        'file' => '01-home.webp',
        'label' => 'Saját arculatú vállalkozói weboldal',
        'description' => 'A szolgáltató saját neve, arculata, bemutatkozása és szolgáltatásai egy rendezett, mobilbarát felületen.',
    ],
    [
        'file' => '02-services.webp',
        'label' => 'Átlátható szolgáltatásválasztás',
        'description' => 'A vendég rögtön látja, mit foglalhat, mennyi ideig tart és mennyibe kerül.',
    ],
    [
        'file' => '03-booking.webp',
        'label' => 'Valóban szabad időpontok',
        'description' => 'A rendszer a munkaidő, a foglalások és a blokkolások alapján kínál fel időpontot.',
    ],
    [
        'file' => '04-booking-mobile.webp',
        'label' => 'Telefonról is egyszerű',
        'description' => 'A foglalási folyamat mobilon is gyorsan és kényelmesen használható.',
    ],
    [
        'file' => '05-admin-calendar.webp',
        'label' => 'Adminnaptár egy helyen',
        'description' => 'Foglalások, manuális időpontok, blokkolások és státuszok egyetlen kezelőfelületen.',
    ],
    [
        'file' => '06-statistics.webp',
        'label' => 'Statisztikák és áttekintés',
        'description' => 'A vállalkozó gyorsan átláthatja a foglalásokat, teljesítéseket és fontosabb mutatókat.',
    ],
];

$availableScreenshots = array_values(array_filter(
    $showcaseScreenshots,
    static fn (array $item): bool => is_file(__DIR__.'/../../assets/sales/screenshots/'.$item['file'])
));

$demoUrl = route_url('main');
$contactEmail = 'olcsikaszbusiness@gmail.com';
$contactHref = 'mailto:'.$contactEmail.'?subject='.rawurlencode('DEMÓ – Olcsi Business időpontfoglaló');
?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Saját arculatú vállalkozói weboldal online időpontfoglalással, adminfelülettel, automatikus e-mailekkel és üzemeltetési háttérrel." />
  <meta name="theme-color" content="#191b1d" />
  <meta property="og:title" content="Olcsi Business – weboldal és online időpontfoglalás" />
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
          <small>Weboldal + online időpontfoglalás</small>
        </span>
      </a>

      <nav class="sales-nav" aria-label="Bemutató navigáció">
        <a href="#mit-kapsz">Mit kapsz?</a>
        <a href="#hogyan-mukodik">Hogyan működik?</a>
        <a class="sales-nav-cta" href="<?= htmlspecialchars($demoUrl, ENT_QUOTES) ?>">Élő demo</a>
      </nav>
    </div>
  </header>

  <main id="sales-main">
    <section class="sales-hero">
      <div class="sales-wrap sales-hero-grid">
        <div class="sales-hero-copy">
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

        <div class="sales-product-scene" aria-label="Az időpontfoglaló rendszer szemléltetése">
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
                  <button type="button">09:00</button><button type="button">09:45</button>
                  <button type="button" class="active">10:30</button><button type="button">13:15</button>
                  <button type="button">15:00</button><button type="button">16:30</button>
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
        <div class="sales-section-heading">
          <p class="sales-kicker">Nem csak egy foglaló</p>
          <h2>Egy komplett vállalkozói online rendszer.</h2>
          <p>Az oldal megjelenésétől az időpontkezelésen át az automatikus értesítésekig egyetlen, vállalkozásodra szabható rendszerben.</p>
        </div>

        <div class="sales-feature-grid">
          <article class="sales-feature-card sales-feature-card-large">
            <span class="sales-feature-number">01</span>
            <h3>Saját weboldal és arculat</h3>
            <p>Saját név, logó, színek, bemutatkozás, szolgáltatások, árak, képek, kapcsolati adatok és GYIK.</p>
          </article>
          <article class="sales-feature-card">
            <span class="sales-feature-number">02</span>
            <h3>Online időpontfoglalás</h3>
            <p>A vendég csak a valóban elérhető időpontok közül választ.</p>
          </article>
          <article class="sales-feature-card">
            <span class="sales-feature-number">03</span>
            <h3>Adminnaptár</h3>
            <p>Foglalás, blokkolás, manuális időpont és státuszkezelés egy helyen.</p>
          </article>
          <article class="sales-feature-card">
            <span class="sales-feature-number">04</span>
            <h3>Automatikus e-mailek</h3>
            <p>Visszaigazolás, módosítás, lemondás és időpont-emlékeztetők.</p>
          </article>
          <article class="sales-feature-card sales-feature-card-dark">
            <span class="sales-feature-number">05</span>
            <h3>Ügyféltörténet és statisztikák</h3>
            <p>Átláthatóbb működés és kevesebb fejben tartandó információ.</p>
          </article>
          <article class="sales-feature-card">
            <span class="sales-feature-number">06</span>
            <h3>Technikai háttér</h3>
            <p>Beállítás, üzemeltetés, mentések, frissítések és support – nem neked kell szervert menedzselned.</p>
          </article>
        </div>
      </div>
    </section>

    <section id="hogyan-mukodik" class="sales-section sales-section-alt">
      <div class="sales-wrap">
        <div class="sales-section-heading sales-section-heading-left">
          <p class="sales-kicker">A vendég oldaláról egyszerű</p>
          <h2>Három lépés. Ennyi.</h2>
        </div>

        <div class="sales-steps">
          <article>
            <span>1</span>
            <div><h3>Szolgáltatást választ</h3><p>Látja az időtartamot, az árat és a szolgáltatás részleteit.</p></div>
          </article>
          <article>
            <span>2</span>
            <div><h3>Időpontot választ</h3><p>A rendszer csak a valóban szabad, szabályoknak megfelelő időpontokat kínálja fel.</p></div>
          </article>
          <article>
            <span>3</span>
            <div><h3>Foglal – a rendszer pedig dolgozik</h3><p>Visszaigazolás, kezelőlink és emlékeztetők automatikusan mehetnek ki.</p></div>
          </article>
        </div>

        <div class="sales-owner-panel">
          <div>
            <p class="sales-kicker">A vállalkozó oldaláról átlátható</p>
            <h3>Te dolgozol. A rendszer rendezi az időpontokat.</h3>
            <p>Az adminfelületen látod a naptárat, az ügyféltörténetet, a státuszokat és a fontosabb statisztikákat. Ha telefonon érkezik egy foglalás, azt is fel tudod vinni.</p>
          </div>
          <div class="sales-owner-metrics" aria-hidden="true">
            <div><strong>28</strong><small>foglalás ezen a héten</small></div>
            <div><strong>92%</strong><small>teljesített időpont</small></div>
            <div><strong>4.9</strong><small>mintaértékelés</small></div>
          </div>
        </div>
      </div>
    </section>

    <?php if ($availableScreenshots): ?>
      <section class="sales-section sales-screenshot-section" id="kepernyokepek">
        <div class="sales-wrap">
          <div class="sales-section-heading">
            <p class="sales-kicker">Nézd meg közelebbről</p>
            <h2>Így néz ki működés közben.</h2>
            <p>Valós képernyőképek az élő demó rendszerből.</p>
          </div>

          <div class="sales-screenshot-grid">
            <?php foreach ($availableScreenshots as $index => $screenshot): ?>
              <figure class="sales-screenshot-card <?= $index === 0 ? 'sales-screenshot-card-featured' : '' ?>">
                <a href="<?= asset('assets/sales/screenshots/'.$screenshot['file']) ?>" target="_blank" rel="noopener">
                  <img src="<?= asset('assets/sales/screenshots/'.$screenshot['file']) ?>" alt="<?= htmlspecialchars($screenshot['label'], ENT_QUOTES) ?>" loading="lazy" decoding="async" />
                </a>
                <figcaption>
                  <strong><?= htmlspecialchars($screenshot['label']) ?></strong>
                  <span><?= htmlspecialchars($screenshot['description']) ?></span>
                </figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="sales-section sales-fit-section">
      <div class="sales-wrap sales-fit-grid">
        <div>
          <p class="sales-kicker">Kinek való?</p>
          <h2>Ha időpontokra dolgozol, valószínűleg neked.</h2>
        </div>
        <div class="sales-professions" aria-label="Példák célcsoportokra">
          <span>Fodrász</span><span>Barber</span><span>Masszőr</span><span>Körmös</span><span>Kozmetikus</span><span>Edző</span><span>Oktató</span><span>Tanácsadó</span><span>Szolgáltató</span>
        </div>
      </div>
    </section>

    <section class="sales-final-cta">
      <div class="sales-wrap sales-final-card">
        <div>
          <p class="sales-kicker">Nézd meg valódi működés közben</p>
          <h2>Próbáld ki vendégként az élő demót.</h2>
          <p>Nem prezentáció: kattints végig egy valódi foglalási folyamatot, és nézd meg, milyen élményt kapna a saját vendéged.</p>
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
