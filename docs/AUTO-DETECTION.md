# Auto-Detection — Zero-Config Setup

QA Orchestrator v3.0.0+ includes an automatic environment detection system that requires **zero manual configuration** in most cases.

## Quick Start

```bash
# 1. Clone and install
git clone https://github.com/your-org/your-project.git
cd your-project
npm install

# 2. Set ONE environment variable (for credentials)
export JIRA_API_TOKEN="your-token-here"

# 3. Run — everything else auto-detects
npx qa-orchestrator test TAR-1234
```

That's it! The system automatically:
- Detects your git provider (GitHub, GitLab, Bitbucket, etc.)
- Finds your test framework (Playwright, Cypress, etc.)
- Locates issue tracker credentials (Jira, Linear, etc.)
- Identifies CI/CD platform (GitHub Actions, GitLab CI, Jenkins, etc.)
- Adapts test execution to your setup

## What Gets Auto-Detected

### 1. Version Control System (VCS)

Auto-detects by reading `.git/config` → `remote.origin.url`:

| Provider | Detection Method | Supported |
|----------|------------------|-----------|
| **GitHub** | URL contains `github.com` | ✅ Yes |
| **GitLab** | URL contains `gitlab.com` or `gitlab` | ✅ Yes |
| **Bitbucket** | URL contains `bitbucket.org` or `bitbucket` | ✅ Yes |
| **Gitee** | URL contains `gitee.com` | ✅ Yes |
| **Generic Git** | Any other git remote | ✅ Yes (limited features) |

**Example:**
```bash
$ git config --get remote.origin.url
# https://github.com/acme-corp/qa-tests.git → Detected: GitHub
# git@gitlab.com:team/project.git → Detected: GitLab
# https://bitbucket.org/workspace/repo.git → Detected: Bitbucket
```

### 2. Test Framework

Auto-detects by scanning `package.json` dependencies:

| Framework | Detection | Supported |
|-----------|-----------|-----------|
| **Playwright** | `dependencies.playwright` exists | ✅ Yes (Primary) |
| **Cypress** | `dependencies.cypress` exists | ✅ Yes |
| **Selenium** | `dependencies.selenium` exists | ✅ Yes |
| **Puppeteer** | `dependencies.puppeteer` exists | ✅ Yes |
| **WebdriverIO** | `dependencies.webdriverio` exists | ✅ Yes |
| **Jest** | `devDependencies.jest` exists | ✅ Yes (Unit tests) |
| **Vitest** | `devDependencies.vitest` exists | ✅ Yes (Unit tests) |

**Priority:** Browser testing frameworks (Playwright, Cypress, etc.) take precedence over unit testing frameworks.

**Example:**
```json
{
  "dependencies": {
    "playwright": "^1.45.0"
  }
}
→ Detected: Playwright
```

### 3. Issue Tracker

Auto-detects by checking environment variables:

| Tracker | Environment Variable | Detected |
|---------|----------------------|----------|
| **Jira** | `JIRA_API_TOKEN` or `JIRA_HOST` | ✅ Yes |
| **Linear** | `LINEAR_API_KEY` or `LINEAR_TOKEN` | ✅ Yes |
| **GitHub Issues** | `GITHUB_TOKEN` | ✅ Yes |
| **Azure DevOps** | `AZURE_DEVOPS_TOKEN` or `SYSTEM_COLLECTIONURI` | ✅ Yes |
| **YouTrack** | `YOUTRACK_TOKEN` or `YOUTRACK_URL` | ✅ Yes |

**Setup:**
```bash
# For Jira
export JIRA_API_TOKEN="your-api-token"
export JIRA_HOST="https://your-instance.atlassian.net"

# For Linear
export LINEAR_API_KEY="your-api-key"

# For GitHub
export GITHUB_TOKEN="your-github-token"
```

**Note:** If no issue tracker is detected, the system continues with mock adapter (useful for CI/CD without Jira).

### 4. CI/CD Platform

Auto-detects by checking environment variables and config files:

| Platform | Detection Method | Environment Variable |
|----------|------------------|----------------------|
| **GitHub Actions** | CI env var OR `.github/workflows/` exists | `GITHUB_ACTIONS=true` |
| **GitLab CI** | CI env var OR `.gitlab-ci.yml` exists | `GITLAB_CI=true` |
| **Jenkins** | CI env var OR `Jenkinsfile` exists | `JENKINS_URL` or `JENKINS_HOME` |
| **CircleCI** | CI env var OR `.circleci/config.yml` exists | `CIRCLECI=true` |
| **Travis CI** | CI env var OR `.travis.yml` exists | `TRAVIS=true` |
| **Bitbucket Pipelines** | CI env var OR `bitbucket-pipelines.yml` exists | `BITBUCKET_PIPELINES=true` |

**Detection in CI:**
These are automatically set by CI platforms when running in their environment. No manual configuration needed.

**Local Detection:**
If running locally, the system checks for config files. You can manually set env vars to override:
```bash
# Force CI/CD detection for local testing
export GITHUB_ACTIONS=true
npx qa-orchestrator test TAR-1234
```

### 5. Environment Details

Auto-detects:

| Detail | Detection Method | Default |
|--------|------------------|---------|
| **Base URL** | `BASE_URL` environment variable | `http://localhost` |
| **Project Name** | `name` field in `package.json` | Directory name |
| **Test Directories** | Scans for `e2e/`, `tests/`, `test/`, `__tests__/`, `spec/`, `specs/`, `playwright/`, `cypress/` | Auto-list |
| **Node Version** | `process.version` | Current runtime |

**Example:**
```bash
# Auto-detected
BASE_URL=http://localhost
projectName=acme-qa-tests
testDirs=["e2e", "tests", "cypress"]

# Override with env var
export BASE_URL="https://staging.acme.com"
npx qa-orchestrator test TAR-1234 --base-url https://staging.acme.com
```

## Common Scenarios

### Scenario 1: GitHub + Playwright + Jira (Most Common)

```bash
# Project structure
your-project/
├── .git → remote: github.com/your-org/your-project
├── package.json → dependencies: { playwright: "^1.45.0" }
├── e2e/ → test files
└── .env (ignored)

# Setup
export JIRA_API_TOKEN="jira_token_here"
npm install

# Run
npx qa-orchestrator test TAR-1234

# Auto-detected:
# ✅ VCS: GitHub
# ✅ Test Framework: Playwright
# ✅ Issue Tracker: Jira (from JIRA_API_TOKEN)
# ✅ CI/CD: GitHub Actions (if running in GHA)
```

### Scenario 2: GitLab + Cypress + Linear

```bash
# Project structure
your-project/
├── .git → remote: gitlab.com/your-org/your-project
├── package.json → dependencies: { cypress: "^14.0.0" }
├── cypress/e2e/ → test files
└── .gitlab-ci.yml

# Setup
export LINEAR_API_KEY="linear_key_here"
npm install

# In CI (GitLab)
GITLAB_CI=true npx qa-orchestrator test TAR-5678

# Auto-detected:
# ✅ VCS: GitLab
# ✅ Test Framework: Cypress
# ✅ Issue Tracker: Linear
# ✅ CI/CD: GitLab CI
```

### Scenario 3: Bitbucket + Selenium + GitHub Issues

```bash
# Project structure
your-project/
├── .git → remote: bitbucket.org/your-org/your-project
├── package.json → dependencies: { selenium: "^4.0.0" }
└── tests/

# Setup
export GITHUB_TOKEN="ghp_xxxx"
npm install

# Run
npx qa-orchestrator test TAR-9012

# Auto-detected:
# ✅ VCS: Bitbucket
# ✅ Test Framework: Selenium
# ✅ Issue Tracker: GitHub Issues
# ✅ CI/CD: Bitbucket Pipelines (if in CI) or None (if local)
```

## Configuration Fallback

If auto-detection fails or gives wrong results, you can override with `config/integrations.js`:

```javascript
// config/integrations.js
module.exports = {
  // Override auto-detected values
  issueTracker: {
    adapter: 'linear',
    apiKey: process.env.LINEAR_API_KEY,
  },
  vcs: {
    adapter: 'github',
    url: 'https://github.com/my-org/my-project',
  },
  testExecutor: {
    adapter: 'cypress',
    tools: ['cypress'],
    configFile: 'cypress.config.js',
  },
};
```

Then run with `--config` flag:
```bash
npx qa-orchestrator test TAR-1234 --config config/integrations.js
```

## Supported Combinations

QA Orchestrator supports these tested combinations (more combinations work, but these are verified):

### VCS + Test Framework
| VCS | Playwright | Cypress | Selenium | Jest | Vitest |
|-----|-----------|---------|----------|------|--------|
| GitHub | ✅ | ✅ | ✅ | ✅ | ✅ |
| GitLab | ✅ | ✅ | ✅ | ✅ | ✅ |
| Bitbucket | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gitee | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| Generic Git | ✅ | ✅ | ⚠️ | ✅ | ✅ |

### Issue Tracker + VCS
| Tracker | GitHub | GitLab | Bitbucket | Azure DevOps |
|---------|--------|--------|-----------|-------------|
| Jira | ✅ | ✅ | ✅ | ✅ |
| Linear | ✅ | ✅ | ✅ | ⚠️ |
| GitHub Issues | ✅ | ✅ | ✅ | ⚠️ |
| Azure DevOps | ⚠️ | ⚠️ | ⚠️ | ✅ |

✅ = Fully supported  
⚠️ = Supported with limitations (see ARCHITECTURE.md)

## Troubleshooting

### "No issue tracker detected"

**What happened:** Auto-detection didn't find credentials for Jira, Linear, GitHub, etc.

**Solution:**
```bash
# Set the appropriate environment variable
export JIRA_API_TOKEN="your-token"

# Or override in config/integrations.js
# Or run with --no-track flag (test without issue tracker)
npx qa-orchestrator test TAR-1234 --no-track
```

### "No test framework detected"

**What happened:** `package.json` doesn't contain Playwright, Cypress, etc.

**Solution:**
```bash
# Install a test framework
npm install --save-dev playwright

# Or specify manually in config/integrations.js
# Or use --framework flag
npx qa-orchestrator test TAR-1234 --framework playwright
```

### "No git repository detected"

**What happened:** No `.git` directory found.

**Solution:**
```bash
# Initialize git
git init
git remote add origin https://github.com/your-org/your-project.git

# Or specify in config/integrations.js
```

### "Wrong VCS detected"

**What happened:** Auto-detection guessed the wrong provider.

**Solution:**
```bash
# Check what's in .git/config
cat .git/config

# Override in config/integrations.js
# Or set GIT_PROVIDER environment variable
export GIT_PROVIDER="github"
```

### "Credentials are invalid"

**What happened:** Token set but has insufficient permissions.

**Solution:**
```bash
# For Jira: token needs "read:jira-work" scope
# For Linear: token needs "issues:read" scope
# For GitHub: token needs "repo" scope

# Verify token works
# Jira: curl -H "Authorization: Bearer $JIRA_API_TOKEN" $JIRA_HOST/rest/api/3/myself
# Linear: curl -H "Authorization: Bearer $LINEAR_API_KEY" https://api.linear.app/graphql
# GitHub: curl -H "Authorization: Bearer $GITHUB_TOKEN" https://api.github.com/user
```

## Advanced: Custom Adapter Auto-Detection

If using custom adapters, implement the adapter's `detect()` method:

```javascript
// adapters/my-tracker.js
class MyTrackerAdapter {
  static detect() {
    // Return true if your tracker is detected
    return process.env.MY_TRACKER_TOKEN !== undefined;
  }

  constructor(config) { ... }
}

module.exports = MyTrackerAdapter;
```

Then register in `config/adapters.js`:
```javascript
const MyTrackerAdapter = require('../adapters/my-tracker');
EnvironmentDetector.registerAdapter('my-tracker', MyTrackerAdapter);
```

Auto-detection will now include your custom tracker.

## Environment Variables Reference

### Issue Tracker Credentials
```bash
JIRA_API_TOKEN          # Jira API token
JIRA_HOST               # Jira instance URL

LINEAR_API_KEY          # Linear API key
LINEAR_TOKEN            # Alternative Linear token

GITHUB_TOKEN            # GitHub PAT (Personal Access Token)

AZURE_DEVOPS_TOKEN      # Azure DevOps PAT
SYSTEM_COLLECTIONURI    # Azure DevOps organization URL

YOUTRACK_TOKEN          # YouTrack API token
YOUTRACK_URL            # YouTrack instance URL
```

### CI/CD Platform Detection
```bash
GITHUB_ACTIONS=true     # GitHub Actions
GITLAB_CI=true          # GitLab CI
JENKINS_URL             # Jenkins
JENKINS_HOME            # Jenkins
CIRCLECI=true           # CircleCI
TRAVIS=true             # Travis CI
BITBUCKET_PIPELINES=true # Bitbucket Pipelines
```

### Environment Customization
```bash
BASE_URL                # Application URL (default: http://localhost)
NODE_ENV                # Environment (development/staging/production)
DEBUG                   # Enable debug logging
```

## FAQ

**Q: Does auto-detection work in CI/CD pipelines?**  
A: Yes! CI platforms set environment variables automatically. The system detects GITHUB_ACTIONS=true, GITLAB_CI=true, etc. automatically.

**Q: What if I want to skip auto-detection?**  
A: Use `--no-detect` flag:
```bash
npx qa-orchestrator test TAR-1234 --no-detect --config config/integrations.js
```

**Q: Can I force a specific tool even if it's not detected?**  
A: Yes, use `config/integrations.js` or pass `--framework`, `--tracker`, etc.:
```bash
npx qa-orchestrator test TAR-1234 --framework cypress --tracker jira
```

**Q: What if my project uses a tool not on the list?**  
A: See ARCHITECTURE.md on implementing custom adapters.

**Q: Does auto-detection require internet?**  
A: No. It only reads local files (package.json, .git/config) and environment variables. Credentials validation happens when adapters are initialized.

**Q: Is auto-detection opt-in or always on?**  
A: Always on by default. Use `--no-detect` to disable.

## Next Steps

After auto-detection, see:
- **ENVIRONMENT_ADAPTATION.md** — Detailed environment setup
- **QA_USAGE_GUIDE.md** — How to use QA Orchestrator
- **OPTIMIZATION_GUIDE.md** — Performance tuning
- **ARCHITECTURE.md** — Understanding adapters and extending the system
