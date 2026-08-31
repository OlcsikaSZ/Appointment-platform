<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin — Időpontfoglalás</title>
  <link id="business-favicon" rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" />
  <link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
  <a class="skip-link" href="#main-content">Ugrás a tartalomhoz</a>
  <div id="adminApp" v-cloak>
    <transition name="modal-pop">
      <div v-if="toasts.list.length" class="feedback-modal-backdrop" @click.self="toasts.clear()">
        <section
          class="feedback-modal"
          :class="toasts.list[0].kind"
          :role="toasts.list[0].kind === 'error' ? 'alertdialog' : 'status'"
          aria-modal="true"
          aria-labelledby="feedbackModalTitle"
          aria-describedby="feedbackModalMessage"
          aria-live="polite"
          aria-atomic="true"
        >
          <div class="feedback-icon" aria-hidden="true">{{ toasts.list[0].kind === 'error' ? '!' : '✓' }}</div>
          <div class="feedback-copy">
            <strong id="feedbackModalTitle">{{ toasts.list[0].kind === 'error' ? 'Nem sikerült' : 'Sikeres művelet' }}</strong>
            <p id="feedbackModalMessage">{{ toasts.list[0].message }}</p>
          </div>
          <button class="feedback-close" type="button" @click="toasts.clear()">Rendben</button>
        </section>
      </div>
    </transition>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark admin-brand-mark">
          <img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
          <template v-else>{{ business.logoText || monogram(business.name) || '·' }}</template>
        </span>
        <span><strong>{{ business.name || 'Időpontfoglalás' }}</strong><small>Admin felület</small></span>
      </a>
      <nav>
        <a href="<?= route_url('main') ?>">Foglalási oldal</a>
        <button
          v-if="token && currentUser"
          class="admin-user-chip"
          :class="{ active: activeTab === 'profile' }"
          :aria-pressed="activeTab === 'profile'"
          type="button"
          @click="openProfileTab"
        >
          <span class="admin-user-name">{{ currentUser.name }}</span>
          <span class="admin-user-role">· {{ currentUser.is_owner ? 'Owner' : 'Admin' }}</span>
        </button>
        <a v-if="token" href="#" @click.prevent="logout">Kijelentkezés</a>
      </nav>
    </header>

    <main v-if="!token" id="main-content" class="shell login-shell" tabindex="-1">
      <section class="panel login-card">
        <span class="brand-mark" style="margin:0 auto 18px;">·</span>
        <template v-if="authMode === 'login'">
          <p class="eyebrow">Admin belépés</p>
          <h1 style="font-size:26px;">Jelentkezz be</h1>
          <p class="lead" style="margin:8px auto 0;">A foglalások kezeléséhez lépj be a saját admin- vagy ownerfiókoddal.</p>
          <form class="login-box" @submit.prevent="login">
          <label>E-mail cím <input v-model.trim="credentials.email" type="email" required autocomplete="username" /></label>
          <label>Jelszó <password-input v-model="credentials.password" required autocomplete="current-password"></password-input></label>
          <button class="button primary block" type="submit" :disabled="loggingIn"><span v-if="loggingIn" class="spinner"></span>{{ loggingIn ? 'Belépés…' : 'Belépés' }}</button>
          </form>
          <div class="admin-auth-links">
            <button type="button" @click="showAuthMode('forgot')">Elfelejtetted a jelszavad?</button>
            <button type="button" @click="showAuthMode('activate')">Ownerfiók aktiválása</button>
          </div>
        </template>

        <template v-else-if="authMode === 'forgot'">
          <p class="eyebrow">Jelszó helyreállítása</p>
          <h1 style="font-size:26px;">Kérj ellenőrző kódot</h1>
          <p class="lead" style="margin:8px auto 0;">Ha a címhez adminfiók tartozik, elküldjük a hatjegyű kódot.</p>
          <form class="login-box" @submit.prevent="requestAdminPasswordReset">
            <label>E-mail cím <input v-model.trim="passwordReset.email" type="email" required autocomplete="email" /></label>
            <button class="button primary block" type="submit" :disabled="loggingIn">{{ loggingIn ? 'Küldés…' : 'Kód küldése' }}</button>
          </form>
          <button class="button block" type="button" @click="showAuthMode('login')">Vissza a belépéshez</button>
        </template>

        <template v-else-if="authMode === 'reset'">
          <p class="eyebrow">Jelszó helyreállítása</p>
          <h1 style="font-size:26px;">Állíts be új jelszót</h1>
          <form class="login-box" @submit.prevent="resetAdminPassword">
            <label>E-mail cím <input v-model.trim="passwordReset.email" type="email" required autocomplete="email" /></label>
            <fieldset class="admin-code-field">
              <legend>Ellenőrző kód</legend>
              <div class="admin-code-inputs" @paste="handleAdminCodePaste('passwordResetDigits', $event)">
                <input v-for="(_, index) in passwordResetDigits" :key="index" :value="passwordResetDigits[index]" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-code-group="passwordResetDigits" :data-code-index="index" @input="handleAdminCodeInput('passwordResetDigits', index, $event)" @keydown="handleAdminCodeKeydown('passwordResetDigits', index, $event)" />
              </div>
            </fieldset>
            <label>Új jelszó <password-input v-model="passwordReset.password" required autocomplete="new-password"></password-input></label>
            <label>Új jelszó ismét <password-input v-model="passwordReset.password_confirmation" required autocomplete="new-password"></password-input></label>
            <small>Legalább 10 karakter, kis- és nagybetű, szám és különleges jel.</small>
            <button class="button primary block" type="submit" :disabled="loggingIn">{{ loggingIn ? 'Mentés…' : 'Új jelszó mentése' }}</button>
          </form>
          <button class="button block" type="button" @click="showAuthMode('forgot')">Új kód kérése</button>
        </template>

        <template v-else>
          <p class="eyebrow">Ownerfiók aktiválása</p>
          <h1 style="font-size:26px;">Erősítsd meg az e-mail-címet</h1>
          <p class="lead" style="margin:8px auto 0;">Az <code>app:create-owner</code> paranccsal létrehozott fiók egyszeri aktiválása.</p>
          <form class="login-box" @submit.prevent="activateOwner">
            <label>E-mail cím <input v-model.trim="ownerActivation.email" type="email" required autocomplete="email" /></label>
            <fieldset class="admin-code-field">
              <legend>Ellenőrző kód</legend>
              <div class="admin-code-inputs" @paste="handleAdminCodePaste('ownerActivationDigits', $event)">
                <input v-for="(_, index) in ownerActivationDigits" :key="index" :value="ownerActivationDigits[index]" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-code-group="ownerActivationDigits" :data-code-index="index" @input="handleAdminCodeInput('ownerActivationDigits', index, $event)" @keydown="handleAdminCodeKeydown('ownerActivationDigits', index, $event)" />
              </div>
            </fieldset>
            <label>Saját jelszó <password-input v-model="ownerActivation.password" required autocomplete="new-password"></password-input></label>
            <label>Jelszó ismét <password-input v-model="ownerActivation.password_confirmation" required autocomplete="new-password"></password-input></label>
            <small>Legalább 10 karakter, kis- és nagybetű, szám és különleges jel.</small>
            <button class="button primary block" type="submit" :disabled="loggingIn">{{ loggingIn ? 'Aktiválás…' : 'Ownerfiók aktiválása' }}</button>
          </form>
          <div class="admin-auth-links">
            <button type="button" :disabled="loggingIn" @click="resendOwnerActivation">Új kód küldése</button>
            <button type="button" @click="showAuthMode('login')">Vissza a belépéshez</button>
          </div>
        </template>
      </section>
    </main>

    <main v-else id="main-content" class="shell admin-shell" tabindex="-1">
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

      <div class="tabs" aria-label="Admin szekciók">
        <button type="button" :aria-pressed="activeTab === 'calendar'" :class="{active: activeTab === 'calendar'}" @click="activeTab = 'calendar'">Naptár</button>
        <button type="button" :aria-pressed="activeTab === 'services'" :class="{active: activeTab === 'services'}" @click="activeTab = 'services'">Szolgáltatások</button>
        <button type="button" :aria-pressed="activeTab === 'customers'" :class="{active: activeTab === 'customers'}" @click="openCustomersTab">Ügyfelek</button>
        <button type="button" :aria-pressed="activeTab === 'statistics'" :class="{active: activeTab === 'statistics'}" @click="openStatisticsTab">Statisztikák</button>
        <button type="button" :aria-pressed="activeTab === 'website'" :class="{active: activeTab === 'website'}" @click="openWebsiteTab">Weboldal</button>
        <button type="button" :aria-pressed="activeTab === 'email'" :class="{active: activeTab === 'email'}" @click="openEmailTab">
          E-mailek <span v-if="Number(emailStats.failed || 0) > 0" class="tab-alert-count">{{ emailStats.failed }}</span>
        </button>
        <button type="button" :aria-pressed="activeTab === 'settings'" :class="{active: activeTab === 'settings'}" @click="openSettingsTab">Beállítások</button>
      </div>

      <section v-if="activeTab === 'profile'" class="settings-admin-section profile-admin-section">
        <section class="panel settings-panel admin-security-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Adminprofil és biztonság</p>
              <h2>Saját fiók és munkamenetek</h2>
              <p class="lead">Minden admin a saját fiókját használja. A biztonsági változásokról e-mail-értesítés érkezik.</p>
            </div>
            <span class="admin-role-badge">{{ currentUser?.is_owner ? 'Owner' : 'Admin' }}</span>
          </div>

          <div class="admin-security-grid">
            <form class="admin-security-card" @submit.prevent="saveAdminProfile">
              <p class="eyebrow">Profil</p>
              <h3>Név módosítása</h3>
              <label>Név
                <input v-model.trim="adminProfileForm.name" type="text" required maxlength="120" autocomplete="name" />
              </label>
              <label>Jelenlegi e-mail
                <input :value="currentUser?.email || ''" type="email" readonly />
              </label>
              <button class="button primary block" type="submit" :disabled="savingAdminProfile">{{ savingAdminProfile ? 'Mentés…' : 'Név mentése' }}</button>
            </form>

            <form class="admin-security-card" @submit.prevent="requestAdminEmailChange">
              <p class="eyebrow">E-mail-csere</p>
              <h3>Új cím megerősítéssel</h3>
              <label>Új e-mail-cím
                <input v-model.trim="adminEmailForm.email" type="email" required autocomplete="email" />
              </label>
              <label>Jelenlegi jelszó
                <password-input v-model="adminEmailForm.current_password" required autocomplete="current-password"></password-input>
              </label>
              <button class="button primary block" type="submit" :disabled="requestingAdminEmail">{{ requestingAdminEmail ? 'Küldés…' : 'Ellenőrző kód küldése' }}</button>

              <div v-if="currentUser?.pending_email" class="pending-email-verification">
                <strong>Megerősítésre vár: {{ currentUser.pending_email }}</strong>
                <fieldset class="admin-code-field">
                  <legend>A kapott hatjegyű kód</legend>
                  <div class="admin-code-inputs" @paste="handleAdminCodePaste('adminEmailDigits', $event)">
                    <input v-for="(_, index) in adminEmailDigits" :key="index" :value="adminEmailDigits[index]" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-code-group="adminEmailDigits" :data-code-index="index" @input="handleAdminCodeInput('adminEmailDigits', index, $event)" @keydown="handleAdminCodeKeydown('adminEmailDigits', index, $event)" />
                  </div>
                </fieldset>
                <button class="button block" type="button" :disabled="verifyingAdminEmail" @click="verifyAdminEmailChange">{{ verifyingAdminEmail ? 'Ellenőrzés…' : 'Új e-mail megerősítése' }}</button>
              </div>
            </form>

            <form class="admin-security-card" @submit.prevent="changeAdminPassword">
              <p class="eyebrow">Jelszó</p>
              <h3>Jelszó módosítása</h3>
              <label>Jelenlegi jelszó
                <password-input v-model="adminPasswordForm.current_password" required autocomplete="current-password"></password-input>
              </label>
              <label>Új jelszó
                <password-input v-model="adminPasswordForm.password" required autocomplete="new-password"></password-input>
              </label>
              <label>Új jelszó ismét
                <password-input v-model="adminPasswordForm.password_confirmation" required autocomplete="new-password"></password-input>
              </label>
              <small>Legalább 10 karakter, kis- és nagybetű, szám és különleges jel.</small>
              <button class="button primary block" type="submit" :disabled="changingAdminPassword">{{ changingAdminPassword ? 'Mentés…' : 'Jelszó módosítása' }}</button>
            </form>

            <section class="admin-security-card admin-sessions-card">
              <p class="eyebrow">Aktív munkamenetek</p>
              <h3>Bejelentkezett eszközök</h3>
              <p v-if="securityLoading" class="empty compact">Munkamenetek betöltése…</p>
              <div v-else class="admin-session-list">
                <article v-for="session in adminSessions" :key="session.id" :class="{ current: session.current }">
                  <div>
                    <strong>{{ adminSessionLabel(session) }} <span v-if="session.current">· ez az eszköz</span></strong>
                    <small>{{ session.ip_address || 'Ismeretlen IP' }} · Utolsó használat: {{ formatDateTime(session.last_used_at || session.created_at) }}</small>
                  </div>
                  <button class="button sm danger" type="button" :disabled="revokingAdminSessionId === session.id" @click="revokeAdminSession(session)">{{ revokingAdminSessionId === session.id ? 'Kiléptetés…' : 'Kijelentkeztetés' }}</button>
                </article>
                <p v-if="!adminSessions.length" class="empty compact">Nincs megjeleníthető admin munkamenet.</p>
              </div>
              <button class="button danger block" type="button" :disabled="!adminSessions.length" @click="logoutAllAdminSessions">Kijelentkezés minden eszközről</button>
            </section>
          </div>
        </section>
      </section>

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

              <label v-if="calendarMode === 'month'" class="month-availability-service">
                <span>Szabad helyek ehhez</span>
                <select v-model="timelineServiceId" @change="handleTimelineServiceChange">
                  <option v-for="service in services.filter(item => item.active)" :key="service.id" :value="service.id">
                    {{ service.name }} · {{ service.duration_minutes }} perc
                  </option>
                </select>
              </label>

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

          <fieldset class="calendar-filter-bar">
            <legend class="sr-only">Naptár foglalásszűrői</legend>
            <label>
              <span>Szolgáltatás</span>
              <select v-model="calendarFilters.service_id" @change="handleCalendarServiceFilterChange">
                <option value="">Összes szolgáltatás</option>
                <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option>
              </select>
            </label>
            <label>
              <span>Státusz</span>
              <select v-model="calendarFilters.status">
                <option value="">Minden státusz</option>
                <option value="booked">Foglalva</option>
                <option value="completed">Teljesítve</option>
                <option value="cancelled">Lemondva</option>
                <option value="no_show">Nem jelent meg</option>
              </select>
            </label>
            <label>
              <span>Pontos dátum</span>

              <div class="native-date-input">
                <input
                  v-model="calendarFilters.date"
                  type="date"
                  :class="{ 'is-empty': !calendarFilters.date }"
                  aria-label="Pontos dátum, nem kötelező"
                />

                <span
                  v-if="!calendarFilters.date"
                  class="native-date-placeholder"
                  aria-hidden="true"
                >
                  Válassz dátumot
                </span>
              </div>
            </label>
            <button v-if="activeCalendarFilterCount" class="button sm" type="button" @click="resetCalendarFilters">
              Szűrők törlése ({{ activeCalendarFilterCount }})
            </button>
            <button class="button sm primary calendar-export-button" type="button" :disabled="exportingBookings" @click="exportCalendarBookings">
              <span v-if="exportingBookings" class="spinner"></span>{{ exportingBookings ? 'Exportálás…' : 'Excel export' }}
            </button>
          </fieldset>

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
                  :class="{
                    'outside-month': !day.inCurrentMonth,
                    today: day.isToday,
                    past: day.isPast,
                    closed: day.isClosed,
                    'sold-out': day.isSoldOut,
                    'has-entries': calendarEntriesForDay(day.key).length
                  }"
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

                  <span class="month-day-statuses">
                    <span
                      v-if="day.isPast && !day.isToday"
                      class="month-availability past-label"
                    >
                      Elmúlt
                    </span>

                    <span
                      v-else-if="day.availability"
                      class="month-availability"
                      :class="{ empty: day.isClosed || day.isSoldOut }"
                    >
                      {{ day.isClosed
                        ? 'Zárva'
                        : (day.isSoldOut
                            ? 'Nincs szabad'
                            : day.availability.available_count + ' szabad')
                      }}
                    </span>
                  </span>

                  <div class="month-day-events">
                    <template v-for="entry in calendarEntriesForDay(day.key).slice(0, 3)" :key="entry.key">
                      <div
                        v-if="entry.type === 'block'"
                        class="month-event block"
                        :title="entry.item.is_all_day ? `Egész nap · ${entry.item.reason || 'Zárva'}` : `${shortTime(entry.item.start_time)}–${shortTime(entry.item.end_time)} · ${entry.item.reason || 'Blokkolva'}`"
                      >
                        <span>{{ entry.item.is_all_day ? 'Egész nap' : shortTime(entry.item.start_time) }}</span>
                        <strong>{{ entry.item.reason || (entry.item.is_all_day ? 'Zárva' : 'Blokkolva') }}</strong>
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
                    <select v-model="timelineServiceId" @change="handleTimelineServiceChange">
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

              <div v-if="dayAllDayBlocks.length" class="all-day-closure-banner">
                <strong>Egész nap zárva</strong>
                <span>{{ dayAllDayBlocks.map(item => item.reason || 'Szabadság / zárva').join(' · ') }}</span>
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
                    v-for="item in filteredDayBookings"
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
                    v-for="item in dayTimedBlocks"
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

        <div class="panel schedule-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Valódi foglalható idő</p>
              <h2>Heti munkaidő</h2>
              <p class="lead block-lead">Ez a beállítás közvetlenül meghatározza, hogy a rendszer mely napokon és órákban kínálhat fel időpontokat.</p>
            </div>
          </div>

          <div v-if="workingHoursLoading" class="day-loading"><span class="spinner"></span> Munkaidő betöltése…</div>

          <div v-else class="workweek-editor">
            <article v-for="day in workWeek" :key="day.weekday" class="workday-row" :class="{ closed: day.closed }">
              <div class="workday-main">
                <strong>{{ day.label }}</strong>
                <label class="inline-checkbox workday-closed-toggle">
                  <input v-model="day.closed" type="checkbox" />
                  <span>Zárva</span>
                </label>
              </div>

              <template v-if="!day.closed">
                <label>Kezdés <input v-model="day.start_time" type="time" /></label>
                <label>Vége <input v-model="day.end_time" type="time" /></label>
                <label class="inline-checkbox workday-break-toggle">
                  <input v-model="day.break_enabled" type="checkbox" />
                  <span>Ebédszünet</span>
                </label>
                <label v-if="day.break_enabled">Szünet kezdete <input v-model="day.break_start" type="time" /></label>
                <label v-if="day.break_enabled">Szünet vége <input v-model="day.break_end" type="time" /></label>
              </template>

              <span v-else class="workday-closed-text">Ezen a napon nem foglalható időpont.</span>
            </article>
          </div>

          <div class="schedule-save-row">
            <label class="inline-checkbox">
              <input v-model="syncPublicOpeningHours" type="checkbox" />
              <span>A publikus „Nyitvatartás” szöveg automatikus frissítése</span>
            </label>
            <button class="button primary" type="button" :disabled="savingWorkingHours || !workingHoursValid" @click="saveWorkingHours(false)">
              {{ savingWorkingHours ? 'Mentés…' : 'Munkaidő mentése' }}
            </button>
          </div>
          <p v-if="!workingHoursValid" class="field-error">Ellenőrizd az időpontokat: a szünetnek a kezdés és a befejezés között kell lennie.</p>
        </div>

        <div class="panel block-panel">
          <div class="section-title">
            <div>
              <p class="eyebrow">Szabadságok és kivételek</p>
              <h2>Szabadság / időszak lezárása</h2>
              <p class="lead block-lead">Lezárhatsz néhány órát, egy teljes napot vagy egy teljes dátumtartományt. Aktív foglalás-ütközésnél a rendszer külön megerősítést kér.</p>
            </div>
          </div>

          <label class="inline-checkbox all-day-toggle">
            <input v-model="block.all_day" type="checkbox" />
            <span>Teljes napos lezárás</span>
          </label>

          <div class="block-form-grid range-block-form-grid" :class="{ 'all-day': block.all_day }">
            <label>Kezdő dátum <input v-model="block.start_date" type="date" @change="syncBlockDates" /></label>
            <label>Záró dátum <input v-model="block.end_date" :min="block.start_date" type="date" /></label>
            <label v-if="!block.all_day">Kezdés <input v-model="block.start_time" type="time" /></label>
            <label v-if="!block.all_day">Vége <input v-model="block.end_time" type="time" /></label>
            <label class="block-reason-field">Indoklás <input v-model.trim="block.reason" placeholder="pl. Szabadság, orvos, karbantartás" /></label>
          </div>
          <button class="button primary" type="button" :disabled="blockingTime" @click="saveBlock(false)">{{ blockingTime ? 'Mentés…' : 'Lezárás mentése' }}</button>

          <div v-if="blockGroups.length" class="block-list block-list-wide">
            <div v-for="group in blockGroups" :key="group.signature + group.start_date + group.end_date" class="block-item">
              <div class="info">
                <strong>
                  {{ group.start_date }}<template v-if="group.end_date !== group.start_date"> – {{ group.end_date }}</template>
                  · {{ group.all_day ? 'Egész nap' : shortTime(group.start_time) + '–' + shortTime(group.end_time) }}
                </strong>
                <span>{{ group.reason || 'Nincs indoklás' }}<template v-if="group.items.length > 1"> · {{ group.items.length }} nap</template></span>
              </div>
              <button class="icon-btn" type="button" title="Blokkolás törlése" @click="deleteBlockGroup(group)">×</button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'statistics'" class="statistics-admin-section">
        <div class="panel statistics-hero">
          <div><p class="eyebrow">Üzleti áttekintés</p><h2>{{ statistics.label || 'Havi statisztikák' }}</h2><p class="lead">A bevétel becslés: a foglalt és teljesített időpontok szolgáltatási ára. A kihasználtság a nyitvatartásból a blokkolások levonása után számolódik.</p></div>
          <div class="statistics-actions"><input v-model="statisticsMonth" type="month" aria-label="Statisztikai hónap" @change="loadStatistics" /><button class="button primary" type="button" :disabled="exportingStatistics" @click="exportStatistics">{{ exportingStatistics ? 'Exportálás…' : 'Statisztikák Excelben' }}</button></div>
        </div>
        <div v-if="statisticsLoading" class="panel statistics-loading"><span class="spinner"></span> Statisztikák számolása…</div>
        <template v-else>
          <div class="statistics-kpi-grid">
            <article class="panel"><span>Havi foglalás</span><strong>{{ statistics.total_bookings ?? 0 }}</strong><small>{{ comparisonLabel(statistics.comparison?.booking_change_percent) }} az előző hónaphoz képest</small></article>
            <article class="panel"><span>Lemondási arány</span><strong>{{ statistics.cancellation_rate ?? 0 }}%</strong><small>{{ statistics.status_counts?.cancelled ?? 0 }} lemondott foglalás</small></article>
            <article class="panel"><span>No-show arány</span><strong>{{ statistics.no_show_rate ?? 0 }}%</strong><small>{{ statistics.status_counts?.no_show ?? 0 }} nem jelent meg</small></article>
            <article class="panel accent"><span>Becsült bevétel</span><strong>{{ formatForint(statistics.estimated_revenue) }}</strong><small>{{ comparisonLabel(statistics.comparison?.revenue_change_percent) }} az előző hónaphoz képest</small></article>
            <article class="panel"><span>Idősáv-kihasználtság</span><strong>{{ statistics.utilization_rate ?? 0 }}%</strong><small>{{ statistics.occupied_minutes ?? 0 }} / {{ statistics.available_minutes ?? 0 }} perc</small></article>
          </div>
          <div class="statistics-grid">
            <section class="panel"><div class="section-title"><div><p class="eyebrow">Napi aktivitás</p><h2>Foglalások a hónapban</h2></div></div><div class="statistics-daily-chart" :aria-label="'Napi foglalások: ' + statistics.label"><div v-for="day in statistics.daily" :key="day.date" class="statistics-day"><span class="statistics-bar"><i :style="{height: dailyBarHeight(day.total)}"></i></span><b>{{ day.total }}</b><small>{{ day.date.slice(8) }}</small></div></div></section>
            <section class="panel"><div class="section-title"><div><p class="eyebrow">Top szolgáltatások</p><h2>Legkeresettebbek</h2></div></div><div v-if="!statistics.top_services?.length" class="empty compact">Ebben a hónapban még nincs adat.</div><div v-else class="statistics-top-list"><article v-for="service in statistics.top_services" :key="service.name"><div><strong>{{ service.name }}</strong><small>{{ service.bookings }} foglalás · {{ formatForint(service.revenue) }}</small></div><span><i :style="{width: topServiceWidth(service.bookings)}"></i></span></article></div></section>
          </div>
        </template>
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
                <img v-if="service.image_url" :src="service.image_thumbnail_url || service.image_url" :alt="service.name" loading="lazy" decoding="async" />
                <span v-else>{{ monogram(service.name) || '•' }}</span>
              </div>
              <div class="service-admin-copy">
                <div class="service-admin-heading"><strong>{{ service.name }}</strong><small>{{ service.category }} · {{ service.duration_minutes }} perc · {{ price(service) || 'Nincs ár' }}</small></div>
                <p>{{ service.description }}</p>
              </div>
            </div>
            <div class="service-actions"><button class="button sm" @click="moveService(service, -1)">↑</button><button class="button sm" @click="moveService(service, 1)">↓</button><button class="button sm" @click="editService(service)">Szerkesztés</button><button class="button sm" @click="toggleService(service)">{{ service.active ? 'Inaktiválás' : 'Aktiválás' }}</button></div>
          </article>
          <p v-if="!services.length" class="empty compact">Még nincs szolgáltatás.</p>
        </div>
      </section>

      <section v-if="activeTab === 'customers'" class="customers-admin-section">
        <section class="panel customers-list-panel">
          <div class="section-title customers-section-title">
            <div>
              <p class="eyebrow">Ügyféltörténet</p>
              <h2>Vendégek fiók nélkül is</h2>
              <p class="lead">Az előzmények e-mail alapján automatikusan összekapcsolódnak; telefonszám és belső megjegyzés itt kezelhető.</p>
            </div>
            <button v-if="selectedCustomer" class="button sm" type="button" @click="selectedCustomer = null; customerBookings = []">← Vissza a listához</button>
          </div>

          <template v-if="!selectedCustomer">
            <form class="customer-search-bar" @submit.prevent="loadCustomers">
              <input v-model.trim="customerSearch" type="search" placeholder="Keresés név, e-mail vagy telefonszám alapján" />
              <button class="button primary" type="submit">Keresés</button>
              <button v-if="customerSearch" class="button" type="button" @click="customerSearch = ''; loadCustomers()">Törlés</button>
            </form>
            <div v-if="customersLoading" class="empty">Ügyfelek betöltése…</div>
            <div v-else-if="!customers.length" class="empty">Még nincs ügyféltörténet.</div>
            <div v-else class="customer-admin-grid">
              <button v-for="customer in customers" :key="customer.id" class="customer-admin-card" type="button" @click="openCustomer(customer)">
                <span class="customer-avatar">{{ customer.name?.charAt(0)?.toLocaleUpperCase('hu-HU') }}</span>
                <span class="customer-card-main"><strong>{{ customer.name }}</strong><small>{{ customer.email }}</small><small v-if="customer.phone">{{ customer.phone }}</small><em v-if="customer.registered_account" class="registered-account-badge">Regisztrált fiók</em></span>
                <span class="customer-card-stats"><b>{{ customer.bookings_count }}</b> foglalás <em :class="{danger: customer.no_show_count > 0}">{{ customer.no_show_count }} Nem jelent meg</em></span>
              </button>
            </div>
          </template>

          <template v-else>
            <div class="customer-detail-hero">
              <span class="customer-avatar large">{{ selectedCustomer.name?.charAt(0)?.toLocaleUpperCase('hu-HU') }}</span>
              <div><h2>{{ selectedCustomer.name }}</h2><p>{{ selectedCustomer.email }}</p><em v-if="selectedCustomer.registered_account" class="registered-account-badge">Regisztrált fiók</em><span>{{ selectedCustomer.bookings_count }} foglalás · {{ selectedCustomer.no_show_count }} Nem jelent meg · {{ selectedCustomer.completed_count }} teljesítve</span></div>
              <button class="button primary" type="button" @click="rebookCustomer(selectedCustomer, customerBookings[0])">+ Újrafoglalás</button>
            </div>
            <div class="customer-detail-grid">
              <div>
                <p class="eyebrow">Korábbi bookingok</p>
                <div class="customer-booking-history">
                  <article v-for="booking in customerBookings" :key="booking.id">
                    <div><strong>{{ booking.service_name }}</strong><small>{{ booking.date }} · {{ shortTime(booking.start_time) }}–{{ shortTime(booking.end_time) }}</small></div>
                    <span class="badge" :class="booking.status">{{ statusLabel(booking.status) }}</span>
                    <button class="button sm" type="button" @click="openBookingModal(booking)">Részletek</button>
                  </article>
                  <p v-if="!customerBookings.length" class="empty compact">Nincs kapcsolódó foglalás.</p>
                </div>
              </div>
              <aside class="customer-note-card">
                <p class="eyebrow">Belső admin adat</p>
                <label>Telefonszám
                  <input v-model.trim="selectedCustomer.phone" maxlength="40" placeholder="+36…" />
                </label>
                <label>Belső megjegyzés
                  <textarea v-model.trim="selectedCustomer.admin_note" rows="8" maxlength="5000" placeholder="Csak az admin látja…"></textarea>
                </label>
                <button class="button primary block" type="button" :disabled="savingCustomer" @click="saveCustomerNote">{{ savingCustomer ? 'Mentés…' : 'Ügyféladat mentése' }}</button>
              </aside>
            </div>
          </template>
        </section>
      </section>

      <section v-if="activeTab === 'settings'" class="settings-admin-section">
        <div v-if="settingsLoading" class="panel empty">Beállítások betöltése…</div>

        <template v-else>
          <section class="panel settings-panel">
            <div class="section-title">
              <div>
                <p class="eyebrow">Foglalási szabályok</p>
                <h2>Mikor és hogyan lehessen foglalni?</h2>
                <p class="lead">Ezek a szabályok a nyilvános foglalásra és a kezelőlinkes módosításra is érvényesek.</p>
              </div>
            </div>

            <div class="settings-grid">
              <label>Minimum előfoglalási idő / perc
                <input v-model.number="adminSettings.min_advance_minutes" type="number" min="0" max="43200" />
                <small>Példa: 60 = legalább egy órával előre.</small>
              </label>
              <label>Maximum előrefoglalási idő / nap
                <input v-model.number="adminSettings.max_advance_days" type="number" min="1" max="730" />
                <small>Példa: 90 = három hónapra előre.</small>
              </label>
              <label>Időpontok lépésköze
                <select v-model.number="adminSettings.slot_interval_minutes">
                  <option :value="5">5 perc</option>
                  <option :value="10">10 perc</option>
                  <option :value="15">15 perc</option>
                  <option :value="20">20 perc</option>
                  <option :value="30">30 perc</option>
                  <option :value="60">60 perc</option>
                </select>
                <small>Ez határozza meg, milyen sűrűn indulhat új időpont.</small>
              </label>
              <label>Vállalkozás időzónája
                <select v-model="adminSettings.timezone">
                  <option v-for="timezone in settingsTimezones" :key="timezone" :value="timezone">{{ timezone }}</option>
                </select>
                <small>A „mai nap”, határidők és e-mailek ezt használják.</small>
              </label>
              <label>Lemondási határidő / perc
                <input v-model.number="adminSettings.cancellation_deadline_minutes" type="number" min="0" max="43200" />
                <small>1440 perc = 24 óra. Nulla esetén a kezdésig lemondható.</small>
              </label>
              <label>Módosítási határidő / perc
                <input v-model.number="adminSettings.reschedule_deadline_minutes" type="number" min="0" max="43200" />
                <small>Ezután a vendégnek közvetlenül kell kapcsolatba lépnie.</small>
              </label>
            </div>
          </section>

          <section class="panel settings-panel">
            <div class="section-title">
              <div>
                <p class="eyebrow">Automatikus emlékeztetők</p>
                <h2>Mikor kapjon értesítést a vendég?</h2>
                <p class="lead">A scheduler percenként ellenőrzi az esedékes időpontokat. Lemondott foglaláshoz nem küld levelet.</p>
              </div>
            </div>
            <div class="reminder-settings-grid">
              <label class="checkline settings-switch">
                <input v-model="adminSettings.reminder_24h_enabled" type="checkbox" />
                <span><strong>24 órás emlékeztető</strong><small>Alapértelmezetten bekapcsolva.</small></span>
              </label>
              <label class="checkline settings-switch">
                <input v-model="adminSettings.reminder_2h_enabled" type="checkbox" />
                <span><strong>2 órás emlékeztető</strong><small>Opcionális, külön kapcsolható.</small></span>
              </label>
            </div>
            <div class="notice reminder-worker-note">
              Fejlesztéskor indítsd a <code>backend\scripts\start-workers.bat</code> fájlt. Ez egyszerre elindítja a queue és scheduler workert.
            </div>
          </section>

          <section class="panel settings-panel">
            <div class="section-title">
              <div>
                <p class="eyebrow">Árkezelés</p>
                <h2>Globális ármegjelenítés</h2>
              </div>
            </div>
            <label class="checkline settings-switch">
              <input v-model="adminSettings.hide_prices" type="checkbox" />
              Minden szolgáltatás árának elrejtése a nyilvános oldalon
            </label>
          </section>

          <section class="panel settings-panel">
            <div class="section-title">
              <div>
                <p class="eyebrow">Adatmegőrzés</p>
                <h2>Meddig tárolja a rendszer az adatokat?</h2>
                <p class="lead">A napi automatikus takarítás ezeket a határidőket alkalmazza.</p>
              </div>
            </div>
            <div class="settings-grid three">
              <label>Foglalási adatok / nap
                <input v-model.number="adminSettings.booking_retention_days" type="number" min="1" max="3650" />
                <small>A lejárt foglalások személyes adatai ezután anonimizálódnak.</small>
              </label>
              <label>Email napló / nap
                <input v-model.number="adminSettings.email_log_retention_days" type="number" min="1" max="3650" />
                <small>A régebbi email naplóbejegyzések törlődnek.</small>
              </label>
              <label>Kezelőlink / nap
                <input v-model.number="adminSettings.manage_token_retention_days" type="number" min="1" max="3650" />
                <small>A foglalás napja után ennyi ideig marad használható.</small>
              </label>
            </div>
          </section>

          <section class="panel settings-panel">
            <div class="section-title">
              <div>
                <p class="eyebrow">Jogi minimum</p>
                <h2>Publikus dokumentumok</h2>
                <p class="lead">A szövegek külön publikus oldalon jelennek meg. Éles használat előtt jogásszal vagy adatvédelmi szakértővel ellenőriztesd.</p>
              </div>
              <div class="inline-actions">
                <a class="button sm" href="<?= route_url('privacy') ?>" target="_blank" rel="noopener">Adatkezelés megnyitása</a>
                <a class="button sm" href="<?= route_url('terms') ?>" target="_blank" rel="noopener">Feltételek</a>
                <a class="button sm" href="<?= route_url('imprint') ?>" target="_blank" rel="noopener">Impresszum</a>
                <a class="button sm" href="<?= route_url('cookies') ?>" target="_blank" rel="noopener">Süti tájékoztató</a>
              </div>
            </div>
            <div class="legal-editor-stack">
              <section v-for="document in legalEditorDocuments" :key="document.field" class="legal-rich-field">
                <strong>{{ document.label }}</strong>
                <div class="legal-editor-toolbar" role="toolbar" :aria-label="document.label + ' formázása'">
                  <button type="button" title="Normál bekezdés" @mousedown.prevent="applyLegalCommand(document.field, 'formatBlock', 'p')">Bekezdés</button>
                  <button type="button" title="Címsor" @mousedown.prevent="applyLegalCommand(document.field, 'formatBlock', 'h2')">Címsor</button>
                  <span aria-hidden="true"></span>
                  <button type="button" title="Kisebb betűméret" @mousedown.prevent="applyLegalCommand(document.field, 'fontSize', '2')">A−</button>
                  <button type="button" title="Normál betűméret" @mousedown.prevent="applyLegalCommand(document.field, 'fontSize', '3')">A</button>
                  <button type="button" title="Nagyobb betűméret" @mousedown.prevent="applyLegalCommand(document.field, 'fontSize', '5')">A+</button>
                  <span aria-hidden="true"></span>
                  <button type="button" title="Félkövér" @mousedown.prevent="applyLegalCommand(document.field, 'bold')"><b>F</b></button>
                  <button type="button" title="Dőlt" @mousedown.prevent="applyLegalCommand(document.field, 'italic')"><i>D</i></button>
                  <button type="button" title="Aláhúzott" @mousedown.prevent="applyLegalCommand(document.field, 'underline')"><u>A</u></button>
                  <button type="button" title="Felsorolás" @mousedown.prevent="applyLegalCommand(document.field, 'insertUnorderedList')">• Lista</button>
                </div>
                <div
                  :ref="'legalEditor_' + document.field"
                  class="legal-rich-editor"
                  contenteditable="true"
                  role="textbox"
                  aria-multiline="true"
                  :data-placeholder="document.placeholder"
                  @input="legalEditorInput(document.field, $event)"
                  @focus="rememberLegalSelection(document.field)"
                  @keyup="rememberLegalSelection(document.field)"
                  @mouseup="rememberLegalSelection(document.field)"
                ></div>
              </section>
            </div>
          </section>

          <div class="settings-save-bar">
            <button class="button primary" type="button" :disabled="savingAdminSettings" @click="saveAdminSettings">
              <span v-if="savingAdminSettings" class="spinner"></span>{{ savingAdminSettings ? 'Mentés…' : 'Beállítások mentése' }}
            </button>
          </div>
        </template>
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
          <article class="email-stat-card pending">
            <span>Várólistán</span>
            <strong>{{ emailStats.pending ?? '–' }}</strong>
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

        <section class="panel reminder-log-panel">
          <div class="section-title email-section-title">
            <div>
              <p class="eyebrow">Emlékeztető napló</p>
              <h2>24 és 2 órás értesítések</h2>
              <p class="lead email-lead">Egy foglalás ugyanabból a típusból csak egy emlékeztetőt kaphat. A lemondott foglalások kiküldés előtt újra ellenőrződnek.</p>
            </div>
            <button class="button sm" type="button" :disabled="remindersLoading" @click="dispatchRemindersNow">{{ remindersLoading ? 'Ellenőrzés…' : 'Esedékesek ellenőrzése' }}</button>
          </div>
          <div class="reminder-mini-stats">
            <span><b>{{ reminderStats.queued || 0 }}</b> várólistán</span>
            <span><b>{{ reminderStats.sent || 0 }}</b> elküldve</span>
            <span><b>{{ reminderStats.skipped || 0 }}</b> kihagyva</span>
            <span><b>{{ reminderStats.failed || 0 }}</b> sikertelen</span>
          </div>
          <div v-if="!reminderLogs.length" class="empty compact">Még nincs emlékeztető naplóbejegyzés.</div>
          <div v-else class="reminder-log-list">
            <article v-for="log in reminderLogs" :key="log.id">
              <div><strong>{{ log.booking?.customer_name || 'Törölt ügyfél' }}</strong><small>{{ log.booking?.service_name }} · {{ log.booking?.date }} {{ shortTime(log.booking?.start_time) }}</small></div>
              <span class="reminder-type-pill">{{ log.reminder_type === '2h' ? '2 órás' : '24 órás' }}</span>
              <span class="email-status-badge" :class="log.status">{{ reminderStatusLabel(log.status) }}</span>
              <small v-if="log.error_message" class="reminder-log-error">{{ log.error_message }}</small>
            </article>
          </div>
        </section>

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
            <span><b>Queue:</b> {{ emailSystem.queue_connection || '–' }}</span>
            <span><b>Várakozó jobok:</b> {{ emailSystem.pending_jobs ?? '–' }}</span>
            <span><b>Sikertelen jobok:</b> {{ emailSystem.failed_jobs ?? '–' }}</span>
            <span><b>Technikai feladó:</b> {{ emailSystem.from_address || '–' }}</span>
            <span><b>Utolsó sikeres:</b> {{ formatDateTime(emailStats.last_sent_at) }}</span>
          </div>
          <div v-if="emailSystem.worker_warning" class="notice email-worker-warning">
            <strong>A queue várólistán vannak levelek.</strong>
            Indítsd el vagy ellenőrizd az email queue workert. Legrégebbi várakozó job: {{ formatDateTime(emailSystem.oldest_pending_at) }}.
          </div>
          <div v-if="Number(emailStats.failed || 0) > 0" class="notice email-failure-warning">
            <strong>{{ emailStats.failed }} sikertelen email található.</strong>
            Szűrj „Sikertelen” státuszra, nézd meg a hiba részleteit, majd használd az újraküldést.
          </div>

          <div class="email-filter-bar">
            <input v-model.trim="emailFilters.q" type="search" placeholder="Keresés címzett / tárgy / vendég / szolgáltatás" @keyup.enter="loadEmailLogs({ resetPage: true })" />
            <select v-model="emailFilters.status" @change="loadEmailLogs({ resetPage: true })">
              <option value="">Minden státusz</option>
              <option value="pending">Várólistán</option>
              <option value="sent">Sikeres</option>
              <option value="failed">Sikertelen</option>
              <option value="skipped">Kihagyva</option>
            </select>
            <select v-model="emailFilters.event_type" @change="loadEmailLogs">
              <option value="">Minden esemény</option>
              <option value="booking_created">Új foglalás</option>
              <option value="booking_rescheduled">Módosítás</option>
              <option value="booking_cancelled">Lemondás</option>
              <option value="email_test">Teszt email</option>
              <option value="booking_reminder_24h">24 órás emlékeztető</option>
              <option value="booking_reminder_2h">2 órás emlékeztető</option>
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
                  <td class="email-log-date" data-label="Időpont">{{ formatDateTime(log.sent_at || log.created_at) }}</td>
                  <td data-label="Címzett"><strong>{{ log.recipient_email }}</strong><small v-if="log.booking">{{ log.booking.customer_name }} · {{ log.booking.service_name }}</small></td>
                  <td data-label="Típus"><span class="email-recipient-pill" :class="log.recipient_type">{{ emailRecipientLabel(log.recipient_type) }}</span></td>
                  <td data-label="Esemény">{{ emailEventLabel(log.event_type) }}</td>
                  <td class="email-subject-cell" data-label="Tárgy">{{ log.subject }}</td>
                  <td data-label="Státusz"><span class="email-status-badge" :class="log.status">{{ emailStatusLabel(log.status) }}</span></td>
                  <td data-label="Műveletek">
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
                <option value="booking_reminder_24h">24 órás emlékeztető</option>
                <option value="booking_reminder_2h">2 órás emlékeztető</option>
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
              <button type="button" :class="{active: emailEditorEvent === 'booking_reminder_24h'}" @click="emailEditorEvent = 'booking_reminder_24h'">24 órás emlékeztető</button>
              <button type="button" :class="{active: emailEditorEvent === 'booking_reminder_2h'}" @click="emailEditorEvent = 'booking_reminder_2h'">2 órás emlékeztető</button>
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
              <img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" />
              <template v-else>{{ business.logoText || monogram(websiteForm.name) || 'IP' }}</template>
            </div>
            <div>
              <strong>Logó</strong>
              <p>JPG, PNG vagy WebP, legfeljebb 3 MB. A rendszer optimalizált WebP főképet és thumbnailt készít; kép nélkül monogram jelenik meg.</p>
              <div class="inline-actions media-action-row">
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
            <div class="section-title"><div><p class="eyebrow">Vélemények</p><h2>Beküldött és kézi visszajelzések</h2><p class="lead content-review-lead">A vendégek véleményei jóváhagyásig rejtve maradnak. Az „Új vélemény” gombbal továbbra is kézzel írhatsz be visszajelzést.</p></div><button class="button sm" type="button" @click="openReviewModal()">Új vélemény</button></div>
            <div class="content-admin-list">
              <article v-for="review in reviews" :key="review.id" class="content-admin-card" :class="{inactive: !review.active}">
                <div>
                  <div class="review-admin-meta">
                    <strong>{{ review.author }}</strong>
                    <span class="review-source-badge" :class="review.source || 'manual'">{{ review.source === 'customer' ? 'Vendégtől' : 'Kézi' }}</span>
                    <span class="review-moderation-badge" :class="review.moderation_status || 'approved'">{{ reviewModerationLabel(review.moderation_status) }}</span>
                  </div>
                  <span class="stars-admin">{{ '★'.repeat(review.rating) }}</span>
                  <p>{{ review.text }}</p>
                  <small v-if="review.submitter_email" class="review-private-email">Kapcsolati e-mail (csak admin): {{ review.submitter_email }}</small>
                </div>
                <div class="service-actions review-admin-actions">
                  <button v-if="review.moderation_status !== 'approved' || !review.active" class="button sm primary" type="button" @click="approveReview(review)">Jóváhagyás és megjelenítés</button>
                  <button v-if="review.active" class="button sm" type="button" @click="hideReview(review)">Elrejtés</button>
                  <button v-if="review.source === 'customer' && review.moderation_status !== 'rejected'" class="button sm" type="button" @click="rejectReview(review)">Elutasítás</button>
                  <button class="button sm" type="button" @click="editReview(review)">Szerkesztés</button>
                  <button class="button sm danger" type="button" @click="deleteReview(review)">Törlés</button>
                </div>
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
      <div v-if="confirmDialog.open" class="modal-backdrop confirm-modal-backdrop" @click.self="closeConfirmDialog(false)">
        <section class="modal-dialog confirm-modal" tabindex="-1" role="alertdialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage">
          <div class="confirm-icon" :class="{ danger: confirmDialog.danger }">{{ confirmDialog.danger ? '!' : '?' }}</div>
          <p class="eyebrow">Megerősítés szükséges</p>
          <h2 id="confirmModalTitle">{{ confirmDialog.title }}</h2>
          <p id="confirmModalMessage" class="lead">{{ confirmDialog.message }}</p>
          <ul v-if="confirmDialog.details.length" class="confirm-detail-list">
            <li v-for="(detail, index) in confirmDialog.details" :key="index">{{ detail }}</li>
          </ul>
          <div class="modal-actions">
            <button class="button" type="button" @click="closeConfirmDialog(false)">{{ confirmDialog.cancelLabel }}</button>
            <button ref="confirmPrimaryButton" class="button" :class="confirmDialog.danger ? 'danger' : 'primary'" type="button" @click="closeConfirmDialog(true)">{{ confirmDialog.confirmLabel }}</button>
          </div>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="emailLogModalOpen && selectedEmailLog" class="modal-backdrop" @click.self="closeEmailLogModal">
        <section class="modal-dialog email-log-detail-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="emailLogModalTitle">
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
            <div><dt>Próbálkozások</dt><dd>{{ selectedEmailLog.attempt_count ?? 0 }}</dd></div>
            <div><dt>Utolsó próbálkozás</dt><dd>{{ formatDateTime(selectedEmailLog.last_attempt_at) }}</dd></div>
            <div v-if="selectedEmailLog.resent_from_id" class="full"><dt>Újraküldés forrása</dt><dd>#{{ selectedEmailLog.resent_from_id }} naplóbejegyzés</dd></div>
          </dl>

          <div v-if="selectedEmailLog.status === 'pending'" class="notice email-pending-box">
            Az email várólistán van. A queue worker automatikusan feldolgozza és hiba esetén a beállított backoff szerint újrapróbálja.
          </div>

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
        <section class="modal-dialog booking-detail-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle">
          <div class="modal-head">
            <div><p class="eyebrow">Foglalás kezelése</p><h2 id="bookingModalTitle">{{ selectedBooking.customer_name }}</h2></div>
            <button class="modal-close" type="button" aria-label="Bezárás" @click="closeBookingModal">×</button>
          </div>

          <div class="booking-detail-hero" :class="selectedBooking.status">
            <div><span class="detail-label">Időpont</span><strong>{{ formatDateLong(selectedBooking.date) }}</strong><b>{{ shortTime(selectedBooking.start_time) }}–{{ shortTime(selectedBooking.end_time) }}</b></div>
            <span class="badge" :class="selectedBooking.status">{{ statusLabel(selectedBooking.status) }}</span>
          </div>

          <dl class="booking-detail-grid">
            <div><dt>Vendég</dt><dd>{{ selectedBooking.customer_name }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ selectedBooking.customer_contact }}</dd></div>
            <div><dt>Rögzítés ideje</dt><dd>{{ formatBookingCreatedAt(selectedBooking.created_at) }}</dd></div>
            <div><dt>Foglalt idő</dt><dd>{{ shortTime(selectedBooking.start_time) }}–{{ shortTime(selectedBooking.end_time) }}</dd></div>
            <div class="full" v-if="selectedBooking.customer_phone"><dt>Telefonszám</dt><dd>{{ selectedBooking.customer_phone }}</dd></div>
            <div class="full"><dt>Szolgáltatás</dt><dd>{{ selectedBooking.service_name }}</dd></div>
            <div class="full"><dt>Megjegyzés</dt><dd>{{ selectedBooking.customer_note || 'Nincs megjegyzés.' }}</dd></div>
          </dl>

          <div class="modal-actions booking-status-actions">
            <button class="button primary" type="button" @click="rebookBooking(selectedBooking)">+ Újrafoglalás</button>
            <template v-if="selectedBooking.status === 'booked'">
              <button class="button" type="button" @click="setStatus(selectedBooking, 'completed')">Teljesítve</button>
              <button class="button" type="button" @click="setStatus(selectedBooking, 'no_show')">Nem jött el</button>
              <button class="button danger" type="button" @click="setStatus(selectedBooking, 'cancelled')">Lemondás</button>
            </template>
            <button v-else class="button primary" type="button" @click="setStatus(selectedBooking, 'booked')">Visszaállítás aktívra</button>
            <button class="button" type="button" @click="copyManageLink(selectedBooking)">Kezelő link másolása</button>
            <button v-if="!selectedBooking.anonymized_at" class="button danger" type="button" @click="anonymizeBooking(selectedBooking)">Személyes adatok törlése</button>
          </div>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="manualModalOpen" class="modal-backdrop" @click.self="closeManualModal">
        <section class="modal-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="manualModalTitle">
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
            <label>Telefonszám <small>(nem kötelező)</small>
              <input v-model.trim="manual.customer_phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+36 30 123 4567" />
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
        <section class="modal-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
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
                <p>JPG, PNG vagy WebP, legfeljebb 5 MB. Mentéskor automatikusan átméretezzük, WebP-re alakítjuk és előnézeti képet készítünk.</p>
                <div class="inline-actions media-action-row">
                  <label class="button sm file-button">Kép kiválasztása<input type="file" accept="image/jpeg,image/png,image/webp" @change="onServiceImageSelected" /></label>
                  <button v-if="serviceImagePreview" class="button sm danger" type="button" :disabled="uploadingServiceImage" @click="deleteServiceImage">Kép törlése</button>
                </div>
              </div>
            </div>

            <div class="two-cols"><label>Időtartam / perc <input v-model.number="serviceForm.duration_minutes" type="number" min="5" required /></label><label>Puffer / perc <input v-model.number="serviceForm.buffer_minutes" type="number" min="0" /></label></div>
            <div class="two-cols">
              <label>Ár megjelenítése
                <select v-model="serviceForm.price_mode">
                  <option value="fixed">Fix ár</option>
                  <option value="consultation">Ár egyeztetés alapján</option>
                  <option value="hidden">Ár elrejtése</option>
                </select>
              </label>
              <label>Ár / Ft
                <input v-model.number="serviceForm.price_forint" type="number" min="0" placeholder="pl. 12000" :disabled="serviceForm.price_mode !== 'fixed'" />
              </label>
            </div>
            <label>Sorrend <input v-model.number="serviceForm.sort_order" type="number" min="0" /></label>
            <label class="checkline"><input v-model="serviceForm.active" type="checkbox" /> Aktív, foglalható szolgáltatás</label>
            <div class="modal-actions"><button class="button" type="button" @click="closeServiceModal">Mégse</button><button class="button primary" :disabled="savingService || uploadingServiceImage">{{ savingService ? 'Mentés…' : 'Szolgáltatás mentése' }}</button></div>
          </form>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="reviewModalOpen" class="modal-backdrop" @click.self="closeReviewModal">
        <section class="modal-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
          <div class="modal-head">
            <div><p class="eyebrow">Vélemények</p><h2 id="reviewModalTitle">{{ reviewForm.id ? 'Vélemény szerkesztése' : 'Új vélemény' }}</h2></div>
            <button class="modal-close" type="button" aria-label="Bezárás" @click="closeReviewModal">×</button>
          </div>
          <form class="modal-form" @submit.prevent="saveReview">
            <div class="two-cols review-primary-row">
              <label>Név <input ref="reviewAuthorInput" v-model.trim="reviewForm.author" required maxlength="120" /></label>
              <fieldset class="admin-star-rating-field">
                <legend>Értékelés</legend>
                <div class="admin-star-rating" role="radiogroup" aria-label="Értékelés csillagokkal">
                  <label v-for="star in 5" :key="star" :class="{ selected: star <= reviewForm.rating }">
                    <input v-model.number="reviewForm.rating" class="sr-only" type="radio" name="admin-review-rating" :value="star" required />
                    <span aria-hidden="true">★</span>
                    <span class="sr-only">{{ star }} csillag</span>
                  </label>
                </div>
              </fieldset>
            </div>
            <label>Szöveg <textarea v-model.trim="reviewForm.text" rows="5" required maxlength="1200"></textarea></label>
            <div class="two-cols"><label>Sorrend <input v-model.number="reviewForm.sort_order" type="number" min="0" max="1000" /></label><label class="checkline"><input v-model="reviewForm.active" type="checkbox" /> Megjelenik a weboldalon</label></div>
            <div class="modal-actions"><button class="button" type="button" @click="closeReviewModal">Mégse</button><button class="button primary" :disabled="savingReview">{{ savingReview ? 'Mentés…' : 'Vélemény mentése' }}</button></div>
          </form>
        </section>
      </div>
    </transition>

    <transition name="modal-pop">
      <div v-if="faqModalOpen" class="modal-backdrop" @click.self="closeFaqModal">
        <section class="modal-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="faqModalTitle">
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
