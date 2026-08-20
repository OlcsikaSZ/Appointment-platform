<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Online időpontfoglalás gyorsan, egyszerűen és átláthatóan." />
  <title>Online időpontfoglalás</title>
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <a class="skip-link" href="#main-content">Ugrás a foglaláshoz</a>
  <div id="bookingApp" v-cloak>
    <div class="toast-stack" aria-live="polite" aria-atomic="false">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" :role="toast.kind === 'error' ? 'alert' : 'status'" @click="toasts.dismiss(toast.id)">
        {{ toast.message }}
      </div>
    </div>

    <header class="topbar public-topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark business-logo-mark">
          <img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
          <template v-else>{{ business.logoText || monogram(business.name) || 'IP' }}</template>
        </span>
        <span>
          <strong>{{ business.name || 'Időpontfoglalás' }}</strong>
          <small>{{ business.tagline || 'Foglalj pár kattintással' }}</small>
        </span>
      </a>
      <nav class="public-nav">
        <a href="#rolunk">Rólunk</a>
        <a href="#kapcsolat">Kapcsolat</a>
        <a class="nav-cta" href="#foglalas">Foglalás</a>
        <a href="<?= route_url('account') ?>">Fiókom</a>
      </nav>
      <a class="mobile-account-button" href="<?= route_url('account') ?>" :aria-label="customerAccount.id ? customerAccount.name + ' fiókja' : 'Ügyfélfiók megnyitása'">
        {{ customerAccount.id ? (monogram(customerAccount.name) || 'F') : 'F' }}
      </a>
    </header>

    <section v-if="step < 4" class="shell hero-section">
      <div class="hero-copy">
        <p class="eyebrow">Online időpontfoglalás</p>
        <h1>{{ business.heroTitle || 'Egyszerű foglalás. Megbízható szolgáltatás.' }}</h1>
        <p class="hero-lead">{{ business.heroText || 'Válassz szolgáltatást és időpontot néhány kattintással.' }}</p>
        <div class="hero-actions">
          <a class="button primary" href="#foglalas">Időpontot foglalok</a>
          <a v-if="business.phone" class="button" :href="phoneHref">{{ business.phone }}</a>
        </div>
        <div class="trust-row" aria-label="Előnyök">
          <span>✓ Gyors online foglalás</span>
          <span>✓ Rugalmas módosítás</span>
          <span>✓ Átlátható időpontok</span>
        </div>
      </div>

      <aside class="hero-card">
        <div class="hero-logo-large business-logo-mark">
          <img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
          <template v-else>{{ business.logoText || monogram(business.name) || 'IP' }}</template>
        </div>
        <div>
          <p class="eyebrow">{{ business.name || 'Vállalkozás' }}</p>
          <h2>{{ business.tagline || 'Foglalj időpontot egyszerűen' }}</h2>
        </div>
        <dl class="hero-contact-list">
          <div v-if="business.phone"><dt>Telefon</dt><dd><a :href="phoneHref">{{ business.phone }}</a></dd></div>
          <div v-if="business.email"><dt>E-mail</dt><dd><a :href="emailHref">{{ business.email }}</a></dd></div>
          <div v-if="business.address"><dt>Cím</dt><dd>{{ business.address }}</dd></div>
        </dl>
      </aside>
    </section>

    <!-- STEP 1-3: WIZARD -->
    <div v-if="step < 4" id="foglalas" class="booking-anchor" aria-hidden="true"></div>
    <main v-if="step < 4" id="main-content" ref="bookingSection" class="shell booking-grid booking-section" tabindex="-1">
      <section class="panel">
        <p class="eyebrow">Új foglalás</p>
        <h1>Foglaljunk egy időpontot</h1>
        <p class="lead">Válaszd ki a szolgáltatást, majd a neked megfelelő időpontot — a foglalás két percet vesz igénybe.</p>

        <ol class="stepper" aria-label="Foglalási lépések">
          <li :class="{ done: step > 1, active: step === 1 }">
            <span class="dot">{{ step > 1 ? '✓' : '1' }}</span><span class="step-label">Szolgáltatás</span>
          </li>
          <span class="bar" :class="{ filled: step > 1 }"></span>
          <li :class="{ done: step > 2, active: step === 2 }">
            <span class="dot">{{ step > 2 ? '✓' : '2' }}</span><span class="step-label">Időpont</span>
          </li>
          <span class="bar" :class="{ filled: step > 2 }"></span>
          <li :class="{ active: step === 3 }">
            <span class="dot">3</span><span class="step-label">Adatok</span>
          </li>
        </ol>

        <div v-if="loadingInit" class="service-grid" aria-busy="true" aria-label="Szolgáltatások betöltése">
          <div class="skeleton" style="height:210px;border-radius:12px;" v-for="n in 4" :key="n"></div>
        </div>

        <template v-else-if="step === 1">
          <div v-if="categories.length > 1" class="chip-row">
            <button class="chip" :class="{ selected: selectedCategory === 'all' }" type="button" @click="selectedCategory = 'all'">Összes</button>
            <button v-for="cat in categories" :key="cat" class="chip" :class="{ selected: selectedCategory === cat }" type="button" @click="selectedCategory = cat">{{ cat }}</button>
          </div>

          <p v-if="!services.length" class="empty">Jelenleg nincs elérhető szolgáltatás. Nézz vissza később.</p>

          <div v-else class="service-grid">
            <button v-for="(item, index) in filteredServices" :key="item.id" class="service-card service-card-with-image" :class="{ selected: selectedService && selectedService.id === item.id }" type="button" @click="selectService(item)">
              <span class="service-image" :class="`placeholder-${index % 4}`">
                <img v-if="item.image_url" :src="item.image_thumbnail_url || item.image_url" :alt="item.name" loading="lazy" decoding="async" />
                <span v-else class="service-placeholder-mark">{{ serviceInitials(item.name) }}</span>
              </span>
              <span class="check">
                <svg viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.2 12L13 4" stroke="#1c2541" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
              <span class="cat">{{ item.category }}</span>
              <span class="name">{{ item.name }}</span>
              <span class="desc">{{ item.description || '' }}</span>
              <span class="meta">
                <span>{{ formatDuration(item.duration_minutes) }}</span>
                <span v-if="priceLabel(item)" class="price">{{ priceLabel(item) }}</span>
              </span>
            </button>
          </div>

          <div class="button-row">
            <button class="button primary" type="button" :disabled="!selectedService" @click="goToStep(2)">Tovább az időpontra</button>
          </div>
        </template>

        <template v-else-if="step === 2">
          <transition name="booking-calendar-view" mode="out-in">
            <div v-if="bookingCalendarMode === 'month'" key="booking-month" class="public-booking-calendar-stage">
              <div class="public-calendar-toolbar">
                <div>
                  <p class="eyebrow">Válassz napot</p>
                  <h2>{{ publicMonthLabel }}</h2>
                </div>
                <div class="public-calendar-actions">
                  <button class="button sm ghost" type="button" :disabled="!canMovePublicMonthBack" aria-label="Előző hónap" @click="movePublicMonth(-1)">‹</button>
                  <button class="button sm" type="button" @click="goPublicCurrentMonth">Aktuális hónap</button>
                  <button class="button sm ghost" type="button" aria-label="Következő hónap" @click="movePublicMonth(1)">›</button>
                </div>
              </div>

              <div class="public-month-weekdays" aria-hidden="true">
                <span>H</span><span>K</span><span>Sze</span><span>Cs</span><span>P</span><span>Szo</span><span>V</span>
              </div>
              <div class="public-month-grid" role="grid" :aria-label="publicMonthLabel">
                <button
                  v-for="day in publicMonthDays"
                  :key="day.key"
                  type="button"
                  class="public-month-day"
                  :class="{
                    'outside-month': !day.inCurrentMonth,
                    today: day.isToday,
                    disabled: day.disabled,
                    closed: day.isClosed,
                    'sold-out': day.isSoldOut,
                    'has-own-booking': day.ownBookings.length > 0
                  }"
                  :disabled="day.disabled"
                  @click="openBookingDay(day.key)"
                >
                  <span class="public-month-day-head">
                    <span class="public-month-day-number">{{ day.dayNumber }}</span>
                    <small v-if="day.isToday">Ma</small>
                  </span>
                  <span
                    v-if="!day.isPast && day.availability"
                    class="public-day-availability"
                    :class="{ empty: day.isClosed || day.isSoldOut }"
                  >
                    {{ day.isClosed ? 'Zárva' : (day.isSoldOut ? 'Nincs szabad időpont' : day.availability.available_count + ' szabad') }}
                  </span>
                  <span v-if="day.ownBookings.length" class="public-own-bookings" aria-label="Saját foglalásaid ezen a napon">
                    <span v-for="booking in day.ownBookings.slice(0, 2)" :key="booking.id">
                      {{ booking.start_time }} · {{ booking.service_name }}
                    </span>
                    <small v-if="day.ownBookings.length > 2">+{{ day.ownBookings.length - 2 }} további</small>
                  </span>
                  <i v-if="!day.disabled" aria-hidden="true">Megnyitás →</i>
                </button>
              </div>
              <p class="public-calendar-hint"><span v-if="loadingMonthAvailability" class="spinner"></span>{{ loadingMonthAvailability ? ' Szabad időpontok számolása…' : 'Kattints egy napra, és megnyílik az órás nézet a szabad időpontokkal.' }}</p>
            </div>

            <div v-else key="booking-day" class="public-booking-calendar-stage public-booking-day-stage">
              <div class="public-day-toolbar">
                <div>
                  <p class="eyebrow">Választott nap</p>
                  <h2>{{ publicDateLabel }}</h2>
                </div>

                <span class="public-selected-service">
                  {{ selectedService?.name }} · {{ formatDuration(selectedService?.duration_minutes) }}
                </span>
              </div>

              <div v-if="loadingSlots" class="public-calendar-loading">
                <span class="spinner"></span>
                <span>Szabad időpontok betöltése…</span>
              </div>

              <p v-else-if="!workingHours.length && !ownBookingsForSelectedDate.length" class="empty">Ezen a napon nincs beállított nyitvatartás — válassz másik napot.</p>

              <div v-else class="public-day-timeline">
                <div v-for="hour in publicTimelineHours" :key="hour" class="public-hour-row">
                  <span class="public-hour-label">{{ String(hour).padStart(2, '0') }}:00</span>
                  <div class="public-quarter-grid">
                    <button
                      v-for="cell in quarterCellsForPublicHour(hour)"
                      :key="cell.time"
                      type="button"
                      class="public-quarter-slot"
                      :class="{ available: cell.available, selected: cell.selected }"
                      :disabled="!cell.available"
                      :title="cell.slot ? `${cell.slot.time}–${cell.slot.endTime}` : `${cell.time} — nem elérhető`"
                      @click="pickPublicSlot(cell.slot)"
                    >
                      <template v-if="cell.available">
                        <strong>{{ cell.time }}</strong>
                        <small>{{ cell.slot.endTime }}-ig</small>
                      </template>
                      <span v-else>—</span>
                    </button>
                    <button
                      v-for="booking in ownBookingsForHour(hour)"
                      :key="'own-booking-' + booking.id"
                      type="button"
                      class="public-own-booking-event"
                      :style="ownBookingEventStyle(booking)"
                      :title="booking.service_name + ' — foglalás kezelése'"
                      @click.stop="openOwnBooking(booking)"
                    >
                      <strong>{{ booking.service_name }}</strong>
                      <small>{{ booking.start_time }}–{{ booking.end_time }} · Kezelés</small>
                    </button>
                  </div>
                </div>
              </div>

              <p v-if="!loadingSlots && workingHours.length && !slots.length && !ownBookingsForSelectedDate.length" class="empty public-day-empty">Erre a napra nincs szabad időpont — lépj vissza, és válassz másik napot.</p>
            </div>
          </transition>

          <div class="button-row">
            <!-- Havi nézetben vissza a szolgáltatásokhoz -->
            <button
              v-if="bookingCalendarMode === 'month'"
              class="button"
              type="button"
              @click="goToStep(1)"
            >
              Vissza a szolgáltatásokhoz
            </button>

            <!-- Napi nézetben vissza a havi naptárhoz -->
            <button
              v-else
              class="button"
              type="button"
              @click="backToBookingMonth"
            >
              ← Vissza a havi naptárhoz
            </button>

            <button
              class="button primary"
              type="button"
              :disabled="!selectedSlot"
              @click="goToStep(3)"
            >
              Tovább az adataidra
            </button>

          </div>
        </template>

        <template v-else-if="step === 3">
          <h2 style="margin-top:6px;">Add meg az adataidat</h2>
          <p class="lead">Ezekre az elérhetőségekre küldjük a visszaigazolást, illetve ezen tudunk elérni, ha bármi változna.</p>
          <div class="booking-account-prompt" :class="{signed: customerAccount.id}">
            <template v-if="customerAccount.id"><div><strong>✓ Bejelentkezve: {{ customerAccount.name }}</strong><small>Az adataidat automatikusan kitöltöttük.</small></div><a href="<?= route_url('account') ?>">Fiókom</a></template>
            <template v-else><div><strong>Gyorsabb foglalást szeretnél?</strong><small>Jelentkezz be vagy regisztrálj, és visszahozunk ehhez a foglaláshoz a kitöltött adataiddal.</small></div><a class="button sm" href="<?= route_url('account') ?>?return=booking" @click.prevent="goToCustomerAccount($event)">Bejelentkezés / Regisztráció</a></template>
          </div>
          <form class="booking-form" @submit.prevent="saveBooking">
            <label class="full">Teljes név
              <input v-model.trim="form.customer_name" type="text" required minlength="2" maxlength="120" autocomplete="name" placeholder="Kovács Anna" />
              <small v-if="nameError" class="field-error">{{ nameError }}</small>
            </label>
            <label class="full">E-mail cím
              <input v-model.trim="form.customer_contact" type="email" required maxlength="160" autocomplete="email" placeholder="anna@example.com" />
              <small v-if="emailError" class="field-error">{{ emailError }}</small>
            </label>
            <label class="full">Telefonszám <small>(nem kötelező)</small>
              <input v-model.trim="form.customer_phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+36 30 123 4567" />
            </label>
            <label class="full">Megjegyzés (nem kötelező)
              <textarea v-model.trim="form.customer_note" minlength="3" maxlength="800" placeholder="Bármi, amit érdemes tudnunk a foglalás előtt."></textarea>
              <small v-if="noteError" class="field-error">{{ noteError }}</small>
            </label>
            <label class="full legal-acceptance">
              <input v-model="form.legal_accepted" type="checkbox" required />
              <span>
                Elolvastam és elfogadom a
                <a href="<?= route_url('terms') ?>" @click.prevent="openLegalModal('Felhasználási és foglalási feltételek', business.legal?.termsText, '<?= route_url('terms') ?>', $event)">Felhasználási és foglalási feltételeket</a>,
                valamint tudomásul vettem az
                <a href="<?= route_url('privacy') ?>" @click.prevent="openLegalModal('Adatkezelési tájékoztató', business.legal?.privacyPolicy, '<?= route_url('privacy') ?>', $event)">Adatkezelési tájékoztatóban</a>
                foglaltakat.
              </span>
            </label>
          </form>

          <div class="button-row">
            <button class="button" type="button" @click="goToStep(2)">Vissza</button>
            <button class="button primary" type="button" :disabled="submitting || !formValid" @click="saveBooking">
              <span v-if="submitting" class="spinner"></span>{{ submitting ? 'Foglalás mentése…' : 'Foglalás véglegesítése' }}
            </button>
          </div>
        </template>
      </section>

      <aside class="side-panel">
        <div class="ticket">
          <div class="stub-head"><h2>Összegzés</h2></div>
          <dl>
            <div><dt>Szolgáltatás</dt><dd>{{ selectedService?.name || '—' }}</dd></div>
            <div v-if="selectedService"><dt>Időtartam</dt><dd class="mono">{{ formatDuration(selectedService.duration_minutes) }}</dd></div>
            <div v-if="priceLabel(selectedService)"><dt>Ár</dt><dd class="mono">{{ priceLabel(selectedService) }}</dd></div>
          </dl>
          <div class="perforation"></div>
          <dl>
            <div><dt>Dátum</dt><dd>{{ date ? formatDateLong(date) : '—' }}</dd></div>
            <div><dt>Időpont</dt><dd class="big-time">{{ selectedSlot?.label || '—' }}</dd></div>
          </dl>
        </div>
      </aside>
    </main>

    <main v-else id="main-content" class="shell confirm-wrap" tabindex="-1">
      <section class="panel confirm-hero">
        <div class="check-mark"><svg viewBox="0 0 24 24" width="26" height="26" fill="none"><path d="M4 12.5L9.5 18L20 6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <p class="eyebrow">Foglalás rögzítve</p>
        <h1>Minden készen áll, {{ confirmedBooking?.customer_name?.split(' ')[0] || '' }}!</h1>
        <p class="lead">Elmentettük az időpontot. Ha később bármi közbejönne, a lenti linken bármikor módosíthatod vagy lemondhatod.</p>
        <div class="button-row">
          <a class="button primary" :href="manageUrl">Foglalás kezelése</a>
          <button class="button" type="button" @click="addToGoogleCalendar">Google Naptár</button>
          <button class="button" type="button" @click="addToDeviceCalendar">Apple / Outlook Naptár</button>
          <button class="button" type="button" @click="startOver">Új foglalás</button>
        </div>
        <div class="notice confirm-manage-notice">Mentsd el ezt a linket, erre lesz szükséged a foglalás módosításához: <a class="confirm-manage-link" :href="manageUrl">{{ manageUrl }}</a></div>
      </section>

      <aside class="side-panel">
        <div class="ticket">
          <div class="stub-head"><h2>Jegy</h2><span class="badge booked">Foglalva</span></div>
          <dl><div><dt>Szolgáltatás</dt><dd>{{ confirmedBooking?.service_name }}</dd></div><div><dt>Vendég</dt><dd>{{ confirmedBooking?.customer_name }}</dd></div></dl>
          <div class="perforation"></div>
          <dl><div><dt>Dátum</dt><dd>{{ formatDateLong(confirmedBooking?.date) }}</dd></div><div><dt>Időpont</dt><dd class="big-time">{{ confirmedBooking?.start_time }}</dd></div></dl>
        </div>
      </aside>
    </main>

    <template v-if="step < 4">
      <section id="rolunk" class="shell trust-section about-section">
        <div class="section-heading">
          <div><p class="eyebrow">Bemutatkozás</p><h2>{{ business.aboutTitle || 'Rólunk' }}</h2></div>
          <p>{{ business.aboutText || 'Itt mutathatod be röviden a vállalkozásodat, a tapasztalatodat és azt, miben számíthatnak rád az ügyfelek.' }}</p>
        </div>
      </section>

      <section class="shell trust-section reviews-section">
        <div class="section-heading compact-heading reviews-heading">
          <div><p class="eyebrow">Visszajelzések</p><h2>Mit mondanak rólunk?</h2></div>
        </div>

        <button
          ref="publicReviewOpenButton"
          class="review-disclosure-button"
          :class="{ open: reviewFormOpen || reviewSubmitted }"
          type="button"
          aria-controls="public-review-form-section"
          :aria-expanded="(reviewFormOpen || reviewSubmitted) ? 'true' : 'false'"
          @click="togglePublicReviewForm"
        >
          <span>Vélemény írása</span>
          <span class="review-disclosure-icon" aria-hidden="true"></span>
        </button>

        <transition name="review-form-reveal">
          <div
            v-if="reviewFormOpen || reviewSubmitted"
            id="public-review-form-section"
            ref="publicReviewSection"
            class="review-dropdown-panel"
          >
            <div class="review-dropdown-inner">
              <div class="public-review-card">
                <div class="public-review-intro">
                  <p class="eyebrow">Saját tapasztalat</p>
                  <h2>Írd meg a véleményed</h2>
                  <p>Mondd el, hogyan érezted magad. A beküldött vélemény jóváhagyás után jelenhet meg a weboldalon; az e-mail címed soha nem lesz nyilvános.</p>
                </div>

                <div v-if="reviewSubmitted" class="public-review-success" role="status">
                  <span aria-hidden="true">✓</span>
                  <div><strong>Köszönjük a visszajelzést!</strong><p>Megkaptuk a véleményedet, jóváhagyás után jelenhet meg.</p></div>
                  <button class="button sm" type="button" @click="openPublicReviewForm">Újabb vélemény írása</button>
                </div>

                <form v-else class="public-review-form" novalidate @submit.prevent="submitPublicReview">
                  <div class="public-review-fields">
                    <label>Név
                      <input ref="publicReviewAuthorInput" v-model.trim="reviewForm.author" type="text" required maxlength="120" autocomplete="name" :aria-invalid="reviewNameError ? 'true' : 'false'" />
                      <small v-if="reviewNameError" class="field-error" role="alert">{{ reviewNameError }}</small>
                    </label>
                    <label>E-mail cím <span class="field-hint">nem nyilvános</span>
                      <input v-model.trim="reviewForm.email" type="email" required maxlength="160" autocomplete="email" :aria-invalid="reviewEmailError ? 'true' : 'false'" />
                      <small v-if="reviewEmailError" class="field-error" role="alert">{{ reviewEmailError }}</small>
                    </label>
                    <fieldset class="star-rating-field">
                      <legend>Értékelés</legend>
                      <div class="star-rating" role="radiogroup" aria-label="Értékelés csillagokkal">
                        <label v-for="star in 5" :key="star" :class="{ selected: star <= reviewForm.rating }">
                          <input v-model.number="reviewForm.rating" class="sr-only" type="radio" name="public-review-rating" :value="star" required />
                          <span aria-hidden="true">★</span>
                          <span class="sr-only">{{ star }} csillag</span>
                        </label>
                      </div>
                      <small class="star-rating-value">{{ reviewForm.rating }} / 5 csillag</small>
                    </fieldset>
                    <label class="public-review-text">Vélemény
                      <textarea v-model.trim="reviewForm.text" rows="5" required minlength="10" maxlength="1200" placeholder="Legalább 10 karakterben írd le a tapasztalatodat." :aria-invalid="reviewTextError ? 'true' : 'false'"></textarea>
                      <span class="review-character-count">{{ reviewForm.text.length }} / 1200</span>
                      <small v-if="reviewTextError" class="field-error" role="alert">{{ reviewTextError }}</small>
                    </label>
                  </div>

                  <label class="public-review-legal checkline">
                    <input v-model="reviewForm.legal_accepted" type="checkbox" required />
                    <span>Elolvastam és elfogadom az
                      <a href="<?= route_url('terms') ?>" @click.prevent="openLegalModal('Felhasználási és foglalási feltételek', business.legal?.termsText, '<?= route_url('terms') ?>', $event)">Felhasználási feltételeket</a>,
                      valamint tudomásul vettem az
                      <a href="<?= route_url('privacy') ?>" @click.prevent="openLegalModal('Adatkezelési tájékoztató', business.legal?.privacyPolicy, '<?= route_url('privacy') ?>', $event)">Adatkezelési tájékoztatót</a>.
                    </span>
                  </label>
                  <label class="sr-only" aria-hidden="true">Weboldal
                    <input v-model="reviewForm.website" type="text" tabindex="-1" autocomplete="off" />
                  </label>

                  <button class="button primary public-review-submit" type="submit" :disabled="!reviewFormValid || reviewSubmitting">
                    <span v-if="reviewSubmitting" class="spinner"></span>{{ reviewSubmitting ? 'Küldés…' : 'Vélemény elküldése' }}
                  </button>
                </form>
              </div>
            </div>
          </div>
        </transition>

        <div v-if="business.reviews?.length" class="review-grid">
          <article v-for="review in business.reviews" :key="review.id" class="review-card">
            <div class="stars" :aria-label="`${review.rating} csillag az 5-ből`">{{ '★'.repeat(review.rating) }}<span>{{ '★'.repeat(5 - review.rating) }}</span></div>
            <blockquote>„{{ review.text }}”</blockquote>
            <strong>{{ review.author }}</strong>
          </article>
        </div>
        <p v-else class="review-empty-state">Még nincs megjelenített vélemény. Légy te az első!</p>
      </section>

      <section v-if="business.faqs?.length" class="shell trust-section faq-section">
        <div class="section-heading compact-heading"><div><p class="eyebrow">GYIK</p><h2>Gyakori kérdések</h2></div></div>
        <div class="faq-list">
          <details v-for="faq in business.faqs" :key="faq.id" class="faq-item">
            <summary>{{ faq.question }}<span>+</span></summary>
            <p>{{ faq.answer }}</p>
          </details>
        </div>
      </section>

      <section id="kapcsolat" class="shell trust-section contact-section">
        <div class="contact-card">
          <div class="contact-intro">
            <p class="eyebrow">Kapcsolat</p>
            <h2>Keress minket bizalommal</h2>
            <p>Foglalás előtt kérdésed van? Az alábbi elérhetőségeken megtalálsz minket.</p>
          </div>
          <div class="contact-grid">
            <div v-if="business.phone" class="contact-item"><span>Telefon</span><a :href="phoneHref">{{ business.phone }}</a></div>
            <div v-if="business.email" class="contact-item"><span>E-mail</span><a :href="emailHref">{{ business.email }}</a></div>
            <div v-if="business.address" class="contact-item"><span>Cím</span><strong>{{ business.address }}</strong></div>
            <div v-if="business.openingHours" class="contact-item"><span>Nyitvatartás</span><strong class="preserve-lines">{{ business.openingHours }}</strong></div>
          </div>
          <div class="contact-actions">
            <a v-if="business.googleMapsUrl" class="button primary" :href="business.googleMapsUrl" target="_blank" rel="noopener">Megnyitás Google Mapsen</a>
            <a class="button" href="#foglalas">Időpontot foglalok</a>
          </div>
        </div>
      </section>
    </template>

    <footer class="site-footer">
      <div class="shell footer-inner">
        <div class="footer-brand">
          <span class="brand-mark business-logo-mark">
            <img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" loading="lazy" decoding="async" />
            <template v-else>{{ business.logoText || monogram(business.name) || 'IP' }}</template>
          </span>
          <div><strong>{{ business.name || 'Időpontfoglalás' }}</strong><small>{{ business.tagline || 'Online időpontfoglalás' }}</small></div>
        </div>
        <div class="footer-links">
          <a href="#foglalas">Foglalás</a>
          <a href="#rolunk">Rólunk</a>
          <a href="#kapcsolat">Kapcsolat</a>
          <a href="<?= route_url('privacy') ?>">Adatkezelés</a>
          <a href="<?= route_url('terms') ?>">Felhasználási feltételek</a>
          <a href="<?= route_url('imprint') ?>">Impresszum</a>
          <a href="<?= route_url('cookies') ?>">Süti tájékoztató</a>
          <a href="<?= route_url('account') ?>">Fiókom</a>
        </div>
        <p class="footer-copy">© {{ currentYear }} {{ business.name || 'Időpontfoglalás' }}. Minden jog fenntartva.</p>
      </div>
    </footer>

    <transition name="legal-modal-pop">
      <div v-if="legalModal.open" class="legal-modal-backdrop" @click.self="closeLegalModal">
        <section
          ref="legalModalDialog"
          class="legal-modal-dialog"
          tabindex="-1"
          role="dialog"
          aria-modal="true"
          aria-labelledby="legalModalTitle"
        >
          <header class="legal-modal-head">
            <div>
              <p class="eyebrow">Jogi információ</p>
              <h2 id="legalModalTitle">{{ legalModal.title }}</h2>
            </div>
            <button class="legal-modal-close" type="button" aria-label="Bezárás" @click="closeLegalModal">×</button>
          </header>

          <div v-if="legalModal.content" class="legal-modal-content" v-html="legalModal.content"></div>
          <div v-else class="notice legal-modal-empty">Ez a dokumentum még nincs kitöltve.</div>

          <footer class="legal-modal-actions">
            <a class="button" :href="legalModal.url" target="_blank" rel="noopener">Megnyitás külön oldalon</a>
            <button class="button primary" type="button" @click="closeLegalModal">Rendben</button>
          </footer>
        </section>
      </div>
    </transition>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
