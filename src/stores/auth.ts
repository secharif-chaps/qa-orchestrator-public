import { User, UserManager, WebStorageStateStore } from 'oidc-client'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

export const useAuthStore = defineStore(
  'auth',
  () => {
    // State
    const user = ref<User | null>(null)
    const userManager = ref<UserManager | null>(null)
    const initialized = ref(false)

    // Getters
    const isAuthenticated = computed(() => !!user.value && !user.value.expired)
    const currentUser = computed(() => user.value)
    const accessToken = computed(() => user.value?.access_token || null)
    const username = computed(
      () => user.value?.profile?.preferred_username || user.value?.profile?.sub || 'unknown',
    )
    const userRoles = ref<string[]>([])
    const userPermissions = ref<string[]>([])

    // Extract user claims from OIDC token
    const extractUserClaims = (authUser: User) => {
      const profile = authUser.profile

      // Extract roles from Keycloak token structure
      const realmRoles = (profile as any)?.realm_access?.roles || []
      const resourceRoles =
        (profile as any)?.resource_access?.[import.meta.env.VITE_KEYCLOAK_CLIENT_ID]?.roles || []

      // Combine all roles
      userRoles.value = [...realmRoles, ...resourceRoles]

      // For now, treat roles as permissions (can be customized later)
      userPermissions.value = [...userRoles.value]

      console.log('Extracted user claims:', {
        roles: userRoles.value,
        permissions: userPermissions.value,
        profile: profile,
      })
    }

    // Initialize UserManager
    const initializeUserManager = () => {
      if (userManager.value) return userManager.value

      const keycloakConfig = {
        authority: `${import.meta.env.VITE_KEYCLOAK_URL}/realms/${import.meta.env.VITE_KEYCLOAK_REALM}`,
        client_id: import.meta.env.VITE_KEYCLOAK_CLIENT_ID as string,
        redirect_uri: `${import.meta.env.VITE_BASE_URL}/auth/callback`,
        silent_redirect_uri: `${import.meta.env.VITE_BASE_URL}/auth/silent-callback`,
        post_logout_redirect_uri: `${import.meta.env.VITE_BASE_URL}/login`,
        response_type: 'code',
        scope: 'openid profile email',
        automaticSilentRenew: true,
        silentRequestTimeout: 10000,
        filterProtocolClaims: true,
        loadUserInfo: true,
        userStore: new WebStorageStateStore({ store: window.localStorage }),
        stateStore: new WebStorageStateStore({ store: window.localStorage }),
      }

      const manager = new UserManager(keycloakConfig)

      // Set up event handlers
      manager.events.addUserLoaded((loadedUser: User) => {
        user.value = loadedUser
        extractUserClaims(loadedUser)
      })

      manager.events.addUserUnloaded(() => {
        user.value = null
      })

      manager.events.addAccessTokenExpiring(() => {
        console.log('Access token expiring')
      })

      manager.events.addAccessTokenExpired(() => {
        user.value = null
      })

      const router = useRouter()

      manager.events.addSilentRenewError((error: Error) => {
        console.error('Silent renew error:', error)
        router.push('/login')
      })

      userManager.value = manager
      return manager
    }

    // Actions
    const initialize = async () => {
      if (initialized.value) return

      const manager = await initializeUserManager()
      if (!manager) {
        return
      }

      console.log('hey')

      try {
        const currentUser = await manager.getUser()
        user.value = currentUser
        if (currentUser) {
          extractUserClaims(currentUser)
        }
        console.log('Auth initialized:', { user: user.value, roles: userRoles.value })
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
        if (callbackUser) {
          extractUserClaims(callbackUser)
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
        }
        return callbackUser
      } catch (error) {
        console.error('Silent callback error:', error)
        throw error
      }
    }

    const getUser = async () => {
      const manager = initializeUserManager()
      if (!manager) return null

      try {
        const currentUser = await manager.getUser()
        user.value = currentUser
        return currentUser
      } catch (error) {
        console.error('Get user error:', error)
        return null
      }
    }

    const getAccessToken = async () => {
      const currentUser = await getUser()
      return currentUser?.access_token || null
    }

    const getCurrentUsername = async () => {
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
      return roles.some((role) => hasRole(role))
    }

    const hasAllRoles = (roles: string[]): boolean => {
      return roles.every((role) => hasRole(role))
    }

    const hasPermission = (permission: string): boolean => {
      return hasRole(permission)
    }

    // Initialize on store creation
    // if (process.client) {
    // initialize()
    // }

    return {
      // State
      user,
      initialized,

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
      hasPermission,
    }
  },
  {
    persist: true,
  },
)
