# Environment Adaptation Guide

**QA Orchestrator adapts to your project's existing configuration instead of imposing new requirements.**

## Core Principle

Your project's environment configuration always takes precedence. QA Orchestrator finds and uses your existing `.env` file automatically.

## Priority Order

When starting, QA Orchestrator loads environment variables in this order (highest to lowest):

```
1. Project's .env.local (local overrides — most specific)
2. Project's .env (your existing configuration)
3. QA Orchestrator's .env (fallback for QA-specific variables)
4. process.env (system environment variables)
```

This means:
- ✅ Your project's `.env` is never overridden
- ✅ QA Orchestrator provides sensible defaults for variables it needs
- ✅ You can have project-specific overrides in `.env.local`
- ✅ Works with any project structure: monorepos, microservices, single repos

## How It Works

### 1. Project Root Detection

QA Orchestrator automatically finds your project root by searching for markers:
- `.git` (git repository)
- `package.json` (Node.js project)
- `.env` (environment file)
- `docker-compose.yml` (Docker project)
- `.qa-orchestrator.yml` (QA configuration)
- `src/` or `app/` directory

If multiple markers exist, the first match is your project root.

### 2. Environment Loading

Starting from the project root, QA Orchestrator loads:

```bash
Project Root/
├── .env           ← Loaded first (your base configuration)
├── .env.local     ← Loaded second (local overrides, not committed to git)
└── (QA fallbacks and system env are applied after)
```

### 3. Variable Precedence

```javascript
// Example: LLM_API_KEY resolution
const value = loadedEnv.LLM_API_KEY;

// Where did it come from? Check sourceMap:
sourceMap.LLM_API_KEY // → "project .env" or "QA .env" or "process.env"
```

## Setup Examples

### Example 1: Using Your Existing Project .env

**Situation:** Your project already has a `.env` with all needed variables.

```bash
my-project/
├── .env          # Has: LLM_API_KEY, API_HEALTH_ENDPOINT, etc.
├── app/
└── qa-orchestrator/
    └── .env      # Can be empty or have only QA-specific overrides
```

**Result:** QA Orchestrator uses your project's `.env` automatically. ✅ No extra setup needed!

### Example 2: Minimal Setup

**Situation:** You only have LLM_API_KEY configured, QA Orchestrator provides defaults for everything else.

```bash
my-project/
├── .env
│   LLM_API_KEY=sk-xxx
│   # Everything else uses QA Orchestrator defaults
```

**What QA Orchestrator provides:**
```env
LLM_BASE_URL=https://llm-gateway.ai.chapsvision.com/llm-gateway
LLM_MODEL=gpt-5.1-sweden
API_HEALTH_ENDPOINT=http://localhost/api/health/ready
FRONTEND_URL=http://localhost
SERVICE_START_COMMAND=task up
```

### Example 3: Project-Specific Overrides

**Situation:** Your project uses custom health endpoints and service commands.

```bash
my-project/
├── .env
│   LLM_API_KEY=sk-xxx
│   API_HEALTH_ENDPOINT=http://localhost:8000/health
│   SERVICE_START_COMMAND=docker compose up
│   FRONTEND_URL=http://localhost:3000
```

**Result:** QA Orchestrator adapts to your setup. ✅

### Example 4: Local Development Overrides

**Situation:** You need different configuration for local testing vs CI.

```bash
my-project/
├── .env             # Production/shared configuration
│   LLM_API_KEY=sk-prod
│   API_HEALTH_ENDPOINT=http://api.example.com/health
│
└── .env.local       # Local overrides (not in git)
    LLM_API_KEY=sk-dev
    API_HEALTH_ENDPOINT=http://localhost:8000/health
```

**Result:** QA Orchestrator uses `.env.local` values for local development. ✅

## Configuration Map

### Critical (Must Be Set)

| Variable | Purpose | Where to Set |
|----------|---------|--------------|
| `LLM_API_KEY` | Claude API access via LLM Gateway | Project `.env` or QA `.env` |

### LLM Gateway (Optional — Defaults Provided)

| Variable | Default | Purpose |
|----------|---------|---------|
| `LLM_BASE_URL` | `https://llm-gateway.ai.chapsvision.com/llm-gateway` | LLM Gateway endpoint |
| `LLM_MODEL` | `gpt-5.1-sweden` | Model to use |

### VCS Integration (Optional)

| Variable | Purpose | Used For |
|----------|---------|----------|
| `QA_HUB_GITLAB_HOST` | GitLab host | MR analysis, branch checkout |
| `QA_HUB_GITLAB_PORT` | GitLab SSH port | Branch operations |
| `QA_HUB_GITLAB_TOKEN` | GitLab API token | Fetching MR details |
| `GITHUB_TOKEN` | GitHub API token | GitHub PR analysis |
| `GITHUB_OWNER` | Repository owner | GitHub operations |
| `GITHUB_REPO` | Repository name | GitHub operations |

### Issue Tracker (Optional)

| Variable | Purpose |
|----------|---------|
| `JIRA_BASE_URL` | Jira instance URL |
| `JIRA_EMAIL` | Jira user email |
| `JIRA_TOKEN` | Jira API token |
| `XRAY_CLIENT_ID` | X-Ray Cloud credentials |
| `XRAY_CLIENT_SECRET` | X-Ray Cloud credentials |

### Project Adaptation (Optional — Auto-Detected)

| Variable | Auto-Detected From | Fallback |
|----------|-------------------|----------|
| `API_HEALTH_ENDPOINT` | config/projects.js or environment | `http://localhost/api/health/ready` |
| `FRONTEND_URL` | config/projects.js or environment | `http://localhost` |
| `SERVICE_START_COMMAND` | Docker Compose or environment | `task up` |
| `DOCKER_COMPOSE_FILE` | environment | `docker-compose.yml` |

## Debugging Environment Loading

### Check Where Variables Come From

QA Orchestrator logs the environment summary when starting:

```
📋 Environment Configuration:
   Project root: /home/user/my-project
   LLM: ✅ Configured
   VCS: ✅ GitLab
   Issue Tracker: ✅ Jira
   API health: ✅ Detected
```

### View Detailed Configuration

```javascript
const { EnvLoader } = require('./core/env-loader');
const loader = new EnvLoader();
loader.load(process.cwd());

// Get summary
const summary = loader.summary();
console.log(summary.environmentSources);

// Output shows where each variable was loaded from:
// {
//   'project .env': ['LLM_API_KEY', 'JIRA_TOKEN', ...],
//   'QA .env': ['LLM_BASE_URL', ...],
//   'process.env': ['PATH', 'HOME', ...]
// }
```

### Validate Configuration Before Running

```bash
# Runs validation and shows missing variables
node index.js --list-agents
```

If critical variables are missing:
```
❌ Configuration Error: Missing critical variables:
   - LLM_API_KEY

Fix: Set missing variables in .env (project or QA Orchestrator)
```

## Integration with Your Workflow

### Local Development

```bash
# Your project directory
cd /home/user/my-project

# QA Orchestrator reads your .env automatically
node tools/qa-orchestrator/index.js test TAR-1234

# Uses configuration from:
# 1. /home/user/my-project/.env.local (if exists)
# 2. /home/user/my-project/.env
# 3. /home/user/my-project/tools/qa-orchestrator/.env (if needed)
```

### Docker / CI Environment

```dockerfile
# CI pipeline
FROM node:20

WORKDIR /app
COPY .env .  # Your CI .env
COPY . .

RUN cd tools/qa-orchestrator && npm install
RUN node tools/qa-orchestrator/index.js test TAR-1234

# QA Orchestrator uses the CI .env automatically
```

### Monorepo Setup

```bash
chapsmind/
├── .env                    # Shared project config
├── .env.local             # Local overrides
├── apps/
│   ├── front/
│   ├── screen/
│   └── global-service/
├── infra/
└── tools/
    └── qa-orchestrator/
        ├── index.js       # Loads from ../../.env
        └── .env           # Fallback only
```

When you run QA Orchestrator from the monorepo root:
```bash
cd /home/user/chapsmind
node tools/qa-orchestrator/index.js test TAR-1234

# Loads configuration from:
# 1. chapsmind/.env.local
# 2. chapsmind/.env
# 3. tools/qa-orchestrator/.env (if needed)
```

## Troubleshooting

### "Missing critical variables"

**Problem:** QA Orchestrator won't start.

**Solution:** Check that `LLM_API_KEY` is set:

```bash
# Check if variable is in your project .env
grep LLM_API_KEY /home/user/my-project/.env

# Or set it temporarily
export LLM_API_KEY=sk-xxx
node index.js test TAR-1234
```

### "Services using wrong configuration"

**Problem:** Health checks fail even though your `.env` has correct values.

**Solution:** Check which file is being loaded:

```bash
# Run with environment debugging
node index.js --help 2>&1 | grep "Environment Configuration"

# Manually check what QA Orchestrator sees
node -e "
const { EnvLoader } = require('./core/env-loader');
const l = new EnvLoader();
l.load(process.cwd());
console.log('API_HEALTH_ENDPOINT:', l.loadedEnv.API_HEALTH_ENDPOINT);
console.log('Source:', l.sourceMap.API_HEALTH_ENDPOINT);
"
```

### "Local overrides not working"

**Problem:** `.env.local` changes aren't being picked up.

**Solution:** Ensure `.env.local` exists and is in the project root (same level as `.env`):

```bash
# Correct structure
my-project/
├── .env           # Base configuration
├── .env.local     # Local overrides (not in git)
└── tools/qa-orchestrator/

# Wrong structure (this won't work)
my-project/
├── tools/
│   └── qa-orchestrator/
│       └── .env.local  # ❌ Must be in project root!
```

## Best Practices

1. **Commit your project `.env` without secrets**
   ```bash
   # .env (in git)
   LLM_API_KEY=sk-xxx  # Can be shared
   API_HEALTH_ENDPOINT=http://localhost:8000/health
   
   # .env.local (NOT in git, add to .gitignore)
   # Override only what's different locally
   ```

2. **Use `.env.local` for local development**
   ```bash
   # .env.local — only for your machine
   LLM_API_KEY=sk-dev  # Your dev key
   DEBUG=qa-orchestrator  # Extra logging
   ```

3. **Let QA Orchestrator provide defaults**
   ```bash
   # Only set what's unique to your project
   # Everything else gets QA Orchestrator defaults
   ```

4. **Test configuration before running workflows**
   ```bash
   # Validate your setup
   node index.js --help 2>&1 | head -20
   ```

## Support

For issues with environment loading:
- Check `.env` file exists in your project root
- Run `node index.js --help` to see configuration summary
- Check that `LLM_API_KEY` is set
- Ensure `.env.local` is in the project root (not in subdirectories)

---

**Key Takeaway:** QA Orchestrator reads and adapts to your project's existing configuration. You don't need to change your setup or create new `.env` files — just make sure `LLM_API_KEY` is configured somewhere.
