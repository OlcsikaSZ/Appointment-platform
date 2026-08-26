(function () {
  const config = window.APPOINTMENT_CONFIG || {};

  const HU_DOW_SHORT = ['V', 'H', 'K', 'Sze', 'Cs', 'P', 'Szo'];
  const HU_MONTHS = [
    'január', 'február', 'március', 'április', 'május', 'június',
    'július', 'augusztus', 'szeptember', 'október', 'november', 'december'
  ];

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function toKey(date) {
    return [date.getFullYear(), pad(date.getMonth() + 1), pad(date.getDate())].join('-');
  }

  function parseKey(key) {
    const [year, month, day] = key.split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  function todayKey() {
    return toKey(new Date());
  }

  function addDaysKey(key, amount) {
    const date = parseKey(key);
    date.setDate(date.getDate() + amount);
    return toKey(date);
  }

  function isToday(key) {
    return key === todayKey();
  }

  function formatDateLong(key) {
    if (!key) return '';
    const date = parseKey(key);
    return `${date.getFullYear()}. ${HU_MONTHS[date.getMonth()]} ${date.getDate()}. (${HU_DOW_SHORT[date.getDay()]})`;
  }

  function formatDuration(minutes) {
    if (!minutes) return '';
    if (minutes < 60) return `${minutes} perc`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest ? `${hours} óra ${rest} perc` : `${hours} óra`;
  }

  function formatPrice(cents) {
    if (cents === null || cents === undefined) return '';
    const forint = Math.round(Number(cents) / 100);
    return `${forint.toLocaleString('hu-HU')} Ft`;
  }

  function servicePriceLabel(service, hidePrices = false) {
    if (!service || hidePrices || service.price_mode === 'hidden') return '';
    if (service.price_mode === 'consultation') return 'Ár egyeztetés alapján';
    return formatPrice(service.price_cents);
  }

  function isPersonName(value) {
    const name = String(value || '').trim();
    if (name.length < 2 || name.length > 120) return false;
    const letters = name.match(/\p{L}/gu) || [];
    return letters.length >= 2 && /^[\p{L}\p{M}][\p{L}\p{M}\s.'’\-]*$/u.test(name);
  }

  function isEmail(value) {
    const email = String(value || '').trim();
    if (!email || email.length > 160) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidOptionalNote(value) {
    const note = String(value || '').trim();
    return note === '' || (note.length >= 3 && note.length <= 800);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;');
  }

  function slotPeriod(time) {
    const hour = Number(String(time).slice(0, 2));
    if (hour < 12) return 'Délelőtt';
    if (hour < 18) return 'Délután';
    return 'Este';
  }

  function groupSlotsByPeriod(slots) {
    const groups = { Délelőtt: [], Délután: [], Este: [] };
    for (const slot of slots) {
      groups[slotPeriod(slot.time)].push(slot);
    }
    return Object.entries(groups).filter(([, items]) => items.length > 0);
  }

  function escapeIcsText(value) {
    return String(value ?? '')
      .replace(/\\/g, '\\\\')
      .replace(/\r\n|\r|\n/g, '\\n')
      .replace(/;/g, '\\;')
      .replace(/,/g, '\\,');
  }

  // Backwards-compatible alias used by older frontend code/tests.
  const icsEscape = escapeIcsText;

  function foldIcsLine(line, maxOctets = 75) {
    const encoder = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
    const byteLength = (value) => encoder
      ? encoder.encode(value).length
      : unescape(encodeURIComponent(value)).length;
    const chars = Array.from(String(line));
    const parts = [];
    let current = '';
    let limit = maxOctets;

    for (const char of chars) {
      const candidate = current + char;
      if (current && byteLength(candidate) > limit) {
        parts.push(current);
        current = char;
        limit = maxOctets - 1; // continuation line starts with one space
        continue;
      }
      current = candidate;
    }

    parts.push(current);
    return parts.map((part, index) => index === 0 ? part : ` ${part}`).join('\r\n');
  }

  function utcTimestamp(date = new Date()) {
    return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
  }

  function formatIcsOffset(minutes) {
    const sign = minutes < 0 ? '-' : '+';
    const absolute = Math.abs(Number(minutes) || 0);
    const hours = Math.floor(absolute / 60);
    const rest = absolute % 60;
    return `${sign}${pad(hours)}${pad(rest)}`;
  }

  function timezoneParts(date, timeZone) {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23'
    }).formatToParts(date);

    return Object.fromEntries(
      parts.filter((part) => part.type !== 'literal').map((part) => [part.type, part.value])
    );
  }

  function timezoneOffsetMinutes(date, timeZone) {
    const parts = timezoneParts(date, timeZone);
    const asUtc = Date.UTC(
      Number(parts.year),
      Number(parts.month) - 1,
      Number(parts.day),
      Number(parts.hour),
      Number(parts.minute),
      Number(parts.second)
    );
    return Math.round((asUtc - date.getTime()) / 60000);
  }

  function zonedLocalToUtc(dateKey, time, timeZone) {
    const [year, month, day] = String(dateKey).split('-').map(Number);
    const [hour, minute, second = 0] = String(time).split(':').map(Number);
    const localAsUtc = Date.UTC(year, month - 1, day, hour, minute, second);
    let utcMs = localAsUtc;

    for (let attempt = 0; attempt < 4; attempt += 1) {
      const offset = timezoneOffsetMinutes(new Date(utcMs), timeZone);
      const next = localAsUtc - offset * 60000;
      if (next === utcMs) break;
      utcMs = next;
    }

    return new Date(utcMs);
  }

  function formatUtcDateTime(date) {
    return [
      date.getUTCFullYear(),
      pad(date.getUTCMonth() + 1),
      pad(date.getUTCDate())
    ].join('') + `T${pad(date.getUTCHours())}${pad(date.getUTCMinutes())}${pad(date.getUTCSeconds())}Z`;
  }

  function formatLocalDateTime(dateKey, time) {
    const [hour, minute, second = 0] = String(time).split(':').map(Number);
    return `${String(dateKey).replaceAll('-', '')}T${pad(hour)}${pad(minute)}${pad(second)}`;
  }

  function transitionRecords(timeZone, year) {
    const start = Date.UTC(year - 1, 0, 1);
    const end = Date.UTC(year + 2, 0, 1);
    const step = 6 * 60 * 60 * 1000;
    const transitions = [];
    let previousMs = start;
    let previousOffset = timezoneOffsetMinutes(new Date(previousMs), timeZone);

    for (let cursor = start + step; cursor <= end; cursor += step) {
      const currentOffset = timezoneOffsetMinutes(new Date(cursor), timeZone);
      if (currentOffset !== previousOffset) {
        let low = previousMs;
        let high = cursor;
        while (high - low > 60 * 1000) {
          const middle = low + Math.floor((high - low) / 2);
          const middleOffset = timezoneOffsetMinutes(new Date(middle), timeZone);
          if (middleOffset === previousOffset) low = middle;
          else high = middle;
        }
        const transitionMs = high;
        const toOffset = timezoneOffsetMinutes(new Date(transitionMs), timeZone);
        transitions.push({ at: transitionMs, from: previousOffset, to: toOffset });
        previousOffset = toOffset;
      }
      previousMs = cursor;
    }

    return transitions;
  }

  function formatVTimezone(timeZone, year) {
    const transitions = transitionRecords(timeZone, year);
    const initialMs = Date.UTC(year - 1, 0, 1);
    const initialOffset = timezoneOffsetMinutes(new Date(initialMs), timeZone);
    const components = [];
    const initialParts = timezoneParts(new Date(initialMs), timeZone);

    components.push([
      'BEGIN:STANDARD',
      `DTSTART:${initialParts.year}${initialParts.month}${initialParts.day}T000000`,
      `TZOFFSETFROM:${formatIcsOffset(initialOffset)}`,
      `TZOFFSETTO:${formatIcsOffset(initialOffset)}`,
      'END:STANDARD'
    ].join('\r\n'));

    for (const transition of transitions) {
      const local = timezoneParts(new Date(transition.at), timeZone);
      const type = transition.to > transition.from ? 'DAYLIGHT' : 'STANDARD';
      components.push([
        `BEGIN:${type}`,
        `DTSTART:${local.year}${local.month}${local.day}T${local.hour}${local.minute}00`,
        `TZOFFSETFROM:${formatIcsOffset(transition.from)}`,
        `TZOFFSETTO:${formatIcsOffset(transition.to)}`,
        `TZNAME:${escapeIcsText(timeZone)}`,
        `END:${type}`
      ].join('\r\n'));
    }

    return [
      'BEGIN:VTIMEZONE',
      `TZID:${escapeIcsText(timeZone)}`,
      `X-LIC-LOCATION:${escapeIcsText(timeZone)}`,
      ...components,
      'END:VTIMEZONE'
    ].join('\r\n');
  }

  function stableBookingUid(bookingId, { uid = '', title = '', dateKey = '', startTime = '', endTime = '' } = {}) {
    if (bookingId !== null && bookingId !== undefined && String(bookingId) !== '') {
      return `booking-${String(bookingId).replace(/[^a-zA-Z0-9._-]/g, '-')}@idovonal`;
    }

    if (uid) {
      const host = window.location.hostname || 'appointment.local';
      return `${String(uid).replace(/[^a-zA-Z0-9._-]/g, '-')}@${host}`;
    }

    const source = `${title}|${dateKey}|${startTime}|${endTime}`;
    let hash = 2166136261;
    for (const char of source) {
      hash ^= char.codePointAt(0);
      hash = Math.imul(hash, 16777619);
    }
    return `booking-${(hash >>> 0).toString(16)}@idovonal`;
  }

  function safeIcsFilename(value) {
    const cleaned = String(value || 'idopont.ics')
      .replace(/[\\/:*?"<>|\u0000-\u001F]/g, '-')
      .replace(/\s+/g, '-')
      .slice(0, 120);
    return cleaned.toLowerCase().endsWith('.ics') ? cleaned : `${cleaned}.ics`;
  }

  function buildIcs({
    uid,
    title,
    description = '',
    location = '',
    dateKey,
    startTime,
    endTime,
    timezone = 'Europe/Budapest',
    timeZone = timezone,
    filename = 'idopont.ics',
    bookingId = null,
    manageUrl = '',
    stamp = new Date()
  }) {
    const safeTimezone = (() => {
      try {
        new Intl.DateTimeFormat('en-US', { timeZone }).format();
        return String(timeZone);
      } catch {
        return 'Europe/Budapest';
      }
    })();

    const start = zonedLocalToUtc(dateKey, startTime, safeTimezone);
    const end = zonedLocalToUtc(dateKey, endTime, safeTimezone);
    const localStart = formatLocalDateTime(dateKey, startTime);
    const localEnd = formatLocalDateTime(dateKey, endTime);
    const descriptionText = [
      description,
      manageUrl ? `Foglalás kezelése: ${manageUrl}` : ''
    ].filter(Boolean).join('\n');
    const stampDate = stamp instanceof Date ? stamp : new Date(stamp);
    const safeStamp = Number.isNaN(stampDate.getTime()) ? new Date() : stampDate;
    const year = Number(String(dateKey).slice(0, 4));

    const lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'PRODID:-//Idovonal//Foglalas//HU',
      `X-WR-TIMEZONE:${escapeIcsText(safeTimezone)}`,
      formatVTimezone(safeTimezone, Number.isFinite(year) ? year : new Date().getFullYear()),
      'BEGIN:VEVENT',
      `UID:${stableBookingUid(bookingId, { uid, title, dateKey, startTime, endTime })}`,
      `DTSTAMP:${formatUtcDateTime(safeStamp)}`,
      `DTSTART;TZID=${escapeIcsText(safeTimezone)}:${localStart}`,
      `DTEND;TZID=${escapeIcsText(safeTimezone)}:${localEnd}`,
      `SUMMARY:${escapeIcsText(title)}`,
      `DESCRIPTION:${escapeIcsText(descriptionText)}`,
      ...(location ? [`LOCATION:${escapeIcsText(location)}`] : []),
      ...(manageUrl ? [`URL:${String(manageUrl)}`] : []),
      'STATUS:CONFIRMED',
      'END:VEVENT',
      'END:VCALENDAR'
    ];

    return lines
      .flatMap((line) => line.split('\r\n'))
      .map((line) => foldIcsLine(line))
      .join('\r\n') + '\r\n';
  }

  function downloadIcs(options) {
    const body = buildIcs(options);
    const filename = options?.filename || 'idopont.ics';
    const blob = new Blob([body], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    const objectUrl = URL.createObjectURL(blob);
    link.href = objectUrl;
    link.download = safeIcsFilename(filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
  }

  function calendarDownloadUrl(manageToken) {
    const base = String(config.apiBase || '/api/v1').replace(/\/$/, '');
    return new URL(`${base}/bookings/${encodeURIComponent(manageToken)}/calendar.ics`, window.location.href).href;
  }

  function googleCalendarUrl({
    title,
    description = '',
    location = '',
    dateKey,
    startTime,
    endTime,
    timezone = 'Europe/Budapest',
    manageUrl = ''
  }) {
    let safeTimezone = timezone;
    try {
      new Intl.DateTimeFormat('en-US', { timeZone: safeTimezone }).format();
    } catch {
      safeTimezone = 'Europe/Budapest';
    }

    const params = new URLSearchParams({
      action: 'TEMPLATE',
      text: String(title || 'Foglalás'),
      dates: `${formatUtcDateTime(zonedLocalToUtc(dateKey, startTime, safeTimezone))}/${formatUtcDateTime(zonedLocalToUtc(dateKey, endTime, safeTimezone))}`,
      ctz: safeTimezone,
      details: [description, manageUrl ? `Foglalás kezelése: ${manageUrl}` : ''].filter(Boolean).join('\n'),
      location: String(location || '')
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
  }

  const PasswordInput = {
    props: {
      modelValue: { type: String, default: '' },
      autocomplete: { type: String, default: 'current-password' },
      required: { type: Boolean, default: false },
      minlength: { type: [Number, String], default: null },
      disabled: { type: Boolean, default: false }
    },
    emits: ['update:modelValue'],
    data() {
      return { visible: false };
    },
    template: `
      <span class="password-field">
        <input
          :type="visible ? 'text' : 'password'"
          :value="modelValue"
          :autocomplete="autocomplete"
          :required="required"
          :minlength="minlength"
          :disabled="disabled"
          autocapitalize="none"
          spellcheck="false"
          @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
          class="password-toggle"
          type="button"
          :aria-label="visible ? 'Jelszó elrejtése' : 'Jelszó megjelenítése'"
          :title="visible ? 'Jelszó elrejtése' : 'Jelszó megjelenítése'"
          :aria-pressed="visible ? 'true' : 'false'"
          @click.stop="visible = !visible"
        >
          <svg v-if="!visible" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
          <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A9.7 9.7 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15.7 15.7 0 0 1-3.1 3.7M6.1 7.7A16.5 16.5 0 0 0 2.5 12s3.4 6 9.5 6c1 0 1.9-.2 2.7-.4M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
      </span>
    `
  };

  function businessMonogram(business = {}) {
    const configured = String(business.logoText || '')
      .trim()
      .replace(/[^\p{L}\p{N}]/gu, '')
      .slice(0, 2);

    if (configured) {
      return configured.toLocaleUpperCase('hu-HU');
    }

    return String(business.name || '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part[0] || '')
      .join('')
      .replace(/[^\p{L}\p{N}]/gu, '')
      .slice(0, 2)
      .toLocaleUpperCase('hu-HU') || 'IP';
  }

  function setBusinessFavicon(business = {}) {
    let favicon = document.getElementById('business-favicon');

    if (!favicon) {
      favicon = document.createElement('link');
      favicon.id = 'business-favicon';
      favicon.rel = 'icon';
      document.head.appendChild(favicon);
    }

    const logoUrl = business.logoThumbnailUrl || business.logoUrl;

    if (logoUrl) {
      favicon.href = new URL(logoUrl, window.location.href).href;
      favicon.type = 'image/webp';
      return;
    }

    const initials = businessMonogram(business);

    const svg =
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">` +
      `<rect width="64" height="64" rx="16" fill="#1c2541"/>` +
      `<text x="32" y="41" text-anchor="middle" font-family="Georgia,serif" ` +
      `font-size="27" font-weight="700" fill="#fffdf9">${initials}</text>` +
      `</svg>`;

    favicon.href = `data:image/svg+xml,${encodeURIComponent(svg)}`;
    favicon.type = 'image/svg+xml';
  }

  async function api(path, options = {}) {
    const { token, headers = {}, ...requestOptions } = options;
    const isFormData = typeof FormData !== 'undefined' && requestOptions.body instanceof FormData;
    const response = await fetch(`${config.apiBase}${path}`, {
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
        ...headers,
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      },
      ...requestOptions
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 401 && token) {
        window.dispatchEvent(new CustomEvent('appointment:auth-expired', { detail: data }));
      }
      const message = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || `HTTP ${response.status}`;
      const error = new Error(message);
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  function useToasts(reactive) {
    const state = reactive([]);
    let counter = 0;

    function push(kind, message, timeout = 4200) {
      const id = ++counter;
      state.push({ id, kind, message });
      if (timeout) {
        setTimeout(() => {
          const index = state.findIndex((item) => item.id === id);
          if (index !== -1) state.splice(index, 1);
        }, timeout);
      }
    }

    return {
      list: state,
      success: (message) => push('success', message),
      error: (message) => push('error', message),
      dismiss: (id) => {
        const index = state.findIndex((item) => item.id === id);
        if (index !== -1) state.splice(index, 1);
      }
    };
  }

  window.App = {
    config,
    api,
    escapeHtml,
    todayKey,
    addDaysKey,
    isToday,
    parseKey,
    formatDateLong,
    formatDuration,
    formatPrice,
    servicePriceLabel,
    isPersonName,
    isEmail,
    isValidOptionalNote,
    groupSlotsByPeriod,
    buildIcs,
    downloadIcs,
    calendarDownloadUrl,
    googleCalendarUrl,
    setBusinessFavicon,
    PasswordInput,
    useToasts,
    HU_DOW_SHORT
  };
})();
