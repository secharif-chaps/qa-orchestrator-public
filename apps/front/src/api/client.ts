/**
 * API client configuration
 */
import { useAuthStore } from '@/stores/auth'
import { useEndpointResolver } from '@/composables/useEndpointResolver'
import { useToast } from '@/composables/useToast'
import { useRealtime } from '@/composables/realtime/useRealtime'
import type { RealtimeHandlers } from '@/composables/realtime/types'
import i18n from '@/i18n'
import { FetchError, ofetch, type $Fetch, type FetchOptions } from 'ofetch'

const { endpoints } = useEndpointResolver()
const API_BASE_URL = endpoints.value.apiUrl

type HTTPMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

// Only retry RFC 9110 idempotent verbs. Re-firing a POST/PATCH on a transient
// 5xx can silently duplicate a side-effect already committed server-side.
const RETRY_SAFE_METHODS = new Set<HTTPMethod>(['GET', 'PUT', 'DELETE'])

const RETRY_OPTIONS = {
  retry: 3,
  retryDelay: 1000,
  // 429 excluded: surface as ApiRateLimitError so the caller honors Retry-After.
  retryStatusCodes: [408, 409, 425, 500, 502, 503, 504],
}

// Negotiates Content-Type and Accept for callers that need a non-default media
// type. `ld+json` targets API Platform endpoints; `merge-patch+json` is the
// PATCH dialect API Platform expects.
export type MediaType = 'json' | 'ld+json' | 'merge-patch+json'

const MEDIA_TYPE_HEADERS: Record<MediaType, { contentType: string; accept: string }> = {
  json: { contentType: 'application/json', accept: 'application/json' },
  'ld+json': { contentType: 'application/ld+json', accept: 'application/ld+json' },
  'merge-patch+json': {
    contentType: 'application/merge-patch+json',
    accept: 'application/ld+json',
  },
}

export interface RealtimeSubscriptionOption<T = unknown> extends RealtimeHandlers<T> {
  // Stable identifier used to deduplicate subscriptions and unsubscribe later.
  key: string
}

export interface ErrorMessage {
  title: string
  description?: string
}

export interface RequestOptions<TRealtime = unknown> {
  query?: Record<string, unknown>
  headers?: Record<string, string>
  signal?: AbortSignal
  // Suppresses the automatic error toast. Use when the caller renders the error
  // inline (form validation, dedicated modal, etc.).
  silent?: boolean
  // Custom title/description used by the automatic error toast instead of the
  // generic translation. Lets the caller provide endpoint-specific context.
  errorMessage?: ErrorMessage
  // Sets Content-Type and Accept headers to match the target API. Defaults to
  // `json` (chapsmind / FastAPI). Use `ld+json` for API Platform endpoints and
  // `merge-patch+json` for API Platform PATCH calls.
  mediaType?: MediaType
  // Opt-in subscription to a server push channel advertised in the response.
  // The client parses the response headers, discovers the descriptor, and
  // subscribes via useRealtime. No-ops silently when the endpoint advertises none.
  realtime?: RealtimeSubscriptionOption<TRealtime>
}

export interface ValidationViolation {
  propertyPath: string
  message: string
  code: string
}

interface ValidationErrorPayload {
  status?: number
  violations?: ValidationViolation[]
  detail?: string
  description?: string
  title?: string
}

const isJsonResponse = (headers: Headers): boolean => {
  const contentType = headers.get('content-type')
  return contentType ? contentType.includes('json') : false
}

// Surfaces an error toast via the shared useToast singleton.
// Translation is resolved against the global i18n instance because apiClient is
// a module-level singleton and lives outside any Vue component setup scope.
const toastError = (
  titleKey: string,
  description?: string,
  params?: Record<string, unknown>,
): void => {
  const t = i18n.global.t
  useToast().error(t(titleKey, params ?? {}), description)
}

// Returns the recommended wait time in seconds before retrying after a 429.
// Falls back to 60s when the header is missing or malformed.
const parseRetryAfter = (headers?: Headers): number => {
  const header = headers?.get('Retry-After')
  if (!header) return 60

  const seconds = Number(header)
  if (!Number.isNaN(seconds) && seconds > 0) return seconds

  const date = Date.parse(header)
  if (!Number.isNaN(date)) {
    const diff = Math.ceil((date - Date.now()) / 1000)
    return diff > 0 ? diff : 60
  }

  return 60
}

class ApiClient {
  private fetch: $Fetch
  private baseURL: string
  private baseURLPath: string

  constructor(baseURL: string) {
    this.baseURL = baseURL
    this.baseURLPath = this.extractPath(baseURL)
    this.fetch = ofetch.create({
      baseURL,
      // Retry policy is set per call in request() so non-idempotent verbs (POST/PATCH)
      // are never silently replayed; see RETRY_SAFE_METHODS.
      // Pre-emptively refresh expired tokens to avoid an extra 401 round-trip, then set
      // Authorization at request time so retries pick up freshly-refreshed tokens.
      // The auth store deduplicates concurrent refresh calls via an internal mutex.
      onRequest: async ({ options }) => {
        const authStore = useAuthStore()
        const expiresAt = authStore.user?.expires_at
        if (expiresAt && Math.floor(Date.now() / 1000) >= expiresAt - 30) {
          await authStore.refreshToken()
        }
        const token = authStore.accessToken
        const headers = new Headers(options.headers as HeadersInit)
        if (token) headers.set('Authorization', `Bearer ${token}`)
        if (!headers.has('Content-Type')) headers.set('Content-Type', 'application/json')
        // Accept both ld+json (Target / API Platform) and plain json (chapsmind / FastAPI)
        // so a single client can hit both stacks without per-call header overrides.
        if (!headers.has('Accept')) {
          headers.set('Accept', 'application/ld+json, application/json')
        }
        if (!headers.has('Accept-Language')) {
          headers.set('Accept-Language', i18n.global.locale.value)
        }
        options.headers = headers
      },
    })
  }

  private extractPath(baseURL: string): string {
    try {
      return new URL(baseURL).pathname.replace(/\/$/, '')
    } catch {
      return baseURL.replace(/\/$/, '')
    }
  }

  // Avoids /api/api/ when callers pass paths that already include the baseURL prefix
  // (e.g. JSON-LD `view.next` returns a path that already starts with /api).
  private normalizePath(path: string): string {
    if (path.startsWith(this.baseURL)) {
      const stripped = path.slice(this.baseURL.length)
      return stripped.startsWith('/') ? stripped : `/${stripped}`
    }
    if (
      this.baseURLPath &&
      (path === this.baseURLPath || path.startsWith(`${this.baseURLPath}/`))
    ) {
      return path.slice(this.baseURLPath.length) || '/'
    }
    return path
  }

  private async request<T, M = unknown>(
    method: HTTPMethod,
    path: string,
    options: RequestOptions<M> & { body?: unknown; retry?: boolean } = {},
  ): Promise<T> {
    const {
      retry = true,
      silent = false,
      errorMessage,
      mediaType,
      realtime,
      body,
      query,
      headers,
      signal,
    } = options

    const mergedHeaders = { ...(headers ?? {}) }
    if (mediaType) {
      const { contentType, accept } = MEDIA_TYPE_HEADERS[mediaType]
      if (!('Content-Type' in mergedHeaders)) mergedHeaders['Content-Type'] = contentType
      if (!('Accept' in mergedHeaders)) mergedHeaders['Accept'] = accept
    }

    const fetchOptions: FetchOptions<'json'> = {
      method,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      query,
      headers: mergedHeaders,
      signal,
      ...(RETRY_SAFE_METHODS.has(method) ? RETRY_OPTIONS : { retry: 0 }),
    }

    try {
      const response = await this.fetch.raw<T>(this.normalizePath(path), fetchOptions)

      if (realtime) {
        const { key, ...handlers } = realtime
        // A subscription failure must never propagate as a REST error: the data
        // is already in hand and callers expect realtime to be best-effort.
        try {
          await useRealtime().subscribeFromHeaders(response.headers, key, handlers)
        } catch (err) {
          console.error('[apiClient] realtime subscription failed:', err)
        }
      }

      if (response._data !== undefined && isJsonResponse(response.headers)) {
        return response._data as T
      }
      return null as T
    } catch (error) {
      if (error instanceof FetchError) {
        const status = error.statusCode ?? error.response?.status ?? 0
        const message = error.statusMessage ?? error.message

        if (status === 401) {
          const authStore = useAuthStore()
          if (retry) {
            const refreshed = await authStore.refreshToken()
            if (refreshed) {
              return this.request<T, M>(method, path, { ...options, retry: false })
            }
          }
          // Refresh failed (or already retried)
          await authStore.signOut()
          throw new ApiUnauthorizedError(`HTTP 401: ${message}`, error.data)
        }

        if (status === 402) {
          // No toast: callers render a dedicated modal for InsufficientTokensError.
          throw new InsufficientTokensError((error.data as Record<string, unknown>) ?? {})
        }

        if (status === 422) {
          const validationError = new ApiValidationError(
            (error.data as ValidationErrorPayload) ?? {},
          )
          if (!silent) {
            if (errorMessage) {
              useToast().error(errorMessage.title, errorMessage.description)
            } else {
              toastError('common.errors.validation', validationError.message)
            }
          }
          throw validationError
        }

        if (status === 429) {
          const retryAfter = parseRetryAfter(error.response?.headers)
          const rateLimitError = new ApiRateLimitError(
            `HTTP 429: ${message}`,
            retryAfter,
            error.data,
          )
          if (!silent) {
            if (errorMessage) {
              useToast().error(errorMessage.title, errorMessage.description)
            } else {
              toastError('common.errors.rateLimit', undefined, { seconds: retryAfter })
            }
          }
          throw rateLimitError
        }

        if (!silent) {
          if (errorMessage) {
            useToast().error(errorMessage.title, errorMessage.description)
          } else {
            toastError('common.errors.unexpected', message)
          }
        }
        throw new ApiError(`HTTP ${status}: ${message}`, status, error.data)
      }
      throw error
    }
  }

  async get<T, M = unknown>(path: string, options: RequestOptions<M> = {}): Promise<T> {
    return this.request<T, M>('GET', path, options)
  }

  async post<T, M = unknown>(
    path: string,
    body: unknown,
    options: RequestOptions<M> = {},
  ): Promise<T> {
    return this.request<T, M>('POST', path, { ...options, body })
  }

  async put<T, M = unknown>(
    path: string,
    body: unknown,
    options: RequestOptions<M> = {},
  ): Promise<T> {
    return this.request<T, M>('PUT', path, { ...options, body })
  }

  async patch<T, M = unknown>(
    path: string,
    body: unknown,
    options: RequestOptions<M> = {},
  ): Promise<T> {
    return this.request<T, M>('PATCH', path, { ...options, body })
  }

  async delete<M = unknown>(path: string, options: RequestOptions<M> = {}): Promise<void> {
    await this.request<unknown, M>('DELETE', path, options)
  }
}

class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data?: unknown,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

class ApiUnauthorizedError extends ApiError {
  constructor(message = 'Unauthorized', data?: unknown) {
    super(message, 401, data)
    this.name = 'ApiUnauthorizedError'
  }
}

class ApiRateLimitError extends ApiError {
  public readonly retryAfter: number

  constructor(message = 'Rate limit exceeded', retryAfter: number, data?: unknown) {
    super(message, 429, data)
    this.name = 'ApiRateLimitError'
    this.retryAfter = retryAfter
  }
}

class ApiValidationError extends ApiError {
  public readonly violations: ValidationViolation[]

  constructor(payload: ValidationErrorPayload) {
    super(payload.detail ?? payload.title ?? 'Validation failed', 422, payload)
    this.name = 'ApiValidationError'
    this.violations = payload.violations ?? []
  }
}

/**
 * Error thrown when token balance is insufficient for an operation.
 *
 * Supports both the old module-based format (current_tokens) and
 * the new global token format (current_balance) for backward compatibility.
 */
class InsufficientTokensError extends Error {
  public readonly currentBalance: number
  public readonly requiredTokens: number

  constructor(data: Record<string, unknown>) {
    const message = (data.message as string) || 'Insufficient tokens for this operation'
    super(message)
    this.name = 'InsufficientTokensError'
    // Support both old (current_tokens) and new (current_balance) field names
    this.currentBalance = (data.current_balance as number) ?? (data.current_tokens as number) ?? 0
    this.requiredTokens = (data.required_tokens as number) ?? 1
  }

  // Alias for backward compatibility
  get currentTokens(): number {
    return this.currentBalance
  }
}

export const apiClient = new ApiClient(API_BASE_URL)
export {
  ApiError,
  ApiUnauthorizedError,
  ApiRateLimitError,
  ApiValidationError,
  InsufficientTokensError,
}
