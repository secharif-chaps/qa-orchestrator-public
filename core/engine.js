/**
 * QA Engine — Real LLM calls via LiteLLM Gateway (OpenAI-compatible)
 * Supports: agent execution, workflow chaining, session management
 */

const fs = require('fs');
const path = require('path');
const { SessionManager } = require('./session-manager');
const { JiraClient } = require('./jira-client');

class QAEngine {
  constructor(config) {
    this.config = {
      llmBaseUrl: config.llmBaseUrl || process.env.LLM_BASE_URL || 'https://llm-gateway.ai.chapsvision.com/llm-gateway',
      llmApiKey: config.llmApiKey || process.env.LLM_API_KEY,
      gitlabHost: config.gitlabHost || process.env.QA_HUB_GITLAB_HOST || 'git.mediaspeech.com',
      gitlabPort: config.gitlabPort || parseInt(process.env.QA_HUB_GITLAB_PORT || '17890'),
      gitlabToken: config.gitlabToken || process.env.QA_HUB_GITLAB_TOKEN,
      baseDir: config.baseDir || process.cwd(),
      ...config,
    };

    this.project = null;
    this.agents = {};
    this.workflows = {};
    this.history = [];
    this.listeners = [];
    this.sessionManager = null;
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

    const messages = [{ role: 'system', content: agent.prompt }];

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

  // ── Workflow Execution ────────────────────────────────────────────────────

  async runWorkflow(workflowId, userMessage, options = {}) {
    const workflow = this.workflows[workflowId];
    if (!workflow) throw new Error(`Unknown workflow: ${workflowId}`);

    // Initialize session for full QA workflow
    if (workflowId === 'qa-workflow' && options.ticketKey && this.project?.key) {
      this.sessionManager = new SessionManager(this.project.key, options.ticketKey, this.project.cloudId);
      this.emit('session:start', { sessionId: this.sessionManager.sessionId });
    }

    this.emit('workflow:start', { workflowId, agents: workflow.agents });

    const results = [];
    let chainContext = '';
    const agentIds = workflow.agents || [];

    for (let i = 0; i < agentIds.length; i++) {
      const agentId = agentIds[i];
      this.emit('workflow:step', { step: i + 1, total: agentIds.length, agentId });

      const result = await this.runAgent(agentId, userMessage, chainContext);
      results.push({ agentId, ...result });

      if (this.sessionManager) {
        this.sessionManager.logExecution(agentId, `Executed ${this.agents[agentId]?.name}`, result.text?.slice(0, 500));
      }

      // After gherkinWriter: import feature file into X-Ray if credentials available
      if (agentId === 'gherkinWriter' && options.ticketKey) {
        await this._importGherkinToXray(result.text, options.ticketKey);
      }

      // Pass last 3000 chars of output as context to next agent
      chainContext += `\n\n--- ${this.agents[agentId]?.name} output ---\n${result.text?.slice(0, 3000) || ''}`;
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
