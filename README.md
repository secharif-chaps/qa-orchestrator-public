# QA Orchestrator — Multi-Agent QA System

9 specialized AI agents that handle the full QA lifecycle: from MR analysis
to X-Ray test management to Playwright automation.

## Quick Start

```bash
npm install
cp .env.example .env
# Fill in your Anthropic API key and GitLab token

# List agents and their models
node index.js --list-agents

# List available workflows
node index.js --list-workflows

# Run a single agent
node index.js --project target --agent reviewer --message "Review TAR-1332"

# Run a full workflow
node index.js --project target --workflow full-ticket \
  --message "Test TAR-1332" --ticket TAR-1332
```

## Agents

| Agent | Model | Why | What it does |
|-------|-------|-----|-------------|
| ⬡ Orchestrator | **Opus** | Intent analysis, multi-step planning | Routes tasks to correct agents, coordinates workflows |
| ⏣ Scanner | Sonnet | File parsing, pattern matching | Scans any repo → detects stack, tests, routes, components |
| ⎔ MR Analyzer | Sonnet | Diff parsing, summarization | Fetches GitLab MRs, pre-testing functional review |
| ◈ Code Reviewer | **Opus** | Deep AC↔code reasoning | Compares code vs acceptance criteria, finds edge cases |
| ◉ Bug Hunter | Sonnet | Structured template output | Creates bug tickets in Jira with full repro steps |
| ◆ Test Generator | **Opus** | X-Ray logic, multi-source | Creates/updates X-Ray tests, test plans, Confluence |
| ⬢ Automator | Sonnet | Fast code generation | Writes Playwright E2E + integration tests |
| ◇ Validator | Sonnet | Checklist execution | Manual validation, Jira comments, ticket transitions |
| ⬟ Project Manager | Sonnet | JQL + metric aggregation | Sprint health, coverage, bug trends |

**3 agents on Opus** = complex reasoning (Orchestrator, Code Reviewer, Test Generator)
**6 agents on Sonnet** = fast structured output (Scanner, MR Analyzer, Bug Hunter, Automator, Validator, PM)

## Workflows

| Workflow | Chain | When to use |
|----------|-------|-------------|
| ⏣ Scan & Adapt | Scanner → PM | First time on a new project |
| ⎔ MR → Review → Tests | MR Analyzer → Reviewer → TestGen → Automator | Before testing sprint |
| ⟐ Full Ticket QA | Reviewer → TestGen → Automator → Validator | Ticket ready for QA |
| ◉ Bug Discovery | BugHunter → TestGen → Automator → PM | Bug found during testing |
| ◆ X-Ray Sync | TestGen → PM | Periodic test library maintenance |
| ⬟ Sprint Health | PM → MR Analyzer → Reviewer | Sprint review prep |

## Project-Agnostic

Every agent adapts to the active project. Switch projects with `--project`:

```bash
# TARGET (staging)
node index.js --project target --agent reviewer --message "Review TAR-1456"

# SCREEN (local)
node index.js --project screen --agent reviewer --message "Review SCR-100"
```

Add new projects in `config/projects.js` — copy the template and fill in
Jira key, Git repo, env URL, and auth config.

## Programmatic Usage

```javascript
const { createEngine } = require('./index');

// Create engine for TARGET
const engine = createEngine('target');

// Listen to events
engine.on('agent:start', ({ agentId }) => console.log(`Running: ${agentId}`));
engine.on('agent:done', ({ agentId, output, duration }) => {
  console.log(`${agentId} done in ${duration}ms`);
});

// Run single agent
const result = await engine.runAgent('reviewer', 'Review TAR-1332');
console.log(result.text);

// Run workflow (agents chain automatically)
const results = await engine.runWorkflow('full-ticket', 'Test TAR-1332', {
  ticketKey: 'TAR-1332',
});

// Each result: { agentId, text, toolCalls, mcpResults, usage }
for (const r of results) {
  console.log(`${r.agentId}: ${r.text.slice(0, 100)}...`);
}

// Switch project
const { PROJECTS } = require('./config/projects');
engine.setProject(PROJECTS.screen);
await engine.runAgent('projectManager', 'Sprint health for SCREEN');
```

## File Structure

```
qa-system/
├── index.js              ← Entry point (CLI + programmatic)
├── package.json
├── .env.example
├── core/
│   └── engine.js         ← Orchestration runtime (API calls, chaining, events)
├── agents/
│   └── registry.js       ← 9 agent definitions (prompts + model assignments)
└── config/
    └── projects.js       ← TARGET, SCREEN, + template for new projects
```

## How Agents Interact with Tools

**Atlassian MCP** (Jira, X-Ray, Confluence):
Agents with `useMCP: true` get the Atlassian MCP server attached to their
API call. Claude handles the tool invocation internally — the agent prompt
tells it which MCP operations to use (getJiraIssue, createJiraIssue,
searchJiraIssuesUsingJql, addCommentToJiraIssue, transitionJiraIssue,
searchConfluenceUsingCql, createConfluencePage, updateConfluencePage).

**GitLab** (MR analysis):
The engine fetches MR data (list, diffs, commits) via GitLab REST API
and injects it as context into the MR Analyzer agent's prompt.

**Playwright** (test automation):
The Automator agent generates Playwright test code as text output.
The generated files can be saved to disk and run with `npx playwright test`.
