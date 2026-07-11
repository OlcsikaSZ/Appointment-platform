<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin — Időpontfoglalás</title>
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <div id="adminApp" v-cloak>
    <div class="toast-stack">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" @click="toasts.dismiss(toast.id)">{{ toast.message }}</div>
    </div>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark admin-brand-mark">
          <img v-if="business.logoUrl" :src="business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
          <template v-else>{{ business.logoText || monogram(business.name) || '·' }}</template>
        </span>
        <span><strong>{{ business.name || 'Időpontfoglalás' }}</strong><small>Admin felület</small></span>
      </a>
      <nav>
        <a href="<?= route_url('main') ?>">Foglalási oldal</a>
        <a v-if="token" href="#" @click.prevent="logout">Kijelentkezés</a>
      </nav>
    </header>

    <main v-if="!token" class="shell login-shell">
      <section class="panel login-card">
        <span class="brand-mark" style="margin:0 auto 18px;">·</span>
        <p class="eyebrow">Admin belépés</p>
        <h1 style="font-size:26px;">Jelentkezz be</h1>
        <p class="lead" style="margin:8px auto 0;">A foglalások kezeléséhez lépj be a vállalkozásod fiókjával.</p>
        <form class="login-box" @submit.prevent="login">
          <label>E-mail cím <input v-model.trim="credentials.email" type="email" required autocomplete="username" /></label>
          <label>Jelszó <input v-model="credentials.password" type="password" required autocomplete="current-password" /></label>
          <button class="button primary block" type="submit" :disabled="loggingIn"><span v-if="loggingIn" class="spinner"></span>{{ loggingIn ? 'Belépés…' : 'Belépés' }}</button>
        </form>
      </section>
    </main>

    <main v-else class="shell admin-shell">
      <div class="admin-headline">
        <div><p class="eyebrow">Áttekintés</p><h1>Admin irányítópult</h1></div>
        <button class="button sm" type="button" @click="refresh"><span v-if="loading" class="spinner"></span> Frissítés</button>
      </div>

      <div class="stat-row">
        <div class="stat-card"><span class="label">Összes foglalás</span><span class="value">{{ stats.total ?? '–' }}</span></div>
        <div class="stat-card accent"><span class="label">Mai aktív</span><span class="value">{{ stats.today ?? '–' }}</span></div>
        <div class="stat-card"><span class="label">Aktív foglalás</span><span class="value">{{ stats.active ?? '–' }}</span></div>
        <div class="stat-card"><span class="label">Lemondva</span><span class="value">{{ stats.cancelled ?? '–' }}</span></div>
      </div>

      <div class="tabs">
        <button :class="{active: activeTab === 'calendar'}" @click="activeTab = 'calendar'">Naptár</button>
        <button :class="{active: activeTab === 'manual'}" @click="activeTab = 'manual'">Kézi foglalás</button>
        <button :class="{active: activeTab === 'services'}" @click="activeTab = 'services'">Szolgáltatások</button>
        <button :class="{active: activeTab === 'website'}" @click="openWebsiteTab">Weboldal</button>
        <button :class="{active: activeTab === 'list'}" @click="activeTab = 'list'">Lista</button>
      </div>

      <section v-if="activeTab === 'calendar'" class="admin-single-column">
        <div class="panel today-panel">
          <div class="section-title"><div><p class="eyebrow">Mai foglalások</p><h2>Ki jön ma?</h2></div></div>
          <div v-if="!todayBookings.length" class="empty compact">Ma nincs aktív foglalás. Ritka nyugi, becsüld meg.</div>
          <div v-else class="today-list">
            <article v-for="item in todayBookings" :key="item.id" class="today-item" :class="item.status">
              <strong>{{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }}</strong>
              <span>{{ item.customer_name }} · {{ item.service_name }}</span>
              <small>{{ item.customer_contact }}<template v-if="item.customer_note"> · {{ item.customer_note }}</template></small>
              <div class="inline-actions" v-if="item.status === 'booked'">
                <button class="button sm" @click="setStatus(item, 'completed')">Teljesítve</button>
                <button class="button sm" @click="setStatus(item, 'no_show')">Nem jött el</button>
                <button class="button sm danger" @click="setStatus(item, 'cancelled')">Lemondva</button>
              </div>
            </article>
          </div>
        </div>

        <div class="panel calendar-panel month-calendar-panel">
          <div class="calendar-toolbar month-calendar-toolbar">
            <div>
              <p class="eyebrow">Naptár</p>
              <h2>{{ currentMonthLabel }}</h2>
            </div>
            <div class="calendar-actions">
              <button class="button sm ghost month-nav" type="button" aria-label="Előző hónap" @click="moveCalendar(-1)">‹</button>
              <button class="button sm" type="button" @click="goToday">Aktuális hónap</button>
              <button class="button sm ghost month-nav" type="button" aria-label="Következő hónap" @click="moveCalendar(1)">›</button>
            </div>
          </div>

          <div class="month-weekdays" aria-hidden="true">
            <span v-for="label in weekdayLabels" :key="label">{{ label }}</span>
          </div>

          <div class="month-calendar-grid" role="grid" :aria-label="currentMonthLabel">
            <article
              v-for="day in monthCalendarDays"
              :key="day.key"
              class="month-day"
              :class="{ 'outside-month': !day.inCurrentMonth, today: day.isToday, 'has-entries': calendarEntriesForDay(day.key).length }"
              role="gridcell"
            >
              <header class="month-day-head">
                <span class="month-day-number">{{ day.dayNumber }}</span>
                <span v-if="day.isToday" class="today-label">Ma</span>
              </header>

              <div class="month-day-events">
                <template v-for="entry in calendarEntriesForDay(day.key).slice(0, 3)" :key="entry.key">
                  <div
                    v-if="entry.type === 'block'"
                    class="month-event block"
                    :title="`${shortTime(entry.item.start_time)}–${shortTime(entry.item.end_time)} · ${entry.item.reason || 'Blokkolva'}`"
                  >
                    <span>{{ shortTime(entry.item.start_time) }}</span>
                    <strong>{{ entry.item.reason || 'Blokkolva' }}</strong>
                  </div>
                  <div
                    v-else
                    class="month-event booking"
                    :class="entry.item.status"
                    :title="`${shortTime(entry.item.start_time)} · ${entry.item.customer_name} · ${entry.item.service_name}`"
                  >
                    <span>{{ shortTime(entry.item.start_time) }}</span>
                    <strong>{{ entry.item.customer_name }}</strong>
                  </div>
                </template>
                <div v-if="calendarEntriesForDay(day.key).length > 3" class="month-more">
                  +{{ calendarEntriesForDay(day.key).length - 3 }} további
                </div>
              </div>
            </article>
          </div>
        </div>

        <div class="panel block-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Naptár lezárása</p>
              <h2>Időszak blokkolása</h2>
              <p class="lead block-lead">Zárd le a naptárad egy adott napra, például szabadság vagy karbantartás miatt. A blokkolás azonnal megjelenik a havi naptárban is.</p>
            </div>
          </div>

          <div class="block-form-grid">
            <label>Dátum <input v-model="block.date" type="date" /></label>
            <label>Kezdés <input v-model="block.start_time" type="time" /></label>
            <label>Vége <input v-model="block.end_time" type="time" /></label>
            <label>Indoklás <input v-model.trim="block.reason" placeholder="pl. Szabadság" /></label>
          </div>
          <button class="button primary" type="button" :disabled="blockingTime" @click="saveBlock">{{ blockingTime ? 'Mentés…' : 'Blokk mentése' }}</button>

          <div v-if="blockedTimes.length" class="block-list block-list-wide">
            <div v-for="item in blockedTimes" :key="item.id" class="block-item">
              <div class="info"><strong>{{ item.date }} · {{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }}</strong><span>{{ item.reason || 'Nincs indoklás' }}</span></div>
              <button class="icon-btn" type="button" title="Blokk törlése" @click="deleteBlock(item)">×</button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'manual'" class="panel form-panel">
        <p class="eyebrow">Admin foglalás</p><h2>Kézi foglalás felvétele</h2>
        <form class="admin-form-grid" @submit.prevent="saveManualBooking">
          <label>Szolgáltatás <select v-model="manual.service_id" @change="loadSlots" required><option value="" disabled>Válassz szolgáltatást</option><option v-for="service in services.filter(s => s.active)" :key="service.id" :value="service.id">{{ service.name }} · {{ service.duration_minutes }} perc</option></select></label>
          <label>Dátum <input v-model="manual.date" type="date" required @change="loadSlots" /></label>
          <label>Időpont <select v-model="manual.time" required><option value="" disabled>Nincs szabad időpont</option><option v-for="slot in slots" :key="slot.time" :value="slot.time">{{ slot.time }}–{{ slot.endTime }}</option></select></label>
          <label>Vendég neve <input v-model.trim="manual.customer_name" required placeholder="pl. Kovács Anna" /></label>
          <label>Telefon / e-mail <input v-model.trim="manual.customer_contact" required placeholder="+36... vagy email" /></label>
          <label class="full">Ügyfél megjegyzés <textarea v-model.trim="manual.customer_note" rows="4" placeholder="pl. kapucsengő, extra kérés, előzmény"></textarea></label>
          <div class="full"><button class="button primary" :disabled="savingManual || !manual.time">{{ savingManual ? 'Mentés…' : 'Foglalás mentése' }}</button></div>
        </form>
      </section>

      <section v-if="activeTab === 'services'" class="panel service-list-panel services-single-panel">
        <div class="section-title">
          <div><p class="eyebrow">Szolgáltatások</p><h2>Árak, időtartamok, sorrend</h2></div>
          <button class="button sm" type="button" @click="openServiceModal()">Új szolgáltatás</button>
        </div>
        <div class="service-admin-list">
          <article v-for="service in services" :key="service.id" class="service-admin-card" :class="{inactive: !service.active}">
            <div><strong>{{ service.name }}</strong><small>{{ service.category }} · {{ service.duration_minutes }} perc · {{ price(service) || 'Nincs ár' }}</small><p>{{ service.description }}</p></div>
            <div class="service-actions"><button class="button sm" @click="moveService(service, -1)">↑</button><button class="button sm" @click="moveService(service, 1)">↓</button><button class="button sm" @click="editService(service)">Szerkesztés</button><button class="button sm" @click="toggleService(service)">{{ service.active ? 'Inaktiválás' : 'Aktiválás' }}</button></div>
          </article>
          <p v-if="!services.length" class="empty compact">Még nincs szolgáltatás.</p>
        </div>
      </section>


      <section v-if="activeTab === 'website'" class="website-admin-section">
        <section class="panel website-settings-panel">
          <div class="section-title">
            <div><p class="eyebrow">Weboldal beállítások</p><h2>Arculat és nyilvános adatok</h2></div>
            <span class="save-state" v-if="savingWebsite">Mentés…</span>
          </div>

          <div class="logo-editor">
            <div class="logo-preview admin-logo-preview">
              <img v-if="business.logoUrl" :src="business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
              <template v-else>{{ business.logoText || monogram(websiteForm.name) || 'IP' }}</template>
            </div>
            <div>
              <strong>Logó</strong>
              <p>JPG, PNG vagy WebP, legfeljebb 3 MB. Ha nincs feltöltve logó, automatikus monogram jelenik meg.</p>
              <div class="inline-actions">
                <label class="button sm file-button">{{ uploadingLogo ? 'Feltöltés…' : 'Logó feltöltése' }}<input ref="logoInput" type="file" accept="image/jpeg,image/png,image/webp" @change="uploadLogo" /></label>
                <button v-if="business.logoUrl" class="button sm danger" type="button" @click="deleteLogo">Logó törlése</button>
              </div>
            </div>
          </div>

          <form class="website-form" @submit.prevent="saveWebsite">
            <div class="two-cols">
              <label>Cégnév <input v-model.trim="websiteForm.name" required maxlength="160" /></label>
              <label>Rövid alcím <input v-model.trim="websiteForm.tagline" maxlength="240" /></label>
            </div>

            <label>Hero főcím <input v-model.trim="websiteForm.hero_title" maxlength="220" placeholder="Egyszerű foglalás. Megbízható szolgáltatás." /></label>
            <label>Hero leírás <textarea v-model.trim="websiteForm.hero_text" rows="3" maxlength="1200"></textarea></label>

            <div class="two-cols">
              <label>Bemutatkozás címe <input v-model.trim="websiteForm.about_title" maxlength="160" /></label>
              <label>Telefonszám <input v-model.trim="websiteForm.phone" maxlength="80" /></label>
            </div>
            <label>Bemutatkozó szöveg <textarea v-model.trim="websiteForm.about_text" rows="6" maxlength="4000"></textarea></label>

            <div class="two-cols">
              <label>E-mail <input v-model.trim="websiteForm.email" type="email" maxlength="160" /></label>
              <label>Cím <input v-model.trim="websiteForm.address" maxlength="255" /></label>
            </div>
            <label>Nyitvatartás <textarea v-model.trim="websiteForm.opening_hours" rows="4" maxlength="2000" placeholder="Hétfő–Péntek: 09:00–17:00"></textarea></label>
            <label>Google Maps link <input v-model.trim="websiteForm.google_maps_url" type="url" maxlength="2000" placeholder="https://www.google.com/maps/..." /></label>

            <button class="button primary" type="submit" :disabled="savingWebsite">{{ savingWebsite ? 'Mentés…' : 'Weboldal beállítások mentése' }}</button>
          </form>
        </section>

        <section class="panel website-preview-panel full-width-preview">
          <div class="section-title">
            <div>
              <p class="eyebrow">Élő előnézet</p>
              <h2>A nyilvános oldal jelenlegi megjelenése</h2>
              <p class="lead preview-lead">Az előnézet a mentett beállításokat mutatja, teljes weboldal méretben.</p>
            </div>
            <a class="button sm" href="<?= route_url('main') ?>" target="_blank" rel="noopener">Nyilvános oldal megnyitása</a>
          </div>
          <div class="website-preview-frame-wrap">
            <iframe :key="websitePreviewVersion" ref="websitePreview" src="<?= route_url('main') ?>" title="Nyilvános weboldal élő előnézete" loading="lazy"></iframe>
          </div>
        </section>

        <div class="content-management-grid">
          <section class="panel content-editor-panel">
            <div class="section-title"><div><p class="eyebrow">Vélemények</p><h2>Bizalomépítő visszajelzések</h2></div><button class="button sm" type="button" @click="openReviewModal()">Új vélemény</button></div>

            <div class="content-admin-list">
              <article v-for="review in reviews" :key="review.id" class="content-admin-card" :class="{inactive: !review.active}">
                <div><strong>{{ review.author }}</strong><span class="stars-admin">{{ '★'.repeat(review.rating) }}</span><p>{{ review.text }}</p></div>
                <div class="service-actions"><button class="button sm" type="button" @click="editReview(review)">Szerkesztés</button><button class="button sm danger" type="button" @click="deleteReview(review)">Törlés</button></div>
              </article>
              <p v-if="!reviews.length" class="empty compact">Még nincs vélemény.</p>
            </div>
          </section>

          <section class="panel content-editor-panel">
            <div class="section-title"><div><p class="eyebrow">GYIK</p><h2>Gyakori kérdések</h2></div><button class="button sm" type="button" @click="openFaqModal()">Új kérdés</button></div>

            <div class="content-admin-list">
              <article v-for="faq in faqs" :key="faq.id" class="content-admin-card" :class="{inactive: !faq.active}">
                <div><strong>{{ faq.question }}</strong><p>{{ faq.answer }}</p></div>
                <div class="service-actions"><button class="button sm" type="button" @click="editFaq(faq)">Szerkesztés</button><button class="button sm danger" type="button" @click="deleteFaq(faq)">Törlés</button></div>
              </article>
              <p v-if="!faqs.length" class="empty compact">Még nincs GYIK elem.</p>
            </div>
          </section>
        </div>
      </section>

      <section v-if="activeTab === 'list'">
        <div class="admin-toolbar">
          <div class="filter-row">
            <select v-model="filters.status" @change="refresh"><option value="">Minden státusz</option><option value="booked">Foglalva</option><option value="completed">Teljesítve</option><option value="cancelled">Lemondva</option><option value="no_show">Nem jelent meg</option></select>
            <input v-model="filters.date" type="date" @change="refresh" />
            <input v-model.trim="filters.q" type="text" placeholder="Keresés név / telefon / email / megjegyzés" @input="debouncedRefresh" />
            <button class="button ghost sm" type="button" @click="clearFilters">Szűrők törlése</button>
          </div>
        </div>
        <div class="table-wrap">
          <p v-if="!loading && !bookings.length" class="empty">Nincs a szűrésnek megfelelő foglalás.</p>
          <table v-else><thead><tr><th>Dátum</th><th>Idő</th><th>Szolgáltatás</th><th>Vendég</th><th>Megjegyzés</th><th>Státusz</th><th>Műveletek</th></tr></thead><tbody>
            <tr v-for="item in bookings" :key="item.id"><td class="mono">{{ item.date }}</td><td class="mono">{{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }}</td><td>{{ item.service_name }}</td><td class="customer-cell"><strong>{{ item.customer_name }}</strong><small>{{ item.customer_contact }}</small></td><td>{{ item.customer_note || '—' }}</td><td><span class="badge" :class="item.status">{{ statusLabel(item.status) }}</span></td><td class="actions-cell"><button v-if="item.status === 'booked'" class="button sm" @click="setStatus(item, 'completed')">Teljesítve</button><button v-if="item.status === 'booked'" class="button sm" @click="setStatus(item, 'no_show')">Nem jött el</button><button v-if="item.status === 'booked'" class="button sm danger" @click="setStatus(item, 'cancelled')">Lemondás</button></td></tr>
          </tbody></table>
        </div>
      </section>
    </main>

    <div v-if="serviceModalOpen" class="modal-backdrop" @click.self="closeServiceModal">
      <section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
        <div class="modal-head">
          <div><p class="eyebrow">{{ serviceForm.id ? 'Szerkesztés' : 'Új szolgáltatás' }}</p><h2 id="serviceModalTitle">{{ serviceForm.id ? serviceForm.name : 'Szolgáltatás felvétele' }}</h2></div>
          <button class="modal-close" type="button" aria-label="Bezárás" @click="closeServiceModal">×</button>
        </div>
        <form class="modal-form" @submit.prevent="saveService">
          <label>Név <input ref="serviceNameInput" v-model.trim="serviceForm.name" required /></label>
          <label>Kategória <input v-model.trim="serviceForm.category" /></label>
          <label>Leírás <textarea v-model.trim="serviceForm.description" rows="3"></textarea></label>
          <label>Szolgáltatás kép URL <input v-model.trim="serviceForm.image_url" type="url" placeholder="https://... (nem kötelező)" /></label>
          <div class="two-cols"><label>Időtartam / perc <input v-model.number="serviceForm.duration_minutes" type="number" min="5" required /></label><label>Puffer / perc <input v-model.number="serviceForm.buffer_minutes" type="number" min="0" /></label></div>
          <div class="two-cols"><label>Ár / Ft <input v-model.number="serviceForm.price_forint" type="number" min="0" placeholder="pl. 12000" /></label><label>Sorrend <input v-model.number="serviceForm.sort_order" type="number" min="0" /></label></div>
          <label class="checkline"><input v-model="serviceForm.active" type="checkbox" /> Aktív, foglalható szolgáltatás</label>
          <div class="modal-actions"><button class="button" type="button" @click="closeServiceModal">Mégse</button><button class="button primary" :disabled="savingService">{{ savingService ? 'Mentés…' : 'Szolgáltatás mentése' }}</button></div>
        </form>
      </section>
    </div>

    <div v-if="reviewModalOpen" class="modal-backdrop" @click.self="closeReviewModal">
      <section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
        <div class="modal-head">
          <div><p class="eyebrow">Vélemények</p><h2 id="reviewModalTitle">{{ reviewForm.id ? 'Vélemény szerkesztése' : 'Új vélemény' }}</h2></div>
          <button class="modal-close" type="button" aria-label="Bezárás" @click="closeReviewModal">×</button>
        </div>
        <form class="modal-form" @submit.prevent="saveReview">
          <div class="two-cols"><label>Név <input ref="reviewAuthorInput" v-model.trim="reviewForm.author" required maxlength="120" /></label><label>Értékelés <select v-model.number="reviewForm.rating"><option :value="5">5 csillag</option><option :value="4">4 csillag</option><option :value="3">3 csillag</option><option :value="2">2 csillag</option><option :value="1">1 csillag</option></select></label></div>
          <label>Szöveg <textarea v-model.trim="reviewForm.text" rows="5" required maxlength="1200"></textarea></label>
          <div class="two-cols"><label>Sorrend <input v-model.number="reviewForm.sort_order" type="number" min="0" max="1000" /></label><label class="checkline"><input v-model="reviewForm.active" type="checkbox" /> Megjelenik a weboldalon</label></div>
          <div class="modal-actions"><button class="button" type="button" @click="closeReviewModal">Mégse</button><button class="button primary" :disabled="savingReview">{{ savingReview ? 'Mentés…' : 'Vélemény mentése' }}</button></div>
        </form>
      </section>
    </div>

    <div v-if="faqModalOpen" class="modal-backdrop" @click.self="closeFaqModal">
      <section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="faqModalTitle">
        <div class="modal-head">
          <div><p class="eyebrow">GYIK</p><h2 id="faqModalTitle">{{ faqForm.id ? 'GYIK szerkesztése' : 'Új GYIK elem' }}</h2></div>
          <button class="modal-close" type="button" aria-label="Bezárás" @click="closeFaqModal">×</button>
        </div>
        <form class="modal-form" @submit.prevent="saveFaq">
          <label>Kérdés <input ref="faqQuestionInput" v-model.trim="faqForm.question" required maxlength="255" /></label>
          <label>Válasz <textarea v-model.trim="faqForm.answer" rows="6" required maxlength="3000"></textarea></label>
          <div class="two-cols"><label>Sorrend <input v-model.number="faqForm.sort_order" type="number" min="0" max="1000" /></label><label class="checkline"><input v-model="faqForm.active" type="checkbox" /> Megjelenik a weboldalon</label></div>
          <div class="modal-actions"><button class="button" type="button" @click="closeFaqModal">Mégse</button><button class="button primary" :disabled="savingFaq">{{ savingFaq ? 'Mentés…' : 'GYIK mentése' }}</button></div>
        </form>
      </section>
    </div>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
