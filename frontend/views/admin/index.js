const { createApp, reactive } = Vue;
const { api, todayKey, addDaysKey, parseKey, formatDateLong, useToasts, servicePriceLabel, isPersonName, isEmail, isValidOptionalNote, setBusinessFavicon, PasswordInput } = window.App;

const STATUS_LABELS = {
  booked: 'Foglalva',
  completed: 'Teljesítve',
  cancelled: 'Lemondva',
  no_show: 'Nem jelent meg'
};

const HOUR_HEIGHT = 64;
const ADMIN_TOKEN_KEY = 'admin_token';
const ADMIN_BUSINESS_KEY = 'admin_business_id';
const ADMIN_ACTIVITY_KEY = 'admin_last_activity_at';
const ADMIN_IDLE_TIMEOUT_MS = 3 * 24 * 60 * 60 * 1000;
const ADMIN_ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'scroll', 'touchstart'];

const EMAIL_EVENT_LABELS = {
  booking_created: 'Új foglalás',
  booking_rescheduled: 'Módosítás',
  booking_cancelled: 'Lemondás',
  booking_reminder_24h: '24 órás emlékeztető',
  booking_reminder_2h: '2 órás emlékeztető',
  email_test: 'Teszt email'
};

const EMAIL_RECIPIENT_LABELS = {
  customer: 'Ügyfél',
  admin: 'Admin'
};

const createDefaultWorkWeek = () => ([
  { weekday: 1, label: 'Hétfő', closed: false, start_time: '09:00', end_time: '17:00', break_enabled: true, break_start: '12:00', break_end: '13:00' },
  { weekday: 2, label: 'Kedd', closed: false, start_time: '09:00', end_time: '17:00', break_enabled: true, break_start: '12:00', break_end: '13:00' },
  { weekday: 3, label: 'Szerda', closed: false, start_time: '09:00', end_time: '17:00', break_enabled: true, break_start: '12:00', break_end: '13:00' },
  { weekday: 4, label: 'Csütörtök', closed: false, start_time: '09:00', end_time: '17:00', break_enabled: true, break_start: '12:00', break_end: '13:00' },
  { weekday: 5, label: 'Péntek', closed: false, start_time: '09:00', end_time: '17:00', break_enabled: true, break_start: '12:00', break_end: '13:00' },
  { weekday: 6, label: 'Szombat', closed: false, start_time: '09:00', end_time: '13:00', break_enabled: false, break_start: '11:00', break_end: '11:30' },
  { weekday: 0, label: 'Vasárnap', closed: true, start_time: '09:00', end_time: '17:00', break_enabled: false, break_start: '12:00', break_end: '13:00' }
]);

const createEmptyEmailSettings = () => ({
  sender_name: '',
  reply_to: '',
  footer_text: '',
  templates: {
    customer: {
      booking_created: { subject: '', intro: '' },
      booking_rescheduled: { subject: '', intro: '' },
      booking_cancelled: { subject: '', intro: '' },
      booking_reminder_24h: { subject: '', intro: '' },
      booking_reminder_2h: { subject: '', intro: '' }
    },
    admin: {
      booking_created: { subject: '', intro: '' },
      booking_rescheduled: { subject: '', intro: '' },
      booking_cancelled: { subject: '', intro: '' },
      booking_reminder_24h: { subject: '', intro: '' },
      booking_reminder_2h: { subject: '', intro: '' }
    }
  }
});

const createDefaultAdminSettings = () => ({
  min_advance_minutes: 60,
  max_advance_days: 90,
  slot_interval_minutes: 15,
  cancellation_deadline_minutes: 1440,
  reschedule_deadline_minutes: 1440,
  reminder_24h_enabled: true,
  reminder_2h_enabled: false,
  timezone: 'Europe/Budapest',
  hide_prices: false,
  booking_retention_days: 730,
  email_log_retention_days: 180,
  manage_token_retention_days: 30,
  privacy_policy: '',
  terms_text: '',
  imprint_text: '',
  cookie_policy: ''
});

const createConfirmDialog = () => ({
  open: false,
  title: 'Megerősítés',
  message: '',
  details: [],
  confirmLabel: 'Folytatás',
  cancelLabel: 'Mégse',
  danger: false,
  resolve: null
});

const legalEditorSelections = new Map();

createApp({
  components: { PasswordInput },
  data() {
    return {
      business: {},
      token: localStorage.getItem(ADMIN_TOKEN_KEY) || '',
      businessId: Number(localStorage.getItem(ADMIN_BUSINESS_KEY)) || null,
      currentUser: null,
      credentials: { email: '', password: '' },
      authMode: 'login',
      ownerActivation: { email: '', password: '', password_confirmation: '' },
      ownerActivationDigits: ['', '', '', '', '', ''],
      passwordReset: { email: '', password: '', password_confirmation: '' },
      passwordResetDigits: ['', '', '', '', '', ''],
      adminProfileForm: { name: '' },
      adminEmailForm: { email: '', current_password: '' },
      adminEmailDigits: ['', '', '', '', '', ''],
      adminPasswordForm: { current_password: '', password: '', password_confirmation: '' },
      adminSessions: [],
      securityLoading: false,
      savingAdminProfile: false,
      requestingAdminEmail: false,
      verifyingAdminEmail: false,
      changingAdminPassword: false,
      revokingAdminSessionId: null,
      sessionCheckTimer: null,
      heartbeatTimer: null,
      lastActivityWriteAt: 0,
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
      statistics: {},
      statisticsMonth: todayKey().slice(0, 7),
      statisticsLoading: false,
      exportingBookings: false,
      exportingStatistics: false,
      bookings: [],
      todayBookings: [],
      calendarItems: [],
      calendarBlocks: [],
      monthAvailability: {},
      monthAvailabilityLoading: false,
      blockedTimes: [],
      services: [],
      reviews: [],
      faqs: [],
      customers: [],
      selectedCustomer: null,
      customerBookings: [],
      customerSearch: '',
      customersLoading: false,
      savingCustomer: false,
      reminderLogs: [],
      reminderStats: {},
      remindersLoading: false,
      emailLogs: [],
      emailStats: {},
      emailSystem: {},
      emailSettings: createEmptyEmailSettings(),
      emailDefaultSettings: createEmptyEmailSettings(),
      adminSettings: createDefaultAdminSettings(),
      settingsTimezones: [],
      settingsLoading: false,
      savingAdminSettings: false,
      confirmDialog: createConfirmDialog(),
      modalReturnFocus: null,
      modalWasOpen: false,
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
      calendarFilters: {
        service_id: '',
        status: '',
        date: ''
      },
      selectedDayDate: todayKey(),
      dayBookings: [],
      dayBlocks: [],
      dayWorkingHours: [],
      dayAvailableSlots: [],
      dayLoading: false,
      workingHoursLoading: false,
      savingWorkingHours: false,
      syncPublicOpeningHours: true,
      workWeek: createDefaultWorkWeek(),
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
        reason: '',
        all_day: false
      },
      manual: {
        service_id: '',
        date: todayKey(),
        time: '',
        customer_name: '',
        customer_contact: '',
        customer_phone: '',
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
        price_mode: 'fixed',
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
      toasts: useToasts(reactive, { single: true, successTimeout: 3600, errorTimeout: 0 })
    };
  },

  computed: {
    legalEditorDocuments() {
      return [
        { field: 'privacy_policy', label: 'Adatkezelési tájékoztató', placeholder: 'Adatkezelő adatai, célok, jogalap, megőrzési idők, érintetti jogok…' },
        { field: 'terms_text', label: 'Felhasználási feltételek', placeholder: 'Foglalás, lemondás, késés, szolgáltatás igénybevételének szabályai…' },
        { field: 'imprint_text', label: 'Impresszum', placeholder: 'Szolgáltató neve, székhelye, adószáma, elérhetősége, tárhelyszolgáltató…' },
        { field: 'cookie_policy', label: 'Süti- és technikai tárolási tájékoztató', placeholder: 'Technikailag szükséges tárolás, admin localStorage, külső szolgáltatások…' }
      ];
    },

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
        const availability = this.monthAvailability[key] || null;
        const isPast = key < todayKey();
        const isClosed = !!availability && !availability.has_working_hours;
        const isSoldOut = !!availability && availability.has_working_hours && Number(availability.available_count || 0) === 0;

        days.push({
          key,
          dayNumber: cursor.getDate(),
          inCurrentMonth: cursor.getMonth() === focus.getMonth() && cursor.getFullYear() === focus.getFullYear(),
          isToday: key === todayKey(),
          isPast,
          availability,
          isClosed,
          isSoldOut
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
      for (const item of [...this.filteredDayBookings, ...this.dayTimedBlocks]) {
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

    dayAllDayBlocks() {
      return this.dayBlocks.filter((item) => item.is_all_day);
    },

    dayTimedBlocks() {
      return this.dayBlocks.filter((item) => !item.is_all_day);
    },

    filteredDayBookings() {
      return this.dayBookings.filter((item) => this.bookingMatchesCalendarFilters(item));
    },

    activeCalendarFilterCount() {
      return Object.values(this.calendarFilters).filter(Boolean).length;
    },

    maxDailyBookings() {
      return Math.max(1, ...(this.statistics.daily || []).map((day) => Number(day.total || 0)));
    },

    maxTopServiceBookings() {
      return Math.max(1, ...(this.statistics.top_services || []).map((service) => Number(service.bookings || 0)));
    },

    workingHoursValid() {
      return this.workWeek.every((day) => {
        if (day.closed) return true;
        if (!day.start_time || !day.end_time || day.start_time >= day.end_time) return false;
        if (!day.break_enabled) return true;
        return !!day.break_start
          && !!day.break_end
          && day.start_time < day.break_start
          && day.break_start < day.break_end
          && day.break_end < day.end_time;
      });
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
        && ['booking_created', 'booking_rescheduled', 'booking_cancelled', 'booking_reminder_24h', 'booking_reminder_2h'].includes(this.testEmail.event_type);
    },

    blockGroups() {
      const sorted = [...this.blockedTimes].sort((a, b) => {
        const dateCompare = String(a.date).localeCompare(String(b.date));
        return dateCompare || String(a.start_time).localeCompare(String(b.start_time));
      });
      const groups = [];

      for (const item of sorted) {
        const signature = `${item.is_all_day ? 1 : 0}|${this.shortTime(item.start_time)}|${this.shortTime(item.end_time)}|${item.reason || ''}|${item.created_at || ''}`;
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
            all_day: !!item.is_all_day,
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

  watch: {
    business: {
      immediate: true,
      deep: true,
      handler(value) {
        setBusinessFavicon(value);
      }
    },

    activeTab(next, previous) {
      if (previous === 'calendar' && next !== 'calendar') {
        this.resetCalendarFilters();
      }
    }
  },

  async mounted() {
    window.addEventListener('keydown', this.handleKeydown);
    window.addEventListener('appointment:auth-expired', this.handleAuthExpired);
    this.bindActivityTracking();
    this.sessionCheckTimer = window.setInterval(this.checkClientSession, 60 * 1000);
    this.heartbeatTimer = window.setInterval(this.sendSessionHeartbeat, 15 * 60 * 1000);

    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
    } catch {}

    if (this.token) {
      if (this.isClientSessionExpired()) {
        this.expireSession('A munkameneted inaktivitás miatt lejárt. Jelentkezz be újra.');
      } else {
        await this.restoreSession();
      }
    }
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.handleKeydown);
    window.removeEventListener('appointment:auth-expired', this.handleAuthExpired);
    this.unbindActivityTracking();
    window.clearInterval(this.sessionCheckTimer);
    window.clearInterval(this.heartbeatTimer);
    document.body.classList.remove('modal-open');
    this.revokeServicePreview();
  },

  methods: {
    statusLabel(status) { return STATUS_LABELS[status] || status; },
    emailEventLabel(eventType) { return EMAIL_EVENT_LABELS[eventType] || eventType; },
    emailRecipientLabel(recipientType) { return EMAIL_RECIPIENT_LABELS[recipientType] || recipientType; },
    emailStatusLabel(status) {
      if (status === 'sent') return 'Sikeres';
      if (status === 'failed') return 'Sikertelen';
      if (status === 'pending') return 'Várólistán';
      if (status === 'skipped') return 'Kihagyva';
      return status;
    },
    reviewModerationLabel(status) {
      if (status === 'pending') return 'Jóváhagyásra vár';
      if (status === 'rejected') return 'Elutasítva';
      return 'Jóváhagyva';
    },
    price(service) { return servicePriceLabel(service, !!this.business.hidePrices); },
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
    formatBookingCreatedAt(value) {
      if (!value) return 'Nincs adat';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return 'Nincs adat';
      return new Intl.DateTimeFormat('hu-HU', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: this.business.timezone || 'Europe/Budapest'
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

    bookingMatchesCalendarFilters(booking) {
      const filters = this.calendarFilters;
      if (filters.service_id && String(booking.service_id) !== String(filters.service_id)) return false;
      if (filters.status && booking.status !== filters.status) return false;
      if (filters.date && booking.date !== filters.date) return false;
      return true;
    },

    itemsForDay(day) {
      return this.calendarItems.filter((item) => item.date === day && this.bookingMatchesCalendarFilters(item));
    },
    blocksForDay(day) { return this.calendarBlocks.filter((item) => item.date === day); },

    async handleCalendarServiceFilterChange() {
      const selected = this.services.find((service) => String(service.id) === String(this.calendarFilters.service_id));
      if (selected?.active) {
        this.timelineServiceId = this.calendarFilters.service_id;
        await this.loadMonthAvailability();
        if (this.calendarMode === 'day') await this.loadDayAvailability();
      }
    },

    resetCalendarFilters() {
      this.calendarFilters = { service_id: '', status: '', date: '' };
    },

    formatForint(value) {
      return new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 0 }).format(Number(value || 0)) + ' Ft';
    },

    comparisonLabel(value) {
      if (value === null || value === undefined) return 'nincs összehasonlítható előző adat';
      const number = Number(value || 0);
      return `${number > 0 ? '+' : ''}${number}%`;
    },

    dailyBarHeight(value) { return `${Math.max(4, Number(value || 0) / this.maxDailyBookings * 100)}%`; },
    topServiceWidth(value) { return `${Math.max(4, Number(value || 0) / this.maxTopServiceBookings * 100)}%`; },

    async openStatisticsTab() {
      this.activeTab = 'statistics';
      if (!this.statisticsMonth) this.statisticsMonth = this.calendarDate.slice(0, 7);
      await this.loadStatistics();
    },

    async loadStatistics() {
      if (!this.token || !this.businessId || !this.statisticsMonth) return;
      this.statisticsLoading = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/statistics?month=${encodeURIComponent(this.statisticsMonth)}`, { token: this.token });
        this.statistics = response.data || {};
      } catch (error) { this.toasts.error(`A statisztikák nem tölthetők be: ${error.message}`); }
      finally { this.statisticsLoading = false; }
    },

    async downloadAdminExport(path, filename) {
      const response = await fetch(`${window.App.config.apiBase}${path}`, { cache: 'no-store', headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Authorization: `Bearer ${this.token}` } });
      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        if (response.status === 401) window.dispatchEvent(new CustomEvent('appointment:auth-expired', { detail: data }));
        throw new Error(data.message || `HTTP ${response.status}`);
      }
      const blob = await response.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a');
      link.href = url; link.download = filename; document.body.appendChild(link); link.click(); link.remove(); window.setTimeout(() => URL.revokeObjectURL(url), 0);
    },

    async exportCalendarBookings() {
      this.exportingBookings = true;
      try {
        const month = this.calendarDate.slice(0, 7); const params = new URLSearchParams({ month });
        Object.entries(this.calendarFilters).forEach(([key, value]) => { if (value) params.set(key, value); });
        await this.downloadAdminExport(`/admin/businesses/${this.businessId}/exports/bookings?${params}`, `foglalasok-${month}.xlsx`);
        this.toasts.success('A havi foglalási Excel elkészült.');
      } catch (error) { this.toasts.error(`Az export nem készíthető el: ${error.message}`); }
      finally { this.exportingBookings = false; }
    },

    async exportStatistics() {
      this.exportingStatistics = true;
      try { await this.downloadAdminExport(`/admin/businesses/${this.businessId}/exports/statistics?month=${encodeURIComponent(this.statisticsMonth)}`, `statisztikak-${this.statisticsMonth}.xlsx`); this.toasts.success('A statisztikai Excel elkészült.'); }
      catch (error) { this.toasts.error(`Az export nem készíthető el: ${error.message}`); }
      finally { this.exportingStatistics = false; }
    },

    calendarEntriesForDay(day) {
      const entries = [
        ...this.blocksForDay(day).map((item) => ({ type: 'block', key: `block-${item.id}`, start: item.start_time, item })),
        ...this.itemsForDay(day).map((item) => ({ type: 'booking', key: `booking-${item.id}`, start: item.start_time, item }))
      ];
      return entries.sort((left, right) => String(left.start || '').localeCompare(String(right.start || '')));
    },

    syncModalBodyLock() {
      const open = this.bookingModalOpen
        || this.manualModalOpen
        || this.serviceModalOpen
        || this.reviewModalOpen
        || this.faqModalOpen
        || this.emailLogModalOpen
        || this.confirmDialog.open;

      if (open && !this.modalWasOpen) {
        const active = document.activeElement;
        this.modalReturnFocus = active && typeof active.focus === 'function' ? active : null;
      }

      this.modalWasOpen = open;
      document.body.classList.toggle('modal-open', open);

      if (open) {
        this.$nextTick(() => {
          const modal = this.getOpenModalElement();
          if (!modal || modal.contains(document.activeElement)) return;
          const focusable = this.getModalFocusableElements(modal);
          (focusable[0] || modal).focus();
        });
      }

      if (!open && this.modalReturnFocus) {
        const returnFocus = this.modalReturnFocus;
        this.modalReturnFocus = null;
        window.requestAnimationFrame(() => {
          if (document.contains(returnFocus) && typeof returnFocus.focus === 'function') {
            returnFocus.focus();
          }
        });
      }
    },

    getOpenModalElement() {
      const backdrops = document.querySelectorAll('.modal-backdrop');
      return backdrops[backdrops.length - 1]?.querySelector('.modal-dialog') || null;
    },

    getModalFocusableElements(modal) {
      if (!modal) return [];
      return [...modal.querySelectorAll(
        'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )].filter((element) => {
        const style = window.getComputedStyle(element);
        return !element.hidden && element.getClientRects().length > 0
          && style.display !== 'none' && style.visibility !== 'hidden';
      });
    },

    confirmAction({
      title = 'Megerősítés',
      message = '',
      details = [],
      confirmLabel = 'Folytatás',
      cancelLabel = 'Mégse',
      danger = false
    } = {}) {
      if (this.confirmDialog.open && typeof this.confirmDialog.resolve === 'function') {
        this.confirmDialog.resolve(false);
      }

      return new Promise((resolve) => {
        this.confirmDialog = {
          open: true,
          title,
          message,
          details: Array.isArray(details) ? details : [],
          confirmLabel,
          cancelLabel,
          danger,
          resolve
        };
        this.syncModalBodyLock();
        this.$nextTick(() => this.$refs.confirmPrimaryButton?.focus());
      });
    },

    closeConfirmDialog(result = false) {
      const resolve = this.confirmDialog.resolve;
      this.confirmDialog = createConfirmDialog();
      this.syncModalBodyLock();
      if (typeof resolve === 'function') resolve(!!result);
    },

    handleKeydown(event) {
      const modal = this.getOpenModalElement();

      if (event.key === 'Tab' && modal) {
        this.trapModalFocus(event);
        return;
      }

      if (event.key !== 'Escape') return;
      if (this.confirmDialog.open) this.closeConfirmDialog(false);
      else if (this.bookingModalOpen) this.closeBookingModal();
      else if (this.manualModalOpen) this.closeManualModal();
      else if (this.serviceModalOpen) this.closeServiceModal();
      else if (this.reviewModalOpen) this.closeReviewModal();
      else if (this.faqModalOpen) this.closeFaqModal();
      else if (this.emailLogModalOpen) this.closeEmailLogModal();
    },

    trapModalFocus(event) {
      const modal = this.getOpenModalElement();
      if (!modal) return;

      const focusable = this.getModalFocusableElements(modal);
      if (!focusable.length) {
        event.preventDefault();
        if (typeof modal.focus === 'function') modal.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (!modal.contains(document.activeElement)) {
        event.preventDefault();
        first.focus();
        return;
      }

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
        return;
      }

      if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },

    bindActivityTracking() {
      for (const eventName of ADMIN_ACTIVITY_EVENTS) {
        window.addEventListener(eventName, this.recordActivity, { passive: true });
      }
      document.addEventListener('visibilitychange', this.handleVisibilityChange);
    },

    unbindActivityTracking() {
      for (const eventName of ADMIN_ACTIVITY_EVENTS) {
        window.removeEventListener(eventName, this.recordActivity);
      }
      document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    },

    recordActivity() {
      if (!this.token) return;
      const now = Date.now();
      if (now - this.lastActivityWriteAt < 60 * 1000) return;
      this.lastActivityWriteAt = now;
      localStorage.setItem(ADMIN_ACTIVITY_KEY, String(now));
    },

    isClientSessionExpired() {
      if (!this.token) return false;
      const stored = Number(localStorage.getItem(ADMIN_ACTIVITY_KEY));
      return Number.isFinite(stored) && stored > 0 && Date.now() - stored >= ADMIN_IDLE_TIMEOUT_MS;
    },

    checkClientSession() {
      if (this.token && this.isClientSessionExpired()) {
        this.expireSession('A munkameneted 3 nap inaktivitás után lejárt.');
      }
    },

    async sendSessionHeartbeat() {
      if (!this.token || document.visibilityState !== 'visible') return;
      const lastActivity = Number(localStorage.getItem(ADMIN_ACTIVITY_KEY));
      if (!lastActivity || Date.now() - lastActivity > 30 * 60 * 1000) return;

      try {
        await api('/auth/me', { token: this.token });
      } catch (error) {
        if (error.status !== 401) {
          console.warn('A munkamenet életben tartása sikertelen:', error);
        }
      }
    },

    handleVisibilityChange() {
      if (document.visibilityState !== 'visible') return;
      this.checkClientSession();
      this.sendSessionHeartbeat();
    },

    handleAuthExpired(event) {
      this.expireSession(event?.detail?.message || 'A munkameneted lejárt. Jelentkezz be újra.');
    },

    persistSession(response) {
      this.token = response.token || this.token;
      this.currentUser = response.user || this.currentUser;
      this.businessId = Number(response.user?.business_id || this.businessId) || null;
      this.syncAdminSecurityForms();

      if (this.token) localStorage.setItem(ADMIN_TOKEN_KEY, this.token);
      if (this.businessId) localStorage.setItem(ADMIN_BUSINESS_KEY, String(this.businessId));
      localStorage.setItem(ADMIN_ACTIVITY_KEY, String(Date.now()));
      this.lastActivityWriteAt = Date.now();
    },

    clearSessionState() {
      this.token = '';
      this.businessId = null;
      this.currentUser = null;
      this.adminSessions = [];
      this.adminEmailDigits = ['', '', '', '', '', ''];
      localStorage.removeItem(ADMIN_TOKEN_KEY);
      localStorage.removeItem(ADMIN_BUSINESS_KEY);
      localStorage.removeItem(ADMIN_ACTIVITY_KEY);
      this.bookings = [];
      this.stats = {};
      this.blockedTimes = [];
      this.services = [];
      this.reviews = [];
      this.faqs = [];
      this.customers = [];
      this.selectedCustomer = null;
      this.customerBookings = [];
      this.reminderLogs = [];
      this.reminderStats = {};
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

    expireSession(message) {
      const hadToken = Boolean(this.token);
      this.clearSessionState();
      if (hadToken && message) this.toasts.error(message);
    },

    async restoreSession() {
      try {
        const response = await api('/auth/me', { token: this.token });
        this.persistSession(response);
        await Promise.all([this.refresh(), this.loadWebsite(), this.loadWorkingHours()]);
      } catch (error) {
        this.expireSession(error.message || 'A munkameneted lejárt. Jelentkezz be újra.');
      }
    },

    async login() {
      this.loggingIn = true;
      try {
        const response = await api('/auth/login', { method: 'POST', body: JSON.stringify(this.credentials) });
        this.persistSession(response);
        this.credentials.password = '';
        this.toasts.success('Sikeres bejelentkezés.');
        await Promise.all([this.refresh(), this.loadWebsite(), this.loadWorkingHours()]);
      } catch (error) {
        if (error.status === 403 && error.data?.code === 'ADMIN_EMAIL_UNVERIFIED') {
          this.ownerActivation.email = error.data.email || this.credentials.email;
          this.authMode = 'activate';
        }
        this.toasts.error(`Sikertelen bejelentkezés: ${error.message}`);
      } finally {
        this.loggingIn = false;
      }
    },

    showAuthMode(mode) {
      this.authMode = mode;
      if (mode === 'forgot') this.passwordReset.email = this.credentials.email;
      if (mode === 'activate') this.ownerActivation.email = this.credentials.email;
    },

    adminCodeValue(group) {
      return (this[group] || []).join('');
    },

    focusAdminCode(group, index) {
      this.$nextTick(() => document.querySelector(`[data-code-group="${group}"][data-code-index="${index}"]`)?.focus());
    },

    fillAdminCode(group, value) {
      const digits = String(value || '').replace(/\D/g, '').slice(0, 6).split('');
      this[group] = Array.from({ length: 6 }, (_, index) => digits[index] || '');
      this.focusAdminCode(group, Math.min(digits.length, 5));
    },

    handleAdminCodeInput(group, index, event) {
      const value = String(event.target?.value || '').replace(/\D/g, '');
      if (value.length > 1) {
        this.fillAdminCode(group, value);
        return;
      }
      this[group][index] = value.slice(-1);
      if (value && index < 5) this.focusAdminCode(group, index + 1);
    },

    handleAdminCodePaste(group, event) {
      event.preventDefault();
      this.fillAdminCode(group, event.clipboardData?.getData('text') || '');
    },

    handleAdminCodeKeydown(group, index, event) {
      if (event.key === 'Backspace' && !this[group][index] && index > 0) {
        this[group][index - 1] = '';
        this.focusAdminCode(group, index - 1);
      }
      if (event.key === 'ArrowLeft' && index > 0) this.focusAdminCode(group, index - 1);
      if (event.key === 'ArrowRight' && index < 5) this.focusAdminCode(group, index + 1);
    },

    async resendOwnerActivation() {
      if (!this.ownerActivation.email || this.loggingIn) return;
      this.loggingIn = true;
      try {
        const response = await api('/auth/owner/resend-verification', {
          method: 'POST', body: JSON.stringify({ email: this.ownerActivation.email })
        });
        this.toasts.success(response.message);
      } catch (error) {
        this.toasts.error(error.message);
      } finally {
        this.loggingIn = false;
      }
    },

    async activateOwner() {
      const code = this.adminCodeValue('ownerActivationDigits');
      if (code.length !== 6) return this.toasts.error('Add meg mind a hat számjegyet.');
      this.loggingIn = true;
      try {
        const response = await api('/auth/owner/activate', {
          method: 'POST',
          body: JSON.stringify({ ...this.ownerActivation, code })
        });
        this.persistSession(response);
        this.ownerActivation.password = '';
        this.ownerActivation.password_confirmation = '';
        this.ownerActivationDigits = ['', '', '', '', '', ''];
        this.authMode = 'login';
        this.toasts.success('A tulajdonosi fiók aktiválva és bejelentkezve.');
        await Promise.all([this.refresh(), this.loadWebsite(), this.loadWorkingHours()]);
      } catch (error) {
        this.toasts.error(error.message);
      } finally {
        this.loggingIn = false;
      }
    },

    async requestAdminPasswordReset() {
      if (!this.passwordReset.email || this.loggingIn) return;
      this.loggingIn = true;
      try {
        const response = await api('/auth/password/forgot', {
          method: 'POST', body: JSON.stringify({ email: this.passwordReset.email })
        });
        this.authMode = 'reset';
        this.toasts.success(response.message);
      } catch (error) {
        this.toasts.error(error.message);
      } finally {
        this.loggingIn = false;
      }
    },

    async resetAdminPassword() {
      const code = this.adminCodeValue('passwordResetDigits');
      if (code.length !== 6) return this.toasts.error('Add meg mind a hat számjegyet.');
      this.loggingIn = true;
      try {
        const response = await api('/auth/password/reset', {
          method: 'POST', body: JSON.stringify({ ...this.passwordReset, code })
        });
        this.persistSession(response);
        this.passwordReset.password = '';
        this.passwordReset.password_confirmation = '';
        this.passwordResetDigits = ['', '', '', '', '', ''];
        this.authMode = 'login';
        this.toasts.success('Új jelszó beállítva, bejelentkeztél.');
        await Promise.all([this.refresh(), this.loadWebsite(), this.loadWorkingHours()]);
      } catch (error) {
        this.toasts.error(error.message);
      } finally {
        this.loggingIn = false;
      }
    },

    async logout() {
      const token = this.token;
      try {
        if (token) {
          await api('/auth/logout', { method: 'POST', token });
        }
      } catch (error) {
        if (error.status !== 401) {
          this.toasts.error(`A szerveroldali kijelentkezés nem sikerült: ${error.message}`);
        }
      } finally {
        this.clearSessionState();
      }
    },

    async refresh() {
      if (!this.token || !this.businessId) return;
      this.loading = true;
      try {
        const { start, end } = this.calendarRange;
        const [summary, bookings, blocks, services, today, calendar, emailOverview] = await Promise.all([
          api(`/admin/businesses/${this.businessId}/summary`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/bookings`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/blocked-times`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/services`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/today`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/calendar?start=${start}&end=${end}`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/email-logs?per_page=10&page=1`, { token: this.token })
        ]);

        this.stats = summary.data || {};
        this.bookings = bookings.data || [];
        this.blockedTimes = blocks.data || [];
        this.services = services.data || [];
        this.todayBookings = today.data || [];
        this.calendarItems = calendar.data || [];
        this.calendarBlocks = calendar.blocks || [];
        this.emailStats = emailOverview.stats || this.emailStats;
        this.emailSystem = emailOverview.system || this.emailSystem;

        const firstActiveService = this.services.find((service) => service.active) || this.services[0];
        if (!this.services.some((service) => service.active && String(service.id) === String(this.timelineServiceId))) {
          this.timelineServiceId = firstActiveService?.id || '';
        }
        if (!this.services.some((service) => service.active && String(service.id) === String(this.manual.service_id))) {
          this.manual.service_id = firstActiveService?.id || '';
        }

        await this.loadMonthAvailability();

        if (this.calendarMode === 'day') {
          await this.loadDay(this.selectedDayDate, false);
        }
      } catch (error) {
        if (error.status === 401) {
          this.expireSession('A munkameneted lejárt, jelentkezz be újra.');
        } else {
          this.toasts.error(`Nem sikerült frissíteni: ${error.message}`);
        }
      } finally {
        this.loading = false;
      }
    },

    async loadMonthAvailability() {
      this.monthAvailability = {};
      if (!this.token || !this.timelineServiceId) return;

      const { start, end } = this.calendarRange;
      this.monthAvailabilityLoading = true;
      try {
        const params = new URLSearchParams({
          service_id: this.timelineServiceId,
          start,
          end
        });
        const response = await api(
          `/admin/businesses/${this.businessId}/availability-calendar?${params}`,
          { token: this.token }
        );
        this.monthAvailability = Object.fromEntries(
          (response.data || []).map((item) => [item.date, item])
        );
      } catch (error) {
        this.toasts.error(`A havi szabadhely-adatok nem tölthetők be: ${error.message}`);
      } finally {
        this.monthAvailabilityLoading = false;
      }
    },

    async handleTimelineServiceChange() {
      await this.loadMonthAvailability();
      if (this.calendarMode === 'day') {
        await this.loadDayAvailability();
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
        const response = await api(`/admin/businesses/${this.businessId}/day?date=${encodeURIComponent(dayKey)}`, { token: this.token });
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
        const response = await api(`/admin/businesses/${this.businessId}/slots?${params}`, { token: this.token });
        this.dayAvailableSlots = response.data || [];
      } catch (error) {
        this.toasts.error(`A szabad időpontok nem tölthetők be: ${error.message}`);
      }
    },

    quarterCellsForHour(hour) {
      const step = Number(this.business.bookingRules?.slotIntervalMinutes || this.adminSettings.slot_interval_minutes || 15);
      const minutes = Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step).filter((minute) => minute < 60);
      return minutes.map((minute) => {
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

    async rebookBooking(booking) {
      if (!booking) return;

      const sourceBooking = { ...booking };

      this.bookingModalOpen = false;
      this.selectedBooking = null;

      await this.rebookCustomer({
        name: sourceBooking.customer_name || '',
        email: sourceBooking.customer_contact || '',
        phone: sourceBooking.customer_phone || ''
      }, sourceBooking);
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

    async loadWorkingHours() {
      if (!this.token) return;
      this.workingHoursLoading = true;
      try {
        const response = await api(
          `/admin/businesses/${this.businessId}/working-hours`,
          { token: this.token }
        );
        this.workWeek = Array.isArray(response.data) && response.data.length
          ? response.data
          : createDefaultWorkWeek();
      } catch (error) {
        this.toasts.error(`A munkaidő nem tölthető be: ${error.message}`);
      } finally {
        this.workingHoursLoading = false;
      }
    },

    async saveWorkingHours(force = false) {
      if (!this.workingHoursValid || this.savingWorkingHours) return;
      this.savingWorkingHours = true;
      try {
        const response = await api(
          `/admin/businesses/${this.businessId}/working-hours`,
          {
            method: 'PUT',
            token: this.token,
            body: JSON.stringify({
              days: this.workWeek,
              sync_public_text: this.syncPublicOpeningHours,
              force
            })
          }
        );

        this.workWeek = response.data || this.workWeek;
        if (this.syncPublicOpeningHours && response.public_opening_hours !== undefined) {
          this.websiteForm.opening_hours = response.public_opening_hours || '';
          this.business.openingHours = response.public_opening_hours || '';
        }
        this.toasts.success('A valódi foglalható munkaidő elmentve.');
        await this.refresh();
      } catch (error) {
        const details = error.data || {};
        if (details.requires_confirmation && !force) {
          const examples = Array.isArray(details.conflicts)
            ? details.conflicts.slice(0, 5).map((item) =>
                `${item.date} ${String(item.start_time || '').slice(0, 5)} – ${item.customer_name}`
              ).join('\n')
            : '';

          this.savingWorkingHours = false;
          const approved = await this.confirmAction({
            title: 'Munkaidő módosítása',
            message: details.message || 'Az új munkaidő meglévő foglalásokat érint.',
            details: [
              ...(examples ? examples.split('\n') : []),
              ...(Number(details.conflict_count || 0) > 5 ? ['…és további foglalások.'] : [])
            ],
            confirmLabel: 'Módosítás folytatása',
            danger: true
          });
          if (approved) await this.saveWorkingHours(true);
          return;
        }

        this.toasts.error(`A munkaidő mentése nem sikerült: ${error.message}`);
      } finally {
        this.savingWorkingHours = false;
      }
    },

    syncBlockDates() {
      if (this.block.end_date < this.block.start_date) this.block.end_date = this.block.start_date;
    },

    async saveBlock(force = false) {
      if (this.blockingTime) return;
      this.blockingTime = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/blocked-times`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify({ ...this.block, force })
        });
        const count = Number(response.count || 1);
        this.toasts.success(count > 1 ? `${count} nap lezárva.` : 'Időszak lezárva.');
        this.block.reason = '';
        await this.refresh();
      } catch (error) {
        if (error.data?.requires_confirmation && !force) {
          const preview = (error.data.conflicts || []).slice(0, 5).map((item) =>
            `${item.date} ${this.shortTime(item.start_time)}–${this.shortTime(item.end_time)} · ${item.customer_name}`
          ).join('\n');
          const extra = Number(error.data.conflict_count || 0) > 5
            ? `\n…és még ${Number(error.data.conflict_count) - 5} foglalás.`
            : '';

          this.blockingTime = false;
          const approved = await this.confirmAction({
            title: 'Lezárás aktív foglalásokkal',
            message: error.message,
            details: [
              ...(preview ? preview.split('\n') : []),
              ...(extra ? [extra.trim()] : [])
            ],
            confirmLabel: 'Lezárás mégis',
            danger: true
          });
          if (approved) await this.saveBlock(true);
          return;
        }

        this.toasts.error(`Nem sikerült menteni: ${error.message}`);
      } finally {
        this.blockingTime = false;
      }
    },

    async deleteBlockGroup(group) {
      const dateLabel = group.start_date === group.end_date ? group.start_date : `${group.start_date} – ${group.end_date}`;
      const approved = await this.confirmAction({
        title: 'Blokkolás törlése',
        message: `Biztosan törlöd ezt a blokkolást (${dateLabel})?`,
        confirmLabel: 'Törlés',
        danger: true
      });
      if (!approved) return;
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
        customer_phone: '',
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
        const response = await api(`/admin/businesses/${this.businessId}/slots?${params}`, { token: this.token });
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
        await api(`/admin/businesses/${this.businessId}/bookings`, {
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
      await Promise.all([this.loadEmailCenter(), this.loadReminderLogs()]);
    },

    async loadReminderLogs() {
      if (!this.token || !this.businessId) return;
      this.remindersLoading = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/reminder-logs`, { token: this.token });
        this.reminderLogs = response.data || [];
        this.reminderStats = response.stats || {};
      } catch (error) {
        this.toasts.error(`Az emlékeztető napló nem tölthető be: ${error.message}`);
      } finally {
        this.remindersLoading = false;
      }
    },

    async dispatchRemindersNow() {
      if (this.remindersLoading) return;
      this.remindersLoading = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/reminders/dispatch`, {
          method: 'POST', token: this.token
        });
        this.toasts.success(`${response.message} Sorba állítva: ${response.data?.queued || 0}.`);
        await Promise.all([this.loadReminderLogs(), this.loadEmailLogs()]);
      } catch (error) {
        this.toasts.error(`Az emlékeztetők nem ellenőrizhetők: ${error.message}`);
      } finally {
        this.remindersLoading = false;
      }
    },

    reminderStatusLabel(status) {
      return { queued: 'Várólistán', sent: 'Elküldve', skipped: 'Kihagyva', failed: 'Sikertelen' }[status] || status;
    },

    async openCustomersTab() {
      this.activeTab = 'customers';
      await this.loadCustomers();
    },

    async loadCustomers() {
      if (!this.token || !this.businessId) return;
      this.customersLoading = true;
      try {
        const params = new URLSearchParams();
        if (this.customerSearch) params.set('q', this.customerSearch);
        const response = await api(`/admin/businesses/${this.businessId}/customers?${params}`, { token: this.token });
        this.customers = response.data || [];
      } catch (error) {
        this.toasts.error(`Az ügyféltörténet nem tölthető be: ${error.message}`);
      } finally {
        this.customersLoading = false;
      }
    },

    async openCustomer(customer) {
      this.customersLoading = true;
      try {
        const response = await api(`/admin/customers/${customer.id}`, { token: this.token });
        this.selectedCustomer = response.data;
        this.customerBookings = response.bookings || [];
      } catch (error) {
        this.toasts.error(`Az ügyfél adatlapja nem tölthető be: ${error.message}`);
      } finally {
        this.customersLoading = false;
      }
    },

    async saveCustomerNote() {
      if (!this.selectedCustomer || this.savingCustomer) return;
      this.savingCustomer = true;
      try {
        const response = await api(`/admin/customers/${this.selectedCustomer.id}`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify({
            phone: this.selectedCustomer.phone || null,
            admin_note: this.selectedCustomer.admin_note || null
          })
        });
        this.selectedCustomer = { ...this.selectedCustomer, ...(response.data || {}) };
        this.toasts.success('Belső ügyfélmegjegyzés elmentve.');
        await this.loadCustomers();
      } catch (error) {
        this.toasts.error(`Az ügyféladat nem menthető: ${error.message}`);
      } finally {
        this.savingCustomer = false;
      }
    },

    async rebookCustomer(customer, booking = null) {
      const service = this.services.find((item) => item.active && String(item.id) === String(booking?.service_id))
        || this.services.find((item) => item.active);
      this.manual = {
        service_id: service?.id || '',
        date: todayKey(),
        time: '',
        customer_name: customer.name || '',
        customer_contact: customer.email || '',
        customer_phone: customer.phone || '',
        customer_note: ''
      };
      this.manualModalOpen = true;
      this.syncModalBodyLock();
      await this.loadManualSlots();
      this.$nextTick(() => this.$refs.manualNameInput?.focus());
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
          api(`/admin/businesses/${this.businessId}/email-logs?${this.emailLogQuery()}`, { token: this.token }),
          api(`/admin/businesses/${this.businessId}/email-settings`, { token: this.token })
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
          `/admin/businesses/${this.businessId}/email-logs?${this.emailLogQuery()}`,
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
        const response = await api(`/admin/businesses/${this.businessId}/email-settings`, {
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

    async resetEmailSettingsToDefaults() {
      const approved = await this.confirmAction({
        title: 'Email sablonok visszaállítása',
        message: 'Visszaállítod az email szövegeket az alapértelmezett értékekre? A módosítás csak mentés után lesz végleges.',
        confirmLabel: 'Alapértékek betöltése'
      });
      if (!approved) return;
      this.emailSettings = JSON.parse(JSON.stringify(this.emailDefaultSettings || createEmptyEmailSettings()));
      this.toasts.success('Az alapértékek betöltve. A véglegesítéshez nyomd meg a Mentés gombot.');
    },

    async sendTestEmail() {
      if (!this.testEmailValid || this.sendingTestEmail) return;
      this.sendingTestEmail = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/email-test`, {
          method: 'POST',
          token: this.token,
          body: JSON.stringify(this.testEmail)
        });
        const log = response.data || {};
        if (log.status === 'sent') this.toasts.success('Teszt email elküldve. Nézd meg a postaládát és a spam mappát is.');
        else if (log.status === 'pending') this.toasts.success('A teszt email várólistára került.');
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
      const approved = await this.confirmAction({
        title: 'Email újraküldése',
        message: 'Biztosan újraküldöd ezt az emailt?',
        details: [log.recipient_email],
        confirmLabel: 'Újraküldés'
      });
      if (!approved) return;

      this.resendingEmailLogId = log.id;
      try {
        const response = await api(`/admin/email-logs/${log.id}/resend`, {
          method: 'POST',
          token: this.token
        });
        const newLog = response.data || {};
        if (newLog.status === 'sent') this.toasts.success('Email újraküldve.');
        else if (newLog.status === 'pending') this.toasts.success('Az email újraküldése várólistára került.');
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

    async openProfileTab() {
      this.activeTab = 'profile';
      await this.loadAdminSecurity();
    },

    async openSettingsTab() {
      this.activeTab = 'settings';
      await this.loadAdminSettings();
    },

    syncAdminSecurityForms() {
      this.adminProfileForm.name = this.currentUser?.name || '';
      this.adminEmailForm.email = this.currentUser?.pending_email || '';
      this.adminEmailForm.current_password = '';
    },

    async loadAdminSecurity() {
      if (!this.token || this.securityLoading) return;
      this.securityLoading = true;
      try {
        const response = await api('/auth/sessions', { token: this.token });
        this.adminSessions = response.data || [];
      } catch (error) {
        this.toasts.error(`A munkamenetek nem tölthetők be: ${error.message}`);
      } finally {
        this.securityLoading = false;
      }
    },

    async saveAdminProfile() {
      if (this.savingAdminProfile) return;
      this.savingAdminProfile = true;
      try {
        const response = await api('/auth/profile', {
          method: 'PATCH', token: this.token, body: JSON.stringify(this.adminProfileForm)
        });
        this.persistSession(response);
        this.toasts.success('Az adminprofil neve frissült.');
      } catch (error) {
        this.toasts.error(`A profil nem menthető: ${error.message}`);
      } finally {
        this.savingAdminProfile = false;
      }
    },

    async requestAdminEmailChange() {
      if (this.requestingAdminEmail) return;
      this.requestingAdminEmail = true;
      try {
        const response = await api('/auth/email/change', {
          method: 'POST', token: this.token, body: JSON.stringify(this.adminEmailForm)
        });
        this.persistSession(response);
        this.adminEmailForm.current_password = '';
        this.adminEmailDigits = ['', '', '', '', '', ''];
        this.toasts.success(response.message);
      } catch (error) {
        this.toasts.error(`Az e-mail-csere nem indítható: ${error.message}`);
      } finally {
        this.requestingAdminEmail = false;
      }
    },

    async verifyAdminEmailChange() {
      const code = this.adminCodeValue('adminEmailDigits');
      if (code.length !== 6) return this.toasts.error('Add meg mind a hat számjegyet.');
      this.verifyingAdminEmail = true;
      try {
        const response = await api('/auth/email/verify', {
          method: 'POST', token: this.token, body: JSON.stringify({ code })
        });
        this.persistSession(response);
        this.adminEmailDigits = ['', '', '', '', '', ''];
        this.toasts.success(response.message);
        await this.loadAdminSecurity();
      } catch (error) {
        this.toasts.error(`Az új e-mail-cím nem erősíthető meg: ${error.message}`);
      } finally {
        this.verifyingAdminEmail = false;
      }
    },

    async changeAdminPassword() {
      if (this.changingAdminPassword) return;
      this.changingAdminPassword = true;
      try {
        const response = await api('/auth/password', {
          method: 'PATCH', token: this.token, body: JSON.stringify(this.adminPasswordForm)
        });
        this.adminPasswordForm = { current_password: '', password: '', password_confirmation: '' };
        this.toasts.success(response.message);
        await this.loadAdminSecurity();
      } catch (error) {
        this.toasts.error(`A jelszó nem módosítható: ${error.message}`);
      } finally {
        this.changingAdminPassword = false;
      }
    },

    adminSessionLabel(session) {
      const source = String(session?.user_agent || 'Ismeretlen böngésző');
      if (/Edg\//.test(source)) return 'Microsoft Edge';
      if (/Firefox\//.test(source)) return 'Mozilla Firefox';
      if (/Chrome\//.test(source)) return 'Google Chrome';
      if (/Safari\//.test(source)) return 'Safari';
      return 'Ismeretlen böngésző';
    },

    async revokeAdminSession(session) {
      if (!session?.id || this.revokingAdminSessionId) return;
      const approved = await this.confirmAction({
        title: session.current ? 'Kijelentkezés erről az eszközről' : 'Munkamenet visszavonása',
        message: 'Biztosan megszünteted ezt az admin munkamenetet?',
        details: [this.adminSessionLabel(session), session.ip_address || 'Ismeretlen IP'],
        confirmLabel: 'Kijelentkeztetés', danger: true
      });
      if (!approved) return;
      this.revokingAdminSessionId = session.id;
      try {
        const response = await api(`/auth/sessions/${session.id}`, { method: 'DELETE', token: this.token });
        if (response.current) this.clearSessionState();
        else await this.loadAdminSecurity();
        this.toasts.success(response.message);
      } catch (error) {
        this.toasts.error(`A munkamenet nem vonható vissza: ${error.message}`);
      } finally {
        this.revokingAdminSessionId = null;
      }
    },

    async logoutAllAdminSessions() {
      const approved = await this.confirmAction({
        title: 'Kijelentkezés minden eszközről',
        message: 'Minden admin munkamenet azonnal megszűnik, ezen az eszközön is.',
        confirmLabel: 'Minden munkamenet törlése', danger: true
      });
      if (!approved) return;
      try {
        const response = await api('/auth/logout-all', { method: 'POST', token: this.token });
        this.clearSessionState();
        this.toasts.success(response.message);
      } catch (error) {
        this.toasts.error(`A kijelentkezés nem sikerült: ${error.message}`);
      }
    },

    async loadAdminSettings() {
      if (!this.token || !this.businessId || this.settingsLoading) return;
      this.settingsLoading = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/settings`, { token: this.token });
        this.adminSettings = { ...createDefaultAdminSettings(), ...(response.data || {}) };
        this.settingsTimezones = Array.isArray(response.timezones) ? response.timezones : [];
        this.syncLegalEditors();
      } catch (error) {
        this.toasts.error(`A foglalási és jogi beállítások nem tölthetők be: ${error.message}`);
      } finally {
        this.settingsLoading = false;
      }
    },

    async saveAdminSettings() {
      if (this.savingAdminSettings) return;
      this.savingAdminSettings = true;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/settings`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify(this.adminSettings)
        });
        this.adminSettings = { ...createDefaultAdminSettings(), ...(response.data || {}) };
        this.syncLegalEditors();
        this.business.timezone = this.adminSettings.timezone;
        this.business.hidePrices = !!this.adminSettings.hide_prices;
        this.business.bookingRules = {
          ...(this.business.bookingRules || {}),
          minAdvanceMinutes: this.adminSettings.min_advance_minutes,
          maxAdvanceDays: this.adminSettings.max_advance_days,
          slotIntervalMinutes: this.adminSettings.slot_interval_minutes,
          cancellationDeadlineMinutes: this.adminSettings.cancellation_deadline_minutes,
          rescheduleDeadlineMinutes: this.adminSettings.reschedule_deadline_minutes
        };
        this.toasts.success('Foglalási szabályok, adatmegőrzés és jogi tartalmak elmentve.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`A beállítások mentése sikertelen: ${error.message}`);
      } finally {
        this.savingAdminSettings = false;
      }
    },

    legalEditorInput(field, event) {
      this.adminSettings[field] = event.currentTarget?.innerHTML || '';
      this.rememberLegalSelection(field);
    },

    legalEditorElement(field) {
      const reference = this.$refs[`legalEditor_${field}`];
      return Array.isArray(reference) ? reference[0] : reference;
    },

    rememberLegalSelection(field) {
      const editor = this.legalEditorElement(field);
      const selection = window.getSelection();
      if (!(editor instanceof HTMLElement) || !selection?.rangeCount) return;

      const range = selection.getRangeAt(0);
      if (editor.contains(range.commonAncestorContainer)) {
        legalEditorSelections.set(field, range.cloneRange());
      }
    },

    applyLegalCommand(field, command, value = null) {
      const editor = this.legalEditorElement(field);
      if (!(editor instanceof HTMLElement)) return;

      const selection = window.getSelection();
      const savedRange = legalEditorSelections.get(field);
      editor.focus({ preventScroll: true });
      if (selection && savedRange && editor.contains(savedRange.commonAncestorContainer)) {
        selection.removeAllRanges();
        selection.addRange(savedRange);
      }

      document.execCommand(command, false, value);
      this.adminSettings[field] = editor.innerHTML;
      this.rememberLegalSelection(field);
    },

    syncLegalEditors() {
      this.$nextTick(() => {
        this.legalEditorDocuments.forEach(({ field }) => {
          const editor = this.legalEditorElement(field);
          if (editor instanceof HTMLElement && editor.innerHTML !== (this.adminSettings[field] || '')) {
            editor.innerHTML = this.adminSettings[field] || '';
          }
        });
      });
    },

    async anonymizeBooking(booking) {
      if (!booking?.id) return;
      const approved = await this.confirmAction({
        title: 'Ügyféladatok végleges törlése',
        message: 'A név, e-mail és megjegyzés véglegesen anonimizálódik. Ez nem vonható vissza.',
        details: [`${booking.customer_name} · ${booking.date} ${this.shortTime(booking.start_time)}`],
        confirmLabel: 'Adatok törlése',
        danger: true
      });
      if (!approved) return;

      try {
        const response = await api(`/admin/bookings/${booking.id}/anonymize`, {
          method: 'POST',
          token: this.token
        });
        this.selectedBooking = response.data || null;
        this.toasts.success('A foglalás személyes adatai anonimizálva.');
        await this.refresh();
      } catch (error) {
        this.toasts.error(`Az anonimizálás sikertelen: ${error.message}`);
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
        const response = await api(`/admin/businesses/${this.businessId}/website`, { token: this.token });
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
        const response = await api(`/admin/businesses/${this.businessId}/website`, {
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
        const response = await api(`/admin/businesses/${this.businessId}/logo`, {
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
      const approved = await this.confirmAction({
        title: 'Logó törlése',
        message: 'Biztosan törlöd a feltöltött logót? Ezután a rendszer monogramot használ.',
        confirmLabel: 'Logó törlése',
        danger: true
      });
      if (!approved) return;
      try {
        const response = await api(`/admin/businesses/${this.businessId}/logo`, { method: 'DELETE', token: this.token });
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

    async updateReviewVisibility(review, payload, successMessage) {
      try {
        await api(`/admin/reviews/${review.id}`, {
          method: 'PATCH',
          token: this.token,
          body: JSON.stringify(payload)
        });
        this.toasts.success(successMessage);
        this.websitePreviewVersion += 1;
        await this.loadWebsite();
      } catch (error) {
        this.toasts.error(`A vélemény állapota nem módosítható: ${error.message}`);
      }
    },

    approveReview(review) {
      return this.updateReviewVisibility(
        review,
        { moderation_status: 'approved', active: true },
        'A vélemény jóváhagyva és megjelenik a weboldalon.'
      );
    },

    hideReview(review) {
      return this.updateReviewVisibility(
        review,
        { active: false },
        'A vélemény elrejtve a weboldalról.'
      );
    },

    async rejectReview(review) {
      const approved = await this.confirmAction({
        title: 'Beküldött vélemény elutasítása',
        message: 'A vélemény az admin naplóban megmarad, de nem jelenik meg a weboldalon.',
        details: [review.author],
        confirmLabel: 'Elutasítás',
        danger: true
      });
      if (!approved) return;
      await this.updateReviewVisibility(
        review,
        { moderation_status: 'rejected', active: false },
        'A vélemény elutasítva.'
      );
    },

    closeReviewModal() {
      if (this.savingReview) return;
      this.reviewModalOpen = false;
      this.syncModalBodyLock();
    },

    resetReviewForm() {
      this.reviewForm = { id: null, author: '', text: '', rating: 5, active: true, sort_order: this.reviews.length + 1 };
    },

    async saveReview() {
      if (!isPersonName(this.reviewForm.author) || String(this.reviewForm.text || '').trim().length < 3) {
        this.toasts.error('Adj meg érvényes nevet és legalább 3 karakteres véleményszöveget.');
        return;
      }
      this.savingReview = true;
      try {
        const path = this.reviewForm.id ? `/admin/reviews/${this.reviewForm.id}` : `/admin/businesses/${this.businessId}/reviews`;
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
      const approved = await this.confirmAction({
        title: 'Vélemény törlése',
        message: 'Biztosan törlöd ezt a véleményt?',
        details: [review.author],
        confirmLabel: 'Törlés',
        danger: true
      });
      if (!approved) return;
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
        const path = this.faqForm.id ? `/admin/faqs/${this.faqForm.id}` : `/admin/businesses/${this.businessId}/faqs`;
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
      const approved = await this.confirmAction({
        title: 'GYIK elem törlése',
        message: 'Biztosan törlöd ezt a kérdés-válasz elemet?',
        details: [faq.question],
        confirmLabel: 'Törlés',
        danger: true
      });
      if (!approved) return;
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
          price_mode: service.price_mode || 'fixed',
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
        price_mode: 'fixed',
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
        price_mode: this.serviceForm.price_mode || 'fixed',
        price_cents: this.serviceForm.price_mode === 'fixed' && this.serviceForm.price_forint !== ''
          ? Number(this.serviceForm.price_forint) * 100
          : null,
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
      const approved = await this.confirmAction({
        title: 'Szolgáltatáskép törlése',
        message: 'Biztosan törlöd a szolgáltatás képét?',
        confirmLabel: 'Kép törlése',
        danger: true
      });
      if (!approved) return;

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
        const path = this.serviceForm.id ? `/admin/services/${this.serviceForm.id}` : `/admin/businesses/${this.businessId}/services`;
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
        const response = await api(`/admin/businesses/${this.businessId}/services/reorder`, {
          method: 'POST', token: this.token, body: JSON.stringify({ items })
        });
        this.services = response.data || [];
      } catch (error) {
        this.toasts.error(`Sorrend mentése sikertelen: ${error.message}`);
      }
    }
  }
}).mount('#adminApp');
