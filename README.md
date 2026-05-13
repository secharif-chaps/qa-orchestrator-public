# QA Orchestrator — Multi-Agent AI Platform for QA Testing

**Version:** 1.3.0 | **Status:** Production Ready ✅ | **Date:** April 5, 2026

A complete, structured QA testing system powered by 11 AI agents with automatic test execution, X-Ray integration, and comprehensive documentation for all QA teams.

## 🎯 What's New in v1.3.0

✅ **Automatic Branch Detection** — Feature branches auto-detected by ticket key  
✅ **Real Test Execution** — Pytest (backend) + Playwright (E2E) integrated  
✅ **Generic Documentation** — QA Usage Guide works for all projects  
✅ **X-Ray Auto-Linking** — Test results auto-linked to Jira X-Ray  
✅ **Session Management** — Complete QA audit trail in JSON  
✅ **Test Report Integration** — HTML + JUnit reports auto-generated  

## 💻 11 AI Agents

| Agent | Model | Purpose |
|-------|-------|---------|
| Orchestrator | Opus | Routes tasks, coordinates workflows |
| Scanner | Sonnet | Scans repo, detects stack |
| MR Analyzer | Sonnet | Analyzes GitLab MRs |
| Code Reviewer | Opus | Compares code vs AC |
| Bug Hunter | Sonnet | Creates bug tickets + Gherkin AC |
| Test Generator | Opus | Creates X-Ray tests + Gherkin |
| Automator | Sonnet | Writes Playwright tests |
| Validator | Sonnet | Validates with feedback loop |
| Project Manager | Sonnet | Sprint health & metrics |
| **SessionManager** | Sonnet | **NEW: Session state & persistence** |
| **GherkinWriter** | Sonnet | **NEW: Automatic Gherkin generation** |

## 🔄 7 Workflows

| Workflow | Agents | Purpose |
|----------|--------|---------|
| **qa-workflow** (NEW) | 7 agents | **Main: structured 9-phase QA** |
| scan-adapt | 2 agents | First discovery on new project |
| mr-to-tests | 4 agents | MR analysis → test generation |
| full-ticket | 4 agents | [DEPRECATED] Use qa-workflow |
| bug-cycle | 4 agents | Bug discovery cycle |
| xray-sync | 2 agents | Test library maintenance |
| sprint-health | 3 agents | Sprint review preparation |

## 🚀 Quick Start
```bash
# Main QA workflow (recommended)
node index.js --project target --workflow qa-workflow \
  --message "Test TAR-1332" --ticket TAR-1332
```

**What happens:**
1. Optional peer-review of AC
2. Test case generation
3. Automated test writing (Playwright)
4. Gherkin scenarios auto-generated
5. [You test on staging + post findings to Jira]
6. Validator reads findings
7. SessionManager finalizes session → JSON + Confluence + Jira

## ✨ Key Features

✅ **Structured 9-Phase QA Methodology** — TODO → peer-review → exploratory → test cases → automation → validation → heuristics  
✅ **Session Persistence** — JSON + Confluence auto-drafts  
✅ **Gherkin Everywhere** — Automatic generation, no manual work  
✅ **Exploratory ↔ Automated Loop** — User tests feed findings back to agents  
✅ **Risk-Based Heuristics** — Learn from bugs, prevent regressions  
✅ **Project-Agnostic** — Works for TARGET, SCREEN, any project  

## 📚 Documentation

### 🚀 Start Here
**[📖 QA_USAGE_GUIDE.md](docs/QA_USAGE_GUIDE.md)** — Complete guide for all QA teams (v1.3.0)

Covers:
- 5-minute quick start
- All workflows (qa-workflow, scan-adapt, mr-to-tests, bug-cycle, xray-sync, sprint-health)
- Manual test execution with auto-branch detection
- Pytest + Playwright integration
- X-Ray Jira integration
- Configuration guide
- Session management
- Troubleshooting (9 solutions)
- Performance optimization
- Security best practices

### 📋 Example Files
- `examples/workflows.yml.example` — Configuration template for your project
- `examples/qa-test-ticket.sh` — Manual test execution script (copy to scripts/)

## 🔧 Installation
```bash
npm install

# Create .env
echo "QA_HUB_ANTHROPIC_KEY=sk-ant-..." >> .env
echo "QA_HUB_GITLAB_TOKEN=glpat-..." >> .env

# Test
node index.js --list-agents     # Should show 11
node index.js --list-workflows  # Should show 7
```

## 📋 What's Changed

### New Files
- `core/session-manager.js` — Session persistence module
- `docs/` folder — Complete documentation

### Modified Files
- `agents/registry.js` — Added 2 new agents, new workflow
- `core/engine.js` — SessionManager integration
- `README.md` — Updated with new features

### Backward Compatible
✅ Old workflows still work (`full-ticket` marked deprecated)  
✅ No breaking changes  
✅ All existing agents compatible  

## 🔗 Resources

- **GitLab:** https://git.mediaspeech.com/mint/qa-orchestrator
- **Confluence:** https://chapsvisiondev.atlassian.net/wiki/spaces/QCD/
- **Documentation:** See `/docs/` folder

## 📞 Support

See `/docs/TROUBLESHOOTING.md` for common issues and solutions.

---

**Status:** Production Ready ✅  
**Version:** 1.1.0  
**Last Updated:** April 2, 2025
