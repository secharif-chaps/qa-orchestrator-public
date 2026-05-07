import { useEndpointResolver } from '@/composables/useEndpointResolver'
import { jwtDecode } from 'jwt-decode'
import { User, UserManager, WebStorageStateStore } from 'oidc-client-ts'
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
    // Use expires_at directly instead of the `expired` getter.
    // Pinia persistence JSON-serializes the User object, which strips
    // prototype getters like `expired`. Checking expires_at works on
    // both real User instances and deserialized plain objects.
    const isAuthenticated = computed(() => {
      if (!user.value) return false
      const expiresAt = user.value.expires_at
      if (!expiresAt) return false
      return Math.floor(Date.now() / 1000) < expiresAt
    })
    const currentUser = computed(() => user.value)
    const accessToken = computed(() => user.value?.access_token || null)
    const userId = computed(() => user.value?.profile?.sub || null)
    const username = computed(
      () => user.value?.profile?.preferred_username || user.value?.profile?.sub || 'unknown',
    )

    // Computed properties for roles and permissions - reactive and always up-to-date
    const userRoles = computed<string[]>(() => {
      if (!user.value?.profile) return []

      const token = jwtDecode(user.value?.access_token || '') as {
        realm_access: { roles: string[] }
      }

      return token.realm_access?.roles || []
    })

    const userPermissions = computed<string[]>(() => {
      // For now, treat roles as permissions
      // You can customize this to map roles to specific permissions
      return userRoles.value
    })

    // Organization context from JWT token
    const organizationId = computed<string | null>(() => {
      if (!user.value?.access_token) return null

      try {
        const token = jwtDecode(user.value.access_token) as {
          organization_id?: string
        }
        return token.organization_id || null
      } catch {
        return null
      }
    })

    const organizationName = computed<string | null>(() => {
      if (!user.value?.access_token) return null

      try {
        const token = jwtDecode(user.value.access_token) as {
          organization_name?: string
        }
        return token.organization_name || null
      } catch {
        return null
      }
    })

    const { endpoints } = useEndpointResolver()

    // Mutex: when multiple concurrent requests get 401, they all call
    // refreshToken(). Without deduplication, each would fire signinSilent()
    // in parallel, which can cause Keycloak to reject some of them.
    let pendingRefresh: Promise<User | null> | null = null

    const refreshToken = async (): Promise<User | null> => {
      // Deduplicate: if a refresh is already in flight, reuse it
      if (pendingRefresh) return pendingRefresh

      pendingRefresh = (async () => {
        const manager = initializeUserManager()
        if (!manager) return null

        try {
          const currentUser = await manager.getUser()
          if (
            currentUser &&
            currentUser.expires_at !== undefined &&
            Math.floor(Date.now() / 1000) >= currentUser.expires_at
          ) {
            const refreshedUser = await manager.signinSilent()
            user.value = refreshedUser
            return refreshedUser
          }
          return currentUser
        } catch (error) {
          console.error('Refresh token error:', error)
          return null
        }
      })()

      try {
        return await pendingRefresh
      } finally {
        pendingRefresh = null
      }
    }

    // Initialize UserManager
    const initializeUserManager = () => {
      if (userManager.value) return userManager.value

      const keycloakConfig = {
        authority: `${endpoints.value.keycloakUrl}/realms/${endpoints.value.keycloakRealm}`,
        client_id: endpoints.value.keycloakClientId as string,
        redirect_uri: `${endpoints.value.baseUrl}/auth/callback`,
        silent_redirect_uri: `${endpoints.value.baseUrl}/auth/silent-callback`,
        post_logout_redirect_uri: `${endpoints.value.baseUrl}/login`,
        response_type: 'code',
        scope: 'openid profile email organization',
        automaticSilentRenew: true,
        silentRequestTimeoutInSeconds: 10,
        filterProtocolClaims: true,
        loadUserInfo: true,
        userStore: new WebStorageStateStore({ store: window.localStorage }),
        stateStore: new WebStorageStateStore({ store: window.localStorage }),
      }

      const manager = new UserManager(keycloakConfig)

      // Set up event handlers
      manager.events.addUserLoaded((loadedUser: User) => {
        user.value = loadedUser
        // No need to extract claims anymore - computed properties handle it
      })

      manager.events.addUserUnloaded(() => {
        user.value = null
      })

      manager.events.addAccessTokenExpiring(() => {
        console.log('Access token expiring')
      })

      manager.events.addAccessTokenExpired(async () => {
        try {
          await refreshToken()
        } catch {
          user.value = null
        }
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
      // Always set up UserManager on startup — it's not persisted across page reloads.
      // Without this, automaticSilentRenew never starts and the first user action
      // triggers token renewal instead of it happening silently in the background.
      const manager = initializeUserManager()
      if (!manager) return

      // Always sync the user from UserManager, even if `initialized` was
      // persisted as true from a previous session. The Pinia-persisted user
      // is a plain object (missing getters), while UserManager holds the
      // canonical token state including a valid refresh_token.
      try {
        const currentUser = await manager.getUser()
        user.value = currentUser
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
        return callbackUser
      } catch (error) {
        console.error('Callback error:', error)
        throw error
      }
    }

    const handleSilentCallback = async () => {
      const manager = initializeUserManager()
      if (!manager) return

      try {
        // In oidc-client-ts v3, signinSilentCallback returns void
        // The user is loaded through the userLoaded event handler
        await manager.signinSilentCallback()
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

    // Permission checking utilities
    const hasPermission = (permission: string): boolean => {
      return userRoles.value.includes(permission)
    }

    const hasAnyPermission = (permissions: string[]): boolean => {
      return permissions.some((permission) => hasPermission(permission))
    }

    const hasAllPermissions = (permissions: string[]): boolean => {
      return permissions.every((permission) => hasPermission(permission))
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
      userId,
      username,
      userRoles,
      userPermissions,
      organizationId,
      organizationName,

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

      // Permission utilities
      hasPermission,
      hasAnyPermission,
      hasAllPermissions,
    }
  },
  {
    persist: true,
  },
)
