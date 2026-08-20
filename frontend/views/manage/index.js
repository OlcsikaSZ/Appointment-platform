const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  parseKey,
  isToday,
  formatDateLong,
  calendarDownloadUrl,
  googleCalendarUrl,
  useToasts
} = window.App;

const MANAGE_TOKEN_SESSION_KEY = 'appointment_manage_token';
const MANAGE_RETURN_STATE_KEY = 'appointment_manage_return_state';

function resolveManageToken() {
  const url = new URL(window.location.href);
  const queryToken = url.searchParams.get('token') || '';
  const hashParams = new URLSearchParams(url.hash.replace(/^#/, ''));
  const hashToken = hashParams.get('token') || '';
  const incomingToken = queryToken || hashToken;

  if (incomingToken) {
    sessionStorage.setItem(MANAGE_TOKEN_SESSION_KEY, incomingToken);
    url.searchParams.delete('token');
    hashParams.delete('token');
    url.hash = hashParams.toString() ? `#${hashParams.toString()}` : '';
    window.history.replaceState(null, document.title, `${url.pathname}${url.search}${url.hash}`);
  }

  return incomingToken || sessionStorage.getItem(MANAGE_TOKEN_SESSION_KEY) || '';
}

const STATUS_LABELS = {
  booked: 'Foglalva',
  completed: 'Teljesítve',
  cancelled: 'Lemondva',
  no_show: 'Nem jelent meg'
};

createApp({
  data() {
    return {
      fromAccount: new URL(window.location.href).searchParams.get('from') === 'account',
      fromBooking: new URL(window.location.href).searchParams.get('from') === 'booking',
      token: resolveManageToken(),
      booking: null,
      business: {},
      manageRules: {},
      loadState: 'loading',
      loadErrorMessage: '',
      loading: true,
      newDate: todayKey(),
      today: todayKey(),
      newTime: '',
      slots: [],
      workingHours: [],
      monthAvailability: {},
      loadingMonthAvailability: false,
      bookingCalendarMode: 'month',
      bookingCalendarDate: todayKey(),
      loadingSlots: false,
      rescheduling: false,
      cancelling: false,
      confirmingCancel: false,
      legalDocuments: {
        privacyPolicy: '',
        termsText: '',
        imprintText: '',
        cookiePolicy: ''
      },
      legalModal: {
        open: false,
        title: '',
        content: ''
      },
      legalReturnFocus: null,
      toasts: useToasts(reactive)
    };
  },

  computed: {
    isActive() {
      return this.booking && this.booking.status === 'booked';
    },

    canCancel() {
      return this.isActive && this.manageRules.can_cancel !== false;
    },

    canReschedule() {
      return this.isActive && this.manageRules.can_reschedule !== false;
    },

    cancelDeadlineLabel() {
      return this.formatDeadline(this.manageRules.cancel_deadline_at);
    },

    rescheduleDeadlineLabel() {
      return this.formatDeadline(this.manageRules.reschedule_deadline_at);
    },

    monthLabel() {
      const value = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: 'long' }).format(parseKey(this.bookingCalendarDate));
      return value.charAt(0).toLocaleUpperCase('hu-HU') + value.slice(1);
    },

    monthDays() {
      const focus = parseKey(this.bookingCalendarDate);
      const firstOfMonth = new Date(focus.getFullYear(), focus.getMonth(), 1);
      const lastOfMonth = new Date(focus.getFullYear(), focus.getMonth() + 1, 0);
      const mondayOffset = (firstOfMonth.getDay() + 6) % 7;
      const sundayOffset = 6 - ((lastOfMonth.getDay() + 6) % 7);
      const gridStart = new Date(firstOfMonth);
      const gridEnd = new Date(lastOfMonth);
      gridStart.setDate(gridStart.getDate() - mondayOffset);
      gridEnd.setDate(gridEnd.getDate() + sundayOffset);

      const days = [];
      for (const cursor = new Date(gridStart); cursor <= gridEnd; cursor.setDate(cursor.getDate() + 1)) {
        const key = this.dateKey(cursor);
        const availability = this.monthAvailability[key] || null;
        const isPast = key < this.today;
        const isClosed = !!availability && !availability.has_working_hours;
        const isSoldOut = !!availability && availability.has_working_hours && Number(availability.available_count || 0) === 0;

        days.push({
          key,
          dayNumber: cursor.getDate(),
          inCurrentMonth: cursor.getMonth() === focus.getMonth() && cursor.getFullYear() === focus.getFullYear(),
          isToday: key === this.today,
          isCurrentBooking: key === this.booking?.date,
          isPast,
          availability,
          isClosed,
          isSoldOut,
          disabled: isPast || isClosed || isSoldOut
        });
      }
      return days;
    },

    canMoveMonthBack() {
      const focus = parseKey(this.bookingCalendarDate);
      const current = parseKey(this.today);
      return focus.getFullYear() > current.getFullYear() || focus.getMonth() > current.getMonth();
    },

    selectedDateLabel() {
      return formatDateLong(this.newDate);
    },

    timelineHours() {
      const points = [];
      for (const range of this.workingHours) {
        points.push(this.timeToMinutes(range.start_time), this.timeToMinutes(range.end_time));
      }

      const valid = points.filter(Number.isFinite);
      const minHour = valid.length ? Math.max(0, Math.floor(Math.min(...valid) / 60)) : 8;
      const maxHour = valid.length ? Math.min(24, Math.ceil(Math.max(...valid) / 60)) : 18;
      const endHour = Math.max(minHour + 1, maxHour);
      return Array.from({ length: endHour - minHour }, (_, index) => minHour + index);
    },

    slotMap() {
      return new Map(this.slots.map((slot) => [slot.time, slot]));
    },

    scheduleChanged() {
      if (!this.booking || !this.newTime) return false;
      const currentTime = String(this.booking.start_time || '').slice(0, 5);
      return this.newDate !== this.booking.date || this.newTime !== currentTime;
    }
  },

  async mounted() {
    window.addEventListener('keydown', this.handleLegalModalKeydown);
    if (this.token) await this.loadBooking();
    else this.loadState = 'missing';
    this.loading = false;
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.handleLegalModalKeydown);
    document.body.classList.remove('modal-open');
  },

  methods: {
    formatDateLong,
    isToday,
    statusLabel: (status) => STATUS_LABELS[status] || status,

    startNewBooking() {
      localStorage.removeItem(MANAGE_RETURN_STATE_KEY);
    },

    openLegalModal(title, content, event) {
      this.legalReturnFocus = event?.currentTarget instanceof HTMLElement
        ? event.currentTarget
        : document.activeElement;
      this.legalModal = {
        open: true,
        title,
        content: String(content || '').trim()
      };
      document.body.classList.add('modal-open');
      this.$nextTick(() => this.$refs.legalModalDialog?.focus());
    },

    closeLegalModal() {
      if (!this.legalModal.open) return;
      this.legalModal.open = false;
      document.body.classList.remove('modal-open');
      this.$nextTick(() => {
        if (this.legalReturnFocus instanceof HTMLElement) this.legalReturnFocus.focus();
        this.legalReturnFocus = null;
      });
    },

    handleLegalModalKeydown(event) {
      if (!this.legalModal.open) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        this.closeLegalModal();
        return;
      }
      if (event.key === 'Tab') this.trapLegalModalFocus(event);
    },

    trapLegalModalFocus(event) {
      const dialog = this.$refs.legalModalDialog;
      if (!dialog) return;
      const focusable = [...dialog.querySelectorAll(
        'button:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )].filter((element) => !element.hidden && element.getClientRects().length > 0);
      if (!focusable.length) {
        event.preventDefault();
        dialog.focus();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },

    formatDeadline(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return new Intl.DateTimeFormat('hu-HU', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
      }).format(date);
    },

    dateKey(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },

    timeToMinutes(value) {
      const [hour, minute] = String(value || '').slice(0, 5).split(':').map(Number);
      return Number.isFinite(hour) && Number.isFinite(minute) ? hour * 60 + minute : NaN;
    },

    async loadBooking() {
      this.loadState = 'loading';
      this.loadErrorMessage = '';
      try {
        const response = await api(`/bookings/${this.token}`);
        this.booking = response.data;
        const bookingBusiness = this.booking?.business || {};
        this.business = {
          name: response.business?.name || bookingBusiness.name || 'Időpontfoglalás',
          logoUrl: response.business?.logoUrl || bookingBusiness.logo_path || '',
          logoThumbnailUrl: response.business?.logoThumbnailUrl || bookingBusiness.logo_thumbnail_path || '',
          logoText: response.business?.logoText || bookingBusiness.logo_text || ''
        };
        document.title = `${this.business.name} — Foglalás kezelése`;
        this.manageRules = response.manage || {};
        this.legalDocuments = {
          ...this.legalDocuments,
          ...(response.legal || {})
        };
        const bookingDate = this.booking.date || this.today;
        this.newDate = bookingDate < this.today ? this.today : bookingDate;
        this.bookingCalendarDate = this.newDate;
        this.bookingCalendarMode = 'month';
        this.newTime = '';
        this.slots = [];
        this.workingHours = [];
        this.monthAvailability = {};
        if (this.booking.status === 'booked' && this.canReschedule) {
          await this.loadMonthAvailability();
        }
        this.loadState = 'ready';
      } catch (error) {
        this.booking = null;
        this.business = {};
        this.loadErrorMessage = error.message || 'A foglalás nem tölthető be.';
        if (error.status === 404) {
          this.loadState = 'invalid';
          sessionStorage.removeItem(MANAGE_TOKEN_SESSION_KEY);
        } else if (error.status === 410) {
          this.loadState = 'expired';
          sessionStorage.removeItem(MANAGE_TOKEN_SESSION_KEY);
        } else {
          this.loadState = 'error';
        }
      }
    },

    async moveMonth(amount) {
      if (amount < 0 && !this.canMoveMonthBack) return;
      const focus = parseKey(this.bookingCalendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.bookingCalendarDate = this.dateKey(next);
      await this.loadMonthAvailability();
    },

    async goCurrentMonth() {
      this.bookingCalendarDate = this.today;
      await this.loadMonthAvailability();
    },

    async openBookingDay(key) {
      const availability = this.monthAvailability[key];
      if (key < this.today || (availability && Number(availability.available_count || 0) === 0)) return;
      this.newDate = key;
      this.newTime = '';
      this.bookingCalendarMode = 'day';
      await this.loadAvailability();
    },

    backToMonth() {
      this.bookingCalendarMode = 'month';
      this.newTime = '';
      this.slots = [];
      this.workingHours = [];
    },

    quarterCellsForHour(hour) {
      const step = Number(this.manageRules.slot_interval_minutes || 15);
      const minutes = Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step).filter((minute) => minute < 60);
      return minutes.map((minute) => {
        const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        const slot = this.slotMap.get(time) || null;
        return {
          time,
          slot,
          available: !!slot,
          selected: !!slot && this.newTime === slot.time,
          current: !!slot
            && this.newDate === this.booking?.date
            && slot.time === String(this.booking?.start_time || '').slice(0, 5)
        };
      });
    },

    pickSlot(slot) {
      if (!slot) return;
      this.newTime = slot.time;
    },

    async loadMonthAvailability() {
      this.monthAvailability = {};
      if (!this.booking || this.booking.status !== 'booked' || !this.canReschedule) return;

      const days = this.monthDays;
      const start = days[0]?.key;
      const end = days[days.length - 1]?.key;
      if (!start || !end) return;

      this.loadingMonthAvailability = true;
      try {
        const params = new URLSearchParams({ start, end });
        const response = await api(`/bookings/${this.token}/availability-calendar?${params}`);
        this.monthAvailability = Object.fromEntries(
          (response.data || []).map((item) => [item.date, item])
        );
      } catch (error) {
        this.toasts.error(`A havi elérhetőség nem tölthető be: ${error.message}`);
      } finally {
        this.loadingMonthAvailability = false;
      }
    },

    async loadAvailability() {
      this.newTime = '';
      this.slots = [];
      this.workingHours = [];
      if (!this.booking || !this.newDate) return;

      this.loadingSlots = true;
      try {
        const params = new URLSearchParams({ date: this.newDate });
        const response = await api(`/bookings/${this.token}/availability?${params}`);
        const data = response.data || {};
        this.slots = data.slots || [];
        this.workingHours = data.workingHours || [];
      } catch (error) {
        this.toasts.error(`Nem sikerült az időpontokat betölteni: ${error.message}`);
      } finally {
        this.loadingSlots = false;
      }
    },

    async reschedule() {
      if (!this.newTime || !this.canReschedule) return;
      this.rescheduling = true;
      try {
        await api(`/bookings/${this.token}/reschedule`, {
          method: 'POST',
          body: JSON.stringify({ date: this.newDate, time: this.newTime })
        });

        this.toasts.success('Az időpont módosítva, az értesítő e-mail küldését a rendszer elindította.');
        await this.loadBooking();
      } catch (error) {
        this.toasts.error(`Nem sikerült módosítani: ${error.message}`);
      } finally {
        this.rescheduling = false;
      }
    },

    async cancelBooking() {
      if (!this.canCancel) return;
      this.cancelling = true;
      try {
        await api(`/bookings/${this.token}/cancel`, { method: 'POST' });
        this.toasts.success('A foglalás lemondva, az értesítő e-mail küldését a rendszer elindította.');
        this.confirmingCancel = false;
        await this.loadBooking();
      } catch (error) {
        this.toasts.error(`Nem sikerült lemondani: ${error.message}`);
      } finally {
        this.cancelling = false;
      }
    },

    calendarEventOptions() {
      if (!this.booking || this.booking.status !== 'booked') return;
      const business = { ...(this.booking.business || {}), ...this.business };
      const manageUrl = this.booking.manage_token
        ? new URL(`./manage?token=${encodeURIComponent(this.booking.manage_token)}`, window.location.href).href
        : '';
      return {
        title: `${this.booking.service_name} – ${business.name || 'Időpontfoglalás'}`,
        description: `Foglalás: ${business.name || 'Időpontfoglalás'}.`,
        location: business.address || '',
        dateKey: this.booking.date,
        startTime: this.booking.start_time,
        endTime: this.booking.end_time,
        timezone: business.timezone || 'Europe/Budapest',
        manageUrl
      };
    },

    addToDeviceCalendar() {
      const token = this.booking?.manage_token || this.token;
      if (!token) return this.toasts.error('A naptárfájl hivatkozása nem érhető el.');
      window.location.assign(calendarDownloadUrl(token));
    },

    addToGoogleCalendar() {
      const options = this.calendarEventOptions();
      if (!options) return;
      window.open(googleCalendarUrl(options), '_blank', 'noopener,noreferrer');
    }
  }
}).mount('#manageApp');
