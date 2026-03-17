# ADR-0012: LangGraph Agent System

## Status

**Accepted** — 2026-03-13

**Supersedes**: [ADR-0004 — Dify for AI Orchestration](./0004-dify-ai-orchestration.md)

## Context

ChapsMind uses AI-driven workflows to research companies and populate structured data sections (profile, digital presence, press, jobs, products, timeline, CSR, team). Until now, this was orchestrated via **Dify** (external workflow engine) with **Celery + RabbitMQ** for async execution and **webhooks** for result ingestion.

### Problems with Dify + Celery

1. **Fragile webhook pipeline** — Dify calls back to our API via webhooks; any network issue, timeout, or Dify bug silently drops results. TAR-1180 introduced `error_details` to surface Dify errors, but the root cause (external dependency) remains.
2. **No retry control** — Celery retries are coarse-grained (retry the whole task); we cannot selectively retry a single agent that returned low-quality data.
3. **Prompt iteration friction** — Prompts live in Dify's UI, not in version control. Changing a prompt requires manual edits in Dify, and there is no diff/review process.
4. **Infrastructure overhead** — Running RabbitMQ, Celery workers, Celery Flower, and a localtunnel (for Dify webhooks in dev) adds operational complexity for a pipeline that is fundamentally request-response.
5. **Cost opacity** — Token usage and cost are tracked in Dify's dashboard, not in our database. Aggregating costs per agent, per company, or per organization requires manual cross-referencing.
6. **No parallelism control** — Dify runs agents sequentially; Celery can parallelize tasks but not coordinate a fan-out/fan-in pattern with a quality gate.
7. **Vendor lock-in** — All orchestration logic is encoded in Dify's proprietary workflow format, making migration expensive.

### Why LangGraph

[LangGraph](https://langchain-ai.github.io/langgraph/) is a library for building stateful, multi-agent graphs on top of LangChain. It provides:

- **Native Python** — Prompts, tools, and orchestration live in our codebase, version-controlled and testable.
- **`Send()` fan-out** — True parallel execution of independent agents via `Send()` API, with automatic fan-in at the synthesizer node.
- **Conditional routing** — The synthesizer node can inspect results and route failed agents back for a single retry, without restarting the entire pipeline.
- **Streaming events** — `astream_events(version="v2")` emits per-node lifecycle events, enabling progressive persistence and real-time SSE broadcasting.
- **Checkpoint support** — `langgraph-checkpoint-postgres` enables durable state and crash recovery from v1.

### Decision Drivers

- Eliminate webhook fragility and external service dependency
- Version-control all prompts and orchestration logic
- Enable per-agent retry with quality gate
- Track token usage and cost per agent in our database
- Reduce infrastructure to just FastAPI + PostgreSQL
- Add a 9th agent (financial) as part of the migration
- Preserve existing chat functionality (DifyService chat → ChatService)

## Decision

Replace Dify + Celery with a **LangGraph-based agent system** running inside the FastAPI process. The system uses the **Azure AI Foundry Responses API** with the `web_search` built-in tool for grounded web research.

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    FastAPI Process                        │
│                                                          │
│  CompanyService.start_analysis()                         │
│         │                                                │
│         ▼                                                │
│  CompanyAnalysisRunner.run()                             │
│         │                                                │
│         ▼                                                │
│  ┌─────────────┐                                         │
│  │   Planner    │  ← web_search: explore company website │
│  └──────┬──────┘                                         │
│         │ Send() fan-out                                 │
│         ▼                                                │
│  ┌──────────────────────────────────────────────┐        │
│  │  profile │ digital │ press │ jobs │ products  │        │
│  │  timeline│ csr     │ team  │ financial        │        │
│  └──────────────────┬───────────────────────────┘        │
│                     │ fan-in                              │
│                     ▼                                     │
│  ┌──────────────┐                                        │
│  │  Synthesizer  │  ← quality gate + optional retry      │
│  └──────┬───────┘                                        │
│         │                                                │
│         ▼                                                │
│  Progressive persistence + SSE broadcast                 │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 9 Research Agents

| Agent | Section | Purpose |
|-------|---------|---------|
| `profile` | Company profile | General info, industry, HQ, size, description |
| `digital` | Digital presence | Website, social media, tech stack |
| `press` | Press coverage | News articles, press releases |
| `jobs` | Job market | Open positions, hiring trends |
| `products` | Products & services | Offerings, pricing, market segments |
| `timeline` | Key events | Milestones, M&A, funding rounds |
| `csr` | CSR & sustainability | ESG initiatives, certifications |
| `team` | Leadership team | Key people, board members |
| `financial` | Financial data | Revenue, funding, investors, divisions |

### Key Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| `config.py` | `app/agents/config.py` | Constants, pricing, agent types, `_build_agent_prompt()` (~100 lines) |
| `prompts/` | `app/agents/prompts/` | Prompt modules: `planner.py`, `methodology.py`, `roles.py`, `search_targets.py`, `output_formats.py`, `domains.py` |
| `state.py` | `app/agents/state.py` | `CompanyAnalysisState` TypedDict with append-only reducer |
| `graph.py` | `app/agents/graph.py` | LangGraph `StateGraph` definition and compilation |
| `runner.py` | `app/agents/runner.py` | `CompanyAnalysisRunner` — entry point, streaming, DB writes |
| `nodes/base.py` | `app/agents/nodes/base.py` | Shared `run_agent()` function |
| `nodes/planner.py` | `app/agents/nodes/planner.py` | Website reconnaissance + brief builder |
| `nodes/synthesizer.py` | `app/agents/nodes/synthesizer.py` | Quality gate + retry routing |
| `nodes/<agent>.py` | `app/agents/nodes/*.py` | 9 specialized agent nodes |
| `tools/web_search.py` | `app/agents/tools/web_search.py` | Azure AI Foundry Responses API wrapper |

### State Design

```python
class AgentResult(TypedDict):
    agent_name: str         # "profile", "digital", etc.
    status: str             # "success" | "error"
    data: dict              # Structured section data
    sources: list[str]      # Referenced URLs
    error: str | None
    input_tokens: int
    output_tokens: int
    duration_ms: int

class CompanyAnalysisState(TypedDict):
    company_id: int
    company_name: str
    website: str
    organization_id: str
    owner_id: str
    country_code: str | None
    company_brief: str | None
    agents_to_run: list[str]
    agent_results: Annotated[list[AgentResult], _merge_agent_results]  # append-only
    quality_issues: list[str]
    agents_to_retry: list[str]
    total_tokens: int
    total_cost: float
```

### Graph Topology

```
START → planner → [Send() fan-out per agent] → synthesizer → END
                                                    ↓ (conditional)
                                              retry agents → synthesizer
```

- **Planner** explores the company website via `web_search`, builds a brief, and determines which agents to run (currently always all 9).
- **Agent nodes** run in true parallel via `Send()`, each calling `run_agent()` with agent-specific prompts and domain whitelists.
- **Synthesizer** inspects all results, calculates aggregate cost/tokens, and optionally routes failed agents for a single retry (`MAX_AGENT_RETRIES = 1`).

### Progressive Persistence

The runner uses `astream_events(version="v2")` to intercept per-node completion:

1. When an agent node completes → persist section data via `write_section_data()`, update task status, broadcast SSE event
2. When synthesizer completes → broadcast `all_tasks_completed` SSE event
3. On exception → mark all running tasks as `ERROR` with `error_details`

This means the frontend sees real-time updates as each agent finishes, identical to the current behavior.

### Timeout & Resilience

Each agent has a per-agent timeout (configurable, default **120 seconds**) applied to its `responses.create()` call. A global analysis timeout acts as a safeguard for the full pipeline.

- Agent exceeding its timeout → marked as `ERROR` with timeout details in `error_details`; other agents continue unaffected
- Global timeout → all remaining agents marked as `ERROR`, synthesizer skipped
- Timeout values are configurable per agent in `config.py` (some agents like `financial` may need longer)

### Rate Limit Resilience

Azure OpenAI enforces per-minute token (TPM) and request (RPM) limits. The system handles this at multiple levels:

- **`search_context_size`**: configurable per agent (default `"medium"` instead of `"high"`) to reduce token consumption per call
- **Exponential backoff with jitter**: `web_search.py` retries HTTP 429 responses with exponential backoff (base 2s, max 60s, jitter ±30%)
- **Concurrency semaphore**: `asyncio.Semaphore` caps concurrent API calls (configurable, default 5) to avoid burst-triggered rate limits

### Chat Functionality

The Dify chat integration (`DifyService` / `chapse.py`) is replaced by a standalone `ChatService` that calls Azure OpenAI directly with streaming. The SSE event format remains compatible with the frontend (`{"event": "message", "answer": ...}`).

### Error Handling

The `error_details` column (added in migration 028, TAR-1180) is reused for LangGraph errors. When an agent fails, the error message and traceback are stored in `error_details` on the corresponding task row, maintaining compatibility with the existing error display UI.

### WorldCheck Compatibility

The `raw_worldcheck_knowledge` column (added in migration 027, TAR-1236) is preserved. WorldCheck data is ingested via a separate flow (not through Dify/LangGraph) and remains unchanged.

## Alternatives Considered

### 1. Keep Dify, Improve Error Handling

Continue using Dify with better webhook retry logic, error mapping, and monitoring.

**Rejected because**: Does not address fundamental issues — prompts outside version control, infrastructure overhead, no per-agent retry, cost opacity. TAR-1180 was a band-aid; the root cause is architectural.

### 2. CrewAI

Multi-agent framework with role-based agents and task delegation.

**Rejected because**: Higher abstraction layer, less control over graph topology and streaming. No native `Send()` equivalent for true parallelism. Smaller ecosystem for checkpoint/persistence.

### 3. Raw LangChain (no LangGraph)

Use LangChain chains/agents directly without the graph layer.

**Rejected because**: No built-in fan-out/fan-in, no conditional routing, no checkpoint support. Would require reimplementing graph semantics manually.

### 4. Custom asyncio Orchestration

Build a custom async pipeline using `asyncio.gather()` with manual state management.

**Rejected because**: Reinvents graph semantics (retries, conditional routing, state reduction). LangGraph provides these primitives with a tested, maintained codebase.

## Consequences

### Positive

- **All orchestration in code** — Prompts, routing, and retry logic are version-controlled, testable, and reviewable.
- **Reduced infrastructure** — Removes RabbitMQ, Celery workers, Celery Flower, localtunnel. Only FastAPI + PostgreSQL remain. Specifically:
  - **Translations**: Already use `FastAPI BackgroundTasks` (not Celery) — `translation_tasks.py` is dead code to delete
  - **Chat**: Separate concern covered by [ADR-0013](./0013-chat-service-replacement.md) (ChatService replaces Dify chat)
  - **Periodic cleanup tasks**: Verify if any use Celery; if so, migrate to BackgroundTasks or cron
  - **Net result**: ADR-0012 (agents) + ADR-0013 (chat) + dead code cleanup = complete Dify+Celery removal
- **Per-agent cost tracking** — `input_tokens`, `output_tokens`, and `total_cost` stored per task row.
- **Quality gate** — Synthesizer enables single-retry for failed agents without restarting the pipeline.
- **True parallelism** — `Send()` runs all 9 agents concurrently; analysis completes in ~wall-clock of the slowest agent, not sum of all.
- **Progressive UX** — `astream_events()` enables real-time per-agent updates identical to current SSE behavior.
- **New financial agent** — 9th agent added as part of the migration, with dedicated database tables.
- **Admin observability** — New dashboard showing per-agent performance, cost trends, and run details.

### Negative

- **In-process execution** — Agents run inside FastAPI; a crash affects the API server. Mitigated by: (a) async execution in background tasks, (b) exception handling in runner, (c) checkpoint-based crash recovery from v1 via `langgraph-checkpoint-postgres`.
- **Azure AI dependency** — Tightly coupled to Azure AI Foundry Responses API (`web_search` tool). Mitigated by: tool abstraction in `web_search.py`, can swap to other providers.
- **Migration complexity** — Requires careful cleanup of Dify/Celery infrastructure (migrations 029+) while preserving error_details (028) and WorldCheck (027) columns.

### Future: Agent Tooling

With Dify, agents were limited to a single capability: calling an LLM with a prompt. There was no mechanism to give agents actual tools — functions they could invoke to interact with databases, APIs, or perform computations. The orchestration was essentially "prompt in, text out" with no intermediate actions.

LangGraph removes this limitation entirely. Because agents are Python functions, any agent can be equipped with **LangChain tools** — typed, callable functions the LLM can invoke mid-reasoning. This opens a class of capabilities that was simply not possible before.

#### Why This Matters

Today, all 9 agents rely exclusively on `web_search` to gather information. This means:
- They can only find what's publicly indexed and returned by search
- They cannot cross-reference with data we already have in our database
- They cannot call external structured APIs (business registries, financial data providers)
- They cannot perform calculations, validate data, or interact with internal systems

With tool-equipped agents, each agent becomes an **autonomous actor** that can reason about which tools to use, in what order, to produce the best result.

#### Tool Ideas

| Tool | Available To | Description |
|------|-------------|-------------|
| **`db_lookup`** | All agents | Query existing company data in our database — avoid redundant research, detect changes vs. last analysis |
| **`company_registry`** | `profile`, `financial` | Query official business registries (INSEE/Sirene for France, Companies House UK, SEC EDGAR) for verified legal data |
| **`linkedin_api`** | `team`, `jobs`, `profile` | Fetch structured employee data, open positions, and company details via LinkedIn API |
| **`financial_api`** | `financial` | Pull structured financial data from providers (Pappers, Societe.com, or open APIs) instead of scraping search results |
| **`pdf_reader`** | `financial`, `csr`, `press` | Download and extract text from PDF documents (annual reports, ESG reports, press releases) |
| **`screenshot`** | `digital` | Capture website screenshots for visual analysis (tech stack detection, UX assessment) |
| **`calculator`** | `financial` | Perform financial calculations (growth rates, ratios, unit conversions) with precision instead of asking the LLM to do math |
| **`worldcheck_lookup`** | `profile`, `team` | Cross-reference people and entities against our existing WorldCheck data |
| **`diff_detector`** | All agents | Compare current findings with previous analysis results to highlight what changed since last run |
| **`geocoder`** | `profile` | Resolve addresses to coordinates and normalized country/city data |

#### Implementation Approach

LangGraph makes tool integration straightforward — tools are defined as Python functions with type annotations, and the LLM decides when to call them:

```python
from langchain_core.tools import tool

@tool
def db_lookup(company_id: int, section: str) -> dict:
    """Fetch existing data for a company section from our database."""
    ...

@tool
def company_registry(siren: str) -> dict:
    """Query French business registry (Sirene) for official company data."""
    ...
```

Tools are then attached per-agent in `config.py`, so each agent only sees the tools relevant to its task. The `run_agent()` function in `nodes/base.py` already accepts a tools parameter — extending it to include custom tools alongside `web_search` is minimal work.

### Future: Multi-Model Strategy

Not all agents need the same model. Lightweight agents (e.g., `jobs`, `digital`) could use a smaller, cheaper model while complex agents (e.g., `financial`, `profile`) benefit from GPT-5.1:

- Per-agent `model` field in `config.py` (defaults to `AZURE_OPENAI_DEPLOYMENT`, overridable per agent)
- Use the API response `usage.cost` field directly for cost tracking instead of hardcoded `PRICE_PER_M_INPUT` / `PRICE_PER_M_OUTPUT` constants (future-proofs against model price changes)
- Explore cost/quality tradeoff per agent type based on production data from the observability dashboard

### Risks

- **Memory pressure** — 9 concurrent agents per company analysis, each holding response data. Monitor memory usage under load.
- **Rate limiting** — Azure OpenAI has per-minute token limits. Mitigated by exponential backoff in `web_search.py` and concurrency semaphore (see Rate Limit Resilience).
- **Prompt drift** — Moving prompts to code means PR reviews must cover prompt quality. Establish prompt review guidelines.

## Production Migration Strategy

Production has minimal activity, so the migration can be deployed during a maintenance window (e.g., lunch break):

1. **Pre-migration**: Mark any `running` or `pending` tasks as `error` with `error_details = "Migration: analysis interrupted by system upgrade"` — prevents orphaned tasks that no worker will ever pick up
2. **Deploy**: Apply migrations 029 (Dify/Celery cleanup) and 030 (financial tables)
   - Migration 029 deletes `data_collection` tasks, drops `blocked` status, removes old columns
   - Migration 030 adds `financial` enum value and tables
3. **Post-migration**: Re-launch affected company analyses manually (companies with interrupted tasks)
4. **Existing data**: Completed tasks and section data are preserved as-is (data lives in section tables like `company_profile`, `company_digital`, etc., not in the removed columns)

## Database Migrations

Migration numbering accounts for existing main-branch migrations (026-028):

- **Migration 029** — Cleanup: remove Dify/Celery infrastructure (workflow_configs table, task_dependencies table, is_prerequisite column, blocked status, data_collection type, raw_*_knowledge columns except worldcheck)
- **Migration 030** — Add financial agent: new task type enum value, `company_financial` table and child tables

## Dependencies

### Python Packages

```toml
langgraph = "^0.4"
langchain-openai = "^0.3"
openai = "^1.68"
langgraph-checkpoint-postgres = "^2.0"   # Enabled from v1 for crash recovery
```

### Environment Variables

```
AZURE_OPENAI_API_KEY       # Required
AZURE_OPENAI_ENDPOINT      # Default: https://chapsmind.cognitiveservices.azure.com
AZURE_OPENAI_API_VERSION   # Default: 2024-05-01-preview
AZURE_OPENAI_DEPLOYMENT    # Default: gpt-5.1
```

## Tags

`backend`, `ai`, `orchestration`, `langgraph`, `agents`
