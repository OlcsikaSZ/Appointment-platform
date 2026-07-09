const { createApp, reactive } = Vue;
const { api, todayKey, addDaysKey, useToasts, formatPrice } = window.App;

const STATUS_LABELS = {
  booked: 'Foglalva',
  completed: 'Teljesítve',
  cancelled: 'Lemondva',
  no_show: 'Nem jelent meg'
};

createApp({
  data() {
    return {
      business: {},
      token: localStorage.getItem('admin_token') || '',
      credentials: { email: 'admin@example.com', password: 'admin123' },
      loggingIn: false,
      loading: false,
      blockingTime: false,
      savingManual: false,
      savingService: false,
      stats: {},
      bookings: [],
      todayBookings: [],
      calendarItems: [],
      calendarBlocks: [],
      blockedTimes: [],
      services: [],
      slots: [],
      activeTab: 'calendar',
      calendarMode: 'day',
      calendarDate: todayKey(),
      filters: { status: '', date: '', q: '' },
      block: { date: todayKey(), start_time: '12:00', end_time: '13:00', reason: '' },
      manual: { service_id: '', date: todayKey(), time: '', customer_name: '', customer_contact: '', customer_note: '' },
      serviceForm: { id: null, category: 'Altalanos', name: '', description: '', duration_minutes: 45, buffer_minutes: 10, price_forint: '', active: true, sort_order: 0 },
      toasts: useToasts(reactive),
      debounceHandle: null
    };
  },

  computed: {
    calendarDays() {
      if (this.calendarMode === 'day') return [this.calendarDate];
      const base = new Date(this.calendarDate);
      const mondayOffset = (base.getDay() + 6) % 7;
      const monday = addDaysKey(this.calendarDate, -mondayOffset);
      return Array.from({ length: 7 }, (_, index) => addDaysKey(monday, index));
    },

    currentService() {
      return this.services.find((service) => String(service.id) === String(this.manual.service_id));
    }
  },

  async mounted() {
    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
    } catch {}

    if (this.token) await this.refresh();
  },

  methods: {
    statusLabel(status) { return STATUS_LABELS[status] || status; },
    price(service) { return formatPrice(service.price_cents); },
    shortTime(value) { return String(value || '').slice(0, 5); },
    itemsForDay(day) { return this.calendarItems.filter((item) => item.date === day); },
    blocksForDay(day) { return this.calendarBlocks.filter((item) => item.date === day); },

    async login() {
      this.loggingIn = true;
      try {
        const response = await api('/auth/login', { method: 'POST', body: JSON.stringify(this.credentials) });
        this.token = response.token;
        localStorage.setItem('admin_token', response.token);
        this.toasts.success('Sikeres bejelentkezés.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Sikertelen bejelentkezés: ${error.message}`);
      } finally {
        this.loggingIn = false;
      }
    },

    logout() {
      this.token = '';
      localStorage.removeItem('admin_token');
      this.bookings = [];
      this.stats = {};
      this.blockedTimes = [];
      this.services = [];
    },

    async refresh() {
      if (!this.token) return;
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.filters.status) params.set('status', this.filters.status);
        if (this.filters.date) params.set('date', this.filters.date);
        if (this.filters.q) params.set('q', this.filters.q);

        const start = this.calendarDays[0];
        const end = this.calendarDays[this.calendarDays.length - 1];

        const [summary, bookings, blocks, services, today, calendar] = await Promise.all([
          api(`/admin/businesses/${window.App.config.businessId}/summary`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/bookings?${params}`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/blocked-times`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/services`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/today`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/calendar?start=${start}&end=${end}`, { token: this.token })
        ]);

        this.stats = summary.data || {};
        this.bookings = bookings.data || [];
        this.blockedTimes = blocks.data || [];
        this.services = services.data || [];
        this.todayBookings = today.data || [];
        this.calendarItems = calendar.data || [];
        this.calendarBlocks = calendar.blocks || [];
        if (!this.manual.service_id && this.services.length) this.manual.service_id = this.services.find((s) => s.active)?.id || this.services[0].id;
        await this.loadSlots();
      } catch (error) {
        if (String(error.message).includes('401')) { this.toasts.error('A munkameneted lejárt, jelentkezz be újra.'); this.logout(); }
        else this.toasts.error(`Nem sikerült frissíteni: ${error.message}`);
      } finally {
        this.loading = false;
      }
    },

    async loadSlots() {
      this.slots = [];
      if (!this.token || !this.manual.service_id || !this.manual.date) return;
      try {
        const params = new URLSearchParams({ service_id: this.manual.service_id, date: this.manual.date });
        const response = await api(`/admin/businesses/${window.App.config.businessId}/slots?${params}`, { token: this.token });
        this.slots = response.data || [];
        if (!this.slots.some((slot) => slot.time === this.manual.time)) this.manual.time = this.slots[0]?.time || '';
      } catch (error) {
        this.toasts.error(`Időpontok betöltése sikertelen: ${error.message}`);
      }
    },

    async saveManualBooking() {
      this.savingManual = true;
      try {
        await api(`/admin/businesses/${window.App.config.businessId}/bookings`, {
          method: 'POST', token: this.token, body: JSON.stringify(this.manual)
        });
        this.toasts.success('Kézi foglalás rögzítve.');
        this.manual.customer_name = ''; this.manual.customer_contact = ''; this.manual.customer_note = '';
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült kézzel foglalni: ${error.message}`);
      } finally {
        this.savingManual = false;
      }
    },

    debouncedRefresh() { clearTimeout(this.debounceHandle); this.debounceHandle = setTimeout(() => this.refresh(), 350); },
    clearFilters() { this.filters = { status: '', date: '', q: '' }; this.refresh(); },
    setCalendarMode(mode) { this.calendarMode = mode; this.refresh(); },
    moveCalendar(amount) { this.calendarDate = addDaysKey(this.calendarDate, this.calendarMode === 'week' ? amount * 7 : amount); this.refresh(); },
    goToday() { this.calendarDate = todayKey(); this.refresh(); },

    async setStatus(booking, status) {
      try {
        await api(`/admin/bookings/${booking.id}/status`, { method: 'PATCH', token: this.token, body: JSON.stringify({ status }) });
        this.toasts.success('Foglalás frissítve.');
        await this.refresh();
      } catch (error) { this.toasts.error(`Nem sikerült frissíteni: ${error.message}`); }
    },

    async saveBlock() {
      this.blockingTime = true;
      try {
        await api(`/admin/businesses/${window.App.config.businessId}/blocked-times`, { method: 'POST', token: this.token, body: JSON.stringify(this.block) });
        this.toasts.success('Időszak blokkolva.');
        this.block.reason = '';
        await this.refresh();
      } catch (error) { this.toasts.error(`Nem sikerült menteni: ${error.message}`); }
      finally { this.blockingTime = false; }
    },

    async deleteBlock(item) {
      if (!confirm('Biztosan törlöd ezt a blokkolt időszakot?')) return;
      try {
        await api(`/admin/blocked-times/${item.id}`, { method: 'DELETE', token: this.token });
        this.toasts.success('Blokk törölve.');
        await this.refresh();
      } catch (error) { this.toasts.error(`Nem sikerült törölni: ${error.message}`); }
    },

    editService(service) {
      this.serviceForm = {
        id: service.id,
        category: service.category || 'Altalanos',
        name: service.name || '',
        description: service.description || '',
        duration_minutes: service.duration_minutes || 45,
        buffer_minutes: service.buffer_minutes ?? 10,
        price_forint: service.price_cents === null || service.price_cents === undefined ? '' : Math.round(service.price_cents / 100),
        active: !!service.active,
        sort_order: service.sort_order || 0
      };
      this.activeTab = 'services';
    },

    resetServiceForm() {
      this.serviceForm = { id: null, category: 'Altalanos', name: '', description: '', duration_minutes: 45, buffer_minutes: 10, price_forint: '', active: true, sort_order: this.services.length + 1 };
    },

    servicePayload() {
      return {
        category: this.serviceForm.category || 'Altalanos',
        name: this.serviceForm.name,
        description: this.serviceForm.description,
        duration_minutes: Number(this.serviceForm.duration_minutes),
        buffer_minutes: Number(this.serviceForm.buffer_minutes || 0),
        price_cents: this.serviceForm.price_forint === '' ? null : Number(this.serviceForm.price_forint) * 100,
        active: !!this.serviceForm.active,
        sort_order: Number(this.serviceForm.sort_order || 0)
      };
    },

    async saveService() {
      this.savingService = true;
      try {
        const path = this.serviceForm.id ? `/admin/services/${this.serviceForm.id}` : `/admin/businesses/${window.App.config.businessId}/services`;
        await api(path, { method: this.serviceForm.id ? 'PATCH' : 'POST', token: this.token, body: JSON.stringify(this.servicePayload()) });
        this.toasts.success(this.serviceForm.id ? 'Szolgáltatás módosítva.' : 'Új szolgáltatás felvéve.');
        this.resetServiceForm();
        await this.refresh();
      } catch (error) { this.toasts.error(`Szolgáltatás mentése sikertelen: ${error.message}`); }
      finally { this.savingService = false; }
    },

    async toggleService(service) {
      try {
        await api(`/admin/services/${service.id}`, { method: 'PATCH', token: this.token, body: JSON.stringify({ active: !service.active }) });
        await this.refresh();
      } catch (error) { this.toasts.error(`Nem sikerült módosítani: ${error.message}`); }
    },

    async moveService(service, direction) {
      const sorted = [...this.services].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
      const index = sorted.findIndex((item) => item.id === service.id);
      const nextIndex = index + direction;
      if (nextIndex < 0 || nextIndex >= sorted.length) return;
      [sorted[index], sorted[nextIndex]] = [sorted[nextIndex], sorted[index]];
      const items = sorted.map((item, idx) => ({ id: item.id, sort_order: idx + 1 }));
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/services/reorder`, { method: 'POST', token: this.token, body: JSON.stringify({ items }) });
        this.services = response.data || [];
      } catch (error) { this.toasts.error(`Sorrend mentése sikertelen: ${error.message}`); }
    }
  }
}).mount('#adminApp');
