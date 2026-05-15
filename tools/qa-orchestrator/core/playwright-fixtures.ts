/**
 * Reusable Playwright Fixtures for Internal API Testing
 *
 * Provides fixtures for JWT generation, API calls, and common test setup.
 *
 * Usage in your test file:
 *   import { test, expect } from '@playwright/test'
 *   import { apiTest } from '../../tools/qa-orchestrator/core/playwright-fixtures'
 *
 *   const test = apiTest.extend({
 *     // Add custom fixtures here if needed
 *   })
 *
 *   test('API test with auto JWT', async ({ internalRequest, orgId }) => {
 *     const response = await internalRequest.post('/tokens/lock', {
 *       data: { amount: 5 }
 *     })
 *     expect(response.status()).toBe(201)
 *   })
 */

import { test as baseTest } from '@playwright/test'
import crypto from 'crypto'

// Type definitions
interface InternalRequestOptions {
  endpoint: string
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'
  data?: Record<string, any>
  headers?: Record<string, string>
  userId?: string
  username?: string
  orgId?: string
  orgName?: string
  roles?: string[]
}

interface InternalTokenPayload {
  sub: string
  username: string
  email?: string | null
  org_id: string
  org_name: string
  roles: string[]
  iss: string
  iat: number
  exp: number
}

// Fixtures
const apiTest = baseTest.extend<{
  internalSecret: string
  testOrgId: string
  testUserId: string
  createJWT: (payload: Partial<InternalTokenPayload>) => string
  internalRequest: (options: InternalRequestOptions) => Promise<any>
}>({
  internalSecret: async ({}, use) => {
    const secret = process.env.INTERNAL_JWT_SECRET || 'local-dev-internal-jwt-secret-32chars'
    await use(secret)
  },

  testOrgId: async ({}, use) => {
    const orgId = process.env.TEST_ORG_ID || '12345678-1234-5678-9abc-def012345678'
    await use(orgId)
  },

  testUserId: async ({}, use) => {
    const userId = process.env.TEST_USER_ID || 'user-test-001'
    await use(userId)
  },

  createJWT: async ({ internalSecret }, use) => {
    const createToken = (payload: Partial<InternalTokenPayload>) => {
      const now = Math.floor(Date.now() / 1000)
      const fullPayload: InternalTokenPayload = {
        sub: payload.sub || 'user-123',
        username: payload.username || 'testuser',
        email: payload.email || null,
        org_id: payload.org_id || '12345678-1234-5678-9abc-def012345678',
        org_name: payload.org_name || 'Test Org',
        roles: payload.roles || ['admin'],
        iss: 'global-gateway',
        iat: now,
        exp: now + 3600,
      }

      const header = { alg: 'HS256', typ: 'JWT' }
      const headerB64 = base64UrlEncode(JSON.stringify(header))
      const payloadB64 = base64UrlEncode(JSON.stringify(fullPayload))

      const signature = crypto
        .createHmac('sha256', internalSecret)
        .update(`${headerB64}.${payloadB64}`)
        .digest('base64')
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '')

      return `${headerB64}.${payloadB64}.${signature}`
    }

    await use(createToken)
  },

  internalRequest: async ({ request, internalSecret, testOrgId, createJWT }, use) => {
    const apiBaseUrl = process.env.API_BASE_URL || 'http://localhost/api'

    const internalRequest = async (options: InternalRequestOptions) => {
      const {
        endpoint,
        method = 'POST',
        data,
        headers = {},
        userId = 'user-123',
        username = 'testuser',
        orgId = testOrgId,
        orgName = 'Test Org',
        roles = ['admin'],
      } = options

      const token = createJWT({
        sub: userId,
        username,
        org_id: orgId,
        org_name: orgName,
        roles,
      })

      const url = endpoint.startsWith('http') ? endpoint : `${apiBaseUrl}${endpoint}`

      return request[method.toLowerCase()](url, {
        headers: {
          'Authorization': `Internal ${token}`,
          'Content-Type': 'application/json',
          ...headers,
        },
        data: method !== 'GET' ? data : undefined,
      })
    }

    await use(internalRequest)
  },
})

// Helper function
function base64UrlEncode(str: string): string {
  return Buffer.from(str)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

export { apiTest }
