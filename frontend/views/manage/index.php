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

    <main class="shell narrow">
      <p v-if="!token" class="empty">Hiányzik a kezelő link a foglalásodhoz. Kérjük, használd a visszaigazolásban kapott linket.</p>

      <template v-else>
        <div v-if="loading" class="panel">
          <div class="skeleton" style="height:220px;"></div>
        </div>

        <template v-else-if="booking">
          <div class="ticket">
            <div class="stub-head">
              <div>
                <p class="eyebrow">Kezelő link</p>
                <h1 style="font-size:26px;">{{ booking.service_name }}</h1>
              </div>
              <span class="badge" :class="booking.status">{{ statusLabel(booking.status) }}</span>
            </div>
            <dl>
              <div><dt>Vendég</dt><dd>{{ booking.customer_name }}</dd></div>
              <div><dt>Elérhetőség</dt><dd class="mono">{{ booking.customer_contact }}</dd></div>
              <div v-if="booking.customer_note"><dt>Megjegyzés</dt><dd>{{ booking.customer_note }}</dd></div>
            </dl>
            <div class="perforation"></div>
            <dl>
              <div><dt>Dátum</dt><dd>{{ formatDateLong(booking.date) }}</dd></div>
              <div><dt>Időpont</dt><dd class="big-time">{{ booking.start_time?.slice(0,5) }}–{{ booking.end_time?.slice(0,5) }}</dd></div>
            </dl>
          </div>

          <section v-if="isActive" class="panel" style="margin-top:20px;">
            <h2 style="font-size:17px;">Időpont módosítása</h2>
            <p class="lead" style="font-size:13.5px;margin-top:4px;">Válassz új dátumot és időpontot — a régi helyed automatikusan felszabadul.</p>

            <div class="date-strip">
              <button
                v-for="opt in dateOptions"
                :key="opt.key"
                class="date-pill"
                :class="{ selected: newDate === opt.key, today: isToday(opt.key) }"
                type="button"
                @click="pickDate(opt.key)"
              >
                <span class="dow">{{ opt.dow }}</span>
                <span class="dnum">{{ opt.day }}</span>
              </button>
            </div>

            <div v-if="loadingSlots" class="slot-grid" style="margin-top:14px;">
              <div class="skeleton" style="height:44px;" v-for="n in 6" :key="n"></div>
            </div>
            <p v-else-if="!slots.length" class="empty">Erre a napra nincs szabad időpont.</p>
            <template v-else>
              <div v-for="[period, items] in groupedSlots" :key="period" class="slot-period">
                <h4>{{ period }}</h4>
                <div class="slot-grid">
                  <button
                    v-for="slot in items"
                    :key="slot.time"
                    class="slot"
                    :class="{ selected: newTime === slot.time }"
                    type="button"
                    @click="newTime = slot.time"
                  >{{ slot.label }}</button>
                </div>
              </div>
            </template>

            <div class="button-row">
              <button class="button primary" type="button" :disabled="!newTime || rescheduling" @click="reschedule">
                {{ rescheduling ? 'Mentés…' : 'Módosítás mentése' }}
              </button>
              <button v-if="!confirmingCancel" class="button danger" type="button" @click="confirmingCancel = true">Lemondás</button>
              <template v-else>
                <span class="lead" style="align-self:center;font-size:13.5px;">Biztosan lemondod?</span>
                <button class="button danger" type="button" :disabled="cancelling" @click="cancelBooking">Igen, lemondom</button>
                <button class="button ghost" type="button" @click="confirmingCancel = false">Mégse</button>
              </template>
            </div>
          </section>

          <div v-else class="notice" style="margin-top:20px;">
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
