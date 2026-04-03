/**
 * Agent Registry — All 11 Agents + 7 Workflows
 * v1.1.0: Added SessionManager (◎) and GherkinWriter (⬡)
 */

const AGENTS = {
  orchestrator: {
    name: 'Orchestrator',
    model: 'claude-opus-4-6',
    useMCP: false,
    prompt: 'You are the QA orchestration agent...',
  },
  scanner: {
    name: 'Scanner',
    model: 'claude-sonnet-4-6',
    useMCP: false,
    prompt: 'You are the code scanner...',
  },
  mrAnalyzer: {
    name: 'MR Analyzer',
    model: 'claude-sonnet-4-6',
    useMCP: false,
    prompt: 'You are the MR analyzer...',
  },
  reviewer: {
    name: 'Code Reviewer',
    model: 'claude-opus-4-6',
    useMCP: true,
    prompt: 'You are the code reviewer...',
  },
  bugHunter: {
    name: 'Bug Hunter',
    model: 'claude-sonnet-4-6',
    useMCP: true,
    prompt: 'You are the bug hunter...',
  },
  testGenerator: {
    name: 'Test Generator',
    model: 'claude-opus-4-6',
    useMCP: true,
    prompt: 'You are the test case generator...',
  },
  automator: {
    name: 'Automator',
    model: 'claude-sonnet-4-6',
    useMCP: false,
    prompt: 'You are the test automation agent...',
  },
  validator: {
    name: 'Validator',
    model: 'claude-sonnet-4-6',
    useMCP: true,
    prompt: 'You are the validation agent...',
  },
  projectManager: {
    name: 'Project Manager',
    model: 'claude-sonnet-4-6',
    useMCP: true,
    prompt: 'You are the project manager...',
  },
  // NEW AGENTS v1.1.0
  sessionManager: {
    name: 'SessionManager',
    model: 'claude-sonnet-4-6',
    useMCP: true,
    prompt: 'You manage QA sessions, track TODO items, and persist session data.',
  },
  gherkinWriter: {
    name: 'GherkinWriter',
    model: 'claude-sonnet-4-6',
    useMCP: true,
    prompt: 'You generate Gherkin BDD scenarios from test cases.',
  },
};

const WORKFLOWS = {
  'qa-workflow': {
    name: 'Full QA Session',
    agents: ['reviewer', 'testGenerator', 'automator', 'validator', 'sessionManager', 'gherkinWriter'],
    description: 'Main workflow: structured 9-phase QA methodology',
    isMainWorkflow: true,
  },
  'scan-adapt': {
    name: 'Scan & Adapt',
    agents: ['scanner', 'projectManager'],
    description: 'First discovery on new project',
  },
  'mr-to-tests': {
    name: 'MR → Review → Tests',
    agents: ['mrAnalyzer', 'reviewer', 'testGenerator', 'automator'],
    description: 'MR analysis to test generation',
  },
  'full-ticket': {
    name: 'Full Ticket QA',
    agents: ['reviewer', 'testGenerator', 'automator', 'validator'],
    description: '[DEPRECATED] Use qa-workflow instead',
    isDeprecated: true,
  },
  'bug-cycle': {
    name: 'Bug Discovery',
    agents: ['bugHunter', 'testGenerator', 'automator', 'projectManager'],
    description: 'Bug discovery and validation',
  },
  'xray-sync': {
    name: 'X-Ray Sync',
    agents: ['testGenerator', 'projectManager'],
    description: 'Test library maintenance',
  },
  'sprint-health': {
    name: 'Sprint Health',
    agents: ['projectManager', 'mrAnalyzer', 'reviewer'],
    description: 'Sprint health check',
  },
};

const ALL_AGENTS = Object.keys(AGENTS);

module.exports = {
  AGENTS,
  WORKFLOWS,
  ALL_AGENTS,
};
