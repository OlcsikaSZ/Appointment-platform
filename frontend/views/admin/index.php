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
        <button :class="{active: activeTab === 'services'}" @click="activeTab = 'services'">Szolgáltatások</button>
        <button :class="{active: activeTab === 'website'}" @click="openWebsiteTab">Weboldal</button>
        <button :class="{active: activeTab === 'email'}" @click="openEmailTab">E-mailek</button>
      </div>

      <section v-if="activeTab === 'calendar'" class="admin-single-column">
        <div class="panel today-panel">
          <div class="section-title"><div><p class="eyebrow">Mai foglalások</p><h2>Ki jön ma?</h2></div></div>
          <div v-if="!todayBookings.length" class="empty compact">Ma nincs aktív foglalás. Ritka nyugi, becsüld meg.</div>
          <div v-else class="today-list">
            <article v-for="item in todayBookings" :key="item.id" class="today-item clickable" :class="item.status" @click="openBookingModal(item)">
              <strong>{{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }}</strong>
              <span>{{ item.customer_name }} · {{ item.service_name }}</span>
              <small>{{ item.customer_contact }}<template v-if="item.customer_note"> · {{ item.customer_note }}</template></small>
              <div class="inline-actions" v-if="item.status === 'booked'">
                <button class="button sm" @click.stop="setStatus(item, 'completed')">Teljesítve</button>
                <button class="button sm" @click.stop="setStatus(item, 'no_show')">Nem jött el</button>
                <button class="button sm danger" @click.stop="setStatus(item, 'cancelled')">Lemondva</button>
              </div>
            </article>
          </div>
        </div>

        <div class="panel calendar-panel smart-calendar-panel">
          <div class="calendar-toolbar smart-calendar-toolbar">
            <div>
              <p class="eyebrow">Naptár</p>
              <h2>{{ calendarMode === 'month' ? currentMonthLabel : selectedDayLabel }}</h2>
              <p class="lead calendar-helper">{{ calendarMode === 'month' ? 'Kattints egy napra a részletes órás nézethez.' : 'A foglalások és blokkolások valódi időtartamuk szerint jelennek meg.' }}</p>
            </div>

            <div class="calendar-toolbar-right">
              <div class="booking-search-wrap">
                <input v-model.trim="bookingSearch" class="booking-search" type="search" placeholder="Keresés név / e-mail / megjegyzés" />
                <div v-if="bookingSearchResults.length" class="booking-search-results">
                  <button v-for="item in bookingSearchResults" :key="item.id" type="button" @click="openBookingFromSearch(item)">
                    <strong>{{ item.customer_name }}</strong>
                    <span>{{ item.date }} · {{ shortTime(item.start_time) }} · {{ item.service_name }}</span>
                  </button>
                </div>
              </div>

              <div v-if="calendarMode === 'month'" class="calendar-actions">
                <button class="button sm ghost month-nav" type="button" aria-label="Előző hónap" @click="moveCalendar(-1)">‹</button>
                <button class="button sm" type="button" @click="goToday">Aktuális hónap</button>
                <button class="button sm ghost month-nav" type="button" aria-label="Következő hónap" @click="moveCalendar(1)">›</button>
              </div>

              <div v-else class="calendar-actions">
                <button class="button sm" type="button" @click="backToMonth">← Vissza a hónaphoz</button>
                <button class="button sm primary" type="button" @click="openManualModal()">+ Kézi foglalás</button>
              </div>
            </div>
          </div>

          <transition name="calendar-view" mode="out-in">
            <div v-if="calendarMode === 'month'" key="month" class="calendar-view-stage">
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
                  tabindex="0"
                  @click="openDay(day.key)"
                  @keydown.enter.prevent="openDay(day.key)"
                  @keydown.space.prevent="openDay(day.key)"
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
                      <button
                        v-else
                        type="button"
                        class="month-event booking"
                        :class="entry.item.status"
                        :title="`${shortTime(entry.item.start_time)} · ${entry.item.customer_name} · ${entry.item.service_name}`"
                        @click.stop="openBookingModal(entry.item)"
                      >
                        <span>{{ shortTime(entry.item.start_time) }}</span>
                        <strong>{{ entry.item.customer_name }}</strong>
                      </button>
                    </template>
                    <div v-if="calendarEntriesForDay(day.key).length > 3" class="month-more">
                      +{{ calendarEntriesForDay(day.key).length - 3 }} további
                    </div>
                  </div>
                </article>
              </div>
            </div>

            <div v-else key="day" class="calendar-view-stage day-calendar-view">
              <div class="day-calendar-controls">
                <div class="day-service-context">
                  <label>Szabad helyek ehhez a szolgáltatáshoz
                    <select v-model="timelineServiceId" @change="loadDayAvailability">
                      <option v-for="service in services.filter(item => item.active)" :key="service.id" :value="service.id">{{ service.name }} · {{ service.duration_minutes }} perc</option>
                    </select>
                  </label>
                </div>
                <div class="day-legend">
                  <span><i class="legend-dot available"></i> Szabad</span>
                  <span><i class="legend-dot booking"></i> Foglalás</span>
                  <span><i class="legend-dot block"></i> Blokkolva</span>
                  <span><i class="legend-dot closed"></i> Nem foglalható</span>
                </div>
              </div>

              <div v-if="dayLoading" class="day-loading"><span class="spinner"></span> Napi naptár betöltése…</div>

              <div v-else ref="dayTimelineScroller" class="day-timeline-scroller">
                <div class="day-timeline" :style="{ height: dayTimelineHeight + 'px' }">
                  <div
                    v-for="hour in dayTimelineHours"
                    :key="hour"
                    class="day-hour-row"
                    :style="{ top: (((hour * 60) - dayTimelineStartMinutes) / 60 * 64) + 'px' }"
                  >
                    <span class="day-hour-label">{{ String(hour).padStart(2, '0') }}:00</span>
                    <div class="day-quarter-grid">
                      <button
                        v-for="cell in quarterCellsForHour(hour)"
                        :key="cell.time"
                        type="button"
                        class="day-quarter-cell"
                        :class="{ available: cell.available, working: cell.working, closed: !cell.working }"
                        :disabled="!cell.available"
                        :title="cell.available ? `${cell.time} — kézi foglalás létrehozása` : `${cell.time} — nem elérhető`"
                        @click="openManualModal(cell.time)"
                      >
                        <span>{{ cell.time.slice(3) === '00' ? '' : cell.time }}</span>
                      </button>
                    </div>
                  </div>

                  <button
                    v-for="item in dayBookings"
                    :key="`booking-${item.id}`"
                    type="button"
                    class="day-event day-booking-event"
                    :class="item.status"
                    :style="timelineEventStyle(item)"
                    @click="openBookingModal(item)"
                  >
                    <strong>{{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }} · {{ item.customer_name }}</strong>
                    <span>{{ item.service_name }}</span>
                  </button>

                  <div
                    v-for="item in dayBlocks"
                    :key="`block-${item.id}`"
                    class="day-event day-block-event"
                    :style="timelineEventStyle(item)"
                    :title="item.reason || 'Blokkolva'"
                  >
                    <strong>{{ shortTime(item.start_time) }}–{{ shortTime(item.end_time) }}</strong>
                    <span>{{ item.reason || 'Blokkolva' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </transition>
        </div>

        <div class="panel block-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Naptár lezárása</p>
              <h2>Időszak blokkolása</h2>
              <p class="lead block-lead">Adj meg kezdő és záró dátumot. A rendszer minden érintett napot blokkolja, és azonnal megjeleníti a havi és napi naptárban is.</p>
            </div>
          </div>

          <div class="block-form-grid range-block-form-grid">
            <label>Kezdő dátum <input v-model="block.start_date" type="date" @change="syncBlockDates" /></label>
            <label>Záró dátum <input v-model="block.end_date" :min="block.start_date" type="date" /></label>
            <label>Kezdés <input v-model="block.start_time" type="time" /></label>
            <label>Vége <input v-model="block.end_time" type="time" /></label>
            <label class="block-reason-field">Indoklás <input v-model.trim="block.reason" placeholder="pl. Szabadság" /></label>
          </div>
          <button class="button primary" type="button" :disabled="blockingTime" @click="saveBlock">{{ blockingTime ? 'Mentés…' : 'Blokk mentése' }}</button>

          <div v-if="blockGroups.length" class="block-list block-list-wide">
            <div v-for="group in blockGroups" :key="group.signature + group.start_date + group.end_date" class="block-item">
              <div class="info">
                <strong>
                  {{ group.start_date }}<template v-if="group.end_date !== group.start_date"> – {{ group.end_date }}</template>
                  · {{ shortTime(group.start_time) }}–{{ shortTime(group.end_time) }}
                </strong>
                <span>{{ group.reason || 'Nincs indoklás' }}<template v-if="group.items.length > 1"> · {{ group.items.length }} nap</template></span>
              </div>
              <button class="icon-btn" type="button" title="Blokkolás törlése" @click="deleteBlockGroup(group)">×</button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'services'" class="panel service-list-panel services-single-panel">
        <div class="section-title">
          <div><p class="eyebrow">Szolgáltatások</p><h2>Árak, időtartamok, sorrend</h2></div>
          <button class="button sm" type="button" @click="openServiceModal()">Új szolgáltatás</button>
        </div>
        <div class="service-admin-list">
          <article v-for="service in services" :key="service.id" class="service-admin-card" :class="{inactive: !service.active}">
            <div class="service-admin-main">
              <div class="service-admin-thumb">
                <img v-if="service.image_url" :src="service.image_url" :alt="service.name" />
                <span v-else>{{ monogram(service.name) || '•' }}</span>
              </div>
              <div><strong>{{ service.name }}</strong><small>{{ service.category }} · {{ service.duration_minutes }} perc · {{ price(service) || 'Nincs ár' }}</small><p>{{ service.description }}</p></div>
            </div>
            <div class="service-actions"><button class="button sm" @click="moveService(service, -1)">↑</button><button class="button sm" @click="moveService(service, 1)">↓</button><button class="button sm" @click="editService(service)">Szerkesztés</button><button class="button sm" @click="toggleService(service)">{{ service.active ? 'Inaktiválás' : 'Aktiválás' }}</button></div>
          </article>
          <p v-if="!services.length" class="empty compact">Még nincs szolgáltatás.</p>
        </div>
      </section>

      <section v-if="activeTab === 'email'" class="email-admin-section">
        <div class="email-stat-grid">
          <article class="email-stat-card">
            <span>Összes próbálkozás</span>
            <strong>{{ emailStats.total ?? '–' }}</strong>
          </article>
          <article class="email-stat-card success">
            <span>Sikeres</span>
            <strong>{{ emailStats.sent ?? '–' }}</strong>
          </article>
          <article class="email-stat-card failed">
            <span>Sikertelen</span>
            <strong>{{ emailStats.failed ?? '–' }}</strong>
          </article>
          <article class="email-stat-card accent">
            <span>Sikerességi arány</span>
            <strong>{{ emailStats.success_rate ?? 0 }}%</strong>
          </article>
        </div>

        <section
          ref="emailLogPanel"
          class="panel email-log-panel"
        >
          <div class="section-title email-section-title">
            <div>
              <p class="eyebrow">Email napló</p>
              <h2>Kiküldések és hibák</h2>
              <p class="lead email-lead">Itt látod, melyik email ment ki, melyik hibázott, és szükség esetén egy kattintással újraküldheted.</p>
            </div>
            <button class="button sm" type="button" :disabled="emailLoading" @click="loadEmailLogs">
              <span v-if="emailLoading" class="spinner"></span>{{ emailLoading ? 'Frissítés…' : 'Frissítés' }}
            </button>
          </div>

          <div class="email-system-strip">
            <span><b>Mailer:</b> {{ emailSystem.mailer || '–' }}</span>
            <span><b>Technikai feladó:</b> {{ emailSystem.from_address || '–' }}</span>
            <span><b>Utolsó sikeres:</b> {{ formatDateTime(emailStats.last_sent_at) }}</span>
          </div>

          <div class="email-filter-bar">
            <input v-model.trim="emailFilters.q" type="search" placeholder="Keresés címzett / tárgy / vendég / szolgáltatás" @keyup.enter="loadEmailLogs({ resetPage: true })" />
            <select v-model="emailFilters.status" @change="loadEmailLogs({ resetPage: true })">
              <option value="">Minden státusz</option>
              <option value="sent">Sikeres</option>
              <option value="failed">Sikertelen</option>
            </select>
            <select v-model="emailFilters.event_type" @change="loadEmailLogs">
              <option value="">Minden esemény</option>
              <option value="booking_created">Új foglalás</option>
              <option value="booking_rescheduled">Módosítás</option>
              <option value="booking_cancelled">Lemondás</option>
              <option value="email_test">Teszt email</option>
            </select>
            <select v-model="emailFilters.recipient_type"  @change="loadEmailLogs({ resetPage: true })">
              <option value="">Minden címzettípus</option>
              <option value="customer">Ügyfél</option>
              <option value="admin">Admin</option>
            </select>
            <button class="button sm" type="button" @click="loadEmailLogs({ resetPage: true })">Keresés</button>
            <button class="button sm ghost" type="button" @click="resetEmailFilters">Szűrők törlése</button>
          </div>

          <div
            v-if="emailPagination.total > 0"
            class="email-pagination email-pagination-top"
          >
            <div class="email-pagination-meta">

              <span class="email-pagination-summary">
                {{ emailPagination.from }}–{{ emailPagination.to }}
                / {{ emailPagination.total }} esemény
              </span>

              <label class="email-page-size">
                <select
                  v-model.number="emailPagination.per_page"
                  @change="changeEmailPageSize"
                >
                  <option
                    v-for="size in emailPageSizeOptions"
                    :key="size"
                    :value="size"
                  >
                    {{ size }}
                  </option>
                </select>

                <span>esemény / oldal</span>
              </label>

            </div>

            <nav
              class="email-pager"
              aria-label="Email napló lapozás"
            >
              <button
                class="email-page-button email-page-arrow"
                type="button"
                :disabled="emailPagination.current_page <= 1 || emailLoading"
                @click="goToEmailPage(emailPagination.current_page - 1)"
              >
                ←
              </button>

              <template
                v-for="page in emailPaginationPages"
                :key="page"
              >
                <span
                  v-if="typeof page !== 'number'"
                  class="email-page-ellipsis"
                >
                  …
                </span>

                <button
                  v-else
                  class="email-page-button"
                  :class="{
                    active: page === emailPagination.current_page
                  }"
                  type="button"
                  :disabled="emailLoading"
                  :aria-current="
                    page === emailPagination.current_page
                      ? 'page'
                      : null
                  "
                  @click="goToEmailPage(page)"
                >
                  {{ page }}
                </button>
              </template>

              <button
                class="email-page-button email-page-arrow"
                type="button"
                :disabled="
                  emailPagination.current_page >= emailPagination.last_page
                  || emailLoading
                "
                @click="goToEmailPage(emailPagination.current_page + 1)"
              >
                →
              </button>
            </nav>
          </div>

          <div v-if="emailLoading && !emailLogs.length" class="empty">Email napló betöltése…</div>
          <div v-else-if="!emailLogs.length" class="empty">A megadott szűrőkkel nincs emailnapló.</div>
          <div v-else class="email-log-table-wrap">
            <table class="email-log-table">
              <thead>
                <tr>
                  <th>Időpont</th>
                  <th>Címzett</th>
                  <th>Típus</th>
                  <th>Esemény</th>
                  <th>Tárgy</th>
                  <th>Státusz</th>
                  <th>Műveletek</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in emailLogs" :key="log.id" :class="`email-row-${log.status}`">
                  <td class="email-log-date">{{ formatDateTime(log.sent_at || log.created_at) }}</td>
                  <td><strong>{{ log.recipient_email }}</strong><small v-if="log.booking">{{ log.booking.customer_name }} · {{ log.booking.service_name }}</small></td>
                  <td><span class="email-recipient-pill" :class="log.recipient_type">{{ emailRecipientLabel(log.recipient_type) }}</span></td>
                  <td>{{ emailEventLabel(log.event_type) }}</td>
                  <td class="email-subject-cell">{{ log.subject }}</td>
                  <td><span class="email-status-badge" :class="log.status">{{ emailStatusLabel(log.status) }}</span></td>
                  <td>
                    <div class="email-row-actions">
                      <button class="button sm" type="button" @click="openEmailLog(log)">Részletek</button>
                      <button class="button sm" type="button" :disabled="resendingEmailLogId === log.id" @click="resendEmail(log)">
                        {{ resendingEmailLogId === log.id ? 'Küldés…' : 'Újraküldés' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="emailPagination.total > 0"
            class="email-pagination email-pagination-bottom"
          >
            <span class="email-pagination-summary">
              {{ emailPagination.from }}–{{ emailPagination.to }}
              / {{ emailPagination.total }} esemény
            </span>

            <nav
              class="email-pager"
              aria-label="Email napló alsó lapozás"
            >
              <button
                class="email-page-button email-page-arrow"
                type="button"
                :disabled="emailPagination.current_page <= 1 || emailLoading"
                @click="goToEmailPage(emailPagination.current_page - 1)"
              >
                ←
              </button>

              <template
                v-for="page in emailPaginationPages"
                :key="page"
              >
                <span
                  v-if="typeof page !== 'number'"
                  class="email-page-ellipsis"
                >
                  …
                </span>

                <button
                  v-else
                  class="email-page-button"
                  :class="{
                    active: page === emailPagination.current_page
                  }"
                  type="button"
                  :disabled="emailLoading"
                  @click="goToEmailPage(page)"
                >
                  {{ page }}
                </button>
              </template>

              <button
                class="email-page-button email-page-arrow"
                type="button"
                :disabled="
                  emailPagination.current_page >= emailPagination.last_page
                  || emailLoading
                "
                @click="goToEmailPage(emailPagination.current_page + 1)"
              >
                →
              </button>
            </nav>
          </div>
        </section>

        <section class="panel email-test-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Kézbesítési teszt</p>
              <h2>Teszt email küldése</h2>
              <p class="lead email-lead">Küldj valódi tesztlevelet anélkül, hogy új foglalást kellene létrehoznod.</p>
            </div>
          </div>
          <form class="email-test-form" @submit.prevent="sendTestEmail">
            <label>Címzett e-mail
              <input v-model.trim="testEmail.recipient_email" type="email" required placeholder="teszt@example.com" />
            </label>
            <label>Nézet
              <select v-model="testEmail.recipient_type">
                <option value="customer">Ügyfél email</option>
                <option value="admin">Admin email</option>
              </select>
            </label>
            <label>Esemény
              <select v-model="testEmail.event_type">
                <option value="booking_created">Új foglalás</option>
                <option value="booking_rescheduled">Módosítás</option>
                <option value="booking_cancelled">Lemondás</option>
              </select>
            </label>
            <button class="button primary" type="submit" :disabled="!testEmailValid || sendingTestEmail">
              <span v-if="sendingTestEmail" class="spinner"></span>{{ sendingTestEmail ? 'Küldés…' : 'Teszt email küldése' }}
            </button>
          </form>
        </section>

        <section class="panel email-template-panel">
          <div class="section-title email-section-title">
            <div>
              <p class="eyebrow">Email tartalom</p>
              <h2>Feladó, sablonok és szövegek</h2>
              <p class="lead email-lead">Az alapadatok – szolgáltatás, dátum, időpont, vendég és kezelő link – mindig benne maradnak. Itt a tárgyat, bevezető szöveget és láblécet szabhatod személyre.</p>
            </div>
            <div class="inline-actions">
              <button class="button sm" type="button" @click="resetEmailSettingsToDefaults">Alapértékek</button>
              <button class="button sm primary" type="button" :disabled="savingEmailSettings" @click="saveEmailSettings">
                {{ savingEmailSettings ? 'Mentés…' : 'Email beállítások mentése' }}
              </button>
            </div>
          </div>

          <div class="email-global-settings">
            <label>Feladó megjelenített neve
              <input v-model.trim="emailSettings.sender_name" maxlength="160" :placeholder="business.name || 'Az Ön Vállalkozása'" />
              <small>A technikai feladócímet továbbra is az SMTP/.env adja; itt a megjelenített név állítható.</small>
            </label>
            <label>Válaszcím (Reply-To)
              <input v-model.trim="emailSettings.reply_to" type="email" maxlength="160" :placeholder="business.email || 'info@vallalkozas.hu'" />
              <small>Ha üres, a vállalkozás Weboldal fülön megadott e-mail címe lesz használva.</small>
            </label>
            <label class="full">Email lábléc
              <textarea v-model.trim="emailSettings.footer_text" rows="3" maxlength="1200"></textarea>
            </label>
          </div>

          <div class="email-placeholder-box">
            <strong>Használható változók</strong>
            <div class="email-placeholder-list">
              <code>{business_name}</code><code>{customer_name}</code><code>{customer_email}</code><code>{service_name}</code><code>{date}</code><code>{time}</code><code>{manage_url}</code>
            </div>
          </div>

          <div class="email-template-switches">
            <div class="email-switch-group">
              <span>Címzett</span>
              <button type="button" :class="{active: emailEditorRecipient === 'customer'}" @click="emailEditorRecipient = 'customer'">Ügyfél</button>
              <button type="button" :class="{active: emailEditorRecipient === 'admin'}" @click="emailEditorRecipient = 'admin'">Admin</button>
            </div>
            <div class="email-switch-group">
              <span>Esemény</span>
              <button type="button" :class="{active: emailEditorEvent === 'booking_created'}" @click="emailEditorEvent = 'booking_created'">Új foglalás</button>
              <button type="button" :class="{active: emailEditorEvent === 'booking_rescheduled'}" @click="emailEditorEvent = 'booking_rescheduled'">Módosítás</button>
              <button type="button" :class="{active: emailEditorEvent === 'booking_cancelled'}" @click="emailEditorEvent = 'booking_cancelled'">Lemondás</button>
            </div>
          </div>

          <div class="email-template-editor">
            <label>Email tárgya
              <input v-model.trim="currentEmailTemplate.subject" maxlength="255" />
            </label>
            <label>Bevezető szöveg
              <textarea v-model.trim="currentEmailTemplate.intro" rows="4" maxlength="1500"></textarea>
            </label>
          </div>

          <div class="email-live-preview">
            <div class="email-preview-header">
              <span>{{ emailEventLabel(emailEditorEvent) }}</span>
              <strong>{{ business.name || 'Az Ön Vállalkozása' }}</strong>
            </div>
            <div class="email-preview-body">
              <small>Tárgy</small>
              <h3>{{ renderEmailTemplatePreview(currentEmailTemplate.subject) }}</h3>
              <p>{{ renderEmailTemplatePreview(currentEmailTemplate.intro) }}</p>
              <dl>
                <div><dt>Szolgáltatás</dt><dd>Konzultáció</dd></div>
                <div><dt>Dátum</dt><dd>2026. 07. 18.</dd></div>
                <div><dt>Időpont</dt><dd>10:00–10:45</dd></div>
                <div><dt>Vendég</dt><dd>Kovács Anna</dd></div>
              </dl>
              <button class="email-preview-button" type="button" disabled>Foglalás kezelése</button>
              <p v-if="emailSettings.footer_text" class="email-preview-footer">{{ emailSettings.footer_text }}</p>
            </div>
          </div>
        </section>
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
    </main>

    <transition name="modal-pop">
      <div v-if="emailLogModalOpen && selectedEmailLog" class="modal-backdrop" @click.self="closeEmailLogModal">
        <section class="modal-dialog email-log-detail-modal" role="dialog" aria-modal="true" aria-labelledby="emailLogModalTitle">
          <div class="modal-head">
            <div>
              <p class="eyebrow">Email részletei</p>
              <h2 id="emailLogModalTitle">{{ emailEventLabel(selectedEmailLog.event_type) }}</h2>
            </div>
            <button class="modal-close" type="button" aria-label="Bezárás" @click="closeEmailLogModal">×</button>
          </div>

          <div class="email-detail-hero" :class="selectedEmailLog.status">
            <div>
              <span class="detail-label">Címzett</span>
              <strong>{{ selectedEmailLog.recipient_email }}</strong>
              <small>{{ emailRecipientLabel(selectedEmailLog.recipient_type) }}</small>
            </div>
            <span class="email-status-badge" :class="selectedEmailLog.status">{{ emailStatusLabel(selectedEmailLog.status) }}</span>
          </div>

          <dl class="booking-detail-grid email-detail-grid">
            <div><dt>Küldési idő</dt><dd>{{ formatDateTime(selectedEmailLog.sent_at || selectedEmailLog.created_at) }}</dd></div>
            <div><dt>Esemény</dt><dd>{{ emailEventLabel(selectedEmailLog.event_type) }}</dd></div>
            <div class="full"><dt>Tárgy</dt><dd>{{ selectedEmailLog.subject }}</dd></div>
            <div v-if="selectedEmailLog.booking"><dt>Vendég</dt><dd>{{ selectedEmailLog.booking.customer_name }}</dd></div>
            <div v-if="selectedEmailLog.booking"><dt>Szolgáltatás</dt><dd>{{ selectedEmailLog.booking.service_name }}</dd></div>
            <div v-if="selectedEmailLog.booking"><dt>Dátum</dt><dd>{{ formatDateLong(selectedEmailLog.booking.date) }}</dd></div>
            <div v-if="selectedEmailLog.booking"><dt>Időpont</dt><dd>{{ shortTime(selectedEmailLog.booking.start_time) }}–{{ shortTime(selectedEmailLog.booking.end_time) }}</dd></div>
            <div v-if="selectedEmailLog.resent_from_id" class="full"><dt>Újraküldés forrása</dt><dd>#{{ selectedEmailLog.resent_from_id }} naplóbejegyzés</dd></div>
          </dl>

          <div v-if="selectedEmailLog.status === 'failed'" class="email-error-box">
            <strong>Hibaüzenet</strong>
            <pre>{{ selectedEmailLog.error_message || 'Ismeretlen emailküldési hiba.' }}</pre>
          </div>

          <div class="modal-actions">
            <button class="button" type="button" @click="closeEmailLogModal">Bezárás</button>
            <button class="button primary" type="button" :disabled="resendingEmailLogId === selectedEmailLog.id" @click="resendEmail(selectedEmailLog)">
              {{ resendingEmailLogId === selectedEmailLog.id ? 'Újraküldés…' : 'Email újraküldése' }}
            </button>
          </div>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="bookingModalOpen && selectedBooking" class="modal-backdrop" @click.self="closeBookingModal">
        <section class="modal-dialog booking-detail-modal" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle">
          <div class="modal-head">
            <div><p class="eyebrow">Foglalás kezelése</p><h2 id="bookingModalTitle">{{ selectedBooking.customer_name }}</h2></div>
            <button class="modal-close" type="button" aria-label="Bezárás" @click="closeBookingModal">×</button>
          </div>

          <div class="booking-detail-hero" :class="selectedBooking.status">
            <div><span class="detail-label">Időpont</span><strong>{{ formatDateLong(selectedBooking.date) }}</strong><b>{{ shortTime(selectedBooking.start_time) }}–{{ shortTime(selectedBooking.end_time) }}</b></div>
            <span class="badge" :class="selectedBooking.status">{{ statusLabel(selectedBooking.status) }}</span>
          </div>

          <dl class="booking-detail-grid">
            <div><dt>Szolgáltatás</dt><dd>{{ selectedBooking.service_name }}</dd></div>
            <div><dt>Vendég</dt><dd>{{ selectedBooking.customer_name }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ selectedBooking.customer_contact }}</dd></div>
            <div><dt>Foglalt idő</dt><dd>{{ shortTime(selectedBooking.start_time) }}–{{ shortTime(selectedBooking.end_time) }}</dd></div>
            <div class="full"><dt>Megjegyzés</dt><dd>{{ selectedBooking.customer_note || 'Nincs megjegyzés.' }}</dd></div>
          </dl>

          <div class="modal-actions booking-status-actions">
            <button class="button" type="button" @click="copyManageLink(selectedBooking)">Kezelő link másolása</button>
            <template v-if="selectedBooking.status === 'booked'">
              <button class="button" type="button" @click="setStatus(selectedBooking, 'completed')">Teljesítve</button>
              <button class="button" type="button" @click="setStatus(selectedBooking, 'no_show')">Nem jött el</button>
              <button class="button danger" type="button" @click="setStatus(selectedBooking, 'cancelled')">Lemondás</button>
            </template>
            <button v-else class="button primary" type="button" @click="setStatus(selectedBooking, 'booked')">Visszaállítás aktívra</button>
          </div>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="manualModalOpen" class="modal-backdrop" @click.self="closeManualModal">
        <section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="manualModalTitle">
          <div class="modal-head">
            <div><p class="eyebrow">Admin foglalás</p><h2 id="manualModalTitle">Kézi foglalás felvétele</h2></div>
            <button class="modal-close" type="button" aria-label="Bezárás" @click="closeManualModal">×</button>
          </div>
          <form class="modal-form" @submit.prevent="saveManualBooking">
            <label>Szolgáltatás
              <select v-model="manual.service_id" required @change="loadManualSlots()">
                <option value="" disabled>Válassz szolgáltatást</option>
                <option v-for="service in services.filter(item => item.active)" :key="service.id" :value="service.id">{{ service.name }} · {{ service.duration_minutes }} perc</option>
              </select>
            </label>
            <div class="two-cols">
              <label>Dátum <input v-model="manual.date" type="date" required @change="loadManualSlots()" /></label>
              <label>Időpont
                <select v-model="manual.time" required>
                  <option value="" disabled>Nincs szabad időpont</option>
                  <option v-for="slot in manualSlots" :key="slot.time" :value="slot.time">{{ slot.time }}–{{ slot.endTime }}</option>
                </select>
              </label>
            </div>
            <label>Vendég neve
              <input ref="manualNameInput" v-model.trim="manual.customer_name" type="text" required minlength="2" maxlength="120" autocomplete="name" placeholder="pl. Kovács Anna" />
              <small v-if="manualNameError" class="field-error">{{ manualNameError }}</small>
            </label>
            <label>E-mail cím
              <input v-model.trim="manual.customer_contact" type="email" required maxlength="160" autocomplete="email" placeholder="anna@example.com" />
              <small v-if="manualEmailError" class="field-error">{{ manualEmailError }}</small>
            </label>
            <label>Ügyfél megjegyzés
              <textarea v-model.trim="manual.customer_note" rows="4" minlength="3" maxlength="800" placeholder="pl. kapucsengő, extra kérés, előzmény"></textarea>
              <small v-if="manualNoteError" class="field-error">{{ manualNoteError }}</small>
            </label>
            <div class="modal-actions"><button class="button" type="button" @click="closeManualModal">Mégse</button><button class="button primary" :disabled="savingManual || !manualValid">{{ savingManual ? 'Mentés…' : 'Foglalás mentése' }}</button></div>
          </form>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
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

            <div class="service-image-uploader">
              <div class="service-image-preview-large">
                <img v-if="serviceImagePreview" :src="serviceImagePreview" :alt="serviceForm.name || 'Szolgáltatás kép'" />
                <span v-else>{{ monogram(serviceForm.name) || 'KÉP' }}</span>
              </div>
              <div>
                <strong>Szolgáltatás képe</strong>
                <p>JPG, PNG vagy WebP, legfeljebb 5 MB. A kép az uploads tárhelyre kerül.</p>
                <div class="inline-actions">
                  <label class="button sm file-button">Kép kiválasztása<input type="file" accept="image/jpeg,image/png,image/webp" @change="onServiceImageSelected" /></label>
                  <button v-if="serviceImagePreview" class="button sm danger" type="button" :disabled="uploadingServiceImage" @click="deleteServiceImage">Kép törlése</button>
                </div>
              </div>
            </div>

            <div class="two-cols"><label>Időtartam / perc <input v-model.number="serviceForm.duration_minutes" type="number" min="5" required /></label><label>Puffer / perc <input v-model.number="serviceForm.buffer_minutes" type="number" min="0" /></label></div>
            <div class="two-cols"><label>Ár / Ft <input v-model.number="serviceForm.price_forint" type="number" min="0" placeholder="pl. 12000" /></label><label>Sorrend <input v-model.number="serviceForm.sort_order" type="number" min="0" /></label></div>
            <label class="checkline"><input v-model="serviceForm.active" type="checkbox" /> Aktív, foglalható szolgáltatás</label>
            <div class="modal-actions"><button class="button" type="button" @click="closeServiceModal">Mégse</button><button class="button primary" :disabled="savingService || uploadingServiceImage">{{ savingService ? 'Mentés…' : 'Szolgáltatás mentése' }}</button></div>
          </form>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
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
    </transition>

    <transition name="modal-pop">
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
    </transition>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
