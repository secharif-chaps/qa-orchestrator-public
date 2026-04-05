#!/bin/bash
# QA Testing Script — Manual local QA orchestrator trigger
# Template for integrating qa-orchestrator into your project
#
# Usage: ./scripts/qa-test-ticket.sh MON-PROJET-1234 [PROJECT] [WORKFLOW]
#
# Example:
#   ./scripts/qa-test-ticket.sh MON-PROJET-1234
#   ./scripts/qa-test-ticket.sh MON-PROJET-1234 target qa-workflow
#
# Features:
#   - Auto-detects feature branch matching ticket key
#   - Launches QA Orchestrator workflow
#   - Executes actual test suites (Pytest + Playwright)
#   - Generates test reports and X-Ray integration
#   - Captures all logs for debugging

set -e

if [ -z "$1" ]; then
  echo "❌ Usage: $0 <TICKET_KEY>"
  echo "   Example: $0 MON-PROJET-1234"
  echo ""
  echo "ℹ️  Available projects: myproject, other-project"
  echo "ℹ️  Available workflows: qa-workflow (full), test-execution-only (fast), mr-to-tests, bug-cycle"
  exit 1
fi

TICKET_KEY="$1"
PROJECT="${2:-myproject}"
WORKFLOW="${3:-qa-workflow}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo ""
echo "🧪 QA Test Runner"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 Ticket     : $TICKET_KEY"
echo "🔧 Project    : $PROJECT"
echo "⚙️  Workflow   : $WORKFLOW"
echo "⏰ Timestamp  : $TIMESTAMP"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Update git and look for feature branch with ticket code
echo "📦 Searching for code on feature branches..."
git fetch --all > /dev/null 2>&1
echo "✅ Git updated"

# Check for feature branch matching ticket (common patterns)
ORIGINAL_BRANCH=$(git rev-parse --abbrev-ref HEAD)
FEATURE_BRANCH=""

# Convert ticket key to lowercase for branch pattern matching
TICKET_KEY_LOWER=$(echo "$TICKET_KEY" | tr '[:upper:]' '[:lower:]')

# Try common feature branch patterns
for pattern in "feat/$TICKET_KEY" "feat/$TICKET_KEY-*" "feature/$TICKET_KEY*" "*/$TICKET_KEY*"; do
  FOUND_BRANCH=$(git branch -r | grep -i "$pattern" | sed 's|origin/||' | xargs | head -1)
  if [ -n "$FOUND_BRANCH" ]; then
    FEATURE_BRANCH="$FOUND_BRANCH"
    break
  fi
done

# Switch to feature branch if found, otherwise use main
if [ -n "$FEATURE_BRANCH" ]; then
  echo "🔀 Found feature branch: $FEATURE_BRANCH"
  git checkout "$FEATURE_BRANCH" 2>/dev/null || (git checkout -b "$FEATURE_BRANCH" "origin/$FEATURE_BRANCH" && echo "✅ Switched to feature branch with code")
else
  echo "ℹ️  No feature branch found, using main"
  git checkout main > /dev/null 2>&1
fi

# Run QA Orchestrator
echo ""
echo "🚀 Launching QA Orchestrator workflow..."
echo ""

node testing/qa-orchestrator/run.js \
  --project "$PROJECT" \
  --workflow "$WORKFLOW" \
  --ticket "$TICKET_KEY" \
  --message "QA test for $TICKET_KEY"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ QA Plan Generated"
echo "📄 Session saved to: qa-sessions/$PROJECT/${TICKET_KEY}-*.json"
echo ""

# 🧪 Execute actual test suites (automated)
echo ""
echo "🧪 Executing actual test suites..."
echo ""

TEST_RESULTS=""

# Run backend tests
echo "🔨 Running backend tests for $TICKET_KEY..."
if task "$PROJECT:test" 2>&1 | tee -a test-execution-${TICKET_KEY}.log; then
  TEST_RESULTS="${TEST_RESULTS}\n✅ Backend tests: PASSED"
else
  TEST_RESULTS="${TEST_RESULTS}\n❌ Backend tests: FAILED"
fi

# Run E2E tests (if test spec files exist)
echo ""
echo "🎭 Running Playwright E2E tests..."
if [ -f "testing/e2e/${TICKET_KEY_LOWER}-*.spec.ts" ] || [ -f "testing/e2e/$(echo $TICKET_KEY | tr '[:upper:]' '[:lower:]')-*.spec.ts" ]; then
  if cd testing/e2e && npx playwright test "${TICKET_KEY_LOWER}-*.spec.ts" --reporter=html 2>&1 | tee -a ../../test-execution-${TICKET_KEY}.log; then
    TEST_RESULTS="${TEST_RESULTS}\n✅ E2E tests: PASSED"
  else
    TEST_RESULTS="${TEST_RESULTS}\n❌ E2E tests: FAILED"
  fi
  cd - > /dev/null
else
  echo "ℹ️  No E2E test specs found matching pattern"
  TEST_RESULTS="${TEST_RESULTS}\n⏭️  E2E tests: SKIPPED (no test files)"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Full QA Execution Complete"
echo "📄 Session saved to: qa-sessions/$PROJECT/${TICKET_KEY}-*.json"
echo ""
echo "📊 Test Results Summary:"
echo -e "$TEST_RESULTS"
echo ""
echo "📝 Test Logs & Reports:"
echo "   - Execution log: test-execution-${TICKET_KEY}.log"
echo "   - E2E HTML report: testing/e2e/playwright-report/index.html"
echo "   - Jira comment: See ticket $TICKET_KEY (auto-posted)"
echo "   - X-Ray test run: Auto-created and linked"
echo ""
echo "🔍 To view session results:"
echo "   cat qa-sessions/$PROJECT/${TICKET_KEY}-\$(date +%Y-%m-%d).json | jq '.phases[] | {phase, status}'"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
