import { useAuthStore } from '@/stores/auth';
import { computed, readonly, ref } from 'vue';

const authError = ref<string | null>(null);
const isTokenRefreshing = ref<boolean>(false);

// Delegates to the main app's auth store while keeping the same API
// that target consumers expect
export function useAuth() {
  const authStore = useAuthStore();

  const isAuthProviderReady = computed(() => authStore.initialized);
  const isAuthenticated = computed(() => authStore.isAuthenticated);

  const userName = computed(() => authStore.username);
  const userEmail = computed(
    () => authStore.user?.profile?.email || null,
  );

  const isInternalUser = computed(
    () =>
      typeof userEmail.value === 'string' &&
      userEmail.value.endsWith('@chapsvision.com'),
  );

  const isLoading = computed(
    () => !authStore.initialized || isTokenRefreshing.value,
  );
  const hasError = computed(() => authError.value !== null);

  const init = async () => {
    await authStore.initialize();
  };

  const getToken = async (): Promise<string | null> => {
    return authStore.accessToken;
  };

  const isTokenExpired = (): boolean => {
    return authStore.user?.expired ?? false;
  };

  const refreshToken = async (): Promise<boolean> => {
    if (isTokenRefreshing.value) return false

    try {
      isTokenRefreshing.value = true;
      authError.value = null;
      const result = await authStore.refreshToken();
      return !!result;
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
    authStore.signOut();
  };

  const getValidToken = async (): Promise<string | null> => {
    if (!authStore.user) return null;

    if (authStore.user.expired) {
      const refreshed = await refreshToken();
      if (!refreshed) return null;
    }

    return authStore.accessToken;
  };

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
