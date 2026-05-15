/**
 * Playwright Configuration Generator for E2E Tests
 *
 * Generates a valid Playwright config that:
 * - Works with ESM modules
 * - Handles __dirname in ESM context
 * - Configures browser launch options
 * - Sets up proper timeouts and retries
 *
 * Usage in e2e/playwright.config.ts:
 *   import { getPlaywrightConfig } from '../../tools/qa-orchestrator/core/playwright-config.js'
 *   export default getPlaywrightConfig()
 */

import { fileURLToPath } from 'url'
import path from 'path'
import { devices } from '@playwright/test'

export function getPlaywrightConfig(options = {}) {
  const __filename = fileURLToPath(import.meta.url)
  const __dirname = path.dirname(__filename)
  const projectRoot = path.resolve(__dirname, '../../e2e')

  const defaults = {
    testDir: path.resolve(projectRoot),
    testMatch: '**/*.spec.ts',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
      ['html', { outputFolder: 'playwright-report' }],
      ['list'],
      process.env.CI && ['junit', { outputFile: 'junit.xml' }],
    ].filter(Boolean),
    use: {
      baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost',
      trace: 'on-first-retry',
      screenshot: 'only-on-failure',
      video: 'retain-on-failure',
    },
    projects: [
      {
        name: 'chromium',
        use: { ...devices['Desktop Chrome'] },
      },
      process.env.PLAYWRIGHT_FIREFOX && {
        name: 'firefox',
        use: { ...devices['Desktop Firefox'] },
      },
      process.env.PLAYWRIGHT_WEBKIT && {
        name: 'webkit',
        use: { ...devices['Desktop Safari'] },
      },
    ].filter(Boolean),
    webServer: process.env.PLAYWRIGHT_WEB_SERVER && {
      command: process.env.PLAYWRIGHT_WEB_SERVER,
      port: parseInt(process.env.PLAYWRIGHT_WEB_SERVER_PORT || '3000'),
      reuseExistingServer: !process.env.CI,
    },
  }

  return {
    ...defaults,
    ...options,
  }
}

/**
 * Export pre-configured instance for direct use
 */
export default getPlaywrightConfig()
