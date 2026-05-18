# QA Orchestrator — Pluggable Architecture

**Version:** 3.0.0 | **Status:** Production Ready

This is a **fully pluggable, tool-agnostic QA orchestration system** designed to adapt to ANY project, tech stack, or DevOps environment.

## 🏗️ Core Philosophy

- **Zero Assumptions** — No dependency on specific tools (Jira, Playwright, GitLab, etc.)
- **Pluggable Adapters** — Swap integrations without touching the core engine
- **Configuration-Driven** — Define your stack in `config/integrations.js`
- **Extensible** — Add new adapters for any tool in 15 minutes
- **Backwards Compatible** — Default adapters included (Jira, Git, Playwright)

## 🔌 Adapter Architecture

### Core Adapters (Pluggable Interfaces)

```
┌─────────────────────────────────────────────────────────────┐
│                    QA Orchestrator Core                     │
│                  (engine.js + agents)                       │
└────────────┬────────────────────────────┬────────────────────┘
             │                            │
    ┌────────▼──────────┐        ┌────────▼──────────┐
    │  Issue Tracker     │        │   Version Control  │
    │  Adapter          │        │   Adapter          │
    ├──────────────────┤        ├──────────────────┤
    │ • JiraAdapter    │        │ • GitAdapter     │
    │ • LinearAdapter  │        │ • GitLabAdapter  │
    │ • GithubAdapter  │        │ • GithubAdapter  │
    │ • Custom...      │        │ • Custom...      │
    └──────────────────┘        └──────────────────┘
             │                            │
    ┌────────▼──────────┐        ┌────────▼──────────┐
    │   Test Executor    │        │    CI/CD Provider  │
    │   Adapter          │        │    Adapter         │
    ├──────────────────┤        ├──────────────────┤
    │ • PlaywrightAdapter│        │ • GitLabCIAdapter  │
    │ • CypressAdapter   │        │ • GithubActions    │
    │ • SeleniumAdapter  │        │ • JenkinsAdapter   │
    │ • CustomAdapter    │        │ • Custom...        │
    └──────────────────┘        └──────────────────┘
```

### 1. Issue Tracker Adapter

**Interface:** `IssueTrackerAdapter`

```javascript
// core/adapters/issue-tracker.js
class IssueTrackerAdapter {
  async getIssue(ticketKey) {
    // Return: { key, title, description, status, acceptanceCriteria }
  }
  
  async createTestExecution(ticketKey, testResults) {
    // Create test execution in your tracking system
  }
  
  async updateIssueStatus(ticketKey, status) {
    // Update issue status
  }
  
  async addComment(ticketKey, comment) {
    // Add comment to issue
  }
}
```

**Built-in Adapters:**
- `JiraAdapter` — Jira Cloud / Server
- `LinearAdapter` — Linear.app
- `GithubIssuesAdapter` — GitHub Issues
- `AzureDevOpsAdapter` — Azure DevOps
- `YouTrackAdapter` — JetBrains YouTrack

### 2. Version Control Adapter

**Interface:** `VersionControlAdapter`

```javascript
// core/adapters/version-control.js
class VersionControlAdapter {
  async findBranch(ticketKey) {
    // Return branch name matching ticket
  }
  
  async switchToBranch(branchName) {
    // Checkout branch
  }
  
  async getDiff(baseBranch, limit) {
    // Return code diff
  }
  
  async getChangedFiles(baseBranch) {
    // Return list of changed files
  }
  
  async getMergeRequests() {
    // Return open MRs/PRs for analysis
  }
}
```

**Built-in Adapters:**
- `GitAdapter` — Generic Git
- `GitLabAdapter` — GitLab with MR API
- `GithubAdapter` — GitHub with PR API
- `BitbucketAdapter` — Bitbucket
- `GiteeAdapter` — Gitee

### 3. Test Executor Adapter

**Interface:** `TestExecutorAdapter`

```javascript
// core/adapters/test-executor.js
class TestExecutorAdapter {
  async generateTestScript(context) {
    // Generate test code for your framework
    // Return: { script, language, framework }
  }
  
  async executeTests(scriptPath, options) {
    // Run tests and return results
    // Return: { passed, failed, duration, artifacts }
  }
  
  async captureScreenshot(selector) {
    // Capture screenshot (if visual testing supported)
  }
}
```

**Built-in Adapters:**
- `PlaywrightAdapter` — Playwright (TypeScript/JS)
- `CypressAdapter` — Cypress
- `SeleniumAdapter` — Selenium (Python/Java/JS)
- `PuppeteerAdapter` — Puppeteer
- `WebdriverIOAdapter` — WebdriverIO
- `CustomTestAdapter` — Your own test framework

### 4. CI/CD Adapter

**Interface:** `CICDAdapter`

```javascript
// core/adapters/ci-cd.js
class CICDAdapter {
  async triggerPipeline(branchName, config) {
    // Trigger CI pipeline for branch
  }
  
  async getPipelineStatus(pipelineId) {
    // Get pipeline execution status
  }
  
  async getArtifacts(pipelineId) {
    // Retrieve test artifacts, reports
  }
}
```

**Built-in Adapters:**
- `GitLabCIAdapter` — GitLab CI
- `GithubActionsAdapter` — GitHub Actions
- `JenkinsAdapter` — Jenkins
- `CircleCIAdapter` — CircleCI
- `TravisAdapter` — Travis CI

## 🚀 Configuration

### `config/integrations.js` — Define Your Stack

```javascript
// config/integrations.js
module.exports = {
  // Which adapters to use
  adapters: {
    issueTracker: 'jira',      // jira | linear | github | azure | youtrack
    versionControl: 'gitlab',  // git | gitlab | github | bitbucket | gitee
    testExecutor: 'playwright', // playwright | cypress | selenium | puppeteer | webdriverio | custom
    cicd: 'gitlab-ci',         // gitlab-ci | github-actions | jenkins | circleci | travis
  },
  
  // Adapter-specific configuration
  jira: {
    host: 'your-jira-instance.atlassian.net',
    projectKey: 'TAR',
    cloudId: process.env.JIRA_CLOUD_ID,
    apiToken: process.env.JIRA_API_TOKEN,
  },
  
  gitlab: {
    host: 'git.example.com',
    projectId: 'mygroup/myproject',
    token: process.env.GITLAB_TOKEN,
    port: 443,
  },
  
  playwright: {
    baseURL: 'http://localhost:3000',
    browser: 'chromium',
    headless: true,
    timeout: 30000,
  },
  
  gitlabCI: {
    host: 'git.example.com',
    token: process.env.GITLAB_TOKEN,
  },
  
  // Custom adapters (path to your implementation)
  customAdapters: {
    // issueTracker: './adapters/my-custom-tracker.js',
    // testExecutor: './adapters/my-custom-test.js',
  },
};
```

## 📝 Creating Custom Adapters

### Example: Custom Test Executor

```javascript
// adapters/my-custom-executor.js
const { TestExecutorAdapter } = require('../core/adapters/test-executor');

class MyCustomTestExecutor extends TestExecutorAdapter {
  async generateTestScript(context) {
    // Generate test code for YOUR framework
    const { ticketKey, changedFiles, url } = context;
    
    const script = `
      import { test, expect } from '@my-framework/test';
      
      test('MYKEY-1234 — User can login', async ({ page }) => {
        await page.goto('${url}/login');
        await page.fill('input[name="email"]', 'user@example.com');
        await page.fill('input[name="password"]', 'password123');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('${url}/dashboard');
      });
    `;
    
    return {
      script,
      language: 'typescript',
      framework: 'my-framework',
    };
  }
  
  async executeTests(scriptPath, options) {
    // Run tests with YOUR framework
    const { execSync } = require('child_process');
    
    try {
      const output = execSync(
        `my-test-runner run "${scriptPath}"`,
        { encoding: 'utf-8' }
      );
      
      // Parse output and return results
      return {
        passed: 5,
        failed: 0,
        duration: 15000,
        output,
        artifacts: { screenshots: [...], videos: [...] },
      };
    } catch (err) {
      return {
        passed: 0,
        failed: 1,
        duration: 5000,
        error: err.message,
      };
    }
  }
}

module.exports = { MyCustomTestExecutor };
```

### Register Custom Adapter

```javascript
// config/integrations.js
module.exports = {
  adapters: {
    testExecutor: 'custom', // Use custom
  },
  
  customAdapters: {
    testExecutor: './adapters/my-custom-executor.js',
  },
};
```

## 🔄 How Adapters Flow

```
1. User runs:
   $ node index.js --project myapp --workflow qa-workflow --ticket MYKEY-1234

2. Engine loads adapters from config/integrations.js:
   issueTracker ← JiraAdapter
   versionControl ← GitLabAdapter
   testExecutor ← PlaywrightAdapter
   cicd ← GitLabCIAdapter

3. Workflow executes:
   a) issueTracker.getIssue('MYKEY-1234')
      ↓ JiraAdapter calls Jira API
   
   b) versionControl.findBranch('MYKEY-1234')
      ↓ GitLabAdapter queries GitLab for branch
   
   c) versionControl.getDiff('main', 5000)
      ↓ GitLabAdapter gets diff
   
   d) agents analyze context + diff
      ↓ agents generate test cases
   
   e) testExecutor.generateTestScript(context)
      ↓ PlaywrightAdapter generates Playwright code
   
   f) testExecutor.executeTests(script)
      ↓ PlaywrightAdapter runs `npx playwright test`
   
   g) issueTracker.createTestExecution('MYKEY-1234', results)
      ↓ JiraAdapter creates test execution in Jira

4. Results returned to user
```

## 📊 Adapter Coverage Matrix

| Tool | Issue Tracker | Version Control | Test Executor | CI/CD |
|------|---------------|-----------------|---------------|-------|
| **Jira** | ✅ | - | - | - |
| **Linear** | ✅ | - | - | - |
| **GitHub** | ✅ | ✅ | - | ✅ |
| **GitLab** | - | ✅ | - | ✅ |
| **Bitbucket** | - | ✅ | - | - |
| **Playwright** | - | - | ✅ | - |
| **Cypress** | - | - | ✅ | - |
| **Selenium** | - | - | ✅ | - |
| **Jenkins** | - | - | - | ✅ |
| **CircleCI** | - | - | - | ✅ |
| **Azure DevOps** | ✅ | - | - | - |

**Add yours in 15 minutes!**

## 🎯 Common Stack Examples

### Example 1: GitHub + Cypress + GitHub Actions

```javascript
// config/integrations.js
module.exports = {
  adapters: {
    issueTracker: 'github',
    versionControl: 'github',
    testExecutor: 'cypress',
    cicd: 'github-actions',
  },
  
  github: {
    owner: 'myorg',
    repo: 'myapp',
    token: process.env.GITHUB_TOKEN,
  },
  
  cypress: {
    baseURL: 'http://localhost:3000',
    specPattern: 'cypress/e2e/**/*.cy.js',
  },
};
```

### Example 2: Linear + Bitbucket + Selenium + Jenkins

```javascript
// config/integrations.js
module.exports = {
  adapters: {
    issueTracker: 'linear',
    versionControl: 'bitbucket',
    testExecutor: 'selenium',
    cicd: 'jenkins',
  },
  
  linear: {
    apiKey: process.env.LINEAR_API_KEY,
    teamId: 'TEAM-123',
  },
  
  bitbucket: {
    host: 'bitbucket.example.com',
    workspace: 'myworkspace',
    repoSlug: 'myapp',
    appPassword: process.env.BITBUCKET_PASSWORD,
  },
  
  selenium: {
    browser: 'chrome',
    baseURL: 'http://localhost:3000',
  },
  
  jenkins: {
    url: 'https://jenkins.example.com',
    jobName: 'myapp-tests',
    token: process.env.JENKINS_TOKEN,
  },
};
```

### Example 3: Azure DevOps + Custom Test Framework + Custom Adapter

```javascript
// config/integrations.js
module.exports = {
  adapters: {
    issueTracker: 'azure',
    versionControl: 'github', // Using GitHub mirror
    testExecutor: 'custom',
    cicd: 'custom',
  },
  
  azure: {
    organization: 'myorg',
    projectId: 'myproject',
    token: process.env.AZURE_DEVOPS_TOKEN,
  },
  
  customAdapters: {
    testExecutor: './adapters/custom-framework-executor.js',
    cicd: './adapters/custom-ci.js',
  },
};
```

## 🛠️ Extending for Your Stack

1. **Identify your tools:**
   - Issue Tracker: Jira? Linear? GitHub? Custom?
   - VCS: GitLab? GitHub? Bitbucket? Custom?
   - Test Framework: Playwright? Cypress? Custom?
   - CI/CD: GitLab CI? GitHub Actions? Jenkins? Custom?

2. **Check if adapters exist:**
   - Browse `core/adapters/` for built-in implementations
   - If found: use them in `config/integrations.js`
   - If missing: create a custom adapter

3. **Create custom adapter (if needed):**
   - Extend the base adapter class
   - Implement required methods
   - Register in `config/integrations.js`

4. **Test with your project:**
   ```bash
   node index.js --project myapp --workflow qa-workflow --ticket YOUR-TICKET
   ```

## 📚 Base Adapter Classes

All adapters extend a base class with standard interface:

```javascript
// core/adapters/base.js
class BaseAdapter {
  constructor(config) {
    this.config = config;
  }
  
  async validate() {
    // Verify credentials and connectivity
  }
  
  async healthCheck() {
    // Check if service is available
  }
}
```

## 🔐 Configuration Best Practices

1. **Never commit secrets:**
   ```bash
   # .gitignore
   .env
   .env.local
   config/integrations.local.js
   ```

2. **Use environment variables:**
   ```javascript
   // config/integrations.js
   jira: {
     apiToken: process.env.JIRA_API_TOKEN, // Not hardcoded!
   },
   ```

3. **Create local overrides:**
   ```javascript
   // config/integrations.js
   const localConfig = require('./integrations.local.js');
   module.exports = { ...defaultConfig, ...localConfig };
   ```

## 🎓 Learn More

- [OPTIMIZATION_GUIDE.md](./docs/OPTIMIZATION_GUIDE.md) — Cost/speed optimization
- [QA_USAGE_GUIDE.md](./docs/QA_USAGE_GUIDE.md) — Full usage guide
- [ENVIRONMENT_ADAPTATION.md](./docs/ENVIRONMENT_ADAPTATION.md) — Environment setup
- `core/adapters/*.js` — Adapter implementations

---

**QA Orchestrator v3.0.0 — Fully pluggable, tool-agnostic, production-ready** ✅
