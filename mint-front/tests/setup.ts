/**
 * Global test setup for all tests
 */

import { beforeAll, afterAll, beforeEach, afterEach } from 'vitest'

// Global test configuration
beforeAll(async () => {
  console.log('🧪 Starting test suite...')
})

afterAll(async () => {
  console.log('✅ Test suite completed')
})

beforeEach(async () => {
  // Reset any global state before each test
})

afterEach(async () => {
  // Cleanup after each test
})

// Global test utilities
export const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

export const waitFor = async (
  condition: () => boolean | Promise<boolean>,
  timeout = 5000,
  interval = 100
) => {
  const start = Date.now()
  while (Date.now() - start < timeout) {
    if (await condition()) {
      return true
    }
    await sleep(interval)
  }
  throw new Error(`Condition not met within ${timeout}ms`)
}