/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * ADAPTIVE ENVIRONMENT LOADER
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Respects the project's existing .env configuration instead of imposing new ones.
 * Priority: project .env > project .env.local > QA Orchestrator .env > process.env
 *
 * Automatically detects project root by searching for markers:
 *   .git, package.json, .env, docker-compose.yml, .qa-orchestrator.yml, src/, app/
 */

const fs = require('fs');
const path = require('path');

class EnvLoader {
  constructor() {
    this.projectRoot = null;
    this.qaRoot = __dirname;
    this.loadedEnv = {};
    this.sourceMap = {}; // Track where each variable came from
  }

  /**
   * Find project root by searching up from current directory
   */
  findProjectRoot(startDir = process.cwd()) {
    const markers = [
      '.git',
      'package.json',
      '.env',
      'docker-compose.yml',
      '.qa-orchestrator.yml',
      'src',
      'app',
    ];

    let dir = startDir;
    let lastDir = null;

    while (dir !== lastDir) {
      for (const marker of markers) {
        const markerPath = path.join(dir, marker);
        try {
          fs.statSync(markerPath);
          return dir; // Found marker, this is project root
        } catch {}
      }
      lastDir = dir;
      dir = path.dirname(dir);
    }

    return startDir; // Fallback to start dir if no markers found
  }

  /**
   * Parse .env file (KEY=VALUE format)
   */
  parseEnvFile(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf-8');
      const env = {};

      content.split('\n').forEach(line => {
        // Skip empty lines and comments
        if (!line || line.startsWith('#')) return;

        const [key, ...rest] = line.split('=');
        if (!key.trim()) return;

        let value = rest.join('=').trim();

        // Remove surrounding quotes
        if ((value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))) {
          value = value.slice(1, -1);
        }

        env[key.trim()] = value;
      });

      return env;
    } catch {
      return {};
    }
  }

  /**
   * Load environment with priority: project .env > QA .env > process.env
   */
  load(startDir = process.cwd()) {
    this.projectRoot = this.findProjectRoot(startDir);
    const projectEnvPath = path.join(this.projectRoot, '.env');
    const projectEnvLocalPath = path.join(this.projectRoot, '.env.local');
    const qaEnvPath = path.join(this.qaRoot, '..', '.env');

    // Start with process.env
    this.loadedEnv = { ...process.env };
    Object.keys(this.loadedEnv).forEach(k => {
      this.sourceMap[k] = 'process.env';
    });

    // Layer 1: QA Orchestrator .env
    try {
      const qaEnv = this.parseEnvFile(qaEnvPath);
      this._merge(qaEnv, 'QA Orchestrator .env');
    } catch {}

    // Layer 2: Project .env.local (overrides project .env)
    try {
      const envLocal = this.parseEnvFile(projectEnvLocalPath);
      this._merge(envLocal, 'project .env.local');
    } catch {}

    // Layer 3: Project .env (highest priority)
    try {
      const projectEnv = this.parseEnvFile(projectEnvPath);
      this._merge(projectEnv, 'project .env');
    } catch {}

    return this.loadedEnv;
  }

  /**
   * Merge environment variables with source tracking
   */
  _merge(env, source) {
    Object.entries(env).forEach(([key, value]) => {
      this.loadedEnv[key] = value;
      this.sourceMap[key] = source;
    });
  }

  /**
   * Apply loaded env to process.env
   */
  apply() {
    Object.entries(this.loadedEnv).forEach(([key, value]) => {
      process.env[key] = value;
    });
  }

  /**
   * Validate critical configuration
   */
  validate() {
    const critical = ['LLM_API_KEY'];
    const optional = [
      'QA_HUB_GITLAB_HOST',
      'QA_HUB_GITLAB_TOKEN',
      'JIRA_BASE_URL',
      'JIRA_EMAIL',
      'JIRA_TOKEN',
      'XRAY_CLIENT_ID',
      'XRAY_CLIENT_SECRET',
    ];

    const missing = { critical: [], optional: [] };

    critical.forEach(key => {
      if (!this.loadedEnv[key]) {
        missing.critical.push(key);
      }
    });

    optional.forEach(key => {
      if (!this.loadedEnv[key]) {
        missing.optional.push(key);
      }
    });

    return { valid: missing.critical.length === 0, missing };
  }

  /**
   * Get health endpoints from environment
   */
  getHealthEndpoints() {
    return {
      api: this.loadedEnv.API_HEALTH_ENDPOINT || this.loadedEnv.API_HEALTH_URL || 'http://localhost/api/health/ready',
      frontend: this.loadedEnv.FRONTEND_URL || this.loadedEnv.APP_URL || 'http://localhost',
      keycloak: this.loadedEnv.KEYCLOAK_HEALTH_ENDPOINT || 'http://localhost:8080/realms/chapsmind/.well-known/openid-configuration',
    };
  }

  /**
   * Get service start command from environment
   */
  getServiceCommand() {
    return {
      start: this.loadedEnv.SERVICE_START_COMMAND || this.loadedEnv.DOCKER_COMPOSE_UP || 'task up',
      stop: this.loadedEnv.SERVICE_STOP_COMMAND || this.loadedEnv.DOCKER_COMPOSE_DOWN || 'task down',
      compose: this.loadedEnv.DOCKER_COMPOSE_FILE || 'docker-compose.yml',
    };
  }

  /**
   * Get LLM configuration from environment
   */
  getLLMConfig() {
    return {
      baseUrl: this.loadedEnv.LLM_BASE_URL || this.loadedEnv.OPENAI_API_BASE || 'https://api.openai.com/v1',
      apiKey: this.loadedEnv.LLM_API_KEY || this.loadedEnv.OPENAI_API_KEY,
      model: this.loadedEnv.LLM_MODEL || this.loadedEnv.OPENAI_MODEL || 'gpt-4',
      timeout: parseInt(this.loadedEnv.LLM_TIMEOUT || '30000'),
    };
  }

  /**
   * Get VCS configuration from environment
   */
  getVCSConfig() {
    return {
      gitlab: {
        host: this.loadedEnv.QA_HUB_GITLAB_HOST || this.loadedEnv.GITLAB_HOST,
        port: parseInt(this.loadedEnv.QA_HUB_GITLAB_PORT || '22'),
        token: this.loadedEnv.QA_HUB_GITLAB_TOKEN || this.loadedEnv.GITLAB_TOKEN,
      },
      github: {
        token: this.loadedEnv.GITHUB_TOKEN,
        owner: this.loadedEnv.GITHUB_OWNER,
        repo: this.loadedEnv.GITHUB_REPO,
      },
    };
  }

  /**
   * Get issue tracker configuration from environment
   */
  getIssueTrackerConfig() {
    return {
      jira: {
        baseUrl: this.loadedEnv.JIRA_BASE_URL,
        email: this.loadedEnv.JIRA_EMAIL,
        token: this.loadedEnv.JIRA_TOKEN,
      },
      github: {
        token: this.loadedEnv.GITHUB_TOKEN,
        owner: this.loadedEnv.GITHUB_OWNER,
        repo: this.loadedEnv.GITHUB_REPO,
      },
      xray: {
        clientId: this.loadedEnv.XRAY_CLIENT_ID,
        clientSecret: this.loadedEnv.XRAY_CLIENT_SECRET,
      },
    };
  }

  /**
   * Generate summary of loaded configuration
   */
  summary() {
    const config = {
      projectRoot: this.projectRoot,
      qaRoot: this.qaRoot,
      llmSetup: this.loadedEnv.LLM_API_KEY ? '✅ Configured' : '❌ Missing LLM_API_KEY',
      vcsSetup: this.loadedEnv.QA_HUB_GITLAB_TOKEN ? '✅ GitLab' :
                this.loadedEnv.GITHUB_TOKEN ? '✅ GitHub' : '⚠️ No VCS configured',
      issueTrackerSetup: this.loadedEnv.JIRA_TOKEN ? '✅ Jira' :
                         this.loadedEnv.GITHUB_TOKEN ? '✅ GitHub Issues' : '⚠️ No issue tracker',
      projectAdaptation: {
        apiHealthEndpoint: this.loadedEnv.API_HEALTH_ENDPOINT ? '✅ Detected' : '⚠️ Using default',
        frontendUrl: this.loadedEnv.FRONTEND_URL ? '✅ Detected' : '⚠️ Using default',
        serviceCommand: this.loadedEnv.SERVICE_START_COMMAND ? '✅ Detected' : '⚠️ Using default',
      },
    };

    return {
      config,
      environmentSources: Object.entries(this.sourceMap)
        .reduce((acc, [key, source]) => {
          if (!acc[source]) acc[source] = [];
          acc[source].push(key);
          return acc;
        }, {}),
    };
  }
}

module.exports = { EnvLoader };
