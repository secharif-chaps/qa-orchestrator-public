import { createI18n } from 'vue-i18n'
import enUS from '~/i18n/locales/en-US'
import frFR from '~/i18n/locales/fr-FR'

const messages = {
  'en-US': enUS,
  'fr-FR': frFR
}

export default defineNuxtPlugin(({ vueApp }) => {
  const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: 'en-US',
    fallbackLocale: 'en-US',
    messages
  })

  vueApp.use(i18n)
}) 