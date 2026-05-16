# QA Orchestrator — Live Validation Testing System

**Version:** 3.0.0 | **Status:** Production Ready ✅ | **Updated:** May 15, 2026

Enterprise agentic QA testing system for ChapsMind with **live validation** (testing running code), **self-learning** (failure pattern injection), and **output verification** (LLM-based gate).

## 🎯 What's New in v3.0.0

🚀 **Live Validation** — Test running services at `http://localhost`, not just diffs  
🔒 **Verification Gate** — LLM validates each agent output (0-100 score) before chaining  
🧠 **Learning System** — Persist failures, inject failure patterns into future prompts  
⚙️ **Environment Manager** — Auto-start services + health polling before agent execution  
📈 **15 Agents in 3 Tiers** — Organized by usage pattern (Daily Use, Live Validation, Advanced)  
🔄 **Session Persistence Fix** — Sessions now properly saved to disk  
📚 **Complete Documentation** — 1400+ lines of CONTEXT.md with project reference  

## 🏗️ Core Architecture

### 3 Pillars of Live Validation

```
┌─────────────────────────────────────────────────────────────────┐
│                    LIVE VALIDATION SYSTEM                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1️⃣  ENVIRONMENT MANAGER (core/env-manager.js)                │
│      • Auto-start Docker services (task up via spawn)          │
│      • Health check polling (/api/health/ready)               │
│      • Optional PR branch checkout (8-pattern matching)        │
│      • Tests against RUNNING code at http://localhost          │
│                                                                 │
│  2️⃣  VERIFICATION GATE (core/verification-gate.js)            │
│      • LLM-based output validation (0-100 score)              │
│      • Structural checks (required sections per agent)         │
│      • Auto-retry (max 2) with issues injected               │
│      • Records failures for learning system                    │
│                                                                 │
│  3️⃣  LEARNING SYSTEM (core/learning-system.js)               │
│      • Persists failures to qa-sessions/learnings/             │
│      • Rolling window (20 entries per agent)                   │
│      • Injects "Known Failure Patterns" into prompts           │
│      • Prevents repeated mistakes across sessions              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 🤖 The 15 Agents

### Daily Use Tier (4 agents)
| Agent | Role | Model |
|-------|------|-------|
| **reviewer** | AC coverage + gap detection | Opus 4.6 |
| **testGenerator** | Test case design | Opus 4.6 |
| **testSelector** | Test inventory scanning | Sonnet 4.6 |
| **bugHunter** | Potential bug discovery | Sonnet 4.6 |

### Live Validation Tier (3 agents)
| Agent | Role | Model |
|-------|------|-------|
| **browserValidator** | Playwright test generation + execution | Sonnet 4.6 |
| **manualValidator** | Non-automatable scenarios (MFA, email, SMS) | Sonnet 4.6 |
| **releaseAnalyzer** | Cross-repo dependency detection | Sonnet 4.6 |

### Advanced Tier (8 agents)
| Agent | Role | Model |
|-------|------|-------|
| **promptTuner** | Agent prompt optimization | Opus 4.6 |
| **dataValidator** | Test data consistency | Haiku 4.5 |
| **accessibilityAuditor** | A11y compliance check | Sonnet 4.6 |
| **performanceAuditor** | Load time + metrics analysis | Sonnet 4.6 |
| **securityAnalyzer** | OWASP top 10 checks | Opus 4.6 |
| **automator** | Legacy—now handled by browserValidator | Haiku 4.5 |
| **gherkinWriter** | BDD scenario generation | Haiku 4.5 |
| **orchestrator** | (Reserved for future orchestration) | Opus 4.6 |

## 🔄 Workflows

### 1. **qa-workflow** ⭐ (Full QA with Live Validation)
```
buildContext → [env-manager] → agents pipeline → [verification gate] → session save
```
- **Triggers**: Environment setup + health polling
- **Agents**: reviewer → [testGenerator + browserValidator] → releaseAnalyzer
- **Gate**: Validates each output before chaining
- **Output**: Session JSON + X-Ray integration
- **Duration**: ~120-180s (includes service startup)

### 2. **browser-validate** 🌐 (Playwright Testing)
```
[env-manager] → browserValidator → [verification gate] → test execution
```
- **Focus**: End-to-end browser automation
- **Output**: Playwright test results + videos
- **Retry**: Auto-retries failed tests with issues injected

### 3. **smart-select** 📋 (Test Inventory Scanning)
```
scan e2e/*.spec.ts → testSelector → match to diff → select subset
```
- **Input**: Git diff
- **Output**: Selected tests (must-run, should-run, optional)
- **Use**: CI optimization—run only relevant tests

### 4. **release-analysis** 📦 (Dependency Detection)
```
buildContext → releaseAnalyzer → cross-repo impact analysis
```
- **Input**: Changed files + imports
- **Output**: Dependency graph + risk assessment
- **Use**: Release readiness check

## 🚀 Quick Start

### Installation
```bash
git clone ssh://git@git.mediaspeech.com:17890/mint/qa-orchestrator.git
cd qa-orchestrator
npm install
cp .env.example .env  # Configure: LLM_API_KEY, JIRA_TOKEN, etc.
```

### Configuration
Edit `.env`:
```env
LLM_BASE_URL=https://llm-gateway.ai.chapsvision.com/llm-gateway
LLM_API_KEY=sk-xxx
LLM_MODEL=gpt-5.1-sweden

JIRA_BASE_URL=https://chapsvisiondev.atlassian.net
JIRA_EMAIL=your@email.com
JIRA_TOKEN=ATATT3x...

XRAY_CLIENT_ID=xxx
XRAY_CLIENT_SECRET=xxx
```

### CLI Usage

**Post-Merge Regression Testing** (test main branch)
```bash
node index.js --project target --workflow qa-workflow --message "Regression test"
```

**Pre-Merge PR Validation** (test feature branch)
```bash
node index.js --project target --workflow qa-workflow --message "Validate PR" --ticket-key TAR-1234
```

**Browser Testing Only**
```bash
node index.js --project target --workflow browser-validate --message "E2E validation"
```

**Test Selection for CI**
```bash
node index.js --project target --workflow smart-select --message "Select tests for diff"
```

### Programmatic Usage

```javascript
const { createEngine } = require('./index');

const engine = createEngine('target');

// Run single agent
const result = await engine.runAgent('reviewer', 'Review TAR-1234');

// Run full workflow
const results = await engine.runWorkflow('qa-workflow', 'Full QA for TAR-1234', {
  ticketKey: 'TAR-1234',        // Optional: checkout branch
  noLive: false,                 // Default: enable environment setup
  noGate: false,                 // Default: enable verification gate
  noLearn: false,                // Default: enable learning system
  failFast: true                 // Default: fail on first error
});
```

## 📊 Verification Gate Scoring

Each agent output is scored 0-100:

| Score | Status | Action |
|-------|--------|--------|
| **≥ 70** | ✅ PASS | Continue to next agent |
| **50-69** | ⚠️ WARN | Retry once with issues injected |
| **< 50** | ❌ FAIL | Retry twice, then continue (degraded) |

**Scoring Factors:**
- Structural completeness (required sections)
- Output length (minimum 200 chars)
- LLM quality assessment (via haiku model)
- Presence of actionable recommendations

## 🧠 Learning System

Failures are persisted to track patterns:

```
qa-sessions/learnings/{agentId}.json
[
  {
    type: "failure",
    timestamp: "2026-05-15T10:30:45Z",
    issues: ["Missing acceptance criteria", "No test cases"],
    outputSnippet: "..."
  },
  ...  // Up to 20 entries (rolling window)
]
```

When an agent runs, learnings are injected:
```
## Known Failure Patterns (learn from these)
- Issue: Missing acceptance criteria | Bad snippet: [...]
- Issue: No test cases | Bad snippet: [...]
```

This prevents repeated mistakes across sessions.

## 🔌 Integration Points

### Jira / X-Ray
- Fetch ticket description + acceptance criteria
- Import gherkin features → create test cases
- Create test execution → link results

### GitLab
- Fetch MR details + diffs
- Detect branch → checkout for testing
- Auto-create issue on failure

### Confluence
- Post session results to Confluence
- Store test reports + recommendations
- Link to Jira tickets

## 📁 Project Structure

```
tools/qa-orchestrator/
├── core/
│   ├── env-manager.js          # Service startup + health polling
│   ├── verification-gate.js    # Output validation + retry
│   ├── learning-system.js      # Failure persistence + injection
│   ├── engine.js               # Workflow orchestration
│   ├── session-manager.js      # Results tracking
│   ├── jira-client.js          # Jira + X-Ray API
│   ├── git-client.js           # Git operations
│   └── playwright-*.js         # Playwright config
├── agents/
│   └── registry.js             # 15 agents + 4 workflows
├── config/
│   ├── projects.js             # Target, Screen project config
│   └── playwright.js           # Test environment setup
├── context/
│   └── CONTEXT.md              # 1400+ lines project reference
├── docs/
│   ├── PLAYWRIGHT_IMPLEMENTATION.md
│   ├── QA_USAGE_GUIDE.md
│   └── README.md
├── index.js                    # CLI entry point
├── package.json
└── .env                        # Configuration (not in repo)
```

## 🔧 Configuration

### Health Endpoints (in config/projects.js)
```javascript
target: {
  healthEndpoints: {
    api: 'http://localhost/api/health/ready',
    frontend: 'http://localhost',
    keycloak: 'http://localhost:8080/realms/chapsmind/.well-known/openid-configuration'
  },
  testCommands: {
    e2e: 'npx playwright test',
    unit: 'cd apps/front && npm run test:unit',
    backend: 'task screen:test'
  }
}
```

### Environment Variables (Adaptive Configuration)

**🔑 Key Principle**: QA Orchestrator **adapts to your project's existing .env** instead of imposing new requirements.

**Priority (highest to lowest):**
1. Project's `.env` file (your existing configuration)
2. Project's `.env.local` (local overrides)
3. QA Orchestrator's `.env` (fallback for QA-specific variables)
4. `process.env` (system environment variables)

This means:
- ✅ If your project already has `.env`, QA Orchestrator uses it automatically
- ✅ You only need to configure QA-specific variables in the QA Orchestrator .env
- ✅ Your project's configuration takes precedence — QA Orchestrator won't override it
- ✅ Works with any project setup (monorepo, microservices, single repo)

**Minimal Required (in any .env):**
```env
# CRITICAL: LLM Gateway credentials (routes to Claude via LiteLLM)
LLM_API_KEY=sk-xxx
```

**Optional (auto-detected or configurable):**
```env
# LLM Gateway (defaults provided if not set)
LLM_BASE_URL=https://llm-gateway.ai.chapsvision.com/llm-gateway
LLM_MODEL=gpt-5.1-sweden

# VCS Integration (for MR analysis + branch detection)
QA_HUB_GITLAB_HOST=git.mediaspeech.com
QA_HUB_GITLAB_PORT=17890
QA_HUB_GITLAB_TOKEN=glpat-xxx

# Issue Tracker (Jira, GitHub Issues, GitLab Issues, etc.)
JIRA_BASE_URL=https://chapsvisiondev.atlassian.net
JIRA_EMAIL=your@email.com
JIRA_TOKEN=ATATT3x...

# X-Ray Cloud (for test case import + execution)
XRAY_CLIENT_ID=xxx
XRAY_CLIENT_SECRET=xxx

# Project Adaptation (detected automatically, can override)
API_HEALTH_ENDPOINT=http://localhost/api/health/ready
FRONTEND_URL=http://localhost
SERVICE_START_COMMAND=task up
```

## 📈 Performance

**Typical Execution Times:**
- Environment setup: 30-90s (first run includes service startup)
- Agent pipeline (without live validation): 60-80s
- Verification gate overhead: +20-30s (2 LLM calls per agent)
- Total: ~120-180s for full qa-workflow

**Optimization Tips:**
- Use `--no-live` to skip environment setup in CI
- Use `--no-gate` to skip verification (testing mode)
- Use `smart-select` workflow to reduce test execution time

## 🐛 Troubleshooting

### Services Won't Start
```bash
# Check Docker
docker ps
docker compose logs --tail=50

# Manual startup
task up
curl http://localhost/api/health/ready
```

### LLM API Errors
```bash
# Check gateway
curl https://llm-gateway.ai.chapsvision.com/health
echo $LLM_API_KEY  # Verify token is set
```

### Tests Fail in Gate
```bash
# Check learnings file
cat qa-sessions/learnings/reviewer.json

# Run without gate
node index.js --project target --workflow qa-workflow --message "Test" --no-gate
```

## 📚 Documentation

- **[ENVIRONMENT_ADAPTATION.md](./docs/ENVIRONMENT_ADAPTATION.md)** ⭐ — How QA Orchestrator adapts to your project's .env
- **[CONTEXT.md](./context/CONTEXT.md)** — 1400+ lines project reference
- **[PLAYWRIGHT_IMPLEMENTATION.md](./docs/PLAYWRIGHT_IMPLEMENTATION.md)** — Test infrastructure
- **[QA_USAGE_GUIDE.md](./docs/QA_USAGE_GUIDE.md)** — Workflows + examples
- **[CHANGELOG.md](./CHANGELOG.md)** — Version history

## 🤝 Contributing

1. Create feature branch: `git checkout -b feat/your-feature`
2. Test locally: `npm test` (if test suite exists)
3. Push and create MR: `git push origin feat/your-feature`
4. Link to Jira ticket in MR description

## 📄 License

Internal ChapsMind project. Proprietary.

---

**Questions?** Check [context/CONTEXT.md](./context/CONTEXT.md) or run:
```bash
node index.js --help
```
