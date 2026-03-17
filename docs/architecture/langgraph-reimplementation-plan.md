# LangGraph Reimplementation Plan

> Self-contained guide for reimplementing the Dify+Celery → LangGraph migration on current main.
> Based on the `poc-langgraph` branch (92 files, +15K/-14K), adapted for main's current state (migrations 026-028, TAR-1180 error_details, TAR-1236 WorldCheck, TAR-1305 Pinia Colada v1).

**Related**: [ADR-0012 — LangGraph Agent System](./adr/0012-langgraph-agent-system.md)

---

## Table of Contents

- [A. What to Remove (Dify/Celery Cleanup)](#a-what-to-remove-difyCelery-cleanup)
- [B. What to Keep from Main (DO NOT Touch)](#b-what-to-keep-from-main-do-not-touch)
- [C. What to Create (New Agent System)](#c-what-to-create-new-agent-system)
- [D. Implementation Phases](#d-implementation-phases)
- [E. Key Patterns from POC to Preserve](#e-key-patterns-from-poc-to-preserve)
- [F. Configuration and Dependencies](#f-configuration-and-dependencies)

---

## A. What to Remove (Dify/Celery Cleanup)

### Files to Delete

| File | Lines | Purpose (to be removed) |
|------|-------|------------------------|
| `apps/screen/app/core/celery_app.py` | 74 | Celery application configuration |
| `apps/screen/app/core/concurrency.py` | 114 | Dify workflow concurrency control |
| `apps/screen/app/core/dify_error_config.py` | 178 | Dify error code → message mappings |
| `apps/screen/app/core/dify_error_i18n.py` | 261 | Dify error i18n translations |
| `apps/screen/app/infrastructure/dify/client.py` | 603 | Dify HTTP client |
| `apps/screen/app/infrastructure/dify/__init__.py` | — | Package marker |
| `apps/screen/app/services/chapse.py` | 685 | Dify chat service (→ replaced by ChatService) |
| `apps/screen/app/services/dify.py` | 960 | Dify workflow orchestration service |
| `apps/screen/app/services/dify_error_handler.py` | 562 | Dify error handling/mapping |
| `apps/screen/app/services/task_dependency_service.py` | 161 | Task dependency resolution |
| `apps/screen/app/services/workflow_config.py` | 63 | Workflow config CRUD |
| `apps/screen/app/workers/dify_tasks.py` | 236 | Celery tasks for Dify workflows |
| `apps/screen/app/workers/translation_tasks.py` | 362 | Celery tasks for translations |
| `apps/screen/app/api/endpoints/webhooks.py` | 562 | Dify webhook callback handlers |
| `apps/screen/app/api/endpoints/concurrency.py` | 52 | Concurrency control endpoints |
| `apps/screen/app/schemas/dify_errors.py` | 166 | Dify error response schemas |

### Services to Remove from Docker Compose

Remove from `infra/compose.yaml` and `infra/compose.local.yaml`:

| Service | Purpose |
|---------|---------|
| `screen_celery_worker` | Celery worker (entrypoint: `celery -A app.core.celery_app worker`) |
| `screen_celery_flower` | Celery monitoring UI (port 5555) |
| `rabbitmq` | Message broker (ports 5672, 15672) |

Also remove:
- `rabbitmq_data` volume
- `RABBITMQ_URL` env var from `screen` service
- `MAX_CONCURRENT_WORKFLOWS` env var from `screen` service
- `depends_on: rabbitmq` from `screen` service
- Any `tunnel` / localtunnel service if present

### Model Fields to Remove (via Migration 029)

**Tasks table**:
- Column `is_prerequisite` (boolean)
- Enum value `blocked` from `task_status_enum` (keep: `pending`, `running`, `succeeded`, `error`)
- Enum value `data_collection` from `task_type_enum` (keep: `profile`, `digital`, `timeline`, `products`, `jobs`, `csr`, `press`, `team`)

**Companies table** — remove raw knowledge columns:
- `raw_mistral_knowledge`
- `raw_gpt_knowledge`
- `raw_wikipedia_knowledge`
- `raw_scraped_website_knowledge`
- `raw_pappers_knowledge`

**Do NOT remove**: `raw_worldcheck_knowledge` (TAR-1236, migration 027)

### Tables to Drop (via Migration 029)

- `workflow_configs`
- `task_dependencies`

### Python Dependencies to Remove

```toml
# Remove from apps/screen/pyproject.toml
celery = "^5.3.4"
flower = "^2.0.1"
kombu = "^5.3.4"
dify-client = "^0.1.10"
```

### Config Settings to Remove

Remove from `apps/screen/app/core/config.py`:
```python
DIFY_API_KEY: str
DIFY_URL: str
DIFY_CHAT_API_KEY: str
RABBITMQ_URL: str
MAX_CONCURRENT_WORKFLOWS: int
```

### Router Registrations to Remove

In `apps/screen/app/api/router.py`:
- Remove webhook endpoint router
- Remove concurrency endpoint router

---

## B. What to Keep from Main (DO NOT Touch)

These elements exist on current main and must be preserved:

### 1. `error_details` Column (TAR-1180, Migration 028)

The `tasks.error_details` text column stores error information. **Reuse it** for LangGraph errors:
- When an agent returns `status: "error"`, write the error message to `error_details`
- The frontend error display already reads this column

### 2. `raw_worldcheck_knowledge` (TAR-1236, Migration 027)

WorldCheck data is ingested via a separate flow (not Dify, not LangGraph). This column and its feature flag must remain untouched.

### 3. `screen` Schema (Migration 026)

Migration 026 creates the `screen` schema. No changes needed.

### 4. Chat Methods (→ Extract to ChatService)

The current Dify chat integration in `chapse.py` handles:
- Streaming chat completions
- Conversation context management
- Company data injection into prompts

**Extract** this functionality to a new `ChatService` class that calls Azure OpenAI directly (no Dify). The SSE event format must remain compatible: `{"event": "message", "answer": chunk}` and `{"event": "message_end", ...}`.

### 5. All Pinia Colada v1 Patterns (TAR-1305)

The frontend was migrated to Pinia Colada v1 with `defineQueryOptions`. All new frontend code must follow v1 patterns:
- Use `defineQueryOptions()` for query definitions
- Use `useQuery()` with query options + parameter function
- Use `defineMutation()` + `useMutation()` for mutations

### 6. Section Persistence Pattern

`write_section_data(db, company_id, agent_name, data)` in the company section service is the single entry point for persisting agent data. The LangGraph runner calls this same function — no changes needed to the persistence layer.

### 7. SSE Infrastructure

`task_event_manager.broadcast_task_update()` and `broadcast_all_tasks_completed()` remain unchanged. The runner uses the existing SSE infrastructure.

### 8. Feature Flags

The `pappers_enabled` and `worldcheck_enabled` feature flags remain unchanged.

---

## C. What to Create (New Agent System)

### Backend: Agent Package (`apps/screen/app/agents/`)

```
apps/screen/app/agents/
├── __init__.py                  # Package marker
├── config.py                    # Constants, pricing, agent types, _build_agent_prompt() (~100 lines)
├── prompts/
│   ├── __init__.py
│   ├── planner.py               # PLANNER_SYSTEM_PROMPT
│   ├── methodology.py           # RESEARCH_METHODOLOGY (shared prefix)
│   ├── roles.py                 # _AGENT_ROLES dict
│   ├── search_targets.py        # AGENT_SEARCH_TARGETS dict
│   ├── output_formats.py        # _AGENT_OUTPUT_FORMATS dict (~250 lines)
│   └── domains.py               # AGENT_ALLOWED_DOMAINS dict
├── state.py                     # CompanyAnalysisState + AgentResult TypedDicts
├── graph.py                     # StateGraph definition, Send() routing, compilation
├── runner.py                    # CompanyAnalysisRunner: streaming, persistence, SSE
├── nodes/
│   ├── __init__.py
│   ├── base.py                  # Shared run_agent() used by all 9 agents
│   ├── planner.py               # Website recon + company brief builder
│   ├── synthesizer.py           # Quality gate + retry routing
│   ├── profile.py               # Company profile agent
│   ├── digital.py               # Digital presence agent
│   ├── press.py                 # Press coverage agent
│   ├── jobs.py                  # Job market agent
│   ├── products.py              # Products & services agent
│   ├── timeline.py              # Key events agent
│   ├── csr.py                   # CSR & sustainability agent
│   ├── team.py                  # Leadership team agent
│   └── financial.py             # Financial data agent (NEW)
└── tools/
    ├── __init__.py
    └── web_search.py            # Azure AI Foundry Responses API wrapper
```

#### File Descriptions

**`config.py`** (~100 lines):
- `AZURE_DEPLOYMENT`: model name from settings
- `MAX_AGENT_RETRIES = 1`: single retry via synthesizer
- `AGENT_TIMEOUT_SECONDS = 120`: per-agent timeout (configurable per agent)
- `GLOBAL_ANALYSIS_TIMEOUT_SECONDS = 900`: safeguard for full pipeline
- `MAX_CONCURRENT_API_CALLS = 5`: semaphore limit for rate limit resilience
- `ALL_AGENT_TYPES`: list of 9 agent names
- `_build_agent_prompt(agent_name) -> str`: assembles prompt from `prompts/` submodules
- `AGENT_PROMPTS`: compiled prompts dict (built at import time)

**`prompts/`** directory (~400 lines total):
- `planner.py`: `PLANNER_SYSTEM_PROMPT` — instructs LLM to explore company website, return JSON with summary/industry/pages/people/brands
- `methodology.py`: `RESEARCH_METHODOLOGY` — shared prefix for all agent prompts (5-level source trust hierarchy)
- `roles.py`: `_AGENT_ROLES` — one-liner role per agent
- `search_targets.py`: `AGENT_SEARCH_TARGETS` — per-agent text describing WHERE to look
- `output_formats.py`: `_AGENT_OUTPUT_FORMATS` — per-agent JSON schema instructions (SourcedValue pattern, ~250 lines)
- `domains.py`: `AGENT_ALLOWED_DOMAINS` — per-agent domain whitelists with `{company_domain}` placeholder

**`state.py`** (~50 lines):
- `AgentResult(TypedDict)`: agent_name, status, data, sources, error, input_tokens, output_tokens, duration_ms
- `_merge_agent_results(existing, new)`: append-only reducer
- `CompanyAnalysisState(TypedDict)`: full state with Annotated reducer on agent_results

**`graph.py`** (~80 lines):
- `AGENT_NODE_MAP`: maps agent names to node functions
- `_route_to_agents(state) -> list[Send]`: creates Send per agent in agents_to_run
- `_route_after_synthesis(state) -> list[Send] | str`: retry or END
- `build_analysis_graph() -> CompiledStateGraph`: builds full graph
- `analysis_graph`: module-level compiled singleton

**`runner.py`** (~200 lines):
- `CompanyAnalysisRunner` class with `run()` and `run_single_agent()` methods
- `run()`: marks tasks RUNNING, streams via `astream_events(version="v2")`, intercepts `on_chain_end` for `agent_*` nodes, persists results progressively
- `run_single_agent()`: runs a single agent outside the graph (for individual task restart)
- Private methods: `_persist_agent_result`, `_update_task_status`, `_broadcast_result`, `_broadcast_completion`, `_mark_tasks_errored`
- Cost calculation: `(input_tokens / 1_000_000) * 1.25 + (output_tokens / 1_000_000) * 10.00` (v1); future: use API `usage.cost` field directly for multi-model accuracy

**`nodes/base.py`** (~100 lines):
- `run_agent(agent_name, company_name, website, company_brief, country_code) -> AgentResult`
- `_extract_domain(website)`: strips www., handles ://
- `_infer_country_code(website)`: TLD → country code map (27 entries)
- `_build_allowed_domains(agent_name, company_domain)`: replaces `{company_domain}` placeholder

**`nodes/planner.py`** (~120 lines):
- `planner_node(state) -> dict`: calls web_search, builds company brief, returns agents_to_run
- `_build_company_brief(planner_data, website) -> str`: multi-line text brief with discovered pages, people, brands

**`nodes/synthesizer.py`** (~80 lines):
- `synthesizer_node(state) -> dict`: counts results, checks retry eligibility, calculates totals
- Returns: quality_issues, agents_to_retry, total_tokens, total_cost

**`nodes/<agent>.py`** (9 files, ~15 lines each):
Each agent node follows the identical pattern:
```python
async def run_<agent>_agent(state: CompanyAnalysisState) -> dict:
    result = await run_agent(
        agent_name="<agent>",
        company_name=state["company_name"],
        website=state["website"],
        company_brief=state.get("company_brief"),
        country_code=state.get("country_code"),
    )
    return {"agent_results": [result]}
```

**`tools/web_search.py`** (~150 lines):
- Uses `AsyncOpenAI` with `base_url=f"{AZURE_OPENAI_ENDPOINT}/openai/v1/"`
- Lazy-initialized module-level `_client` singleton
- `web_search_query(system_prompt, user_query, agent_name, country_code, allowed_domains) -> dict`
- Tries `web_search` tool first (supports `filters.allowed_domains`), falls back to `web_search_preview` if unavailable
- Exponential backoff with jitter on HTTP 429 responses (base 2s, max 60s)
- `search_context_size` configurable per agent (default `"medium"` to reduce token consumption)
- Uses `client.responses.create(model, instructions, tools, input)`
- Extracts text from response output messages
- Extracts citation URLs from annotations
- Parses JSON via `_parse_json_response()` (handles markdown code blocks)
- Extracts additional URLs from data recursively
- Returns: `{data, sources, input_tokens, output_tokens, duration_ms}`

### Backend: ChatService (`apps/screen/app/services/chat_service.py`)

Replaces `chapse.py` (DifyService chat):

```python
class ChatService:
    """Direct Azure OpenAI chat, replaces Dify chat workflow."""

    CHAT_SYSTEM_PROMPT = "You are Chaps-e, an AI assistant for ChapsMind..."

    def __init__(self, db: Session): ...

    async def stream_chat(
        self, query, user_id, organization_id,
        conversation_id=None, company_ids=None, username=None
    ) -> AsyncGenerator[str, None]:
        """Stream chat completions with company context injection."""
        # Validates company IDs belong to org (max 3)
        # Builds context from company section data
        # Yields Dify-compatible SSE events

    def get_context(self, conversation_id, user_id) -> dict: ...
    def update_context(self, conversation_id, user_id, organization_id, company_ids) -> dict: ...
```

SSE event format (compatible with existing frontend):
```json
{"event": "message", "answer": "chunk...", "conversation_id": "..."}
{"event": "message_end", "conversation_id": "..."}
```

### Backend: Admin Agent Endpoints

**`apps/screen/app/api/endpoints/admin_agents.py`**:
- `GET /admin/agents/overview` → `AgentOverviewResponse`
  - KPIs: total_runs, total_executions, total_cost, success_rate, avg_cost_per_run, avg_latency_seconds
  - Per-agent performance rows
  - Cost trend data points
- `GET /admin/agents/runs` → `AgentRunsResponse`
  - Paginated per-company run details with individual agent results
- Requires `admin.organizations` role

**`apps/screen/app/schemas/admin_agents.py`**:
- `AgentOverviewKpis`
- `AgentPerformanceRow`: agent_type, total_executions, succeeded, failed, success_rate, avg_duration_seconds, avg_input_tokens, avg_output_tokens, avg_cost, total_cost
- `CostTrendPoint`: date, cost, executions
- `AgentOverviewResponse`: period, kpis, agents, cost_trend
- `AgentRunAgent`: task_id, agent_type, status, error, input_tokens, output_tokens, cost, duration_seconds
- `AgentRunItem`: company_id, company_name, organization_id, run_status, total_agents, succeeded/failed/running/pending counts, total_cost, duration_seconds, agents list
- `AgentRunsResponse`: items, total, page, size, pages

### Backend: Config Changes

Add to `apps/screen/app/core/config.py`:
```python
AZURE_OPENAI_API_KEY: Optional[str] = None
AZURE_OPENAI_ENDPOINT: str = "https://chapsmind.cognitiveservices.azure.com"
AZURE_OPENAI_API_VERSION: str = "2024-05-01-preview"
AZURE_OPENAI_DEPLOYMENT: str = "gpt-5.1"
```

### Backend: CompanyService Integration

Modify `apps/screen/app/services/company.py`:
- Replace Dify workflow calls with `CompanyAnalysisRunner.run()`
- Replace individual task restart with `CompanyAnalysisRunner.run_single_agent()`
- Run analysis in `asyncio.create_task()` (background, non-blocking)

### Backend: Migrations

**Migration 029** — Dify/Celery cleanup:
```python
# 1. Delete data_collection tasks
DELETE FROM tasks WHERE type = 'data_collection'

# 2. Drop tables
DROP TABLE task_dependencies
DROP TABLE workflow_configs

# 3. Remove columns
ALTER TABLE tasks DROP COLUMN is_prerequisite
ALTER TABLE companies DROP COLUMN raw_mistral_knowledge
ALTER TABLE companies DROP COLUMN raw_gpt_knowledge
ALTER TABLE companies DROP COLUMN raw_wikipedia_knowledge
ALTER TABLE companies DROP COLUMN raw_scraped_website_knowledge
ALTER TABLE companies DROP COLUMN raw_pappers_knowledge

# 4. Rebuild enums (remove 'blocked' from status, 'data_collection' from type)
# Use temp enum → rename pattern for PostgreSQL
```

**Migration 030** — Financial agent:
```python
# 1. Add 'financial' to task_type_enum

# 2. Create company_financial table (1:1 with companies)
# Columns: id, company_id (FK), revenue, revenue_source, employee_count, ...

# 3. Create child tables:
#    - company_core_divisions (company_financial_id FK)
#    - company_key_platforms (company_financial_id FK)
#    - company_sectors (company_financial_id FK)
#    - company_investors (company_financial_id FK)
#    - company_recent_developments (company_financial_id FK)

# 4. Recreate materialized views (task_type_cost_summary, organization_cost_summary)
```

### Frontend: Agent Observability Dashboard

**New files**:

| File | Description |
|------|-------------|
| `apps/front/src/types/agent-observability.ts` | TypeScript types for all observability data |
| `apps/front/src/api/agent-observability.ts` | API functions (getAgentOverview, getAgentRuns) |
| `apps/front/src/queries/agent-observability.ts` | Pinia Colada query definitions |
| `apps/front/src/pages/admin/agents.vue` | Admin agent observability page |
| `apps/front/src/components/admin/agents/AgentOverviewKpis.vue` | KPI cards (total runs, cost, success rate) |
| `apps/front/src/components/admin/agents/AgentPerformanceTable.vue` | Per-agent performance table |
| `apps/front/src/components/admin/agents/AgentRecentRuns.vue` | Recent runs list with expandable details |
| `apps/front/src/components/admin/agents/AgentRunDetail.vue` | Single run detail (per-agent breakdown) |
| `apps/front/src/components/admin/agents/AgentRunRow.vue` | Run row component with status indicators |

**Types** (`agent-observability.ts`):
```typescript
interface AgentOverviewResponse {
  period: string
  kpis: AgentOverviewKpis
  agents: AgentPerformanceRow[]
  cost_trend: CostTrendPoint[]
}

interface AgentRunsResponse {
  items: AgentRunItem[]
  total: number
  page: number
  size: number
  pages: number
}

type RunStatus = 'all_succeeded' | 'partial' | 'all_failed' | 'running'
```

**Route** (`pages/admin/agents.vue`):
```yaml
meta:
  permissions:
    - admin.organizations
  requiresAuth: true
  title: 'Agent Observability'
```

**Modified files**:
- `apps/front/src/pages/admin/(admin).vue` — Add "Agents" nav link
- `apps/front/src/types/task.ts` — Add `financial` to TaskType enum, remove `data_collection`

### Frontend: Financial Section Page

| File | Description |
|------|-------------|
| `apps/front/src/pages/[folderId]/companies/[companyId]/financial.vue` | Financial section page (~441 lines) |
| `apps/front/src/components/company/SectionModal.vue` | Update to include financial section |

### Frontend: i18n Updates

Add translation keys for:
- Financial section labels and descriptions
- Agent observability dashboard labels
- Agent status messages
- Cost formatting

---

## D. Implementation Phases

### Phase 1: Cleanup + Migration 029

**Goal**: Remove all Dify/Celery infrastructure from main.

1. Delete all files listed in [Section A: Files to Delete](#files-to-delete)
2. Remove Celery/Dify services from compose files
3. Remove Python dependencies (`celery`, `flower`, `kombu`, `dify-client`)
4. Remove config settings (`DIFY_*`, `RABBITMQ_URL`, `MAX_CONCURRENT_WORKFLOWS`)
5. Remove router registrations (webhooks, concurrency)
6. Remove any imports/references to deleted modules throughout the codebase
7. Write migration 029 (cleanup tables, columns, enums)
8. Update SQLAlchemy models to remove deleted columns/relationships
9. Verify: `task screen:lint`, `task screen:test`, app starts without errors

### Phase 2: Agent Core (config, state, graph, web_search tool)

**Goal**: Establish the agent framework without any business logic.

1. Create `apps/screen/app/agents/` package with `prompts/` subdirectory
2. Add Azure OpenAI config settings to `config.py`
3. Add Azure env vars to compose files
4. Implement `tools/web_search.py` (Azure AI Foundry Responses API wrapper with backoff + semaphore)
5. Implement `state.py` (TypedDicts + append-only reducer)
6. Implement `prompts/` submodules (planner, methodology, roles, search_targets, output_formats, domains)
7. Implement `config.py` (constants, `_build_agent_prompt()`, compiled `AGENT_PROMPTS` dict)
8. Implement `graph.py` (empty graph structure with Send routing)
9. Configure `langgraph-checkpoint-postgres` for crash recovery (connect to existing PostgreSQL)
10. Verify: import succeeds, graph compiles, checkpoint store connects

### Phase 3: Agent Nodes (planner, 9 agents, synthesizer)

**Goal**: Implement all node functions.

1. Implement `nodes/base.py` (shared `run_agent()`, domain extraction, country inference)
2. Implement `nodes/planner.py` (website recon + brief builder)
3. Implement 9 agent nodes (profile, digital, press, jobs, products, timeline, csr, team, financial) — all follow identical pattern
4. Implement `nodes/synthesizer.py` (quality gate + retry)
5. Wire all nodes into `graph.py`
6. Verify: graph compiles with all nodes, manual test with a single company

### Phase 4: Runner + CompanyService Integration

**Goal**: Connect the graph to the existing application flow.

1. Implement `runner.py` (CompanyAnalysisRunner)
   - `run()`: full analysis with streaming + progressive persistence
   - `run_single_agent()`: individual task restart
2. Implement `ChatService` (extract from old chapse.py, adapt to Azure OpenAI)
3. Modify `CompanyService`:
   - Replace Dify workflow calls with `CompanyAnalysisRunner.run()`
   - Replace individual task restart with `run_single_agent()`
   - Run in `asyncio.create_task()` for non-blocking execution
4. Update chat endpoints to use `ChatService`
5. Verify: end-to-end test — create company → tasks run → sections populated → SSE updates arrive

### Phase 5: Migration 030 (Financial Tables) + Section Service

**Goal**: Add the financial agent's database structure.

1. Write migration 030 (financial enum value + tables)
2. Create/update SQLAlchemy models for `company_financial` and child tables
3. Update `write_section_data()` to handle the `financial` agent name
4. Create Pydantic schemas for financial section
5. Add financial section API endpoint (if needed)
6. Verify: financial agent data persists correctly

### Phase 6: Admin Observability (Backend + Frontend)

**Goal**: Build the agent monitoring dashboard.

1. Create `schemas/admin_agents.py` (all Pydantic schemas)
2. Create `api/endpoints/admin_agents.py` (overview + runs endpoints)
3. Register admin agent router
4. Create frontend types, API functions, and Pinia Colada queries
5. Build admin page with KPI cards, performance table, and run list
6. Update admin nav to include "Agents" link
7. Add i18n translations
8. Verify: admin user sees agent observability dashboard with real data

### Phase 7: Financial Section Frontend

**Goal**: Display financial data in the company view.

1. Create financial section page (`pages/[folderId]/companies/[companyId]/financial.vue`)
2. Update `SectionModal.vue` to include financial section
3. Add TaskType `financial` to frontend types
4. Remove `data_collection` from frontend TaskType enum
5. Add i18n translations for financial labels
6. Verify: financial section renders with data from agent

### Phase 8: Testing + Manual Verification

**Goal**: Comprehensive testing of the full pipeline.

1. Write unit tests for:
   - `_parse_json_response()` in web_search.py
   - `_merge_agent_results()` reducer
   - `_extract_domain()`, `_infer_country_code()`, `_build_allowed_domains()` in base.py
   - `_build_company_brief()` in planner.py
   - Synthesizer retry logic
2. Write integration tests for:
   - Graph compilation and routing
   - Runner with mocked agents
   - ChatService streaming
3. Manual testing via Playwright MCP:
   - Create a company → verify all 9 tasks appear
   - Watch progressive updates in UI
   - Check all section pages populate
   - Verify financial section displays
   - Test individual task restart
   - Test chat with company context
   - Login as admin → check observability dashboard
4. Verify no regressions:
   - WorldCheck data still displays correctly
   - Error display still works (error_details)
   - Permission checks intact
   - All existing company sections still render

### Production Migration Strategy

Production has minimal activity, so the migration can be deployed during a maintenance window (e.g., lunch break):

1. **Pre-migration**: Mark any `running` or `pending` tasks as `error` with `error_details = "Migration: analysis interrupted by system upgrade"` — prevents orphaned tasks
2. **Deploy**: Apply migrations 029 + 030, deploy new code
3. **Post-migration**: Re-launch affected company analyses manually (companies with interrupted tasks)
4. **Existing data**: Completed tasks and section data are preserved (data lives in section tables, not in removed columns)

---

## E. Key Patterns from POC to Preserve

### 1. Azure AI Foundry Responses API with `web_search` Tool

The POC uses the OpenAI SDK (`AsyncOpenAI`) pointed at Azure's endpoint, not `AzureOpenAI`. This is intentional — the Responses API (`client.responses.create()`) is only available through the OpenAI-compatible endpoint:

```python
client = AsyncOpenAI(
    api_key=settings.AZURE_OPENAI_API_KEY,
    base_url=f"{settings.AZURE_OPENAI_ENDPOINT}/openai/v1/",
)

response = await client.responses.create(
    model=AZURE_DEPLOYMENT,
    instructions=system_prompt,
    tools=[tool_config],    # {"type": "web_search", ...}
    input=user_query,
)
```

The `web_search` tool is a **built-in** server-side tool — the model calls it internally, no function-calling loop needed. Falls back to `web_search_preview` if `web_search` is unavailable on the deployment.

### 2. `Send()` Fan-Out for True Parallelism

```python
def _route_to_agents(state: CompanyAnalysisState) -> list[Send]:
    return [
        Send(f"agent_{agent_name}", state)
        for agent_name in state["agents_to_run"]
    ]
```

LangGraph's `Send()` creates independent execution branches that run concurrently. The graph fan-in happens automatically at the synthesizer node.

### 3. Append-Only Reducer for Agent Results

```python
def _merge_agent_results(
    existing: list[AgentResult],
    new: list[AgentResult]
) -> list[AgentResult]:
    return existing + new
```

Using `Annotated[list[AgentResult], _merge_agent_results]` on the state field ensures that:
- Each agent node returns `{"agent_results": [result]}` (a list of one)
- The reducer appends it to the existing list
- Retried agents accumulate without overwriting original results

### 4. Progressive Persistence via `astream_events()` v2

```python
async for event in analysis_graph.astream_events(initial_state, version="v2"):
    if event["event"] == "on_chain_end" and event["name"].startswith("agent_"):
        agent_name = event["name"].replace("agent_", "")
        result = _extract_result(event)
        await _persist_agent_result(db, company_id, result)
        await _update_task_status(db, task, result)
        await _broadcast_result(owner_id, company_id, task, result)
```

This enables the frontend to show real-time updates as each agent finishes, without waiting for the full pipeline.

### 5. SSE Broadcasting Per-Agent Completion

The runner uses the **existing** SSE infrastructure (`task_event_manager`):
- `broadcast_task_update()` — sent when each agent completes
- `broadcast_all_tasks_completed()` — sent when synthesizer finishes

No frontend SSE changes needed.

### 6. Single Retry via Synthesizer Quality Gate

The synthesizer checks agent results and routes failed agents (with `<= MAX_AGENT_RETRIES` attempts) back for retry:

```python
def _route_after_synthesis(state: CompanyAnalysisState) -> list[Send] | str:
    if state.get("agents_to_retry"):
        return [Send(f"agent_{name}", state) for name in state["agents_to_retry"]]
    return END
```

After retry, the synthesizer runs again but won't retry a second time (attempt count exceeds `MAX_AGENT_RETRIES`).

### 7. SourcedValue Pattern

All agent outputs use the `{value: string, source: URL}` pattern for traceability:

```json
{
  "headquarters": {
    "value": "Paris, France",
    "source": "https://company.com/about"
  }
}
```

This is defined in `config.py`'s `_AGENT_OUTPUT_FORMATS` and enforced by the prompt.

### 8. Company Brief Injection

The planner's output (company brief) is injected into each agent's user query:

```
=== COMPANY BRIEF (from website exploration) ===
Summary: ...
Industry: ...
Discovered pages:
- About page: https://company.com/about
- Careers: https://company.com/careers
...
```

This gives each agent context about the company without requiring a separate web search for basic facts.

### 9. Domain Whitelisting

Each agent has allowed domains configured in `AGENT_ALLOWED_DOMAINS` with a `{company_domain}` placeholder:

```python
"profile": ["{company_domain}", "wikipedia.org", "linkedin.com", "crunchbase.com"],
"financial": ["{company_domain}", "crunchbase.com", "societe.com", "sec.gov", ...],
```

The `_build_allowed_domains()` function replaces the placeholder with the actual domain at runtime. Note: some agents (timeline, csr) have no domain restrictions.

---

## F. Configuration and Dependencies

### Python Packages to Add

```toml
# Add to apps/screen/pyproject.toml [tool.poetry.dependencies]
langgraph = "^0.4"
langchain-openai = "^0.3"
openai = "^1.68"
langgraph-checkpoint-postgres = "^2.0"   # Enabled from v1 for crash recovery
```

Also update `httpx` pin if still at `0.24.1`:
```toml
httpx = ">=0.27"
```

### Environment Variables

Add to `infra/compose.yaml` (screen service):
```yaml
AZURE_OPENAI_API_KEY: ${AZURE_OPENAI_API_KEY}
AZURE_OPENAI_ENDPOINT: ${AZURE_OPENAI_ENDPOINT:-https://chapsmind.cognitiveservices.azure.com}
AZURE_OPENAI_API_VERSION: ${AZURE_OPENAI_API_VERSION:-2024-05-01-preview}
AZURE_OPENAI_DEPLOYMENT: ${AZURE_OPENAI_DEPLOYMENT:-gpt-5.1}
```

Add to `infra/compose.local.yaml` (screen service, for local dev with OpenAI direct):
```yaml
OPENAI_API_KEY: ${OPENAI_API_KEY}
OPENAI_MODEL: ${OPENAI_MODEL:-gpt-4.1}
```

Add to `.env.example`:
```
AZURE_OPENAI_API_KEY=
AZURE_OPENAI_ENDPOINT=https://chapsmind.cognitiveservices.azure.com
AZURE_OPENAI_API_VERSION=2024-05-01-preview
AZURE_OPENAI_DEPLOYMENT=gpt-5.1
```

### Python Version

The POC requires Python 3.11+ (for `TypedDict` features). Update `pyproject.toml`:
```toml
python = "^3.11"
```

### Config Class Updates

```python
# Add to apps/screen/app/core/config.py Settings class
AZURE_OPENAI_API_KEY: Optional[str] = None
AZURE_OPENAI_ENDPOINT: str = "https://chapsmind.cognitiveservices.azure.com"
AZURE_OPENAI_API_VERSION: str = "2024-05-01-preview"
AZURE_OPENAI_DEPLOYMENT: str = "gpt-5.1"
```
