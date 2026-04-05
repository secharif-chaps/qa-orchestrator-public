# QA Orchestrator Hub — Complete Usage Guide v1.3.0

**Version:** 1.3.0 | **Status:** Generic for all QA teams | **Date:** April 5, 2026

A comprehensive guide to using the QA Orchestrator for automated test planning, execution, and validation across your projects.

---

## 🎯 Quick Start (5 Minutes)

### Basic Command

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow qa-workflow \
  --ticket MON-PROJET-1234 \
  --message "Test new feature"
```

### What Happens

1. **Code Review** — Agent reviews your implementation
2. **Test Planning** — Generate comprehensive test cases
3. **E2E Script Generation** — Create Playwright automation tests
4. **Validation** — Validate test results
5. **Session Management** — Store results in JSON
6. **Gherkin Generation** — Auto-generate BDD scenarios
7. **Test Execution** — Run actual test suites (Pytest + Playwright)

Result: Test report linked to Jira + X-Ray test run created

---

## 📋 Available Workflows

### 1. **qa-workflow** (Recommended)
**Use for:** Full QA cycle from code review to test execution

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow qa-workflow \
  --ticket MON-PROJET-1234
```

**Steps:**
- Code reviewer validates implementation
- Test generator creates test cases
- Automator writes Playwright scripts
- Validator reviews tests
- SessionManager persists session
- GherkinWriter auto-generates scenarios
- Tests actually execute (Pytest + Playwright)

**Output:**
- Test plan in Jira comments
- X-Ray test run created + linked
- Session saved: `qa-sessions/MON-PROJET/MON-PROJET-1234-*.json`
- Test execution logs: `test-execution-MON-PROJET-1234.log`

---

### 2. **scan-adapt**
**Use for:** First-time setup on a new project (code stack detection)

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow scan-adapt \
  --ticket MON-PROJET-9999
```

**Steps:**
- Scanner detects project stack (frontend, backend, frameworks, dependencies)
- Adapter configures QA for the detected stack

**Output:**
- Detected tech stack in session
- Workflow configuration recommendations

---

### 3. **mr-to-tests**
**Use for:** Analyze merge requests and auto-generate tests

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow mr-to-tests \
  --message "Analyze MR !1234"
```

**Steps:**
- MR Analyzer extracts changes from GitLab MR
- Test Generator creates test cases for changed code
- Automator writes Playwright scripts
- Validator reviews tests

**Output:**
- Test cases tailored to MR changes
- E2E test scripts

---

### 4. **bug-cycle**
**Use for:** Bug discovery and test case generation

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow bug-cycle \
  --ticket MON-PROJET-9876
```

**Steps:**
- Bug Hunter analyzes the code
- Test Generator creates test cases to cover the bug
- Gherkin scenarios auto-generated
- Validator ensures tests catch the bug

**Output:**
- Bug test cases
- Gherkin scenarios for regression prevention

---

### 5. **xray-sync**
**Use for:** Maintain test library in X-Ray (Jira)

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow xray-sync
```

**Steps:**
- Scanner scans test files
- X-Ray syncer updates test library

**Output:**
- Tests registered in X-Ray
- Test library updated

---

### 6. **sprint-health**
**Use for:** Sprint review preparation (metrics, health check)

```bash
node testing/qa-orchestrator/run.js \
  --project MON-PROJET \
  --workflow sprint-health \
  --message "Sprint 42 health"
```

**Steps:**
- Project Manager analyzes sprint tickets
- Health reporter generates metrics
- Metrics stored in session

**Output:**
- Sprint health report
- Risk assessment
- Metrics for retrospective

---

## 🔧 Manual Test Execution Script

For manual QA testing with automatic branch detection and test execution:

```bash
./scripts/qa-test-ticket.sh MON-PROJET-1234 [PROJECT] [WORKFLOW]
```

### Parameters

- `MON-PROJET-1234` — Jira ticket key (required)
- `PROJECT` — Project name (optional, default: `screen`)
- `WORKFLOW` — Workflow to execute (optional, default: `qa-workflow`)

### What It Does

1. **Fetches all branches** from git
2. **Auto-detects feature branch** using patterns:
   - `feat/MON-PROJET-1234`
   - `feat/MON-PROJET-1234-*`
   - `feature/MON-PROJET-1234*`
3. **Switches to feature branch** if found
4. **Launches QA Orchestrator** with the workflow
5. **Executes actual tests:**
   - Pytest (backend unit/integration tests)
   - Playwright (E2E tests)
6. **Generates reports:**
   - Test execution logs
   - Playwright HTML report
   - X-Ray test results (auto-linked)
7. **Returns to original branch**

### Example

```bash
# Test TAR-1234 (ChapsMind example)
./scripts/qa-test-ticket.sh TAR-1234

# Test MON-PROJET-9999 on TARGET project
./scripts/qa-test-ticket.sh MON-PROJET-9999 target

# Test MON-PROJET-5555 with full-ticket workflow
./scripts/qa-test-ticket.sh MON-PROJET-5555 screen full-ticket
```

### Output Files

- `test-execution-MON-PROJET-1234.log` — Combined test logs
- `testing/e2e/playwright-report/index.html` — HTML test report
- `qa-sessions/PROJECT/MON-PROJET-1234-*.json` — Session data
- Jira comments with test results + X-Ray link

---

## ⚙️ Configuration

### Project Configuration File

Create `.gitlab/agent/workflows.yml` to map tickets to test files:

```yaml
# QA Orchestrator Workflow Configuration
workflows:
  qa-workflow:
    steps:
      - id: test-execution
        description: Execute actual test suites
        script: |
          task project:test
          cd testing/e2e
          npx playwright test mon-projet-*.spec.ts --reporter=html
          cd -

test-files:
  patterns:
    - ticket: "MON-PROJET-1234"
      pytest: "apps/project/tests/test_feature.py"
      playwright: "testing/e2e/mon-projet-1234-feature.spec.ts"

    - ticket: "MON-PROJET-*"
      pytest: "apps/project/tests/test_*.py"
      playwright: "testing/e2e/mon-projet-*.spec.ts"

timeouts:
  pytest: 300
  playwright: 600

reports:
  pytest:
    format: "junit"
    output: "test-reports/pytest-${TICKET}.xml"
  
  playwright:
    format: "html"
    output: "testing/e2e/playwright-report/index.html"

xray:
  enabled: true
  auto-link-tests: true
  comment-on-original: true
```

### Environment Variables

```bash
# .env file
QA_HUB_ANTHROPIC_KEY=sk-ant-...           # Claude API key
QA_HUB_GITLAB_TOKEN=glpat-...             # GitLab API token
XRAY_ENABLED=true                         # Enable X-Ray integration
XRAY_TICKET=MON-PROJET-5000               # X-Ray QA ticket
LLM_PROVIDER=azure                        # or 'openai'
```

---

## 🧪 Test Execution

### Pytest (Backend Tests)

```bash
# Run all tests for a project
task project:test

# Run specific test file
task project:test -- "tests/test_feature.py" -v

# With coverage report
task project:test -- --cov=app tests/
```

**Coverage:**
- Unit tests (AC validation)
- Integration tests (API endpoints)
- Database tests
- Third-party service mocking

### Playwright (E2E Tests)

```bash
# Run all E2E tests
cd testing/e2e
npx playwright test --reporter=html

# Run specific test file
npx playwright test mon-projet-1234.spec.ts

# With detailed output
npx playwright test --reporter=html --debug
```

**Coverage:**
- User workflows
- UI interactions
- Data display
- Error handling
- Cross-browser testing

---

## 🔗 X-Ray Integration

### Automatic Test Run Creation

After tests execute, QA Orchestrator automatically:

1. **Creates X-Ray Test Run**
   - Links to original ticket (MON-PROJET-1234)
   - Imports Pytest results (JUnit XML)
   - Imports Playwright results (HTML)

2. **Auto-Links Test Results**
   - Each test case linked to Acceptance Criteria
   - Pass/fail status updated
   - Execution time recorded

3. **Comments on Jira**
   - Links to X-Ray test run
   - Summary: `✅ 45/45 passed`, `❌ 3 failed`
   - Failed tests flagged for review

### Manual X-Ray Creation

If automatic linking fails:

```bash
# Use X-Ray Test Importer in Jira
# 1. Go to Jira ticket MON-PROJET-1234
# 2. Create → Test Run
# 3. Import → Select test reports:
#    - test-reports/pytest-MON-PROJET-1234.xml
#    - testing/e2e/playwright-report/results.json
```

---

## 📊 Session Management

### Session Structure

Sessions store complete QA execution history:

```json
{
  "session_id": "uuid-1234",
  "ticket": "MON-PROJET-1234",
  "workflow": "qa-workflow",
  "created_at": "2026-04-05T10:30:00Z",
  "phases": [
    {
      "phase": "code-review",
      "agent": "CodeReviewer",
      "status": "completed",
      "findings": ["AC1 implemented correctly", "AC2 needs test"]
    },
    {
      "phase": "test-planning",
      "agent": "TestGenerator",
      "status": "completed",
      "test_cases": 45
    },
    {
      "phase": "test-execution",
      "status": "completed",
      "pytest": {
        "passed": 42,
        "failed": 3,
        "skipped": 0
      },
      "playwright": {
        "passed": 8,
        "failed": 0
      }
    }
  ],
  "xray_ticket": "MON-PROJET-5001",
  "xray_link": "https://jira.domain.com/issues/MON-PROJET-5001"
}
```

### Session Persistence

Sessions stored in:
- **Location:** `qa-sessions/PROJECT/TICKET-TIMESTAMP.json`
- **Archive:** Auto-uploaded to Confluence (draft)
- **Retention:** 90 days (configurable)

### Session Retrieval

```bash
# List all sessions for a ticket
ls -la qa-sessions/PROJECT/ | grep MON-PROJET-1234

# View session details
cat qa-sessions/PROJECT/MON-PROJET-1234-2026-04-05.json | jq '.phases[] | {phase, status}'

# Extract test results
cat qa-sessions/PROJECT/MON-PROJET-1234-*.json | jq '.phases[] | select(.phase=="test-execution")'
```

---

## 🐛 Troubleshooting

### Issue: Tests Not Executing

**Symptom:** QA plan generated but no actual tests run

**Cause:** `qa-workflow` might have skipped test-execution step

**Solution:**
```bash
# Check .gitlab/agent/workflows.yml has test-execution step
grep -A 5 "test-execution:" .gitlab/agent/workflows.yml

# If missing, add:
- id: test-execution
  description: Execute actual test suites
  script: |
    task project:test
    cd testing/e2e && npx playwright test --reporter=html && cd -
```

---

### Issue: Feature Branch Not Found

**Symptom:** "No feature branch found, using main" message

**Cause:** Branch name doesn't match patterns in script

**Solution:**
```bash
# Check available branches
git branch -r | grep -E "feat/MON-PROJET|feature/MON-PROJET"

# If branch exists with different name, use it directly:
git checkout your-custom-branch-name
./scripts/qa-test-ticket.sh MON-PROJET-1234
```

---

### Issue: X-Ray Test Run Not Created

**Symptom:** Jira comment shows test results but no X-Ray ticket

**Cause:** X-Ray integration disabled or credentials missing

**Solution:**
```bash
# Check X-Ray is enabled
grep "xray:" .gitlab/agent/workflows.yml

# Check X-Ray ticket is configured
grep "XRAY_TICKET=" .env

# If needed, enable X-Ray:
echo "XRAY_ENABLED=true" >> .env
echo "XRAY_TICKET=MON-PROJET-5000" >> .env
```

---

### Issue: Playwright Tests Timeout

**Symptom:** "Timeout waiting for selector" errors

**Cause:** Page not fully loaded or selector changed

**Solution:**
```typescript
// Increase timeout in test
await page.waitForSelector('[data-testid="element"]', { timeout: 60000 });

// Or wait for network idle
await page.waitForLoadState('networkidle');

// Check selector exists
await expect(page.locator('[data-testid="element"]')).toBeVisible();
```

---

### Issue: Permission Denied on Script

**Symptom:** "`./scripts/qa-test-ticket.sh: permission denied`"

**Solution:**
```bash
# Make script executable
chmod +x scripts/qa-test-ticket.sh

# Then run
./scripts/qa-test-ticket.sh MON-PROJET-1234
```

---

## 📈 Performance & Optimization

### Parallel Test Execution

Backend tests (Pytest) and frontend tests (Playwright) run sequentially by default. To run in parallel:

```bash
# In .gitlab/agent/workflows.yml
test-execution:
  parallel:
    - id: pytest
      script: task project:test
    - id: playwright
      script: cd testing/e2e && npx playwright test --reporter=html && cd -
```

### Test Caching

For faster test runs on repeated executions:

```bash
# Cache Playwright browsers
npm ci --prefer-offline --no-audit

# Run Playwright with state persistence
npx playwright test --use-index
```

### Test Timeouts

Adjust per your project:

```yaml
# .gitlab/agent/workflows.yml
timeouts:
  pytest: 600      # 10 minutes for all pytest
  playwright: 900  # 15 minutes for all playwright
```

---

## 🔒 Security & Best Practices

### API Token Management

**NEVER** commit API tokens:

```bash
# ✅ CORRECT: Store in environment
export QA_HUB_ANTHROPIC_KEY="sk-ant-..."

# ❌ WRONG: Hardcode in script
API_KEY="sk-ant-xxx"
```

### Test Data Handling

- **Use test fixtures** for consistent data
- **Never test with production data**
- **Clean up after tests** (databases, files)
- **Mock external services** (APIs, third-party services)

### CI/CD Safety

- **Always run tests in feature branches** (never on main/master)
- **Require code review** before test execution on main
- **Archive test sessions** for audit trail
- **Review failed test logs** before merging

---

## 📞 Support & Resources

### Common Questions

**Q: Which workflow should I use?**
A: Start with `qa-workflow` (full cycle). Use others for specific needs (bug-cycle, mr-to-tests, etc.)

**Q: Can I run QA Orchestrator without Docker?**
A: Not recommended. Docker ensures consistent environment. Use: `docker compose up -d && ./scripts/qa-test-ticket.sh MON-PROJET-1234`

**Q: How are test results stored?**
A: Sessions stored in `qa-sessions/` as JSON. Auto-linked to Jira + X-Ray test runs. Results persist for 90 days.

**Q: What if I need custom test configuration?**
A: Edit `.gitlab/agent/workflows.yml` and configure test file patterns, timeouts, and report formats.

---

## 📝 Changelog

### v1.3.0 (April 5, 2026)
- ✅ Auto-branch detection in qa-test-ticket.sh
- ✅ Generic documentation for all QA teams
- ✅ Test execution integration (Pytest + Playwright)
- ✅ X-Ray auto-linking improvements
- ✅ Session persistence refinements

### v1.2.0 (March 15, 2026)
- ✅ X-Ray integration
- ✅ Jira auto-comments

### v1.1.0 (April 2, 2026)
- ✅ SessionManager agent
- ✅ GherkinWriter agent
- ✅ qa-workflow introduction

---

**Last Updated:** April 5, 2026  
**Maintained By:** QA Orchestrator Team  
**Support:** See troubleshooting section above
