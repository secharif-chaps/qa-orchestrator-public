/**
 * Playwright Configuration for E2E Tests
 *
 * Provides environment-specific configuration for Playwright tests
 * including API endpoints, JWT secrets, and test data.
 */

require('dotenv').config()

module.exports = {
  // API Configuration
  api: {
    baseUrl: process.env.API_BASE_URL || 'http://localhost/api',
    timeout: parseInt(process.env.PLAYWRIGHT_TIMEOUT || '30000'),
  },

  // JWT Configuration
  jwt: {
    secret: process.env.INTERNAL_JWT_SECRET,
    algorithm: 'HS256',
    issuer: 'global-gateway',
    expirySeconds: 3600,
  },

  // Test Data
  testData: {
    orgId: process.env.TEST_ORG_ID || '12345678-1234-5678-9abc-def012345678',
    userId: process.env.TEST_USER_ID || 'user-test-001',
    username: 'testuser',
    orgName: 'Test Organization',
    roles: ['admin'],
  },

  // Playwright Configuration
  playwright: {
    baseUrl: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost',
    timeout: parseInt(process.env.PLAYWRIGHT_TIMEOUT || '30000'),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    fullyParallel: !process.env.CI,
  },

  // Environment Detection
  environment: {
    isCI: !!process.env.CI,
    isDevelopment: process.env.NODE_ENV === 'development',
    isProduction: process.env.NODE_ENV === 'production',
  },

  /**
   * Get complete config for a specific test
   */
  getConfig: function() {
    return {
      api: this.api,
      jwt: this.jwt,
      testData: this.testData,
      playwright: this.playwright,
      environment: this.environment,
    }
  },

  /**
   * Validate configuration
   */
  validate: function() {
    const errors = []

    if (!this.jwt.secret) {
      errors.push('INTERNAL_JWT_SECRET not configured')
    }

    if (!this.api.baseUrl) {
      errors.push('API_BASE_URL not configured')
    }

    if (errors.length > 0) {
      throw new Error(`Playwright configuration errors:\n${errors.map((e) => `  - ${e}`).join('\n')}`)
    }

    return true
  },
}
