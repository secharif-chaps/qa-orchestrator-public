# ADR-2026-008: Multi-Provider Collection Architecture

| Status | Date       | Author                    |
| ------ | ---------- | ------------------------- |
| Draft  | 2026-02-06 | Frédéric Fayard-Le Barzic |

## Context

Target uses **Bakus** as its sole collection provider for web scraping, RSS feeds, YouTube, Reddit, and 80+ source
types. This creates a critical vendor dependency: if Bakus becomes unavailable, changes its API, or its pricing model
evolves unfavorably, the entire collection pipeline stops.

### Current Architecture

```
Domain/Collect/
├── ProviderGatewayInterface.php          # 4 methods: createTask, cancelTask, getTaskStatus, getCollectors
├── CollectDataHandlerInterface.php       # Chain of Responsibility for event processing
├── CollectTask.php                       # Entity with providerName field (hardcoded to 'bakus')
├── Stream/
│   ├── CollectDataPullStreamInterface.php
│   └── CollectDataPushStreamInterface.php
└── Auth/AuthenticationClientInterface.php

Infrastructure/Collect/
├── Bakus/
│   ├── BakusProviderGateway.php          # Implements ProviderGatewayInterface + getDocumentContent()
│   ├── BakusCollectTaskMapper.php        # Task mapping + status mapping + callback building
│   ├── CollectorFactory.php              # Bakus-specific collector creation
│   └── Stream/                           # WebSocket client/server
├── MergedResultCollectDataHandler.php    # Depends on BakusProviderGateway (concrete class!)
└── QueryStatusCollectDataHandler.php     # Depends on BakusCollectTaskMapper (concrete class!)
```

### Identified Problems

1. **Tight coupling in handlers**: `MergedResultCollectDataHandler` injects `BakusProviderGateway` (concrete class)
   because `getDocumentContent()` is not in the interface. `QueryStatusCollectDataHandler` injects
   `BakusCollectTaskMapper` for status mapping.

2. **Hardcoded provider name**: `CreateCollectTaskHandler` creates all `CollectTask` with `providerName: 'bakus'`.

3. **Bakus-specific handlers in generic namespace**: `MergedResultCollectDataHandler` processes Bakus field names
   (`raw_id`, `hash_document_sha1`, `document_origin`) but lives in `Infrastructure/Collect/` instead of
   `Infrastructure/Collect/Bakus/`.

4. **Bakus-specific event types**: `CollectDataReceivedEvent::DOCUMENT_TYPES` hardcodes Bakus type strings.

5. **No provider selection mechanism**: No way to choose a provider per source type.

### Scale

- 50M documents/year (~140k docs/day)
- 80+ source types
- Volume: RSS/Web 45%, YouTube 25%, Reddit 15%, Domains 10%, Other 5%

## Decision Drivers

- **Reduce vendor lock-in** by enabling multiple collection providers
- **Minimize premature abstraction**: build only what is needed for the next provider
- **Explicit coupling over hidden coupling**: Bakus-specific code must be visibly Bakus-specific
- **Backward compatibility**: existing Bakus flow must continue unchanged
- **Provider-aware observability** from day one

## Considered Options

### Option A: Enrich ProviderGatewayInterface + ProviderRouter (Strategy Pattern)

Add `getDocumentContent()` to the Domain interface. Create a `ProviderRouter` that routes to the correct provider.

**Pros:**

- Clean single abstraction, transparent to callers
- Natural fit for circuit breaker

**Cons:**

- `getDocumentContent()` bakes a Bakus-shaped model into the Domain (fetch by SHA1 hash). Webhook providers deliver
  content inline — this method is not universal.
- `cancelTask(taskId)` / `getTaskStatus(taskId)` have no provider context — router must look up the entity.
- Strategy pattern is YAGNI at 2 providers.
- Refactoring handlers to be provider-agnostic requires a normalization layer for Bakus-specific fields.

### Option B: Per-Provider Handler Packages + Tagged Service Locator (Selected)

Keep `ProviderGatewayInterface` unchanged. Move Bakus handlers into the Bakus namespace. Each provider brings its own
Gateway, StatusMapper, and handlers. A tagged service locator resolves providers at runtime.

```
Infrastructure/Collect/
├── ProviderGatewayLocator.php            # ServiceLocator resolves by providerName
├── Bakus/
│   ├── BakusProviderGateway.php          # Unchanged, keeps getDocumentContent()
│   ├── BakusStatusMapper.php             # Extracted from BakusCollectTaskMapper
│   ├── BakusCollectTaskMapper.php        # Unchanged
│   ├── Handler/
│   │   ├── BakusMergedResultHandler.php  # Moved from Infrastructure/Collect/
│   │   ├── BakusQueryStatusHandler.php   # Moved from Infrastructure/Collect/
│   │   └── BakusQueryLogHandler.php      # NEW — handles query_log messages
│   └── Stream/                           # Unchanged
└── <FutureProvider>/
    ├── <Provider>Gateway.php
    ├── <Provider>StatusMapper.php
    └── Handler/
```

**Pros:**

- No premature abstraction — `ProviderGatewayInterface` stays at 4 universal methods
- Explicit coupling — Bakus handlers are visibly in the Bakus namespace
- Each provider owns its data model — no forced normalization
- Symfony tagged `ServiceLocator` is idiomatic and zero-overhead
- Minimal refactoring — handlers keep existing logic, just move directories

**Cons:**

- No single interface for content fetching (provider-specific)
- Handler routing requires provider-aware dispatching in `CollectDataReceivedEventListener`
- No built-in failover (by design — YAGNI until 2 providers in production)

### Option C: Do Nothing

**Pros:** Zero effort. **Cons:** Lock-in risk remains, coupling grows over time.

## Decision

**Option B — Per-Provider Handler Packages + Tagged Service Locator.**

### Rationale

1. **No false generics**: `getDocumentContent(hash, resultType)` is Bakus-specific. Webhook providers deliver
   content inline. Adding this to the Domain interface shapes it around one provider.

2. **Handlers are provider-specific**: `MergedResultCollectDataHandler` parses `raw_id`, `hash_document_sha1`,
   `ts_collection`, `cfc_restricted` — Bakus payload conventions, not universal fields.

3. **Simple resolution**: `$this->providerLocator->get($collectTask->getProviderName())` — done.

4. **Routing already solved**: `CollectTask.providerName` field exists, service locator uses it directly.

## Provider Selection Strategy

The `ProviderResolverInterface` uses **SourceType-based routing** with a global default fallback:

```yaml
# config/services/provider_routing.yaml
parameters:
    app.collect.provider_routing:
        default: '%env(COLLECT_DEFAULT_PROVIDER)%' # e.g. 'bakus'
        source_types:
            rss_feed: 'apify'
            blog: 'apify'
            # Everything else → default
```

```php
readonly class SourceTypeProviderResolver implements ProviderResolverInterface
{
    public function resolve(Source $source): string
    {
        return $this->sourceTypeOverrides[$source->getType()->value]
            ?? $this->defaultProvider;
    }
}
```

This enables **progressive migration**: switch one `SourceType` at a time via YAML config, rollback instantly by
removing the override. Staging can test everything on the new provider via `COLLECT_DEFAULT_PROVIDER=apify`.

## Fallback and Resilience

Three levels, ordered by complexity. Only Level 1 is planned for Sprint 1.

### Level 1: Fallback at Task Creation (Sprint 1)

If `createTask()` fails on the primary provider, retry on fallback. No partial data → no duplication risk.
`CollectTask.providerName` stores the **actually used** provider.

### Level 2: Circuit Breaker (Deferred — needs production metrics)

When a provider fails repeatedly, route all new tasks to fallback for a cooldown period (CLOSED → OPEN → HALF-OPEN).
Requires per-provider metrics from Level 1 in production.

### Level 3: Mid-Collection Failover (Deferred — highest risk)

Re-collect from scratch on fallback after partial failure. **Hard prerequisites**: document deduplication
(ADR-2026-006), `CollectStatus::RETRYING` state, per-task document counter.

## Monitoring (Provider-Aware)

### Source Activity Logging

Add `provider_name` and `collect_task_id` to `SourceActivityLogger` context:

```php
$this->sourceActivityLogger->logSourceCollectStatusChanged(
    $source, null, $oldStatus, $newStatus,
    ['provider_name' => $collectTask->getProviderName(), 'collect_task_id' => $collectTask->getId()],
);
```

### Collection Activity Logs (query_log)

Bakus sends `query_log` messages (per-URL crawl data) when `return_query_logs: true` is configured. Currently
**silently dropped** — no handler matches this type. A new `BakusQueryLogHandler` captures these into
`SourceActivity` with a `SOURCE_COLLECT_LOG` action type.

### Structured Logging

Add `provider_name` to all Monolog entries in collection context. Enables Kibana per-provider dashboards:
`provider_name:bakus AND level:ERROR`.

### Prometheus Metrics (Deferred — when scale requires)

Expose `WebSocketStreamStatistics` as Prometheus counters per provider. Not needed in Sprint 0 — Kibana is
sufficient.

## Consolidated Implementation Roadmap

### Phase 1 — Decouple & Observe (Sprint 0, ~1 week)

Core abstractions and observability. No functional change — Bakus remains the only provider.

| #   | Action                                                                           | Size | Domain     |
| --- | -------------------------------------------------------------------------------- | ---- | ---------- |
| 1   | Create `StatusMapperInterface` in `Domain/Collect/`                              | S    | Core       |
| 2   | Extract `BakusStatusMapper` from `BakusCollectTaskMapper`                        | S    | Core       |
| 3   | Create `ProviderResolverInterface` in `Domain/Collect/`                          | S    | Core       |
| 4   | Implement `SourceTypeProviderResolver` (env-based default)                       | S    | Core       |
| 5   | Create `ProviderGatewayLocator` (Symfony ServiceLocator)                         | S    | Core       |
| 6   | Modify `CreateCollectTaskHandler`: inject `ProviderResolverInterface`            | S    | Core       |
| 7   | Move `MergedResultCollectDataHandler` → `Bakus/Handler/BakusMergedResultHandler` | M    | Core       |
| 8   | Move `QueryStatusCollectDataHandler` → `Bakus/Handler/BakusQueryStatusHandler`   | S    | Core       |
| 9   | Add `provider_name` to `SourceActivityLogger` log entries                        | S    | Monitoring |
| 10  | Add `provider_name` to Monolog structured logs                                   | S    | Monitoring |
| 11  | Create `BakusQueryLogHandler` for `query_log` messages                           | M    | Monitoring |
| 12  | Add `SOURCE_COLLECT_LOG` action type to `SourceActivityActionType`               | S    | Monitoring |
| 13  | Add `providerName` validation in `CollectTaskAuthorizationMiddleware`            | S    | Security   |
| 14  | CI test: all `SourceType` values have a provider mapping                         | S    | CI         |
| 15  | Document minimal provider contract (what a provider MUST deliver)                | S    | Docs       |

### Phase 2 — Second Provider (Sprint 1+, when provider is chosen)

| #   | Action                                                            | Size | Domain     |
| --- | ----------------------------------------------------------------- | ---- | ---------- |
| 16  | Create `<Provider>Gateway implements ProviderGatewayInterface`    | M    | Core       |
| 17  | Create `<Provider>StatusMapper implements StatusMapperInterface`  | S    | Core       |
| 18  | Create provider-specific handlers (`CollectDataHandlerInterface`) | M    | Core       |
| 19  | Create provider-specific ingestion (webhook controller / polling) | M    | Core       |
| 20  | Implement Level 1 fallback at task creation                       | M    | Resilience |
| 21  | Frontend: display `SOURCE_COLLECT_LOG` in timeline                | S    | Frontend   |
| 22  | Provider credentials management (env vars, secrets)               | S    | Ops        |

### Deferred (explicit prerequisites before activation)

| Item                                                   | Prerequisite For                | Related ADR  |
| ------------------------------------------------------ | ------------------------------- | ------------ |
| Async `getDocumentContent()` (decouple from WebSocket) | Scaling to 50M docs/year        | —            |
| Document deduplication by URL/hash                     | Level 3 failover                | ADR-2026-006 |
| Error message sanitization for analysts                | 2nd provider in prod            | —            |
| Circuit breaker (Level 2 fallback)                     | Production metrics from Level 1 | —            |
| `CollectStatus::RETRYING` state                        | Level 3 failover                | —            |
| Internal post-processing pipeline                      | Provider-agnostic enrichment    | ADR-2026-009 |

## Consequences

### Positive

- **Vendor lock-in reduced**: adding a 2nd provider requires only isolated code in its own namespace
- **Explicit coupling**: Bakus handlers are in `Infrastructure/Collect/Bakus/Handler/`
- **Provider-aware observability**: `providerName` in logs and activity from day one
- **Minimal disruption**: existing Bakus flow continues unchanged
- **CI safety net**: SourceType-to-provider mapping validated at build time

### Negative

- **No unified content fetching abstraction**: each provider manages content its own way
- **Handler routing complexity**: event listener must dispatch to correct provider's handlers
- **Two-step migration**: Sprint 0 decouples, Sprint 1+ adds the actual 2nd provider

### Neutral

- **Test restructuring**: handlers move to `Bakus/Handler/`, tests follow with same logic
- **Config growth**: each provider adds YAML config + env vars (linear cost)

## Migration of Existing Data

- **`CollectTask` table**: all existing rows have `providerName = 'bakus'`. No migration needed — the field already
  exists and the new resolver defaults to `bakus`.
- **Running tasks**: tasks in `RUNNING` or `QUEUED` state continue routing to Bakus via their stored `providerName`.
  No risk of mid-flight routing change.
- **`CollectDataReceivedEvent::DOCUMENT_TYPES`**: the hardcoded list remains for Bakus handlers. New providers
  define their own type constants.

## Testing Strategy

- **`SourceTypeProviderResolver`**: unit test with various SourceType/override combinations. No mock needed —
  pure logic.
- **`ProviderGatewayLocator`**: integration test verifying Symfony DI wiring. Use `KernelTestCase` to confirm all
  tagged providers resolve correctly.
- **Moved handlers**: tests move with the handlers (namespace change only). Zero logic change.
- **CI validation**: a test asserts every `SourceType` enum value has a provider mapping in config. Prevents
  silent failures when new source types are added.
- **Future provider**: bring its own test suite under `tests/Integration/Collect/<Provider>/`.

## Credentials and Secrets

Each provider's credentials are injected via environment variables:

```yaml
# .env
BAKUS_API_URL=https://api.bakus.example.com
BAKUS_API_KEY=secret

    # Future provider
APIFY_API_URL=https://api.apify.com
APIFY_API_TOKEN=secret
```

Credentials are **never stored in YAML config**. The `ProviderGatewayLocator` resolves providers by name; each
provider's service reads its own env vars via `#[Autowire('%env(...)%')]`. In production, secrets are managed via
the deployment pipeline (Docker secrets, environment injection).

## P0 Performance Warning

`BakusProviderGateway::getDocumentContent()` is called **synchronously per document** inside the WebSocket message
loop. At burst volumes (500 docs), this blocks the thread for ~100 seconds.

**This is orthogonal to multi-provider but becomes critical at scale.** Decouple content fetching into async
Symfony Messenger before scaling beyond current volumes. Tracked separately from this ADR.

## Decision Log

| #   | Decision                                              | Alternative                 | Source              | Resolution                                |
| --- | ----------------------------------------------------- | --------------------------- | ------------------- | ----------------------------------------- |
| D1  | Do NOT add `getDocumentContent()` to Domain interface | Enrich interface (Option A) | Skeptic             | Bakus "fetch by hash" is not universal    |
| D2  | Handlers move to Bakus namespace                      | Refactor to generic         | Skeptic             | Explicit > hidden coupling                |
| D3  | Tagged service locator, not Strategy pattern          | ProviderRouter (Option A)   | Skeptic             | YAGNI at 2 providers                      |
| D4  | Defer circuit breaker                                 | Implement in Sprint 3       | Skeptic             | Needs dedup + production metrics first    |
| D5  | Provider-aware monitoring from Sprint 0               | Defer to Phase 2            | User Advocate       | Cannot diagnose without per-provider data |
| D6  | CI validation of SourceType → provider mapping        | Runtime-only validation     | User Advocate       | Silent prod failures unacceptable         |
| D7  | Validate providerName in auth middleware              | No validation               | Constraint Guardian | Prevents cross-provider token reuse       |

## References

- [Symfony Tagged Service Locator](https://symfony.com/doc/current/service_container/service_subscribers_locators.html)
- ADR-2026-003: Document Processing Pipeline Architecture
- ADR-2026-006: Document Deduplication Strategy
- ADR-2026-009: Provider-Agnostic Post-Collection Processing (companion ADR)
