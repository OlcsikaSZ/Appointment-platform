<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Foglalás kezelése</title>
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <div id="manageApp" v-cloak>

    <div class="toast-stack">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" @click="toasts.dismiss(toast.id)">
        {{ toast.message }}
      </div>
    </div>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark">·</span>
        <span>
          <strong>Időpontfoglalás</strong>
          <small>Foglalás kezelése</small>
        </span>
      </a>
      <nav><a href="<?= route_url('main') ?>">Új foglalás</a></nav>
    </header>

    <main class="shell narrow manage-shell">
      <p v-if="!token" class="empty">Hiányzik a kezelő link a foglalásodhoz. Kérjük, használd a visszaigazolásban kapott linket.</p>

      <template v-else>
        <div v-if="loading" class="panel">
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
          </div>

          <section v-if="isActive" class="panel manage-calendar-panel">
            <div class="manage-section-head">
              <div>
                <p class="eyebrow">Időpont módosítása</p>
                <h2>Válassz új napot és időpontot</h2>
                <p class="lead">A régi helyed csak sikeres módosítás után szabadul fel.</p>
              </div>
            </div>

            <transition name="manage-calendar-view" mode="out-in">
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
                    :class="{ 'outside-month': !day.inCurrentMonth, today: day.isToday, 'current-booking': day.isCurrentBooking, disabled: day.disabled }"
                    :disabled="day.disabled"
                    @click="openBookingDay(day.key)"
                  >
                    <span class="manage-month-day-number">{{ day.dayNumber }}</span>
                    <small v-if="day.isToday">Ma</small>
                    <i v-if="!day.disabled" aria-hidden="true">Megnyitás →</i>
                  </button>
                </div>
                <p class="manage-calendar-hint">Kattints egy napra, majd válassz a szolgáltatásodhoz ténylegesen elérhető idősávok közül.</p>
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
              <button v-if="bookingCalendarMode === 'day'" class="button primary" type="button" :disabled="!scheduleChanged || rescheduling" @click="reschedule">
                {{ rescheduling ? 'Mentés…' : 'Módosítás mentése' }}
              </button>
              <button v-if="!confirmingCancel" class="button danger" type="button" @click="confirmingCancel = true">Foglalás lemondása</button>
              <template v-else>
                <span class="lead cancel-question">Biztosan lemondod?</span>
                <button class="button danger" type="button" :disabled="cancelling" @click="cancelBooking">Igen, lemondom</button>
                <button class="button ghost" type="button" @click="confirmingCancel = false">Mégse</button>
              </template>
            </div>
          </section>

          <div v-else class="notice manage-inactive-notice">
            Ez a foglalás már nem aktív, ezért nem módosítható.
          </div>
        </template>
      </template>
    </main>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
