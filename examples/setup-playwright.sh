#!/bin/bash

# Playwright E2E Setup Script
# Automatically configures Playwright for ChapsMind internal API testing
#
# Usage: bash examples/setup-playwright.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ORCHESTRATOR_ROOT="$(dirname "$SCRIPT_DIR")"
E2E_DIR="$ORCHESTRATOR_ROOT/../../e2e"

echo "🎭 Setting up Playwright for ChapsMind E2E Testing..."
echo ""

# Check if e2e directory exists
if [ ! -d "$E2E_DIR" ]; then
  echo "❌ Error: e2e directory not found at $E2E_DIR"
  exit 1
fi

cd "$E2E_DIR"

# 1. Create/update package.json with ESM configuration
echo "📦 Configuring package.json for ESM..."
if [ ! -f "package.json" ]; then
  cat > package.json << 'EOF'
{
  "name": "chapsmind-e2e",
  "version": "1.0.0",
  "type": "module",
  "description": "ChapsMind E2E tests with Playwright",
  "scripts": {
    "test": "playwright test",
    "test:ui": "playwright test --ui",
    "test:debug": "playwright test --debug",
    "test:headed": "playwright test --headed",
    "test:report": "playwright show-report"
  },
  "devDependencies": {
    "@playwright/test": "^1.60.0"
  }
}
EOF
  echo "✅ Created package.json"
else
  # Update type to module if it's commonjs
  if grep -q '"type": "commonjs"' package.json; then
    sed -i 's/"type": "commonjs"/"type": "module"/' package.json
    echo "✅ Updated package.json type to module"
  fi
fi

# 2. Update playwright.config.ts
echo ""
echo "⚙️  Creating playwright.config.ts with ESM support..."
cat > playwright.config.ts << 'EOF'
import { defineConfig, devices } from '@playwright/test'
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
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
EOF
echo "✅ Created playwright.config.ts with ESM support"

# 3. Create .env.local with defaults
echo ""
echo "📝 Creating .env.local..."
if [ ! -f ".env.local" ]; then
  cat > .env.local << 'EOF'
# Playwright E2E Configuration
INTERNAL_JWT_SECRET=local-dev-internal-jwt-secret-32chars
API_BASE_URL=http://localhost/api
PLAYWRIGHT_BASE_URL=http://localhost
PLAYWRIGHT_TIMEOUT=30000
TEST_ORG_ID=12345678-1234-5678-9abc-def012345678
TEST_USER_ID=user-test-001
EOF
  echo "✅ Created .env.local (update with your backend secret)"
else
  echo "✅ .env.local already exists (verify INTERNAL_JWT_SECRET is correct)"
fi

# 4. Create helpers directory
echo ""
echo "📁 Creating helpers directory..."
mkdir -p helpers
echo "✅ Created helpers/ directory"

# 5. Create a sample auth helper
echo ""
echo "📄 Creating sample auth helper..."
cat > helpers/auth.ts << 'EOF'
import crypto from 'crypto'

export interface TokenPayload {
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

/**
 * Create a signed internal JWT token
 * Used for testing internal API endpoints
 */
export function createInternalToken(options: {
  userId?: string
  username?: string
  orgId?: string
  orgName?: string
  roles?: string[]
  email?: string
  expirySeconds?: number
}): string {
  const secret = process.env.INTERNAL_JWT_SECRET || 'local-dev-internal-jwt-secret-32chars'
  const now = Math.floor(Date.now() / 1000)
  const expirySeconds = options.expirySeconds || 3600

  const payload: TokenPayload = {
    sub: options.userId || 'user-123',
    username: options.username || 'testuser',
    email: options.email || null,
    org_id: options.orgId || (process.env.TEST_ORG_ID || '12345678-1234-5678-9abc-def012345678'),
    org_name: options.orgName || 'Test Org',
    roles: options.roles || ['admin'],
    iss: 'global-gateway',
    iat: now,
    exp: now + expirySeconds,
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
EOF
echo "✅ Created helpers/auth.ts"

# 6. Install dependencies
echo ""
echo "📥 Installing dependencies..."
npm install --save-dev @playwright/test
echo "✅ Dependencies installed"

# 7. Print success message
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Playwright Setup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 Next Steps:"
echo "  1. Update .env.local with your INTERNAL_JWT_SECRET:"
echo "     - Get it from your running backend"
echo "     - Or from infra/compose.local.yaml"
echo ""
echo "  2. Create your first test:"
echo "     cp ../../tools/qa-orchestrator/examples/playwright-test-example.spec.ts ."
echo ""
echo "  3. Run tests:"
echo "     npm test                    # Run all tests"
echo "     npm run test:ui            # Interactive UI mode"
echo "     npm run test:debug         # Debug mode"
echo "     npm run test:report        # View HTML report"
echo ""
echo "📚 Documentation:"
echo "  - Setup Guide: ../../tools/qa-orchestrator/docs/PLAYWRIGHT_SETUP.md"
echo "  - Examples: ../../tools/qa-orchestrator/examples/"
echo ""
echo "🎭 Happy Testing!"
echo ""
