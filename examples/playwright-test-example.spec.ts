/**
 * Example Playwright Test Using QA Orchestrator Tools
 *
 * This demonstrates how to use the JWT generator and fixtures
 * provided by the QA orchestrator for testing internal APIs.
 *
 * Copy this as a template for your own tests:
 *   cp examples/playwright-test-example.spec.ts e2e/YOUR-TEST.spec.ts
 */

import { test, expect } from '@playwright/test'
import crypto from 'crypto'

// ─────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────

const API_BASE_URL = process.env.API_BASE_URL || 'http://localhost/api'
const INTERNAL_JWT_SECRET = process.env.INTERNAL_JWT_SECRET || 'local-dev-internal-jwt-secret-32chars'
const TEST_ORG_ID = process.env.TEST_ORG_ID || '12345678-1234-5678-9abc-def012345678'

// ─────────────────────────────────────────────────────────────────────────
// JWT Helper (Inline for this example - extract to helpers in real usage)
// ─────────────────────────────────────────────────────────────────────────

interface TokenPayload {
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

function createInternalToken(options: {
  userId?: string
  username?: string
  orgId?: string
  orgName?: string
  roles?: string[]
  email?: string
}): string {
  const now = Math.floor(Date.now() / 1000)
  const payload: TokenPayload = {
    sub: options.userId || 'user-123',
    username: options.username || 'testuser',
    email: options.email || null,
    org_id: options.orgId || TEST_ORG_ID,
    org_name: options.orgName || 'Test Org',
    roles: options.roles || ['admin'],
    iss: 'global-gateway',
    iat: now,
    exp: now + 3600,
  }

  const header = { alg: 'HS256', typ: 'JWT' }
  const headerB64 = base64UrlEncode(JSON.stringify(header))
  const payloadB64 = base64UrlEncode(JSON.stringify(payload))

  const signature = crypto
    .createHmac('sha256', INTERNAL_JWT_SECRET)
    .update(`${headerB64}.${payloadB64}`)
    .digest('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')

  return `${headerB64}.${payloadB64}.${signature}`
}

function base64UrlEncode(str: string): string {
  return Buffer.from(str)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

// ─────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────

test.describe('Internal API with JWT Authentication', () => {
  test.describe('Token Endpoints', () => {
    test('POST /tokens/lock returns 201 with valid JWT', async ({ request }) => {
      const token = createInternalToken({})
      const correlationId = `test-${Date.now()}`

      const response = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': `Internal ${token}`,
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: correlationId,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      expect(response.status()).toBe(201)
      const body = await response.json()
      expect(body).toHaveProperty('lock_id')
      expect(body.status).toBe('locked')
      expect(body.amount).toBe(5)
    })

    test('GET without Authorization header returns 401', async ({ request }) => {
      const response = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: { 'Content-Type': 'application/json' },
          data: { amount: 5, correlation_id: `test-${Date.now()}` },
        }
      )

      expect(response.status()).toBe(401)
    })

    test('Request with wrong org ID in JWT returns 403', async ({ request }) => {
      const token = createInternalToken({ orgId: 'wrong-org-id' })

      const response = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': `Internal ${token}`,
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: `test-${Date.now()}`,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      expect(response.status()).toBe(403)
    })

    test('Multiple requests with same correlation_id returns same lock_id', async ({
      request,
    }) => {
      const token = createInternalToken({})
      const correlationId = `idempotent-${Date.now()}`

      const response1 = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': `Internal ${token}`,
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: correlationId,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      const body1 = await response1.json()
      const lockId1 = body1.lock_id

      const response2 = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': `Internal ${token}`,
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: correlationId,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      const body2 = await response2.json()
      expect(body2.lock_id).toBe(lockId1)
    })
  })

  test.describe('Error Handling', () => {
    test('Invalid JWT format returns 401', async ({ request }) => {
      const response = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': 'Internal invalid.jwt.token',
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: `test-${Date.now()}`,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      expect(response.status()).toBe(401)
    })

    test('Expired JWT returns 401', async ({ request }) => {
      // Create a token that's already expired
      const now = Math.floor(Date.now() / 1000)
      const payload: TokenPayload = {
        sub: 'user-123',
        username: 'testuser',
        email: null,
        org_id: TEST_ORG_ID,
        org_name: 'Test Org',
        roles: ['admin'],
        iss: 'global-gateway',
        iat: now - 7200, // Created 2 hours ago
        exp: now - 3600, // Expired 1 hour ago
      }

      const header = { alg: 'HS256', typ: 'JWT' }
      const headerB64 = base64UrlEncode(JSON.stringify(header))
      const payloadB64 = base64UrlEncode(JSON.stringify(payload))

      const signature = crypto
        .createHmac('sha256', INTERNAL_JWT_SECRET)
        .update(`${headerB64}.${payloadB64}`)
        .digest('base64')
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '')

      const expiredToken = `${headerB64}.${payloadB64}.${signature}`

      const response = await request.post(
        `${API_BASE_URL}/internal/organizations/${TEST_ORG_ID}/tokens/lock`,
        {
          headers: {
            'Authorization': `Internal ${expiredToken}`,
            'Content-Type': 'application/json',
          },
          data: {
            amount: 5,
            correlation_id: `test-${Date.now()}`,
            module_name: 'SCREEN',
            reference_type: 'COMPANY_CARD',
            reference_id: 'test-ref',
          },
        }
      )

      expect(response.status()).toBe(401)
    })
  })
})
