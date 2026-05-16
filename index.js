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

/**
 * Load adaptive environment: project .env > QA .env > process.env
 * This respects the project's existing configuration instead of imposing new ones
 */
const { EnvLoader } = require('./core/env-loader');
const envLoader = new EnvLoader();
envLoader.load(process.cwd());
envLoader.apply();

const { QAEngine } = require('./core/engine');
const { ALL_AGENTS, workflows, AGENT_TIERS } = require('./agents/registry');
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

// ── Test Inventory Context builder ─────────────────────────────────────────

function buildTestInventoryContext(project) {
  const parts = [];
  const { execSync } = require('child_process');

  try {
    const localPath = project?.localPath || process.cwd();

    // Scan E2E tests (canonical TAR-XXXX.spec.ts files)
    const e2eTests = execSync(
      `find ${localPath}/e2e -maxdepth 1 -name 'TAR-*.spec.ts' -o -name 'test-*.spec.ts' 2>/dev/null | sort`,
      { encoding: 'utf8' }
    ).trim().split('\n').filter(Boolean);

    // Scan Vitest unit tests in apps/front
    const vitestTests = execSync(
      `find ${localPath}/apps/front/src -name '*.test.ts' -o -name '*.spec.ts' 2>/dev/null | wc -l`,
      { encoding: 'utf8' }
    ).trim();

    // Scan Pytest tests in apps/screen
    const pytestTests = execSync(
      `find ${localPath}/apps/screen/tests -name 'test_*.py' 2>/dev/null | sort`,
      { encoding: 'utf8' }
    ).trim().split('\n').filter(Boolean);

    parts.push(`\n## Test Inventory`);
    parts.push(`### E2E Tests (Playwright)`);
    if (e2eTests.length > 0) {
      parts.push(`Found ${e2eTests.length} canonical test files:`);
      e2eTests.forEach((f, i) => {
        const basename = f.split('/').pop();
        parts.push(`  ${i + 1}. ${basename}`);
      });
    } else {
      parts.push(`No TAR-XXXX.spec.ts files found.`);
    }

    parts.push(`\n### Unit Tests (Vitest)`);
    parts.push(`Found ~${vitestTests} Vitest unit tests in apps/front/src/**/*.test.ts`);

    parts.push(`\n### Backend Tests (Pytest)`);
    if (pytestTests.length > 0) {
      parts.push(`Found ${pytestTests.length} Pytest test modules:`);
      pytestTests.slice(0, 10).forEach((f, i) => {
        const basename = f.split('/').pop();
        parts.push(`  ${i + 1}. ${basename}`);
      });
      if (pytestTests.length > 10) parts.push(`  ... and ${pytestTests.length - 10} more`);
    } else {
      parts.push(`No test_*.py files found.`);
    }

    console.log(`  📚 Test Inventory: ${e2eTests.length} E2E, ${vitestTests} Unit, ${pytestTests.length} Pytest tests`);
    return parts.join('\n');
  } catch (e) {
    console.log(`  ⚠️  Test inventory scan failed: ${e.message}`);
    return `\n## Test Inventory\n(Scan failed: ${e.message})`;
  }
}

// ── Multi-Repo Context builder ─────────────────────────────────────────────

function buildReleaseContext(engine) {
  const parts = [];
  const repos = Object.values(PROJECTS).filter(p => p.localPath);

  parts.push(`\n## Release Context — Multi-Repo Analysis`);

  for (const project of repos) {
    try {
      const git = new GitClient(project.localPath);
      const diff = git.getDiff('main', 4000);
      const files = git.getChangedFiles('main');

      parts.push(`\n### ${project.name} (${project.key})`);
      parts.push(`Changed files: ${files.length}`);
      if (files.length > 0) {
        parts.push(files.slice(0, 10).join('\n'));
        if (files.length > 10) parts.push(`... and ${files.length - 10} more files`);
      }

      if (diff) {
        parts.push(`\n**Diff (truncated):**\n\`\`\`\n${diff.slice(0, 2000)}\n\`\`\``);
      }
    } catch (e) {
      parts.push(`\n### ${project.name} — Error: ${e.message}`);
    }
  }

  console.log(`  🌍 Release Context: analyzed ${repos.length} repositories`);
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
  } else if (subcommand === 'validate' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'browser-validate', '--message', `Browser validate ${subArg}`, '--ticket', subArg);
  } else if (subcommand === 'select' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'smart-select', '--message', `Select tests for ${subArg}`, '--ticket', subArg);
  } else if (subcommand === 'release') {
    argv.splice(0, 1, '--project', 'target', '--workflow', 'release-analysis', '--message', 'Analyze release readiness across all repos');
  } else if (subcommand === 'manual' && subArg) {
    argv.splice(0, 2, '--project', 'target', '--workflow', 'manual-guide', '--message', `Create manual test guide for ${subArg}`, '--ticket', subArg);
  }

  const args = parseArgs(argv);

  if (args.help || (!args.agent && !args.workflow && !args['list-agents'] && !args['list-workflows'] && !args['list-tiers'])) {
    console.log(`
QA Orchestrator — Agentic QA Testing System

Usage (short commands):
  qa test <TICKET>        Full QA cycle (review → tests → Playwright → Gherkin → Browser Validation)
  qa review <TICKET>      Quick code review vs AC (~30s)
  qa scan                 Scan project stack
  qa mr <MR-URL>          Analyze a Merge Request
  qa sprint               Sprint health report
  qa bug <TICKET>         Bug discovery cycle
  qa validate <TICKET>    Live browser validation via Playwright (requires BROWSER_VALIDATION_ENABLED=true)
  qa select <TICKET>      Smart test selector (scan inventory + match diff)
  qa release              Release analyzer (cross-repo dependency detection)
  qa manual <TICKET>      Manual test guide (MFA, email, SMS, external integrations)

Usage (full flags):
  node index.js --project <id> --workflow <id> --message <text> [--ticket <key>] [--options]

Projects: ${Object.keys(PROJECTS).join(', ')}
Workflows: ${workflows.map(w => w.id).join(', ')}
Agents: ${ALL_AGENTS.map(a => a.id).join(', ')}

Options:
  --list-agents       List all agents with models
  --list-workflows    List all workflows with agent chains
  --list-tiers        List agents organized by usage tier
  --sampling <mode>   Execution mode: quick (100s, $0.27) | full (220s, $0.37) | deep (300s, $0.60)
  --no-cache          Disable caching (default: caching enabled, 1h TTL)
  --no-live           Skip Environment Manager (services must already be running)
  --no-learn          Skip learning system injection (agents don't learn from past failures)
  --no-gate           Skip Verification Gate (output not validated per agent)
  --fail-fast         Stop immediately on first error (default: true)
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

  if (args['list-tiers']) {
    console.log('\n╔═══════════════════════════════════════════════════════════╗');
    console.log('║          QA Orchestrator — Agent Organization by Tier     ║');
    console.log('╚═══════════════════════════════════════════════════════════╝\n');

    for (const [tierKey, tier] of Object.entries(AGENT_TIERS)) {
      console.log(`\n${tier.name}`);
      console.log(`${tier.description}\n`);

      for (const agent of tier.agents) {
        console.log(`  ${agent.icon} ${agent.id.padEnd(18)} — ${agent.description}`);
        console.log(`     Model: ${agent.model} | Use: ${agent.use}`);
      }
    }

    console.log(`\n\n📊 Total: 15 agents across 3 tiers`);
    console.log(`  🟢 Tier 1 (Daily):     4 agents  — fast, no external deps`);
    console.log(`  🟡 Tier 2 (Validation): 4 agents — requires running env`);
    console.log(`  🔴 Tier 3 (Advanced):   7 agents — complex workflows\n`);
    return;
  }

  const projectId = args.project || 'target';

  // Validate environment before creating engine
  const validation = envLoader.validate();
  if (!validation.valid) {
    console.error('\n❌ Configuration Error: Missing critical variables:');
    validation.missing.critical.forEach(v => console.error(`   - ${v}`));
    console.error('\nFix: Set missing variables in .env (project or QA Orchestrator)');
    process.exit(1);
  }

  if (validation.missing.optional.length > 0) {
    console.warn('\n⚠️  Optional variables not configured:');
    validation.missing.optional.forEach(v => console.warn(`   - ${v}`));
    console.warn('Some features may be limited. Continue anyway? (y/n)');
  }

  const engine = createEngine(projectId);

  if (!PROJECTS[projectId]) {
    console.error(`Unknown project: ${projectId}. Available: ${Object.keys(PROJECTS).join(', ')}`);
    process.exit(1);
  }

  // Show environment configuration summary
  const envSummary = envLoader.summary();
  console.log(`\n📋 Environment Configuration:`);
  console.log(`   Project root: ${envSummary.config.projectRoot}`);
  console.log(`   LLM: ${envSummary.config.llmSetup}`);
  console.log(`   VCS: ${envSummary.config.vcsSetup}`);
  console.log(`   Issue Tracker: ${envSummary.config.issueTrackerSetup}`);
  console.log(`   API health: ${envSummary.config.projectAdaptation.apiHealthEndpoint}`);
  console.log(``);

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

  // Browser Validation listeners
  engine.on('browser-validation:script-saved', (data) => console.log(`💾 Browser Validation: script saved → ${data.path}`));
  engine.on('browser-validation:execute-start', (data) => console.log(`🌐 Browser Validation: executing Playwright...`));
  engine.on('browser-validation:execute-done', (data) => console.log(`✅ Browser Validation: tests passed`));
  engine.on('browser-validation:execute-error', (data) => console.warn(`❌ Browser Validation: ${data.error}`));

  // Environment Manager listeners
  engine.on('env:checking', () => console.log(`🔍 Checking environment...`));
  engine.on('env:branch-finding', (d) => console.log(`🌿 Finding branch for ${d.ticketKey}...`));
  engine.on('env:branch-checked-out', (d) => console.log(`✅ Checked out branch: ${d.branch}`));
  engine.on('env:stashed', () => console.log(`📦 Stashed dirty working tree`));
  engine.on('env:already-running', () => console.log(`✅ Services already running`));
  engine.on('env:starting', (d) => console.log(`🐳 Starting services: ${d.command}...`));
  engine.on('env:started', () => console.log(`✅ Services started`));
  engine.on('env:health-check', (d) => console.log(`⏳ Health check: attempt ${d.attempt} (${d.elapsed}ms)`));
  engine.on('env:healthy', (d) => console.log(`✅ Environment healthy (${d.attempts} attempt(s))`));
  engine.on('env:health-timeout', (d) => console.warn(`⚠️  Environment timeout after ${d.attempts} attempts`));
  engine.on('env:ready', (d) => console.log(`🚀 Environment ready: ${d.url} | branch: ${d.branch}`));
  engine.on('env:error', (d) => console.error(`❌ Environment error: ${d.error}`));
  engine.on('env:failed', (d) => console.error(`❌ Environment setup failed: ${d.error}`));

  // Verification Gate listeners
  engine.on('gate:result', (d) => {
    const icon = d.pass ? '✅' : '❌';
    console.log(`🔒 [${d.agentId}] Gate: ${icon} ${d.score}/100 ${d.pass ? '' : `— Issues: ${d.issues.slice(0, 2).join(', ')}`}`);
  });
  engine.on('gate:retry', (d) => console.log(`🔄 Retrying ${d.agentId} (attempt ${d.attempt}/2)...`));

  // Learning System listeners
  engine.on('learning:loaded', (d) => console.log(`🧠 Learnings: ${d.agentId} has ${d.count} past failure patterns`));
  engine.on('learning:saved', (d) => console.log(`💾 Failure recorded for ${d.agentId}`));

  // Optimization (Parallel Executor) listeners
  engine.on('cache:hit', (d) => console.log(`💾 Cache HIT for ${d.ticketKey} (${d.branch}) — returned cached results in 100ms`));
  engine.on('tier:start', (d) => console.log(`🚀 Tier ${d.tier}: ${d.name}`));
  engine.on('parallel:done', (d) => console.log(`⚡ Parallel execution done: ${d.agentsRun.join(', ')} in ${d.time}s`));
  engine.on('conditional:skip', (d) => console.log(`⏭️  Skipping ${d.agentId} (trigger not met)`));

  // Build rich context for workflows
  let message = args.message || 'Hello';
  if (args.ticket && (args.workflow === 'qa-workflow' || args.workflow === 'quick-review' || args.workflow === 'bug-cycle' || args.workflow === 'browser-validate' || args.workflow === 'smart-select' || args.workflow === 'manual-guide')) {
    console.log(`\n🔍 Fetching context for ${args.ticket}...`);
    message = await buildTicketContext(args.ticket, PROJECTS[projectId]);

    // Enrich with test inventory for smart-select and mr-to-tests
    if (args.workflow === 'smart-select' || args.workflow === 'mr-to-tests') {
      message += buildTestInventoryContext(PROJECTS[projectId]);
    }
  } else if (args.workflow === 'release-analysis') {
    console.log(`\n🌍 Fetching cross-repo context...`);
    message = args.message || 'Analyze release readiness across all repositories';
    message += buildReleaseContext(engine);
  }

  try {
    if (args.workflow) {
      console.log(`\n🔄 Running workflow: ${args.workflow} on ${PROJECTS[projectId].name}`);
      const workflowOptions = {
        ticketKey: args.ticket,
        samplingMode: args.sampling || 'full', // quick | full | deep
        useCache: args['no-cache'] !== true, // default: true (caching enabled)
        noLive: args['no-live'] === true,
        noGate: args['no-gate'] === true,
        noLearn: args['no-learn'] === true,
        failFast: args['fail-fast'] !== false,
      };
      await engine.runWorkflow(args.workflow, message, workflowOptions);
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
