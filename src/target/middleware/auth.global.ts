import { useAuth } from '@target/composables/useAuth'
import type { NavigationGuardWithThis } from 'vue-router'

const authMiddleware: NavigationGuardWithThis<undefined> = () => {
  const { isAuthenticated, isAuthProviderReady } = useAuth()
  if (!isAuthProviderReady?.value) {
    return false
  }

  if (!isAuthenticated?.value) {
    return false
  }
}

export default authMiddleware
