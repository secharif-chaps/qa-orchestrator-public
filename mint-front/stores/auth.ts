import { jwtDecode } from 'jwt-decode'
import { UserManager, User } from 'oidc-client'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const userManager = ref<UserManager | null>(null)
  const initialized = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!user.value && !user.value.expired)
  const currentUser = computed(() => user.value)
  const accessToken = computed(() => user.value?.access_token || null)
  const username = computed(() => user.value?.profile?.preferred_username || user.value?.profile?.sub || 'unknown')
  const userRoles = ref<string[]>([])
  const userPermissions = ref<string[]>([])

  // Extract roles from JWT token
  const extractRolesFromToken = (token: string) => {
    try {
      const decoded = jwtDecode<any>(token)
      
      // Extract realm roles
      const realmRoles = decoded.realm_access?.roles || []
      
      // Extract client roles (if any)
      const clientRoles = decoded.resource_access?.['mint-front']?.roles || []
      
      // Combine all roles
      const allRoles = [...realmRoles, ...clientRoles]
      
      // Filter out system roles and keep only custom roles
      const customRoles = allRoles.filter(role => 
        !['offline_access', 'uma_authorization', 'default-roles-mint-dev'].includes(role)
      )
      
      userRoles.value = customRoles
      
      // Extract permissions from roles (roles that follow the pattern resource.action)
      userPermissions.value = customRoles.filter(role => role.includes('.'))
    } catch (error) {
      console.error('Error extracting roles from token:', error)
      userRoles.value = []
      userPermissions.value = []
    }
  }

  // Initialize UserManager
  const initializeUserManager = () => {
    if (userManager.value || process.server) return userManager.value

    const config = useRuntimeConfig()
    const keycloakConfig = {
      authority: `${config.public.keycloakUrl}/realms/mint-dev`,
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

    const manager = new UserManager(keycloakConfig)

    // Set up event handlers
    manager.events.addUserLoaded((loadedUser) => {
      user.value = loadedUser
      if (loadedUser.access_token) {
        extractRolesFromToken(loadedUser.access_token)
      }
    })

    manager.events.addUserUnloaded(() => {
      user.value = null
      userRoles.value = []
      userPermissions.value = []
    })

    manager.events.addAccessTokenExpiring(() => {
      console.log('Access token expiring')
    })

    manager.events.addAccessTokenExpired(() => {
      user.value = null
      userRoles.value = []
      userPermissions.value = []
    })

    manager.events.addSilentRenewError((error) => {
      console.error('Silent renew error:', error)
    })

    userManager.value = manager
    return manager
  }

  // Actions
  const initialize = async () => {
    if (initialized.value || process.server) return

    const manager = initializeUserManager()
    if (!manager) return

    try {
      const currentUser = await manager.getUser()
      user.value = currentUser
      if (currentUser?.access_token) {
        extractRolesFromToken(currentUser.access_token)
      }
      initialized.value = true
    } catch (error) {
      console.error('Initialize auth error:', error)
      initialized.value = true
    }
  }

  const signIn = async () => {
    const manager = initializeUserManager()
    if (!manager) return

    try {
      await manager.signinRedirect()
    } catch (error) {
      console.error('Sign in error:', error)
      throw error
    }
  }

  const signOut = async () => {
    const manager = initializeUserManager()
    if (!manager) return

    try {
      await manager.signoutRedirect()
    } catch (error) {
      console.error('Sign out error:', error)
      throw error
    }
  }

  const handleCallback = async () => {
    const manager = initializeUserManager()
    if (!manager) return null

    try {
      const callbackUser = await manager.signinRedirectCallback()
      user.value = callbackUser
      if (callbackUser?.access_token) {
        extractRolesFromToken(callbackUser.access_token)
      }
      return callbackUser
    } catch (error) {
      console.error('Callback error:', error)
      throw error
    }
  }

  const handleSilentCallback = async () => {
    const manager = initializeUserManager()
    if (!manager) return null

    try {
      const callbackUser = await manager.signinSilentCallback()
      if (callbackUser) {
        user.value = callbackUser
        if (callbackUser.access_token) {
          extractRolesFromToken(callbackUser.access_token)
        }
      }
      return callbackUser
    } catch (error) {
      console.error('Silent callback error:', error)
      throw error
    }
  }

  const getUser = async () => {
    if (process.server) return null

    const manager = initializeUserManager()
    if (!manager) return null

    try {
      const currentUser = await manager.getUser()
      user.value = currentUser
      if (currentUser?.access_token) {
        extractRolesFromToken(currentUser.access_token)
      }
      return currentUser
    } catch (error) {
      console.error('Get user error:', error)
      return null
    }
  }

  const getAccessToken = async () => {
    if (process.server) return null
    
    const currentUser = await getUser()
    return currentUser?.access_token || null
  }

  const getCurrentUsername = async () => {
    if (process.server) return 'unknown'
    
    const currentUser = await getUser()
    return currentUser?.profile?.preferred_username || currentUser?.profile?.sub || 'unknown'
  }

  const refreshToken = async () => {
    const manager = initializeUserManager()
    if (!manager) return null

    try {
      const currentUser = await manager.getUser()
      if (currentUser && currentUser.expired) {
        const refreshedUser = await manager.signinSilent()
        user.value = refreshedUser
        if (refreshedUser?.access_token) {
          extractRolesFromToken(refreshedUser.access_token)
        }
        return refreshedUser
      }
      return currentUser
    } catch (error) {
      console.error('Refresh token error:', error)
      return null
    }
  }

  // Role checking utilities
  const hasRole = (role: string): boolean => {
    return userRoles.value.includes(role)
  }

  const hasAnyRole = (roles: string[]): boolean => {
    return roles.some(role => hasRole(role))
  }

  const hasAllRoles = (roles: string[]): boolean => {
    return roles.every(role => hasRole(role))
  }

  const hasPermission = (permission: string): boolean => {
    return userPermissions.value.includes(permission)
  }

  // Initialize on store creation
  if (process.client) {
    initialize()
  }

  return {
    // State
    user: readonly(user),
    initialized: readonly(initialized),
    
    // Getters
    isAuthenticated,
    currentUser,
    accessToken,
    username,
    userRoles,
    userPermissions,
    
    // Actions
    initialize,
    signIn,
    signOut,
    handleCallback,
    handleSilentCallback,
    getUser,
    getAccessToken,
    getCurrentUsername,
    refreshToken,
    
    // Role utilities
    hasRole,
    hasAnyRole,
    hasAllRoles,
    hasPermission
  }
}
)