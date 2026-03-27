import enUS from '@/i18n/locales/en-US.json'
import frFR from '@/i18n/locales/fr-FR.json'
import { datetimeFormats } from '@/i18n/datetime-formats'
import { createI18n } from 'vue-i18n'

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  fallbackLocale: {
    'fr-CA': ['fr-FR', 'en-US'],
    default: ['en-US'],
  },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
  messages: { 'en-US': enUS, 'fr-FR': frFR },
  datetimeFormats,
})

export default i18n
