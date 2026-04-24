import { useAuthStore } from '@/stores/auth'
import { computed, readonly, ref } from 'vue'

const authError = ref<string | null>(null)
const isTokenRefreshing = ref<boolean>(false)
let visibilityChangeRegistered = false

// Token refresh strategy:
//
// The OIDC session (Keycloak via oidc-client-ts) stays alive as long as the tab is open,
// through 3 complementary mechanisms:
//
// 1. Before each API request: useAppFetch checks isTokenExpired() and refreshes if needed.
//    Handles the common case where the user actively interacts with the app.
//
// 2. Automatic silent renew (automaticSilentRenew: true in oidc-client-ts config, see auth store):
//    the library proactively refreshes the token before expiry, keeping the session alive even when
//    no API call is made (e.g. user waiting for an AI response in the chat).
//    If the token expires despite this (e.g. throttled timer), addAccessTokenExpired in the auth
//    store attempts a signinSilent() recovery before disconnecting the user.
//
// 3. Visibility change handler (registerVisibilityChangeHandler): when the user switches back to the
//    tab, forces an immediate token refresh. This covers the case where the browser throttled the
//    silent renew timer while the tab was in the background.
//
// The session will still expire naturally when:
// - The user closes the tab/browser (timers stop, session idle timeout kicks in)
// - The SSO Session Max lifespan is reached (absolute limit, independent of activity)
// - The refresh token itself expires
export function useAuth() {
  const authStore = useAuthStore()

  const isAuthProviderReady = computed(() => authStore.initialized)
  const isAuthenticated = computed(() => authStore.isAuthenticated)

  const userName = computed(() => authStore.username)
  const userEmail = computed(() => authStore.user?.profile?.email || null)

  const isInternalUser = computed(
    () =>
      typeof userEmail.value === 'string' &&
      (userEmail.value.endsWith('@chapsvision.com') ||
        userEmail.value.endsWith('@chapsmind.local')),
  )

  const isLoading = computed(() => !authStore.initialized || isTokenRefreshing.value)
  const hasError = computed(() => authError.value !== null)

  const init = async () => {
    await authStore.initialize()
    registerVisibilityChangeHandler()
  }

  const getToken = async (): Promise<string | null> => {
    return authStore.accessToken
  }

  const isTokenExpired = (): boolean => {
    return authStore.user?.expired ?? false
  }

  const refreshToken = async (): Promise<boolean> => {
    if (isTokenRefreshing.value) return false

    try {
      isTokenRefreshing.value = true
      authError.value = null
      const result = await authStore.refreshToken()
      return !!result
    } catch (error) {
      authError.value = 'Token refresh failed'
      console.error('Token refresh failed:', error)
      return false
    } finally {
      isTokenRefreshing.value = false
    }
  }

  const updateToken = async () => {
    return await refreshToken()
  }

  const logout = () => {
    authStore.signOut()
  }

  // Mechanism 3: refresh on tab return (browsers throttle timers in background tabs).
  const registerVisibilityChangeHandler = () => {
    if (visibilityChangeRegistered || typeof document === 'undefined') return
    visibilityChangeRegistered = true

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && authStore.user) {
        refreshToken()
      }
    })
  }

  const getValidToken = async (): Promise<string | null> => {
    if (!authStore.user) return null

    if (authStore.user.expired) {
      const refreshed = await refreshToken()
      if (!refreshed) return null
    }

    return authStore.accessToken
  }

  return {
    // State
    isAuthenticated: readonly(isAuthenticated),
    isAuthProviderReady: readonly(isAuthProviderReady),
    authError: readonly(authError),
    isTokenRefreshing: readonly(isTokenRefreshing),

    // Computed
    isLoading,
    hasError,
    userName,
    userEmail,
    isInternalUser,

    // Methods
    init,
    getToken,
    getValidToken,
    isTokenExpired,
    updateToken,
    refreshToken,
    logout,
  }
}
