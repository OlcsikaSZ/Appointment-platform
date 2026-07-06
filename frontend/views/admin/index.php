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
      <div v-for="toast in toasts.list" :key="toast.id" class="toast" :class="toast.kind" @click="toasts.dismiss(toast.id)">
        {{ toast.message }}
      </div>
    </div>

    <header class="topbar">
      <a class="brand" href="<?= route_url('main') ?>">
        <span class="brand-mark">{{ business.logoText || '·' }}</span>
        <span>
          <strong>{{ business.name || 'Időpontfoglalás' }}</strong>
          <small>Admin felület</small>
        </span>
      </a>
      <nav>
        <a href="<?= route_url('main') ?>">Foglalási oldal</a>
        <a v-if="token" href="#" @click.prevent="logout">Kijelentkezés</a>
      </nav>
    </header>

    <!-- LOGIN -->
    <main v-if="!token" class="shell login-shell">
      <section class="panel login-card">
        <span class="brand-mark" style="margin:0 auto 18px;">·</span>
        <p class="eyebrow">Admin belépés</p>
        <h1 style="font-size:26px;">Jelentkezz be</h1>
        <p class="lead" style="margin:8px auto 0;">A foglalások kezeléséhez lépj be a vállalkozásod fiókjával.</p>
        <form class="login-box" @submit.prevent="login">
          <label>
            E-mail cím
            <input v-model.trim="credentials.email" type="email" required autocomplete="username" />
          </label>
          <label>
            Jelszó
            <input v-model="credentials.password" type="password" required autocomplete="current-password" />
          </label>
          <button class="button primary block" type="submit" :disabled="loggingIn">
            <span v-if="loggingIn" class="spinner"></span>
            {{ loggingIn ? 'Belépés…' : 'Belépés' }}
          </button>
        </form>
      </section>
    </main>

    <!-- DASHBOARD -->
    <main v-else class="shell">
      <p class="eyebrow">Áttekintés</p>
      <h1>Foglalások</h1>

      <div class="stat-row">
        <div class="stat-card"><span class="label">Összes foglalás</span><span class="value">{{ stats.total ?? '–' }}</span></div>
        <div class="stat-card accent"><span class="label">Mai aktív</span><span class="value">{{ stats.today ?? '–' }}</span></div>
        <div class="stat-card"><span class="label">Aktív foglalás</span><span class="value">{{ stats.active ?? '–' }}</span></div>
        <div class="stat-card"><span class="label">Lemondva</span><span class="value">{{ stats.cancelled ?? '–' }}</span></div>
      </div>

      <div class="admin-toolbar">
        <div class="filter-row">
          <select v-model="filters.status" @change="refresh">
            <option value="">Minden státusz</option>
            <option value="booked">Foglalva</option>
            <option value="completed">Teljesítve</option>
            <option value="cancelled">Lemondva</option>
            <option value="no_show">Nem jelent meg</option>
          </select>
          <input v-model="filters.date" type="date" @change="refresh" />
          <input v-model.trim="filters.q" type="text" placeholder="Keresés név vagy elérhetőség szerint" @input="debouncedRefresh" />
          <button class="button ghost sm" type="button" @click="clearFilters">Szűrők törlése</button>
        </div>
        <button class="button sm" type="button" @click="refresh">
          <span v-if="loading" class="spinner"></span> Frissítés
        </button>
      </div>

      <div class="admin-layout">
        <section>
          <div class="table-wrap">
            <p v-if="!loading && !bookings.length" class="empty">Nincs a szűrésnek megfelelő foglalás.</p>
            <table v-else>
              <thead>
                <tr>
                  <th>Dátum</th>
                  <th>Idő</th>
                  <th>Szolgáltatás</th>
                  <th>Vendég</th>
                  <th>Státusz</th>
                  <th>Műveletek</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in bookings" :key="item.id">
                  <td class="mono">{{ item.date }}</td>
                  <td class="mono">{{ item.start_time?.slice(0,5) }}–{{ item.end_time?.slice(0,5) }}</td>
                  <td>{{ item.service_name }}</td>
                  <td class="customer-cell">
                    <strong>{{ item.customer_name }}</strong>
                    <small>{{ item.customer_contact }}</small>
                  </td>
                  <td><span class="badge" :class="item.status">{{ statusLabel(item.status) }}</span></td>
                  <td class="actions-cell">
                    <button v-if="item.status === 'booked'" class="button sm" type="button" @click="setStatus(item, 'completed')">Teljesítve</button>
                    <button v-if="item.status === 'booked'" class="button sm" type="button" @click="setStatus(item, 'no_show')">Nem jött el</button>
                    <button v-if="item.status === 'booked'" class="button sm danger" type="button" @click="setStatus(item, 'cancelled')">Lemondás</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <aside class="side-panel">
          <div class="panel" style="padding:22px;">
            <h2 style="font-size:16px;">Időszak blokkolása</h2>
            <p class="lead" style="font-size:13.5px;">Zárd le a naptárad egy adott napra, pl. szabadság vagy karbantartás miatt.</p>
            <label>Dátum <input v-model="block.date" type="date" /></label>
            <label>Kezdés <input v-model="block.start_time" type="time" /></label>
            <label>Vége <input v-model="block.end_time" type="time" /></label>
            <label>Indoklás <input v-model.trim="block.reason" placeholder="pl. Szabadság" /></label>
            <button class="button primary block" style="margin-top:16px;" type="button" :disabled="blockingTime" @click="saveBlock">
              {{ blockingTime ? 'Mentés…' : 'Blokk mentése' }}
            </button>

            <div v-if="blockedTimes.length" class="block-list">
              <div v-for="item in blockedTimes" :key="item.id" class="block-item">
                <div class="info">
                  <strong>{{ item.date }} · {{ item.start_time?.slice(0,5) }}–{{ item.end_time?.slice(0,5) }}</strong>
                  <span>{{ item.reason || 'Nincs indoklás' }}</span>
                </div>
                <button class="icon-btn" type="button" title="Blokk törlése" @click="deleteBlock(item)">
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6.5 4V2.5h3V4M3.5 4l.6 9a1 1 0 001 .9h5.8a1 1 0 001-.9l.6-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </main>
  </div>

  <script src="<?= asset('assets/config.js') ?>"></script>
  <script src="<?= asset('assets/vendor/vue.global.prod.js') ?>"></script>
  <script src="<?= asset('assets/shared.js') ?>"></script>
  <script src="<?= view_asset('index.js') ?>"></script>
</body>
</html>
