const { createApp, reactive } = Vue;
const { api, todayKey, formatDateLong, isPersonName, isEmail, useToasts, setBusinessFavicon, PasswordInput } = window.App;
const CUSTOMER_TOKEN_KEY = 'appointment_customer_token';
const CUSTOMER_EXPIRES_KEY = 'appointment_customer_expires_at';
const CUSTOMER_ACCOUNT_KEY = 'appointment_customer_account';

createApp({
  components: { PasswordInput },
  data() {
    const root = document.getElementById('accountApp');
    return {
      business: {}, token: localStorage.getItem(CUSTOMER_TOKEN_KEY) || '', account: {}, bookings: [], sessions: [],
      mainUrl: root?.dataset.mainUrl || '/',
      returnToBooking: new URLSearchParams(window.location.search).get('return') === 'booking',
      authMode: 'login', accountView: 'bookings', pendingEmail: '', loading: true, busy: false,
      registerForm: { name: '', email: '', phone: '', password: '', password_confirmation: '' },
      verificationDigits: ['', '', '', '', '', ''], loginForm: { email: '', password: '' },
      resetDigits: ['', '', '', '', '', ''],
      resetForm: { email: '', password: '', password_confirmation: '' },
      profile: { name: '', phone: '' }, passwordForm: { current_password: '', password: '', password_confirmation: '' },
      savingProfile: false, changingPassword: false, deletingAccount: false,
      deleteDialogOpen: false, deleteDialogReturnFocus: null, toasts: useToasts(reactive)
    };
  },
  computed: {
    upcomingBookings() { const today = todayKey(); return this.bookings.filter((item) => item.status === 'booked' && item.date >= today).reverse(); },
    pastBookings() { const ids = new Set(this.upcomingBookings.map((item) => item.id)); return this.bookings.filter((item) => !ids.has(item.id)); }
  },
  watch: {
    business: {
      immediate: true,
      deep: true,
      handler(value) {
        setBusinessFavicon(value);
      }
    }
  },
  async mounted() {
    window.addEventListener('keydown', this.handleDeleteDialogKeydown);
    const expiresAt = localStorage.getItem(CUSTOMER_EXPIRES_KEY);
    if (expiresAt && new Date(expiresAt).getTime() <= Date.now()) this.clearSession();
    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
      if (this.token) {
        await this.loadAccount();
        if (this.returnToBooking) this.redirectToBooking();
      }
    } catch (error) {
      if (this.token && [401, 403].includes(error.status)) this.clearSession();
      this.toasts.error(error.message);
    } finally { this.loading = false; }
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.handleDeleteDialogKeydown);
    document.body.classList.remove('modal-open');
  },
  methods: {
    formatDateLong,
    monogram(name) { return String(name || '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toLocaleUpperCase('hu-HU') || '').join(''); },
    statusLabel(status) { return { booked: 'Foglalva', completed: 'Teljesítve', cancelled: 'Lemondva', no_show: 'Nem jelent meg' }[status] || status; },
    formatDateTime(value) { return value ? new Intl.DateTimeFormat('hu-HU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'még nem használt'; },
    manageFromAccount(url) { const result = new URL(url, window.location.href); result.searchParams.set('from', 'account'); return result.href; },
    codeDigits(group) { return group === 'registration' ? this.verificationDigits : this.resetDigits; },
    codeValue(group) { return this.codeDigits(group).join(''); },
    clearCode(group) {
      if (group === 'registration') this.verificationDigits = ['', '', '', '', '', ''];
      else this.resetDigits = ['', '', '', '', '', ''];
    },
    focusCodeDigit(group, index) {
      const safeIndex = Math.max(0, Math.min(5, index));
      this.$nextTick(() => document.querySelector(`[data-code-group="${group}"][data-code-index="${safeIndex}"]`)?.focus());
    },
    fillCodeDigits(group, value, startIndex = 0) {
      const digits = String(value || '').replace(/\D/g, '').slice(0, 6);
      if (!digits) return;
      const target = [...this.codeDigits(group)];
      const offset = digits.length === 6 ? 0 : startIndex;
      digits.split('').forEach((digit, position) => {
        if (offset + position < 6) target[offset + position] = digit;
      });
      if (group === 'registration') this.verificationDigits = target;
      else this.resetDigits = target;
      this.focusCodeDigit(group, Math.min(5, offset + digits.length));
    },
    handleCodeInput(group, index, event) {
      const value = String(event.target.value || '').replace(/\D/g, '');
      if (value.length > 1) {
        this.fillCodeDigits(group, value, index);
        return;
      }
      const target = [...this.codeDigits(group)];
      target[index] = value.slice(-1);
      if (group === 'registration') this.verificationDigits = target;
      else this.resetDigits = target;
      event.target.value = target[index];
      if (target[index] && index < 5) this.focusCodeDigit(group, index + 1);
    },
    handleCodePaste(group, index, event) {
      const value = event.clipboardData?.getData('text') || '';
      if (!/\d/.test(value)) return;
      event.preventDefault();
      this.fillCodeDigits(group, value, index);
    },
    handleCodeKeydown(group, index, event) {
      if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault(); this.focusCodeDigit(group, index - 1);
      } else if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault(); this.focusCodeDigit(group, index + 1);
      } else if (event.key === 'Backspace' && !this.codeDigits(group)[index] && index > 0) {
        event.preventDefault();
        const target = [...this.codeDigits(group)]; target[index - 1] = '';
        if (group === 'registration') this.verificationDigits = target;
        else this.resetDigits = target;
        this.focusCodeDigit(group, index - 1);
      }
    },
    saveSession(response) {
      this.token = response.token; this.account = response.account || {};
      localStorage.setItem(CUSTOMER_TOKEN_KEY, this.token);
      localStorage.setItem(CUSTOMER_EXPIRES_KEY, response.expires_at || '');
      localStorage.setItem(CUSTOMER_ACCOUNT_KEY, JSON.stringify(this.account));
    },
    clearSession() {
      this.token = ''; this.account = {}; this.bookings = []; this.sessions = [];
      [CUSTOMER_TOKEN_KEY, CUSTOMER_EXPIRES_KEY, CUSTOMER_ACCOUNT_KEY].forEach((key) => localStorage.removeItem(key));
    },
    redirectToBooking() {
      window.location.replace(`${this.mainUrl}#foglalas`);
    },
    async register() {
      if (!isPersonName(this.registerForm.name) || !isEmail(this.registerForm.email)) return this.toasts.error('Ellenőrizd a nevet és az e-mail-címet.');
      if (this.registerForm.password !== this.registerForm.password_confirmation) return this.toasts.error('A két jelszó nem egyezik.');
      this.busy = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/customer-auth/register`, { method: 'POST', body: JSON.stringify(this.registerForm) });
        this.pendingEmail = response.email || this.registerForm.email; this.clearCode('registration'); this.authMode = 'verify'; this.focusCodeDigit('registration', 0); this.toasts.success(response.message);
      } catch (error) { this.toasts.error(`A regisztráció nem indítható: ${error.message}`); } finally { this.busy = false; }
    },
    async verifyRegistration() {
      const code = this.codeValue('registration');
      if (code.length !== 6) { this.toasts.error('Add meg mind a hat számjegyet.'); this.focusCodeDigit('registration', this.verificationDigits.findIndex((digit) => !digit)); return; }
      this.busy = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/customer-auth/verify-registration`, { method: 'POST', body: JSON.stringify({ email: this.pendingEmail, code }) });
        this.saveSession(response); await this.loadAccount();
        if (this.returnToBooking) { this.redirectToBooking(); return; }
        this.toasts.success('A regisztráció sikeresen befejeződött.');
      } catch (error) { this.toasts.error(`A kód nem fogadható el: ${error.message}`); } finally { this.busy = false; }
    },
    async login() {
      this.busy = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/customer-auth/login`, { method: 'POST', body: JSON.stringify(this.loginForm) });
        this.saveSession(response); await this.loadAccount();
        if (this.returnToBooking) { this.redirectToBooking(); return; }
        this.toasts.success('Sikeres bejelentkezés.');
      } catch (error) { this.toasts.error(`Sikertelen bejelentkezés: ${error.message}`); } finally { this.busy = false; }
    },
    async forgotPassword() {
      this.busy = true;
      try {
        const response = await api(`/businesses/${window.App.config.businessSlug}/customer-auth/password/forgot`, { method: 'POST', body: JSON.stringify({ email: this.loginForm.email }) });
        this.resetForm.email = this.loginForm.email; this.clearCode('reset'); this.authMode = 'reset'; this.focusCodeDigit('reset', 0); this.toasts.success(response.message);
      } catch (error) { this.toasts.error(error.message); } finally { this.busy = false; }
    },
    async resetPassword() {
      const code = this.codeValue('reset');
      if (code.length !== 6) { this.toasts.error('Add meg mind a hat számjegyet.'); this.focusCodeDigit('reset', this.resetDigits.findIndex((digit) => !digit)); return; }
      if (this.resetForm.password !== this.resetForm.password_confirmation) return this.toasts.error('A két jelszó nem egyezik.');
      this.busy = true;
      try {
        const email = this.resetForm.email;
        const response = await api(`/businesses/${window.App.config.businessSlug}/customer-auth/password/reset`, { method: 'POST', body: JSON.stringify({ ...this.resetForm, code }) });
        this.clearSession();
        this.loginForm = { email, password: '' };
        this.resetForm = { email: '', password: '', password_confirmation: '' };
        this.clearCode('reset');
        this.authMode = 'login';
        this.toasts.success(response.message || 'Az új jelszó elkészült. Jelentkezz be az új jelszavaddal.');
        this.$nextTick(() => document.querySelector('input[autocomplete="current-password"]')?.focus());
      } catch (error) { this.toasts.error(error.message); } finally { this.busy = false; }
    },
    async loadAccount() {
      const [me, bookings, sessions] = await Promise.all([api('/customer/me', { token: this.token }), api('/customer/bookings', { token: this.token }), api('/customer/sessions', { token: this.token })]);
      if (me.account?.business?.slug !== window.App.config.businessSlug) { this.clearSession(); throw new Error('Ez a munkamenet egy másik szolgáltatóhoz tartozik.'); }
      this.account = me.account || {}; this.profile = { name: this.account.name || '', phone: this.account.phone || '' };
      this.bookings = bookings.data || []; this.sessions = sessions.data || [];
      localStorage.setItem(CUSTOMER_ACCOUNT_KEY, JSON.stringify(this.account));
    },
    async saveProfile() {
      if (!isPersonName(this.profile.name)) return this.toasts.error('Adj meg egy érvényes nevet.');
      this.savingProfile = true;
      try { const response = await api('/customer/profile', { method: 'PATCH', token: this.token, body: JSON.stringify(this.profile) }); this.account = response.account; localStorage.setItem(CUSTOMER_ACCOUNT_KEY, JSON.stringify(this.account)); this.toasts.success('Profil elmentve.'); }
      catch (error) { this.toasts.error(`A profil nem menthető: ${error.message}`); } finally { this.savingProfile = false; }
    },
    async changePassword() {
      if (this.passwordForm.password !== this.passwordForm.password_confirmation) return this.toasts.error('A két új jelszó nem egyezik.');
      this.changingPassword = true;
      try { const response = await api('/customer/password', { method: 'PATCH', token: this.token, body: JSON.stringify(this.passwordForm) }); this.passwordForm = { current_password: '', password: '', password_confirmation: '' }; await this.loadAccount(); this.toasts.success(response.message); }
      catch (error) { this.toasts.error(error.message); } finally { this.changingPassword = false; }
    },
    async revokeSession(id) {
      try { const response = await api(`/customer/sessions/${id}`, { method: 'DELETE', token: this.token }); await this.loadAccount(); this.toasts.success(response.message); } catch (error) { this.toasts.error(error.message); }
    },
    async logout() { try { await api('/customer/logout', { method: 'POST', token: this.token }); } catch {} this.clearSession(); },
    async logoutAll() { try { await api('/customer/logout-all', { method: 'POST', token: this.token }); } catch {} this.clearSession(); },
    openDeleteDialog() {
      this.deleteDialogReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      this.deleteDialogOpen = true;
      document.body.classList.add('modal-open');
      this.$nextTick(() => (this.$refs.deleteCancelButton || this.$refs.deleteDialog)?.focus());
    },
    finishDeleteDialog() {
      this.deleteDialogOpen = false;
      document.body.classList.remove('modal-open');
      const returnFocus = this.deleteDialogReturnFocus;
      this.deleteDialogReturnFocus = null;
      this.$nextTick(() => returnFocus?.focus?.());
    },
    closeDeleteDialog() {
      if (this.deletingAccount) return;
      this.finishDeleteDialog();
    },
    handleDeleteDialogKeydown(event) {
      if (!this.deleteDialogOpen) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        this.closeDeleteDialog();
        return;
      }
      if (event.key !== 'Tab') return;
      const dialog = this.$refs.deleteDialog;
      if (!dialog) return;
      const focusable = [...dialog.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])')];
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
    async deleteAccount() {
      if (this.deletingAccount) return;
      this.deletingAccount = true;
      try { const response = await api('/customer/account', { method: 'DELETE', token: this.token }); this.finishDeleteDialog(); this.clearSession(); this.toasts.success(response.message); }
      catch (error) { this.toasts.error(`A fiók nem törölhető: ${error.message}`); } finally { this.deletingAccount = false; }
    }
  }
}).mount('#accountApp');
