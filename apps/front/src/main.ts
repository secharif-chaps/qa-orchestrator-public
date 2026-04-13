import { PiniaColada } from '@pinia/colada'
import { createHead } from '@unhead/vue/client'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { createApp } from 'vue'

import '@/assets/main.css'
import clarityPlugin from '@/plugins/clarity.client'
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
// staleTime = duration (ms) during which cached data is considered fresh.
// While fresh: no refetch on mount, navigation, or window focus.
// After expiry: data is served from cache immediately, then refetched in background.
// Mutations bypass staleTime entirely and invalidate the cache immediately.
// Pinia Colada defaults to 5s which causes excessive refetching. Override to 5 min.
// Individual queries can override this value when they need fresher data.
app.use(PiniaColada, {
  queryOptions: {
    staleTime: 60 * 1000, // 1 minute
  },
})

app.use(router)
app.use(i18n)
app.use(sanitizeHtmlPlugin)
app.use(clarityPlugin)

app.mount('#app')
