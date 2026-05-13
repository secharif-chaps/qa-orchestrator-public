/**
 * Git client — auto-detects feature branch for a ticket key
 * Searches local + remote branches matching ticket patterns
 */

const { execSync } = require('child_process');

class GitClient {
  constructor(localPath) {
    this.localPath = localPath || process.cwd();
  }

  exec(cmd) {
    try {
      return execSync(cmd, { cwd: this.localPath, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }).trim();
    } catch {
      return '';
    }
  }

  // Find branch matching ticket key (e.g. TAR-1332)
  findBranch(ticketKey) {
    this.exec('git fetch --all --quiet');

    const all = this.exec('git branch -a').split('\n').map(b => b.replace(/^\*?\s+/, '').replace(/^remotes\/origin\//, '').trim());
    const unique = [...new Set(all)].filter(Boolean);

    // Priority order of patterns
    const patterns = [
      b => b === `feat/${ticketKey}`,
      b => b === `feature/${ticketKey}`,
      b => b === `fix/${ticketKey}`,
      b => b.startsWith(`feat/${ticketKey}-`) || b.startsWith(`feat/${ticketKey}/`),
      b => b.startsWith(`feature/${ticketKey}-`) || b.startsWith(`feature/${ticketKey}/`),
      b => b.startsWith(`fix/${ticketKey}-`) || b.startsWith(`fix/${ticketKey}/`),
      b => b.includes(ticketKey.toLowerCase()),
      b => b.includes(ticketKey),
    ];

    for (const pattern of patterns) {
      const found = unique.find(pattern);
      if (found) return found;
    }

    return null;
  }

  // Switch to branch and return what happened
  switchToBranch(branch) {
    const current = this.exec('git branch --show-current');
    if (current === branch) return { switched: false, branch, current };

    const result = this.exec(`git checkout ${branch}`);
    const now = this.exec('git branch --show-current');
    return { switched: now === branch, branch, current, result };
  }

  // Get current branch
  currentBranch() {
    return this.exec('git branch --show-current') || 'main';
  }

  // Get recent commits on current branch vs main
  getRecentDiff(base = 'main') {
    return this.exec(`git log ${base}..HEAD --oneline`);
  }

  // Get changed files vs base branch
  getChangedFiles(base = 'main') {
    return this.exec(`git diff --name-only ${base}...HEAD`).split('\n').filter(Boolean);
  }

  // Get diff content for changed files (truncated)
  getDiff(base = 'main', maxChars = 8000) {
    const diff = this.exec(`git diff ${base}...HEAD -- '*.py' '*.ts' '*.vue' '*.php' '*.js'`);
    return diff.slice(0, maxChars);
  }
}

module.exports = { GitClient };
