/**
 * Tests for the intermittent 401 bug on token endpoints.
 *
 * Proves that:
 * 1. Root cause: Pinia persistence strips User.expired getter
 * 2. Fix 1: isAuthenticated uses expires_at directly (survives JSON round-trip)
 * 3. Fix 2: initialize() always syncs user from UserManager after reload
 * 4. Fix 3: refreshToken() mutex deduplicates concurrent calls
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { User } from 'oidc-client-ts'

// ── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/composables/useEndpointResolver', () => ({
  useEndpointResolver: () => ({
    endpoints: {
      value: {
        baseUrl: 'http://localhost',
        apiUrl: 'http://localhost/api',
        keycloakRealm: 'chapsmind',
        keycloakClientId: 'chapsmind-front',
        keycloakUrl: 'http://localhost:8080',
      },
    },
  }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('jwt-decode', () => ({
  jwtDecode: () => ({
    realm_access: { roles: ['organization.read'] },
    organization_id: 'org-uuid-123',
    organization_name: 'Test Org',
  }),
}))

// ── Helpers ──────────────────────────────────────────────────────────────────

const NOW_SECONDS = Math.floor(Date.now() / 1000)

/** Build a real oidc-client-ts User with a working `expired` getter. */
function createOidcUser(expiresIn = 300): User {
  return new User({
    access_token: 'eyJ-valid-token',
    token_type: 'Bearer',
    expires_at: NOW_SECONDS + expiresIn,
    profile: {
      sub: 'user-uuid-123',
      iss: 'http://localhost:8080/realms/chapsmind',
      aud: 'chapsmind-front',
      exp: NOW_SECONDS + expiresIn,
      iat: NOW_SECONDS,
      preferred_username: 'admin',
    },
  })
}

/** Simulate what Pinia persistedstate does: JSON round-trip. */
function piniaRoundTrip(obj: unknown): unknown {
  return JSON.parse(JSON.stringify(obj))
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('Auth store – intermittent 401 bug', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ── 1. Root cause: User.expired getter lost after JSON round-trip ────────

  describe('1. Root cause: User.expired getter lost after JSON round-trip', () => {
    it('real User object: expired getter works correctly', () => {
      expect(createOidcUser(-60).expired).toBe(true)
      expect(createOidcUser(300).expired).toBe(false)
    })

    it('after JSON round-trip: expired getter is LOST (becomes undefined)', () => {
      const user = createOidcUser(-60)
      expect(user.expired).toBe(true)

      const deserialized = piniaRoundTrip(user) as Record<string, unknown>
      expect(deserialized.expired).toBeUndefined()
    })

    it('but expires_at IS preserved after JSON round-trip', () => {
      const user = createOidcUser(-60)
      const deserialized = piniaRoundTrip(user) as Record<string, unknown>

      // expires_at is a plain data property → survives serialization
      expect(deserialized.expires_at).toBe(user.expires_at)
    })
  })

  // ── 2. Fix 1: isAuthenticated uses expires_at instead of expired getter ──

  describe('2. Fix 1: isAuthenticated correctly detects expired tokens after Pinia restore', () => {
    it('returns false for deserialized user with expired token', async () => {
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      const expiredUser = createOidcUser(-60)
      store.user = piniaRoundTrip(expiredUser) as User

      // FIX: isAuthenticated now checks expires_at directly
      // Before fix: true (because !undefined === true)
      // After fix: false (because Date.now()/1000 > expires_at)
      expect(store.isAuthenticated).toBe(false)
    })

    it('returns true for deserialized user with valid token', async () => {
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      const validUser = createOidcUser(300)
      store.user = piniaRoundTrip(validUser) as User

      expect(store.isAuthenticated).toBe(true)
    })

    it('returns false when user is null', async () => {
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      store.user = null
      expect(store.isAuthenticated).toBe(false)
    })

    it('returns true for real (non-serialized) User with valid token', async () => {
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      store.user = createOidcUser(300)
      expect(store.isAuthenticated).toBe(true)
    })
  })

  // ── 3. Fix 2: initialize() always syncs user from UserManager ────────────

  describe('3. Fix 2: initialize() always syncs user from UserManager after reload', () => {
    it('overwrites stale Pinia user even when initialized is already true', async () => {
      const { UserManager } = await import('oidc-client-ts')
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      // Simulate Pinia restoring a stale session (initialized = true, expired user)
      const staleUser = createOidcUser(-60)
      store.user = piniaRoundTrip(staleUser) as User
      store.$patch({ initialized: true })

      // UserManager has the fresh canonical user
      const freshUser = createOidcUser(300)
      vi.spyOn(UserManager.prototype, 'getUser').mockResolvedValue(freshUser)

      await store.initialize()

      // initialize() must overwrite the stale Pinia user with the fresh one
      expect(store.user).toStrictEqual(freshUser)
      expect(store.isAuthenticated).toBe(true)
    })

    it('sets user to null when UserManager has no session', async () => {
      const { UserManager } = await import('oidc-client-ts')
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      // Pinia restored a user but the OIDC session is gone (e.g. cleared storage)
      store.user = piniaRoundTrip(createOidcUser(300)) as User
      store.$patch({ initialized: true })

      vi.spyOn(UserManager.prototype, 'getUser').mockResolvedValue(null)

      await store.initialize()

      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
    })
  })

  // ── 4. Fix 3: refreshToken() mutex deduplicates concurrent calls ──────────

  describe('4. Fix 3: refreshToken() mutex deduplicates concurrent calls', () => {
    it('signinSilent is called exactly once for concurrent refresh calls', async () => {
      const { UserManager } = await import('oidc-client-ts')
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      const freshUser = createOidcUser(300)

      // Spy on the prototype so the spy is in place before the store creates its UserManager
      const signinSilentSpy = vi
        .spyOn(UserManager.prototype, 'signinSilent')
        .mockResolvedValue(freshUser)

      // getUser must return an expired user so refreshToken() triggers signinSilent
      vi.spyOn(UserManager.prototype, 'getUser').mockResolvedValue(createOidcUser(-60))

      // Force store to create a new UserManager by initializing it
      await store.initialize()

      // 3 concurrent calls — only one signinSilent should fire
      const [r1, r2, r3] = await Promise.allSettled([
        store.refreshToken(),
        store.refreshToken(),
        store.refreshToken(),
      ])

      expect(r1.status).toBe('fulfilled')
      expect(r2.status).toBe('fulfilled')
      expect(r3.status).toBe('fulfilled')

      // The mutex ensures signinSilent is called exactly once, not 3 times
      expect(signinSilentSpy).toHaveBeenCalledTimes(1)
    })
  })

  // ── Full scenario: the fix prevents the 200 → 401 → 401 cycle ─────────

  describe('Full scenario: fix prevents alternating 200/401 pattern', () => {
    it('page reload with Pinia restore → correctly detects expired → redirects to login', async () => {
      const { useAuthStore } = await import('./auth')
      const store = useAuthStore()

      // ── Step 1: Normal session, token is valid → 200 OK ──
      const validUser = createOidcUser(300)
      store.user = validUser
      store.$patch({ initialized: true })

      expect(store.isAuthenticated).toBe(true)

      // ── Step 2: Page reload — Pinia restores stale state ──
      const expiredUser = createOidcUser(-60)
      const restored = piniaRoundTrip(expiredUser)
      store.user = restored as User

      // ── Step 3: FIX — isAuthenticated now correctly returns false ──
      expect(store.isAuthenticated).toBe(false)

      // The router guard sees isAuthenticated=false → redirects to /login
      // → no 401 sent to the backend, clean redirect instead

      // ── Step 4: After login/silent renew, fresh user set ──
      const freshUser = createOidcUser(300)
      store.user = freshUser
      expect(store.isAuthenticated).toBe(true)
      // Next API call succeeds → 200 ✅
    })
  })
})
