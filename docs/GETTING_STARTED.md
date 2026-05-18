# Getting Started with QA Orchestrator

Complete beginner-friendly guide to QA Orchestrator.

## What Is QA Orchestrator?

QA Orchestrator is an **AI-powered QA system** that automatically:

1. **Analyzes** your code changes
2. **Generates** test cases
3. **Detects** bugs
4. **Validates** output quality
5. **Learns** from failures
6. **Reports** comprehensive results

All in **3 minutes** instead of 30 minutes, and **85% cheaper** than traditional sequential testing.

## How Does It Work?

### Simple Version

```
You push code → QA Orchestrator analyzes it → You get QA report
                   ↓
          15 AI agents work together
          in 3 parallel tiers
```

### Detailed Version

```
INPUT: Code change (pull request / ticket)
  ↓
TIER 1 (10 seconds, $0.007): Fast baseline
  • Check basic structure
  • Scan test inventory
  • Generate BDD scenarios
  ↓
TIER 2 (90 seconds, $0.25): Smart analysis (7 agents in parallel)
  • Find bugs
  • Generate browser tests
  • Check accessibility
  • Measure performance
  • Validate test data
  • Check automation feasibility
  • More...
  ↓
TIER 3 (variable, ~$0.10): Only if needed (smart conditional)
  • Deep code review (if bugs found)
  • Generate missing tests (if gaps detected)
  • Security analysis (if suspicious patterns)
  • Advanced analysis...
  ↓
OUTPUT: Complete QA report with test cases, bugs, coverage analysis
```

## Installation (5 minutes)

### Prerequisites

- Node.js 16+ installed
- A code repository (GitHub, GitLab, Bitbucket, etc.)
- Test framework (Playwright, Cypress, Jest, etc.) - optional but recommended

### Step 1: Clone

```bash
git clone https://github.com/secharif-chaps/qa-orchestrator-public.git
cd qa-orchestrator-public
```

### Step 2: Install Dependencies

```bash
npm install
```

### Step 3: Configure Credentials

Choose your issue tracker and set ONE environment variable:

**For Jira:**
```bash
export JIRA_API_TOKEN="jira_xxxx"
export JIRA_HOST="https://your-instance.atlassian.net"
```

**For Linear:**
```bash
export LINEAR_API_KEY="lin_xxxx"
```

**For GitHub Issues:**
```bash
export GITHUB_TOKEN="ghp_xxxx"
```

**For Azure DevOps:**
```bash
export AZURE_DEVOPS_TOKEN="xxxx"
export SYSTEM_COLLECTIONURI="https://dev.azure.com/your-org"
```

**For YouTrack:**
```bash
export YOUTRACK_TOKEN="xxxx"
export YOUTRACK_URL="https://your-instance.youtrack.cloud"
```

### Step 4: Verify Installation

```bash
# Should show list of available agents
npx qa-orchestrator --list-agents

# Should show available workflows
npx qa-orchestrator --list-workflows
```

✅ You're ready!

## Your First Test (3 minutes)

### Run a Test

```bash
# Basic usage
npx qa-orchestrator test YOUR-TICKET-ID

# Example
npx qa-orchestrator test MYKEY-1234
```

The system automatically:
1. ✅ Detects your Git provider (GitHub, GitLab, Bitbucket)
2. ✅ Finds your test framework (Playwright, Cypress, Jest)
3. ✅ Locates issue tracker
4. ✅ Analyzes your code
5. ✅ Runs 15 AI agents in parallel
6. ✅ Generates comprehensive report

### Expected Output

```
🔍 Auto-detecting environment...

📊 Detection Results:
  VCS: ✅ github
       https://github.com/acme/project.git
  Test Framework: ✅ playwright
       Available: playwright
  Issue Tracker: ✅ jira
  CI/CD: ✅ github-actions
  Environment: my-project
       Base URL: http://localhost
       Test dirs: e2e, tests

✅ Environment ready for testing

🚀 Running QA Workflow...

⏱️  Execution timeline:
  • Tier 1 (baseline):      10s ✅
  • Tier 2 (parallel):      90s ✅
  • Tier 3 (conditional):   skip (not needed)

📊 Results:
  ✅ Code review passed
  ✅ 12 new test cases generated
  ✅ 3 potential bugs found
  ✅ Accessibility: WCAG 2.1 AA compliant
  ✅ Performance: Acceptable
  ✅ Test coverage: 92%

📁 Full report saved to: qa-sessions/MYKEY-1234-2026-05-18.json
```

## Understanding the Results

QA Orchestrator produces a comprehensive report with:

### 1. Code Review
- Does it meet acceptance criteria?
- Are there coding issues?
- Architecture recommendations

### 2. Test Generation
- Recommended test cases
- Generated Gherkin scenarios
- Playwright test scripts

### 3. Bug Detection
- Potential bugs found
- Severity level (Critical/High/Medium/Low)
- Reproduction steps

### 4. Quality Metrics
- Code complexity
- Test coverage estimate
- Performance analysis
- Accessibility compliance (A11y)

### 5. Recommendations
- What to test manually
- Automation priorities
- Security concerns
- Next steps

## Common Commands

### Test Your Code

```bash
# Standard test (balanced approach)
npx qa-orchestrator test MYKEY-1234

# Fast feedback (during development)
npx qa-orchestrator test MYKEY-1234 --sampling quick

# Thorough analysis (before release)
npx qa-orchestrator test MYKEY-1234 --sampling deep

# Skip caching (always run fresh)
npx qa-orchestrator test MYKEY-1234 --no-cache

# Show available agents
npx qa-orchestrator --list-agents

# Show all workflows
npx qa-orchestrator --list-workflows

# Show agents by tier
npx qa-orchestrator --list-tiers
```

## Sampling Modes Explained

Choose the right depth for your situation:

### 🟢 QUICK (100 seconds, $0.27)
- **Best for:** Local development, quick feedback
- **Includes:** Fast agents only, minimal analysis
- **Trade-off:** Less thorough, but instant feedback
- **When to use:** During active development, rapid iteration

```bash
npx qa-orchestrator test MYKEY-1234 --sampling quick
```

### 🟡 FULL (220 seconds, $0.37) — DEFAULT
- **Best for:** Pull request reviews, CI/CD pipelines
- **Includes:** All agents, smart conditional logic
- **Trade-off:** Balanced speed and thoroughness
- **When to use:** Code review before merge

```bash
npx qa-orchestrator test MYKEY-1234 --sampling full
```

### 🔴 DEEP (300 seconds, $0.60)
- **Best for:** Release validation, critical paths
- **Includes:** All agents, all analysis, no conditions skipped
- **Trade-off:** Slowest but most thorough
- **When to use:** Before deploying to production

```bash
npx qa-orchestrator test MYKEY-1234 --sampling deep
```

## How Much Does It Cost?

### Per Test

| Mode | Time | Cost | Notes |
|------|------|------|-------|
| Quick | 100s | $0.27 | Dev feedback |
| Full | 220s | $0.37 | Standard (recommended) |
| Deep | 300s | $0.60 | Release validation |

### Monthly (100 tests/month example)

| Mode | Monthly | Savings vs Traditional |
|------|---------|------------------------|
| Quick | $27 | 85% cheaper |
| Full | $37 | 85% cheaper |
| Deep | $60 | 85% cheaper |
| Traditional | $180 | (baseline) |

## Caching — Free Results

Once you test a ticket, identical tests return results instantly:

```bash
# First run (full analysis)
npx qa-orchestrator test MYKEY-1234
# → 3 minutes, $0.37

# Second run (same code, same ticket)
npx qa-orchestrator test MYKEY-1234
# → 100ms, $0.00 ⚡ FREE!
```

Cache is valid for 1 hour per ticket/branch.

Disable with `--no-cache` if you want fresh analysis.

## Supported Tech Stacks

### Version Control

✅ GitHub  
✅ GitLab  
✅ Bitbucket  
✅ Gitee  
✅ Generic Git  

### Test Frameworks

✅ Playwright (recommended)  
✅ Cypress  
✅ Selenium  
✅ Puppeteer  
✅ WebdriverIO  
✅ Jest  
✅ Vitest  

### Issue Trackers

✅ Jira  
✅ Linear  
✅ GitHub Issues  
✅ Azure DevOps  
✅ YouTrack  

### CI/CD

✅ GitHub Actions  
✅ GitLab CI  
✅ Jenkins  
✅ CircleCI  
✅ Travis CI  
✅ Bitbucket Pipelines  

### Languages/Frameworks

✅ TypeScript / JavaScript  
✅ Python  
✅ Java  
✅ Go  
✅ Rust  
✅ PHP  
✅ And more...  

Everything is auto-detected!

## Troubleshooting

### "Command not found: npx"

Make sure Node.js is installed:
```bash
node --version  # Should be 16+
npm --version   # Should be 8+
```

If not installed, download from https://nodejs.org

### "No issue tracker detected"

Set the environment variable for your tracker:
```bash
export JIRA_API_TOKEN="your-token"
# or
export LINEAR_API_KEY="your-key"
# or
export GITHUB_TOKEN="your-token"
```

Then try again:
```bash
npx qa-orchestrator test MYKEY-1234
```

### "No test framework detected"

Install a test framework in your project:
```bash
npm install --save-dev playwright
# or
npm install --save-dev cypress
# or
npm install --save-dev jest
```

### "Invalid credentials"

Verify your token has the correct permissions:

**Jira:**
```bash
curl -H "Authorization: Bearer $JIRA_API_TOKEN" \
  $JIRA_HOST/rest/api/3/myself
# Should return 200 OK with your user info
```

**Linear:**
```bash
curl -H "Authorization: Bearer $LINEAR_API_KEY" \
  https://api.linear.app/graphql
# Should return 200 OK
```

**GitHub:**
```bash
curl -H "Authorization: token $GITHUB_TOKEN" \
  https://api.github.com/user
# Should return 200 OK with your user info
```

### Still having issues?

1. Check [AUTO-DETECTION.md](./AUTO-DETECTION.md) for zero-config setup
2. Review [ARCHITECTURE.md](../ARCHITECTURE.md) for technical details
3. Check environment variables are set: `env | grep -i jira` (or linear/github)

## Next Steps

1. ✅ **Installation complete** — You're done!
2. **Learn more:** Read specific agent docs
3. **Customize:** Set up [custom integrations](../config/integrations.example.js)
4. **Optimize:** Review [OPTIMIZATION_GUIDE.md](./OPTIMIZATION_GUIDE.md)
5. **Extend:** Build [custom agents](../ARCHITECTURE.md)

## Examples by Scenario

### Scenario 1: Quick Local Testing

```bash
# During development, you want fast feedback
npx qa-orchestrator test MYKEY-1234 --sampling quick

# Output in 100 seconds
# - Does it break anything?
# - Basic test suggestions
# - Quick code review
```

### Scenario 2: Pull Request Review

```bash
# Before merge, full QA validation
npx qa-orchestrator test MYKEY-1234 --sampling full

# Output in 220 seconds
# - Complete test suite
# - Bug detection
# - Code review
# - Coverage analysis
# - A11y compliance
```

### Scenario 3: Pre-Release Testing

```bash
# Before production deployment, thorough analysis
npx qa-orchestrator test MYKEY-1234 --sampling deep

# Output in 300 seconds
# - All agents, all analysis
# - Multi-repo impact analysis
# - Security review
# - Performance analysis
# - Release readiness
```

## Key Concepts

### What are "Agents"?

Agents are specialized AI experts, each focusing on different aspects:
- Code reviewer
- Bug hunter
- Test generator
- Browser validator
- Accessibility auditor
- And 10 more...

They work together to provide comprehensive QA.

### What is the "3-Tier Model"?

Three levels of analysis, progressively deeper:

1. **Tier 1:** Fast baseline (10s, cheap)
2. **Tier 2:** Smart analysis (90s, 7 agents in parallel)
3. **Tier 3:** Advanced analysis (conditional, only when needed)

This approach = 85% cost reduction + 10x speedup.

### What is "Parallel Execution"?

Instead of running tests one-by-one sequentially:

```
Sequential (slow):
Agent 1 (30s) → Agent 2 (30s) → Agent 3 (30s) → ... = 240 seconds total

Parallel (fast):
Agent 1 (30s)
Agent 2 (30s) ← All at the same time
Agent 3 (30s) → = 30 seconds total
```

QA Orchestrator runs 7 agents simultaneously in Tier 2.

### What is "Conditional Logic"?

Smart decisions about when to run expensive operations:

```
IF Tier 2 found critical bugs
  THEN run deep code reviewer (Opus)
  ELSE skip (save money)

IF test coverage gaps detected
  THEN generate missing tests
  ELSE skip (already comprehensive)

IF suspicious security patterns
  THEN run security analyzer
  ELSE skip (no concerns)
```

This prevents wasting money on unnecessary deep analysis.

## Performance Comparison

### Without Orchestrator (Sequential)

```
Manual code review:        20 min
Manual test selection:     10 min
Running tests:             30 min
Analyzing results:         15 min
─────────────────────────────────
Total per PR:              75 min
Cost per PR:               $1.80
```

### With QA Orchestrator (Parallel)

```
Automated analysis:        3 min (all parallel)
Automated tests:           included
Automated review:          included
─────────────────────────────────
Total per PR:              3 min
Cost per PR:               $0.37
```

**Savings: 72 minutes + 80% cost reduction per PR!**

## Support & Help

- **Documentation:** See [docs/](../docs) folder
- **Examples:** See [examples/](../examples) folder
- **Issues:** Open an issue on GitHub
- **Questions:** Check the troubleshooting section above

## What's Next?

Once you're comfortable with the basics:

1. **Integrate with CI/CD** — Run tests on every PR automatically
2. **Customize agents** — Tailor prompts for your project
3. **Build adapters** — Support your specific tools
4. **Monitor metrics** — Track testing efficiency over time

See [ARCHITECTURE.md](../ARCHITECTURE.md) for advanced topics.

---

**Questions?** Check [AUTO-DETECTION.md](./AUTO-DETECTION.md) for zero-config troubleshooting.
