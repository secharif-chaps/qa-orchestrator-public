import { PiniaColada } from '@pinia/colada'
import { createHead } from '@unhead/vue/client'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { createApp } from 'vue'

import '@/assets/main.css'
import clarityPlugin from '@target/plugins/clarity.client'
import sanitizeHtmlPlugin from '@target/plugins/sanitizeHtml.client'
import App from './App.vue'
import i18n from './i18n'
import router from './router'

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
