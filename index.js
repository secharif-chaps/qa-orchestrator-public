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

require('dotenv').config();

const { QAEngine } = require('./core/engine');
const { ALL_AGENTS, workflows } = require('./agents/registry');
const { PROJECTS } = require('./config/projects');

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
  const args = parseArgs(process.argv.slice(2));

  if (args.help || (!args.agent && !args.workflow && !args["list-agents"] && !args["list-workflows"])) {
    console.log(`
QA Orchestrator — Agentic QA Testing System

Usage:
  node index.js [options]

Options:
  --project <id>      Project to use: ${Object.keys(PROJECTS).join(', ')}
  --agent <id>        Run single agent: ${ALL_AGENTS.map(a => a.id).join(', ')}
  --workflow <id>     Run workflow: ${workflows.map(w => w.id).join(', ')}
  --message <text>    Message to send to the agent/workflow
  --ticket <key>      Ticket key (e.g., TAR-1332)
  --list-agents       List all agents with models
  --list-workflows    List all workflows with agent chains
  --help              Show this help

Examples:
  node index.js --project target --agent reviewer --message "Review TAR-1332"
  node index.js --project target --workflow full-ticket --message "Test TAR-1332" --ticket TAR-1332
  node index.js --project screen --workflow scan-adapt --message "Scan and recommend"
  node index.js --project target --workflow mr-to-tests --message "Analyze latest MRs"
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

  engine.on('gitlab:fetched', (data) => {
    console.log(`📦 GitLab: ${data.count} MRs fetched, ${data.detailed} detailed`);
  });

  const message = args.message || 'Hello';

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

module.exports = { createEngine, PROJECTS, ALL_AGENTS, workflows, cli };
