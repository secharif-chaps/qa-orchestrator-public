# Changelog

## [3.0.0] - 2026-05-15

### 🚀 Major Release: Live Validation System

#### Architecture Transformation
- **Environment Manager** (NEW) — Auto-start Docker services + health polling before agent execution
  - Supports optional PR branch checkout (git stash + fetch + checkout with 8 patterns)
  - Flexible for both post-merge (test main) and pre-merge (test PR) workflows
  - Tests against RUNNING code at http://localhost (true live validation)
  - Health endpoint polling with configurable timeout (default 120s)

- **Verification Gate** (NEW) — LLM-based output validation before chaining
  - Scores each agent output 0-100 based on structural completeness
  - Auto-retry (max 2) with issues injected when score < 70
  - Records failures for learning system
  - Prevents bad output from propagating through agent chain

- **Learning System** (NEW) — Self-improving agent framework
  - Persists failures to qa-sessions/learnings/{agentId}.json
  - Rolling window of 20 entries per agent (prevents bloat)
  - Injects "Known Failure Patterns" into future agent prompts
  - Prevents repeated mistakes across sessions

#### Agent Tier Organization
- **Daily Use Tier** (4 agents) — reviewer, testGenerator, testSelector, bugHunter
- **Live Validation Tier** (3 agents) — browserValidator, manualValidator, releaseAnalyzer
- **Advanced Tier** (8 agents) — promptTuner, dataValidator, accessibility/performance/security auditors, etc.
- Total: 15 agents (up from 11 in v2.0.0)

#### Key Fixes
- **Session Persistence** — Fixed critical bug where saveToJson() never wrote to disk
  - Sessions now properly persisted to qa-sessions/{projectKey}/{sessionId}.json
  - Full audit trail of agent executions + outputs

#### New Workflows
- **qa-workflow** — Full QA with live environment + verification gate
- **browser-validate** — Playwright test generation + execution with live validation
- **smart-select** — Test inventory scanning + matching to diffs (CI optimization)
- **release-analysis** — Cross-repo dependency detection + impact analysis

#### Documentation Enhancements
- **CONTEXT.md** (NEW) — 1400+ lines of comprehensive project reference
  - Project structure, tech stack, deployment, test users, health endpoints
  - Complete Keycloak integration guide
  - Database migration procedures + Kubernetes access
  - i18n key naming conventions + translation guidelines

- **README.md** — Complete rewrite for v3.0.0
  - New architecture diagrams showing 3 pillars
  - Usage examples for post-merge and pre-merge testing
  - Verification gate scoring system explained
  - Learning system + failure patterns documented
  - Performance optimization tips

- **PLAYWRIGHT_IMPLEMENTATION.md** — Test infrastructure details
- **QA_USAGE_GUIDE.md** — Workflow examples + troubleshooting

#### Configuration Enhancements
- **projects.js** enriched with:
  - healthEndpoints (api, frontend, keycloak)
  - testCommands (e2e, unit, backend)
  - dockerServices + dockerProject
  - startCommand + localPlaywrightDir

- **.env** pre-configured with:
  - LLM Gateway (gpt-5.1-sweden model)
  - GitLab integration (git.mediaspeech.com)
  - Jira API + X-Ray Cloud credentials

#### Backward Compatibility
✅ All v2.0.0 workflows still work  
✅ Verification gate is optional (--no-gate flag)  
✅ Environment manager is optional (--no-live flag)  
✅ Learning system is optional (--no-learn flag)  
⚠️ Breaking: Agent count changed from 11 to 15 (agent IDs unchanged)

---

## [2.0.0] - 2026-05-13

### Added
- **Auto-Branch Detection** — qa-test-ticket.sh auto-detects feature branches by ticket key
- **Test Execution Integration** — Pytest (backend) + Playwright (E2E) tests execute automatically
- **Generic QA Usage Guide** — Comprehensive documentation works for all projects (not project-specific)
- **Example Files** — workflows.yml.example and qa-test-ticket.sh template for consumers
- **X-Ray Auto-Linking** — Test results auto-linked to Jira X-Ray tickets
- **Session Persistence** — Complete QA audit trail stored in JSON format
- **Test Report Generation** — HTML + JUnit reports auto-generated for all test runs

### Changed
- README: Updated to reflect v1.3.0 features
- Documentation: Now project-agnostic (MON-PROJET examples instead of hardcoded projects)
- Test execution: Now runs actual tests instead of plan-only mode
- Session management: Enhanced persistence and retrieval

### Improvements
- Branch detection handles multiple naming patterns (feat/*, feature/*)
- Better logging of test execution progress
- Comprehensive troubleshooting guide (9 common issues + solutions)
- Performance optimization tips included
- Security best practices documented

### Backward Compatibility
✅ All v1.1.0 features still work  
✅ No breaking changes  
✅ Existing workflows compatible  

---

## [1.1.0] - 2025-04-02

### Added
- SessionManager agent (◎) for session state tracking and persistence
- GherkinWriter agent (⬡) for automatic Gherkin scenario generation
- New workflow `qa-workflow` (main, structured QA methodology)
- Complete QA Workflow Guide (9-phase methodology)
- Session persistence module with JSON + Confluence support
- TODO list tracking (persistent across sessions)
- Risk-based heuristics evolution system
- 6 real-world usage examples
- Complete troubleshooting guide

### Changed
- BugHunter: Auto-generates Gherkin AC in bug tickets
- TestGenerator: Enhanced with Gherkin metadata
- Automator: Added assertions guidance
- Validator: Enhanced to read exploratory findings from Jira
- README: Updated with new features

### Backward Compatibility
- Old workflows still work (full-ticket marked deprecated)
- No breaking changes
- All existing agents remain compatible
- Smooth migration path provided

---

**Version:** 1.1.0 | **Date:** April 2, 2025 | **Status:** Production Ready ✅
