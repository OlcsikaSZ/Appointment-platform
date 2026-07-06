const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  addDaysKey,
  isToday,
  formatDateLong,
  groupSlotsByPeriod,
  useToasts,
  HU_DOW_SHORT
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
    dateOptions() {
      const options = [];
      for (let i = 0; i < 14; i += 1) {
        const key = addDaysKey(this.today, i);
        options.push({ key, day: Number(key.slice(8, 10)), dow: HU_DOW_SHORT[new Date(key).getDay()] });
      }
      return options;
    },
    groupedSlots() {
      return groupSlotsByPeriod(this.slots);
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

    async loadBooking() {
      try {
        const response = await api(`/bookings/${this.token}`);
        this.booking = response.data;
        this.newDate = this.booking.date || todayKey();
        if (this.isActive) await this.loadSlots();
      } catch (error) {
        this.toasts.error(`Nem sikerült betölteni a foglalást: ${error.message}`);
      }
    },

    pickDate(key) {
      this.newDate = key;
      this.loadSlots();
    },

    async loadSlots() {
      this.newTime = '';
      this.slots = [];
      if (!this.booking || !this.newDate) return;

      this.loadingSlots = true;
      try {
        const slug = this.booking.business?.slug || window.App.config.businessSlug;
        const params = new URLSearchParams({
          service_id: this.booking.service_id,
          date: this.newDate
        });
        const response = await api(`/businesses/${slug}/slots?${params}`);
        this.slots = response.data || [];
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

        this.toasts.success('Az időpont módosítva.');
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
        this.toasts.success('A foglalás lemondva.');
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
