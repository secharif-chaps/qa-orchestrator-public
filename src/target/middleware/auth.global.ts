import type { NavigationGuardWithThis } from 'vue-router';
import { useAuth } from '~/composables/useAuth';

const authMiddleware: NavigationGuardWithThis<undefined> = () => {
  const { isAuthenticated, isAuthProviderReady } = useAuth();
  if (!isAuthProviderReady?.value) {
    return false;
  }

  if (!isAuthenticated?.value) {
    return false;
  }
};

export default authMiddleware;
