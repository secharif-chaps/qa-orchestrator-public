/**
 * Integration Configuration — Define your QA Orchestrator stack
 * 
 * Copy this file to config/integrations.js and customize for YOUR project:
 *   cp config/integrations.example.js config/integrations.js
 * 
 * Then update adapters to match your DevOps stack:
 *   - Issue Tracker: Jira, Linear, GitHub, Azure DevOps, etc.
 *   - Version Control: Git, GitLab, GitHub, Bitbucket, etc.
 *   - Test Executor: Playwright, Cypress, Selenium, custom, etc.
 *   - CI/CD: GitLab CI, GitHub Actions, Jenkins, CircleCI, etc.
 */

module.exports = {
  // ═══════════════════════════════════════════════════════════════════════════
  // STEP 1: Choose your adapters (swap for your stack)
  // ═══════════════════════════════════════════════════════════════════════════
  
  adapters: {
    // Issue Tracking System
    issueTracker: 'jira',
    // Options: 'jira' | 'linear' | 'github' | 'azure' | 'youtrack' | 'custom'
    
    // Version Control System
    versionControl: 'gitlab',
    // Options: 'git' | 'gitlab' | 'github' | 'bitbucket' | 'gitee' | 'custom'
    
    // Test Execution Framework
    testExecutor: 'playwright',
    // Options: 'playwright' | 'cypress' | 'selenium' | 'puppeteer' | 'webdriverio' | 'custom'
    
    // CI/CD Platform
    cicd: 'gitlab-ci',
    // Options: 'gitlab-ci' | 'github-actions' | 'jenkins' | 'circleci' | 'travis' | 'custom'
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // STEP 2: Configure each adapter for YOUR environment
  // ═══════════════════════════════════════════════════════════════════════════

  // --- Jira Configuration ---
  jira: {
    host: 'your-jira-instance.atlassian.net',
    projectKey: 'TAR', // Change to your project key
    cloudId: process.env.JIRA_CLOUD_ID,
    apiToken: process.env.JIRA_API_TOKEN, // Never hardcode!
    apiVersion: 'cloud', // 'cloud' or 'server'
  },

  // --- Linear Configuration ---
  linear: {
    apiKey: process.env.LINEAR_API_KEY,
    teamId: 'TEAM-123', // Your team ID
  },

  // --- GitHub Configuration ---
  github: {
    owner: 'myorg',
    repo: 'myapp',
    token: process.env.GITHUB_TOKEN,
  },

  // --- Azure DevOps Configuration ---
  azure: {
    organization: 'myorg',
    projectId: 'myproject',
    teamProject: 'myproject',
    token: process.env.AZURE_DEVOPS_TOKEN,
  },

  // --- GitLab Configuration ---
  gitlab: {
    host: 'git.mediaspeech.com', // or github.com, bitbucket.org, etc.
    projectId: 'target', // Project ID or path
    token: process.env.QA_HUB_GITLAB_TOKEN,
    port: 17890,
  },

  // --- GitHub (as VCS) Configuration ---
  githubVCS: {
    owner: 'myorg',
    repo: 'myapp',
    token: process.env.GITHUB_TOKEN,
  },

  // --- Bitbucket Configuration ---
  bitbucket: {
    host: 'bitbucket.org',
    workspace: 'myworkspace',
    repoSlug: 'myapp',
    username: process.env.BITBUCKET_USERNAME,
    appPassword: process.env.BITBUCKET_APP_PASSWORD,
  },

  // --- Playwright Configuration ---
  playwright: {
    baseURL: 'http://localhost',
    browser: 'chromium', // 'chromium' | 'firefox' | 'webkit'
    headless: true,
    slowMo: 0,
    timeout: 30000,
    navigationTimeout: 30000,
    screenshots: 'only-on-failure',
    videos: 'retain-on-failure',
  },

  // --- Cypress Configuration ---
  cypress: {
    baseURL: 'http://localhost:3000',
    specPattern: 'cypress/e2e/**/*.cy.js',
    viewportWidth: 1280,
    viewportHeight: 720,
    defaultCommandTimeout: 10000,
  },

  // --- Selenium Configuration ---
  selenium: {
    host: 'localhost',
    port: 4444,
    browser: 'chrome', // 'chrome' | 'firefox' | 'safari'
    baseURL: 'http://localhost:3000',
    browserVersion: 'latest',
    platformName: 'windows', // or 'mac', 'linux'
    acceptInsecureCerts: true,
  },

  // --- GitLab CI Configuration ---
  gitlabCI: {
    host: 'git.mediaspeech.com',
    token: process.env.QA_HUB_GITLAB_TOKEN,
    projectId: 'target',
  },

  // --- GitHub Actions Configuration ---
  githubActions: {
    owner: 'myorg',
    repo: 'myapp',
    token: process.env.GITHUB_TOKEN,
    workflowFile: '.github/workflows/test.yml',
  },

  // --- Jenkins Configuration ---
  jenkins: {
    url: 'https://jenkins.example.com',
    username: process.env.JENKINS_USERNAME,
    apiToken: process.env.JENKINS_API_TOKEN,
    jobName: 'myapp-test-job',
  },

  // --- CircleCI Configuration ---
  circleci: {
    token: process.env.CIRCLECI_TOKEN,
    organization: 'myorg',
    projectSlug: 'myorg/myapp',
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // STEP 3: (Optional) Register custom adapters
  // ═══════════════════════════════════════════════════════════════════════════

  customAdapters: {
    // issueTracker: './adapters/my-custom-issue-tracker.js',
    // versionControl: './adapters/my-custom-vcs.js',
    // testExecutor: './adapters/my-custom-test-executor.js',
    // cicd: './adapters/my-custom-cicd.js',
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // STEP 4: (Optional) Global settings
  // ═══════════════════════════════════════════════════════════════════════════

  global: {
    timeout: 120000, // 2 minutes
    retryAttempts: 2,
    retryDelay: 1000,
    verbose: false,
    logLevel: 'info', // 'debug' | 'info' | 'warn' | 'error'
  },
};

// ═══════════════════════════════════════════════════════════════════════════
// QUICK EXAMPLES — Copy & customize for your stack
// ═══════════════════════════════════════════════════════════════════════════

/*
 * EXAMPLE 1: GitHub + Cypress + GitHub Actions
 * 
 * adapters: {
 *   issueTracker: 'github',
 *   versionControl: 'github',
 *   testExecutor: 'cypress',
 *   cicd: 'github-actions',
 * },
 * 
 * github: {
 *   owner: 'myorg',
 *   repo: 'myapp',
 *   token: process.env.GITHUB_TOKEN,
 * },
 * 
 * cypress: {
 *   baseURL: 'http://localhost:3000',
 *   specPattern: 'cypress/e2e/**/*.cy.js',
 * },
 * 
 * githubActions: {
 *   owner: 'myorg',
 *   repo: 'myapp',
 *   token: process.env.GITHUB_TOKEN,
 *   workflowFile: '.github/workflows/test.yml',
 * },
 */

/*
 * EXAMPLE 2: Linear + Bitbucket + Selenium + Jenkins
 * 
 * adapters: {
 *   issueTracker: 'linear',
 *   versionControl: 'bitbucket',
 *   testExecutor: 'selenium',
 *   cicd: 'jenkins',
 * },
 * 
 * linear: {
 *   apiKey: process.env.LINEAR_API_KEY,
 *   teamId: 'TEAM-123',
 * },
 * 
 * bitbucket: {
 *   host: 'bitbucket.org',
 *   workspace: 'myworkspace',
 *   repoSlug: 'myapp',
 *   appPassword: process.env.BITBUCKET_APP_PASSWORD,
 * },
 * 
 * selenium: {
 *   browser: 'chrome',
 *   baseURL: 'http://localhost:3000',
 * },
 * 
 * jenkins: {
 *   url: 'https://jenkins.example.com',
 *   jobName: 'myapp-tests',
 *   apiToken: process.env.JENKINS_API_TOKEN,
 * },
 */

/*
 * EXAMPLE 3: Azure DevOps + Custom Adapters
 * 
 * adapters: {
 *   issueTracker: 'azure',
 *   versionControl: 'github',
 *   testExecutor: 'custom',
 *   cicd: 'custom',
 * },
 * 
 * azure: {
 *   organization: 'myorg',
 *   projectId: 'myproject',
 *   token: process.env.AZURE_DEVOPS_TOKEN,
 * },
 * 
 * customAdapters: {
 *   testExecutor: './adapters/my-test-framework.js',
 *   cicd: './adapters/my-ci-system.js',
 * },
 */
