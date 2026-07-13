const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  parseKey,
  isToday,
  formatDateLong,
  useToasts
} = window.App;

const STATUS_LABELS = {
  booked: 'Foglalva',
  completed: 'Teljesítve',
  cancelled: 'Lemondva',
  no_show: 'Nem jelent meg'
};

createApp({
  data() {
    const params = new URLSearchParams(window.location.search);

    return {
      token: params.get('token') || '',
      booking: null,
      loading: true,
      newDate: todayKey(),
      today: todayKey(),
      newTime: '',
      slots: [],
      workingHours: [],
      bookingCalendarMode: 'month',
      bookingCalendarDate: todayKey(),
      loadingSlots: false,
      rescheduling: false,
      cancelling: false,
      confirmingCancel: false,
      toasts: useToasts(reactive)
    };
  },

  computed: {
    isActive() {
      return this.booking && this.booking.status === 'booked';
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
        days.push({
          key,
          dayNumber: cursor.getDate(),
          inCurrentMonth: cursor.getMonth() === focus.getMonth() && cursor.getFullYear() === focus.getFullYear(),
          isToday: key === this.today,
          isCurrentBooking: key === this.booking?.date,
          disabled: key < this.today
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
    if (this.token) await this.loadBooking();
    this.loading = false;
  },

  methods: {
    formatDateLong,
    isToday,
    statusLabel: (status) => STATUS_LABELS[status] || status,

    dateKey(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },

    timeToMinutes(value) {
      const [hour, minute] = String(value || '').slice(0, 5).split(':').map(Number);
      return Number.isFinite(hour) && Number.isFinite(minute) ? hour * 60 + minute : NaN;
    },

    async loadBooking() {
      try {
        const response = await api(`/bookings/${this.token}`);
        this.booking = response.data;
        const bookingDate = this.booking.date || this.today;
        this.newDate = bookingDate < this.today ? this.today : bookingDate;
        this.bookingCalendarDate = this.newDate;
        this.bookingCalendarMode = 'month';
        this.newTime = '';
        this.slots = [];
        this.workingHours = [];
      } catch (error) {
        this.toasts.error(`Nem sikerült betölteni a foglalást: ${error.message}`);
      }
    },

    moveMonth(amount) {
      if (amount < 0 && !this.canMoveMonthBack) return;
      const focus = parseKey(this.bookingCalendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.bookingCalendarDate = this.dateKey(next);
    },

    goCurrentMonth() {
      this.bookingCalendarDate = this.today;
    },

    async openBookingDay(key) {
      if (key < this.today) return;
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
      return [0, 15, 30, 45].map((minute) => {
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
      if (!this.newTime) return;
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
    }
  }
}).mount('#manageApp');
