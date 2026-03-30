/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * QA ORCHESTRATOR ENGINE — ChapsVision LiteLLM Gateway
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Uses: https://llm-gateway.ai.chapsvision.com/llm-gateway/v1/chat/completions
 * Format: OpenAI-compatible (system/user/assistant messages)
 * 
 * Models available:
 *   claude-opus-4        → Complex reasoning agents
 *   claude-sonnet-4      → Fast execution agents
 *   claude-haiku-4-5     → Lightweight tasks
 */

class QAEngine {
  constructor(config) {
    this.config = {
      llmBaseUrl: config.llmBaseUrl || 'https://llm-gateway.ai.chapsvision.com/llm-gateway',
      llmApiKey: config.llmApiKey,
      gitlabHost: config.gitlabHost || 'git.mediaspeech.com',
      gitlabPort: config.gitlabPort || 17890,
      gitlabToken: config.gitlabToken,
      ...config,
    };
    this.project = null;
    this.scanProfile = null;
    this.agents = {};
    this.workflows = {};
    this.history = [];
    this.listeners = [];
  }

  setProject(project) {
    this.project = project;
    this.scanProfile = null;
    this.emit('project:changed', project);
  }

  setScanProfile(profile) {
    this.scanProfile = profile;
    if (profile?.stack && this.project) {
      this.project.stack = [
        ...(profile.stack.frameworks || []),
        ...(profile.stack.databases || []),
        ...(profile.stack.tools || []),
      ];
    }
    this.emit('scan:complete', profile);
  }

  buildContext() {
    const p = this.project;
    if (!p) return 'PROJECT CONTEXT:\nNo project selected.';

    let ctx = `PROJECT CONTEXT:
Name: ${p.name} | Key: ${p.key} | Cloud ID: ${p.cloudId}
Environment: ${p.env.type} → ${p.env.url}
Git: ${p.git.protocol}://${p.git.host}:${p.git.port}/${p.git.group}/${p.git.repo}
GitLab Project ID: ${p.git.projectId}
Auth: ${p.auth.provider} (realm: ${p.auth.realm}, client: ${p.auth.client})
Confluence: ${p.confluenceSpace} | X-Ray: ${p.xray ? 'ENABLED' : 'DISABLED'}

JIRA RULES: cloudId "${p.cloudId}" | JQL: "project = ${p.key}"
X-Ray types: Test, Test Plan, Test Execution, Test Set, Precondition`;

    if (this.scanProfile) {
      const s = this.scanProfile.stack || {};
      const t = this.scanProfile.existing_tests || {};
      ctx += `\n\nSCAN PROFILE: ${s.type} | ${(s.frameworks||[]).join(', ')}`;
      ctx += ` | DBs: ${(s.databases||[]).join(', ')} | Tools: ${(s.tools||[]).join(', ')}`;
      if (t.total_count > 0) {
        ctx += `\nTests: ${t.total_count} files (${Object.entries(t.by_type||{}).map(([k,v])=>`${k}:${v}`).join(', ')})`;
      }
    }
    return ctx;
  }

  registerAgent(agent) { this.agents[agent.id] = agent; }
  registerWorkflow(workflow) { this.workflows[workflow.id] = workflow; }

  // ── RUN AGENT ────────────────────────────────────────────────────────

  async runAgent(agentId, userMessage, extraContext = '') {
    const agent = this.agents[agentId];
    if (!agent) throw new Error(`Agent not found: ${agentId}`);

    let systemPrompt = agent.systemPrompt.replace('{{PROJECT_CONTEXT}}', this.buildContext());
    if (this.scanProfile?.agent_instructions?.[agentId]) {
      const recs = this.scanProfile.agent_instructions[agentId];
      systemPrompt += `\n\nSCANNER NOTES:\n${Array.isArray(recs) ? recs.join('\n') : recs}`;
    }

    const fullMessage = extraContext ? `${userMessage}\n\n${extraContext}` : userMessage;

    this.emit('agent:start', { agentId, message: userMessage });
    const startTime = Date.now();

    try {
      const response = await this._callLLM({
        model: agent.model,
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: fullMessage },
        ],
        max_tokens: agent.maxTokens || 4096,
      });

      const result = this._parseResponse(response);
      const duration = Date.now() - startTime;
      const entry = { agentId, model: agent.model, input: userMessage.slice(0, 200), output: result.text, usage: result.usage, duration, timestamp: new Date().toISOString() };
      this.history.push(entry);
      this.emit('agent:done', entry);
      return result;
    } catch (err) {
      const entry = { agentId, model: agent.model, input: userMessage.slice(0, 200), error: err.message, duration: Date.now() - startTime, timestamp: new Date().toISOString() };
      this.history.push(entry);
      this.emit('agent:error', entry);
      throw err;
    }
  }

  // ── RUN WORKFLOW ─────────────────────────────────────────────────────

  async runWorkflow(workflowId, userMessage, options = {}) {
    const workflow = this.workflows[workflowId];
    if (!workflow) throw new Error(`Workflow not found: ${workflowId}`);

    this.emit('workflow:start', { workflowId, agents: workflow.agents });
    const results = [];
    let chainContext = '';

    for (let i = 0; i < workflow.agents.length; i++) {
      const agentId = workflow.agents[i];
      let extraContext = chainContext;

      if (agentId === 'mrAnalyzer') {
        extraContext += '\n\n' + await this._fetchMRs();
      }
      if (options.ticketKey) {
        extraContext += `\nTarget Ticket: ${options.ticketKey}`;
      }

      this.emit('workflow:step', { workflowId, step: i + 1, total: workflow.agents.length, agentId });

      const result = await this.runAgent(agentId, userMessage, extraContext);
      results.push({ agentId, ...result });
      chainContext += `\n\n--- ${this.agents[agentId].name} ---\n${result.text.slice(0, 2500)}`;
    }

    this.emit('workflow:done', { workflowId, results });
    return results;
  }

  // ── LLM GATEWAY ──────────────────────────────────────────────────────

  async _callLLM(payload) {
    const url = `${this.config.llmBaseUrl}/v1/chat/completions`;

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.config.llmApiKey}`,
      },
      body: JSON.stringify({
        model: payload.model,
        messages: payload.messages,
        max_tokens: payload.max_tokens,
        temperature: 0.1,
      }),
    });

    if (!res.ok) {
      const errBody = await res.text().catch(() => '');
      throw new Error(`LLM Gateway ${res.status}: ${errBody.slice(0, 300)}`);
    }
    return res.json();
  }

  _parseResponse(response) {
    const choice = response.choices?.[0];
    return {
      text: choice?.message?.content || '(No response)',
      usage: response.usage || {},
      model: response.model,
      finishReason: choice?.finish_reason,
    };
  }

  // ── GITLAB ───────────────────────────────────────────────────────────

  async _fetchMRs() {
    if (!this.project?.git?.projectId) return 'No GitLab project configured.';
    const pid = encodeURIComponent(this.project.git.projectId);
    const base = `https://${this.config.gitlabHost}:${this.config.gitlabPort}/api/v4`;

    try {
      const mrsRes = await fetch(`${base}/projects/${pid}/merge_requests?state=merged&order_by=updated_at&per_page=5`, {
        headers: { 'PRIVATE-TOKEN': this.config.gitlabToken },
      });
      const mrs = await mrsRes.json();
      if (!mrs.length) return 'No recent MRs found.';

      const details = await Promise.all(mrs.slice(0, 3).map(async (mr) => {
        const [chg, cmt] = await Promise.all([
          fetch(`${base}/projects/${pid}/merge_requests/${mr.iid}/changes`, { headers: { 'PRIVATE-TOKEN': this.config.gitlabToken } }).then(r => r.ok ? r.json() : null).catch(() => null),
          fetch(`${base}/projects/${pid}/merge_requests/${mr.iid}/commits`, { headers: { 'PRIVATE-TOKEN': this.config.gitlabToken } }).then(r => r.ok ? r.json() : []).catch(() => []),
        ]);
        return {
          iid: mr.iid, title: mr.title, author: mr.author?.name,
          source_branch: mr.source_branch, target_branch: mr.target_branch,
          merged_at: mr.merged_at, web_url: mr.web_url,
          commits: cmt.slice(0, 10).map(c => ({ title: c.title, author: c.author_name })),
          files: (chg?.changes || []).map(c => ({ path: c.new_path, new_file: c.new_file, deleted: c.deleted_file, diff: c.diff?.slice(0, 800) })),
        };
      }));

      this.emit('gitlab:fetched', { count: mrs.length, detailed: details.length });
      return `GITLAB MR DATA:\n${JSON.stringify(details, null, 2)}`;
    } catch (err) {
      return `GitLab error: ${err.message}`;
    }
  }

  // ── EVENTS ───────────────────────────────────────────────────────────

  on(event, callback) { this.listeners.push({ event, callback }); }
  emit(event, data) {
    for (const l of this.listeners) {
      if (l.event === event || l.event === '*') { try { l.callback(data, event); } catch {} }
    }
  }

  getHistory() { return this.history; }
  getAgentList() { return Object.values(this.agents).map(a => ({ id: a.id, name: a.name, model: a.model })); }
  getWorkflowList() { return Object.values(this.workflows).map(w => ({ id: w.id, name: w.name, agents: w.agents })); }
}

module.exports = { QAEngine };
