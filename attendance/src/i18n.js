import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import hi from './locales/hi.json';

// Retrieve language from localStorage or default to 'en'
const savedLanguage = localStorage.getItem('language') || 'en';

const i18n = createI18n({
  legacy: false,
  locale: savedLanguage, // Set locale based on stored preference
  fallbackLocale: 'en', // Fallback to English if translation is missing
  globalInjection: true, // Allows using `$t()` globally
  missingWarn: false,
  messages: { en, hi }
});

export default i18n;
