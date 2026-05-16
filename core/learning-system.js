/**
 * Learning System — Agent Self-Improvement
 *
 * Persistent learning from failures:
 * 1. Records failures to disk (qa-sessions/learnings/{agentId}.json)
 * 2. Injects past failure patterns into agent system prompts
 * 3. Keeps rolling window of last 20 failures per agent
 *
 * This allows agents to learn from mistakes and avoid repeating them.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

class LearningSystem {
  constructor(baseDir) {
    this.baseDir = baseDir;
    this.learningsDir = path.join(baseDir, 'qa-sessions', 'learnings');
    this.cache = {}; // in-memory cache per agent
  }

  async recordFailure(agentId, input, badOutput, issues) {
    const entry = {
      type: 'failure',
      timestamp: new Date().toISOString(),
      issues: Array.isArray(issues) ? issues : [issues],
      inputHash: this._hash(input.slice(0, 100)),
      outputSnippet: badOutput.slice(0, 300),
    };

    this._append(agentId, entry);
  }

  async recordSuccess(agentId, input, output) {
    const entry = {
      type: 'success',
      timestamp: new Date().toISOString(),
      inputHash: this._hash(input.slice(0, 100)),
      outputLength: output.length,
    };

    this._append(agentId, entry);
  }

  getLearnings(agentId) {
    // Check cache first
    if (this.cache[agentId]) {
      return this.cache[agentId];
    }

    // Load from disk
    const file = path.join(this.learningsDir, `${agentId}.json`);
    if (!fs.existsSync(file)) {
      return [];
    }

    try {
      const content = fs.readFileSync(file, 'utf8');
      const data = JSON.parse(content);
      // Cache it
      this.cache[agentId] = data;
      return data;
    } catch (e) {
      return [];
    }
  }

  injectLearnings(agentId, systemPrompt, limit = 5) {
    const learnings = this.getLearnings(agentId);
    if (!learnings.length) {
      return systemPrompt;
    }

    // Get last N failures only (most recent)
    const failures = learnings
      .filter(l => l.type === 'failure')
      .slice(-limit);

    if (!failures.length) {
      return systemPrompt;
    }

    const section = `## 🧠 Known Failure Patterns (Learn from these)

Common issues you've made before in this role:
${failures.map((f, i) => `${i + 1}. ${f.issues.join(', ')} (at ${f.timestamp})`).join('\n')}

Avoid these mistakes in your output. Make sure you include all required sections and format output correctly.`;

    return systemPrompt + '\n\n' + section;
  }

  _append(agentId, entry) {
    const file = path.join(this.learningsDir, `${agentId}.json`);

    // Ensure directory exists
    fs.mkdirSync(this.learningsDir, { recursive: true });

    // Load existing
    let existing = [];
    if (fs.existsSync(file)) {
      try {
        existing = JSON.parse(fs.readFileSync(file, 'utf8'));
      } catch (e) {
        existing = [];
      }
    }

    // Append and trim to rolling window (20 entries max)
    existing.push(entry);
    if (existing.length > 20) {
      existing.shift();
    }

    // Write back
    fs.writeFileSync(file, JSON.stringify(existing, null, 2));

    // Update cache
    this.cache[agentId] = existing;
  }

  _hash(str) {
    return crypto
      .createHash('md5')
      .update(str)
      .digest('hex')
      .slice(0, 8);
  }

  // Utility: list all learnings files
  listLearningFiles() {
    try {
      if (!fs.existsSync(this.learningsDir)) return [];
      return fs
        .readdirSync(this.learningsDir)
        .filter(f => f.endsWith('.json'))
        .map(f => f.replace('.json', ''));
    } catch (e) {
      return [];
    }
  }

  // Utility: clear learnings for a specific agent
  clearLearnings(agentId) {
    const file = path.join(this.learningsDir, `${agentId}.json`);
    if (fs.existsSync(file)) {
      fs.unlinkSync(file);
    }
    delete this.cache[agentId];
  }
}

module.exports = { LearningSystem };
