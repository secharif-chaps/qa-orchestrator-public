import enUS from '@/i18n/locales/en-US'
import frFR from '@/i18n/locales/fr-FR'
import { datetimeFormats } from '@/target/i18n/i18n.config'
import targetEnUS from '@/target/i18n/locales/en-US.json'
import targetFrFR from '@/target/i18n/locales/fr-FR.json'
import { createI18n } from 'vue-i18n'

const messages = {
  'en-US': { ...enUS, ...targetEnUS },
  'fr-FR': { ...frFR, ...targetFrFR },
}

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  fallbackLocale: 'en-US',
  messages,
  datetimeFormats,
})

export default i18n
