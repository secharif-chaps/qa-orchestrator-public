import { User, UserManager, WebStorageStateStore } from 'oidc-client-ts';
import { computed, readonly, ref, shallowRef } from 'vue';
import { config } from '~/config';

let manager: UserManager | null = null;
const currentUser = shallowRef<User | null>(null);
const isAuthenticated = ref<boolean>(false);
const isAuthProviderReady = ref<boolean>(false);
const authError = ref<string | null>(null);
const isTokenRefreshing = ref<boolean>(false);

const getManager = (): UserManager => {
  if (manager) return manager;

  manager = new UserManager({
    authority: `${config.oidcBaseUrl}/realms/${config.oidcRealm}`,
    client_id: config.oidcClientId,
    redirect_uri: window.location.origin + '/',
    post_logout_redirect_uri: config.oidcLogoutRedirectUri,
    response_type: 'code',
    scope: 'openid profile email',
    automaticSilentRenew: true,
    loadUserInfo: true,
    userStore: new WebStorageStateStore({ store: window.localStorage }),
  });

  // Event handlers
  manager.events.addUserLoaded((loaded: User) => {
    currentUser.value = loaded;
    isAuthenticated.value = true;
    authError.value = null;
  });

  manager.events.addUserUnloaded(() => {
    currentUser.value = null;
    isAuthenticated.value = false;
  });

  manager.events.addAccessTokenExpired(() => {
    isAuthenticated.value = false;
  });

  manager.events.addSilentRenewError((error: Error) => {
    authError.value = error.message || 'Silent renew failed';
    console.error('Silent renew error:', error);
  });

  return manager;
};

export function useAuth() {
  const mgr = getManager();

  const init = async () => {
    try {
      const params = new URLSearchParams(window.location.search);
      const hasCallback = params.has('code') && params.has('state');

      if (hasCallback) {
        // Process the OIDC redirect callback
        const user = await mgr.signinRedirectCallback();
        currentUser.value = user;
        isAuthenticated.value = true;

        // Clean callback params from URL
        window.history.replaceState({}, '', window.location.pathname);
      } else {
        // Try to restore session from storage
        const user = await mgr.getUser();
        if (user && !user.expired) {
          currentUser.value = user;
          isAuthenticated.value = true;
        } else {
          // No valid session → redirect to Keycloak login
          await mgr.signinRedirect();
          return; // signinRedirect navigates away, won't reach here
        }
      }
    } catch (e) {
      throw new Error('Authentication provider initialization failed', {
        cause: e,
      });
    } finally {
      isAuthProviderReady.value = true;
    }
  };

  const getToken = async (): Promise<string | null> => {
    return currentUser.value?.access_token ?? null;
  };

  const isTokenExpired = (): boolean => {
    return currentUser.value?.expired ?? false;
  };

  const refreshToken = async (): Promise<boolean> => {
    if (isTokenRefreshing.value) return false;

    try {
      isTokenRefreshing.value = true;
      authError.value = null;
      const user = await mgr.signinSilent();
      if (user) {
        currentUser.value = user;
        isAuthenticated.value = true;
        return true;
      }
      return false;
    } catch (error) {
      authError.value = 'Token refresh failed';
      console.error('Token refresh failed:', error);
      return false;
    } finally {
      isTokenRefreshing.value = false;
    }
  };

  const updateToken = async () => {
    return await refreshToken();
  };

  const logout = () => {
    mgr.signoutRedirect({
      post_logout_redirect_uri: config.oidcLogoutRedirectUri,
    });
  };

  // Computed properties
  const isLoading = computed(
    () => !isAuthProviderReady.value || isTokenRefreshing.value,
  );
  const hasError = computed(() => authError.value !== null);

  const userEmail = computed(
    () => currentUser.value?.profile?.email || null,
  );
  const userName = computed(
    () => currentUser.value?.profile?.name || null,
  );

  const isInternalUser = computed(
    () =>
      typeof userEmail.value === 'string' &&
      userEmail.value.endsWith('@chapsvision.com'),
  );

  const getValidToken = async (): Promise<string | null> => {
    if (!currentUser.value) return null;

    if (currentUser.value.expired) {
      const refreshed = await refreshToken();
      if (!refreshed) return null;
    }

    return currentUser.value.access_token || null;
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
  };
}
