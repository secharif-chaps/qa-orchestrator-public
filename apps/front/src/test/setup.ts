/**
 * Vitest global setup file.
 * Provides common mocks and configurations for all tests.
 */

import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// Mock i18n globally for all tests
// This provides the $t function that's used in templates
const mockT = (key: string, fallback?: string | number | Record<string, unknown>) => {
  // If fallback is a string, return it; otherwise return the key
  if (typeof fallback === 'string') {
    return fallback
  }
  return key
}

// Configure Vue Test Utils with global mocks
config.global.mocks = {
  $t: mockT,
  $tc: mockT,
  $te: (key: string) => true,
  $d: (date: Date) => date.toISOString(),
  $n: (num: number) => num.toString(),
}

// Mock vue-i18n module
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: mockT,
    tc: mockT,
    te: (key: string) => true,
    d: (date: Date) => date.toISOString(),
    n: (num: number) => num.toString(),
    locale: { value: 'en-US' },
  }),
  createI18n: vi.fn(() => ({
    global: {
      t: mockT,
      locale: 'en-US',
    },
  })),
}))
