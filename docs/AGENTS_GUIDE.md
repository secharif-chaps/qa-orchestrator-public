# Complete Agents Guide

Deep dive into all 15 QA agents, how they work, and how they interact.

## Overview

QA Orchestrator uses **15 specialized AI agents** organized in **3 tiers**:

- **Tier 1 (4 agents):** Fast baseline, always runs
- **Tier 2 (4 agents):** Smart analysis, parallel execution
- **Tier 3 (7 agents):** Advanced analysis, runs conditionally

Each agent is an expert in a specific domain. Together, they provide comprehensive QA coverage.

## Tier 1: Daily Use (Fast Baseline)

Tier 1 agents run first, providing quick feedback. Always executed, never skipped.

**Time:** ~10 seconds total  
**Cost:** ~$0.007 per run  
**Model:** Haiku (fast, cheap)  
**Execution:** Sequential

### 1. Data Validator

**Role:** Validates test data structure and completeness

**What it does:**
- Checks if test data matches requirements
- Validates data types and constraints
- Identifies missing test cases
- Suggests data improvements

**Input:**
- Test specifications
- Expected data structures
- Requirements documentation

**Output:**
```json
{
  "isValid": true,
  "dataStructure": "✅ Matches requirements",
  "coverage": "95%",
  "missingScenarios": ["multi-language input"],
  "suggestions": ["Add edge case data for..."],
  "score": 92
}
```

**When it helps:**
- Catching incomplete test data before automation
- Ensuring all scenarios are covered
- Validating test fixtures

### 2. Automator

**Role:** Assesses automation feasibility

**What it does:**
- Evaluates which scenarios can be automated
- Identifies manual-only scenarios
- Suggests automation approach
- Flags blockers or challenges

**Input:**
- Feature requirements
- Technical constraints
- Test scenarios

**Output:**
```json
{
  "automatable": ["Login", "Form submission", "Data validation"],
  "manual": ["Email verification", "SMS confirmation"],
  "approach": "Playwright for UI, API for backend",
  "difficulty": "Medium",
  "estimatedEffort": "16 hours",
  "recommendations": [...]
}
```

**When it helps:**
- Planning test automation strategy
- Identifying cost-benefit of automation
- Allocating manual vs. automated testing

### 3. Gherkin Writer

**Role:** Generates BDD (Behavior-Driven Development) scenarios

**What it does:**
- Writes Gherkin/BDD format scenarios
- Uses Given-When-Then structure
- Creates executable specifications
- Documents expected behavior

**Input:**
- User stories
- Requirements
- Use cases

**Output:**
```gherkin
Feature: User Login
  Scenario: Valid credentials
    Given user is on login page
    When user enters valid email
    And user enters correct password
    And user clicks login
    Then user is redirected to dashboard
    And user profile is displayed
```

**When it helps:**
- Creating executable specifications
- Bridging business and technical teams
- Documenting expected behavior

### 4. Test Selector

**Role:** Scans test inventory and suggests relevant tests

**What it does:**
- Lists all existing tests in project
- Analyzes code changes
- Recommends tests to run
- Identifies new gaps

**Input:**
- Code diff
- Test directory
- Test file patterns

**Output:**
```json
{
  "totalTests": 342,
  "relevantTests": [
    "e2e/login.spec.ts",
    "e2e/profile.spec.ts",
    "unit/auth.spec.ts"
  ],
  "coverage": "78%",
  "gaps": ["Payment flow", "Multi-language"],
  "suggestions": [...]
}
```

**When it helps:**
- Running only relevant tests (faster CI/CD)
- Identifying test coverage gaps
- Optimizing test execution

---

## Tier 2: Live Validation (Smart Analysis)

Tier 2 agents run **simultaneously** (parallel execution). Always executed.

**Time:** ~90 seconds total (wall time, not sequential)  
**Cost:** ~$0.25 per run  
**Model:** Sonnet (balanced)  
**Execution:** Parallel (all 7 agents at once)

### 5. Bug Hunter

**Role:** Detects potential bugs in code

**What it does:**
- Analyzes code for logical errors
- Identifies edge cases
- Finds potential runtime issues
- Checks error handling
- Validates business logic

**Input:**
- Code changes (diff)
- Requirements
- Test cases

**Output:**
```json
{
  "bugsFound": 3,
  "severity": "Medium",
  "bugs": [
    {
      "type": "Logic error",
      "location": "auth.ts:42",
      "description": "Null pointer when token expires",
      "severity": "High",
      "fix": "Check if token exists before use"
    },
    ...
  ],
  "score": 78
}
```

**When it helps:**
- Finding issues before QA
- Preventing production bugs
- Rapid feedback on code quality

### 6. Browser Validator

**Role:** Generates Playwright tests for browser validation

**What it does:**
- Generates automated Playwright test scripts
- Creates end-to-end test cases
- Validates UI behavior
- Tests user interactions
- Verifies visual changes

**Input:**
- Feature description
- UI mockups or screenshots
- Requirements

**Output:**
```typescript
test('User can login with valid credentials', async ({ page }) => {
  await page.goto('http://localhost/login');
  
  await page.fill('input[name="email"]', 'test@example.com');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  
  await expect(page).toHaveURL('http://localhost/dashboard');
  await expect(page.locator('h1')).toContainText('Dashboard');
});
```

**When it helps:**
- Generating test scripts from requirements
- Validating UI changes
- Creating regression tests

### 7. Accessibility Auditor

**Role:** Checks WCAG 2.1 accessibility compliance

**What it does:**
- Validates color contrast ratios
- Checks keyboard navigation
- Tests screen reader compatibility
- Verifies semantic HTML
- Checks ARIA attributes

**Input:**
- Page HTML
- Component definitions
- Design specifications

**Output:**
```json
{
  "wcagLevel": "AA",
  "compliant": true,
  "issues": [
    {
      "type": "Contrast",
      "element": ".button-primary",
      "ratio": "3.5:1",
      "required": "4.5:1",
      "severity": "Low"
    }
  ],
  "score": 94,
  "recommendations": [...]
}
```

**When it helps:**
- Ensuring accessible products
- Meeting legal requirements (ADA, WCAG)
- Improving user experience for all

### 8. Performance Auditor

**Role:** Analyzes performance metrics and bottlenecks

**What it does:**
- Measures page load times
- Identifies slow operations
- Checks resource usage
- Detects performance regressions
- Provides optimization suggestions

**Input:**
- Code changes
- Performance baseline
- Requirements

**Output:**
```json
{
  "metrics": {
    "pageLoadTime": "2.1s",
    "firstContentfulPaint": "0.8s",
    "largestContentfulPaint": "1.5s"
  },
  "issues": [
    {
      "type": "Unoptimized image",
      "impact": "High",
      "suggestion": "Use WebP format, size: 2.5MB"
    }
  ],
  "score": 87,
  "grade": "Good"
}
```

**When it helps:**
- Catching performance regressions
- Optimizing user experience
- Meeting performance budgets

### 9-11. Additional Tier 2 Agents

The remaining Tier 2 agents (dataValidator, automator, gherkinWriter in parallel mode) provide additional perspectives and validation.

---

## Tier 3: Advanced Analysis (Conditional)

Tier 3 agents run **only when needed** based on smart conditional logic.

**Time:** Variable (~10-120 seconds)  
**Cost:** ~$0.10-0.30 (only when triggered)  
**Model:** Opus (deep reasoning)  
**Execution:** Sequential (runs after Tier 2 evaluation)

### When Tier 3 Triggers

Tier 3 automatically activates when:

```javascript
IF (bugHunter.score < 70 OR criticalBugsFound)
  THEN run Reviewer (deep code analysis)

IF (testSelector.gaps > 5 OR coverage < 70%)
  THEN run TestGenerator (create missing tests)

IF (suspiciousPatterns OR securityConcerns)
  THEN run SecurityAnalyzer (threat detection)

IF (complexDecisionNeeded)
  THEN run Orchestrator (strategic recommendations)
```

This prevents wasting expensive Opus calls.

### 12. Reviewer (Opus)

**Role:** Expert code review

**What it does:**
- Detailed code analysis
- Architecture evaluation
- Best practices verification
- Security review (basic)
- Refactoring suggestions

**When it triggers:**
- Tier 2 found significant issues
- Code complexity is high
- Multiple bugs detected

**Output:**
```json
{
  "review": {
    "architecture": "✅ Good",
    "codeQuality": "⚠️ Needs improvement",
    "issues": [
      {
        "type": "Maintainability",
        "severity": "Medium",
        "suggestion": "Extract function for reusability"
      }
    ]
  },
  "score": 72
}
```

### 13. Test Generator

**Role:** Generates missing test cases

**What it does:**
- Analyzes code coverage gaps
- Generates test implementations
- Creates edge case tests
- Writes error handling tests

**When it triggers:**
- Test coverage below threshold
- Gaps identified by testSelector
- High complexity without tests

**Output:**
```typescript
// Generated test for edge case
test('Should handle empty input gracefully', async () => {
  const result = processData('');
  expect(result).toEqual({ valid: false, error: 'Empty input' });
});
```

### 14. Security Analyzer

**Role:** Detects security threats and vulnerabilities

**What it does:**
- OWASP vulnerability scanning
- Authentication/authorization review
- Data protection validation
- Input validation checking
- Dependency vulnerability detection

**When it triggers:**
- Security-related code changes
- Authentication/payment features
- Data handling code

**Output:**
```json
{
  "vulnerabilities": [
    {
      "type": "SQL Injection",
      "severity": "Critical",
      "location": "query.ts:45",
      "fix": "Use parameterized queries"
    }
  ],
  "score": 65
}
```

### 15-17. Additional Tier 3 Agents

**Manual Validator** — Identifies scenarios that cannot be automated  
**Prompt Tuner** — Optimizes agent prompts based on results  
**Release Analyzer** — Analyzes multi-repo impact  

And potentially others based on configuration.

---

## How Agents Communicate

Agents don't run in isolation. They pass information forward:

```
Tier 1 Output (baseline)
  ↓
Tier 2 agents analyze (7 parallel)
  ├── bugHunter finds issues
  ├── browserValidator generates tests
  ├── accessibilityAuditor checks A11y
  └── performanceAuditor measures perf
  ↓
Tier 2 scores are evaluated
  ↓
Smart conditional logic decides:
  IF conditions met → Activate Tier 3 agents
  ELSE → Skip (save money)
  ↓
Tier 3 agents (if triggered) do deep analysis
  ↓
Final Report synthesizes all findings
```

## Agent Models Explained

### Haiku (Tier 1)

- **Speed:** ⚡⚡⚡⚡⚡ (Fastest)
- **Cost:** 💰 (Cheapest)
- **Intelligence:** 🧠 (Basic)
- **Use:** Fast baseline, always runs
- **Examples:** Data validation, test selection

### Sonnet (Tier 2)

- **Speed:** ⚡⚡⚡ (Fast)
- **Cost:** 💰💰 (Reasonable)
- **Intelligence:** 🧠🧠🧠 (Smart)
- **Use:** Analysis, recommendations
- **Examples:** Bug hunting, test generation

### Opus (Tier 3)

- **Speed:** ⚡⚡ (Slower)
- **Cost:** 💰💰💰 (Expensive)
- **Intelligence:** 🧠🧠🧠🧠🧠 (Most capable)
- **Use:** Deep analysis (conditional)
- **Examples:** Security review, strategic decisions

---

## Workflows — Agent Chains

Workflows define which agents run and in what order:

### Workflow: qa-workflow (Full QA Cycle)

```
1. Start with Tier 1 (always)
2. Run Tier 2 in parallel (always)
3. Evaluate Tier 2 results
4. Run Tier 3 based on conditions
5. Synthesize final report
```

### Workflow: quick-review (Fast Feedback)

```
1. Run fast Tier 1 agents
2. Skip Tier 2 (save time)
3. Skip Tier 3 (conditional only)
4. Return quick feedback
```

### Workflow: browser-validate (UI Testing)

```
1. Data validator (verify test data)
2. Browser validator (generate tests)
3. Run tests live against running app
4. Report results with screenshots
```

### Workflow: bug-cycle (Bug Discovery)

```
1. Bug hunter (scan for issues)
2. Security analyzer (check for vulnerabilities)
3. Manual validator (identify untestable scenarios)
4. Provide bug report with reproduction steps
```

---

## Agent Configuration

### Customizing Agent Behavior

Each agent has configurable parameters:

```javascript
// In agents/registry.js
{
  id: 'bugHunter',
  name: 'Bug Hunter',
  model: 'claude-sonnet-4-6',
  icon: '🐛',
  tier: 'tier2',
  prompt: `You are an expert bug finder...`,
  temperature: 0.7,  // Creativity level
  maxTokens: 2000,   // Response length
  timeout: 30000,    // Max execution time
}
```

### Modifying Prompts

Customize agent instructions for your project:

```javascript
const agent = agents.find(a => a.id === 'reviewer');
agent.prompt = `You are an expert code reviewer for Vue 3 applications...
  Focus on:
  - TypeScript best practices
  - Vue Composition API patterns
  - Tailwind CSS optimization
  ...`;
```

### Disabling Agents

Skip agents you don't need:

```bash
# Skip accessibility checks
npx qa-orchestrator test MYKEY-1234 --disable-agents accessibilityAuditor

# Skip security analysis
npx qa-orchestrator test MYKEY-1234 --disable-agents securityAnalyzer
```

---

## Performance & Cost by Agent

### Tier 1 (Total: ~10s, $0.007)

| Agent | Time | Cost | Model |
|-------|------|------|-------|
| dataValidator | 3s | $0.002 | Haiku |
| automator | 2s | $0.001 | Haiku |
| gherkinWriter | 3s | $0.002 | Haiku |
| testSelector | 2s | $0.002 | Haiku |
| **TOTAL** | **10s** | **$0.007** | |

### Tier 2 Parallel (Total: ~90s, $0.25)

Running 7 agents simultaneously, but show sequential cost for reference:

| Agent | Time | Cost | Model |
|-------|------|------|-------|
| bugHunter | 15s | $0.04 | Sonnet |
| browserValidator | 20s | $0.05 | Sonnet |
| accessibilityAuditor | 12s | $0.03 | Sonnet |
| performanceAuditor | 10s | $0.02 | Sonnet |
| dataValidator | 8s | $0.02 | Sonnet |
| automator | 10s | $0.02 | Sonnet |
| gherkinWriter | 15s | $0.07 | Sonnet |
| **PARALLEL TIME** | **20s** | **$0.25** | |

### Tier 3 (Variable, triggered conditionally)

Only runs when needed:

| Agent | Time | Cost | Model | Triggers |
|-------|------|------|-------|----------|
| reviewer | 30s | $0.15 | Opus | bugHunter.score < 70 |
| testGenerator | 25s | $0.12 | Opus | coverage < 70% |
| securityAnalyzer | 20s | $0.10 | Opus | security concerns |
| **IF ALL TRIGGERED** | **75s** | **$0.37** | | |

---

## Real-World Example: Testing a Login Feature

### Code Change
```typescript
// Feature: Add "Remember Me" checkbox to login
async function login(email, password, rememberMe) {
  const user = await verifyCredentials(email, password);
  if (rememberMe) {
    localStorage.setItem('token', user.token);
  }
  return user;
}
```

### Agent Analysis

**Tier 1 (10 seconds):**
- ✅ dataValidator: "Test data structure looks good"
- ✅ automator: "This is automatable via Playwright"
- ✅ gherkinWriter: "Here's the BDD scenario..."
- ✅ testSelector: "Found 5 related existing tests"

**Tier 2 (90 seconds, parallel):**
- 🐛 bugHunter: "Potential bug: localStorage not cleared on logout"
- ✅ browserValidator: "Generated Playwright test"
- ✅ accessibilityAuditor: "Checkbox is accessible"
- ✅ performanceAuditor: "No performance issues"

**Tier 3 (triggered because bugHunter found issue):**
- 🔒 reviewer: "Add CSRF protection, sanitize inputs"
- 🔐 securityAnalyzer: "Check localStorage security implications"
- ✅ testGenerator: "Generated test for logout clearing storage"

**Final Report:**
```
QA Analysis for Login Feature
═════════════════════════════
✅ Feature is automatable
✅ 5 existing tests cover this feature
✅ Accessibility compliant
✅ Performance acceptable
⚠️ 1 bug found: localStorage not cleared on logout
🔐 Security consideration: HTTPS required for token storage
📝 1 new test generated for logout scenario
```

---

## Extending Agents

### Creating a Custom Agent

```javascript
// agents/my-custom-agent.js
class MyCustomAgent {
  constructor(config) {
    this.config = config;
  }

  async analyze(input) {
    // Your analysis logic
    return {
      findings: [...],
      score: 85,
      severity: 'Medium'
    };
  }
}

module.exports = MyCustomAgent;
```

### Registering Your Agent

```javascript
// agents/registry.js
const MyCustomAgent = require('./my-custom-agent');

const allAgents = [
  // ...existing agents...
  {
    id: 'myCustom',
    name: 'My Custom Agent',
    agent: MyCustomAgent,
    tier: 'tier2',
    model: 'claude-sonnet-4-6',
  }
];
```

### Using Your Agent

```bash
npx qa-orchestrator test MYKEY-1234 --include-agents myCustom
```

---

## Monitoring Agent Performance

Track how agents perform over time:

```bash
# View agent statistics
npx qa-orchestrator --agent-stats

# Output:
# Agent          Runs  Avg Time  Cost    Accuracy
# bugHunter      342   12.3s     $4.12   87%
# browserValidator 324  18.1s    $6.24   92%
# ...
```

---

## Troubleshooting Agents

### Agent times out

```bash
# Increase timeout for slow agents
npx qa-orchestrator test MYKEY-1234 --timeout 60000
```

### Agent results are poor

```bash
# Review and improve agent prompt
npx qa-orchestrator --edit-agent bugHunter --prompt

# Or use promptTuner to optimize automatically
npx qa-orchestrator test MYKEY-1234 --optimize-agents
```

### Agent not running

```bash
# Check if agent is enabled
npx qa-orchestrator --list-agents

# Enable disabled agent
npx qa-orchestrator test MYKEY-1234 --enable-agents browserValidator
```

---

## Summary

| Tier | Agents | Time | Cost | When |
|------|--------|------|------|------|
| **1** | 4 agents | 10s | $0.007 | Always |
| **2** | 7 parallel | 90s | $0.25 | Always |
| **3** | 7 conditional | Variable | $0.10-0.30 | If needed |
| **TOTAL** | 15 agents | 220s | $0.37 | Default |

The 3-tier model, combined with parallel execution and smart conditional logic, achieves **85% cost reduction and 10x speedup** while maintaining **95% quality**.

---

**Next:** See [WORKFLOWS.md](./WORKFLOWS.md) for detailed workflow examples.
