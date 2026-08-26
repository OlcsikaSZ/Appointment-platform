<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="referrer" content="no-referrer" />
  <title>Foglalás kezelése</title>
  <link id="business-favicon" rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <a class="skip-link" href="#main-content">Ugrás a tartalomhoz</a>
  <div id="manageApp" v-cloak>

    <div class="toast-stack" aria-live="polite" aria-atomic="false">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" :role="toast.kind === 'error' ? 'alert' : 'status'" @click="toasts.dismiss(toast.id)">
        {{ toast.message }}
      </div>
    </div>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark"><img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" /><template v-else>{{ business.logoText || 'IP' }}</template></span>
        <span>
          <strong>{{ business.name || 'Időpontfoglalás' }}</strong>
          <small>Foglalás kezelése</small>
        </span>
      </a>
      <nav><a v-if="fromBooking" href="<?= route_url('main') ?>#foglalas">← Vissza a foglaláshoz</a><a v-else-if="fromAccount" href="<?= route_url('account') ?>">← Vissza a fiókomba</a><a href="<?= route_url('main') ?>" @click="startNewBooking">Új foglalás</a></nav>
    </header>

    <main id="main-content" class="shell narrow manage-shell" tabindex="-1">
      <section v-if="loadState === 'missing'" class="panel manage-state-panel" role="alert">
        <span class="manage-state-icon" aria-hidden="true">?</span>
        <p class="eyebrow">Hiányzó kezelőlink</p>
        <h1>Nem található foglalási token</h1>
        <p class="lead">Nyisd meg újra az e-mailben kapott teljes kezelőlinket, vagy készíts új foglalást.</p>
        <a class="button primary" href="<?= route_url('main') ?>">Új foglalás indítása</a>
      </section>

      <section v-else-if="loadState === 'invalid'" class="panel manage-state-panel" role="alert">
        <span class="manage-state-icon danger" aria-hidden="true">!</span>
        <p class="eyebrow">Érvénytelen link</p>
        <h1>Ez a kezelőlink nem létezik</h1>
        <p class="lead">Ellenőrizd, hogy a teljes linket nyitottad-e meg. Biztonsági okból a token nem látható a címsorban.</p>
        <a class="button primary" href="<?= route_url('main') ?>">Új foglalás indítása</a>
      </section>

      <section v-else-if="loadState === 'expired'" class="panel manage-state-panel" role="alert">
        <span class="manage-state-icon" aria-hidden="true">⌛</span>
        <p class="eyebrow">Lejárt kezelőlink</p>
        <h1>A link érvényessége lejárt</h1>
        <p class="lead">{{ loadErrorMessage || 'A foglalás már nem kezelhető ezzel a linkkel. Vedd fel a kapcsolatot a szolgáltatóval.' }}</p>
        <a class="button primary" href="<?= route_url('main') ?>">Új foglalás indítása</a>
      </section>

      <section v-else-if="loadState === 'error'" class="panel manage-state-panel" role="alert">
        <span class="manage-state-icon danger" aria-hidden="true">!</span>
        <p class="eyebrow">Átmeneti hiba</p>
        <h1>A foglalás most nem tölthető be</h1>
        <p class="lead">{{ loadErrorMessage }}</p>
        <button class="button primary" type="button" @click="loadBooking">Újrapróbálás</button>
      </section>

      <template v-else>
        <div v-if="loading || loadState === 'loading'" class="panel" aria-busy="true" aria-label="Foglalás betöltése">
          <div class="skeleton" style="height:220px;"></div>
        </div>

        <template v-else-if="booking">
          <div class="ticket manage-ticket">
            <div class="stub-head">
              <div>
                <p class="eyebrow">Kezelő link</p>
                <h1 style="font-size:26px;">{{ booking.service_name }}</h1>
              </div>
              <span class="badge" :class="booking.status">{{ statusLabel(booking.status) }}</span>
            </div>
            <dl>
              <div><dt>Vendég</dt><dd>{{ booking.customer_name }}</dd></div>
              <div><dt>E-mail</dt><dd class="mono">{{ booking.customer_contact }}</dd></div>
              <div v-if="booking.customer_note"><dt>Megjegyzés</dt><dd>{{ booking.customer_note }}</dd></div>
            </dl>
            <div class="perforation"></div>
            <dl>
              <div><dt>Dátum</dt><dd>{{ formatDateLong(booking.date) }}</dd></div>
              <div><dt>Időpont</dt><dd class="big-time">{{ booking.start_time?.slice(0,5) }}–{{ booking.end_time?.slice(0,5) }}</dd></div>
            </dl>
            <div v-if="booking.status === 'booked'" class="manage-calendar-buttons">
              <button class="button sm" type="button" @click="addToGoogleCalendar">Google Naptár</button>
              <button class="button sm" type="button" @click="addToDeviceCalendar">Apple / Outlook Naptár</button>
            </div>
          </div>

          <section v-if="isActive" class="panel manage-calendar-panel">
            <div class="manage-section-head">
              <div>
                <p class="eyebrow">Időpont módosítása</p>
                <h2>Válassz új napot és időpontot</h2>
                <p class="lead">A régi helyed csak sikeres módosítás után szabadul fel.</p>
              </div>
            </div>

            <div v-if="!canReschedule" class="notice manage-deadline-notice">
              A módosítási határidő lejárt<template v-if="rescheduleDeadlineLabel"> ({{ rescheduleDeadlineLabel }})</template>. Vedd fel közvetlenül a kapcsolatot a szolgáltatóval.
            </div>

            <transition v-if="canReschedule" name="manage-calendar-view" mode="out-in">
              <div v-if="bookingCalendarMode === 'month'" key="manage-month" class="manage-calendar-stage">
                <div class="manage-calendar-toolbar">
                  <div>
                    <p class="eyebrow">Válassz napot</p>
                    <h2>{{ monthLabel }}</h2>
                  </div>
                  <div class="manage-calendar-actions">
                    <button class="button sm ghost" type="button" :disabled="!canMoveMonthBack" aria-label="Előző hónap" @click="moveMonth(-1)">‹</button>
                    <button class="button sm" type="button" @click="goCurrentMonth">Aktuális hónap</button>
                    <button class="button sm ghost" type="button" aria-label="Következő hónap" @click="moveMonth(1)">›</button>
                  </div>
                </div>

                <div class="manage-month-weekdays" aria-hidden="true">
                  <span>H</span><span>K</span><span>Sze</span><span>Cs</span><span>P</span><span>Szo</span><span>V</span>
                </div>
                <div class="manage-month-grid" role="grid" :aria-label="monthLabel">
                  <button
                    v-for="day in monthDays"
                    :key="day.key"
                    type="button"
                    class="manage-month-day"
                    :class="{
                      'outside-month': !day.inCurrentMonth,
                      today: day.isToday,
                      'current-booking': day.isCurrentBooking,
                      disabled: day.disabled,
                      closed: day.isClosed,
                      'sold-out': day.isSoldOut
                    }"
                    :disabled="day.disabled"
                    @click="openBookingDay(day.key)"
                  >
                    <span class="manage-month-day-head">
                      <span class="manage-month-day-number">{{ day.dayNumber }}</span>
                      <small v-if="day.isToday">Ma</small>
                    </span>
                    <span
                      v-if="!day.isPast && day.availability"
                      class="manage-day-availability"
                      :class="{ empty: day.isClosed || day.isSoldOut }"
                    >
                      {{ day.isClosed ? 'Zárva' : (day.isSoldOut ? 'Nincs szabad időpont' : day.availability.available_count + ' szabad') }}
                    </span>
                    <i v-if="!day.disabled" aria-hidden="true">Megnyitás →</i>
                  </button>
                </div>
                <p class="manage-calendar-hint"><span v-if="loadingMonthAvailability" class="spinner"></span>{{ loadingMonthAvailability ? ' Szabad időpontok számolása…' : 'Kattints egy napra, majd válassz a ténylegesen elérhető idősávok közül.' }}</p>
              </div>

              <div v-else key="manage-day" class="manage-calendar-stage manage-day-stage">
                <div class="manage-day-toolbar">
                  <div>
                    <p class="eyebrow">Választott nap</p>
                    <h2>{{ selectedDateLabel }}</h2>
                  </div>
                  <span class="manage-selected-service">{{ booking.service_name }}</span>
                </div>

                <div v-if="loadingSlots" class="manage-calendar-loading">
                  <span class="spinner"></span>
                  <span>Szabad időpontok betöltése…</span>
                </div>

                <p v-else-if="!workingHours.length" class="empty">Ezen a napon nincs beállított nyitvatartás — válassz másik napot.</p>

                <div v-else class="manage-day-timeline">
                  <div v-for="hour in timelineHours" :key="hour" class="manage-hour-row">
                    <span class="manage-hour-label">{{ String(hour).padStart(2, '0') }}:00</span>
                    <div class="manage-quarter-grid">
                      <button
                        v-for="cell in quarterCellsForHour(hour)"
                        :key="cell.time"
                        type="button"
                        class="manage-quarter-slot"
                        :class="{ available: cell.available, selected: cell.selected, current: cell.current }"
                        :disabled="!cell.available"
                        :title="cell.slot ? `${cell.slot.time}–${cell.slot.endTime}` : `${cell.time} — nem elérhető`"
                        @click="pickSlot(cell.slot)"
                      >
                        <template v-if="cell.available">
                          <strong>{{ cell.time }}</strong>
                          <small>{{ cell.current ? 'Jelenlegi időpont' : cell.slot.endTime + '-ig' }}</small>
                        </template>
                        <span v-else>—</span>
                      </button>
                    </div>
                  </div>
                </div>

                <p v-if="!loadingSlots && workingHours.length && !slots.length" class="empty manage-day-empty">Erre a napra nincs szabad időpont — lépj vissza, és válassz másik napot.</p>
              </div>
            </transition>

            <div class="button-row manage-actions-row">
              <button v-if="bookingCalendarMode === 'day'" class="button" type="button" @click="backToMonth">← Vissza a havi naptárhoz</button>
              <button v-if="canReschedule && bookingCalendarMode === 'day'" class="button primary" type="button" :disabled="!scheduleChanged || rescheduling" @click="reschedule">
                {{ rescheduling ? 'Mentés…' : 'Módosítás mentése' }}
              </button>
              <button v-if="canCancel && !confirmingCancel" class="button danger" type="button" @click="confirmingCancel = true">Foglalás lemondása</button>
              <span v-else-if="!canCancel" class="notice inline-deadline-notice">
                A lemondási határidő lejárt<template v-if="cancelDeadlineLabel"> ({{ cancelDeadlineLabel }})</template>.
              </span>
              <template v-else>
                <span class="lead cancel-question">Biztosan lemondod?</span>
                <button class="button danger" type="button" :disabled="cancelling" @click="cancelBooking">Igen, lemondom</button>
                <button class="button ghost" type="button" @click="confirmingCancel = false">Mégse</button>
              </template>
            </div>
          </section>

          <section v-else-if="booking.status === 'cancelled'" class="panel manage-closed-state" role="status">
            <span class="manage-state-icon danger" aria-hidden="true">×</span>
            <p class="eyebrow">Lezárt foglalás</p>
            <h2>A foglalást lemondtad</h2>
            <p class="lead">Ez az időpont már nem módosítható. Ha mégis szeretnél jönni, indíts egy új foglalást.</p>
            <a class="button primary" href="<?= route_url('main') ?>">Új foglalás indítása</a>
          </section>

          <section v-else class="panel manage-closed-state" role="status">
            <span class="manage-state-icon success" aria-hidden="true">✓</span>
            <p class="eyebrow">Lezárt foglalás</p>
            <h2>{{ booking.status === 'completed' ? 'A szolgáltatás teljesítve' : 'A foglalás lezárult' }}</h2>
            <p class="lead">Ez a foglalás már nem módosítható.</p>
            <a class="button primary" href="<?= route_url('main') ?>">Új foglalás indítása</a>
          </section>
        </template>
      </template>
    </main>

    <footer class="manage-legal-footer">
      <button type="button" @click="openLegalModal('Adatkezelési tájékoztató', legalDocuments.privacyPolicy, $event)">Adatkezelés</button>
      <button type="button" @click="openLegalModal('Felhasználási és foglalási feltételek', legalDocuments.termsText, $event)">Felhasználási feltételek</button>
      <button type="button" @click="openLegalModal('Impresszum', legalDocuments.imprintText, $event)">Impresszum</button>
      <button type="button" @click="openLegalModal('Süti tájékoztató', legalDocuments.cookiePolicy, $event)">Süti tájékoztató</button>
    </footer>

    <transition name="legal-modal-pop">
      <div v-if="legalModal.open" class="manage-legal-modal-backdrop" @click.self="closeLegalModal">
        <section
          ref="legalModalDialog"
          class="manage-legal-modal-dialog"
          tabindex="-1"
          role="dialog"
          aria-modal="true"
          aria-labelledby="manageLegalModalTitle"
        >
          <header class="manage-legal-modal-head">
            <div>
              <p class="eyebrow">Jogi információ</p>
              <h2 id="manageLegalModalTitle">{{ legalModal.title }}</h2>
            </div>
            <button class="manage-legal-modal-close" type="button" aria-label="Bezárás" @click="closeLegalModal">×</button>
          </header>

          <div v-if="legalModal.content" class="manage-legal-modal-content" v-html="legalModal.content"></div>
          <div v-else class="notice">Ez a dokumentum még nincs kitöltve.</div>

          <footer class="manage-legal-modal-actions">
            <button class="button primary" type="button" @click="closeLegalModal">Vissza a foglalásomhoz</button>
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
