/**
 * QA Engine — Real LLM calls via LiteLLM Gateway (OpenAI-compatible)
 * Supports: agent execution, workflow chaining, session management
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { SessionManager } = require('./session-manager');
const { JiraClient } = require('./jira-client');
const { EnvironmentManager } = require('./env-manager');
const { VerificationGate } = require('./verification-gate');
const { LearningSystem } = require('./learning-system');
const { ParallelExecutor } = require('./parallel-executor');
const { OptimizationConfig } = require('./optimization-config');

class QAEngine {
  constructor(config) {
    this.config = {
      llmBaseUrl: config.llmBaseUrl || process.env.LLM_BASE_URL || 'https://api.openai.com/v1',
      llmApiKey: config.llmApiKey || process.env.LLM_API_KEY,
      gitlabHost: config.gitlabHost || process.env.GITLAB_HOST || 'gitlab.com',
      gitlabPort: config.gitlabPort || parseInt(process.env.GITLAB_PORT || '443'),
      gitlabToken: config.gitlabToken || process.env.GITLAB_TOKEN,
      baseDir: config.baseDir || process.cwd(),
      ...config,
    };

    this.project = null;
    this.agents = {};
    this.workflows = {};
    this.history = [];
    this.listeners = [];
    this.sessionManager = null;
    // New systems for live validation + learning
    this.learningSystem = new LearningSystem(config.baseDir || process.cwd());
    this.verificationGate = new VerificationGate(this);
    this.envManager = null; // instantiated per workflow if needed
    this.parallelExecutor = new ParallelExecutor(this); // Optimized execution
  }

  // ── LLM Call ──────────────────────────────────────────────────────────────

  async callLLM(model, messages, options = {}) {
    const url = `${this.config.llmBaseUrl.replace(/\/$/, '')}/v1/chat/completions`;

    // Gateway may override model (e.g. LiteLLM gateway uses its own model names)
    const resolvedModel = process.env.LLM_MODEL || model;

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.config.llmApiKey}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: resolvedModel,
        messages,
        max_tokens: options.maxTokens || 4096,
        temperature: options.temperature ?? 0.3,
      }),
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`LLM API error ${res.status}: ${err}`);
    }

    const data = await res.json();
    return data.choices?.[0]?.message?.content || '';
  }

  // ── Agent Execution ───────────────────────────────────────────────────────

  async runAgent(agentId, userMessage, extraContext = '') {
    const agent = this.agents[agentId];
    if (!agent) return { text: `Agent ${agentId} not found` };

    this.emit('agent:start', { agentId, name: agent.name, model: agent.model });

    const startTime = Date.now();

    // Inject learnings from past failures
    const enrichedPrompt = this.learningSystem.injectLearnings(agentId, agent.prompt);

    const messages = [{ role: 'system', content: enrichedPrompt }];

    if (extraContext) {
      messages.push({ role: 'user', content: `--- Context from previous agents ---\n${extraContext}\n---` });
      messages.push({ role: 'assistant', content: 'Understood. I have the context from previous agents and will build on it.' });
    }

    messages.push({ role: 'user', content: userMessage });

    let text;
    try {
      text = await this.callLLM(agent.model, messages);
    } catch (err) {
      this.emit('agent:error', { agentId, error: err.message });
      return { text: `Error: ${err.message}`, model: agent.model, duration: Date.now() - startTime };
    }

    const duration = Date.now() - startTime;
    this.emit('agent:done', { agentId, output: text, duration });
    this.history.push({ agentId, input: userMessage, output: text, timestamp: new Date().toISOString() });

    return { text, model: agent.model, duration };
  }

  // ── Stage Normalization ───────────────────────────────────────────────────

  _normalizeStages(agents) {
    // If agents[0] is a string, convert to stages (each agent = solo stage)
    // If agents[0] is an array, return as-is (already in stage format)
    if (agents.length === 0) return [];
    if (typeof agents[0] === 'string') {
      return agents.map(id => [id]);
    }
    return agents;
  }

  // ── Workflow Execution ────────────────────────────────────────────────────

  async runWorkflow(workflowId, userMessage, options = {}) {
    const workflow = this.workflows[workflowId];
    if (!workflow) throw new Error(`Unknown workflow: ${workflowId}`);

    // ⚡ OPTIMIZATION: Use ParallelExecutor if sampling mode specified
    if (options.samplingMode || options.useOptimization !== false) {
      console.log(`\n🚀 Using Optimized Execution (Option A: 90% cost reduction)`);
      return await this.parallelExecutor.executeWorkflow(userMessage, {
        samplingMode: options.samplingMode || 'full',
        useCache: options.useCache !== false,
        ticketKey: options.ticketKey,
        branch: options.branch || 'main',
      });
    }

    // Initialize session for full QA workflow
    if (workflowId === 'qa-workflow' && options.ticketKey && this.project?.key) {
      this.sessionManager = new SessionManager(this.project.key, options.ticketKey, this.project.cloudId);
      this.emit('session:start', { sessionId: this.sessionManager.sessionId });
    }

    // Live Validation: Initialize environment if needed
    if (workflow.requiresLive && options.noLive !== true) {
      try {
        this.envManager = new EnvironmentManager(this.project, this);
        const envResult = await this.envManager.prepare(options.ticketKey);
        if (!envResult.healthy) {
          throw new Error('Environment failed to become healthy');
        }
      } catch (err) {
        this.emit('env:failed', { error: err.message });
        if (options.failFast !== false) {
          throw err;
        }
        // Otherwise continue (degraded mode)
      }
    }

    this.emit('workflow:start', { workflowId, agents: workflow.agents });

    const results = [];
    let chainContext = '';
    const stages = this._normalizeStages(workflow.agents || []);

    // Count total agents for progress reporting
    const totalAgents = stages.reduce((sum, stage) => sum + stage.length, 0);
    let stepCounter = 0;

    for (const stage of stages) {
      // Execute stage: solo agent or parallel agents
      const stageResults = await Promise.all(
        stage.map(async (agentId) => {
          this.emit('workflow:step', { step: ++stepCounter, total: totalAgents, agentId });

          let result = await this.runAgent(agentId, userMessage, chainContext);

          // Verification Gate: validate output before passing to next agent
          if (options.noGate !== true) {
            let retries = 0;
            while (retries < 2) {
              const verdict = await this.verificationGate.validate(agentId, result.text, userMessage, chainContext);
              this.emit('gate:result', { agentId, score: verdict.score, pass: verdict.pass, issues: verdict.issues });

              if (verdict.pass) {
                // Success: record and break
                await this.learningSystem.recordSuccess(agentId, userMessage, result.text);
                break;
              }

              // Failure: record and retry (max 2 attempts)
              await this.learningSystem.recordFailure(agentId, userMessage, result.text, verdict.issues);

              if (retries < 1) {
                // Retry with issues injected
                this.emit('gate:retry', { agentId, attempt: retries + 2, issues: verdict.issues });
                const retryMessage = userMessage + `\n\n⚠️  Previous attempt FAILED: ${verdict.issues.join('; ')}`;
                result = await this.runAgent(agentId, retryMessage, chainContext);
              }

              retries++;
            }
          }

          if (this.sessionManager) {
            this.sessionManager.logExecution(agentId, `Executed ${this.agents[agentId]?.name}`, result.text?.slice(0, 500));
          }

          // After gherkinWriter: import feature file into X-Ray if credentials available
          if (agentId === 'gherkinWriter' && options.ticketKey) {
            await this._importGherkinToXray(result.text, options.ticketKey);
          }

          // After browserValidator: extract and execute Playwright script if enabled
          if (agentId === 'browserValidator' && process.env.BROWSER_VALIDATION_ENABLED === 'true') {
            const execResult = await this._runBrowserValidation(result.text, options.ticketKey);
            result.text += `\n\n---\n## Browser Execution Results\n${execResult}`;
          }

          return { agentId, ...result };
        })
      );

      results.push(...stageResults);

      // Emit parallel event if stage has multiple agents
      if (stage.length > 1) {
        this.emit('workflow:parallel', { agents: stage, step: stepCounter - stage.length + 1 });
      }

      // Accumulate context from all agents in this stage for next stage
      for (const r of stageResults) {
        chainContext += `\n\n--- ${this.agents[r.agentId]?.name} output ---\n${r.text?.slice(0, 3000) || ''}`;
      }
    }

    // Save session for full QA workflow
    if (workflowId === 'qa-workflow' && this.sessionManager) {
      const { filePath } = this.sessionManager.saveToJson(this.config.baseDir);
      this.emit('session:save', { filePath });
    }

    this.emit('workflow:done', { workflowId, results });
    return results;
  }

  // ── X-Ray post-hook ───────────────────────────────────────────────────────

  async _importGherkinToXray(gherkinText, ticketKey) {
    const jira = new JiraClient();
    if (!jira.hasXray) return;

    // Extract Feature block from agent output
    const match = gherkinText.match(/```(?:gherkin|feature)?\s*(Feature:[\s\S]*?)```/i)
                || gherkinText.match(/(Feature:[\s\S]+)/i);
    if (!match) {
      this.emit('xray:skip', { reason: 'No Feature block found in gherkinWriter output' });
      return;
    }

    const featureContent = match[1].trim();
    const projectKey = ticketKey.split('-')[0];

    // Save .feature file for traceability
    const featureDir = path.join(this.config.baseDir || process.cwd(), 'qa-reports', projectKey);
    fs.mkdirSync(featureDir, { recursive: true });
    const featurePath = path.join(featureDir, `${ticketKey}.feature`);
    fs.writeFileSync(featurePath, featureContent);
    this.emit('xray:feature-saved', { path: featurePath });

    try {
      this.emit('xray:import-start', { ticketKey, projectKey });
      const importResult = await jira.importGherkin(featureContent, projectKey);

      const testKeys = (importResult.updatedOrCreatedTests || [])
        .map(t => t.key || t.self?.split('/issue/')[1])
        .filter(Boolean);

      this.emit('xray:import-done', { testKeys, count: testKeys.length });

      if (testKeys.length) {
        const exec = await jira.createTestExecution(ticketKey, testKeys);
        this.emit('xray:execution-created', { execKey: exec.key, testCount: testKeys.length });
      }
    } catch (err) {
      this.emit('xray:error', { error: err.message });
    }
  }

  // ── Browser Validation post-hook ──────────────────────────────────────────

  async _runBrowserValidation(agentOutput, ticketKey) {
    const ticketLabel = ticketKey || 'validation';

    // Extract TypeScript code block from agent output
    const tsMatch = agentOutput.match(/```(?:typescript|ts)?\s*([\s\S]*?)```/i);
    if (!tsMatch) {
      return `❌ ERROR: No Playwright script found in browser validator output\n\nAgent output:\n${agentOutput.slice(0, 500)}...`;
    }

    const scriptContent = tsMatch[1].trim();
    const baseDir = this.config.baseDir || process.cwd();
    const sessionsDir = path.join(baseDir, 'qa-sessions');
    const scriptPath = path.join(sessionsDir, `browser-validation-${ticketLabel}.spec.ts`);

    // Save script
    fs.mkdirSync(sessionsDir, { recursive: true });
    fs.writeFileSync(scriptPath, scriptContent);
    this.emit('browser-validation:script-saved', { path: scriptPath });

    try {
      // Execute Playwright test
      this.emit('browser-validation:execute-start', { script: scriptPath });
      const cmd = `npx playwright test "${scriptPath}" --reporter=line --reporter=json`;
      const output = execSync(cmd, {
        cwd: baseDir,
        encoding: 'utf8',
        stdio: ['pipe', 'pipe', 'pipe'], // capture stdout/stderr
      });

      this.emit('browser-validation:execute-done', { output: output.slice(0, 200) });
      return output;
    } catch (err) {
      // Playwright test failed — capture error but don't crash
      const errorMsg = err.stdout ? err.stdout.toString('utf8') : err.message;
      this.emit('browser-validation:execute-error', { error: err.message });
      return `❌ Test Execution Failed\n\n${errorMsg}\n\n(Script saved at: ${scriptPath})`;
    }
  }

  // ── GitLab MR Fetching ────────────────────────────────────────────────────

  async fetchGitLabMRs(projectId, limit = 10) {
    const base = `https://${this.config.gitlabHost}/api/v4`;
    const encoded = encodeURIComponent(projectId);

    const res = await fetch(`${base}/projects/${encoded}/merge_requests?state=opened&per_page=${limit}`, {
      headers: { 'PRIVATE-TOKEN': this.config.gitlabToken },
    });

    if (!res.ok) throw new Error(`GitLab API error ${res.status}`);
    const mrs = await res.json();

    const detailed = await Promise.all(
      mrs.slice(0, 5).map(async (mr) => {
        const diffRes = await fetch(`${base}/projects/${encoded}/merge_requests/${mr.iid}/diffs`, {
          headers: { 'PRIVATE-TOKEN': this.config.gitlabToken },
        });
        const diffs = diffRes.ok ? await diffRes.json() : [];
        return { ...mr, diffs: diffs.slice(0, 3) };
      })
    );

    this.emit('gitlab:fetched', { count: mrs.length, detailed: detailed.length });
    return detailed;
  }

  // ── Registration ──────────────────────────────────────────────────────────

  registerAgent(agent) { this.agents[agent.id] = agent; }
  registerWorkflow(workflow) { this.workflows[workflow.id] = workflow; }
  setProject(project) { this.project = project; this.emit('project:changed', project); }
  setAgents(agents) { this.agents = agents; }
  setWorkflows(workflows) { this.workflows = workflows; }

  // ── Events ────────────────────────────────────────────────────────────────

  on(event, callback) { this.listeners.push({ event, callback }); }
  emit(event, data) {
    this.listeners.filter(l => l.event === event).forEach(l => l.callback(data));
  }
}

module.exports = { QAEngine, SessionManager };
