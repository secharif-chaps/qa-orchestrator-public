import { UserManager, User } from 'oidc-client-ts'

export const useAuth = () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => !!user.value && !user.value.expired)

  const keycloakConfig = {
    authority: 'http://localhost:8080/realms/mint-dev',
    client_id: 'mint-front',
    redirect_uri: `${window.location.origin}/auth/callback`,
    post_logout_redirect_uri: `${window.location.origin}/login`,
    response_type: 'code',
    scope: 'openid profile email',
    automaticSilentRenew: true,
    silent_redirect_uri: `${window.location.origin}/auth/silent-callback`,
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
    try {
      const currentUser = await userManager.getUser()
      user.value = currentUser
      return currentUser
    } catch (error) {
      console.error('Get user error:', error)
      return null
    }
  }

  const getAccessToken = async () => {
    const user = await getUser()
    return user?.access_token || null
  }

  // Initialize user on composable creation
  onMounted(() => {
    getUser()
  })

  return {
    user: readonly(user),
    isAuthenticated,
    signIn,
    signOut,
    handleCallback,
    getUser,
    getAccessToken
  }
}