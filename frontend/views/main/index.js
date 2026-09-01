const { createApp, reactive } = Vue;
const {
  api,
  todayKey,
  parseKey,
  isToday,
  formatDateLong,
  formatDuration,
  formatPrice,
  servicePriceLabel,
  calendarDownloadUrl,
  googleCalendarUrl,
  isPersonName,
  isEmail,
  isValidOptionalNote,
  useToasts,
  setBusinessFavicon
} = window.App;
const CUSTOMER_TOKEN_KEY = 'appointment_customer_token';
const CUSTOMER_EXPIRES_KEY = 'appointment_customer_expires_at';
const CUSTOMER_ACCOUNT_KEY = 'appointment_customer_account';
const BOOKING_RETURN_STATE_KEY = 'appointment_booking_return_state';
const MANAGE_RETURN_STATE_KEY = 'appointment_manage_return_state';

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
      monthAvailability: {},
      loadingMonthAvailability: false,
      step: 1,
      bookingCalendarMode: 'month',
      bookingCalendarDate: todayKey(),
      loadingInit: true,
      loadingSlots: false,
      submitting: false,
      reviewSubmitting: false,
      reviewSubmitted: false,
      reviewFormOpen: false,
      confirmedBooking: null,
      manageUrl: '',
      customerToken: localStorage.getItem(CUSTOMER_TOKEN_KEY) || '',
      customerAccount: {},
      customerBookings: [],
      legalModal: {
        open: false,
        title: '',
        content: '',
        url: ''
      },
      legalReturnFocus: null,
      form: {
        customer_name: '',
        customer_contact: '',
        customer_phone: '',
        customer_note: '',
        legal_accepted: false
      },
      reviewForm: {
        author: '',
        email: '',
        rating: 5,
        text: '',
        legal_accepted: false,
        website: ''
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
      return isPersonName(this.form.customer_name)
        && isEmail(this.form.customer_contact)
        && isValidOptionalNote(this.form.customer_note)
        && this.form.legal_accepted === true;
    },

    nameError() {
      if (!this.form.customer_name) return '';
      return isPersonName(this.form.customer_name)
        ? ''
        : 'Csak valódi nevet adj meg: betűk, szóköz, kötőjel, pont vagy aposztróf használható.';
    },

    emailError() {
      if (!this.form.customer_contact) return '';
      return isEmail(this.form.customer_contact) ? '' : 'Adj meg egy érvényes e-mail címet.';
    },

    noteError() {
      if (!this.form.customer_note) return '';
      return isValidOptionalNote(this.form.customer_note)
        ? ''
        : 'A megjegyzés legalább 3, legfeljebb 800 karakter legyen.';
    },

    reviewFormValid() {
      const textLength = String(this.reviewForm.text || '').trim().length;
      return isPersonName(this.reviewForm.author)
        && isEmail(this.reviewForm.email)
        && Number(this.reviewForm.rating) >= 1
        && Number(this.reviewForm.rating) <= 5
        && textLength >= 10
        && textLength <= 1200
        && this.reviewForm.legal_accepted === true
        && !this.reviewForm.website;
    },

    reviewNameError() {
      if (!this.reviewForm.author) return '';
      return isPersonName(this.reviewForm.author)
        ? ''
        : 'Adj meg egy valódi nevet betűkkel.';
    },

    reviewEmailError() {
      if (!this.reviewForm.email) return '';
      return isEmail(this.reviewForm.email) ? '' : 'Adj meg egy érvényes e-mail címet.';
    },

    reviewTextError() {
      if (!this.reviewForm.text) return '';
      const length = String(this.reviewForm.text).trim().length;
      return length >= 10 && length <= 1200
        ? ''
        : 'A vélemény 10 és 1200 karakter közötti legyen.';
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
        const availability = this.monthAvailability[key] || null;
        const isPast = key < this.today;
        const isClosed = !!availability && !availability.has_working_hours;
        const isSoldOut = !!availability && availability.has_working_hours && Number(availability.available_count || 0) === 0;
        const ownBookings = this.ownBookingsByDate[key] || [];

        days.push({
          key,
          dayNumber: cursor.getDate(),
          inCurrentMonth: cursor.getMonth() === focus.getMonth() && cursor.getFullYear() === focus.getFullYear(),
          isToday: key === this.today,
          isPast,
          availability,
          isClosed,
          isSoldOut,
          ownBookings,
          disabled: isPast || ((isClosed || isSoldOut) && ownBookings.length === 0)
        });
      }
      return days;
    },

    ownBookingsByDate() {
      return this.customerBookings
        .filter((booking) => booking.status === 'booked')
        .reduce((groups, booking) => {
          (groups[booking.date] ||= []).push(booking);
          groups[booking.date].sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)));
          return groups;
        }, {});
    },

    ownBookingsForSelectedDate() {
      return this.ownBookingsByDate[this.date] || [];
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
      for (const booking of this.ownBookingsForSelectedDate) {
        points.push(this.timeToMinutes(booking.start_time), this.timeToMinutes(booking.end_time));
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

  watch: {
    business: {
      immediate: true,
      deep: true,
      handler(value) {
        setBusinessFavicon(value);
        this.syncBusinessTheme(value);
      }
    }
  },

  async mounted() {
    window.addEventListener('keydown', this.handleLegalModalKeydown);
    window.addEventListener('storage', this.handleCustomerStorage);
    document.addEventListener('visibilitychange', this.handleCustomerVisibility);

    try {
      const businessResponse = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = businessResponse.data || {};
      document.title = `${this.business.name || 'Időpontfoglalás'} — Online foglalás`;

      const description = document.querySelector('meta[name="description"]');
      if (description && this.business.heroText) description.setAttribute('content', this.business.heroText);

      const serviceResponse = await api(`/businesses/${window.App.config.businessSlug}/services`);
      this.services = serviceResponse.data || [];
      await this.loadCustomerSession();
      await this.restoreBookingReturnState();
    } catch (error) {
      this.toasts.error(`Indítási hiba: ${error.message}`);
    } finally {
      this.loadingInit = false;
    }
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.handleLegalModalKeydown);
    window.removeEventListener('storage', this.handleCustomerStorage);
    document.removeEventListener('visibilitychange', this.handleCustomerVisibility);
    document.body.classList.remove('modal-open');
    document.body.classList.remove('aranyvonal-theme');
  },

  methods: {
    formatDuration,
    formatPrice,

    priceLabel(service) {
      return servicePriceLabel(service, !!this.business.hidePrices);
    },

    syncBusinessTheme(business = {}) {
      const isAranyvonal = String(business.name || '').trim().toLocaleLowerCase('hu-HU') === 'aranyvonal hair studio';
      document.body.classList.toggle('aranyvonal-theme', isAranyvonal);
    },

    openPublicReviewForm() {
      this.reviewSubmitted = false;
      this.reviewFormOpen = true;
      this.prefillCustomerData();
      this.$nextTick(() => {
        this.$refs.publicReviewAuthorInput?.focus();
        this.$refs.publicReviewSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    },

    togglePublicReviewForm() {
      if (this.reviewSubmitting) return;

      if (this.reviewFormOpen || this.reviewSubmitted) {
        this.reviewSubmitted = false;
        this.closePublicReviewForm();
        return;
      }

      this.openPublicReviewForm();
    },

    closePublicReviewForm() {
      if (this.reviewSubmitting) return;
      this.reviewFormOpen = false;
      this.$nextTick(() => this.$refs.publicReviewOpenButton?.focus());
    },

    async submitPublicReview() {
      if (!this.reviewFormValid || this.reviewSubmitting) {
        this.toasts.error('Ellenőrizd a nevet, az e-mail címet, a véleményt és az elfogadó jelölőnégyzetet.');
        return;
      }

      this.reviewSubmitting = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/reviews`, {
          method: 'POST',
          body: JSON.stringify(this.reviewForm)
        });
        this.reviewSubmitted = true;
        this.reviewFormOpen = false;
        this.reviewForm = {
          author: this.customerAccount.name || '',
          email: this.customerAccount.email || '',
          rating: 5,
          text: '',
          legal_accepted: false,
          website: ''
        };
        this.toasts.success(response.message || 'Köszönjük! A véleményedet elküldtük.');
      } catch (error) {
        this.toasts.error(`A vélemény nem küldhető el: ${error.message}`);
      } finally {
        this.reviewSubmitting = false;
      }
    },

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
      this.monthAvailability = {};
      this.bookingCalendarMode = 'month';
      this.bookingCalendarDate = this.today;
      this.date = this.today;
    },

    async movePublicMonth(amount) {
      if (amount < 0 && !this.canMovePublicMonthBack) return;
      const focus = parseKey(this.bookingCalendarDate);
      const next = new Date(focus.getFullYear(), focus.getMonth() + amount, 1);
      this.bookingCalendarDate = this.dateKey(next);
      await this.loadMonthAvailability();
    },

    async goPublicCurrentMonth() {
      this.bookingCalendarDate = this.today;
      await this.loadMonthAvailability();
    },

    async openBookingDay(key) {
      const availability = this.monthAvailability[key];
      const hasOwnBooking = (this.ownBookingsByDate[key] || []).length > 0;
      if (key < this.today || (!hasOwnBooking && availability && Number(availability.available_count || 0) === 0)) return;
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
      const step = Number(this.business.bookingRules?.slotIntervalMinutes || 15);
      const minutes = Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step).filter((minute) => minute < 60);
      return minutes.map((minute) => {
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

    ownBookingsForHour(hour) {
      return this.ownBookingsForSelectedDate.filter((booking) => {
        const start = this.timeToMinutes(booking.start_time);
        return Number.isFinite(start) && Math.floor(start / 60) === hour;
      });
    },

    ownBookingEventStyle(booking) {
      const start = this.timeToMinutes(booking.start_time);
      const end = this.timeToMinutes(booking.end_time);
      const hourHeight = window.matchMedia('(max-width: 760px)').matches ? 62 : 68;
      const top = Number.isFinite(start) ? ((start % 60) / 60) * hourHeight + 3 : 3;
      const duration = Number.isFinite(start) && Number.isFinite(end) ? Math.max(30, end - start) : 30;
      const height = Math.max(34, (duration / 60) * hourHeight - 6);
      return { top: `${top}px`, height: `${height}px` };
    },

    bookingReturnState() {
      return {
        businessSlug: window.App.config.businessSlug,
        savedAt: Date.now(),
        step: this.step,
        selectedCategory: this.selectedCategory,
        selectedServiceId: this.selectedService?.id || null,
        selectedSlotTime: this.selectedSlot?.time || null,
        date: this.date,
        bookingCalendarDate: this.bookingCalendarDate,
        bookingCalendarMode: this.bookingCalendarMode,
        scrollY: window.scrollY,
        form: { ...this.form }
      };
    },

    openOwnBooking(booking) {
      if (!booking?.manage_url) {
        this.toasts.error('Ehhez a foglaláshoz már nem érhető el kezelőlink.');
        return;
      }

      localStorage.setItem(MANAGE_RETURN_STATE_KEY, JSON.stringify(this.bookingReturnState()));
      const manageUrl = new URL(booking.manage_url, window.location.href);
      manageUrl.searchParams.set('from', 'booking');
      window.location.assign(manageUrl.href);
    },

    pickPublicSlot(slot) {
      if (!slot) return;
      this.selectedSlot = slot;
    },

    scrollToBooking() {
      this.$nextTick(() => {
        const bookingSection = this.$refs.bookingSection;
        if (!bookingSection) return;

        const top = bookingSection.getBoundingClientRect().top + window.scrollY - 16;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
      });
    },

    async goToStep(step) {
      if (step === 2 && !this.selectedService) return;
      if (step === 3 && !this.selectedSlot) return;

      if (step === 2 && this.step === 1) {
        this.bookingCalendarMode = 'month';
        this.bookingCalendarDate = this.today;
        this.date = this.today;
        this.selectedSlot = null;
        this.slots = [];
        this.workingHours = [];
        this.step = step;
        this.scrollToBooking();
        await this.loadMonthAvailability();
        return;
      }

      this.step = step;
      if (step === 3) this.prefillCustomerData();
      this.scrollToBooking();
    },

    handleCustomerStorage(event) {
      if ([CUSTOMER_TOKEN_KEY, CUSTOMER_ACCOUNT_KEY, CUSTOMER_EXPIRES_KEY].includes(event.key)) this.loadCustomerSession();
    },

    handleCustomerVisibility() {
      if (document.visibilityState === 'visible') this.loadCustomerSession();
    },

    async loadCustomerSession() {
      const expiresAt = localStorage.getItem(CUSTOMER_EXPIRES_KEY);
      if (expiresAt && new Date(expiresAt).getTime() <= Date.now()) {
        this.clearCustomerSession();
        return;
      }
      this.customerToken = localStorage.getItem(CUSTOMER_TOKEN_KEY) || '';
      if (!this.customerToken) { this.customerAccount = {}; this.customerBookings = []; return; }
      try {
        const [response, bookingsResponse] = await Promise.all([
          api('/customer/me', { token: this.customerToken }),
          api('/customer/bookings', { token: this.customerToken })
        ]);
        const account = response.account || {};
        if (account.business?.slug !== window.App.config.businessSlug) { this.customerAccount = {}; this.customerBookings = []; return; }
        this.customerAccount = account;
        this.customerBookings = bookingsResponse.data || [];
        localStorage.setItem(CUSTOMER_ACCOUNT_KEY, JSON.stringify(this.customerAccount));
        this.prefillCustomerData();
      } catch (error) {
        if ([401, 403].includes(error.status)) this.clearCustomerSession();
      }
    },

    clearCustomerSession() {
      this.customerToken = ''; this.customerAccount = {}; this.customerBookings = [];
      [CUSTOMER_TOKEN_KEY, CUSTOMER_EXPIRES_KEY, CUSTOMER_ACCOUNT_KEY].forEach((key) => localStorage.removeItem(key));
    },

    prefillCustomerData(force = false) {
      if (!this.customerAccount.id) return;
      if (force || !this.form.customer_name) this.form.customer_name = this.customerAccount.name || '';
      if (force || !this.form.customer_contact) this.form.customer_contact = this.customerAccount.email || '';
      if (force || !this.form.customer_phone) this.form.customer_phone = this.customerAccount.phone || '';
      if (!this.reviewForm.author) this.reviewForm.author = this.customerAccount.name || '';
      if (!this.reviewForm.email) this.reviewForm.email = this.customerAccount.email || '';
    },

    goToCustomerAccount(event) {
      localStorage.setItem(BOOKING_RETURN_STATE_KEY, JSON.stringify(this.bookingReturnState()));
      window.location.assign(event?.currentTarget?.href || window.location.href);
    },

    async restoreBookingReturnState() {
      const stateKey = localStorage.getItem(MANAGE_RETURN_STATE_KEY)
        ? MANAGE_RETURN_STATE_KEY
        : BOOKING_RETURN_STATE_KEY;
      const raw = localStorage.getItem(stateKey);
      if (!raw) return;

      localStorage.removeItem(stateKey);
      let state;
      try { state = JSON.parse(raw); } catch { return; }
      if (state.businessSlug !== window.App.config.businessSlug || Date.now() - Number(state.savedAt || 0) > 30 * 60 * 1000) return;

      const service = this.services.find((item) => String(item.id) === String(state.selectedServiceId));
      if (!service) return;

      this.selectedService = service;
      this.selectedCategory = state.selectedCategory || 'all';
      this.date = state.date || this.today;
      this.bookingCalendarDate = state.bookingCalendarDate || this.date;
      this.bookingCalendarMode = state.bookingCalendarMode === 'day' ? 'day' : 'month';
      this.form = { ...this.form, ...(state.form || {}) };
      this.prefillCustomerData(true);

      if (state.selectedSlotTime && this.date) {
        this.bookingCalendarMode = 'day';
        await this.loadAvailability();
        this.selectedSlot = this.slots.find((slot) => slot.time === state.selectedSlotTime) || null;
        this.step = this.selectedSlot ? Math.min(3, Math.max(2, Number(state.step || 2))) : 2;
        if (!this.selectedSlot) this.toasts.error('A korábban kiválasztott időpont közben már nem elérhető. Válassz egy másikat.');
      } else {
        this.step = Math.min(2, Math.max(1, Number(state.step || 1)));
        if (this.step === 2) {
          await this.loadMonthAvailability();
          if (this.bookingCalendarMode === 'day' && this.date) await this.loadAvailability();
        }
      }

      if (Number.isFinite(Number(state.scrollY))) {
        this.$nextTick(() => window.scrollTo({ top: Number(state.scrollY), behavior: 'auto' }));
      } else {
        this.scrollToBooking();
      }
    },

    openLegalModal(title, content, url, event) {
      this.legalReturnFocus = event?.currentTarget instanceof HTMLElement
        ? event.currentTarget
        : document.activeElement;
      this.legalModal = {
        open: true,
        title,
        content: String(content || '').trim(),
        url
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
        'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
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

    async loadMonthAvailability() {
      this.monthAvailability = {};
      if (!this.selectedService) return;

      const days = this.publicMonthDays;
      const start = days[0]?.key;
      const end = days[days.length - 1]?.key;
      if (!start || !end) return;

      this.loadingMonthAvailability = true;
      try {
        const params = new URLSearchParams({
          service_id: this.selectedService.id,
          start,
          end
        });
        const response = await api(`/businesses/${window.App.config.businessSlug}/availability-calendar?${params}`);
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
        if (this.customerToken) await this.loadCustomerSession();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch (error) {
        this.toasts.error(`Nem sikerült menteni a foglalást: ${error.message}`);
      } finally {
        this.submitting = false;
      }
    },

    calendarEventOptions() {
      if (!this.confirmedBooking) return;
      const manageUrl = new URL(this.manageUrl, window.location.href).href;
      return {
        title: `${this.confirmedBooking.service_name} – ${this.business.name || ''}`,
        description: `Foglalás: ${this.business.name || 'Időpontfoglalás'}.`,
        location: this.business.address || '',
        dateKey: this.confirmedBooking.date,
        startTime: this.confirmedBooking.start_time,
        endTime: this.confirmedBooking.end_time,
        timezone: this.business.timezone || 'Europe/Budapest',
        manageUrl
      };
    },

    addToDeviceCalendar() {
      const token = this.confirmedBooking?.manage_token;
      if (!token) return this.toasts.error('A naptárfájl hivatkozása nem érhető el.');
      window.location.assign(calendarDownloadUrl(token));
    },

    addToGoogleCalendar() {
      const options = this.calendarEventOptions();
      if (!options) return;
      window.open(googleCalendarUrl(options), '_blank', 'noopener,noreferrer');
    },

    startOver() {
      this.selectedService = null;
      this.selectedSlot = null;
      this.slots = [];
      this.workingHours = [];
      this.monthAvailability = {};
      this.date = this.today;
      this.bookingCalendarDate = this.today;
      this.bookingCalendarMode = 'month';
      this.form = { customer_name: this.customerAccount.name || '', customer_contact: this.customerAccount.email || '', customer_phone: this.customerAccount.phone || '', customer_note: '', legal_accepted: false };
      this.confirmedBooking = null;
      this.step = 1;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }
}).mount('#bookingApp');
