const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

/**
 * EnvironmentDetector — Auto-detect tech stack and configure adapters
 *
 * Detects:
 * - Version Control (GitHub, GitLab, Bitbucket, Gitee, generic Git)
 * - Test Framework (Playwright, Cypress, Selenium, Puppeteer, WebdriverIO, Jest, Vitest)
 * - Issue Tracker (Jira, Linear, GitHub Issues, Azure DevOps, YouTrack)
 * - CI/CD Platform (GitHub Actions, GitLab CI, Jenkins, CircleCI, Travis, Bitbucket Pipelines)
 * - Environment (BASE_URL, project name, test directories)
 *
 * Returns: { vcs, testFramework, issueTracker, cicd, environment, config }
 */
class EnvironmentDetector {
  constructor(baseDir = process.cwd()) {
    this.baseDir = baseDir;
    this.packageJsonPath = path.join(baseDir, 'package.json');
    this.gitDir = path.join(baseDir, '.git');
  }

  /**
   * Run full auto-detection
   */
  async detectAll() {
    const result = {
      vcs: this.detectVCS(),
      testFramework: this.detectTestFramework(),
      issueTracker: this.detectIssueTracker(),
      cicd: this.detectCICD(),
      environment: this.detectEnvironment(),
    };

    result.config = this.generateConfig(result);
    return result;
  }

  /**
   * Detect version control system
   */
  detectVCS() {
    const result = { type: null, url: null, remote: null };

    // Check if .git exists
    if (!fs.existsSync(this.gitDir)) {
      return { type: 'git', detected: false, reason: 'No .git directory found' };
    }

    try {
      const remoteUrl = execSync('git config --get remote.origin.url', {
        cwd: this.baseDir,
        encoding: 'utf-8',
      }).trim();

      result.url = remoteUrl;

      // Detect provider from URL
      if (remoteUrl.includes('github.com')) {
        result.type = 'github';
        result.remote = 'github';
      } else if (remoteUrl.includes('gitlab.com') || remoteUrl.includes('gitlab')) {
        result.type = 'gitlab';
        result.remote = 'gitlab';
      } else if (remoteUrl.includes('bitbucket.org') || remoteUrl.includes('bitbucket')) {
        result.type = 'bitbucket';
        result.remote = 'bitbucket';
      } else if (remoteUrl.includes('gitee.com')) {
        result.type = 'gitee';
        result.remote = 'gitee';
      } else {
        result.type = 'git';
        result.remote = 'generic';
      }

      result.detected = true;
      return result;
    } catch (e) {
      return { type: 'git', detected: false, reason: 'Failed to detect git remote', error: e.message };
    }
  }

  /**
   * Detect test framework from package.json
   */
  detectTestFramework() {
    const result = { type: null, detected: false, tools: [] };

    if (!fs.existsSync(this.packageJsonPath)) {
      return { ...result, reason: 'No package.json found' };
    }

    try {
      const pkg = JSON.parse(fs.readFileSync(this.packageJsonPath, 'utf-8'));
      const deps = { ...pkg.dependencies, ...pkg.devDependencies };

      // Browser testing frameworks
      if (deps.playwright) result.tools.push('playwright');
      if (deps.cypress) result.tools.push('cypress');
      if (deps.selenium) result.tools.push('selenium');
      if (deps.puppeteer) result.tools.push('puppeteer');
      if (deps.webdriverio) result.tools.push('webdriverio');

      // Unit/Component testing
      if (deps.jest) result.tools.push('jest');
      if (deps.vitest) result.tools.push('vitest');

      if (result.tools.length > 0) {
        // Primary: prefer browser testing over unit testing
        const browserTools = result.tools.filter(t => ['playwright', 'cypress', 'selenium', 'puppeteer', 'webdriverio'].includes(t));
        result.type = browserTools.length > 0 ? browserTools[0] : result.tools[0];
        result.detected = true;
      }

      return result;
    } catch (e) {
      return { ...result, reason: 'Failed to parse package.json', error: e.message };
    }
  }

  /**
   * Detect issue tracker from environment variables
   */
  detectIssueTracker() {
    const result = { type: null, detected: false, hasCredentials: false };

    // Check Jira
    if (process.env.JIRA_API_TOKEN || process.env.JIRA_HOST) {
      result.type = 'jira';
      result.detected = true;
      result.hasCredentials = !!process.env.JIRA_API_TOKEN;
      return result;
    }

    // Check Linear
    if (process.env.LINEAR_API_KEY || process.env.LINEAR_TOKEN) {
      result.type = 'linear';
      result.detected = true;
      result.hasCredentials = !!process.env.LINEAR_API_KEY;
      return result;
    }

    // Check GitHub Issues
    if (process.env.GITHUB_TOKEN) {
      result.type = 'github';
      result.detected = true;
      result.hasCredentials = true;
      return result;
    }

    // Check Azure DevOps
    if (process.env.AZURE_DEVOPS_TOKEN || process.env.SYSTEM_COLLECTIONURI) {
      result.type = 'azure-devops';
      result.detected = true;
      result.hasCredentials = !!process.env.AZURE_DEVOPS_TOKEN;
      return result;
    }

    // Check YouTrack
    if (process.env.YOUTRACK_TOKEN || process.env.YOUTRACK_URL) {
      result.type = 'youtrack';
      result.detected = true;
      result.hasCredentials = !!process.env.YOUTRACK_TOKEN;
      return result;
    }

    return { ...result, reason: 'No issue tracker credentials detected in environment variables' };
  }

  /**
   * Detect CI/CD platform
   */
  detectCICD() {
    const result = { type: null, detected: false, environment: null };

    // GitHub Actions
    if (process.env.GITHUB_ACTIONS === 'true') {
      return { type: 'github-actions', detected: true, environment: 'github-actions' };
    }

    // GitLab CI
    if (process.env.GITLAB_CI === 'true') {
      return { type: 'gitlab-ci', detected: true, environment: 'gitlab-ci' };
    }

    // Jenkins
    if (process.env.JENKINS_URL || process.env.JENKINS_HOME) {
      return { type: 'jenkins', detected: true, environment: 'jenkins' };
    }

    // CircleCI
    if (process.env.CIRCLECI === 'true') {
      return { type: 'circleci', detected: true, environment: 'circleci' };
    }

    // Travis CI
    if (process.env.TRAVIS === 'true') {
      return { type: 'travis', detected: true, environment: 'travis' };
    }

    // Bitbucket Pipelines
    if (process.env.BITBUCKET_PIPELINES === 'true') {
      return { type: 'bitbucket-pipelines', detected: true, environment: 'bitbucket-pipelines' };
    }

    // Check for config files
    const configFiles = {
      '.github/workflows': 'github-actions',
      '.gitlab-ci.yml': 'gitlab-ci',
      'Jenkinsfile': 'jenkins',
      '.circleci/config.yml': 'circleci',
      '.travis.yml': 'travis',
      'bitbucket-pipelines.yml': 'bitbucket-pipelines',
    };

    for (const [file, type] of Object.entries(configFiles)) {
      const fullPath = path.join(this.baseDir, file);
      if (fs.existsSync(fullPath)) {
        return { type, detected: true, configFile: file, environment: 'local' };
      }
    }

    return { ...result, reason: 'No CI/CD platform detected' };
  }

  /**
   * Detect environment details (BASE_URL, project name, test directories)
   */
  detectEnvironment() {
    const result = {
      baseUrl: process.env.BASE_URL || 'http://localhost',
      projectName: null,
      testDirs: [],
      nodeVersion: process.version,
    };

    // Get project name from package.json
    try {
      const pkg = JSON.parse(fs.readFileSync(this.packageJsonPath, 'utf-8'));
      result.projectName = pkg.name || path.basename(this.baseDir);
    } catch (e) {
      result.projectName = path.basename(this.baseDir);
    }

    // Detect test directories
    const possibleTestDirs = [
      'e2e',
      'tests',
      'test',
      '__tests__',
      'spec',
      'specs',
      'playwright',
      'cypress',
    ];

    for (const dir of possibleTestDirs) {
      const fullPath = path.join(this.baseDir, dir);
      if (fs.existsSync(fullPath) && fs.statSync(fullPath).isDirectory()) {
        result.testDirs.push(dir);
      }
    }

    return result;
  }

  /**
   * Generate adapter configuration from detected environment
   */
  generateConfig(detection) {
    const config = {
      issueTracker: this._generateIssueTrackerConfig(detection.issueTracker),
      vcs: this._generateVCSConfig(detection.vcs),
      testExecutor: this._generateTestExecutorConfig(detection.testFramework),
      cicd: this._generateCICDConfig(detection.cicd),
      environment: detection.environment,
    };

    return config;
  }

  _generateIssueTrackerConfig(issueTracker) {
    if (!issueTracker.type) {
      return {
        adapter: 'mock',
        note: 'No issue tracker detected. Using mock adapter. Configure credentials to enable real integrations.',
      };
    }

    const configs = {
      jira: {
        adapter: 'jira',
        host: process.env.JIRA_HOST || 'https://your-instance.atlassian.net',
        email: process.env.JIRA_EMAIL || 'your-email@example.com',
        token: process.env.JIRA_API_TOKEN ? '***MASKED***' : 'MISSING',
      },
      linear: {
        adapter: 'linear',
        apiKey: process.env.LINEAR_API_KEY ? '***MASKED***' : 'MISSING',
        teamKey: process.env.LINEAR_TEAM_KEY || 'auto-detect',
      },
      github: {
        adapter: 'github',
        owner: process.env.GITHUB_REPOSITORY_OWNER || 'auto-detect',
        repo: process.env.GITHUB_REPOSITORY || 'auto-detect',
        token: process.env.GITHUB_TOKEN ? '***MASKED***' : 'MISSING',
      },
      'azure-devops': {
        adapter: 'azure-devops',
        organization: process.env.SYSTEM_COLLECTIONURI || 'auto-detect',
        token: process.env.AZURE_DEVOPS_TOKEN ? '***MASKED***' : 'MISSING',
      },
      youtrack: {
        adapter: 'youtrack',
        url: process.env.YOUTRACK_URL || 'https://your-instance.youtrack.cloud',
        token: process.env.YOUTRACK_TOKEN ? '***MASKED***' : 'MISSING',
      },
    };

    return configs[issueTracker.type] || { adapter: 'mock' };
  }

  _generateVCSConfig(vcs) {
    if (!vcs.detected) {
      return {
        adapter: 'mock',
        note: 'No git repository detected. Using mock adapter.',
      };
    }

    const configs = {
      github: {
        adapter: 'github',
        url: vcs.url,
        type: 'github',
      },
      gitlab: {
        adapter: 'gitlab',
        url: vcs.url,
        type: 'gitlab',
      },
      bitbucket: {
        adapter: 'bitbucket',
        url: vcs.url,
        type: 'bitbucket',
      },
      gitee: {
        adapter: 'gitee',
        url: vcs.url,
        type: 'gitee',
      },
      git: {
        adapter: 'git',
        url: vcs.url,
        type: 'generic',
      },
    };

    return configs[vcs.type] || { adapter: 'mock' };
  }

  _generateTestExecutorConfig(testFramework) {
    if (!testFramework.type) {
      return {
        adapter: 'mock',
        tools: [],
        note: 'No test framework detected. Using mock adapter. Install a test framework to enable real testing.',
      };
    }

    const configs = {
      playwright: {
        adapter: 'playwright',
        tools: testFramework.tools,
        configFile: 'playwright.config.js',
        testDir: 'e2e',
      },
      cypress: {
        adapter: 'cypress',
        tools: testFramework.tools,
        configFile: 'cypress.config.js',
        testDir: 'cypress/e2e',
      },
      selenium: {
        adapter: 'selenium',
        tools: testFramework.tools,
        note: 'Selenium requires additional setup',
      },
      jest: {
        adapter: 'jest',
        tools: testFramework.tools,
        configFile: 'jest.config.js',
      },
      vitest: {
        adapter: 'vitest',
        tools: testFramework.tools,
        configFile: 'vitest.config.js',
      },
    };

    return configs[testFramework.type] || { adapter: 'mock', tools: testFramework.tools };
  }

  _generateCICDConfig(cicd) {
    if (!cicd.detected) {
      return {
        adapter: 'mock',
        note: 'No CI/CD platform detected. Using mock adapter.',
      };
    }

    const configs = {
      'github-actions': {
        adapter: 'github-actions',
        configFile: '.github/workflows',
      },
      'gitlab-ci': {
        adapter: 'gitlab-ci',
        configFile: '.gitlab-ci.yml',
      },
      jenkins: {
        adapter: 'jenkins',
        configFile: 'Jenkinsfile',
      },
      circleci: {
        adapter: 'circleci',
        configFile: '.circleci/config.yml',
      },
      travis: {
        adapter: 'travis',
        configFile: '.travis.yml',
      },
      'bitbucket-pipelines': {
        adapter: 'bitbucket-pipelines',
        configFile: 'bitbucket-pipelines.yml',
      },
    };

    return configs[cicd.type] || { adapter: 'mock' };
  }

  /**
   * Validate that minimum requirements are met
   */
  validate(detection) {
    const errors = [];

    if (!detection.vcs.detected) {
      errors.push('❌ No git repository detected. This system requires a git repository.');
    }

    if (!detection.testFramework.detected) {
      errors.push('⚠️  No test framework detected. Install Playwright, Cypress, or another framework.');
    }

    if (!detection.issueTracker.hasCredentials) {
      errors.push('⚠️  No issue tracker credentials detected. Set environment variables for Jira/Linear/GitHub/etc.');
    }

    if (!detection.cicd.detected) {
      errors.push('⚠️  No CI/CD platform detected. This is optional for local testing.');
    }

    return {
      valid: errors.length === 0,
      errors,
      warnings: errors.filter(e => e.startsWith('⚠️')),
    };
  }
}

module.exports = { EnvironmentDetector };
