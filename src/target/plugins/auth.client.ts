import { useAuth } from '@target/composables/useAuth'

export default {
  install: async () => {
    const auth = useAuth()
    await auth.init()
  },
}
