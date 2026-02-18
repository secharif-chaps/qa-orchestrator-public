import type { App } from 'vue'
import { useAuth } from '~/composables/useAuth'

export default {
  install: async (_app: App) => {
    const auth = useAuth()
    await auth.init()
  },
}
