import { useEndpointResolver } from '@/composables/useEndpointResolver'
import { ofetch } from 'ofetch'
import { useI18n } from 'vue-i18n'
import { useAuth } from './useAuth'
import { useRateLimit } from './useRateLimit'

import {
  ApiError,
  ApiRateLimitError,
  ApiUnauthorizedError,
  ApiValidationError,
  type ValidationError,
} from '@target/types/jsonld'
import { parseRetryAfter } from '@target/utils/parseRetryAfter'

export function useAppFetch() {
  const { endpoints } = useEndpointResolver()
  const baseURL = endpoints.value.apiUrl
  const auth = useAuth()
  const { t, locale } = useI18n()
  const { isRateLimited, getRemainingSeconds, setRateLimit } = useRateLimit()

  async function getAuthenticationToken() {
    if (auth.isTokenExpired()) {
      try {
        await auth.updateToken()
      } catch (error) {
        throw new ApiError(t('common.errors.token_refresh_failed'), 401, error)
      }
    }
    return auth.getToken()
  }

  return ofetch.create({
    baseURL,
    headers: {
      'Content-Type': 'application/ld+json',
      Accept: 'application/ld+json',
    },
    async onRequest({ options }) {
      if (isRateLimited.value) {
        throw new ApiRateLimitError(
          t('common.errors.rate_limit', { seconds: getRemainingSeconds() }),
        )
      }

      const token = await getAuthenticationToken()

      if (!(options.headers instanceof Headers)) {
        options.headers = new Headers(options.headers)
      }
      options.headers.set('Authorization', `Bearer ${token}`)
      options.headers.set('Accept-Language', locale.value)
    },
    onResponseError({ response }) {
      if (!response.ok && response.status !== 204) {
        switch (response.status) {
          case 401:
            throw new ApiUnauthorizedError(t('common.errors.unauthorized'))
          case 403:
            throw new ApiError(t('common.errors.forbidden'), response.status, null)
          case 422:
            throw new ApiValidationError(response._data as ValidationError)
          case 429: {
            const retryAfterSeconds = parseRetryAfter(response)
            setRateLimit(retryAfterSeconds)
            throw new ApiRateLimitError(
              response._data?.detail ||
                t('common.errors.rate_limit', { seconds: retryAfterSeconds }),
            )
          }
          default:
            throw new ApiError(
              response._data?.detail || t('common.errors.unexpected'),
              response.status,
              response._data,
            )
        }
      }
    },
    retry: 3,
    retryDelay: 1000,
    // ofetch do not use blacklists, only whitelist. We are using default retryStatusCodes minus 429: rate limit must not be retried automatically
    retryStatusCodes: [408, 409, 425, 500, 502, 503, 504],
  })
}
