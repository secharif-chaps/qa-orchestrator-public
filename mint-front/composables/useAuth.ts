import { UserManager, User } from 'oidc-client'

export const useAuth = () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => !!user.value && !user.value.expired)

  const keycloakConfig = {
    authority: 'http://10.0.1.2:8080/realms/mint-dev',
    client_id: 'mint-front',
    redirect_uri: `${window.location.origin}/auth/callback`,
    silent_redirect_uri: `${window.location.origin}/auth/silent-callback`,
    post_logout_redirect_uri: `${window.location.origin}/login`,
    response_type: 'code',
    scope: 'openid profile email',
    automaticSilentRenew: true,
    silentRequestTimeout: 10000,
    filterProtocolClaims: true,
    loadUserInfo: true
  }

  const userManager = new UserManager(keycloakConfig)

  const signIn = async () => {
    try {
      await userManager.signinRedirect()
    } catch (error) {
      console.error('Sign in error:', error)
      throw error
    }
  }

  const signOut = async () => {
    try {
      await userManager.signoutRedirect()
    } catch (error) {
      console.error('Sign out error:', error)
      throw error
    }
  }

  const handleCallback = async () => {
    try {
      const callbackUser = await userManager.signinRedirectCallback()
      user.value = callbackUser
      return callbackUser
    } catch (error) {
      console.error('Callback error:', error)
      throw error
    }
  }

  const getUser = async () => {
    // Skip SSR
    if (process.server) return null
    
    try {
      const currentUser = await userManager.getUser()
      user.value = currentUser
      return currentUser
    } catch (error) {
      console.error('Get user error:', error)
      return null
    }
  }

  const handleSilentCallback = async () => {
    try {
      const callbackUser = await userManager.signinSilentCallback()
      if (callbackUser) {

        user.value = callbackUser
      }
      return callbackUser
    } catch (error) {
      console.error('Silent callback error:', error)
      throw error
    }
  }

  const getAccessToken = async () => {
    // Skip SSR
    if (process.server) return null
    
    const user = await getUser()
    return user?.access_token || null
  }

  const getCurrentUsername = async () => {
    // Skip SSR
    if (process.server) return 'unknown'
    
    const user = await getUser()
    return user?.profile?.preferred_username || user?.profile?.sub || 'unknown'
  }

  // Set up event handlers for token events
  userManager.events.addUserLoaded((user) => {

  })

  userManager.events.addUserUnloaded(() => {

    user.value = null
  })

  userManager.events.addAccessTokenExpiring(() => {

  })

  userManager.events.addAccessTokenExpired(() => {

    user.value = null
  })

  userManager.events.addSilentRenewError((error) => {
    console.error('Silent renew error:', error)
  })

  getUser()

  return {
    user: readonly(user),
    isAuthenticated,
    signIn,
    signOut,
    handleCallback,
    handleSilentCallback,
    getUser,
    getAccessToken,
    getCurrentUsername
  }
}