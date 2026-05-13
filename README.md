# QA Orchestrator — Agentic QA Testing System

**Version:** 2.0.0 | **Status:** Production Ready ✅ | **Updated:** May 13, 2026

Agentic QA testing system for ChapsMind using 11 specialized agents and 7 workflows to automate test planning, code review, bug discovery, and test execution.

## 🎯 What's New in v2.0.0

✅ **Parallelization** — automator + gherkinWriter run in parallel (18 seconds faster)  
✅ **Stage-Based Execution** — Agents organized in stages with Promise.all() support  
✅ **Updated Documentation** — Complete usage guide with all 11 agents and 7 workflows  
✅ **Aligned Across Platforms** — Local, GitLab, GitHub, and Confluence all synchronized  
✅ **Performance Optimized** — 96s → 78s execution time (-18.8%)  
✅ **Production Ready** — Full test coverage and error handling  

## 🤖 The 11 Agents

| Agent | Role | Model | Speed |
|-------|------|-------|-------|
| **Orchestrator** 🎯 | Coordinator & router | Opus 4.6 | Slow |
| **Code Reviewer** 👁️ | AC coverage check | Opus 4.6 | Slow |
| **Test Generator** 📝 | Test case design | Opus 4.6 | Medium |
| **Automator** 🤖 | Playwright writer | Haiku 4.5 | Fast |
| **Bug Hunter** 🐛 | Bug discovery | Sonnet 4.6 | Medium |
| **Validator** ✅ | Coverage checker | Haiku 4.5 | Fast |
| **Scanner** 🔍 | Stack detection | Sonnet 4.6 | Fast |
| **MR Analyzer** 📋 | MR breakdown | Sonnet 4.6 | Medium |
| **Gherkin Writer** 🥒 | BDD scenarios | Haiku 4.5 | Fast |
| **Project Manager** 📊 | Sprint metrics | Haiku 4.5 | Fast |
| **Session Manager** 💾 | Results consolidation | Haiku 4.5 | Fast |

## 🔄 The 7 Workflows

### 1. **qa-workflow** ⭐ (Full QA Session)
Complete QA cycle: code review → test cases → (Playwright + Gherkin in **parallel**) → validation → summary  
**Performance**: ~78 seconds (18.8% faster with parallelization)

### 2. **quick-review** ⚡ (Fast Code Review)
Quick AC coverage check (~30 seconds)

### 3. **scan-adapt** 🔍 (Stack Detection)
Detect tech stack and recommend QA approach

### 4. **mr-to-tests** 📋 (MR Analysis)
Analyze Merge Request and generate tests

### 5. **bug-cycle** 🐛 (Bug Discovery)
Find potential bugs and create test cases

### 6. **xray-sync** 🔗 (X-Ray Integration)
Sync test cases to X-Ray Jira

### 7. **sprint-health** 📊 (Sprint Metrics)
Sprint health check and metrics

## 🚀 Quick Start

```bash
# Full QA cycle (recommended)
node index.js test TAR-1234

# Or other commands
node index.js review TAR-1234     # Fast review (~30s)
node index.js scan                # Stack detection
node index.js mr feat/TAR-1234    # MR analysis
node index.js sprint              # Sprint health
node index.js bug TAR-1234        # Bug discovery
```

## ✨ Key Features

✅ **Parallelization** — automator + gherkinWriter run simultaneously  
✅ **18 Seconds Faster** — 96s → 78s execution time  
✅ **Session Persistence** — JSON + audit trail  
✅ **Gherkin BDD** — Automatic Gherkin scenario generation  
✅ **Jira Integration** — Auto-comments with results  
✅ **X-Ray Sync** — Test run creation and linking  
✅ **Project-Agnostic** — Works for TARGET, SCREEN, any project  

## 📚 Documentation

**[📖 QA_USAGE_GUIDE.md](./docs/QA_USAGE_GUIDE.md)** — Complete usage guide with:
- Installation & setup
- All 11 agents explained
- All 7 workflows detailed
- CLI commands
- Performance tips
- Troubleshooting
- Examples

## 🔧 Installation

```bash
cd tools/qa-orchestrator
npm install

# Configure environment
export LLM_BASE_URL="https://llm-gateway.ai.chapsvision.com/llm-gateway"
export LLM_API_KEY="your-api-key"
export QA_HUB_GITLAB_HOST="git.mediaspeech.com"
export QA_HUB_GITLAB_PORT="17890"
export QA_HUB_GITLAB_TOKEN="your-gitlab-token"

# Verify
node index.js --list-agents     # Should show 11
node index.js --list-workflows  # Should show 7
```

## 📊 Performance

| Metric | Before | After | Gain |
|--------|--------|-------|------|
| qa-workflow time | 96s | 78s | -18.8% |
| Parallelization | None | automator + gherkinWriter | 18s saved |

## 📝 Recent Changes (v2.0.0)

✅ Parallelization in qa-workflow  
✅ Stage-based execution with Promise.all()  
✅ Updated documentation (local + remote)  
✅ Synced GitLab, GitHub, Confluence docs  
✅ Performance optimizations  
✅ Production ready  

## 🔗 Resources

- **Local**: `tools/qa-orchestrator/docs/QA_USAGE_GUIDE.md`
- **GitLab**: https://git.mediaspeech.com/mint/qa-orchestrator
- **GitHub**: https://github.com/secharif-chaps/qa-orchestrator-public
- **Confluence**: https://chapsvisiondev.atlassian.net/wiki/spaces/QCD/pages/731349236/

---

**Status:** Production Ready ✅  
**Version:** 2.0.0  
**Last Updated:** May 13, 2026
