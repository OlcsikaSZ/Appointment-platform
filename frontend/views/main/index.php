<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Időpontfoglalás</title>
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <div id="bookingApp" v-cloak>

    <div class="toast-stack">
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" @click="toasts.dismiss(toast.id)">
        {{ toast.message }}
      </div>
    </div>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark">{{ business.logoText || '·' }}</span>
        <span>
          <strong>{{ business.name || 'Időpontfoglalás' }}</strong>
          <small>{{ business.tagline || 'Foglalj pár kattintással' }}</small>
        </span>
      </a>
      <nav>
        <a href="<?= route_url('admin') ?>">Admin belépés</a>
      </nav>
    </header>

    <!-- STEP 1-3: WIZARD -->
    <main v-if="step < 4" class="shell booking-grid">
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

        <!-- Loading skeleton for initial load -->
        <div v-if="loadingInit" class="service-grid">
          <div class="skeleton" style="height:150px;border-radius:12px;" v-for="n in 4" :key="n"></div>
        </div>

        <!-- STEP 1: SERVICE -->
        <template v-else-if="step === 1">
          <div v-if="categories.length > 1" class="chip-row">
            <button
              class="chip"
              :class="{ selected: selectedCategory === 'all' }"
              type="button"
              @click="selectedCategory = 'all'"
            >Összes</button>
            <button
              v-for="cat in categories"
              :key="cat"
              class="chip"
              :class="{ selected: selectedCategory === cat }"
              type="button"
              @click="selectedCategory = cat"
            >{{ cat }}</button>
          </div>

          <p v-if="!services.length" class="empty">Jelenleg nincs elérhető szolgáltatás. Nézz vissza később.</p>

          <div v-else class="service-grid">
            <button
              v-for="item in filteredServices"
              :key="item.id"
              class="service-card"
              :class="{ selected: selectedService && selectedService.id === item.id }"
              type="button"
              @click="selectService(item)"
            >
              <span class="check">
                <svg viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.2 12L13 4" stroke="#1c2541" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
              <span class="cat">{{ item.category }}</span>
              <span class="name">{{ item.name }}</span>
              <span class="desc">{{ item.description || '' }}</span>
              <span class="meta">
                <span>{{ formatDuration(item.duration_minutes) }}</span>
                <span class="price">{{ item.price_cents ? formatPrice(item.price_cents) : '' }}</span>
              </span>
            </button>
          </div>

          <div class="button-row">
            <button class="button primary" type="button" :disabled="!selectedService" @click="goToStep(2)">
              Tovább az időpontra
            </button>
          </div>
        </template>

        <!-- STEP 2: DATE & TIME -->
        <template v-else-if="step === 2">
          <h2 style="margin-top:6px;">Válassz napot</h2>
          <div class="date-strip">
            <button
              v-for="opt in dateOptions"
              :key="opt.key"
              class="date-pill"
              :class="{ selected: date === opt.key, today: isToday(opt.key) }"
              type="button"
              @click="pickDate(opt.key)"
            >
              <span class="dow">{{ opt.dow }}</span>
              <span class="dnum">{{ opt.day }}</span>
            </button>
          </div>
          <details class="date-custom">
            <summary>Másik dátumot választanék</summary>
            <input v-model="date" :min="today" type="date" @change="loadSlots" />
          </details>

          <h2 style="margin-top:26px;">Válassz időpontot</h2>
          <div v-if="loadingSlots" class="slot-grid" style="margin-top:14px;">
            <div class="skeleton" style="height:44px;" v-for="n in 8" :key="n"></div>
          </div>
          <p v-else-if="!slots.length" class="empty">Erre a napra nincs szabad időpont — próbálj másik dátumot.</p>
          <template v-else>
            <div v-for="[period, items] in groupedSlots" :key="period" class="slot-period">
              <h4>{{ period }}</h4>
              <div class="slot-grid">
                <button
                  v-for="slot in items"
                  :key="slot.time"
                  class="slot"
                  :class="{ selected: selectedSlot && selectedSlot.time === slot.time }"
                  type="button"
                  @click="selectedSlot = slot"
                >{{ slot.label }}</button>
              </div>
            </div>
          </template>

          <div class="button-row">
            <button class="button" type="button" @click="goToStep(1)">Vissza</button>
            <button class="button primary" type="button" :disabled="!selectedSlot" @click="goToStep(3)">
              Tovább az adataidra
            </button>
          </div>
        </template>

        <!-- STEP 3: DETAILS -->
        <template v-else-if="step === 3">
          <h2 style="margin-top:6px;">Add meg az adataidat</h2>
          <p class="lead">Ezekre az elérhetőségekre küldjük a visszaigazolást, illetve ezen tudunk elérni, ha bármi változna.</p>
          <form class="booking-form" @submit.prevent="saveBooking">
            <label class="full">
              Teljes név
              <input v-model.trim="form.customer_name" required placeholder="Kovács Anna" />
            </label>
            <label class="full">
              Telefonszám vagy e-mail
              <input v-model.trim="form.customer_contact" required placeholder="+36 30 123 4567" />
            </label>
            <label class="full">
              Megjegyzés (nem kötelező)
              <textarea v-model.trim="form.customer_note" placeholder="Bármi, amit érdemes tudnunk a foglalás előtt."></textarea>
            </label>
          </form>

          <div class="button-row">
            <button class="button" type="button" @click="goToStep(2)">Vissza</button>
            <button class="button primary" type="button" :disabled="submitting || !formValid" @click="saveBooking">
              <span v-if="submitting" class="spinner"></span>
              {{ submitting ? 'Foglalás mentése…' : 'Foglalás véglegesítése' }}
            </button>
          </div>
        </template>
      </section>

      <aside class="side-panel">
        <div class="ticket">
          <div class="stub-head">
            <h2>Összegzés</h2>
          </div>
          <dl>
            <div><dt>Szolgáltatás</dt><dd>{{ selectedService?.name || '—' }}</dd></div>
            <div v-if="selectedService"><dt>Időtartam</dt><dd class="mono">{{ formatDuration(selectedService.duration_minutes) }}</dd></div>
            <div v-if="selectedService?.price_cents"><dt>Ár</dt><dd class="mono">{{ formatPrice(selectedService.price_cents) }}</dd></div>
          </dl>
          <div class="perforation"></div>
          <dl>
            <div><dt>Dátum</dt><dd>{{ date ? formatDateLong(date) : '—' }}</dd></div>
            <div><dt>Időpont</dt><dd class="big-time">{{ selectedSlot?.label || '—' }}</dd></div>
          </dl>
        </div>
      </aside>
    </main>

    <!-- STEP 4: CONFIRMATION -->
    <main v-else class="shell confirm-wrap">
      <section class="panel confirm-hero">
        <div class="check-mark">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none"><path d="M4 12.5L9.5 18L20 6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <p class="eyebrow">Foglalás rögzítve</p>
        <h1>Minden készen áll, {{ confirmedBooking?.customer_name?.split(' ')[0] || '' }}!</h1>
        <p class="lead">Elmentettük az időpontot. Ha később bármi közbejönne, a lenti linken bármikor módosíthatod vagy lemondhatod.</p>

        <div class="button-row">
          <a class="button primary" :href="manageUrl">Foglalás kezelése</a>
          <button class="button" type="button" @click="addToCalendar">Naptárba mentés (.ics)</button>
          <button class="button ghost" type="button" @click="startOver">Új foglalás</button>
        </div>

        <div class="notice" style="margin-top:28px;">
          Mentsd el ezt a linket, erre lesz szükséged a foglalás módosításához:
          <a :href="manageUrl">{{ manageUrl }}</a>
        </div>
      </section>

      <aside class="side-panel">
        <div class="ticket">
          <div class="stub-head">
            <h2>Jegy</h2>
            <span class="badge booked">Foglalva</span>
          </div>
          <dl>
            <div><dt>Szolgáltatás</dt><dd>{{ confirmedBooking?.service_name }}</dd></div>
            <div><dt>Vendég</dt><dd>{{ confirmedBooking?.customer_name }}</dd></div>
          </dl>
          <div class="perforation"></div>
          <dl>
            <div><dt>Dátum</dt><dd>{{ formatDateLong(confirmedBooking?.date) }}</dd></div>
            <div><dt>Időpont</dt><dd class="big-time">{{ confirmedBooking?.start_time }}</dd></div>
          </dl>
        </div>
      </aside>
    </main>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
