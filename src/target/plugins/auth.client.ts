import { useAuth } from '@target/composables/useAuth'
import type { App } from 'vue'

export default {
  install: async (_app: App) => {
    const auth = useAuth()
    await auth.init()
  },
}
