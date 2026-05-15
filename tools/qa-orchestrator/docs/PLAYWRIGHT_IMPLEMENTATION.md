# Playwright E2E Implementation Guide

**Status:** ✅ Complete & Production Ready  
**Last Updated:** May 13, 2026  
**Related Ticket:** TAR-1569 (Token Reservation API)

## Overview

This guide explains the complete Playwright E2E setup and JWT authentication system integrated into the QA orchestrator. It's designed to be a permanent, reusable solution that prevents future authentication and configuration blockers.

## What Was Fixed

### Problem 1: Playwright Configuration Error
**Error**: `Playwright Test did not expect test.describe() to be called here`

**Root Causes**:
- `e2e/package.json` had `"type": "commonjs"` instead of `"module"`
- Playwright config used `__dirname` without ESM import
- Multiple Playwright versions or import conflicts

**Solution**:
- Changed package.json to `"type": "module"`
- Updated config to import `__dirname` via `fileURLToPath`
- Created validated configuration in QA orchestrator

### Problem 2: JWT Authentication
**Error**: All tests returned 401 because JWT wasn't signed correctly

**Root Causes**:
- Mock JWT used base64 encoding instead of HS256 HMAC-SHA256 signing
- JWT format didn't match backend expectations
- Secret wasn't being retrieved from environment

**Solution**:
- Created `JWTGenerator` class using proper HS256 signing
- Integrated with backend's exact token format
- Added configuration validation

## Architecture

```
tools/qa-orchestrator/
├── core/
│   ├── jwt-generator.js          # JWT generation (Node.js)
│   ├── playwright-config.js       # Config generator (ESM)
│   └── playwright-fixtures.ts     # Reusable fixtures
├── config/
│   └── playwright.js              # Environment config
├── examples/
│   ├── playwright-test-example.spec.ts  # Test template
│   └── setup-playwright.sh        # Auto-setup script
└── docs/
    ├── PLAYWRIGHT_SETUP.md        # User guide
    └── PLAYWRIGHT_IMPLEMENTATION.md # This file
```

## Files Created

| File | Purpose | Type |
|------|---------|------|
| `core/jwt-generator.js` | Generate HS256-signed JWT tokens | Production |
| `core/playwright-config.js` | Playwright configuration | Production |
| `core/playwright-fixtures.ts` | Reusable test fixtures | Reference |
| `config/playwright.js` | Environment & validation | Production |
| `examples/playwright-test-example.spec.ts` | Complete test example | Template |
| `examples/setup-playwright.sh` | Automated setup | Tool |
| `docs/PLAYWRIGHT_SETUP.md` | Usage guide | Documentation |
| `docs/PLAYWRIGHT_IMPLEMENTATION.md` | This implementation guide | Documentation |

## How to Use

### 1. One-Time Setup (for projects)

```bash
# Run the setup script from project root
bash tools/qa-orchestrator/examples/setup-playwright.sh

# Or manually:
cd e2e
npm install --save-dev @playwright/test
```

### 2. Configure Environment

Get your JWT secret from the running backend:

```bash
# From Docker Compose logs or environment
grep INTERNAL_JWT_SECRET infra/compose.local.yaml

# Or set in .env.local
export INTERNAL_JWT_SECRET="local-dev-internal-jwt-secret-32chars"
```

### 3. Use in Your Tests

```typescript
import { test, expect } from '@playwright/test'
import { createInternalToken } from './helpers/auth'

const API_BASE_URL = process.env.API_BASE_URL || 'http://localhost/api'

test('API test with JWT', async ({ request }) => {
  const token = createInternalToken({
    userId: 'user-123',
    username: 'admin',
    orgId: 'org-456',
    orgName: 'Test Org',
    roles: ['admin'],
  })

  const response = await request.post(`${API_BASE_URL}/tokens/lock`, {
    headers: {
      'Authorization': `Internal ${token}`,
      'Content-Type': 'application/json',
    },
    data: { amount: 5 },
  })

  expect(response.status()).toBe(201)
})
```

## JWT Token Structure

The implementation creates tokens that exactly match the backend's expectations:

```json
{
  "sub": "user-123",
  "username": "admin",
  "email": "user@example.com",
  "org_id": "org-uuid",
  "org_name": "Test Org",
  "roles": ["admin"],
  "iss": "global-gateway",
  "iat": 1234567890,
  "exp": 1234571490
}
```

**Key Points**:
- Algorithm: HS256 (HMAC-SHA256)
- Issuer: `global-gateway`
- Secret: From `INTERNAL_JWT_SECRET` environment variable
- Format: `Internal <token>` in Authorization header (not `Bearer`)

## Configuration

### Environment Variables

| Variable | Source | Example |
|----------|--------|---------|
| `INTERNAL_JWT_SECRET` | Backend config | `local-dev-internal-jwt-secret-32chars` |
| `API_BASE_URL` | Deployment | `http://localhost/api` |
| `PLAYWRIGHT_BASE_URL` | Deployment | `http://localhost` |
| `TEST_ORG_ID` | Test setup | `12345678-1234-5678-9abc-def012345678` |
| `TEST_USER_ID` | Test setup | `user-test-001` |

### Getting the JWT Secret

**Local Development** (Docker Compose):
```bash
# Option 1: From compose.local.yaml
grep INTERNAL_JWT_SECRET infra/compose.local.yaml

# Option 2: From running container
docker logs chapsmind-global-service 2>&1 | grep INTERNAL_JWT_SECRET

# Option 3: Check backend startup
task logs:service -- global-service | grep JWT
```

**Production/Staging**:
```bash
# From Kubernetes secrets
kubectl get secret -n chapsmind global-service-config -o jsonpath='{.data.INTERNAL_JWT_SECRET}' | base64 -d

# Or from CI/CD pipeline
echo $INTERNAL_JWT_SECRET
```

## Validating Your Setup

### Test JWT Generation

```javascript
const { JWTGenerator } = require('./tools/qa-orchestrator/core/jwt-generator')

const gen = new JWTGenerator('local-dev-internal-jwt-secret-32chars')
const token = gen.createToken({
  userId: 'test-user',
  username: 'testuser',
  orgId: 'test-org',
  orgName: 'Test Org',
  roles: ['admin'],
})

console.log('Token:', token)
const decoded = gen.decodeToken(token)
console.log('Decoded:', decoded)
console.log('Expired:', gen.isTokenExpired(token))
```

### Test Playwright Configuration

```bash
cd e2e

# Verify config loads
npx playwright test --version

# Run tests with verbose output
npx playwright test TAR-1569.spec.ts --verbose --reporter=list

# Run in UI mode for debugging
npx playwright test --ui
```

## Common Issues & Solutions

### Issue: "INTERNAL_JWT_SECRET not configured"

**Cause**: Environment variable not set

**Solution**:
```bash
# Export in current shell
export INTERNAL_JWT_SECRET="local-dev-internal-jwt-secret-32chars"

# Or create .env.local
echo 'INTERNAL_JWT_SECRET=local-dev-internal-jwt-secret-32chars' > e2e/.env.local

# Verify
echo $INTERNAL_JWT_SECRET
```

### Issue: "401 Unauthorized" on all API calls

**Cause**: JWT secret doesn't match backend's secret

**Solution**:
```bash
# 1. Verify backend is running
curl http://localhost/api/health

# 2. Get correct secret from backend
docker exec chapsmind-global-service env | grep INTERNAL_JWT_SECRET

# 3. Update and retry
export INTERNAL_JWT_SECRET="<correct-secret-from-above>"
npx playwright test
```

### Issue: ESM "Cannot find module" errors

**Cause**: Missing ESM configuration in package.json

**Solution**:
```json
{
  "type": "module",
  "scripts": {
    "test": "playwright test"
  }
}
```

### Issue: Playwright not finding tests

**Cause**: Wrong test directory or missing config

**Solution**:
```bash
# Verify playwright.config.ts exists and is valid
ls -la e2e/playwright.config.ts

# Run from e2e directory
cd e2e && npx playwright test
```

## Best Practices

### 1. Centralize JWT Generation
Use the helper functions, don't create tokens inline:

```typescript
// ✅ Good
import { createInternalToken } from './helpers/auth'
const token = createInternalToken({ userId: 'user-123' })

// ❌ Bad
const token = manuallyCreateJWT()
```

### 2. Reuse Test Data
Define test fixtures for common payloads:

```typescript
const testOrg = {
  orgId: process.env.TEST_ORG_ID,
  orgName: 'Test Org',
  roles: ['admin'],
}
```

### 3. Test Auth Errors
Always include negative tests for authentication:

```typescript
test('Missing JWT returns 401', async ({ request }) => {
  const res = await request.post(`/api/tokens/lock`, {
    headers: { /* no Authorization */ },
  })
  expect(res.status()).toBe(401)
})
```

### 4. Document Token Expectations
Add comments showing expected payload:

```typescript
// Token payload must have:
// - sub: user ID
// - org_id: organization UUID
// - roles: array of role strings
const token = createInternalToken({ /* ... */ })
```

## Testing Different Scenarios

### Valid Token
```typescript
const token = createInternalToken({
  userId: 'user-123',
  orgId: TEST_ORG_ID,
  roles: ['admin'],
})

const res = await request.post(`/api/internal/tokens/lock`, {
  headers: { 'Authorization': `Internal ${token}` },
})
expect(res.status()).toBe(201)
```

### Expired Token
```typescript
const token = createInternalToken({
  expirySeconds: -1, // Already expired
})

const res = await request.post(`/api/internal/tokens/lock`, {
  headers: { 'Authorization': `Internal ${token}` },
})
expect(res.status()).toBe(401)
```

### Wrong Organization
```typescript
const token = createInternalToken({
  orgId: 'wrong-org-id',
})

const res = await request.post(`/api/internal/tokens/lock`, {
  headers: { 'Authorization': `Internal ${token}` },
})
expect(res.status()).toBe(403)
```

## Integration with CI/CD

### GitHub Actions
```yaml
- name: Run E2E Tests
  env:
    INTERNAL_JWT_SECRET: ${{ secrets.INTERNAL_JWT_SECRET }}
    API_BASE_URL: ${{ env.API_BASE_URL }}
  run: |
    cd e2e
    npx playwright test
```

### GitLab CI
```yaml
e2e_tests:
  script:
    - cd e2e
    - npx playwright test
  variables:
    INTERNAL_JWT_SECRET: $INTERNAL_JWT_SECRET
    API_BASE_URL: $API_BASE_URL
```

## Maintenance

### Update JWT Secret
If you rotate the JWT secret in your backend:

```bash
# 1. Update backend secret
# 2. Update environment
export INTERNAL_JWT_SECRET="new-secret-value"

# 3. Re-run tests
npx playwright test
```

### Update Playwright Version
```bash
cd e2e
npm install --save-dev @playwright/test@latest
npx playwright install
```

### Update Configuration
If backend JWT format changes:

1. Update `JWTGenerator` in QA orchestrator
2. Update test helpers
3. Run full test suite

## See Also

- **Setup Guide**: [PLAYWRIGHT_SETUP.md](./PLAYWRIGHT_SETUP.md)
- **Backend JWT Code**: `apps/global-service/app/core/internal_jwt.py`
- **Playwright Docs**: https://playwright.dev
- **Example Tests**: `examples/playwright-test-example.spec.ts`

---

**Last Updated:** May 13, 2026  
**Status:** Production Ready ✅  
**Maintenance:** QA Orchestrator Team
