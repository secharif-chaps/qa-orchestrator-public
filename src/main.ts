import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { PiniaColada } from '@pinia/colada'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

import App from './App.vue'
import router from './router'
import '@/assets/main.css'

import enUS from '@/i18n/locales/en-US'
import frFR from '@/i18n/locales/fr-FR'

const messages = {
  'en-US': enUS,
  'fr-FR': frFR,
}

import { createI18n } from 'vue-i18n'

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'en-US',
  fallbackLocale: 'en-US',
  messages,
})

const app = createApp(App)

const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)

app.use(pinia)
app.use(PiniaColada, {})

app.use(router)
app.use(i18n)

app.mount('#app')
