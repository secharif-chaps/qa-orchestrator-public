/**
 * Environment Manager — Live Validation Foundation
 *
 * Responsibilities:
 * 1. Find and checkout PR branch (git stash + fetch + checkout -b) — OPTIONAL
 * 2. Ensure Docker services are running (curl health check → task up if needed)
 * 3. Wait for services to be healthy (/api/health/ready polling)
 * 4. Enable browserValidator to test against RUNNING code
 *
 * Usage:
 * - prepare() → test main branch (post-merge regression testing)
 * - prepare('MYKEY-1234') → test PR branch before merge (pre-merge validation)
 */

const { execSync, spawn } = require('child_process');

class EnvironmentManager {
  constructor(project, emitter) {
    this.project = project;
    this.emitter = emitter;
    this.localPath = project.localPath;
  }

  async prepare(ticketKey, options = {}) {
    const maxRetries = options.maxRetries || 3;
    const healthTimeout = options.healthTimeout || 120000;

    this.emit('env:checking', { stage: 'starting' });

    try {
      // Step 1: Find and checkout branch (OPTIONAL — only if ticketKey provided)
      if (ticketKey) {
        const branch = await this.findAndCheckoutBranch(ticketKey);
        this.emit('env:branch-ready', { branch });
      }

      // Step 2: Ensure services running
      await this.ensureServicesRunning();
      this.emit('env:services-checking', {});

      // Step 3: Wait for health
      const healthy = await this.waitForHealthy(healthTimeout);
      if (!healthy) {
        throw new Error(`Services not healthy after ${healthTimeout}ms`);
      }

      this.emit('env:ready', {
        url: this.project.env.url || 'http://localhost',
        branch: ticketKey ? this.getCurrentBranch() : 'main',
        healthy: true,
      });

      return { healthy: true };
    } catch (err) {
      this.emit('env:error', { error: err.message });
      throw err;
    }
  }

  async findAndCheckoutBranch(ticketKey) {
    this.emit('env:branch-finding', { ticketKey });

    // Stash if dirty
    try {
      const status = execSync('git status --porcelain', { cwd: this.localPath, encoding: 'utf8' });
      if (status.trim()) {
        execSync('git stash push --include-untracked', { cwd: this.localPath });
        this.emit('env:stashed', {});
      }
    } catch (e) {
      // Stash may fail if no changes; ignore
    }

    // Fetch all
    try {
      execSync('git fetch --all --quiet', { cwd: this.localPath, timeout: 30000 });
    } catch (e) {
      // Network error; proceed anyway
    }

    // Find branch (8-pattern priority)
    const patterns = [
      `${ticketKey}`,
      `feat/${ticketKey}`,
      `fix/${ticketKey}`,
      `feature/${ticketKey}`,
      `chore/${ticketKey}`,
      `refactor/${ticketKey}`,
      `docs/${ticketKey}`,
      `test/${ticketKey}`,
    ];

    let branchName = null;
    for (const pattern of patterns) {
      try {
        execSync(`git show-ref --verify --quiet refs/remotes/origin/${pattern}`, { cwd: this.localPath });
        branchName = pattern;
        break;
      } catch (e) {
        // Branch not found; try next pattern
      }
    }

    if (!branchName) {
      throw new Error(`No branch found for ${ticketKey} (tried: ${patterns.join(', ')})`);
    }

    // Checkout (create local tracking branch if it doesn't exist)
    try {
      execSync(`git checkout -b ${branchName} origin/${branchName}`, { cwd: this.localPath, stdio: 'ignore' });
    } catch (e) {
      // Branch may already exist locally; just switch
      try {
        execSync(`git checkout ${branchName}`, { cwd: this.localPath });
      } catch (switchErr) {
        throw new Error(`Failed to checkout ${branchName}: ${switchErr.message}`);
      }
    }

    this.emit('env:branch-checked-out', { branch: branchName });
    return branchName;
  }

  async ensureServicesRunning() {
    // Check if already running (curl health endpoint)
    try {
      const apiUrl = this.project.healthEndpoints?.api || 'http://localhost/api/health/live';
      await this.curl(apiUrl, { timeout: 5000 });
      this.emit('env:already-running', {});
      return;
    } catch (e) {
      // Services not running; start them
    }

    this.emit('env:starting', { command: 'task up' });

    // Run task up (non-blocking, stream output)
    return new Promise((resolve, reject) => {
      const proc = spawn('task', ['up'], {
        cwd: this.localPath,
        stdio: ['ignore', 'inherit', 'inherit'], // Stream output to console
      });

      const timeout = setTimeout(() => {
        proc.kill();
        reject(new Error('task up timeout after 180s'));
      }, 180000);

      proc.on('close', (code) => {
        clearTimeout(timeout);
        if (code === 0) {
          this.emit('env:started', {});
          resolve();
        } else {
          reject(new Error(`task up exited with code ${code}`));
        }
      });

      proc.on('error', (err) => {
        clearTimeout(timeout);
        reject(new Error(`Failed to start services: ${err.message}`));
      });
    });
  }

  async waitForHealthy(timeout = 120000) {
    const apiUrl = this.project.healthEndpoints?.api || 'http://localhost/api/health/ready';
    const pollInterval = 3000;
    const startTime = Date.now();
    let attempt = 0;

    while (Date.now() - startTime < timeout) {
      attempt++;
      try {
        const response = await this.curl(apiUrl);
        if (response.status === 200) {
          const body = await response.json();
          if (body.status === 'ready') {
            this.emit('env:healthy', { attempts: attempt });
            return true;
          }
        }
      } catch (e) {
        // Still not ready
      }

      this.emit('env:health-check', { attempt, status: 'checking', elapsed: Date.now() - startTime });

      // Wait before next poll
      await new Promise(resolve => setTimeout(resolve, pollInterval));
    }

    this.emit('env:health-timeout', { attempts: attempt, timeout });
    return false;
  }

  getCurrentBranch() {
    try {
      return execSync('git branch --show-current', { cwd: this.localPath, encoding: 'utf8' }).trim();
    } catch (e) {
      return 'main';
    }
  }

  async curl(url, options = {}) {
    const timeout = options.timeout || 10000;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);

    try {
      const response = await fetch(url, {
        signal: controller.signal,
        timeout,
      });
      return response;
    } finally {
      clearTimeout(timeoutId);
    }
  }

  emit(event, data) {
    if (this.emitter) {
      this.emitter.emit(event, data);
    }
  }
}

module.exports = { EnvironmentManager };
