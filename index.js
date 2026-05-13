#!/usr/bin/env node

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * QA ORCHESTRATOR — MAIN ENTRY POINT
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Usage:
 * 
 *   CLI mode:
 *     node index.js --project target --agent reviewer --message "Review TAR-1332"
 *     node index.js --project screen --workflow full-ticket --message "Test SCR-100"
 *     node index.js --project target --workflow mr-to-tests --message "Review latest MRs"
 * 
 *   Programmatic:
 *     const { createEngine } = require('./index');
 *     const engine = createEngine('target');
 *     const result = await engine.runAgent('reviewer', 'Review TAR-1332');
 *     const results = await engine.runWorkflow('full-ticket', 'Test TAR-1332');
 */

require('dotenv').config({ path: require('path').join(__dirname, '.env') });

const { QAEngine } = require('./core/engine');
const { ALL_AGENTS, workflows } = require('./agents/registry');
const { PROJECTS } = require('./config/projects');
const { JiraClient } = require('./core/jira-client');
const { GitClient } = require('./core/git-client');

// ── Context builder ───────────────────────────────────────────────────────────

async function buildTicketContext(ticketKey, project) {
  const parts = [];
  parts.push(`# QA Request: ${ticketKey}\n`);

  // Fetch Jira ticket
  try {
    const jira = new JiraClient();
    const md = await jira.getIssueMarkdown(ticketKey);
    parts.push(md);
    console.log(`  📋 Jira: ${ticketKey} fetched`);
  } catch (e) {
    parts.push(`## Ticket: ${ticketKey}\n(Jira fetch failed: ${e.message})\n`);
  }

  // Detect branch + get diff
  try {
    const localPath = project?.localPath || process.cwd();
    const git = new GitClient(localPath);
    const branch = git.findBranch(ticketKey);
    if (branch) {
      git.switchToBranch(branch);
      const diff = git.getDiff('main', 6000);
      const files = git.getChangedFiles('main');
      parts.push(`\n## Changed Files (branch: ${branch})\n${files.join('\n')}\n`);
      if (diff) parts.push(`\n## Code Diff\n\`\`\`\n${diff}\n\`\`\``);
      console.log(`  🌿 Branch: ${branch} (${files.length} files changed)`);
    } else {
      console.log(`  ⚠️  No branch found for ${ticketKey}, using main`);
    }
  } catch (e) {
    parts.push(`\n(Git context unavailable: ${e.message})\n`);
  }

  return parts.join('\n');
}

// ── Factory ──────────────────────────────────────────────────────────────────

function createEngine(projectId) {
  const engine = new QAEngine({
    llmBaseUrl: process.env.LLM_BASE_URL || 'https://llm-gateway.ai.chapsvision.com/llm-gateway',
    llmApiKey: process.env.LLM_API_KEY,
    gitlabHost: process.env.QA_HUB_GITLAB_HOST || 'git.mediaspeech.com',
    gitlabPort: parseInt(process.env.QA_HUB_GITLAB_PORT || '17890'),
    gitlabToken: process.env.QA_HUB_GITLAB_TOKEN,
  });

  // Register all agents
  for (const agent of ALL_AGENTS) {
    engine.registerAgent(agent);
  }

  // Register all workflows
  for (const wf of workflows) {
    engine.registerWorkflow(wf);
  }

  // Set project
  const project = PROJECTS[projectId];
  if (project) {
    engine.setProject(project);
  }

  return engine;
}

// ── CLI ──────────────────────────────────────────────────────────────────────

async function cli() {
  const argv = process.argv.slice(2);

  // ── Short commands: qa test|review|scan|mr|sprint [TICKET] ───────────────
  const subcommand = argv[0];
  const subArg = argv[1];

  if (subcommand === 'test' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'qa-workflow', '--message', `Test ${subArg}`, '--ticket', subArg);
  } else if (subcommand === 'review' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'quick-review', '--message', `Review ${subArg}`, '--ticket', subArg);
  } else if (subcommand === 'scan') {
    argv.splice(0, 1, '--project', 'target', '--workflow', 'scan-adapt', '--message', 'Scan project stack and recommend QA approach');
  } else if (subcommand === 'mr' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'mr-to-tests', '--message', `Analyze MR: ${subArg}`);
  } else if (subcommand === 'sprint') {
    argv.splice(0, 1, '--project', 'target', '--workflow', 'sprint-health', '--message', 'Sprint health report');
  } else if (subcommand === 'bug' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'bug-cycle', '--message', `Bug discovery for ${subArg}`, '--ticket', subArg);
  }

  const args = parseArgs(argv);

  if (args.help || (!args.agent && !args.workflow && !args['list-agents'] && !args['list-workflows'])) {
    console.log(`
QA Orchestrator — Agentic QA Testing System

Usage (short commands):
  qa test <TICKET>        Full QA cycle (review → tests → Playwright → Gherkin)
  qa review <TICKET>      Quick code review vs AC (~30s)
  qa scan                 Scan project stack
  qa mr <MR-URL>          Analyze a Merge Request
  qa sprint               Sprint health report
  qa bug <TICKET>         Bug discovery cycle

Usage (full flags):
  node index.js --project <id> --workflow <id> --message <text> [--ticket <key>]

Projects: ${Object.keys(PROJECTS).join(', ')}
Workflows: ${workflows.map(w => w.id).join(', ')}
Agents: ${ALL_AGENTS.map(a => a.id).join(', ')}

Options:
  --list-agents       List all agents with models
  --list-workflows    List all workflows with agent chains
  --help              Show this help
    `);
    return;
  }

  if (args['list-agents']) {
    console.log('\nAgents:\n');
    for (const a of ALL_AGENTS) {
      const modelTag = a.model.includes('opus') ? '🟡 Opus' : '🟢 Sonnet';
      console.log(`  ${a.icon} ${a.id.padEnd(18)} ${modelTag.padEnd(12)} ${a.useMCP ? '🔗 MCP' : '      '} ${a.name}`);
    }
    console.log(`\nTotal: ${ALL_AGENTS.length} agents (${ALL_AGENTS.filter(a => a.model.includes('opus')).length} Opus, ${ALL_AGENTS.filter(a => a.model.includes('sonnet')).length} Sonnet)`);
    return;
  }

  if (args['list-workflows']) {
    console.log('\nWorkflows:\n');
    for (const w of workflows) {
      console.log(`  ${w.name}`);
      console.log(`    ${w.description}`);
      console.log(`    Chain: ${w.agents.join(' → ')}\n`);
    }
    return;
  }

  const projectId = args.project || 'target';
  const engine = createEngine(projectId);

  if (!PROJECTS[projectId]) {
    console.error(`Unknown project: ${projectId}. Available: ${Object.keys(PROJECTS).join(', ')}`);
    process.exit(1);
  }

  // Logging
  engine.on('agent:start', (data) => {
    const agent = ALL_AGENTS.find(a => a.id === data.agentId);
    console.log(`\n${'═'.repeat(60)}`);
    console.log(`${agent?.icon || '?'} ${agent?.name || data.agentId} (${agent?.model?.includes('opus') ? 'Opus' : 'Sonnet'})`);
    console.log(`${'═'.repeat(60)}`);
  });

  engine.on('agent:done', (data) => {
    console.log(`\n${data.output}`);
    console.log(`\n⏱ ${data.duration}ms | Tools: ${data.tools?.map(t => t.name).join(', ') || 'none'}`);
  });

  engine.on('agent:error', (data) => {
    console.error(`\n❌ Error: ${data.error}`);
  });

  engine.on('workflow:step', (data) => {
    console.log(`\n▸ Step ${data.step}/${data.total}: ${data.agentId}`);
  });

  engine.on('workflow:parallel', (data) => {
    const agentNames = data.agents.map(id => ALL_AGENTS.find(a => a.id === id)?.name || id).join(' + ');
    console.log(`\n⚡ Running in parallel: ${agentNames}`);
  });

  engine.on('gitlab:fetched', (data) => {
    console.log(`📦 GitLab: ${data.count} MRs fetched, ${data.detailed} detailed`);
  });

  engine.on('xray:feature-saved', (data) => console.log(`💾 X-Ray: .feature saved → ${data.path}`));
  engine.on('xray:import-start',  (data) => console.log(`🔗 X-Ray: importing Gherkin for ${data.ticketKey} (project ${data.projectKey})...`));
  engine.on('xray:import-done',   (data) => console.log(`✅ X-Ray: ${data.count} test(s) created/updated → ${data.testKeys.join(', ')}`));
  engine.on('xray:execution-created', (data) => console.log(`🎯 X-Ray: Test Execution ${data.execKey} (${data.testCount} tests)`));
  engine.on('xray:skip',          (data) => console.log(`⏭  X-Ray: skipped — ${data.reason}`));
  engine.on('xray:error',         (data) => console.warn(`⚠️  X-Ray: ${data.error}`));

  // Build rich context for ticket-based workflows
  let message = args.message || 'Hello';
  if (args.ticket && (args.workflow === 'qa-workflow' || args.workflow === 'quick-review' || args.workflow === 'bug-cycle')) {
    console.log(`\n🔍 Fetching context for ${args.ticket}...`);
    message = await buildTicketContext(args.ticket, PROJECTS[projectId]);
  }

  try {
    if (args.workflow) {
      console.log(`\n🔄 Running workflow: ${args.workflow} on ${PROJECTS[projectId].name}`);
      await engine.runWorkflow(args.workflow, message, { ticketKey: args.ticket });
    } else if (args.agent) {
      console.log(`\n🤖 Running agent: ${args.agent} on ${PROJECTS[projectId].name}`);
      await engine.runAgent(args.agent, message);
    }
  } catch (err) {
    console.error(`\n💥 Fatal: ${err.message}`);
    process.exit(1);
  }
}

function parseArgs(argv) {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    if (argv[i].startsWith('--')) {
      const key = argv[i].slice(2);
      const next = argv[i + 1];
      if (next && !next.startsWith('--')) {
        args[key] = next;
        i++;
      } else {
        args[key] = true;
      }
    }
  }
  return args;
}

// ── Run ──────────────────────────────────────────────────────────────────────

if (require.main === module) {
  cli().catch(err => {
    console.error(err);
    process.exit(1);
  });
}

module.exports = { createEngine, PROJECTS, ALL_AGENTS, workflows };
