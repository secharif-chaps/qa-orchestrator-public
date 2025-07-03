/**
 * Unit test setup - lightweight, no backend dependencies
 */

import { beforeEach, afterEach, vi } from 'vitest'
import { config } from '@vue/test-utils'

// Configure Vue Test Utils globally
config.global.mocks = {
  $t: (key: string) => key, // Mock i18n
  $route: {
    params: {},
    query: {},
    path: '/'
  },
  $router: {
    push: vi.fn(),
    replace: vi.fn(),
    go: vi.fn(),
    back: vi.fn(),
    forward: vi.fn()
  }
}

beforeEach(() => {
  // Reset all mocks before each test
  vi.clearAllMocks()
})

afterEach(() => {
  // Cleanup after each test
  vi.resetAllMocks()
})

// Unit test utilities
export const createMockComponent = (props = {}) => ({
  template: '<div data-testid="mock-component">Mock Component</div>',
  props: Object.keys(props)
})

export const mockComposable = (name: string, returnValue: any) => {
  vi.mock(name, () => ({
    default: () => returnValue,
    [name]: () => returnValue
  }))
}