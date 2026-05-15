# Playwright E2E Testing Setup Guide

This guide explains how to use the QA orchestrator's Playwright setup to run E2E tests with proper JWT authentication.

## Quick Start

### 1. Configure Your Environment

```bash
cd e2e
```

**Update `package.json`**:
```json
{
  "type": "module",
  "scripts": {
    "test": "playwright test",
    "test:ui": "playwright test --ui",
    "test:debug": "playwright test --debug"
  }
}
```

**Create `.env.local`**:
```bash
# Get the actual secret from your running backend
INTERNAL_JWT_SECRET="local-dev-internal-jwt-secret-32chars"
PLAYWRIGHT_BASE_URL="http://localhost"
API_BASE_URL="http://localhost/api"
```

### 2. Update `playwright.config.ts`

```typescript
import { defineConfig } from '@playwright/test'
import { fileURLToPath } from 'url'
import path from 'path'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

export default defineConfig({
  testDir: path.resolve(__dirname),
  testMatch: '**/*.spec.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['html', { outputFolder: 'playwright-report' }], ['list']],
  use: {
    baseURL: 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
})
```

### 3. Create JWT Helper in Your Test

```typescript
// e2e/helpers/auth.ts
import crypto from 'crypto'

export function createInternalToken(
  userId: string,
  username: string,
  orgId: string,
  orgName: string,
  roles: string[],
  email?: string
): string {
  const secret = process.env.INTERNAL_JWT_SECRET || ''
  const now = Math.floor(Date.now() / 1000)
  const exp = now + 3600

  const payload = {
    sub: userId,
    username,
    email: email || null,
    org_id: orgId,
    org_name: orgName,
    roles,
    iss: 'global-gateway',
    iat: now,
    exp,
  }

  const header = { alg: 'HS256', typ: 'JWT' }
  const headerB64 = base64UrlEncode(JSON.stringify(header))
  const payloadB64 = base64UrlEncode(JSON.stringify(payload))

  const signature = crypto
    .createHmac('sha256', secret)
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
```

### 4. Use in Your Tests

```typescript
import { test, expect } from '@playwright/test'
import { createInternalToken } from './helpers/auth'

const API_BASE_URL = process.env.API_BASE_URL || 'http://localhost/api'
const TEST_ORG_ID = '12345678-1234-5678-9abc-def012345678'

test('Lock creation returns 201', async ({ request }) => {
  const token = createInternalToken(
    'user-123',
    'testuser',
    TEST_ORG_ID,
    'Test Org',
    ['admin']
  )

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
        reference_id: 'ref-123',
      },
    }
  )

  expect(response.status()).toBe(201)
})
```

## Running Tests

```bash
# Run all tests
npx playwright test

# Run specific test file
npx playwright test TAR-1569.spec.ts

# Run with UI
npx playwright test --ui

# Run in debug mode
npx playwright test --debug

# Show report
npx playwright show-report
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `INTERNAL_JWT_SECRET` | (required) | Backend JWT signing secret |
| `PLAYWRIGHT_BASE_URL` | `http://localhost` | Base URL for tests |
| `API_BASE_URL` | `http://localhost/api` | API endpoint base URL |
| `PLAYWRIGHT_TIMEOUT` | `30000` | Test timeout in ms |
| `CI` | (undefined) | Set in CI environments for proper retries |

## Key Points

### ✅ ESM Module Support
- `package.json` must have `"type": "module"`
- Config must import `__dirname` using `fileURLToPath`

### ✅ JWT Authentication
- Format: `Internal <token>` (not `Bearer`)
- Algorithm: HS256
- Issuer: `global-gateway`
- Secret: Must match backend's `INTERNAL_JWT_SECRET`

### ✅ Token Payload
```json
{
  "sub": "user-id",
  "username": "username",
  "email": "user@example.com",
  "org_id": "org-uuid",
  "org_name": "org-name",
  "roles": ["role1", "role2"],
  "iss": "global-gateway",
  "iat": 1234567890,
  "exp": 1234571490
}
```

## Common Issues & Fixes

### Issue: "Playwright Test did not expect test() to be called here"
**Fix**: Ensure `package.json` has `"type": "module"` and config imports `__dirname` for ESM

### Issue: "INTERNAL_JWT_SECRET not configured"
**Fix**: Set env var from `.env.local` or backend's actual secret

### Issue: 401 Unauthorized on API calls
**Fix**: Verify JWT secret matches backend, check token expiry, ensure `Internal` prefix in header

### Issue: Tests timeout on slow network
**Fix**: Increase `PLAYWRIGHT_TIMEOUT` or configure in test: `test.setTimeout(60000)`

## Best Practices

1. **Reuse JWT helpers** - Create shared auth utilities in `e2e/helpers/`
2. **Fixture-based setup** - Use Playwright fixtures for common test setup
3. **Separate concerns** - Keep JWT generation away from test logic
4. **Document payloads** - Add comments showing expected token structure
5. **Test auth errors** - Always test missing/invalid JWT scenarios

## Testing Authentication Errors

```typescript
test('Missing JWT returns 401', async ({ request }) => {
  const response = await request.post(`${API_BASE_URL}/tokens/lock`, {
    headers: { 'Content-Type': 'application/json' },
    data: { /* payload */ },
  })
  expect(response.status()).toBe(401)
})

test('Invalid org returns 403', async ({ request }) => {
  const token = createInternalToken('user', 'user', 'wrong-org', 'Org', [])
  const response = await request.post(`${API_BASE_URL}/tokens/lock`, {
    headers: { 'Authorization': `Internal ${token}` },
    data: { /* payload */ },
  })
  expect(response.status()).toBe(403)
})
```

## See Also

- [QA Orchestrator README](../README.md)
- [Backend JWT Documentation](../../../apps/global-service/app/core/internal_jwt.py)
- [Playwright Documentation](https://playwright.dev)
