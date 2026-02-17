import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { PiniaColada } from '@pinia/colada'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { createHead } from '@unhead/vue/client'

import App from './App.vue'
import router from './router'
import i18n from './i18n'
import sanitizeHtmlPlugin from '~/plugins/sanitizeHtml.client'
import clarityPlugin from '~/plugins/clarity.client'
import '@/assets/main.css'

const app = createApp(App)
const head = createHead()
app.use(head)

const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)

app.use(pinia)
app.use(PiniaColada, {})

app.use(router)
app.use(i18n)
app.use(sanitizeHtmlPlugin)
app.use(clarityPlugin)

app.mount('#app')
