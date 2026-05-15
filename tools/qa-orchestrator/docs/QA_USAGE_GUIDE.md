# QA Orchestrator — Complete Usage Guide

**Version:** 2.0.0 | **Updated:** May 13, 2026 | **Status:** Production Ready

Agentic QA testing system for ChapsMind using 11 specialized agents and 7 workflows to automate test planning, code review, bug discovery, and test execution.

---

## 🚀 Installation

### Prerequisites
- Node.js 18+ 
- Access to LLM Gateway (Claude API via LiteLLM)
- GitLab token (optional, for MR analysis)

### Setup

```bash
cd tools/qa-orchestrator

# Install dependencies
npm install

# Create .env file
cp .env.example .env

# Configure environment
export LLM_BASE_URL="https://llm-gateway.ai.chapsvision.com/llm-gateway"
export LLM_API_KEY="your-api-key"
export QA_HUB_GITLAB_HOST="git.mediaspeech.com"
export QA_HUB_GITLAB_PORT="17890"
export QA_HUB_GITLAB_TOKEN="your-gitlab-token"
```

### Verify Installation

```bash
node index.js --list-agents    # Show all 11 agents
node index.js --list-workflows # Show all 7 workflows
node index.js --help           # Show CLI help
```

---

## 🎯 Quick Start (2 Minutes)

### Basic Command

```bash
# Test a Jira ticket
node index.js test TAR-1234

# Review code only (fast, ~30s)
node index.js review TAR-1234

# Discover bugs
node index.js bug TAR-1234

# Sprint health check
node index.js sprint

# Analyze Merge Request
node index.js mr feat/TAR-1234
```

All commands run from `tools/qa-orchestrator/` directory.

---

## 🤖 The 11 Agents

### Core Agents (Heavy Lifting)

#### 1. **Orchestrator** 🎯
- **Role**: Coordinator and router
- **Model**: Claude Opus 4.6
- **Does**: Analyzes incoming requests, identifies QA work type, routes to right agents
- **Input**: Ticket or task description
- **Output**: Analysis, routing decision, blockers, go/no-go recommendation

#### 2. **Code Reviewer** 👁️
- **Role**: Code review against Acceptance Criteria
- **Model**: Claude Opus 4.6 (uses MCP for codebase access)
- **Does**: Checks if implementation matches AC, identifies gaps, flags security issues
- **Input**: Code changes, ticket AC
- **Output**: Coverage report, gaps found, recommendation (PASS/NEEDS_WORK/BLOCK)

#### 3. **Test Generator** 📝
- **Role**: Test case design
- **Model**: Claude Opus 4.6 (uses MCP)
- **Does**: Creates 20+ test cases covering AC, negative cases, edge cases, security
- **Input**: AC list, implementation details
- **Output**: Structured test plan with priority levels

#### 4. **Automator** 🤖
- **Role**: Playwright test writer
- **Model**: Claude Haiku 4.5
- **Does**: Writes executable Playwright TypeScript tests
- **Input**: Test plan from Test Generator
- **Output**: `.spec.ts` files ready to run

#### 5. **Bug Hunter** 🐛
- **Role**: Heuristic bug discovery
- **Model**: Claude Sonnet 4.6 (uses MCP)
- **Does**: Finds potential bugs using heuristics (boundaries, nulls, race conditions)
- **Input**: Code changes, architecture
- **Output**: Bug report with severity, steps to reproduce, test cases

#### 6. **Validator** ✅
- **Role**: QA completeness checker
- **Model**: Claude Haiku 4.5 (uses MCP)
- **Does**: Verifies all ACs have tests, checks Playwright syntax, calculates coverage %
- **Input**: Outputs from reviewer, testGenerator, automator
- **Output**: Coverage score, issues found, QA gate (PASS/FAIL)

### Supporting Agents

#### 7. **Scanner** 🔍
- **Role**: Tech stack analyzer
- **Model**: Claude Sonnet 4.6
- **Does**: Detects project tech stack, existing tests, risk areas
- **Input**: Changed files, project structure
- **Output**: Stack report, test infrastructure detected, risk analysis

#### 8. **MR Analyzer** 📋
- **Role**: Merge Request breakdown
- **Model**: Claude Sonnet 4.6
- **Does**: Extracts changes from MR, identifies blast radius, implicit requirements
- **Input**: MR details, diffs
- **Output**: Change summary, impact analysis, QA focus areas

#### 9. **Gherkin Writer** 🥒
- **Role**: BDD scenario generator
- **Model**: Claude Haiku 4.5
- **Does**: Writes Gherkin Feature files for X-Ray Jira
- **Input**: Test plan
- **Output**: `.feature` files with Scenario + Scenario Outline

#### 10. **Project Manager** 📊
- **Role**: Sprint health and metrics
- **Model**: Claude Haiku 4.5 (uses MCP)
- **Does**: Calculates QA coverage, identifies bottlenecks, reports metrics
- **Input**: Sprint data, ticket status
- **Output**: Sprint health report, bottlenecks, recommendations

#### 11. **Session Manager** 💾
- **Role**: Results consolidation
- **Model**: Claude Haiku 4.5 (uses MCP)
- **Does**: Summarizes QA cycle, extracts metrics, creates Confluence draft
- **Input**: Outputs from all agents
- **Output**: Session summary, TODOs, next steps

---

## 🔄 The 7 Workflows

### 1. **qa-workflow** ⭐ (Full QA Session)
**Use when**: You want the complete QA cycle on a ticket

```bash
node index.js test TAR-1234
# or
node index.js --project target --workflow qa-workflow --ticket TAR-1234
```

**Agents Chain** (with parallelization):
```
Stage 1: reviewer
     ↓
Stage 2: testGenerator
     ↓
Stage 3: automator ⚡ gherkinWriter  (PARALLEL)
     ↓
Stage 4: validator
     ↓
Stage 5: sessionManager
```

**What happens**:
1. Code Reviewer checks AC implementation
2. Test Generator creates 20+ test cases
3. **Automator & GherkinWriter run in parallel** (saves ~18s)
4. Validator ensures full coverage
5. Session Manager consolidates results

**Output**:
- Test plan in Jira
- Playwright `.spec.ts` files
- Gherkin Feature files
- Confluence draft report
- Session JSON in `qa-reports/TAR/`

**Performance**: ~78 seconds (18.8% faster thanks to parallelization)

---

### 2. **quick-review** ⚡ (Fast Code Review)
**Use when**: You just need a quick code vs AC check (~30 seconds)

```bash
node index.js review TAR-1234
```

**Agents**: reviewer only

**Output**: Quick coverage report, gaps identified, go/no-go recommendation

---

### 3. **scan-adapt** 🔍 (Stack Detection)
**Use when**: First-time setup on a new project

```bash
node index.js scan
```

**Agents**: scanner → projectManager

**Output**: 
- Detected tech stack (Vue 3, FastAPI, etc.)
- Test infrastructure identified
- Risk areas flagged
- Recommendations

---

### 4. **mr-to-tests** 📋 (MR Analysis)
**Use when**: Analyzing a Merge Request for test generation

```bash
node index.js mr feat/TAR-1234
```

**Agents**: mrAnalyzer → reviewer → testGenerator → automator

**Output**:
- What changed in the MR
- Blast radius analysis
- Test cases for those changes
- Playwright scripts

---

### 5. **bug-cycle** 🐛 (Bug Discovery)
**Use when**: Doing bug discovery on a feature

```bash
node index.js bug TAR-1234
```

**Agents**: bugHunter → testGenerator → automator → projectManager

**Output**:
- Potential bugs found (with severity)
- Test cases to catch each bug
- Playwright tests
- Metrics on bug discovery

---

### 6. **xray-sync** 🔗 (X-Ray Integration)
**Use when**: Syncing test cases to X-Ray Jira

```bash
node index.js --workflow xray-sync --ticket TAR-1234
```

**Agents**: testGenerator → gherkinWriter → projectManager

**Output**:
- Test cases ready for X-Ray
- Gherkin scenarios
- Auto-import to X-Ray if credentials available

---

### 7. **sprint-health** 📊 (Sprint Metrics)
**Use when**: Preparing sprint review or health check

```bash
node index.js sprint
```

**Agents**: projectManager → mrAnalyzer → reviewer

**Output**:
- Sprint health score (0-100)
- Test coverage %
- Risk areas
- Bottlenecks
- Recommendations

---

## 📊 Agent Models & Capabilities

| Agent | Model | MCP | Speed | Use Case |
|-------|-------|-----|-------|----------|
| Orchestrator | Opus 4.6 | No | Slow | Routing, analysis |
| Code Reviewer | Opus 4.6 | ✅ | Slow | Deep code analysis |
| Test Generator | Opus 4.6 | ✅ | Medium | Test design |
| Automator | Haiku 4.5 | No | Fast | Playwright writing |
| Bug Hunter | Sonnet 4.6 | ✅ | Medium | Bug discovery |
| Validator | Haiku 4.5 | ✅ | Fast | Validation checks |
| Scanner | Sonnet 4.6 | No | Fast | Stack detection |
| MR Analyzer | Sonnet 4.6 | No | Medium | MR breakdown |
| Gherkin Writer | Haiku 4.5 | No | Fast | Scenario writing |
| Project Manager | Haiku 4.5 | ✅ | Fast | Metrics |
| Session Manager | Haiku 4.5 | ✅ | Fast | Consolidation |

**MCP** = Uses Model Context Protocol for codebase access

---

## 💻 CLI Commands

### List Commands
```bash
node index.js --list-agents       # Show all agents
node index.js --list-workflows    # Show all workflows
node index.js --help              # Full help
```

### Short Commands (Recommended)
```bash
node index.js test TICKET         # Full QA cycle
node index.js review TICKET       # Fast review (~30s)
node index.js scan                # Stack detection
node index.js mr MR-URL           # MR analysis
node index.js sprint              # Sprint health
node index.js bug TICKET          # Bug discovery
```

### Full Commands
```bash
node index.js \
  --project target \
  --workflow qa-workflow \
  --ticket TAR-1234 \
  --message "Test new dashboard"
```

### Projects
- `target` (TAR project)
- `screen` (SCR project)

### Environment Variables
```bash
LLM_BASE_URL          # LLM gateway URL
LLM_API_KEY          # Claude API key
LLM_MODEL            # Optional: override model
QA_HUB_GITLAB_HOST   # git.mediaspeech.com
QA_HUB_GITLAB_PORT   # 17890
QA_HUB_GITLAB_TOKEN  # GitLab token
```

---

## 📁 Output Files

### Session Storage
```
qa-reports/
  ├── TAR/
  │   ├── TAR-1234-2026-05-13T10-47-03.json    # Session data
  │   └── TAR-1234.feature                     # Gherkin Feature file
  └── SCR/
      └── SCR-100-...json
```

### Session JSON Structure
```json
{
  "sessionId": "uuid-1234",
  "ticketKey": "TAR-1234",
  "projectKey": "TAR",
  "timestamp": "2026-05-13T10:47:03Z",
  "workflow": "qa-workflow",
  "execution": [
    {
      "agentId": "reviewer",
      "agentName": "Code Reviewer",
      "status": "completed",
      "output": "... (first 500 chars)"
    },
    // ... more agents
  ],
  "summary": {
    "totalAgents": 6,
    "passed": 6,
    "failed": 0,
    "duration": "78 seconds"
  }
}
```

### Jira Comments
Results are automatically commented on the Jira ticket with:
- Test plan
- Coverage score
- Recommendation (PASS/NEEDS_WORK/BLOCK)
- Link to session file

---

## ⚡ Performance Tips

### Parallelization
The `qa-workflow` now parallelizes `automator` and `gherkinWriter`:
- **Old**: Sequential execution = 96s
- **New**: automator + gherkinWriter in parallel = 78s
- **Gain**: 18 seconds saved (-18.8%)

### Model Selection
- Use **Opus 4.6** for deep analysis (code review, test design)
- Use **Sonnet 4.6** for medium complexity (MR analysis, bug hunting)
- Use **Haiku 4.5** for fast output (automation, validation)

### Caching
- First run fetches context from codebase
- Subsequent runs are faster (reuse cache)
- Cache expires after 5 minutes

---

## 🐛 Troubleshooting

### "Agent X not found"
```bash
# Verify agent is registered
node index.js --list-agents | grep agentId
```

### "Unknown workflow: Y"
```bash
# Check workflow exists
node index.js --list-workflows
```

### LLM timeout
```bash
# Increase timeout in core/engine.js callLLM()
// max_tokens: options.maxTokens || 8192,
```

### Jira comment fails
- Check JIRA_CLOUD_ID in config
- Verify Jira ticket exists
- Check permission to comment

### MCP tools unavailable
- Some agents need codebase context
- MCP tools require git repo access
- Check git credentials

---

## 📚 File Structure

```
tools/qa-orchestrator/
├── index.js                    # CLI entry point
├── agents/
│   └── registry.js            # Agent definitions (11 agents)
├── core/
│   ├── engine.js              # Execution engine (stages + parallelization)
│   ├── session-manager.js     # Session persistence
│   ├── jira-client.js         # Jira integration
│   ├── git-client.js          # Git operations
│   └── xray-cli.js            # X-Ray test import
├── config/
│   └── projects.js            # Project definitions
└── docs/
    ├── QA_USAGE_GUIDE.md      # This file
    └── API.md                 # API documentation
```

---

## 🔒 Security

### API Keys
- Store in `.env` (never commit)
- Use environment variables
- Rotate periodically

### Test Data
- Never use production data
- Use fixtures and mocks
- Clean up after tests

### Git Access
- Use deploy keys for CI/CD
- Limit token scopes
- Revoke unused tokens

---

## 📖 Examples

### Example 1: Full QA Cycle
```bash
cd tools/qa-orchestrator
node index.js test TAR-1234
```

Output:
1. Code Reviewer analyzes implementation
2. Test Generator creates 25 test cases
3. Automator writes Playwright tests in parallel with GherkinWriter
4. Validator confirms 100% AC coverage
5. SessionManager saves results
6. Jira comment posted with results

---

### Example 2: Bug Discovery
```bash
node index.js bug TAR-5000
```

Output:
1. Bug Hunter identifies potential bugs (race conditions, null handling, etc.)
2. Test Generator creates test cases for each bug
3. Automator writes Playwright tests
4. Project Manager reports metrics

---

### Example 3: MR Analysis
```bash
node index.js mr feat/TAR-2000
```

Output:
1. MR Analyzer extracts files changed
2. Code Reviewer checks against AC
3. Test Generator creates tests
4. Automator writes scripts

---

## 🤝 Contributing

To add a new agent:
1. Define in `agents/registry.js`
2. Add to workflow in WORKFLOWS
3. Test with `node index.js --list-agents`

To add a new workflow:
1. Define agents chain in WORKFLOWS
2. Test with `node index.js --list-workflows`
3. Test execution: `node index.js --workflow my-workflow --message "test"`

---

## 📞 Support

**Documentation**: See `/docs/` directory
**Issues**: Check troubleshooting section above
**Questions**: Reference the agents and workflows sections

---

## 📝 Changelog

### v2.0.0 (May 13, 2026)
- ✅ Added parallelization in qa-workflow (automator + gherkinWriter)
- ✅ Updated for tools/qa-orchestrator directory structure
- ✅ Documented all 11 agents with roles and models
- ✅ Explained 7 workflows and when to use each
- ✅ Added performance optimizations
- ✅ Updated installation and setup

### v1.3.0 (April 5, 2026)
- ✅ Initial QA Orchestrator Hub version
- ✅ 11 agents + 7 workflows
- ✅ Session management

---

**Last Updated**: May 13, 2026  
**Maintained By**: QA Orchestrator Team  
**Status**: Production Ready
