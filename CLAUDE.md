# Claude AI Assistant Instructions

## Agent Behavior Rules

### 1. Plan First

- Enter plan mode for any non-trivial task (3+ steps or architecture decisions)
- Write detailed specs upfront to reduce ambiguity
- If something goes wrong, stop and re-plan immediately — don't push through
- Validate the plan with the user before starting implementation
- Before implementing any visual change, file restructuring, or Jira ticket creation: describe your planned approach in 2-3 bullet points and wait for confirmation before writing any code

### 2. Sub-Agent Strategy

- Use sub-agents extensively to keep the main context window clean
- Delegate research, exploration, and parallel analysis to sub-agents
- One task per sub-agent for focused execution
- For complex problems, use more compute via parallel sub-agents

### 3. Self-Improvement Loop

- After any user correction: save a feedback memory with the lesson learned
- Write rules for yourself that prevent the same mistake
- Review relevant memories at the start of related tasks

### 4. Verify Before Completing

- Never mark a task as done without proving it works
- Run tests, check logs, demonstrate the fix
- Ask yourself: "Is this complete, correct, and ready to ship?"
- Compare behavior between main branch and your changes when relevant

### 5. Require Elegance (Balanced)

- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer

### 6. Autonomous Bug Fixing

- When given a bug report: just fix it. Don't ask for hand-holding
- Point to logs, errors, failing tests — then resolve them
- Go fix failing CI tests without being told how

---

## Project Structure

This project is a **monorepo** containing all ChapsMind applications:

```
chapsmind/                              # THIS REPO - Monorepo
├── CLAUDE.md                           # This file - Claude configuration
├── .claude/
│   ├── agents/                         # Specialized AI agents
│   ├── commands/                       # Custom slash commands
│   └── skills/                         # Shared skills
├── agent-os/                           # Agent OS configuration
│   ├── config.yml
│   ├── product/                        # Product docs (mission, roadmap, tech-stack)
│   ├── specs/                          # Feature specifications
│   └── standards/                      # Coding standards
├── docs/                               # Documentation (ADRs, architecture, product)
│
├── apps/
│   ├── front/                          # Frontend (Vue 3)
│   │   ├── src/
│   │   │   ├── api/                    # API functions (fetch wrappers)
│   │   │   ├── components/
│   │   │   │   ├── ui/                 # Base UI components
│   │   │   │   ├── layout/             # Layout components
│   │   │   │   └── features/           # Feature-specific components
│   │   │   ├── composables/            # Composition functions
│   │   │   ├── stores/                 # Pinia stores (global state)
│   │   │   ├── queries/                # Pinia Colada queries
│   │   │   ├── pages/                  # Page components (file-based routing)
│   │   │   ├── plugins/                # Vue plugins
│   │   │   ├── utils/                  # Utility functions
│   │   │   ├── assets/                 # Static assets (CSS, images)
│   │   │   ├── main.ts                 # App entry point
│   │   │   └── App.vue                 # Root component
│   │   └── public/                     # Public static files
│   │
│   ├── screen/                         # Backend (FastAPI)
│   │   ├── app/
│   │   │   ├── api/                    # API endpoints
│   │   │   ├── models/                 # SQLAlchemy models
│   │   │   ├── schemas/                # Pydantic schemas
│   │   │   ├── services/               # Business logic
│   │   │   └── core/                   # Core configuration
│   │   └── alembic/                    # Database migrations
│   │
│   └── global-service/                 # Global service / API gateway
│
├── infra/                              # Infrastructure
│   ├── compose.yaml                    # Base compose
│   ├── compose.local.yaml              # Local overrides
│   └── ...
│
├── scripts/                            # CI scripts, subtree sync
└── Taskfile.yml                        # Task runner (all commands)
```

---

## External Repositories

### QA Orchestrator
**Location:** `ssh://git@git.mediaspeech.com:17890/mint/qa-orchestrator.git`

Enterprise agentic QA testing system with live validation, self-learning, and output verification.

**Key Features (v3.0.0+):**
- 🚀 **Live Validation** — Test running code at `http://localhost`, not just diffs
- 🔒 **Verification Gate** — LLM validates each agent output (0-100 score) before chaining
- 🧠 **Learning System** — Persist failures, inject failure patterns into future prompts
- ⚙️ **Environment Manager** — Auto-start services + health polling
- 📈 **15 Agents in 3 Tiers** — Daily Use, Live Validation, Advanced
- 🔄 **4 Workflows** — qa-workflow, browser-validate, smart-select, release-analysis

**Setup:**
```bash
git clone ssh://git@git.mediaspeech.com:17890/mint/qa-orchestrator.git
cd qa-orchestrator
npm install && cp .env.example .env
node index.js --project target --workflow qa-workflow --message "Test TAR-1234"
```

**Documentation:**
- README.md — Architecture + quick start
- CONTEXT.md — 1400+ lines project reference
- CHANGELOG.md — v3.0.0 release notes

---

# ChapsMind Frontend Application

A modern Vue 3 application with TypeScript, Tailwind CSS v4, and comprehensive tooling for monitoring companies online.

## Project Overview

### User Application Workflow

**Organizations**

- Users create company cards to monitor companies' information online
- A user always belongs to an organization
- Company cards are shared within an organization

**Company Lifecycle & Tasks**

- Creating a company card triggers tasks that find specific information online via Dify workflows
- Frontend monitors task status and calls the backend to start tasks

**Key Components**: User, Company, Organization, Tasks

**User Experience**:

1. Dashboard: See stats and recent companies
2. Companies List View: Browse all companies
3. Company View: Detailed information about a company

---

## Tech Stack

- **Framework**: Vue 3 with Composition API + `<script setup lang="ts">`
- **Language**: TypeScript
- **Styling**: Tailwind CSS v4
- **UI Components**: Custom design system + Reka UI
- **State Management**: Pinia (global state)
- **Data Fetching**: Pinia Colada (queries & mutations)
- **Routing**: Vue Router + unplugin-vue-router (file-based)
- **Authentication**: Keycloak with role-based permissions
- **Architecture**: Organization-based multi-tenancy (Keycloak Organizations)

---

## Development Standards

### Core Principles

1. **ALWAYS** use Composition API with `<script setup lang="ts">`, **NEVER** Options API
2. **ALWAYS** use TypeScript, prefer `interface` over `type`
3. **ALWAYS** use Tailwind CSS classes, avoid manual CSS
4. **DO NOT** hard-code colors, use semantic color tokens → see `tailwind-styling` skill
5. **ALWAYS** use arrow functions for all functions and methods
6. **ALWAYS** prefer named exports over default exports
7. Add meaningful comments explaining **why**, not **what**

> Spacing rules, color system, responsive breakpoints: see `tailwind-styling` skill.
> Data fetching patterns (queries/mutations): see `pinia-colada` skill.
> Routing patterns: see `apps/front/src/pages/CLAUDE.md`.
> Component structure: see `apps/front/src/components/CLAUDE.md` + `vue-components` skill.
> Custom UI components (Alert, Button, Badge, Input, Card): see `frontend-ui-components` skill.
> i18n usage: see `i18n-icu-messageformat` skill.
> Git commits: see `git-commits` + `jira-conventions` skills.

### File Organization

- Keep types alongside code
- Keep tests alongside files: `Button.vue` + `Button.spec.ts`
- Use consistent PascalCase for component files
- Use kebab-case for other files

### Dev Environment

- All services run behind nginx reverse proxy at `http://localhost`
- Keycloak admin console at `http://localhost:8080`
- **NEVER** launch the dev server yourself (it's already running in Docker)
- Screen backend is **never** exposed directly — global-service is the API gateway

---

## Specialized Agents

- **Frontend Design System** (`frontend-design-system-dev`) — all frontend UI/UX, component creation, design system compliance. Location: `.claude/agents/frontend-design-system-dev.md`
- **Smart Commit** (`/commit`) — intelligent git commit grouping. Location: `.claude/commands/commit.md`

---

## Available Permissions

| Permission            | Scope  | Description               |
| --------------------- | ------ | ------------------------- |
| `company.view`        | Org    | View companies            |
| `company.create`      | Org    | Search + create companies |
| `company.update`      | Org    | Update companies          |
| `company.delete`      | Org    | Delete companies          |
| `organization.read`   | Org    | Read-only org access      |
| `organization.write`  | Org    | Manage org + team         |
| `admin.organizations` | Global | Admin all organizations   |
| `admin.tasks`         | Global | Monitor all tasks         |
| `admin.costs`         | Global | AI cost analysis          |

**Frontend composables**: `useCompanyPermissions` → `canCreateCompany`, `canViewCompany`, `canDeleteCompany`, `canManageCompanies`. Auth store: `hasPermission()`, `hasRole()`, `hasAnyRole()`, `hasAllRoles()`.

> Implementation details: see `keycloak` skill.

---

## Test Users

All defined in `infra/files/realm-chapsmind.json`, added to **ChapsMind Dev** org by `setup-keycloak.sh`.

| Username          | Password      | Roles                                                                      | Description               |
| ----------------- | ------------- | -------------------------------------------------------------------------- | ------------------------- |
| `admin`           | `admin123`    | admin (composite: all roles)                                               | Full access               |
| `company_manager` | `manager123`  | company.create, organization.read, organization.write, organization.manage | Company + team management |
| `company_viewer`  | `viewer123`   | organization.read                                                          | Read-only access          |
| `no_access`       | `noaccess123` | (none)                                                                     | For testing 403 errors    |

---

## Development Workflow

1. Plan tasks, review with user
2. Write code following project standards and skills
3. Test with Playwright MCP (navigate → interact → check JS console)
4. Stage changes once feature works
5. Use `/commit` for intelligent commit grouping

---

## Project Commands

All commands from monorepo root via Taskfile:

| Area             | Key Commands                                                                           |
| ---------------- | -------------------------------------------------------------------------------------- |
| Frontend         | `task front:build` · `task front:lint` · `task front:typecheck` · `task front:dev`     |
| Screen backend   | `task screen:test` · `task screen:lint` · `task screen:format` · `task screen:shell`   |
| Dev environment  | `task up` · `task down` · `task restart` · `task logs` · `task logs:service -- <name>` |
| Migrations       | `task migrate` · `task migrate:status`                                                 |
| First-time setup | `task init` · `task doctor`                                                            |

---

## Research & Documentation

- **NEVER** hallucinate or guess URLs
- **ALWAYS** try accessing `llms.txt` first (e.g., `https://pinia-colada.esm.dev/llms.txt`)
- **ALWAYS** follow existing links in documentation indices
- Verify examples from documentation before using

---

## i18n Key Naming

Format: `module.feature.element` in camelCase. Namespaces: `common`, `target`, `screen`, `dashboard`, `settings`, `admin`.

> Full ICU MessageFormat rules (plurals, select, pipe syntax): see `i18n-icu-messageformat` skill.

---

# Claude Code Configuration

## Kubernetes Production Environment

### Accessing Database in Kubernetes

```bash
# Find database pod name
kubectl get pods -n chapsmind | grep postgres

# Connect to database
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "YOUR_SQL_QUERY"

# List tables
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "\\dt"

# Describe table structure
kubectl exec -it <postgres-pod-name> -n chapsmind -- psql -U postgres -d chapsmind_db -c "\\d TABLE_NAME"
```

### Running Alembic Migrations in Kubernetes

```bash
# Find screen backend pod name
kubectl get pods -n chapsmind | grep screen

# Run migrations
kubectl exec -it <screen-pod-name> -n chapsmind -- alembic upgrade head

# Check current migration version
kubectl exec -it <screen-pod-name> -n chapsmind -- alembic current
```

**Important Notes:**

- Always use the `-n chapsmind` namespace flag
- Screen backend pod name typically starts with `chapsmind-screen-`
- Database pod name typically starts with `postgres-` or similar

---

## Docker Compose Commands

This project uses Taskfile to wrap Docker Compose commands. Run all commands from the monorepo root.

```bash
task init             # First-time setup
task up               # Start all services
task down             # Stop all services
task restart          # Restart all services
task logs             # Tail all logs
task logs:service -- screen   # Tail logs for a specific service

# Full reset (removes volumes)
docker compose down -v --rmi local && task init
```

### Services Available

- **nginx**: Reverse proxy — single entry point at `http://localhost`
- **frontend**: Vue.js app (internal, behind nginx)
- **global-service**: API gateway (internal, behind nginx at `/api`)
- **screen**: FastAPI backend (internal, never exposed directly)
- **db**: PostgreSQL database
- **rabbitmq**: Message broker (management UI at `http://localhost:15672`)
- **keycloak**: Auth server at `http://localhost:8080` (local dev only)

### Local Testing URLs

- **Application**: `http://localhost`
- **API**: `http://localhost/api`
- **API Docs**: `http://localhost/docs`
- **Keycloak**: `http://localhost:8080` — realm `chapsmind`
- **Integration Keycloak**: `https://sso.dwcode.team/auth` — realm `chapsmind`

---

## Database Migrations

```bash
task migrate            # Apply migrations
task migrate:status     # Check current version

# Create a new migration (must be inside container)
task screen:shell
alembic revision -m "description"
```

**Important**: Never run alembic commands locally — the database host `db` only resolves inside the Docker network.

---

## Deployment Rules

### CRITICAL: Never Copy Files Directly to Production Server

- **NEVER** use scp, ssh, or any method to directly copy files to the production server
- **NEVER** create or modify files directly on the production server
- **ALWAYS** commit and push changes, then ask user to deploy via proper deployment process

### Proper Deployment Process

1. Make changes locally and test with `task up`
2. Commit with gitmoji format
3. Push to repository
4. Ask user to deploy
5. Verify deployment worked

---

## Database Schema Guidelines

### User and Organization Reference Architecture

**CRITICAL**: This application does NOT use database tables for users or organization membership.

- **No Users Table**: There is NO `users` table in the database
- **No Organization Membership Table**: Organization membership is managed in Keycloak Organizations
- **User References**: Users are managed entirely in Keycloak — store only UUIDs

### Table Schema Rules

- `owner_id`: `VARCHAR/UUID` — Keycloak user UUID (from JWT `sub` claim)
- `owner_username`: `VARCHAR` — username denormalized for display (avoids Keycloak lookup)
- `organization_id`: `VARCHAR/UUID` — Keycloak organization UUID
- **NO workspace_members table** — organization membership is in Keycloak

### Model Relationships

- **NO foreign key relationships to users table** (doesn't exist)
- **NO foreign key relationships to organizations table** (managed in Keycloak)
- User IDs and organization IDs are simple string/UUID fields

### Migration Rules

- Never create `users` or `organization_members` tables
- Never create foreign keys to users or organizations
- Always use VARCHAR/String/UUID fields for user and organization references

### i18n — ICU MessageFormat (CRITICAL)

Uses `@messageformat/core` as custom `messageCompiler` (ADR-0012):

- Always use: `t(translationKey, { param: value })`
- Never use: `t(translationKey, 'fallback', { param: value })` — no fallback as 2nd arg
- **Pipe syntax is disabled** (`zero | one | many` does NOT work)
- Plurals: `"{count, plural, =0 {No items} one {# item} other {# items}}"`
- Select: `"{gender, select, male {assigné à} female {assignée à} other {assigné(e) à}} {name}"`
- `other` branch is mandatory in both `plural` and `select`
- French CLDR: 0 falls under `one`; use `=0` for distinct zero messages

- vuellar is our own private component lib — don't research it externally, you won't find anything

---

## Jira — Modules and Components

| Prefix      | Description                                                 | Jira Component  |
| ----------- | ----------------------------------------------------------- | --------------- |
| `[LEGACY]`  | AMI v9 / Target v9 - Maintenance (branch ST9_3)             | `Target Legacy` |
| `[GLOBAL]`  | Infrastructure, auth, cross-cutting features                | `Global`        |
| `[TARGET]`  | Competitive intelligence and strategic monitoring           | `Target`        |
| `[SCREEN]`  | Automated company cards                                     | `Screen`        |
| `[STREAM]`  | Multi-channel distribution (newsletters, API, Slack, Teams) | `Stream`        |
| `[EXPLORE]` | Data exploration as a graph                                 | `Explore`       |

### Jira Rules

- **Component**: automatically inferred from the module prefix. Always verify against the actual project list before using
- **Story = 1 single module**. If multi-module detected → create multiple linked Stories
- **Epic**: can be multi-module (combined prefixes `[TARGET][SCREEN]`)
- **Bug**: module = where the bug is DETECTED (not where it is caused)
- **PO validation required** before any Jira creation (wait for "yes"/"ok"/"go"/"validated")
- **Never estimate in story points** (that is the team's responsibility)
- **Never modify Jira statuses**

### Jira API (MCP Atlassian)

- **Descriptions**: always use **markdown** format (`contentFormat: "markdown"`), never wiki markup
- **Priority**: use ID strings (`"10007"`), never names (`"Medium"`)
- **Components**: verify exact names via `getVisibleJiraProjects` or the table above before setting them
- **Jira**: project `TAR`

### Skill Labels

| Label    | Usage                                                                  |
| -------- | ---------------------------------------------------------------------- |
| `Back`   | API, services, database, infrastructure                                |
| `Front`  | UI, components, UX                                                     |
| `IA`     | AI, LLM, LangGraph agents, prompts, workflows n8n/Dify                 |
| `DevOps` | CI/CD, Docker, Kubernetes, Helm, infrastructure, déploiement           |
| `Data`   | Pipelines de données, OpenSearch, indexation, enrichissement, collecte |

Combinations: `Back + IA`, `Front + IA`, `Back + Front` (rare), `Back + DevOps`, `Back + Data`

### Epic Phases

```
Phase 0: Design (Figma mockups, UI specs)
Phase 1: Foundation (architecture, base components)
Phase 2: Back (services, API, integrations)
Phase 3: Front (interface, final UX)
```

### Terminology

```
Dossier de veille -> Watchfile (English term, ALWAYS one word — preserves case/number:
                                watchfile / Watchfile / watchfiles / Watchfiles —
                                NEVER "watch file" / "Watch File")
                     WatchFile / watchFile only as code identifier (PascalCase / camelCase)
Acteur            -> Actor
Source            -> Source
```
