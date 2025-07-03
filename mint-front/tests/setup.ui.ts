/**
 * Simplified test setup for Vitest UI 
 * Uses jsdom instead of nuxt environment for better UI compatibility
 */

import { beforeEach, afterEach, vi } from 'vitest'
import { config } from '@vue/test-utils'

// Mock Vue reactivity functions for jsdom environment
globalThis.ref = vi.fn((value) => ({ value }))
globalThis.computed = vi.fn((fn) => ({ value: fn() }))
globalThis.reactive = vi.fn((value) => value)
globalThis.readonly = vi.fn((value) => value)
globalThis.watch = vi.fn()
globalThis.watchEffect = vi.fn()
globalThis.nextTick = vi.fn(() => Promise.resolve())

// Mock Nuxt runtime functions globally
globalThis.useRuntimeConfig = vi.fn(() => ({
  public: {
    backendApi: 'http://localhost:8000',
    authServerUrl: 'http://10.0.1.2:8080/realms/mint-dev',
    clientId: 'mint-front'
  }
}))

globalThis.navigateTo = vi.fn()

// Mock @nuxt/test-utils/runtime functions
globalThis.mockNuxtImport = vi.fn((name, factory) => {
  if (typeof factory === 'function') {
    globalThis[name] = factory()
  }
  return factory
})

// Enhanced vi.mock for jsdom environment - just let it work normally
// The mocking will be handled by individual test files

// Mock useAuth composable - create a default implementation that can be overridden by individual tests
const defaultUseAuth = {
  getAccessToken: vi.fn().mockResolvedValue(null),
  signIn: vi.fn(),
  signOut: vi.fn(),
  getUser: vi.fn(),
  getCurrentUsername: vi.fn(),
  handleCallback: vi.fn(),
  handleSilentCallback: vi.fn(),
  isAuthenticated: { value: false }
}

globalThis.useAuth = vi.fn(() => defaultUseAuth)

// Mock Nuxt auto-imports that might be used in tests
globalThis.useState = vi.fn()
globalThis.useCookie = vi.fn()
globalThis.useRoute = vi.fn(() => ({
  path: '/',
  params: {},
  query: {},
  meta: {}
}))
globalThis.useRouter = vi.fn(() => ({
  push: vi.fn(),
  replace: vi.fn(),
  go: vi.fn(),
  back: vi.fn(),
  forward: vi.fn()
}))

// Set up Vue Test Utils global config
config.global.mocks = {
  $router: {
    push: vi.fn(),
    replace: vi.fn(),
    go: vi.fn(),
    back: vi.fn(),
    forward: vi.fn()
  },
  $route: {
    path: '/',
    params: {},
    query: {},
    meta: {}
  },
  $t: (key: string) => key,
  $i18n: {
    locale: 'en'
  }
}

beforeEach(() => {
  // Reset all mocks before each test
  vi.clearAllMocks()
  
  // Reset global mocks to defaults
  globalThis.useRuntimeConfig = vi.fn(() => ({
    public: {
      backendApi: 'http://localhost:8000',
      authServerUrl: 'http://10.0.1.2:8080/realms/mint-dev',
      clientId: 'mint-front'
    }
  }))
  
  globalThis.navigateTo = vi.fn()
  
  // Reset useAuth mock
  globalThis.useAuth = vi.fn(() => ({
    getAccessToken: vi.fn().mockResolvedValue(null),
    signIn: vi.fn(),
    signOut: vi.fn(),
    getUser: vi.fn(),
    getCurrentUsername: vi.fn(),
    handleCallback: vi.fn(),
    handleSilentCallback: vi.fn(),
    isAuthenticated: { value: false }
  }))
})

afterEach(() => {
  // Clean up after each test
  vi.restoreAllMocks()
})