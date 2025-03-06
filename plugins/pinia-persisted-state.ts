// plugins/pinia-persisted-state.ts
import { defineNuxtPlugin } from '#app'
import { createPersistedState } from 'pinia-plugin-persistedstate'

export default defineNuxtPlugin(({ $pinia }) => {
  $pinia.use(createPersistedState({
    storage: localStorage,
    // You can add global configuration here
  }))
})