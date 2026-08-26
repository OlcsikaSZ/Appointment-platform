const { createApp, reactive } = Vue;
const { api, useToasts, setBusinessFavicon} = window.App;

createApp({
  data() {
    const root = document.getElementById('legalApp');
    return {
      business: {},
      field: root?.dataset.legalField || 'privacyPolicy',
      title: root?.dataset.legalTitle || 'Jogi dokumentum',
      eyebrow: root?.dataset.legalEyebrow || 'Jogi dokumentum',
      mainUrl: root?.dataset.mainUrl || '/',
      loading: true,
      toasts: useToasts(reactive)
    };
  },
  computed: {
    content() {
      return String(this.business.legal?.[this.field] || '').trim();
    }
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
    try {
      const response = await api(`/businesses/${window.App.config.businessSlug}`);
      this.business = response.data || {};
    } catch (error) {
      this.toasts.error(`A dokumentum nem tölthető be: ${error.message}`);
    } finally {
      this.loading = false;
    }
  },
  methods: {
    goBack() {
      try {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        if (referrer?.origin === window.location.origin && window.history.length > 1) {
          window.history.back();
          return;
        }
      } catch (_) {
        // Hibás vagy nem elérhető referrer esetén a főoldal a biztonságos cél.
      }

      window.location.assign(this.mainUrl);
    },

    monogram(name) {
      return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2)
        .map((part) => part[0]?.toLocaleUpperCase('hu-HU') || '').join('');
    }
  }
}).mount('#legalApp');
