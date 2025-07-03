/**
 * Unit tests for useAuth composable
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { useAuth } from '~/composables/useAuth'

// Mock oidc-client
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

vi.mock('oidc-client', () => ({
  UserManager: vi.fn(() => mockUserManager),
  User: vi.fn()
}))

// Mock Nuxt runtime config
mockNuxtImport('useRuntimeConfig', () => () => ({
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

describe('useAuth', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initialization', () => {
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

  describe('signIn', () => {
    it('should call userManager.signinRedirect', async () => {
      const auth = useAuth()
      
      await auth.signIn()
      
      expect(mockUserManager.signinRedirect).toHaveBeenCalled()
    })

    it('should handle signin errors', async () => {
      const auth = useAuth()
      const error = new Error('Sign in failed')
      mockUserManager.signinRedirect.mockRejectedValue(error)
      
      await expect(auth.signIn()).rejects.toThrow('Sign in failed')
    })
  })

  describe('signOut', () => {
    it('should call userManager.signoutRedirect', async () => {
      const auth = useAuth()
      
      await auth.signOut()
      
      expect(mockUserManager.signoutRedirect).toHaveBeenCalled()
    })

    it('should handle signout errors', async () => {
      const auth = useAuth()
      const error = new Error('Sign out failed')
      mockUserManager.signoutRedirect.mockRejectedValue(error)
      
      await expect(auth.signOut()).rejects.toThrow('Sign out failed')
    })
  })

  describe('getUser', () => {
    it('should return null on server side', async () => {
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

    it('should handle getUser errors', async () => {
      mockUserManager.getUser.mockRejectedValue(new Error('Get user failed'))
      
      const auth = useAuth()
      const result = await auth.getUser()
      
      expect(result).toBeNull()
    })
  })

  describe('getAccessToken', () => {
    it('should return null on server side', async () => {
      globalThis.process.server = true
      const auth = useAuth()
      
      const result = await auth.getAccessToken()
      
      expect(result).toBeNull()
      
      globalThis.process.server = false
    })

    it('should return access token from user', async () => {
      const mockUser = { access_token: 'test-token' }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getAccessToken()
      
      expect(result).toBe('test-token')
    })

    it('should return null when no user', async () => {
      mockUserManager.getUser.mockResolvedValue(null)
      
      const auth = useAuth()
      const result = await auth.getAccessToken()
      
      expect(result).toBeNull()
    })

    it('should return null when user has no access token', async () => {
      const mockUser = { profile: { preferred_username: 'test' } }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getAccessToken()
      
      expect(result).toBeNull()
    })
  })

  describe('getCurrentUsername', () => {
    it('should return "unknown" on server side', async () => {
      globalThis.process.server = true
      const auth = useAuth()
      
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('unknown')
      
      globalThis.process.server = false
    })

    it('should return preferred_username from user profile', async () => {
      const mockUser = { profile: { preferred_username: 'testuser' } }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('testuser')
    })

    it('should return sub when preferred_username not available', async () => {
      const mockUser = { profile: { sub: 'user-123' } }
      mockUserManager.getUser.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('user-123')
    })

    it('should return "unknown" when no user', async () => {
      mockUserManager.getUser.mockResolvedValue(null)
      
      const auth = useAuth()
      const result = await auth.getCurrentUsername()
      
      expect(result).toBe('unknown')
    })
  })

  describe('handleCallback', () => {
    it('should handle signin redirect callback', async () => {
      const mockUser = { access_token: 'callback-token' }
      mockUserManager.signinRedirectCallback.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.handleCallback()
      
      expect(mockUserManager.signinRedirectCallback).toHaveBeenCalled()
      expect(result).toBe(mockUser)
    })

    it('should handle callback errors', async () => {
      const error = new Error('Callback failed')
      mockUserManager.signinRedirectCallback.mockRejectedValue(error)
      
      const auth = useAuth()
      
      await expect(auth.handleCallback()).rejects.toThrow('Callback failed')
    })
  })

  describe('handleSilentCallback', () => {
    it('should handle silent signin callback', async () => {
      const mockUser = { access_token: 'silent-token' }
      mockUserManager.signinSilentCallback.mockResolvedValue(mockUser)
      
      const auth = useAuth()
      const result = await auth.handleSilentCallback()
      
      expect(mockUserManager.signinSilentCallback).toHaveBeenCalled()
      expect(result).toBe(mockUser)
    })

    it('should handle silent callback errors', async () => {
      const error = new Error('Silent callback failed')
      mockUserManager.signinSilentCallback.mockRejectedValue(error)
      
      const auth = useAuth()
      
      await expect(auth.handleSilentCallback()).rejects.toThrow('Silent callback failed')
    })
  })

  describe('isAuthenticated computed', () => {
    it('should return true when user exists and is not expired', async () => {
      mockUserManager.getUser.mockResolvedValue({ expired: false })
      const auth = useAuth()
      
      // Wait for the user to be loaded
      await new Promise(resolve => setTimeout(resolve, 0))
      
      expect(auth.isAuthenticated.value).toBe(true)
    })

    it('should return false when user is null', async () => {
      mockUserManager.getUser.mockResolvedValue(null)
      const auth = useAuth()
      
      await new Promise(resolve => setTimeout(resolve, 0))
      
      expect(auth.isAuthenticated.value).toBe(false)
    })

    it('should return false when user is expired', async () => {
      mockUserManager.getUser.mockResolvedValue({ expired: true })
      const auth = useAuth()
      
      await new Promise(resolve => setTimeout(resolve, 0))
      
      expect(auth.isAuthenticated.value).toBe(false)
    })
  })
})