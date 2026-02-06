import { useAuthStore } from '@/stores/auth'

export const useAuth = () => {
  const authStore = useAuthStore()

  return {
    user: authStore.user,
    isAuthenticated: authStore.isAuthenticated,
    currentUser: authStore.currentUser,
    accessToken: authStore.accessToken,
    username: authStore.username,
    userRoles: authStore.userRoles,

    signIn: authStore.signIn,
    signOut: authStore.signOut,
    handleCallback: authStore.handleCallback,
    handleSilentCallback: authStore.handleSilentCallback,
    getUser: authStore.getUser,
    getAccessToken: authStore.getAccessToken,
    getCurrentUsername: authStore.getCurrentUsername,
    refreshToken: authStore.refreshToken,
    
    hasPermission: authStore.hasPermission,
  }
}
