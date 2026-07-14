const { createApp, reactive } = Vue;
const { api, todayKey, addDaysKey, parseKey, formatDateLong, useToasts, formatPrice, isPersonName, isEmail, isValidOptionalNote } = window.App;

const STATUS_LABELS = {
  booked: 'Foglalva',
  completed: 'Teljesítve',
  cancelled: 'Lemondva',
  no_show: 'Nem jelent meg'
};

const HOUR_HEIGHT = 64;

const EMAIL_EVENT_LABELS = {
  booking_created: 'Új foglalás',
  booking_rescheduled: 'Módosítás',
  booking_cancelled: 'Lemondás',
  email_test: 'Teszt email'
};

const EMAIL_RECIPIENT_LABELS = {
  customer: 'Ügyfél',
  admin: 'Admin'
};

const createEmptyEmailSettings = () => ({
  sender_name: '',
  reply_to: '',
  footer_text: '',
  templates: {
    customer: {
      booking_created: { subject: '', intro: '' },
      booking_rescheduled: { subject: '', intro: '' },
      booking_cancelled: { subject: '', intro: '' }
    },
    admin: {
      booking_created: { subject: '', intro: '' },
      booking_rescheduled: { subject: '', intro: '' },
      booking_cancelled: { subject: '', intro: '' }
    }
  }
});

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
      uploadingServiceImage: false,
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
      emailLogs: [],
      emailStats: {},
      emailSystem: {},
      emailSettings: createEmptyEmailSettings(),
      emailDefaultSettings: createEmptyEmailSettings(),
      emailFilters: {
        status: '',
        event_type: '',
        recipient_type: '',
        q: ''
      },
      emailPagination: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
        has_more_pages: false
      },
      emailPageSizeOptions: [
        10,
        20,
        50,
        100
      ],
      emailLoading: false,
      emailLoading: false,
      savingEmailSettings: false,
      sendingTestEmail: false,
      resendingEmailLogId: null,
      emailLogModalOpen: false,
      selectedEmailLog: null,
      emailEditorRecipient: 'customer',
      emailEditorEvent: 'booking_created',
      testEmail: { recipient_email: '', recipient_type: 'customer', event_type: 'booking_created' },
      activeTab: 'calendar',
      calendarDate: todayKey(),
      calendarMode: 'month',
      selectedDayDate: todayKey(),
      dayBookings: [],
      dayBlocks: [],
      dayWorkingHours: [],
      dayAvailableSlots: [],
      dayLoading: false,
      timelineServiceId: '',
      weekdayLabels: ['H', 'K', 'Sze', 'Cs', 'P', 'Szo', 'V'],
      bookingSearch: '',
      selectedBooking: null,
      bookingModalOpen: false,
      manualModalOpen: false,
      serviceModalOpen: false,
      reviewModalOpen: false,
      faqModalOpen: false,
      websitePreviewVersion: 0,
      block: {
        start_date: todayKey(),
        end_date: todayKey(),
        start_time: '12:00',
        end_time: '13:00',
        reason: ''
      },
      manual: {
        service_id: '',
        date: todayKey(),
        time: '',
        customer_name: '',
        customer_contact: '',
        customer_note: ''
      },
      manualSlots: [],
      serviceForm: {
        id: null,
        category: 'Altalanos',
        name: '',
        description: '',
        image_url: '',
        duration_minutes: 45,
        buffer_minutes: 10,
        price_forint: '',
        active: true,
        sort_order: 0
      },
      serviceImageFile: null,
      serviceImagePreview: '',
      websiteForm: {
        name: '',
        tagline: '',
        hero_title: '',
        hero_text: '',
        about_title: '',
        about_text: '',
        phone: '',
        email: '',
        address: '',
        opening_hours: '',
        google_maps_url: ''
      },
      reviewForm: { id: null, author: '', text: '', rating: 5, active: true, sort_order: 0 },
      faqForm: { id: null, question: '', answer: '', active: true, sort_order: 0 },
      toasts: useToasts(reactive)
    };
  },

  computed: {
    currentMonthLabel() {
      const value = new Intl.DateTimeFormat('hu-HU', { year: 'numeric', month: 'long' }).format(parseKey(this.calendarDate));
      return value.charAt(0).toLocaleUpperCase('hu-HU') + value.slice(1);
    },

    selectedDayLabel() {
      return formatDateLong(this.selectedDayDate);
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

    dayTimelineHours() {
      const points = [];
      for (const range of this.dayWorkingHours) {
        points.push(this.timeToMinutes(range.start_time), this.timeToMinutes(range.end_time));
      }
      for (const item of [...this.dayBookings, ...this.dayBlocks]) {
        points.push(this.timeToMinutes(item.start_time), this.timeToMinutes(item.end_time));
      }

      const valid = points.filter(Number.isFinite);
      const minHour = valid.length ? Math.max(0, Math.floor(Math.min(...valid) / 60) - 1) : 7;
      const maxHour = valid.length ? Math.min(24, Math.ceil(Math.max(...valid) / 60) + 1) : 19;
      const endHour = Math.min(24, Math.max(minHour + 4, maxHour));

      return Array.from({ length: endHour - minHour }, (_, index) => minHour + index);
    },

    dayTimelineStartMinutes() {
      return (this.dayTimelineHours[0] || 0) * 60;
    },

    dayTimelineHeight() {
      return this.dayTimelineHours.length * HOUR_HEIGHT;
    },

    dayAvailableSlotSet() {
      return new Set(this.dayAvailableSlots.map((slot) => slot.time));
    },

    currentTimelineService() {
      return this.services.find((service) => String(service.id) === String(this.timelineServiceId));
    },

    bookingSearchResults() {
      const query = this.bookingSearch.trim().toLocaleLowerCase('hu-HU');
      if (query.length < 2) return [];

      return this.bookings
        .filter((item) => [item.customer_name, item.customer_contact, item.customer_note, item.service_name]
          .some((value) => String(value || '').toLocaleLowerCase('hu-HU').includes(query)))
        .slice(0, 8);
    },

    manualValid() {
      return !!this.manual.service_id
        && !!this.manual.date
        && !!this.manual.time
        && isPersonName(this.manual.customer_name)
        && isEmail(this.manual.customer_contact)
        && isValidOptionalNote(this.manual.customer_note);
    },

    manualNameError() {
      if (!this.manual.customer_name) return '';
      return isPersonName(this.manual.customer_name)
        ? ''
        : 'Csak valódi nevet adj meg: betűk, szóköz, kötőjel, pont vagy aposztróf használható.';
    },

    manualEmailError() {
      if (!this.manual.customer_contact) return '';
      return isEmail(this.manual.customer_contact) ? '' : 'Adj meg egy érvényes e-mail címet.';
    },

    manualNoteError() {
      if (!this.manual.customer_note) return '';
      return isValidOptionalNote(this.manual.customer_note)
        ? ''
        : 'A megjegyzés legalább 3, legfeljebb 800 karakter legyen.';
    },

    currentEmailTemplate() {
      return this.emailSettings.templates?.[this.emailEditorRecipient]?.[this.emailEditorEvent]
        || { subject: '', intro: '' };
    },

    testEmailValid() {
      return isEmail(this.testEmail.recipient_email)
        && ['customer', 'admin'].includes(this.testEmail.recipient_type)
        && ['booking_created', 'booking_rescheduled', 'booking_cancelled'].includes(this.testEmail.event_type);
    },

    blockGroups() {
      const sorted = [...this.blockedTimes].sort((a, b) => {
        const dateCompare = String(a.date).localeCompare(String(b.date));
        return dateCompare || String(a.start_time).localeCompare(String(b.start_time));
      });
      const groups = [];

      for (const item of sorted) {
        const signature = `${this.shortTime(item.start_time)}|${this.shortTime(item.end_time)}|${item.reason || ''}|${item.created_at || ''}`;
        const previous = groups[groups.length - 1];
        const canExtend = previous
          && previous.signature === signature
          && item.date === addDaysKey(previous.end_date, 1);

        if (canExtend) {
          previous.end_date = item.date;
          previous.items.push(item);
        } else {
          groups.push({
            signature,
            start_date: item.date,
            end_date: item.date,
            start_time: item.start_time,
            end_time: item.end_time,
            reason: item.reason,
            items: [item]
          });
        }
      }

      return groups;
    },

    emailPaginationPages() {
      const current = Number(
        this.emailPagination.current_page || 1
      );

      const last = Number(
        this.emailPagination.last_page || 1
      );

      if (last <= 7) {
        return Array.from(
          { length: last },
          (_, index) => index + 1
        );
      }

      const pages = [1];

      const start = Math.max(
        2,
        current - 2
      );

      const end = Math.min(
        last - 1,
        current + 2
      );

      if (start > 2) {
        pages.push('ellipsis-left');
      }

      for (let page = start; page <= end; page += 1) {
        pages.push(page);
      }

      if (end < last - 1) {
        pages.push('ellipsis-right');
      }

      pages.push(last);

      return pages;
    },
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
    this.revokeServicePreview();
  },

  methods: {
    statusLabel(status) { return STATUS_LABELS[status] || status; },
    emailEventLabel(eventType) { return EMAIL_EVENT_LABELS[eventType] || eventType; },
    emailRecipientLabel(recipientType) { return EMAIL_RECIPIENT_LABELS[recipientType] || recipientType; },
    emailStatusLabel(status) { return status === 'sent' ? 'Sikeres' : status === 'failed' ? 'Sikertelen' : status; },
    price(service) { return formatPrice(service.price_cents); },
    shortTime(value) { return String(value || '').slice(0, 5); },
    formatDateLong,

    formatDateTime(value) {
      if (!value) return '–';
      const date = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return String(value);
      return new Intl.DateTimeFormat('hu-HU', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
      }).format(date);
    },

    renderEmailTemplatePreview(value) {
      const replacements = {
        '{business_name}': this.business.name || 'Az Ön Vállalkozása',
        '{customer_name}': 'Kovács Anna',
        '{customer_email}': 'anna@example.com',
        '{service_name}': 'Konzultáció',
        '{date}': '2026. 07. 18.',
        '{time}': '10:00–10:45',
        '{manage_url}': '/manage?token=MINTA'
      };
      return Object.entries(replacements).reduce((text, [key, replacement]) => String(text || '').split(key).join(replacement), String(value || ''));
    },

    monogram(name) {
      return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toLocaleUpperCase('hu-HU') || '').join('');
    },

    dateKey(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },

    timeToMinutes(value) {
      const [hour, minute] = String(value || '').slice(0, 5).split(':').map(Number);
      return Number.isFinite(hour) && Number.isFinite(minute) ? hour * 60 + minute : NaN;
    },

    minutesToTime(total) {
      const hour = Math.floor(total / 60);
      const minute = total % 60;
      return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
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
      const open = this.bookingModalOpen || this.manualModalOpen || this.serviceModalOpen || this.reviewModalOpen || this.faqModalOpen || this.emailLogModalOpen;
      document.body.classList.toggle('modal-open', open);
    },

    handleKeydown(event) {
      if (event.key !== 'Escape') return;
      if (this.bookingModalOpen) this.closeBookingModal();
      else if (this.manualModalOpen) this.closeManualModal();
      else if (this.serviceModalOpen) this.closeServiceModal();
      else if (this.reviewModalOpen) this.closeReviewModal();
      else if (this.faqModalOpen) this.closeFaqModal();
      else if (this.emailLogModalOpen) this.closeEmailLogModal();
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
      this.emailLogs = [];
      this.emailStats = {};
      this.emailSystem = {};
      this.emailSettings = createEmptyEmailSettings();
      this.emailDefaultSettings = createEmptyEmailSettings();
      this.selectedEmailLog = null;
      this.emailLogModalOpen = false;
      this.bookingModalOpen = false;
      this.manualModalOpen = false;
      this.serviceModalOpen = false;
      this.reviewModalOpen = false;
      this.faqModalOpen = false;
      this.syncModalBodyLock();
    },

    async refresh() {
      if (!this.token) return;
      this.loading = true;
      try {
        const { start, end } = this.calendarRange;
        const [summary, bookings, blocks, services, today, calendar] = await Promise.all([
          api(`/admin/businesses/${window.App.config.businessId}/summary`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/bookings`, { token: this.token }),
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

        const firstActiveService = this.services.find((service) => service.active) || this.services[0];
        if (!this.services.some((service) => service.active && String(service.id) === String(this.timelineServiceId))) {
          this.timelineServiceId = firstActiveService?.id || '';
        }
        if (!this.services.some((service) => service.active && String(service.id) === String(this.manual.service_id))) {
          this.manual.service_id = firstActiveService?.id || '';
        }

        if (this.calendarMode === 'day') {
          await this.loadDay(this.selectedDayDate, false);
        }
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

    moveCalendar(amount) {
      const focus = parseKey(this.calendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.calendarDate = this.dateKey(next);
      this.calendarMode = 'month';
      this.refresh();
    },

    goToday() {
      this.calendarDate = todayKey();
      this.calendarMode = 'month';
      this.refresh();
    },

    async openDay(dayKey) {
      this.selectedDayDate = dayKey;
      this.calendarMode = 'day';
      this.bookingSearch = '';
      await this.loadDay(dayKey, true);
    },

    backToMonth() {
      this.calendarMode = 'month';
    },

    async loadDay(dayKey = this.selectedDayDate, scrollToWorkday = false) {
      if (!this.token) return;
      this.dayLoading = true;
      this.selectedDayDate = dayKey;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/day?date=${encodeURIComponent(dayKey)}`, { token: this.token });
        const data = response.data || {};
        this.dayBookings = data.bookings || [];
        this.dayBlocks = data.blocks || [];
        this.dayWorkingHours = data.workingHours || [];
        await this.loadDayAvailability();

        if (scrollToWorkday) {
          this.$nextTick(() => {
            const scroller = this.$refs.dayTimelineScroller;
            if (!scroller) return;
            const firstWorking = this.dayWorkingHours[0]?.start_time;
            const targetMinutes = firstWorking ? Math.max(0, this.timeToMinutes(firstWorking) - 60) : this.dayTimelineStartMinutes;
            scroller.scrollTop = Math.max(0, ((targetMinutes - this.dayTimelineStartMinutes) / 60) * HOUR_HEIGHT);
          });
        }
      } catch (error) {
        this.toasts.error(`A napi naptár nem tölthető be: ${error.message}`);
      } finally {
        this.dayLoading = false;
      }
    },

    async loadDayAvailability() {
      this.dayAvailableSlots = [];
      if (!this.token || !this.timelineServiceId || !this.selectedDayDate) return;
      try {
        const params = new URLSearchParams({ service_id: this.timelineServiceId, date: this.selectedDayDate });
        const response = await api(`/admin/businesses/${window.App.config.businessId}/slots?${params}`, { token: this.token });
        this.dayAvailableSlots = response.data || [];
      } catch (error) {
        this.toasts.error(`A szabad időpontok nem tölthetők be: ${error.message}`);
      }
    },

    quarterCellsForHour(hour) {
      return [0, 15, 30, 45].map((minute) => {
        const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        return {
          time,
          available: this.dayAvailableSlotSet.has(time),
          working: this.isWithinWorkingHours(time)
        };
      });
    },

    isWithinWorkingHours(time) {
      const minute = this.timeToMinutes(time);
      return this.dayWorkingHours.some((range) => {
        const start = this.timeToMinutes(range.start_time);
        const end = this.timeToMinutes(range.end_time);
        return minute >= start && minute < end;
      });
    },

    timelineEventStyle(item) {
      const start = this.timeToMinutes(item.start_time);
      const end = this.timeToMinutes(item.end_time);
      const top = ((start - this.dayTimelineStartMinutes) / 60) * HOUR_HEIGHT;
      const height = Math.max(28, ((end - start) / 60) * HOUR_HEIGHT);
      return { top: `${top}px`, height: `${height}px` };
    },

    openBookingModal(booking) {
      this.selectedBooking = booking;
      this.bookingModalOpen = true;
      this.syncModalBodyLock();
    },

    closeBookingModal() {
      this.bookingModalOpen = false;
      this.selectedBooking = null;
      this.syncModalBodyLock();
    },

    async openBookingFromSearch(booking) {
      this.selectedDayDate = booking.date;
      this.calendarDate = booking.date;
      this.calendarMode = 'day';
      this.bookingSearch = '';
      await this.refresh();
      const fresh = this.dayBookings.find((item) => item.id === booking.id) || booking;
      this.openBookingModal(fresh);
      this.$nextTick(() => {
        const scroller = this.$refs.dayTimelineScroller;
        if (!scroller) return;
        const targetMinutes = Math.max(this.dayTimelineStartMinutes, this.timeToMinutes(fresh.start_time) - 60);
        scroller.scrollTop = Math.max(0, ((targetMinutes - this.dayTimelineStartMinutes) / 60) * HOUR_HEIGHT);
      });
    },

    async setStatus(booking, status) {
      try {
        const response = await api(`/admin/bookings/${booking.id}/status`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify({ status })
        });
        const updated = response.data || { ...booking, status };
        if (this.selectedBooking?.id === updated.id) this.selectedBooking = updated;
        this.toasts.success('Foglalás frissítve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült frissíteni: ${error.message}`);
      }
    },

    manageLinkFor(booking) {
      if (!booking?.manage_token) return '';
      const path = window.location.pathname.replace(/\/(admin(?:\.html|\.php)?)(?:\/.*)?$/i, '');
      return `${window.location.origin}${path}/manage?token=${encodeURIComponent(booking.manage_token)}`;
    },

    async copyManageLink(booking) {
      const link = this.manageLinkFor(booking);
      if (!link) return;
      try {
        await navigator.clipboard.writeText(link);
        this.toasts.success('Kezelő link a vágólapra másolva.');
      } catch {
        this.toasts.error('A link másolása nem sikerült.');
      }
    },

    syncBlockDates() {
      if (this.block.end_date < this.block.start_date) this.block.end_date = this.block.start_date;
    },

    async saveBlock() {
      this.blockingTime = true;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/blocked-times`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify(this.block)
        });
        const count = Number(response.count || 1);
        this.toasts.success(count > 1 ? `${count} nap blokkolva.` : 'Időszak blokkolva.');
        this.block.reason = '';
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült menteni: ${error.message}`);
      } finally {
        this.blockingTime = false;
      }
    },

    async deleteBlockGroup(group) {
      const dateLabel = group.start_date === group.end_date ? group.start_date : `${group.start_date} – ${group.end_date}`;
      if (!confirm(`Biztosan törlöd ezt a blokkolást (${dateLabel})?`)) return;
      try {
        await Promise.all(group.items.map((item) => api(`/admin/blocked-times/${item.id}`, { method: 'DELETE', token: this.token })));
        this.toasts.success('Blokkolás törölve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült törölni: ${error.message}`);
      }
    },

    async openManualModal(time = '') {
      const firstActiveService = this.services.find((service) => service.active);
      this.manual = {
        service_id: this.timelineServiceId || firstActiveService?.id || '',
        date: this.selectedDayDate,
        time,
        customer_name: '',
        customer_contact: '',
        customer_note: ''
      };
      this.manualModalOpen = true;
      this.syncModalBodyLock();
      await this.loadManualSlots(time);
      this.$nextTick(() => this.$refs.manualNameInput?.focus());
    },

    closeManualModal() {
      if (this.savingManual) return;
      this.manualModalOpen = false;
      this.syncModalBodyLock();
    },

    async loadManualSlots(preferredTime = '') {
      this.manualSlots = [];
      if (!this.token || !this.manual.service_id || !this.manual.date) return;
      try {
        const params = new URLSearchParams({ service_id: this.manual.service_id, date: this.manual.date });
        const response = await api(`/admin/businesses/${window.App.config.businessId}/slots?${params}`, { token: this.token });
        this.manualSlots = response.data || [];
        const desired = preferredTime || this.manual.time;
        if (this.manualSlots.some((slot) => slot.time === desired)) this.manual.time = desired;
        else this.manual.time = this.manualSlots[0]?.time || '';
      } catch (error) {
        this.toasts.error(`Időpontok betöltése sikertelen: ${error.message}`);
      }
    },

    async saveManualBooking() {
      if (!this.manualValid) {
        this.toasts.error('Ellenőrizd a nevet, az e-mail címet, a megjegyzést és az időpontot.');
        return;
      }

      this.savingManual = true;
      try {
        await api(`/admin/businesses/${window.App.config.businessId}/bookings`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify(this.manual)
        });
        this.toasts.success('Kézi foglalás rögzítve.');
        this.manualModalOpen = false;
        this.syncModalBodyLock();
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült kézzel foglalni: ${error.message}`);
      } finally {
        this.savingManual = false;
      }
    },

    async openEmailTab() {
      this.activeTab = 'email';
      await this.loadEmailCenter();
    },

    emailLogQuery() {
      const params = new URLSearchParams({
        page: String(
          this.emailPagination.current_page || 1
        ),

        per_page: String(
          this.emailPagination.per_page || 10
        )
      });

      for (const [key, value] of Object.entries(this.emailFilters)) {
        if (String(value || '').trim() !== '') {
          params.set(
            key,
            String(value).trim()
          );
        }
      }

      return params.toString();
    },

    async loadEmailCenter() {
      if (!this.token) return;
      this.emailLoading = true;
      try {
        const [logsResponse, settingsResponse] = await Promise.all([
          api(`/admin/businesses/${window.App.config.businessId}/email-logs?${this.emailLogQuery()}`, { token: this.token }),
          api(`/admin/businesses/${window.App.config.businessId}/email-settings`, { token: this.token })
        ]);

        this.emailLogs = logsResponse.data || [];
        this.emailPagination = {
          ...this.emailPagination,
          ...(logsResponse.pagination || {})
        };
        this.emailStats = logsResponse.stats || {};
        this.emailSystem = settingsResponse.system || logsResponse.system || {};
        this.emailSettings = settingsResponse.data || createEmptyEmailSettings();
        this.emailDefaultSettings = settingsResponse.defaults || createEmptyEmailSettings();

        if (!this.testEmail.recipient_email) {
          this.testEmail.recipient_email = this.business.email || this.emailSystem.from_address || '';
        }
      } catch (error) {
        this.toasts.error(`Az email központ nem tölthető be: ${error.message}`);
      } finally {
        this.emailLoading = false;
      }
    },

    async loadEmailLogs(options = {}) {
      if (!this.token) return;

      const {
        resetPage = false,
        scrollToTop = false
      } = options;

      if (resetPage) {
        this.emailPagination.current_page = 1;
      }

      this.emailLoading = true;

      try {
        const response = await api(
          `/admin/businesses/${window.App.config.businessId}/email-logs?${this.emailLogQuery()}`,
          {
            token: this.token
          }
        );

        this.emailLogs = response.data || [];

        this.emailPagination = {
          ...this.emailPagination,
          ...(response.pagination || {})
        };

        this.emailStats = response.stats || {};

        this.emailSystem =
          response.system
          || this.emailSystem;

        if (scrollToTop) {
          this.$nextTick(() => {
            this.$refs.emailLogPanel?.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          });
        }

      } catch (error) {
        this.toasts.error(
          `Az email napló nem tölthető be: ${error.message}`
        );
      } finally {
        this.emailLoading = false;
      }
    },

    async changeEmailPageSize() {
      this.emailPagination.current_page = 1;

      await this.loadEmailLogs();
    },

    async goToEmailPage(page) {
      const targetPage = Number(page);

      const currentPage = Number(
        this.emailPagination.current_page || 1
      );

      const lastPage = Number(
        this.emailPagination.last_page || 1
      );

      if (
        !Number.isInteger(targetPage)
        || targetPage < 1
        || targetPage > lastPage
        || targetPage === currentPage
      ) {
        return;
      }

      this.emailPagination.current_page = targetPage;

      await this.loadEmailLogs({
        scrollToTop: true
      });
    },

    resetEmailFilters() {
      this.emailFilters = {
        status: '',
        event_type: '',
        recipient_type: '',
        q: ''
      };

      this.emailPagination.current_page = 1;

      this.loadEmailLogs();
    },

    async saveEmailSettings() {
      if (this.savingEmailSettings) return;
      this.savingEmailSettings = true;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/email-settings`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify(this.emailSettings)
        });
        this.emailSettings = response.data || this.emailSettings;
        this.toasts.success('Email beállítások elmentve. A következő levelek már ezeket használják.');
      } catch (error) {
        this.toasts.error(`Az email beállítások mentése sikertelen: ${error.message}`);
      } finally {
        this.savingEmailSettings = false;
      }
    },

    resetEmailSettingsToDefaults() {
      if (!confirm('Visszaállítod az email szövegeket az alapértelmezett értékekre? A módosítás csak mentés után lesz végleges.')) return;
      this.emailSettings = JSON.parse(JSON.stringify(this.emailDefaultSettings || createEmptyEmailSettings()));
      this.toasts.success('Az alapértékek betöltve. A véglegesítéshez nyomd meg a Mentés gombot.');
    },

    async sendTestEmail() {
      if (!this.testEmailValid || this.sendingTestEmail) return;
      this.sendingTestEmail = true;
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/email-test`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify(this.testEmail)
        });
        const log = response.data || {};
        if (log.status === 'sent') this.toasts.success('Teszt email elküldve. Nézd meg a postaládát és a spam mappát is.');
        else this.toasts.error(`A teszt email sikertelen: ${log.error_message || response.message || 'ismeretlen hiba'}`);
        await this.loadEmailLogs({
          resetPage: true
        });
      } catch (error) {
        this.toasts.error(`A teszt email küldése sikertelen: ${error.message}`);
      } finally {
        this.sendingTestEmail = false;
      }
    },

    openEmailLog(log) {
      this.selectedEmailLog = log;
      this.emailLogModalOpen = true;
      this.syncModalBodyLock();
    },

    closeEmailLogModal() {
      if (this.resendingEmailLogId) return;
      this.emailLogModalOpen = false;
      this.selectedEmailLog = null;
      this.syncModalBodyLock();
    },

    async resendEmail(log) {
      if (!log?.id || this.resendingEmailLogId) return;
      if (!confirm(`Újraküldöd ezt az emailt a következő címre?\n${log.recipient_email}`)) return;

      this.resendingEmailLogId = log.id;
      try {
        const response = await api(`/admin/email-logs/${log.id}/resend`, {
          method: 'POST',
          token: this.token
        });
        const newLog = response.data || {};
        if (newLog.status === 'sent') this.toasts.success('Email újraküldve.');
        else this.toasts.error(`Az újraküldés sikertelen: ${newLog.error_message || response.message || 'ismeretlen hiba'}`);
        this.emailLogModalOpen = false;
        this.selectedEmailLog = null;
        this.syncModalBodyLock();
        await this.loadEmailLogs({
          resetPage: true
        });
      } catch (error) {
        this.toasts.error(`Az email újraküldése sikertelen: ${error.message}`);
      } finally {
        this.resendingEmailLogId = null;
      }
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
          method: 'POST', token: this.token, body: formData
        });
        this.business = response.data || this.business;
        this.websitePreviewVersion += 1;
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
        this.websitePreviewVersion += 1;
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
      this.revokeServicePreview();
      this.serviceImageFile = null;
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
        this.serviceImagePreview = service.image_url || '';
      } else {
        this.resetServiceForm();
      }
      this.serviceModalOpen = true;
      this.syncModalBodyLock();
      this.$nextTick(() => this.$refs.serviceNameInput?.focus());
    },

    editService(service) { this.openServiceModal(service); },

    closeServiceModal() {
      if (this.savingService || this.uploadingServiceImage) return;
      this.serviceModalOpen = false;
      this.revokeServicePreview();
      this.syncModalBodyLock();
    },

    resetServiceForm() {
      this.revokeServicePreview();
      this.serviceForm = {
        id: null,
        category: 'Altalanos',
        name: '',
        description: '',
        image_url: '',
        duration_minutes: 45,
        buffer_minutes: 10,
        price_forint: '',
        active: true,
        sort_order: this.services.length + 1
      };
      this.serviceImageFile = null;
      this.serviceImagePreview = '';
    },

    onServiceImageSelected(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      this.revokeServicePreview();
      this.serviceImageFile = file;
      this.serviceImagePreview = URL.createObjectURL(file);
    },

    revokeServicePreview() {
      if (this.serviceImagePreview?.startsWith('blob:')) URL.revokeObjectURL(this.serviceImagePreview);
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

    async uploadServiceImage(serviceId, file) {
      const formData = new FormData();
      formData.append('image', file);
      const response = await api(`/admin/services/${serviceId}/image`, {
        method: 'POST', token: this.token, body: formData
      });
      return response.data;
    },

    async deleteServiceImage() {
      if (this.serviceImageFile) {
        this.revokeServicePreview();
        this.serviceImageFile = null;
        this.serviceImagePreview = this.serviceForm.image_url || '';
        return;
      }
      if (!this.serviceForm.id || !this.serviceForm.image_url) return;
      if (!confirm('Biztosan törlöd a szolgáltatás képét?')) return;

      this.uploadingServiceImage = true;
      try {
        const response = await api(`/admin/services/${this.serviceForm.id}/image`, { method: 'DELETE', token: this.token });
        this.serviceForm.image_url = response.data?.image_url || '';
        this.serviceImagePreview = '';
        this.toasts.success('Szolgáltatáskép törölve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`A kép törlése sikertelen: ${error.message}`);
      } finally {
        this.uploadingServiceImage = false;
      }
    },

    async saveService() {
      this.savingService = true;
      try {
        const path = this.serviceForm.id ? `/admin/services/${this.serviceForm.id}` : `/admin/businesses/${window.App.config.businessId}/services`;
        const response = await api(path, {
          method: this.serviceForm.id ? 'PATCH' : 'POST',
          token: this.token,
          body: JSON.stringify(this.servicePayload())
        });
        let savedService = response.data;

        if (this.serviceImageFile && savedService?.id) {
          this.uploadingServiceImage = true;
          savedService = await this.uploadServiceImage(savedService.id, this.serviceImageFile);
        }

        this.toasts.success(this.serviceForm.id ? 'Szolgáltatás módosítva.' : 'Új szolgáltatás felvéve.');
        this.serviceModalOpen = false;
        this.revokeServicePreview();
        this.syncModalBodyLock();
        this.resetServiceForm();
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Szolgáltatás mentése sikertelen: ${error.message}`);
      } finally {
        this.savingService = false;
        this.uploadingServiceImage = false;
      }
    },

    async toggleService(service) {
      try {
        await api(`/admin/services/${service.id}`, {
          method: 'PATCH', token: this.token, body: JSON.stringify({ active: !service.active })
        });
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Nem sikerült módosítani: ${error.message}`);
      }
    },

    async moveService(service, direction) {
      const sorted = [...this.services].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
      const index = sorted.findIndex((item) => item.id === service.id);
      const nextIndex = index + direction;
      if (nextIndex < 0 || nextIndex >= sorted.length) return;
      [sorted[index], sorted[nextIndex]] = [sorted[nextIndex], sorted[index]];
      const items = sorted.map((item, idx) => ({ id: item.id, sort_order: idx + 1 }));
      try {
        const response = await api(`/admin/businesses/${window.App.config.businessId}/services/reorder`, {
          method: 'POST', token: this.token, body: JSON.stringify({ items })
        });
        this.services = response.data || [];
      } catch (error) {
        this.toasts.error(`Sorrend mentése sikertelen: ${error.message}`);
      }
    }
  }
}).mount('#adminApp');
