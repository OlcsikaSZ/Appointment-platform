<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" /><title>Fiókom — Időpontfoglalás</title>
  <link id="business-favicon" rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>" />
  <link rel="stylesheet" href="<?= asset('assets/styles.css') ?>" /><link rel="stylesheet" href="<?= view_asset('styles.css') ?>" />
</head>
<body>
<a class="skip-link" href="#main-content">Ugrás a tartalomhoz</a>
<div id="accountApp" data-main-url="<?= htmlspecialchars(route_url('main'), ENT_QUOTES, 'UTF-8') ?>" v-cloak>
  <div class="toast-stack" aria-live="polite" aria-atomic="false"><div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" :role="toast.kind === 'error' ? 'alert' : 'status'" @click="toasts.dismiss(toast.id)">{{ toast.message }}</div></div>
  <header class="topbar">
    <a class="brand" href="<?= route_url('main') ?>"><span class="brand-mark"><img v-if="business.logoUrl" :src="business.logoThumbnailUrl || business.logoUrl" :alt="business.name ? business.name + ' logó' : 'Vállalkozás logó'" /><template v-else>{{ business.logoText || monogram(business.name) || 'IP' }}</template></span><span><strong>{{ business.name || 'Időpontfoglalás' }}</strong><small>Ügyfélfiók</small></span></a>
    <nav><a href="<?= route_url('main') ?>">Foglalási oldal</a><a v-if="token" href="#" @click.prevent="logout">Kijelentkezés</a></nav>
  </header>

  <main id="main-content" class="shell account-shell" tabindex="-1">
    <section v-if="loading" class="panel account-state-card"><span class="spinner"></span><p>Fiók betöltése…</p></section>

    <section v-else-if="!token" class="account-auth-grid">
      <div class="panel account-intro-card">
        <p class="eyebrow">Opcionális ügyfélfiók</p><h1>A foglalásaid egy helyen</h1>
        <p class="lead">Jelszóval és e-mailes megerősítéssel. A fiók nem kötelező: továbbra is foglalhatsz vendégként.</p>
        <ul class="account-benefits"><li>Aktuális és korábbi foglalások</li><li>Közvetlen foglaláskezelés</li><li>Saját profil, munkamenetek és adattörlés</li></ul>
      </div>
      <div class="panel account-login-card">
        <div v-if="authMode === 'verify'" class="account-code-box">
          <button class="text-button" type="button" @click="authMode = 'register'">← Vissza</button>
          <p class="eyebrow">E-mail megerősítés</p><h2>Add meg a hatjegyű kódot</h2><p>A kódot erre a címre küldtük: <strong>{{ pendingEmail }}</strong></p>
          <form @submit.prevent="verifyRegistration">
            <fieldset class="verification-code-field">
              <legend>Ellenőrző kód</legend>
              <div class="verification-code-inputs" role="group" aria-label="Hatjegyű regisztrációs ellenőrző kód">
                <input v-for="(digit, index) in verificationDigits" :key="'registration-' + index" :value="digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" :autocomplete="index === 0 ? 'one-time-code' : 'off'" :aria-label="(index + 1) + '. számjegy a hatból'" data-code-group="registration" :data-code-index="index" @input="handleCodeInput('registration', index, $event)" @keydown="handleCodeKeydown('registration', index, $event)" @paste="handleCodePaste('registration', index, $event)" />
              </div>
              <small>Beírhatod egyenként, vagy beillesztheted egyszerre mind a hat számjegyet.</small>
            </fieldset>
            <button class="button primary block auth-submit" :disabled="busy">{{ busy ? 'Ellenőrzés…' : 'Regisztráció befejezése' }}</button>
          </form>
        </div>
        <div v-else-if="authMode === 'forgot' || authMode === 'reset'" class="account-code-box">
          <button class="text-button" type="button" @click="authMode = 'login'">← Vissza a belépéshez</button>
          <p class="eyebrow">Jelszó helyreállítása</p><h2>{{ authMode === 'forgot' ? 'Kérj ellenőrző kódot' : 'Állíts be új jelszót' }}</h2>
          <form v-if="authMode === 'forgot'" @submit.prevent="forgotPassword"><label>E-mail cím<input v-model.trim="loginForm.email" type="email" required autocomplete="email" /></label><button class="button primary block auth-submit" :disabled="busy">{{ busy ? 'Küldés…' : 'Kód küldése' }}</button></form>
          <form v-else @submit.prevent="resetPassword">
            <label>E-mail cím<input v-model.trim="resetForm.email" type="email" required autocomplete="email" /></label>
            <fieldset class="verification-code-field">
              <legend>Ellenőrző kód</legend>
              <div class="verification-code-inputs" role="group" aria-label="Hatjegyű jelszó-visszaállító kód">
                <input v-for="(digit, index) in resetDigits" :key="'reset-' + index" :value="digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" :autocomplete="index === 0 ? 'one-time-code' : 'off'" :aria-label="(index + 1) + '. számjegy a hatból'" data-code-group="reset" :data-code-index="index" @input="handleCodeInput('reset', index, $event)" @keydown="handleCodeKeydown('reset', index, $event)" @paste="handleCodePaste('reset', index, $event)" />
              </div>
              <small>Beírhatod egyenként, vagy beillesztheted egyszerre mind a hat számjegyet.</small>
            </fieldset>
            <label>Új jelszó<password-input v-model="resetForm.password" required :minlength="8" autocomplete="new-password"></password-input></label><label>Új jelszó ismét<password-input v-model="resetForm.password_confirmation" required autocomplete="new-password"></password-input></label><button class="button primary block auth-submit" :disabled="busy">{{ busy ? 'Mentés…' : 'Új jelszó mentése' }}</button>
          </form>
        </div>
        <template v-else>
          <div class="account-mode-switch" role="tablist"><button type="button" role="tab" :aria-selected="authMode === 'login'" :class="{active: authMode === 'login'}" @click="authMode = 'login'">Belépés</button><button type="button" role="tab" :aria-selected="authMode === 'register'" :class="{active: authMode === 'register'}" @click="authMode = 'register'">Regisztráció</button></div>
          <form v-if="authMode === 'login'" @submit.prevent="login"><p class="eyebrow">Ügyfélfiók</p><h2>Jelentkezz be</h2><label>E-mail cím<input v-model.trim="loginForm.email" type="email" required autocomplete="email" /></label><label>Jelszó<password-input v-model="loginForm.password" required autocomplete="current-password"></password-input></label><button class="text-button forgot-link" type="button" @click="authMode = 'forgot'">Elfelejtetted a jelszavad?</button><button class="button primary block auth-submit" :disabled="busy">{{ busy ? 'Belépés…' : 'Bejelentkezés' }}</button></form>
          <form v-else @submit.prevent="register"><p class="eyebrow">Regisztráció</p><h2>Hozd létre a fiókodat</h2><label>Név<input v-model.trim="registerForm.name" required maxlength="120" autocomplete="name" /></label><label>E-mail cím<input v-model.trim="registerForm.email" type="email" required maxlength="160" autocomplete="email" /></label><label>Telefonszám <small>opcionális</small><input v-model.trim="registerForm.phone" maxlength="40" autocomplete="tel" /></label><label>Jelszó <small>min. 8 karakter, betű és szám</small><password-input v-model="registerForm.password" required :minlength="8" autocomplete="new-password"></password-input></label><label>Jelszó ismét<password-input v-model="registerForm.password_confirmation" required autocomplete="new-password"></password-input></label><button class="button primary block auth-submit" :disabled="busy">{{ busy ? 'Kód küldése…' : 'Regisztráció és kód küldése' }}</button></form>
        </template>
      </div>
    </section>

    <template v-else>
      <div class="account-headline"><div><p class="eyebrow">Fiókom</p><h1>Szia, {{ account.name }}!</h1><p class="lead">{{ account.email }}</p></div><a class="button primary" href="<?= route_url('main') ?>#foglalas">+ Új foglalás</a></div>
      <div class="account-view-tabs" role="tablist"><button type="button" role="tab" :aria-selected="accountView === 'bookings'" :class="{active: accountView === 'bookings'}" @click="accountView = 'bookings'">Foglalásaim</button><button type="button" role="tab" :aria-selected="accountView === 'profile'" :class="{active: accountView === 'profile'}" @click="accountView = 'profile'">Személyes adatok és biztonság</button></div>

      <section v-if="accountView === 'bookings'" class="panel account-bookings-panel">
        <div class="section-title"><div><p class="eyebrow">Foglalásaim</p><h2>Közelgő időpontok</h2></div></div>
        <div v-if="!upcomingBookings.length" class="empty">Nincs közelgő aktív foglalásod.</div>
        <div v-else class="account-booking-list"><article v-for="booking in upcomingBookings" :key="booking.id" class="account-booking-card"><div><span class="badge booked">Foglalva</span><h3>{{ booking.service_name }}</h3><p>{{ formatDateLong(booking.date) }} · {{ booking.start_time }}–{{ booking.end_time }}</p></div><a v-if="booking.manage_url" class="button sm primary" :href="manageFromAccount(booking.manage_url)">Foglalás kezelése</a></article></div>
        <div class="account-history-head"><p class="eyebrow">Előzmények</p><h2>Korábbi foglalások</h2></div><div v-if="!pastBookings.length" class="empty compact">Még nincs korábbi foglalásod.</div><div v-else class="account-history-list"><article v-for="booking in pastBookings" :key="booking.id"><div><strong>{{ booking.service_name }}</strong><small>{{ formatDateLong(booking.date) }} · {{ booking.start_time }}</small></div><span class="badge" :class="booking.status">{{ statusLabel(booking.status) }}</span></article></div>
      </section>

      <section v-else class="account-settings-grid">
        <div class="panel account-profile-panel"><p class="eyebrow">Profil</p><h2>Személyes adatok</h2><form @submit.prevent="saveProfile"><label>Név<input v-model.trim="profile.name" required maxlength="120" autocomplete="name" /></label><label>E-mail cím<input :value="account.email" disabled /></label><label>Telefonszám<input v-model.trim="profile.phone" maxlength="40" autocomplete="tel" /></label><button class="button primary block auth-submit" :disabled="savingProfile">{{ savingProfile ? 'Mentés…' : 'Profil mentése' }}</button></form></div>
        <div class="panel account-security-panel"><p class="eyebrow">Biztonság</p><h2>Jelszó és munkamenetek</h2><form @submit.prevent="changePassword"><label>Jelenlegi jelszó<password-input v-model="passwordForm.current_password" required autocomplete="current-password"></password-input></label><label>Új jelszó<password-input v-model="passwordForm.password" required :minlength="8" autocomplete="new-password"></password-input></label><label>Új jelszó ismét<password-input v-model="passwordForm.password_confirmation" required autocomplete="new-password"></password-input></label><button class="button block auth-submit" :disabled="changingPassword">{{ changingPassword ? 'Mentés…' : 'Jelszó módosítása' }}</button></form><div class="session-list"><h3>Aktív munkamenetek</h3><article v-for="session in sessions" :key="session.id"><div><strong>{{ session.current ? 'Ez az eszköz' : 'Bejelentkezett eszköz' }}</strong><small>Utolsó használat: {{ formatDateTime(session.last_used_at || session.created_at) }}</small></div><button v-if="!session.current" class="button sm" type="button" @click="revokeSession(session.id)">Kijelentkeztetés</button></article><button v-if="sessions.length > 1" class="text-button" type="button" @click="logoutAll">Kijelentkezés minden eszközről</button></div></div>
        <div class="panel account-danger-zone"><strong>Fiók és adatok törlése</strong><p>Az aktív foglalásokat előbb le kell mondanod. A törlés nem vonható vissza.</p><button class="button danger" type="button" :disabled="deletingAccount" @click="openDeleteDialog">Fiókom törlése</button></div>
      </section>
    </template>
  </main>
  <footer class="account-footer"><a href="<?= route_url('privacy') ?>">Adatkezelés</a><a href="<?= route_url('terms') ?>">Felhasználási feltételek</a><a href="<?= route_url('main') ?>">Vissza a foglalási oldalra</a></footer>

  <transition name="account-modal-pop">
    <div v-if="deleteDialogOpen" class="account-modal-backdrop" @click.self="closeDeleteDialog">
      <section
        ref="deleteDialog"
        class="account-delete-modal"
        tabindex="-1"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="deleteAccountModalTitle"
        aria-describedby="deleteAccountModalDescription"
        :aria-busy="deletingAccount ? 'true' : 'false'"
      >
        <header class="account-delete-modal-head">
          <span class="account-delete-modal-icon" aria-hidden="true">!</span>
          <div>
            <p class="eyebrow">Végleges művelet</p>
            <h2 id="deleteAccountModalTitle">Biztosan törlöd a fiókodat?</h2>
          </div>
          <button class="account-modal-close" type="button" aria-label="A törlési ablak bezárása" :disabled="deletingAccount" @click="closeDeleteDialog">×</button>
        </header>
        <div id="deleteAccountModalDescription" class="account-delete-modal-content">
          <p>A fiókod és a hozzá kapcsolódó személyes adatok véglegesen törlődnek. Ezt később nem lehet visszavonni.</p>
          <ul>
            <li>Az aktív foglalásokat előbb le kell mondanod.</li>
            <li>A korábbi foglalásokban szereplő személyes adataid anonimizáljuk.</li>
            <li>Minden ügyfélfiókos munkameneted megszűnik.</li>
          </ul>
        </div>
        <footer class="account-delete-modal-actions">
          <button ref="deleteCancelButton" class="button" type="button" :disabled="deletingAccount" @click="closeDeleteDialog">Mégsem, megtartom</button>
          <button class="button danger" type="button" :disabled="deletingAccount" @click="deleteAccount">{{ deletingAccount ? 'Fiók törlése…' : 'Igen, véglegesen törlöm' }}</button>
        </footer>
      </section>
    </div>
  </transition>
</div>
<script src="<?= asset('assets/config.js') ?>"></script><script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script><script src="<?= asset('assets/shared.js') ?>"></script><script src="<?= view_asset('index.js') ?>"></script>
</body></html>
