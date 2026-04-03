/**
 * QA Engine — Runtime with SessionManager Integration
 * v1.1.0: Integrated SessionManager for session persistence
 */

const { SessionManager } = require('./session-manager');

class QAEngine {
  constructor(config) {
    this.config = {
      anthropicKey: config.anthropicKey,
      gitlabHost: config.gitlabHost || 'git.mediaspeech.com',
      gitlabPort: config.gitlabPort || 17890,
      gitlabToken: config.gitlabToken,
      baseDir: config.baseDir || process.cwd(),
      ...config,
    };

    this.project = null;
    this.scanProfile = null;
    this.agents = {};
    this.workflows = {};
    this.history = [];
    this.listeners = [];
    this.sessionManager = null; // Initialized per workflow
  }

  // Initialize SessionManager for qa-workflow
  initializeSession(projectKey, ticketKey, cloudId) {
    this.sessionManager = new SessionManager(projectKey, ticketKey, cloudId);
    this.emit('session:start', { sessionId: this.sessionManager.sessionId });
    return this.sessionManager;
  }

  // Run workflow with optional session tracking
  async runWorkflow(workflowId, userMessage, options = {}) {
    // Initialize SessionManager for qa-workflow
    if (workflowId === 'qa-workflow' && options.ticketKey && this.project?.key && this.project?.cloudId) {
      this.initializeSession(this.project.key, options.ticketKey, this.project.cloudId);
    }

    this.emit('workflow:start', { workflowId, agents: this.workflows[workflowId]?.agents });

    const results = [];
    let chainContext = '';

    // Execute agents in workflow
    for (const agentId of (this.workflows[workflowId]?.agents || [])) {
      const result = await this.runAgent(agentId, userMessage, chainContext);
      results.push({ agentId, ...result });

      // Log to session if available
      if (this.sessionManager) {
        this.sessionManager.logExecution(agentId, `Executed ${this.agents[agentId]?.name}`, result.text?.slice(0, 500));
      }

      // Chain context
      chainContext += `\n\n--- ${this.agents[agentId]?.name} output ---\n${result.text?.slice(0, 2500) || ''}`;
    }

    // Finalize session if qa-workflow
    if (workflowId === 'qa-workflow' && this.sessionManager) {
      const { filePath, data } = this.sessionManager.saveToJson(this.config.baseDir);
      this.emit('session:save', { sessionId: this.sessionManager.sessionId, filePath });
      
      const confluenceDraft = this.sessionManager.generateConfluenceDraft();
      this.emit('session:confluence-draft-ready', {
        sessionId: this.sessionManager.sessionId,
        title: confluenceDraft.title,
      });
    }

    this.emit('workflow:done', { workflowId, results });
    return results;
  }

  async runAgent(agentId, userMessage, extraContext = '') {
    const agent = this.agents[agentId];
    if (!agent) return { text: `Agent ${agentId} not found` };

    // Simulate agent execution
    return {
      text: `${agent.name} processed: ${userMessage}`,
      model: agent.model,
    };
  }

  setProject(project) {
    this.project = project;
    this.emit('project:changed', project);
  }

  setAgents(agents) {
    this.agents = agents;
  }

  setWorkflows(workflows) {
    this.workflows = workflows;
  }

  on(event, callback) {
    this.listeners.push({ event, callback });
  }

  emit(event, data) {
    this.listeners
      .filter(l => l.event === event)
      .forEach(l => l.callback(data));
  }
}

module.exports = { QAEngine, SessionManager };
