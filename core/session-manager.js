/**
 * SessionManager — Session State & Persistence
 * Tracks TODO items, test cases, heuristics, and persists to JSON + Confluence
 */

class SessionManager {
  constructor(projectKey, ticketKey, cloudId) {
    this.projectKey = projectKey;
    this.ticketKey = ticketKey;
    this.cloudId = cloudId;
    this.sessionId = `${ticketKey}-${new Date().toISOString().split('T')[0]}`;
    this.todoList = { open: [], resolved: [] };
    this.testCases = [];
    this.heuristicsCandidates = [];
    this.gherkinScenarios = [];
    this.executionLog = [];
  }

  addTodoItem(task, phase, owner, notes) {
    const id = `TODO-${this.todoList.open.length + 1}`;
    this.todoList.open.push({ id, task, phase, owner, notes, createdAt: new Date() });
    return id;
  }

  resolveTodoItem(id) {
    const item = this.todoList.open.find(t => t.id === id);
    if (item) {
      this.todoList.open = this.todoList.open.filter(t => t.id !== id);
      this.todoList.resolved.push({ ...item, resolvedAt: new Date() });
    }
  }

  addTestCase(title, steps, status = 'pending') {
    const id = `TC-${this.testCases.length + 1}`;
    this.testCases.push({ id, title, steps, status, createdAt: new Date() });
    return id;
  }

  addHeuristicCandidate(jiraId, title, description, testApproach, severity = 'medium') {
    this.heuristicsCandidates.push({
      id: `HEU-${this.heuristicsCandidates.length + 1}`,
      jiraId,
      title,
      description,
      testApproach,
      severity,
      status: 'candidate',
      createdAt: new Date(),
    });
  }

  addGherkinScenario(feature, scenario, linkedTestCaseId) {
    this.gherkinScenarios.push({
      feature,
      scenario,
      linkedTestCaseId,
      createdAt: new Date(),
    });
  }

  logExecution(agentId, action, output) {
    this.executionLog.push({
      agentId,
      action,
      output,
      timestamp: new Date(),
    });
  }

  getSummary() {
    return {
      sessionId: this.sessionId,
      projectKey: this.projectKey,
      ticketKey: this.ticketKey,
      todoItems: {
        open: this.todoList.open.length,
        resolved: this.todoList.resolved.length,
        total: this.todoList.open.length + this.todoList.resolved.length,
      },
      testCases: {
        total: this.testCases.length,
        pending: this.testCases.filter(t => t.status === 'pending').length,
        automated: this.testCases.filter(t => t.status === 'automated').length,
      },
      heuristics: this.heuristicsCandidates.length,
      gherkinScenarios: this.gherkinScenarios.length,
      executionSteps: this.executionLog.length,
    };
  }

  saveToJson(baseDir = '.') {
    const filePath = `${baseDir}/qa-sessions/${this.projectKey}/${this.sessionId}.json`;
    const data = {
      sessionId: this.sessionId,
      projectKey: this.projectKey,
      ticketKey: this.ticketKey,
      todoList: this.todoList,
      testCases: this.testCases,
      heuristicsCandidates: this.heuristicsCandidates,
      gherkinScenarios: this.gherkinScenarios,
      executionLog: this.executionLog,
      stats: this.getSummary(),
    };
    return { filePath, data };
  }

  generateConfluenceDraft() {
    return {
      title: `QA Session: ${this.ticketKey}`,
      content: `# QA Session: ${this.ticketKey}\n\n...content...`,
    };
  }
}

module.exports = { SessionManager };
