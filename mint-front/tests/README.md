# Testing Guide

This directory contains comprehensive test suites for the Mint frontend application using Vitest and Nuxt Test Utils.

## Test Structure

```
tests/
├── e2e/                    # End-to-end tests
│   ├── auth.test.ts       # Authentication flow tests
│   ├── companies.test.ts  # Company management tests
│   └── search.test.ts     # Search functionality tests
├── unit/                   # Unit tests
│   └── composables/       # Composable tests
│       ├── useAuth.test.ts
│       └── useApiService.test.ts
├── mocks/                  # Mock implementations
│   └── backend.ts         # Backend API mocks using MSW
├── fixtures/              # Test data and fixtures
├── setup.ts               # Global test setup
├── setup.e2e.ts          # E2E test setup with backend mocks
└── setup.unit.ts         # Unit test setup
```

## Running Tests

### All Tests
```bash
npm run test
```

### E2E Tests Only
```bash
npm run test:e2e
```

### Unit Tests Only
```bash
npm run test:unit
```

### Interactive Mode
```bash
npm run test:ui
```

### Watch Mode
```bash
npm run test:watch
```

### Coverage Report
```bash
npm run test:coverage
```

## Test Categories

### 🔐 Authentication Tests (`auth.test.ts`)
- Login flow with valid/invalid credentials
- Authentication state management
- Protected route access
- Logout functionality
- Token handling and expiration
- Error handling for network issues

### 🏢 Company Management Tests (`companies.test.ts`)
- Company list display
- Company creation workflow
- Company details view
- Task management
- Authorization (user can only access own companies)
- Error handling

### 🔍 Search Tests (`search.test.ts`)
- Search form validation
- Input sanitization
- Form interactions
- Company creation via search
- Error handling
- Loading states

### 🧩 Unit Tests
- **useAuth**: Authentication composable testing
- **useApiService**: API service functionality
- Component unit tests (can be added)

## Mock Backend

The tests use MSW (Mock Service Worker) to simulate backend API responses. The mock handlers are defined in `tests/mocks/backend.ts` and provide:

- Authentication endpoints (`/api/auth/*`)
- Company CRUD operations (`/api/companies/*`)
- Task management (`/api/tasks/*`)
- Admin endpoints (`/api/admin/*`)
- Security endpoints (`/api/security/*`)

## Test Data

Mock data is defined in `tests/setup.e2e.ts`:

- `mockUser`: Standard test user
- `mockAdminUser`: Admin user for testing admin features
- `mockCompany`: Sample company data
- `mockAccessToken`: JWT token for authentication

## Configuration

Three separate Vitest configurations:

1. **vitest.config.ts**: Main configuration for all tests
2. **vitest.config.e2e.ts**: E2E specific configuration with Nuxt environment
3. **vitest.config.unit.ts**: Unit tests with happy-dom environment

## Security Testing

The tests validate security features implemented in Phase 1 & 2:

- ✅ Authentication bypass prevention
- ✅ Authorization checks (company ownership)
- ✅ Input validation and sanitization
- ✅ XSS protection
- ✅ SQL injection prevention
- ✅ Rate limiting behavior
- ✅ Error handling without information disclosure

## Writing New Tests

### E2E Test Template
```typescript
import { describe, it, expect } from 'vitest'
import { setup, createPage } from '@nuxt/test-utils/e2e'

describe('Feature E2E', async () => {
  await setup({
    nuxtConfig: {
      ssr: false,
      runtimeConfig: {
        public: {
          backendApi: 'http://localhost:8001'
        }
      }
    }
  })

  it('should test feature', async () => {
    const page = await createPage('/feature')
    // Test implementation
    await page.close()
  })
})
```

### Unit Test Template
```typescript
import { describe, it, expect, vi } from 'vitest'

describe('Feature Unit', () => {
  it('should test functionality', () => {
    // Test implementation
  })
})
```

## Test Data Attributes

Components should include `data-cy` attributes for reliable test targeting:

```vue
<template>
  <form data-cy="login-form">
    <input data-cy="username-input" />
    <input data-cy="password-input" />
    <button data-cy="login-button">Login</button>
  </form>
</template>
```

## Best Practices

1. **Use data-cy attributes** for test targeting instead of class names or IDs
2. **Mock external dependencies** to ensure tests are isolated
3. **Test error conditions** as well as happy paths
4. **Keep tests fast** by mocking network requests
5. **Use descriptive test names** that explain what is being tested
6. **Clean up resources** (close pages, reset mocks) after each test
7. **Test security features** to ensure vulnerabilities are caught

## Continuous Integration

Tests are designed to run in CI environments:

- No external dependencies (uses mocked backend)
- Deterministic and reliable
- Fast execution
- Comprehensive coverage of critical user flows

## Debugging Tests

### Enable Debug Mode
```bash
DEBUG=true npm run test:e2e
```

### Screenshot on Failure
E2E tests can be configured to take screenshots on failure for debugging.

### Inspect Mode
```bash
npm run test:ui
```

Use the Vitest UI for interactive debugging and test exploration.