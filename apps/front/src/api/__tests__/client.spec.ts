/**
 * Tests for src/api/client.ts (apiClient).
 *
 * Strategy: stub `globalThis.fetch` so we exercise the real ofetch wrapper
 * (retry, headers negotiation, timeout) instead of mocking ofetch itself.
 * Module-level dependencies (auth store, toast, realtime, endpoint resolver)
 * are mocked at module load.
 *
 * Note: apiClient is a module-level singleton instantiated against the mocked
 * baseURL, so all tests share the same instance — this is fine because ofetch
 * resolves `globalThis.fetch` at call-time, not at construction.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// ── Module mocks ─────────────────────────────────────────────────────────────

vi.mock('@/composables/useEndpointResolver', () => ({
  useEndpointResolver: () => ({
    endpoints: { value: { apiUrl: 'http://localhost/api' } },
  }),
}))

const mockSignOut = vi.fn(async () => undefined)
const mockRefreshToken = vi.fn(async () => null as unknown)
const authState = {
  user: { expires_at: Math.floor(Date.now() / 1000) + 3600 } as { expires_at: number } | null,
  accessToken: 'test-access-token' as string | null,
}

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get user() {
      return authState.user
    },
    get accessToken() {
      return authState.accessToken
    },
    refreshToken: mockRefreshToken,
    signOut: mockSignOut,
  }),
}))

const mockToastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    error: mockToastError,
    success: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  }),
}))

const mockSubscribeFromHeaders = vi.fn(async () => undefined)
vi.mock('@/composables/realtime/useRealtime', () => ({
  useRealtime: () => ({
    subscribe: vi.fn(),
    subscribeFromHeaders: mockSubscribeFromHeaders,
    unsubscribe: vi.fn(),
    unsubscribeAll: vi.fn(),
    isConnected: { value: true },
    activeSubscriptions: { value: [] },
  }),
}))

// `i18n` is already mocked globally in test-utils/setup.ts

// ── Helpers ──────────────────────────────────────────────────────────────────

interface MockResponseOptions {
  status?: number
  body?: unknown
  headers?: Record<string, string>
}

const mockResponse = ({ status = 200, body, headers = {} }: MockResponseOptions = {}): Response => {
  const responseHeaders = new Headers({ 'content-type': 'application/json', ...headers })
  const payload = body === undefined ? null : JSON.stringify(body)
  return new Response(payload, { status, headers: responseHeaders })
}

const mockFetchOnce = (...responses: Response[]) => {
  const fetchMock = vi.fn()
  for (const r of responses) fetchMock.mockResolvedValueOnce(r)
  globalThis.fetch = fetchMock as unknown as typeof globalThis.fetch
  return fetchMock
}

const mockFetchAlways = (response: Response) => {
  const fetchMock = vi.fn().mockResolvedValue(response)
  globalThis.fetch = fetchMock as unknown as typeof globalThis.fetch
  return fetchMock
}

// ── Imports under test ───────────────────────────────────────────────────────
// Imported lazily after mocks are installed.
import {
  apiClient,
  ApiError,
  ApiRateLimitError,
  ApiUnauthorizedError,
  ApiValidationError,
  InsufficientTokensError,
} from '@/api/client'

// ── Test setup ───────────────────────────────────────────────────────────────

beforeEach(() => {
  mockSignOut.mockClear()
  mockRefreshToken.mockClear().mockResolvedValue(null)
  mockToastError.mockClear()
  mockSubscribeFromHeaders.mockClear()
  authState.user = { expires_at: Math.floor(Date.now() / 1000) + 3600 }
  authState.accessToken = 'test-access-token'
})

afterEach(() => {
  vi.useRealTimers()
})

// ─────────────────────────────────────────────────────────────────────────────
// Critical 1: Retry whitelist by HTTP method
// ─────────────────────────────────────────────────────────────────────────────

describe('retry whitelist', () => {
  // We use fake timers so ofetch's retryDelay (1s) doesn't slow tests down.
  beforeEach(() => {
    vi.useFakeTimers()
  })

  it('retries GET 3 times on 502 (4 calls total)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 502 }))

    const promise = apiClient.get('/foo').catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(4)
  })

  it('retries PUT 3 times on 503', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 503 }))

    const promise = apiClient.put('/foo', {}).catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(4)
  })

  it('retries DELETE 3 times on 504', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 504 }))

    const promise = apiClient.delete('/foo').catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(4)
  })

  it('does NOT retry POST on 502 (single call)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 502 }))

    const promise = apiClient.post('/foo', {}).catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('does NOT retry PATCH on 504 (single call)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 504 }))

    const promise = apiClient.patch('/foo', {}).catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('does NOT retry GET on 429 (rate limit must surface, not auto-replay)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 429 }))

    const promise = apiClient.get('/foo').catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('does NOT retry on non-whitelist statuses (e.g. 404)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ status: 404 }))

    const promise = apiClient.get('/foo').catch(() => undefined)
    await vi.runAllTimersAsync()
    await promise

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Critical 2: Typed errors
// ─────────────────────────────────────────────────────────────────────────────

describe('typed errors', () => {
  it('throws ApiUnauthorizedError on 401 when refresh fails, and calls signOut', async () => {
    mockFetchAlways(mockResponse({ status: 401, body: { detail: 'Token expired' } }))
    mockRefreshToken.mockResolvedValue(null)

    await expect(apiClient.get('/foo')).rejects.toBeInstanceOf(ApiUnauthorizedError)
    expect(mockRefreshToken).toHaveBeenCalled()
    expect(mockSignOut).toHaveBeenCalledTimes(1)
  })

  it('retries the request once on 401 when refresh succeeds, then resolves', async () => {
    const fetchMock = mockFetchOnce(
      mockResponse({ status: 401 }),
      mockResponse({ status: 200, body: { id: 1 } }),
    )
    mockRefreshToken.mockResolvedValue({ access_token: 'fresh-token' })

    const result = await apiClient.get<{ id: number }>('/foo')

    expect(result).toEqual({ id: 1 })
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(mockSignOut).not.toHaveBeenCalled()
  })

  it('throws InsufficientTokensError on 402 with currentBalance and requiredTokens', async () => {
    mockFetchAlways(
      mockResponse({
        status: 402,
        body: { current_balance: 50, required_tokens: 100, message: 'Not enough tokens' },
      }),
    )

    await expect(apiClient.post('/foo', {})).rejects.toMatchObject({
      name: 'InsufficientTokensError',
      currentBalance: 50,
      requiredTokens: 100,
    })
  })

  it('throws ApiValidationError on 422 with violations', async () => {
    const violations = [{ propertyPath: 'email', message: 'Invalid format', code: 'invalid' }]
    mockFetchAlways(
      mockResponse({ status: 422, body: { detail: 'Validation failed', violations } }),
    )

    await expect(apiClient.post('/foo', {})).rejects.toMatchObject({
      name: 'ApiValidationError',
      violations,
    })
  })

  it('throws ApiRateLimitError on 429 with retryAfter parsed from header', async () => {
    mockFetchAlways(
      mockResponse({
        status: 429,
        headers: { 'Retry-After': '42' },
        body: { detail: 'slow down' },
      }),
    )

    await expect(apiClient.get('/foo')).rejects.toMatchObject({
      name: 'ApiRateLimitError',
      retryAfter: 42,
    })
  })

  it('falls back to retryAfter=60 when Retry-After is absent', async () => {
    mockFetchAlways(mockResponse({ status: 429 }))

    await expect(apiClient.get('/foo')).rejects.toMatchObject({
      name: 'ApiRateLimitError',
      retryAfter: 60,
    })
  })

  it('throws generic ApiError on non-typed 4xx (e.g. 404)', async () => {
    mockFetchAlways(mockResponse({ status: 404, body: { detail: 'Not found' } }))

    await expect(apiClient.get('/foo')).rejects.toMatchObject({
      name: 'ApiError',
      status: 404,
    })
  })

  it('exports all error classes as a stable surface', () => {
    expect(ApiError).toBeDefined()
    expect(ApiUnauthorizedError).toBeDefined()
    expect(ApiRateLimitError).toBeDefined()
    expect(ApiValidationError).toBeDefined()
    expect(InsufficientTokensError).toBeDefined()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Critical 3: normalizePath (fixes /api/api/ duplication)
// ─────────────────────────────────────────────────────────────────────────────

describe('normalizePath', () => {
  it('strips full baseURL prefix to avoid /api/api/', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('http://localhost/api/conversations/123/messages')

    const calledUrl = fetchMock.mock.calls[0]?.[0] as string
    expect(calledUrl).toBe('http://localhost/api/conversations/123/messages')
  })

  it('strips baseURL pathname when caller passes a path-only prefixed URL', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    // Real-world case: JSON-LD `view.next` returns `/api/...` which would
    // otherwise concat with baseURL `http://localhost/api` → `/api/api/...`.
    await apiClient.get('/api/conversations/123/messages?cursor[lt]=42')

    const calledUrl = fetchMock.mock.calls[0]?.[0] as string
    expect(calledUrl).toBe('http://localhost/api/conversations/123/messages?cursor[lt]=42')
  })

  it('passes through paths that do not collide with baseURL', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/companies/42')

    const calledUrl = fetchMock.mock.calls[0]?.[0] as string
    expect(calledUrl).toBe('http://localhost/api/companies/42')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Behavioral 4: Auto-toast / silent / errorMessage
// ─────────────────────────────────────────────────────────────────────────────

describe('auto-toast on errors', () => {
  it('toasts on 422 by default', async () => {
    mockFetchAlways(
      mockResponse({ status: 422, body: { detail: 'Validation failed', violations: [] } }),
    )

    await apiClient.post('/foo', {}).catch(() => undefined)

    expect(mockToastError).toHaveBeenCalledTimes(1)
  })

  it('toasts on 429 by default', async () => {
    mockFetchAlways(mockResponse({ status: 429 }))

    await apiClient.get('/foo').catch(() => undefined)

    expect(mockToastError).toHaveBeenCalledTimes(1)
  })

  it('toasts on generic 4xx (e.g. 404) by default', async () => {
    mockFetchAlways(mockResponse({ status: 404, body: { detail: 'Not found' } }))

    await apiClient.get('/foo').catch(() => undefined)

    expect(mockToastError).toHaveBeenCalledTimes(1)
  })

  it('does NOT toast on 401 (auth flow handles signOut)', async () => {
    mockFetchAlways(mockResponse({ status: 401 }))
    mockRefreshToken.mockResolvedValue(null)

    await apiClient.get('/foo').catch(() => undefined)

    expect(mockToastError).not.toHaveBeenCalled()
  })

  it('does NOT toast on 402 (caller renders dedicated modal)', async () => {
    mockFetchAlways(mockResponse({ status: 402, body: { current_balance: 0 } }))

    await apiClient.post('/foo', {}).catch(() => undefined)

    expect(mockToastError).not.toHaveBeenCalled()
  })

  it('respects silent: true and skips the toast', async () => {
    mockFetchAlways(mockResponse({ status: 404 }))

    await apiClient.get('/foo', { silent: true }).catch(() => undefined)

    expect(mockToastError).not.toHaveBeenCalled()
  })

  it('still throws when silent: true (silent only affects the toast)', async () => {
    mockFetchAlways(mockResponse({ status: 404 }))

    await expect(apiClient.get('/foo', { silent: true })).rejects.toBeInstanceOf(ApiError)
  })

  it('uses errorMessage.title instead of the generic translation when provided', async () => {
    mockFetchAlways(mockResponse({ status: 500 }))

    await apiClient
      .post('/foo', {}, { errorMessage: { title: 'Custom error title', description: 'oops' } })
      .catch(() => undefined)

    expect(mockToastError).toHaveBeenCalledWith('Custom error title', 'oops')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Behavioral 5: Preemptive token refresh (30s buffer)
// ─────────────────────────────────────────────────────────────────────────────

describe('preemptive token refresh', () => {
  it('does NOT refresh when token expires in more than 30s', async () => {
    authState.user = { expires_at: Math.floor(Date.now() / 1000) + 60 }
    mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(mockRefreshToken).not.toHaveBeenCalled()
  })

  it('refreshes when token expires within 30s', async () => {
    authState.user = { expires_at: Math.floor(Date.now() / 1000) + 20 }
    mockRefreshToken.mockResolvedValue({ access_token: 'fresh' })
    mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(mockRefreshToken).toHaveBeenCalledTimes(1)
  })

  it('refreshes when the token is already expired', async () => {
    authState.user = { expires_at: Math.floor(Date.now() / 1000) - 10 }
    mockRefreshToken.mockResolvedValue({ access_token: 'fresh' })
    mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(mockRefreshToken).toHaveBeenCalledTimes(1)
  })

  it('does not refresh when there is no user (unauthenticated)', async () => {
    authState.user = null
    mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(mockRefreshToken).not.toHaveBeenCalled()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Behavioral 6: Default headers
// ─────────────────────────────────────────────────────────────────────────────

describe('default headers', () => {
  const getHeaders = (fetchMock: ReturnType<typeof vi.fn>): Headers => {
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit
    return new Headers(init.headers as HeadersInit)
  }

  it('sets Authorization: Bearer <token> when access token is present', async () => {
    authState.accessToken = 'my-token'
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(getHeaders(fetchMock).get('Authorization')).toBe('Bearer my-token')
  })

  it('omits Authorization when no access token', async () => {
    authState.accessToken = null
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(getHeaders(fetchMock).get('Authorization')).toBeNull()
  })

  it('sets Accept-Language from i18n locale by default', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    // The global i18n mock returns 'en-US' as locale.
    expect(getHeaders(fetchMock).get('Accept-Language')).toBe('en-US')
  })

  it('sets default Content-Type to application/json', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.post('/foo', { hello: 'world' })

    expect(getHeaders(fetchMock).get('Content-Type')).toBe('application/json')
  })

  it('sets default Accept to ld+json, json (dual-stack)', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(getHeaders(fetchMock).get('Accept')).toBe('application/ld+json, application/json')
  })

  it('lets the caller override headers via options.headers', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo', { headers: { 'X-Custom': 'hello', Accept: 'text/plain' } })

    const h = getHeaders(fetchMock)
    expect(h.get('X-Custom')).toBe('hello')
    expect(h.get('Accept')).toBe('text/plain')
  })

  it('does NOT let the caller override Authorization (auth comes from the store)', async () => {
    // The onRequest hook unconditionally sets Authorization from the auth
    // store. This is intentional: a caller can't smuggle an arbitrary token
    // through `headers`. Documents the security boundary.
    authState.accessToken = 'store-token'
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo', { headers: { Authorization: 'Bearer caller-token' } })

    expect(getHeaders(fetchMock).get('Authorization')).toBe('Bearer store-token')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Behavioral 7: mediaType option
// ─────────────────────────────────────────────────────────────────────────────

describe('mediaType option', () => {
  const getHeaders = (fetchMock: ReturnType<typeof vi.fn>): Headers => {
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit
    return new Headers(init.headers as HeadersInit)
  }

  it('ld+json: sets Content-Type and Accept to application/ld+json', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.post('/foo', {}, { mediaType: 'ld+json' })

    const h = getHeaders(fetchMock)
    expect(h.get('Content-Type')).toBe('application/ld+json')
    expect(h.get('Accept')).toBe('application/ld+json')
  })

  it('merge-patch+json: sets Content-Type to merge-patch and Accept to ld+json', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.patch('/foo', {}, { mediaType: 'merge-patch+json' })

    const h = getHeaders(fetchMock)
    expect(h.get('Content-Type')).toBe('application/merge-patch+json')
    expect(h.get('Accept')).toBe('application/ld+json')
  })

  it('json (explicit): sets both to application/json', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.post('/foo', {}, { mediaType: 'json' })

    const h = getHeaders(fetchMock)
    expect(h.get('Content-Type')).toBe('application/json')
    expect(h.get('Accept')).toBe('application/json')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Behavioral 8: realtime option
// ─────────────────────────────────────────────────────────────────────────────

describe('realtime subscription option', () => {
  it('invokes subscribeFromHeaders with the response headers, key, and handlers', async () => {
    const linkHeader =
      '<https://hub/.well-known/mercure>; rel="mercure", </topics/wf-1>; rel="topic"'
    mockFetchAlways(mockResponse({ body: { id: 1 }, headers: { Link: linkHeader } }))

    const onMessage = vi.fn()
    await apiClient.get<{ id: number }>('/foo', {
      realtime: { key: 'wf-1', onMessage },
    })

    expect(mockSubscribeFromHeaders).toHaveBeenCalledTimes(1)
    const call = mockSubscribeFromHeaders.mock.calls[0] as unknown as [
      Headers,
      string,
      { onMessage: typeof onMessage },
    ]
    expect(call[0].get('Link')).toBe(linkHeader)
    expect(call[1]).toBe('wf-1')
    expect(call[2]).toMatchObject({ onMessage })
  })

  it('does not invoke subscribeFromHeaders when the option is omitted', async () => {
    mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo')

    expect(mockSubscribeFromHeaders).not.toHaveBeenCalled()
  })

  it('swallows subscription errors and still resolves the fetch promise', async () => {
    mockFetchAlways(mockResponse({ body: { id: 1 } }))
    mockSubscribeFromHeaders.mockRejectedValueOnce(new Error('mercure exploded'))
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

    const result = await apiClient.get<{ id: number }>('/foo', {
      realtime: { key: 'k', onMessage: vi.fn() },
    })

    expect(result).toEqual({ id: 1 })
    expect(consoleSpy).toHaveBeenCalled()
    consoleSpy.mockRestore()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Public surface smoke
// ─────────────────────────────────────────────────────────────────────────────

describe('public surface', () => {
  it('get returns the parsed JSON body directly (not wrapped)', async () => {
    mockFetchAlways(mockResponse({ body: { name: 'Acme', id: 42 } }))

    const result = await apiClient.get<{ name: string; id: number }>('/foo')

    expect(result).toEqual({ name: 'Acme', id: 42 })
  })

  it('post serializes the body to JSON', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: { ok: true } }))

    await apiClient.post('/foo', { hello: 'world', n: 7 })

    const init = fetchMock.mock.calls[0]?.[1] as RequestInit
    expect(init.body).toBe(JSON.stringify({ hello: 'world', n: 7 }))
  })

  it('returns null on 204 No Content', async () => {
    mockFetchAlways(new Response(null, { status: 204 }))

    const result = await apiClient.get('/foo')

    expect(result).toBeNull()
  })

  it('returns null when the response is non-JSON', async () => {
    mockFetchAlways(
      new Response('plain text body', {
        status: 200,
        headers: { 'content-type': 'text/plain' },
      }),
    )

    const result = await apiClient.get('/foo')

    expect(result).toBeNull()
  })

  it('delete resolves to void', async () => {
    mockFetchAlways(new Response(null, { status: 204 }))

    const result = await apiClient.delete('/foo')

    expect(result).toBeUndefined()
  })

  it('forwards the HTTP method correctly', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.put('/foo', { x: 1 })

    const init = fetchMock.mock.calls[0]?.[1] as RequestInit
    expect(init.method).toBe('PUT')
  })

  it('serializes query params via ofetch', async () => {
    const fetchMock = mockFetchAlways(mockResponse({ body: {} }))

    await apiClient.get('/foo', { query: { page: 2, search: 'acme' } })

    const calledUrl = fetchMock.mock.calls[0]?.[0] as string
    expect(calledUrl).toContain('page=2')
    expect(calledUrl).toContain('search=acme')
  })
})
