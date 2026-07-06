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

  function downloadIcs({ title, description, dateKey, startTime, endTime }) {
    const start = `${dateKey.replaceAll('-', '')}T${startTime.replace(':', '')}00`;
    const end = `${dateKey.replaceAll('-', '')}T${endTime.replace(':', '')}00`;
    const body = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Idovonal//Foglalas//HU',
      'BEGIN:VEVENT',
      `UID:${Date.now()}@idovonal`,
      `DTSTAMP:${start}Z`,
      `DTSTART:${start}`,
      `DTEND:${end}`,
      `SUMMARY:${title}`,
      `DESCRIPTION:${description || ''}`,
      'END:VEVENT',
      'END:VCALENDAR'
    ].join('\r\n');

    const blob = new Blob([body], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'idopont.ics';
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  async function api(path, options = {}) {
    const { token, headers = {}, ...requestOptions } = options;
    const response = await fetch(`${config.apiBase}${path}`, {
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...headers,
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      },
      ...requestOptions
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || `HTTP ${response.status}`;
      throw new Error(message);
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
    groupSlotsByPeriod,
    downloadIcs,
    useToasts,
    HU_DOW_SHORT
  };
})();
