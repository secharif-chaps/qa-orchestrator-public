/**
 * Tests for src/composables/realtime/useMercure.ts (scope B).
 *
 * Covers:
 * - getMercureToken (cookie cache + apiClient fetch)
 * - unsubscribe / unsubscribeAll / resetMercure (lifecycle, ressource cleanup)
 *
 * Out of scope (intentionally not tested here):
 * - Retry/backoff timing logic (heavy fake-timer setup, low bug rate in prod)
 * - online/offline detection + connection lost/restored toasts (same)
 *
 * The cookie helper is exercised via document.cookie. EventSource is stubbed
 * (jsdom does not provide it) so subscribe()/unsubscribe() can be tested.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// ── Hoisted mocks ────────────────────────────────────────────────────────────
// Vitest hoists vi.mock calls; shared state must live inside vi.hoisted to
// avoid TDZ when the mocked module loads.

const hoisted = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockToastWarning: vi.fn(),
  mockToastInfo: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  apiClient: {
    get: (...args: unknown[]) => hoisted.mockApiGet(...args),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@vueuse/core', async () => {
  const { ref } = await import('vue')
  return { useOnline: () => ref(true) }
})

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    warning: hoisted.mockToastWarning,
    info: hoisted.mockToastInfo,
    error: vi.fn(),
    success: vi.fn(),
  }),
}))

import { useMercure } from '@/composables/realtime/useMercure'

// ── EventSource stub (jsdom does not provide one) ────────────────────────────

class MockEventSource {
  url: string
  withCredentials: boolean
  onopen: ((ev: Event) => void) | null = null
  onmessage: ((ev: MessageEvent) => void) | null = null
  onerror: ((ev: Event) => void) | null = null
  readyState = 0
  close = vi.fn(() => {
    this.readyState = 2
  })

  constructor(url: string, init?: EventSourceInit) {
    this.url = url
    this.withCredentials = init?.withCredentials ?? false
    MockEventSource.instances.push(this)
  }

  static instances: MockEventSource[] = []
  static reset() {
    this.instances = []
  }
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const clearCookies = () => {
  document.cookie.split(';').forEach((c) => {
    const name = c.split('=')[0]?.trim()
    if (name) document.cookie = `${name}=; path=/; max-age=0`
  })
}

// ── Test setup ───────────────────────────────────────────────────────────────

beforeEach(() => {
  setActivePinia(createPinia())
  hoisted.mockApiGet.mockReset()
  hoisted.mockToastWarning.mockClear()
  hoisted.mockToastInfo.mockClear()
  clearCookies()
  MockEventSource.reset()
  globalThis.EventSource = MockEventSource as unknown as typeof EventSource

  // Each test gets a fresh useMercure() to clear active subscriptions tracked
  // in the Pinia store. Module-level state (eventSources Map, retryCounts) is
  // wiped via unsubscribeAll() in afterEach as a safety net.
  //
  // CAVEAT — module-level state that persists across tests:
  // - `onlineWatcherInitialized` flag in useMercure: set to true on the first
  //   useMercure() call and never reset. The watcher captures the FIRST test's
  //   mercureStore + activeSubscriptions, which become stale after each
  //   setActivePinia(createPinia()). Harmless in scope B (we never trigger
  //   isOnline), but any future test of online/offline flow will need to
  //   reset this flag (e.g. via vi.resetModules()).
  // - Each test's mocked useOnline() returns a fresh ref(true), but only the
  //   first one is observed by the watcher (see point above).
})

afterEach(async () => {
  await useMercure().unsubscribeAll()
})

// ─────────────────────────────────────────────────────────────────────────────
// getMercureToken
// ─────────────────────────────────────────────────────────────────────────────

describe('getMercureToken', () => {
  it('returns the cookie value when present and refreshForce is false', async () => {
    document.cookie = 'mercureAuthorization=cached-jwt; path=/; max-age=3600'

    const token = await useMercure().getMercureToken()

    expect(token).toBe('cached-jwt')
    expect(hoisted.mockApiGet).not.toHaveBeenCalled()
  })

  it('fetches a new token via apiClient when no cookie is set', async () => {
    hoisted.mockApiGet.mockResolvedValueOnce({ token: 'fresh-jwt', expires_at: 9999 })

    const token = await useMercure().getMercureToken()

    expect(hoisted.mockApiGet).toHaveBeenCalledTimes(1)
    const [path, opts] = hoisted.mockApiGet.mock.calls[0] as [string, Record<string, unknown>]
    expect(path).toBe('/security/real-time/token')
    expect(opts).toMatchObject({ silent: true })
    expect(token).toBe('fresh-jwt')
  })

  it('forces a refresh when refreshForce=true even with a valid cookie', async () => {
    document.cookie = 'mercureAuthorization=stale-jwt; path=/; max-age=3600'
    hoisted.mockApiGet.mockResolvedValueOnce({ token: 'forced-jwt', expires_at: 9999 })

    const token = await useMercure().getMercureToken(true)

    expect(token).toBe('forced-jwt')
    expect(hoisted.mockApiGet).toHaveBeenCalledTimes(1)
  })

  it('returns null when the apiClient call fails', async () => {
    hoisted.mockApiGet.mockRejectedValueOnce(new Error('network down'))
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

    const token = await useMercure().getMercureToken()

    expect(token).toBeNull()
    expect(consoleSpy).toHaveBeenCalled()
    consoleSpy.mockRestore()
  })

  // NOTE: cookie persistence after a successful fetch is not asserted here
  // because jsdom rejects cookies with `secure: true` over http (its default
  // test URL). The setter logic in useCookie() is trivial and exercised at
  // runtime in production (https). Switching the whole test env to https just
  // for this single assertion would risk regressions elsewhere.
})

// ─────────────────────────────────────────────────────────────────────────────
// unsubscribe / unsubscribeAll / resetMercure
// ─────────────────────────────────────────────────────────────────────────────

describe('unsubscribe', () => {
  beforeEach(() => {
    // Make every subscribe path succeed: a non-null token short-circuits the
    // apiClient call AND lets EventSource construction proceed.
    document.cookie = 'mercureAuthorization=test-jwt; path=/; max-age=3600'
  })

  it('closes the EventSource when unsubscribing a known key', async () => {
    const m = useMercure()
    await m.subscribe(
      'https://hub/.well-known/mercure',
      ['/topics/foo'],
      { onUpdate: vi.fn() },
      'sub-1',
    )

    expect(MockEventSource.instances).toHaveLength(1)
    const es = MockEventSource.instances[0]!
    expect(es.close).not.toHaveBeenCalled()

    await m.unsubscribe('sub-1')

    expect(es.close).toHaveBeenCalledTimes(1)
  })

  it('removes the subscription from activeSubscriptions on unsubscribe', async () => {
    const m = useMercure()
    await m.subscribe('https://hub/.well-known/mercure', ['/t'], { onUpdate: vi.fn() }, 'sub-x')

    expect(m.activeSubscriptions.value).toHaveLength(1)

    await m.unsubscribe('sub-x')

    expect(m.activeSubscriptions.value).toHaveLength(0)
  })

  it('is a no-op when the key is unknown', async () => {
    const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    await useMercure().unsubscribe('never-subscribed')

    expect(MockEventSource.instances).toHaveLength(0)
    expect(consoleSpy).toHaveBeenCalled()
    consoleSpy.mockRestore()
  })
})

describe('unsubscribeAll', () => {
  beforeEach(() => {
    document.cookie = 'mercureAuthorization=test-jwt; path=/; max-age=3600'
  })

  it('closes every active EventSource and empties activeSubscriptions', async () => {
    const m = useMercure()
    await m.subscribe('https://hub/.well-known/mercure', ['/t1'], { onUpdate: vi.fn() }, 'a')
    await m.subscribe('https://hub/.well-known/mercure', ['/t2'], { onUpdate: vi.fn() }, 'b')
    await m.subscribe('https://hub/.well-known/mercure', ['/t3'], { onUpdate: vi.fn() }, 'c')

    expect(MockEventSource.instances).toHaveLength(3)

    await m.unsubscribeAll()

    for (const es of MockEventSource.instances) {
      expect(es.close).toHaveBeenCalledTimes(1)
    }
    expect(m.activeSubscriptions.value).toHaveLength(0)
  })
})

describe('resetMercure', () => {
  it('unsubscribes all and clears the mercureAuthorization cookie', async () => {
    document.cookie = 'mercureAuthorization=test-jwt; path=/; max-age=3600'
    const m = useMercure()
    await m.subscribe('https://hub/.well-known/mercure', ['/t'], { onUpdate: vi.fn() }, 'k')

    expect(MockEventSource.instances[0]?.close).not.toHaveBeenCalled()

    await m.resetMercure()

    expect(MockEventSource.instances[0]?.close).toHaveBeenCalledTimes(1)
    expect(m.activeSubscriptions.value).toHaveLength(0)
    // Cookie should be cleared (max-age=0). jsdom honors the deletion.
    expect(document.cookie).not.toContain('mercureAuthorization=test-jwt')
  })
})
