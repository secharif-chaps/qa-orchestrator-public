/**
 * UI-compatible tests for useAuth composable
 * Simplified version that works in jsdom environment
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock oidc-client for jsdom environment
const mockUserManager = {
  signinRedirect: vi.fn(),
  signoutRedirect: vi.fn(),
  signinRedirectCallback: vi.fn(),
  signinSilentCallback: vi.fn(),
  getUser: vi.fn(),
  events: {
    addUserLoaded: vi.fn(),
    addUserUnloaded: vi.fn(),
    addAccessTokenExpiring: vi.fn(),
    addAccessTokenExpired: vi.fn(),
    addSilentRenewError: vi.fn()
  }
}

// Mock the oidc-client module
vi.mock('oidc-client', () => ({
  UserManager: vi.fn(() => mockUserManager),
  User: vi.fn()
}))

describe('useAuth (UI)', () => {
  let useAuth: any

  beforeEach(async () => {
    vi.clearAllMocks()
    
    // Set up global mocks for useAuth test
    globalThis.useRuntimeConfig = vi.fn(() => ({
      public: {
        authServerUrl: 'http://10.0.1.2:8080/realms/mint-dev',
        clientId: 'mint-front'
      }
    }))

    // Mock window and process for SSR handling
    Object.defineProperty(globalThis, 'window', {
      value: {
        location: {
          origin: 'http://localhost:3000'
        }
      },
      writable: true
    })

    Object.defineProperty(globalThis, 'process', {
      value: {
        server: false
      },
      writable: true
    })

    // Import the useAuth composable
    const module = await import('~/composables/useAuth')
    useAuth = module.useAuth
  })

  describe('Basic functionality', () => {
    it('should initialize useAuth composable correctly', () => {
      const auth = useAuth()
      
      // Check that all expected methods are available
      expect(typeof auth.signIn).toBe('function')
      expect(typeof auth.signOut).toBe('function')
      expect(typeof auth.getUser).toBe('function')
      expect(typeof auth.getAccessToken).toBe('function')
      expect(typeof auth.getCurrentUsername).toBe('function')
      expect(typeof auth.handleCallback).toBe('function')
      expect(typeof auth.handleSilentCallback).toBe('function')
      expect(auth.isAuthenticated).toBeDefined()
    })

    it('should set up event handlers', () => {
      useAuth()
      
      expect(mockUserManager.events.addUserLoaded).toHaveBeenCalled()
      expect(mockUserManager.events.addUserUnloaded).toHaveBeenCalled()
      expect(mockUserManager.events.addAccessTokenExpiring).toHaveBeenCalled()
      expect(mockUserManager.events.addAccessTokenExpired).toHaveBeenCalled()
      expect(mockUserManager.events.addSilentRenewError).toHaveBeenCalled()
    })
  })

  describe('Authentication methods', () => {
    it('should call userManager.signinRedirect for signIn', async () => {
      const auth = useAuth()
      
      await auth.signIn()
      
      expect(mockUserManager.signinRedirect).toHaveBeenCalled()
    })

    it('should call userManager.signoutRedirect for signOut', async () => {
      const auth = useAuth()
      
      await auth.signOut()
      
      expect(mockUserManager.signoutRedirect).toHaveBeenCalled()
    })

    it('should return null on server side for getUser', async () => {
      globalThis.process.server = true
      const auth = useAuth()
      
      const result = await auth.getUser()
      
      expect(result).toBeNull()
      expect(mockUserManager.getUser).not.toHaveBeenCalled()
      
      globalThis.process.server = false
    })

    it('should call userManager.getUser on client side', async () => {
      const mockUser = { access_token: 'test-token', profile: { preferred_username: 'test' } }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getUser()
      
      expect(mockUserManager.getUser).toHaveBeenCalled()
      expect(result).toBe(mockUser)
    })

    it('should return access token from user', async () => {
      const mockUser = { access_token: 'test-token' }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getAccessToken()
      
      expect(result).toBe('test-token')
    })

    it('should return null when no user for getAccessToken', async () => {
      mockUserManager.getUser.mockResolvedValue(null)
      
      const auth = useAuth()
      const result = await auth.getAccessToken()
      
      expect(result).toBeNull()
    })

    it('should return preferred_username from user profile', async () => {
      const mockUser = { profile: { preferred_username: 'testuser' } }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('testuser')
    })

    it('should return "unknown" when no user for getCurrentUsername', async () => {
      mockUserManager.getUser.mockResolvedValue(null)
      
      const auth = useAuth()
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('unknown')
    })
  })

  describe('Callback handling', () => {
    it('should handle signin redirect callback', async () => {
      const mockUser = { access_token: 'callback-token' }
      mockUserManager.signinRedirectCallback.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.handleCallback()
      
      expect(mockUserManager.signinRedirectCallback).toHaveBeenCalled()
      expect(result).toBe(mockUser)
    })

    it('should handle silent signin callback', async () => {
      const mockUser = { access_token: 'silent-token' }
      mockUserManager.signinSilentCallback.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.handleSilentCallback()
      
      expect(mockUserManager.signinSilentCallback).toHaveBeenCalled()
      expect(result).toBe(mockUser)
    })
  })
})