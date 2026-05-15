# QA Orchestrator — Project Context

This single file centralizes all configuration for QA Orchestrator, ensuring agents access consistent project metadata.

## Project Overview

**Name:** ChapsMind QA Platform  
**Type:** Multi-repository SaaS (monorepo + satellite repos)  
**QA Orchestrator:** AI-powered agentic QA system (15 agents, 10 workflows)  
**Jira Project:** TAR (Target) + SCR (Screen)

---

## Repositories

### 1. chapsmind/chapsmind (Primary — Monorepo)

**Path:** `/home/samiecharif/workspace/chapsmind`  
**Jira Key:** TAR  
**Git Remote:** git.mediaspeech.com:17890/chapsmind/chapsmind

**Apps:**
- `apps/front/` — Vue 3 + TypeScript + Playwright E2E
- `apps/screen/` — FastAPI + SQLAlchemy + Pytest
- `apps/global-service/` — API Gateway
- `apps/stream/` — Distribution service
- `apps/target/` — Legacy (maintenance only)

**Test Inventory:**
- E2E: `e2e/*.spec.ts` (Playwright, 23 canonical TAR-XXXX files)
- Unit (Frontend): `apps/front/src/**/*.test.ts` (Vitest)
- Unit (Backend): `apps/screen/tests/test_*.py` (Pytest)
- Fixtures: `e2e/helpers/`, `apps/front/test-fixtures/`

**Key Files:**
- `Taskfile.yml` — all commands: `task front:test`, `task screen:test`, etc.
- `infra/compose.yaml` — service stack
- `apps/front/src/pages/CLAUDE.md` — routing conventions
- `apps/front/src/components/CLAUDE.md` — component structure

---

### 2. chapsmind/chapsmind-screen (Satellite Backend)

**Path:** `/home/samiecharif/workspace/chapsmind/apps/screen`  
**Jira Key:** SCR  
**Git Remote:** git.mediaspeech.com:17890/chapsmind/chapsmind-screen

**Stack:** FastAPI + SQLAlchemy + Alembic (DB migrations) + Pytest + Pydantic

**Tests:**
- Unit: `tests/test_*.py`
- Fixtures: `tests/fixtures/`, `conftest.py`
- DB: integration tests with live SQLite

---

## Acceptance Criteria Format

**Platform:** Jira  
**Format:** AC-1, AC-2, ... (numbered list in Jira description)

**Example:**
```
AC-1: User can search companies by name
AC-2: Search results show name + logo + valuation
AC-3: Pagination works with > 100 results
```

**QA Coverage:** Every AC must have >= 2 test cases (happy + negative)

---

## Environment Variables

| Variable | Purpose | Default | Set By |
|----------|---------|---------|--------|
| `LLM_BASE_URL` | LLM Gateway (OpenAI-compatible) | `https://llm-gateway.ai.chapsvision.com/llm-gateway` | .env |
| `LLM_API_KEY` | Gateway API key | — | .env (required) |
| `LLM_MODEL` | Model override for all agents | `gpt-5.1-sweden` | .env |
| `QA_HUB_GITLAB_HOST` | GitLab host | `git.mediaspeech.com` | .env |
| `QA_HUB_GITLAB_PORT` | GitLab port | `17890` | .env |
| `QA_HUB_GITLAB_TOKEN` | GitLab API token | — | .env (required for MR workflows) |
| `JIRA_BASE_URL` | Jira Cloud URL | `https://chapsvisiondev.atlassian.net` | .env |
| `JIRA_EMAIL` | Jira user email | — | .env (required) |
| `JIRA_TOKEN` | Jira API token | — | .env (required) |
| `XRAY_CLIENT_ID` | X-Ray Cloud client ID | — | .env (required for Gherkin import) |
| `XRAY_CLIENT_SECRET` | X-Ray Cloud secret | — | .env (required for Gherkin import) |
| `PLAYWRIGHT_BASE_URL` | E2E base URL | `http://localhost` | .env |
| `PLAYWRIGHT_TIMEOUT` | E2E request timeout (ms) | `30000` | .env |
| `INTERNAL_JWT_SECRET` | HS256 signing secret (internal API auth) | — | .env (required for API tests) |
| `API_BASE_URL` | Backend API base | `http://localhost/api` | .env |
| `TEST_ORG_ID` | Test org UUID | `12345678-1234-5678-9abc-def012345678` | .env |
| `TEST_USER_ID` | Test user ID | `user-test-001` | .env |
| `BROWSER_VALIDATION_ENABLED` | Enable live Playwright execution | `false` | .env (set to `true` for qa validate) |
| `CI` | CI mode flag (Docker/GitHub Actions) | unset | CI system |

---

## Test Users

All defined in `infra/files/realm-chapsmind.json`, managed by `setup-keycloak.sh`.

| Username | Password | Roles | Use Case |
|----------|----------|-------|----------|
| `admin` | `admin123` | admin (all roles) | Full access testing |
| `company_manager` | `manager123` | company.create, org.read, org.write | Company + team mgmt |
| `company_viewer` | `viewer123` | organization.read | Read-only access |
| `no_access` | `noaccess123` | (none) | Permission rejection testing |

**Authentication:**
- **UI Login:** Keycloak (realm: `chapsmind`, client: `Basile-PWA`)
- **API Tests:** HS256 JWT via `INTERNAL_JWT_SECRET` (headers: `Authorization: Internal <token>`)

---

## Services & Ports

### Local Development (`task up`)

```
http://localhost           ← Nginx reverse proxy (all services behind)
  ├── /                    → apps/front (Vue 3)
  ├── /api                 → apps/global-service (API Gateway)
  └── /docs               → FastAPI OpenAPI (apps/screen)

http://localhost:8080     ← Keycloak admin console
  realm: chapsmind
  client: Basile-PWA

http://localhost:15672    ← RabbitMQ management UI
  username: guest / guest

http://localhost:5432     ← PostgreSQL (internal only)
```

### Staging URLs

- **Frontend:** https://main.staging.target.localnet
- **API:** https://api.staging.target.localnet
- **Keycloak:** https://sso.dwcode.team/auth (realm: chapsmind)

---

## Test Infrastructure

### E2E Tests (Playwright)

**Location:** `/home/samiecharif/workspace/chapsmind/e2e/`  
**Config:** `e2e/playwright.config.ts`  
**Run:** `npm run test:e2e` or `npx playwright test`

**Canonical Test Files (Jira-ticket-named):**
- TAR-1241.spec.ts — Delete Company Modal
- TAR-1295.spec.ts — Watch Files / Internal API
- TAR-1406.spec.ts — Financial Metric Card
- TAR-1410.spec.ts — Public Company View
- TAR-1439.spec.ts — Online Presence
- TAR-1569.spec.ts — Token Endpoints
- ... and 17 more

**Selectors:** data-testid (preferred) → ARIA roles → visible text

**Auth in E2E:** 
- UI login via Keycloak: `await page.goto('http://localhost'); await loginViaKeycloak(page, 'admin', 'admin123');`
- API auth: `const token = createInternalToken({...});`

### Vitest (Frontend Unit Tests)

**Location:** `apps/front/src/**/*.test.ts`  
**Run:** `npm run test:unit` (from `apps/front/`)

### Pytest (Backend Unit + Integration)

**Location:** `apps/screen/tests/test_*.py`  
**Run:** `pytest apps/screen/tests/` or `task screen:test`

---

## QA Orchestrator Commands

### Short Syntax

```bash
# Tier 1 (Daily Use)
qa review <TICKET>       # Code review vs AC (~30s)
qa test <TICKET>         # Full QA cycle (~5m)
qa select <TICKET>       # Smart test selector (~1m)
qa bug <TICKET>          # Bug discovery (~2m)

# Tier 2 (Validation)
qa validate <TICKET>     # Browser validation (requires BROWSER_VALIDATION_ENABLED=true + app running)
qa manual <TICKET>       # Manual test guide (MFA, email, SMS)

# Tier 3 (Advanced)
qa scan                  # Tech stack detection
qa mr <MR-URL>           # Merge Request analysis
qa sprint                # Sprint health report
qa release               # Cross-repo dependency analysis

# Metadata
qa --list-agents         # List 15 agents with models
qa --list-workflows      # List 10 workflows
qa --list-tiers          # List agents organized by tier
qa --help                # Show help
```

### Full Syntax

```bash
node index.js --project target --workflow qa-workflow --message "Test TAR-1234" --ticket TAR-1234
node index.js --project target --agent reviewer --message "Review this code"
```

---

## Cross-Repo Dependencies

### Frontend → Backend

| FE Component | BE Endpoint | Method | AC |
|---|---|---|---|
| CompanyCard | `/api/companies/{id}` | GET | Must return camelCase JSON |
| SearchBox | `/api/companies/search` | POST | Pagination: limit + offset |
| OnlinePresence | `/api/companies/{id}/online-presence` | GET | Per-source social links |

### Backend → RabbitMQ

| Event | Message Type | Consumers |
|---|---|---|
| `company.created` | CompanyCreatedEvent | N8N workflows |
| `company.updated` | CompanyUpdatedEvent | OpenSearch indexing |
| `task.completed` | TaskCompletedEvent | Frontend notifications |

### Alembic Migrations

- **Location:** `apps/screen/alembic/versions/`
- **Strategy:** Backward-compatible only (no breaking schema changes without deprecation period)
- **How to run:** `task migrate` (from monorepo root) or `alembic upgrade head` (in container)

---

## Deployment Order (Release Analyzer)

1. **Backend first** (`apps/screen`): migrations + API changes
2. **Global Service** (`apps/global-service`): if consuming new API fields
3. **Frontend** (`apps/front`): UI changes + new API calls

**Never deploy Frontend → Backend** in that order (FE will call non-existent endpoints).

---

## X-Ray Integration

**Project Key:** TAR  
**Cloud URL:** https://app.getxray.app  
**API:** Jira Cloud REST + X-Ray Cloud v2

**Capabilities:**
- Auto-create test cases from Gherkin features
- Create test executions from Playwright results
- Sync Jira issue links ↔ test case mappings

---

## Jira Components & Labels

**QA Labels (use in stories/epics):**
- `Back` — backend/API/database changes
- `Front` — UI/UX/component changes
- `IA` — AI/LLM/LangGraph/N8N workflows
- `DevOps` — CI/CD/Docker/Kubernetes
- `Data` — data pipelines/OpenSearch/indexing

**Bug Module Rule:** Module = where bug is DETECTED, not caused
- User sees broken UI → module = Frontend
- API returns wrong data → module = Backend
- Search results wrong → module = Frontend (even if issue is OpenSearch indexing)

---

## ADRs (Architecture Decision Records)

- ADR-0012: i18n uses `@messageformat/core` with ICU MessageFormat (no fallback in vue-i18n)
- ADR-0016: Documentation strategy (docs/ + CLAUDE.md per directory)

---

## Key Contacts & References

- **Jira Board:** https://chapsvisiondev.atlassian.net/jira/software/c/projects/TAR/
- **Confluence Space:** QCD (QA Cycle Documentation)
- **X-Ray Project:** TAR (tests + test plans + executions)
- **Git Repos:**
  - Main: git.mediaspeech.com:17890/chapsmind/chapsmind
  - Screen: git.mediaspeech.com:17890/chapsmind/chapsmind-screen
- **Slack Channels:** #qcd (QA), #ta (Technical Architecture)

---

## Last Updated

- **Date:** 2026-05-15
- **Updated By:** QA Orchestrator Feature Expansion (3 new agents + tiers)
- **Next Review:** After first release cycle with new agents
