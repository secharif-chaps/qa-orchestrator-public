# Changelog

## [1.3.0] - 2026-04-05

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
