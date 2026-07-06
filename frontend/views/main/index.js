const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  addDaysKey,
  isToday,
  formatDateLong,
  formatDuration,
  formatPrice,
  groupSlotsByPeriod,
  downloadIcs,
  useToasts,
  HU_DOW_SHORT
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
      step: 1,
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
    dateOptions() {
      const options = [];
      for (let i = 0; i < 14; i += 1) {
        const key = addDaysKey(this.today, i);
        const dateObj = new Date(key);
        options.push({ key, day: Number(key.slice(8, 10)), dow: HU_DOW_SHORT[dateObj.getDay()] });
      }
      return options;
    },
    groupedSlots() {
      return groupSlotsByPeriod(this.slots);
    },
    formValid() {
      return this.form.customer_name.length > 1 && this.form.customer_contact.length > 3;
    }
  },

  async mounted() {
    try {
      const businessResponse = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = businessResponse.data || {};

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

    async selectService(service) {
      this.selectedService = service;
      this.selectedSlot = null;
    },

    pickDate(key) {
      this.date = key;
      this.loadSlots();
    },

    goToStep(step) {
      if (step === 2 && !this.selectedService) return;
      if (step === 3 && !this.selectedSlot) return;
      this.step = step;
      if (step === 2 && !this.slots.length) this.loadSlots();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    async loadSlots() {
      this.selectedSlot = null;
      this.slots = [];
      if (!this.selectedService || !this.date) return;

      this.loadingSlots = true;
      try {
        const params = new URLSearchParams({
          service_id: this.selectedService.id,
          date: this.date
        });
        const response = await api(`/businesses/${window.App.config.businessSlug}/slots?${params}`);
        this.slots = response.data || [];
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
        this.manageUrl = `./manage?token=${encodeURIComponent(response.data.manage_token)}`;
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
        description: 'Foglalás az Időpontfoglalás rendszerén keresztül.',
        dateKey: this.confirmedBooking.date,
        startTime: this.confirmedBooking.start_time,
        endTime: this.confirmedBooking.end_time
      });
    },

    startOver() {
      this.selectedService = null;
      this.selectedSlot = null;
      this.slots = [];
      this.date = this.today;
      this.form = { customer_name: '', customer_contact: '', customer_note: '' };
      this.confirmedBooking = null;
      this.step = 1;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }
}).mount('#bookingApp');
