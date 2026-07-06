const { createApp, reactive } = Vue;
const { api, todayKey, useToasts } = window.App;

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
      credentials: {
        email: 'admin@example.com',
        password: 'admin123'
      },
      loggingIn: false,
      loading: false,
      blockingTime: false,
      stats: {},
      bookings: [],
      blockedTimes: [],
      filters: {
        status: '',
        date: '',
        q: ''
      },
      block: {
        date: todayKey(),
        start_time: '12:00',
        end_time: '13:00',
        reason: ''
      },
      toasts: useToasts(reactive),
      debounceHandle: null
    };
  },

  async mounted() {
    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
    } catch {
      /* branding is optional on the admin screen */
    }

    if (this.token) {
      await this.refresh();
    }
  },

  methods: {
    statusLabel(status) {
      return STATUS_LABELS[status] || status;
    },

    async login() {
      this.loggingIn = true;
      try {
        const response = await api('/auth/login', {
          method: 'POST',
          body: JSON.stringify(this.credentials)
        });

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
    },

    async refresh() {
      if (!this.token) return;
      this.loading = true;

      try {
        const params = new URLSearchParams();
        if (this.filters.status) params.set('status', this.filters.status);
        if (this.filters.date) params.set('date', this.filters.date);
        if (this.filters.q) params.set('q', this.filters.q);

        const [summary, bookings, blocks] = await Promise.all([
          api(`/admin/businesses/${window.App.config.businessId}/summary`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/bookings?${params}`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/blocked-times`, { token: this.token })
        ]);

        this.stats = summary.data || {};
        this.bookings = bookings.data || [];
        this.blockedTimes = blocks.data || [];
      } catch (error) {
        if (String(error.message).includes('401')) {
          this.toasts.error('A munkameneted lejárt, jelentkezz be újra.');
          this.logout();
        } else {
          this.toasts.error(`Nem sikerült frissíteni: ${error.message}`);
        }
      } finally {
        this.loading = false;
      }
    },

    debouncedRefresh() {
      clearTimeout(this.debounceHandle);
      this.debounceHandle = setTimeout(() => this.refresh(), 350);
    },

    clearFilters() {
      this.filters = { status: '', date: '', q: '' };
      this.refresh();
    },

    async setStatus(booking, status) {
      try {
        await api(`/admin/bookings/${booking.id}/status`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify({ status })
        });
        this.toasts.success('Foglalás frissítve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült frissíteni: ${error.message}`);
      }
    },

    async saveBlock() {
      this.blockingTime = true;
      try {
        await api(`/admin/businesses/${window.App.config.businessId}/blocked-times`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify(this.block)
        });

        this.toasts.success('Időszak blokkolva.');
        this.block.reason = '';
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült menteni: ${error.message}`);
      } finally {
        this.blockingTime = false;
      }
    },

    async deleteBlock(item) {
      try {
        await api(`/admin/blocked-times/${item.id}`, {
          method: 'DELETE',
          token: this.token
        });
        this.toasts.success('Blokk törölve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült törölni: ${error.message}`);
      }
    }
  }
}).mount('#adminApp');
