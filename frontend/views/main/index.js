const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  parseKey,
  isToday,
  formatDateLong,
  formatDuration,
  formatPrice,
  downloadIcs,
  useToasts
} = window.App;

createApp({
  data() {
    return {
      business: {},
      services: [],
      selectedCategory: 'all',
      selectedService: null,
      selectedSlot: null,
      date: todayKey(),
      today: todayKey(),
      slots: [],
      workingHours: [],
      step: 1,
      bookingCalendarMode: 'month',
      bookingCalendarDate: todayKey(),
      loadingInit: true,
      loadingSlots: false,
      submitting: false,
      confirmedBooking: null,
      manageUrl: '',
      form: {
        customer_name: '',
        customer_contact: '',
        customer_note: ''
      },
      toasts: useToasts(reactive)
    };
  },

  computed: {
    categories() {
      return [...new Set(this.services.map((item) => item.category).filter(Boolean))];
    },

    filteredServices() {
      if (this.selectedCategory === 'all') return this.services;
      return this.services.filter((item) => item.category === this.selectedCategory);
    },

    formValid() {
      return this.form.customer_name.length > 1 && this.form.customer_contact.length > 3;
    },

    phoneHref() {
      return `tel:${String(this.business.phone || '').replace(/\s+/g, '')}`;
    },

    emailHref() {
      return `mailto:${this.business.email || ''}`;
    },

    currentYear() {
      return new Date().getFullYear();
    },

    publicMonthLabel() {
      const value = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: 'long' }).format(parseKey(this.bookingCalendarDate));
      return value.charAt(0).toLocaleUpperCase('hu-HU') + value.slice(1);
    },

    publicMonthDays() {
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
          disabled: key < this.today
        });
      }
      return days;
    },

    canMovePublicMonthBack() {
      const focus = parseKey(this.bookingCalendarDate);
      const current = parseKey(this.today);
      return focus.getFullYear() > current.getFullYear() || focus.getMonth() > current.getMonth();
    },

    publicDateLabel() {
      return formatDateLong(this.date);
    },

    publicTimelineHours() {
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

    publicSlotMap() {
      return new Map(this.slots.map((slot) => [slot.time, slot]));
    }
  },

  async mounted() {
    try {
      const businessResponse = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = businessResponse.data || {};
      document.title = `${this.business.name || 'Időpontfoglalás'} — Online foglalás`;

      const description = document.querySelector('meta[name="description"]');
      if (description && this.business.heroText) description.setAttribute('content', this.business.heroText);

      const serviceResponse = await api(`/businesses/${window.App.config.businessSlug}/services`);
      this.services = serviceResponse.data || [];
    } catch (error) {
      this.toasts.error(`Indítási hiba: ${error.message}`);
    } finally {
      this.loadingInit = false;
    }
  },

  methods: {
    formatDuration,
    formatPrice,
    formatDateLong,
    isToday,

    monogram(name) {
      return String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toLocaleUpperCase('hu-HU') || '')
        .join('');
    },

    serviceInitials(name) {
      return this.monogram(name) || '•';
    },

    dateKey(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },

    timeToMinutes(value) {
      const [hour, minute] = String(value || '').slice(0, 5).split(':').map(Number);
      return Number.isFinite(hour) && Number.isFinite(minute) ? hour * 60 + minute : NaN;
    },

    async selectService(service) {
      this.selectedService = service;
      this.selectedSlot = null;
      this.slots = [];
      this.workingHours = [];
      this.bookingCalendarMode = 'month';
      this.bookingCalendarDate = this.today;
      this.date = this.today;
    },

    movePublicMonth(amount) {
      if (amount < 0 && !this.canMovePublicMonthBack) return;
      const focus = parseKey(this.bookingCalendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.bookingCalendarDate = this.dateKey(next);
    },

    goPublicCurrentMonth() {
      this.bookingCalendarDate = this.today;
    },

    async openBookingDay(key) {
      if (key < this.today) return;
      this.date = key;
      this.selectedSlot = null;
      this.bookingCalendarMode = 'day';
      await this.loadAvailability();
    },

    backToBookingMonth() {
      this.bookingCalendarMode = 'month';
      this.selectedSlot = null;
    },

    quarterCellsForPublicHour(hour) {
      return [0, 15, 30, 45].map((minute) => {
        const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        const slot = this.publicSlotMap.get(time) || null;
        return {
          time,
          slot,
          available: !!slot,
          selected: !!slot && this.selectedSlot?.time === slot.time
        };
      });
    },

    pickPublicSlot(slot) {
      if (!slot) return;
      this.selectedSlot = slot;
    },

    goToStep(step) {
      if (step === 2 && !this.selectedService) return;
      if (step === 3 && !this.selectedSlot) return;

      if (step === 2 && this.step === 1) {
        this.bookingCalendarMode = 'month';
        this.bookingCalendarDate = this.today;
        this.date = this.today;
        this.selectedSlot = null;
        this.slots = [];
        this.workingHours = [];
      }

      this.step = step;
      window.scrollTo({ top: document.querySelector('#foglalas')?.offsetTop || 0, behavior: 'smooth' });
    },

    async loadAvailability() {
      this.selectedSlot = null;
      this.slots = [];
      this.workingHours = [];
      if (!this.selectedService || !this.date) return;

      this.loadingSlots = true;
      try {
        const params = new URLSearchParams({ service_id: this.selectedService.id, date: this.date });
        const response = await api(`/businesses/${window.App.config.businessSlug}/availability?${params}`);
        const data = response.data || {};
        this.slots = data.slots || [];
        this.workingHours = data.workingHours || [];
      } catch (error) {
        this.toasts.error(`Nem sikerült betölteni az időpontokat: ${error.message}`);
      } finally {
        this.loadingSlots = false;
      }
    },

    async saveBooking() {
      if (!this.selectedService || !this.selectedSlot || !this.formValid || this.submitting) return;

      this.submitting = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/bookings`, {
          method: 'POST',
          body: JSON.stringify({
            service_id: this.selectedService.id,
            date: this.date,
            time: this.selectedSlot.time,
            ...this.form
          })
        });

        this.confirmedBooking = response.data;
        this.manageUrl = response.manageUrl || `./manage?token=${encodeURIComponent(response.data.manage_token)}`;
        this.step = 4;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch (error) {
        this.toasts.error(`Nem sikerült menteni a foglalást: ${error.message}`);
      } finally {
        this.submitting = false;
      }
    },

    addToCalendar() {
      if (!this.confirmedBooking) return;
      downloadIcs({
        title: `${this.confirmedBooking.service_name} – ${this.business.name || ''}`,
        description: `Foglalás: ${this.business.name || 'Időpontfoglalás'}.`,
        dateKey: this.confirmedBooking.date,
        startTime: this.confirmedBooking.start_time,
        endTime: this.confirmedBooking.end_time
      });
    },

    startOver() {
      this.selectedService = null;
      this.selectedSlot = null;
      this.slots = [];
      this.workingHours = [];
      this.date = this.today;
      this.bookingCalendarDate = this.today;
      this.bookingCalendarMode = 'month';
      this.form = { customer_name: '', customer_contact: '', customer_note: '' };
      this.confirmedBooking = null;
      this.step = 1;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }
}).mount('#bookingApp');
