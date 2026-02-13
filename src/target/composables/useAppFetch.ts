import { ofetch } from 'ofetch';
import { useI18n } from 'vue-i18n';
import { config } from '~/config';
import { useAuth } from './useAuth';

import {
  ApiError,
  ApiRateLimitError,
  ApiUnauthorizedError,
  ApiValidationError,
  type ValidationError,
} from '~/types/jsonld';

export function useAppFetch() {
  const baseURL = config.apiBaseUrl;
  const auth = useAuth();
  const { t, locale } = useI18n();

  async function getAuthenticationToken() {
    if (auth.isTokenExpired()) {
      try {
        await auth.updateToken();
      } catch (error) {
        throw new ApiError(t('common.errors.token_refresh_failed'), 401, error);
      }
    }
    return auth.getToken();
  }

  return ofetch.create({
    baseURL,
    headers: {
      'Content-Type': 'application/ld+json',
      Accept: 'application/ld+json',
    },
    async onRequest({ options }) {
      // Set authorization header dynamically for each request
      const token = await getAuthenticationToken();

      // Ensure headers is a Headers instance and set authorization
      if (!(options.headers instanceof Headers)) {
        options.headers = new Headers(options.headers);
      }
      options.headers.set('Authorization', `Bearer ${token}`);

      // Set Accept-Language header with current locale
      options.headers.set('Accept-Language', locale.value);
    },
    onResponseError({ response }) {
      if (!response.ok && response.status !== 204) {
        switch (response.status) {
          case 401:
            throw new ApiUnauthorizedError(t('common.errors.unauthorized'));
          case 403:
            throw new ApiError(
              t('common.errors.forbidden'),
              response.status,
              null,
            );
          case 422:
            throw new ApiValidationError(response._data as ValidationError);
          case 429:
            throw new ApiRateLimitError(response._data?.detail);
          default:
            throw new ApiError(
              response._data?.detail || t('common.errors.unexpected'),
              response.status,
              response._data,
            );
        }
      }
    },
    retry: 3,
    retryDelay: 1000,
  });
}
