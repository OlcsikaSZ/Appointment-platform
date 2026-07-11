const { createApp, reactive } = Vue;
const { api, todayKey, parseKey, useToasts, formatPrice } = window.App;

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
      savingWebsite: false,
      uploadingLogo: false,
      savingReview: false,
      savingFaq: false,
      stats: {},
      bookings: [],
      todayBookings: [],
      calendarItems: [],
      calendarBlocks: [],
      blockedTimes: [],
      services: [],
      reviews: [],
      faqs: [],
      slots: [],
      activeTab: 'calendar',
      calendarDate: todayKey(),
      weekdayLabels: ['H', 'K', 'Sze', 'Cs', 'P', 'Szo', 'V'],
      serviceModalOpen: false,
      reviewModalOpen: false,
      faqModalOpen: false,
      websitePreviewVersion: 0,
      filters: { status: '', date: '', q: '' },
      block: { date: todayKey(), start_time: '12:00', end_time: '13:00', reason: '' },
      manual: { service_id: '', date: todayKey(), time: '', customer_name: '', customer_contact: '', customer_note: '' },
      serviceForm: { id: null, category: 'Altalanos', name: '', description: '', image_url: '', duration_minutes: 45, buffer_minutes: 10, price_forint: '', active: true, sort_order: 0 },
      websiteForm: { name: '', tagline: '', hero_title: '', hero_text: '', about_title: '', about_text: '', phone: '', email: '', address: '', opening_hours: '', google_maps_url: '' },
      reviewForm: { id: null, author: '', text: '', rating: 5, active: true, sort_order: 0 },
      faqForm: { id: null, question: '', answer: '', active: true, sort_order: 0 },
      toasts: useToasts(reactive),
      debounceHandle: null
    };
  },

  computed: {
    currentMonthLabel() {
      const value = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: 'long' }).format(parseKey(this.calendarDate));
      return value.charAt(0).toLocaleUpperCase('hu-HU') + value.slice(1);
    },

    monthCalendarDays() {
      const focus = parseKey(this.calendarDate);
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
          isToday: key === todayKey()
        });
      }
      return days;
    },

    calendarRange() {
      return {
        start: this.monthCalendarDays[0]?.key || this.calendarDate,
        end: this.monthCalendarDays[this.monthCalendarDays.length - 1]?.key || this.calendarDate
      };
    },

    currentService() {
      return this.services.find((service) => String(service.id) === String(this.manual.service_id));
    }
  },

  async mounted() {
    window.addEventListener('keydown', this.handleKeydown);
    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
    } catch {}

    if (this.token) {
      await Promise.all([this.refresh(), this.loadWebsite()]);
    }
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.handleKeydown);
    document.body.classList.remove('modal-open');
  },

  methods: {
    statusLabel(status) { return STATUS_LABELS[status] || status; },
    price(service) { return formatPrice(service.price_cents); },
    shortTime(value) { return String(value || '').slice(0, 5); },
    monogram(name) {
      return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toLocaleUpperCase('hu-HU') || '').join('');
    },
    dateKey(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },
    itemsForDay(day) { return this.calendarItems.filter((item) => item.date === day); },
    blocksForDay(day) { return this.calendarBlocks.filter((item) => item.date === day); },
    calendarEntriesForDay(day) {
      const entries = [
        ...this.blocksForDay(day).map((item) => ({ type: 'block', key: `block-${item.id}`, start: item.start_time, item })),
        ...this.itemsForDay(day).map((item) => ({ type: 'booking', key: `booking-${item.id}`, start: item.start_time, item }))
      ];
      return entries.sort((left, right) => String(left.start || '').localeCompare(String(right.start || '')));
    },
    syncModalBodyLock() {
      document.body.classList.toggle('modal-open', this.serviceModalOpen || this.reviewModalOpen || this.faqModalOpen);
    },
    handleKeydown(event) {
      if (event.key !== 'Escape') return;
      if (this.serviceModalOpen) this.closeServiceModal();
      else if (this.reviewModalOpen) this.closeReviewModal();
      else if (this.faqModalOpen) this.closeFaqModal();
    },

    async login() {
      this.loggingIn = true;
      try {
        const response = await api('/auth/login', { method: 'POST', body: JSON.stringify(this.credentials) });
        this.token = response.token;
        localStorage.setItem('admin_token', response.token);
        this.toasts.success('Sikeres bejelentkezés.');
        await Promise.all([this.refresh(), this.loadWebsite()]);
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
      this.reviews = [];
      this.faqs = [];
      this.serviceModalOpen = false;
      this.reviewModalOpen = false;
      this.faqModalOpen = false;
      this.syncModalBodyLock();
    },

    async refresh() {
      if (!this.token) return;
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.filters.status) params.set('status', this.filters.status);
        if (this.filters.date) params.set('date', this.filters.date);
        if (this.filters.q) params.set('q', this.filters.q);

        const { start, end } = this.calendarRange;

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
    moveCalendar(amount) {
      const focus = parseKey(this.calendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.calendarDate = this.dateKey(next);
      this.refresh();
    },
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

    async openWebsiteTab() {
      this.activeTab = 'website';
      if (!this.websiteForm.name) await this.loadWebsite();
    },

    mapWebsiteForm(business) {
      return {
        name: business.name || '',
        tagline: business.tagline || '',
        hero_title: business.heroTitle || '',
        hero_text: business.heroText || '',
        about_title: business.aboutTitle || '',
        about_text: business.aboutText || '',
        phone: business.phone || '',
        email: business.email || '',
        address: business.address || '',
        opening_hours: business.openingHours || '',
        google_maps_url: business.googleMapsUrl || ''
      };
    },

    async loadWebsite() {
      if (!this.token) return;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/website`, { token: this.token });
        const data = response.data || {};
        this.business = data.business || this.business;
        this.websiteForm = this.mapWebsiteForm(this.business);
        this.reviews = data.reviews || [];
        this.faqs = data.faqs || [];
      } catch (error) {
        this.toasts.error(`A weboldal beállításai nem tölthetők be: ${error.message}`);
      }
    },

    async saveWebsite() {
      this.savingWebsite = true;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/website`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify(this.websiteForm)
        });
        this.business = response.data || this.business;
        this.websiteForm = this.mapWebsiteForm(this.business);
        this.websitePreviewVersion += 1;
        this.toasts.success('Weboldal beállítások elmentve. A publikus oldal és az előnézet frissült.');
      } catch (error) {
        this.toasts.error(`Weboldal mentése sikertelen: ${error.message}`);
      } finally {
        this.savingWebsite = false;
      }
    },

    async uploadLogo(event) {
      const file = event.target.files?.[0];
      if (!file || this.uploadingLogo) return;
      this.uploadingLogo = true;
      try {
        const formData = new FormData();
        formData.append('logo', file);
        const response = await api(`/admin/businesses/${window.App.config.businessId}/logo`, {
          method: 'POST',
          token: this.token,
          body: formData
        });
        this.business = response.data || this.business;
        this.toasts.success('Logó feltöltve.');
      } catch (error) {
        this.toasts.error(`Logó feltöltése sikertelen: ${error.message}`);
      } finally {
        this.uploadingLogo = false;
        event.target.value = '';
      }
    },

    async deleteLogo() {
      if (!confirm('Biztosan törlöd a feltöltött logót? Ezután a rendszer monogramot használ.')) return;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/logo`, { method: 'DELETE', token: this.token });
        this.business = response.data || this.business;
        this.toasts.success('Logó törölve, a monogram aktív.');
      } catch (error) {
        this.toasts.error(`Logó törlése sikertelen: ${error.message}`);
      }
    },

    openReviewModal(review = null) {
      if (review) {
        this.reviewForm = {
          id: review.id,
          author: review.author || '',
          text: review.text || '',
          rating: Number(review.rating || 5),
          active: !!review.active,
          sort_order: Number(review.sort_order || 0)
        };
      } else {
        this.resetReviewForm();
      }
      this.reviewModalOpen = true;
      this.syncModalBodyLock();
      this.$nextTick(() => this.$refs.reviewAuthorInput?.focus());
    },

    editReview(review) { this.openReviewModal(review); },

    closeReviewModal() {
      if (this.savingReview) return;
      this.reviewModalOpen = false;
      this.syncModalBodyLock();
    },

    resetReviewForm() {
      this.reviewForm = { id: null, author: '', text: '', rating: 5, active: true, sort_order: this.reviews.length + 1 };
    },

    async saveReview() {
      this.savingReview = true;
      try {
        const path = this.reviewForm.id ? `/admin/reviews/${this.reviewForm.id}` : `/admin/businesses/${window.App.config.businessId}/reviews`;
        await api(path, {
          method: this.reviewForm.id ? 'PATCH' : 'POST',
          token: this.token,
          body: JSON.stringify(this.reviewForm)
        });
        this.toasts.success(this.reviewForm.id ? 'Vélemény módosítva.' : 'Vélemény hozzáadva.');
        this.reviewModalOpen = false;
        this.syncModalBodyLock();
        this.resetReviewForm();
        await this.loadWebsite();
      } catch (error) {
        this.toasts.error(`Vélemény mentése sikertelen: ${error.message}`);
      } finally {
        this.savingReview = false;
      }
    },

    async deleteReview(review) {
      if (!confirm(`Biztosan törlöd ezt a véleményt: ${review.author}?`)) return;
      try {
        await api(`/admin/reviews/${review.id}`, { method: 'DELETE', token: this.token });
        this.toasts.success('Vélemény törölve.');
        await this.loadWebsite();
      } catch (error) {
        this.toasts.error(`Vélemény törlése sikertelen: ${error.message}`);
      }
    },

    openFaqModal(faq = null) {
      if (faq) {
        this.faqForm = {
          id: faq.id,
          question: faq.question || '',
          answer: faq.answer || '',
          active: !!faq.active,
          sort_order: Number(faq.sort_order || 0)
        };
      } else {
        this.resetFaqForm();
      }
      this.faqModalOpen = true;
      this.syncModalBodyLock();
      this.$nextTick(() => this.$refs.faqQuestionInput?.focus());
    },

    editFaq(faq) { this.openFaqModal(faq); },

    closeFaqModal() {
      if (this.savingFaq) return;
      this.faqModalOpen = false;
      this.syncModalBodyLock();
    },

    resetFaqForm() {
      this.faqForm = { id: null, question: '', answer: '', active: true, sort_order: this.faqs.length + 1 };
    },

    async saveFaq() {
      this.savingFaq = true;
      try {
        const path = this.faqForm.id ? `/admin/faqs/${this.faqForm.id}` : `/admin/businesses/${window.App.config.businessId}/faqs`;
        await api(path, {
          method: this.faqForm.id ? 'PATCH' : 'POST',
          token: this.token,
          body: JSON.stringify(this.faqForm)
        });
        this.toasts.success(this.faqForm.id ? 'GYIK módosítva.' : 'GYIK elem hozzáadva.');
        this.faqModalOpen = false;
        this.syncModalBodyLock();
        this.resetFaqForm();
        await this.loadWebsite();
      } catch (error) {
        this.toasts.error(`GYIK mentése sikertelen: ${error.message}`);
      } finally {
        this.savingFaq = false;
      }
    },

    async deleteFaq(faq) {
      if (!confirm(`Biztosan törlöd ezt a GYIK elemet: ${faq.question}?`)) return;
      try {
        await api(`/admin/faqs/${faq.id}`, { method: 'DELETE', token: this.token });
        this.toasts.success('GYIK elem törölve.');
        await this.loadWebsite();
      } catch (error) {
        this.toasts.error(`GYIK törlése sikertelen: ${error.message}`);
      }
    },

    openServiceModal(service = null) {
      if (service) {
        this.serviceForm = {
          id: service.id,
          category: service.category || 'Altalanos',
          name: service.name || '',
          description: service.description || '',
          image_url: service.image_url || '',
          duration_minutes: service.duration_minutes || 45,
          buffer_minutes: service.buffer_minutes ?? 10,
          price_forint: service.price_cents === null || service.price_cents === undefined ? '' : Math.round(service.price_cents / 100),
          active: !!service.active,
          sort_order: service.sort_order || 0
        };
      } else {
        this.resetServiceForm();
      }
      this.serviceModalOpen = true;
      this.syncModalBodyLock();
      this.$nextTick(() => this.$refs.serviceNameInput?.focus());
    },

    editService(service) { this.openServiceModal(service); },

    closeServiceModal() {
      if (this.savingService) return;
      this.serviceModalOpen = false;
      this.syncModalBodyLock();
    },

    resetServiceForm() {
      this.serviceForm = { id: null, category: 'Altalanos', name: '', description: '', image_url: '', duration_minutes: 45, buffer_minutes: 10, price_forint: '', active: true, sort_order: this.services.length + 1 };
    },

    servicePayload() {
      return {
        category: this.serviceForm.category || 'Altalanos',
        name: this.serviceForm.name,
        description: this.serviceForm.description,
        image_url: this.serviceForm.image_url || null,
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
        this.serviceModalOpen = false;
        this.syncModalBodyLock();
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
