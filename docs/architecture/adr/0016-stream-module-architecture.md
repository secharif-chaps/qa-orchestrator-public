# ADR-0016: Stream Module Architecture

## Status

**Status:** Proposed

**Date:** 2026-03-23

**Decision Makers:** ChapsMind Engineering Team

**Tags:** backend, architecture, stream, distribution

**Prerequisite:** This ADR assumes the **Global Service v2 with auto-discovery** is merged and live (MRs !70–!77: correlation ID middleware, OpenAPI merge fix, health/ready aggregation, ModuleRegistry + config, proxy multi-backend via registry). Stream leverages this infrastructure — it does not build it.

**Related:**

- [ADR-0009 — Global Service Architecture](./0009-global-service-architecture.md)
- [ADR-0012 — LangGraph Agent System](./0012-langgraph-agent-system.md)
- [ADR-0013 — Chat Service Replacement](./0013-chat-service-replacement.md)
- [ADR-0015 — Multi-Module API Gateway](./0015-multi-module-gateway.md)

---

## Context

ChapsMind's intelligence is consumed passively — users must visit the app to see new data. There is no way to push updates to external channels (Teams, Slack, newsletters, webhooks). Users want to be notified when new intelligence arrives in their folders, and to distribute curated digests to colleagues who don't use ChapsMind directly.

### Requirements

**Phase 1 scope (this ADR):**

1. **Multi-channel distribution**: Push folder events to Teams, Slack, and webhooks
2. **Live mode**: Immediate dispatch on event (recurrence deferred to Phase 2)
3. **Event type subscription**: Stripe-style event filtering per stream (hardcoded catalog, auto-discovery later)
4. **Multi-source events**: Screen pushes events now, Target/Explore will push events later
5. **Per-folder streams**: Each folder can have multiple streams across different channels
6. **Credit-based pricing**: Stream creation and dispatch consume credits, varying by channel type
7. **Feature-flagged**: Off by default, enabled per organization
8. **Simple form UI**: Minimal frontend to create and test streams

**Future iterations:**

- Recurrence mode with scheduled template-based digests (Phase 2)
- Auto-discovery of event types from producer services (Phase 3)
- AI-assisted newsletter generation with LangGraph agent, email channel, distribution lists (Phase 4)

### Historical Note

`stream` was previously listed in `ModuleName` but was removed before implementation. This ADR defines Stream as a **FeatureFlag** (add-on capability), not a core module, since it augments existing modules rather than providing standalone functionality.

---

## Decision

Build Stream as a **standalone FastAPI service** at `apps/stream/` with its own database (`stream_db`), following the Global Service gateway pattern from ADR-0009. This ADR focuses on the **backend service and minimal frontend** needed to validate the core event-to-channel pipeline. Stream creation uses a **simple form-based UI** — the AI-assisted content agent and newsletter features are deferred to later iterations once the core service is proven.

### Architecture Overview

```text
                    ┌─────────────────────────────────────────┐
                    │              Frontend SPA               │
                    │  (Stream sidebar, Stream editor page)   │
                    └──────────────────┬──────────────────────┘
                                       │ REST + SSE
                                       ▼
                    ┌─────────────────────────────────────────┐
                    │         Global Service (Gateway v2)     │
                    │  ┌─────────────────────────────────┐    │
                    │  │ ModuleRegistry (auto-discovery)  │    │
                    │  │ Correlation ID · Health agg.     │    │
                    │  │ OpenAPI merge · Multi-backend    │    │
                    │  └─────────────────────────────────┘    │
                    │  /api/stream/* → Stream Service         │
                    │  /api/*       → Screen Service (default)│
                    └──────────┬──────────────────────────────┘
                               │ Internal JWT + X-Correlation-ID
                               ▼
                    ┌─────────────────────────────────────────┐
                    │           Stream Service                │
                    │  ┌────────────┐  ┌───────────────────┐  │
                    │  │ Stream API │  │ Channel Adapters  │  │
                    │  │ (CRUD,     │  │ (Teams, Slack,    │  │
                    │  │  dispatch) │  │  Webhook)         │  │
                    │  └────────────┘  └───────────────────┘  │
                    │  ┌────────────────────────────────────┐  │
                    │  │ APScheduler (Phase 2 — recurrence) │  │
                    │  └────────────────────────────────────┘  │
                    └──────────────────┬──────────────────────┘
                                       │
                                  ┌────┴────┐
                                  │stream_db│
                                  └─────────┘

    ┌──────────────┐          ┌──────────┐
    │Screen Service│──writes──► screen_db │ (outbox table, same tx)
    └──────────────┘          └────┬─────┘
                                   │ relay (poll, optional pg_notify)
                                   ▼
                        POST /internal/events/ingest
                                   │
    ┌──────────────┐          ┌────┴─────┐
    │Target Service│──writes──► target_db │ (outbox table, same tx)
    └──────────────┘          └────┬─────┘
                                   │ relay (poll, optional pg_notify)
                                   ▼
                        POST /internal/events/ingest
                                   │
                    ┌──────────────┐│
                    │Stream Service│◄┘
                    └──────────────┘
```

### Event Flow

```text
Producer Service ──writes to outbox table──► producer_db (same transaction as business op)
                                                  │
Producer Relay ──polls (+ optional pg_notify)──────┘
        │
        ▼
Stream Service ◄── POST /internal/events/ingest
        │
 ┌──────┴──────┐
 │             │
LIVE mode    RECURRENCE mode
 │             │
Dispatch      Queue events
immediately   APScheduler fires
 │             at schedule
 ▼             │
Channel Adapter ◄───┘
(Teams/Slack/Email/Webhook)
```

- Each producer writes events to its own outbox table (transactional write), a per-service relay polls the outbox (optionally accelerated by `pg_notify`) and POSTs to Stream's ingest endpoint
- Stream matches events to all active streams on the folder
- **Live streams**: dispatch immediately via channel adapter
- **Recurrence streams**: queue events, APScheduler triggers dispatch on schedule (cron-based)

### Key Decisions

| Decision            | Choice                                                   | Rationale                                                                          |
| ------------------- | -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Service placement   | Standalone `apps/stream/`                                | Clean domain boundary, independent scaling, follows ADR-0009                       |
| Scheduling          | APScheduler (in-process, PostgreSQL job store) — Phase 2 | No new infra, persistent across restarts                                           |
| Event storage       | `stream_events` + `stream_deliveries` tables             | Simple, queryable, enables preview                                                 |
| Channel abstraction | Adapter pattern (ABC + concrete per channel)             | Clean interface, easy to add channels                                              |
| Content generation  | Template-based (Phase 1-2), LangGraph agent (future)     | Validate core pipeline first, add AI content later                                 |
| Feature flag        | Add `STREAM` to existing `FeatureFlag` enum              | Reuses existing system, OFF by default                                             |
| Stream creation UX  | Simple form-based page                                   | Minimal UI to configure and test streams; rich editor deferred                     |
| Event subscription  | Stripe-style event type filtering (hardcoded catalog)    | Granular control from day one; auto-discovery deferred                             |
| Event transport     | PostgreSQL outbox + per-service relay                    | Handles 10k+ events/day, guaranteed delivery, zero new infra                       |
| Credit system       | Variable cost per channel type and mode                  | Reflects different channel costs (webhook < Teams/Slack)                           |
| Proxy routing       | ModuleRegistry auto-discovery in Global Service v2       | Stream registers as a module; routing, health, OpenAPI merge handled automatically |

---

## Event Transport: Inter-Service Communication

### Problem

When a producer service completes work (Screen: company analysis, Target: watchfile alerts, Explore: graph updates), it must notify Stream so the event can be dispatched to subscribed channels. The transport mechanism must guarantee **at-least-once delivery** — a lost event means a user misses an intelligence update with no indication that anything went wrong.

**Volume context:** Screen is low-volume today (~tens of events/day), but Target arrives next month. A single watchfile can generate hundreds of documents/day; with multiple users and watchfiles, the realistic baseline is **10k+ events/day**. The Phase 1 transport must handle this volume from day one.

**Multi-producer constraint:** Screen, Target, and eventually Explore each produce events from their own service and database. The transport design must support multiple independent producers without requiring Stream to couple to each producer's database.

### Options Considered

#### Option A: REST + Retry (Current Implicit Approach)

Screen fires an HTTP POST to Stream's internal endpoint after analysis completion.

**Pros:**

- Simplest to implement — standard HTTP call
- No new infrastructure or tables
- Easy to understand and debug

**Cons:**

- If Stream is down or the network fails, the event is lost
- Retry logic adds complexity (exponential backoff, dead-letter handling)
- No transactional guarantee — analysis can commit but event delivery can fail
- Retry storms under sustained outages — at 10k events/day, a 30-minute Stream outage queues ~200 retries that fire simultaneously on recovery
- Scales poorly with multiple producers: each service needs its own retry logic, circuit breakers, and dead-letter handling

#### Option B: PostgreSQL Outbox + Per-Service Relay (Recommended)

Each producer writes events to an `outbox` table in its own database within the same transaction as the business operation. A lightweight relay process per service reads the outbox and POSTs to Stream's `POST /internal/events/ingest` endpoint. Stream keeps a single ingest endpoint regardless of how many producers exist.

**Pros:**

- **Guaranteed delivery**: event write is atomic with the business transaction — if the operation commits, the event is guaranteed to exist
- **Zero new infrastructure**: uses existing PostgreSQL, no broker needed
- **Validated at 10k+/day**: 10k events/day is ~7/min average — trivial for PostgreSQL polling; `pg_notify` can optionally reduce latency further
- **Multi-producer ready**: each service owns its outbox; adding Target or Explore means adding an outbox table + relay, not modifying Stream
- **Queryable**: outbox table is inspectable for debugging and monitoring
- **Decoupled consumer**: Stream never reads another service's database — the relay bridges the gap using REST, combining outbox transactional safety with REST simplicity

**Cons:**

- Polling introduces slight latency (optionally mitigated by `pg_notify` — see managed PostgreSQL note below)
- Outbox table requires periodic cleanup (simple cron job)
- Each producer service needs a relay process (lightweight — ~50 lines of async polling code)

#### Option C: RabbitMQ

Screen publishes events to a RabbitMQ exchange; Stream consumes from a queue.

**Pros:**

- Best queue semantics (acknowledgments, dead-letter, fan-out)
- Proven technology for async messaging
- Clean decoupling between producer and consumer

**Cons:**

- RabbitMQ is not currently deployed — it was removed when Dify (its only consumer) was dropped. Target will reintroduce it, but until then it adds a dependency that doesn't exist yet
- Unnecessary complexity for Stream's use case: the outbox pattern provides the same delivery guarantees without a broker
- Justified at 10k events/day volume, but infrastructure overhead is disproportionate given the outbox pattern handles it without new dependencies
- Additional operational burden (monitoring, cluster management)

#### Option D: Redis Streams

Screen writes events to a Redis Stream; Stream consumes with a consumer group.

**Pros:**

- Lightweight persistent queue with consumer groups
- Sub-millisecond latency
- Redis may already be available for caching

**Cons:**

- New infrastructure dependency if Redis is not already deployed
- Less durable than PostgreSQL (depends on persistence config)
- No transactional guarantee with Screen's database write
- Adds operational complexity without clear benefit over the outbox pattern at 10k events/day

### Recommendation

**PostgreSQL outbox + per-service relay** (Option B). At 10k+ events/day (~7/min), PostgreSQL polling handles this volume comfortably — this is a validated production choice, not a stopgap. Each producer writes to its own outbox table (transactional guarantee), and a lightweight relay process per service polls the outbox and POSTs to Stream's `POST /internal/events/ingest`. Stream maintains a single ingest endpoint regardless of the number of producers. `pg_notify` can optionally be added to reduce polling latency, but is not required — the outbox pattern is polling-first by design.

**Multi-producer pattern:**

- **Screen**: writes to `screen_db.outbox` → Screen relay → `POST /internal/events/ingest`
- **Target** (next month): writes to `target_db.outbox` → Target relay → `POST /internal/events/ingest`
- **Explore** (future): same pattern

**Upgrade threshold**: If event volume exceeds **100k events/day** or latency requirements drop below **1 second**, migrate to RabbitMQ (Option C) or Redis Streams (Option D) by replacing the relay with a broker consumer — the producer side (writing to outbox) remains unchanged, making the migration low-risk.

> **Managed PostgreSQL note**: `pg_notify` requires a `LISTEN` connection that bypasses PgBouncer's transaction pooling. On managed services (OVH, etc.), PgBouncer typically runs in transaction pooling mode, which breaks `LISTEN` (the receiver side — `NOTIFY` on the sender side works fine in any mode). If `pg_notify` is desired, the relay should use a direct connection URI (bypassing PgBouncer) for its `LISTEN` socket, or the managed service can expose a session-mode pool for this purpose. However, polling alone (every 2-5s) handles ~7 events/min with negligible latency and requires zero special configuration. `pg_notify` is an optimization, not a requirement; the outbox pattern is polling-first by design.

---

## Stream Creation: Simple Form

This ADR focuses on a **minimal form-based UI** to create and manage streams. The AI-assisted content agent (LangGraph) and newsletter features are deferred to a future iteration — the priority is validating the core event-to-channel pipeline.

### Stream Creation Form

Single-page form with dynamic sections based on channel type and mode:

```text
┌─────────────────────────────────────────────────────────┐
│  ← Retour au dossier            Créer un Stream         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Nom du stream          [______________________________]│
│                                                         │
│  Type de canal          [Teams ▼]                       │
│                         (Teams / Slack / Webhook)       │
│                                                         │
│  Mode                   ○ Live (envoi immédiat)         │
│                         ○ Récurrence (digest planifié)  │
│                                                         │
│  ─── Événements ───────────────────────────────────     │
│                                                         │
│  ☐ Tout sélectionner                                    │
│                                                         │
│  ── Screen ─────────────────────────────────────────    │
│  ☑ Fiche entreprise créée                               │
│  ☑ Fiche entreprise actualisée                          │
│  ☐ Analyse de données terminée                          │
│  ☐ Données financières mises à jour                     │
│                                                         │
│  ── Target (bientôt disponible) ────────────────────    │
│  ☐ Nouveau document publié                              │
│  ☐ Nouvel acteur détecté                                │
│  ☐ Alerte veille déclenchée                             │
│                                                         │
│  ─── Configuration du canal ───────────────────────     │
│                                                         │
│  (Teams)                                                │
│  URL Power Automate     [______________________________]│
│                         [🔗 Tester la connexion]        │
│                                                         │
│  (Slack)                                                │
│  Workspace              Acme Corp ✓ connecté            │
│  Canal                  [#veille-concurrents ▼]         │
│                         [🔗 Tester la connexion]        │
│  — ou si non connecté — [🔗 Connecter Slack]            │
│                                                         │
│  (Webhook)                                              │
│  URL                    [______________________________]│
│  Méthode HTTP           [POST ▼]                        │
│  Headers (optionnel)    [______________________________]│
│                         [🔗 Tester la connexion]        │
│                                                         │
│  ─── Planification (récurrence uniquement) ────────     │
│                                                         │
│  Récurrence             [Hebdomadaire ▼]                │
│  Jour d'envoi           [Lundi ▼]                       │
│  Heure d'envoi          [09:00 ▼]                       │
│  Fuseau horaire         [Europe/Paris ▼]                │
│                                                         │
│  ─────────────────────────────────────────────────      │
│                                                         │
│              [Annuler]  [Créer le stream]                │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Channel Configuration (dynamic per channel type)

| Channel     | Integration Model                   | Config Fields                                                                                                                              |
| ----------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **Teams**   | Power Automate Workflow URL         | User creates a Workflow in Teams ("Post to a channel when a webhook request is received"), copies the generated URL, pastes it in our form |
| **Slack**   | Slack App with OAuth V2 + bot token | "Connecter Slack" button triggers OAuth flow, user approves, we store bot token + user picks target channel from dropdown                  |
| **Webhook** | Generic HTTP                        | URL + HTTP method (POST/PUT) + optional custom headers (JSONB)                                                                             |

All channels expose a **"Tester la connexion"** button that sends a test payload to validate the configuration before saving.

> **Why not simple webhook URLs for all channels?** See [Channel Integration Approaches](#channel-integration-approaches) below for the full rationale — Teams legacy webhooks die April 2026, and Slack App bot tokens are far more capable than per-channel webhook URLs.

### Stream Status Lifecycle

`draft` → `active` ↔ `paused` → `archived`

- **Draft**: stream is saved but does not receive or dispatch events
- **Active**: stream receives events and dispatches according to its mode
- **Paused**: stream stops dispatching but continues storing events (resumes on reactivation)
- **Archived**: terminal state, the stream is permanently deactivated and cannot be restarted (soft-delete alternative)

### Recurrence Digest Format (Template-Based)

In recurrence mode, dispatched digests use a **simple template** that aggregates queued events — no AI generation. Example Slack/Teams output:

```text
📋 Digest hebdomadaire — "Concurrents IA"
Période : 24 mars – 30 mars 2026

• 📄 OpenAI lance GPT-5 (Les Echos) — 25 mars
• 📄 Mistral lève 600M€ (Le Monde) — 27 mars
• 🏢 Nouvelle fiche entreprise : Anthropic — 28 mars

3 événements • via ChapsMind Stream
```

AI-generated digest summaries are deferred to the agent iteration.

---

## Channel Integration Approaches

### Microsoft Teams: Power Automate Workflows

**Context:** Legacy Incoming Webhooks (Office 365 Connectors) were deprecated in August 2024 (no new creation) and **stop working entirely on April 30, 2026**. Power Automate Workflows are Microsoft's official replacement.

**How it works:**

1. Customer opens their Teams channel → `...` menu → **Workflows**
2. Selects template: **"Post to a channel when a webhook request is received"**
3. Power Automate generates a workflow URL
4. Customer copies the URL into ChapsMind's stream form
5. Stream service POSTs **Adaptive Card JSON** to that URL

**Payload format:** Adaptive Cards (recommended by Microsoft, full rich card support).

```json
{
  "type": "message",
  "attachments": [
    {
      "contentType": "application/vnd.microsoft.card.adaptive",
      "content": {
        "type": "AdaptiveCard",
        "version": "1.4",
        "body": [
          { "type": "TextBlock", "text": "Nouvelle fiche entreprise", "weight": "Bolder" },
          { "type": "TextBlock", "text": "Anthropic — créée le 28 mars 2026" }
        ],
        "actions": [
          { "type": "Action.OpenUrl", "title": "Voir dans ChapsMind", "url": "https://..." }
        ]
      }
    }
  ]
}
```

**Limitations:**

- Workflow is tied to the user who created it (orphan risk if they leave)
- Customer needs Power Automate licensing (included in most M365 plans)
- One-way only (our service → Teams)
- Private channel support still in progress

**Options considered and rejected:**

- **Bot Framework**: Full-featured but requires Azure Bot registration, Bot Framework SDK, and Azure infrastructure. Overkill for one-way notifications in Phase 1. Worth revisiting if two-way interaction is needed later.
- **Graph API**: Cannot send channel messages with application permissions (restricted to migration scenarios). Would require RSC + bot installation — effectively the Bot Framework approach.

### Slack: Incoming Webhook URL (Phase 1)

**Context:** Phase 1 uses Slack's **Incoming Webhook** approach — the simplest integration path. The user creates an Incoming Webhook in their Slack workspace and pastes the URL into ChapsMind. This mirrors the Generic Webhook pattern, with Slack-specific message formatting (Block Kit).

> **Future upgrade path:** A full Slack App with OAuth V2 bot token (dynamic channel targeting, channel picker dropdown) is planned for a later phase. The webhook approach is intentionally simple to ship fast, and the adapter pattern makes upgrading straightforward without breaking existing streams.

**How it works:**

1. User creates a **Slack App** with Incoming Webhooks enabled in their workspace
2. User generates a webhook URL for the target channel (e.g. `https://hooks.slack.com/services/T.../B.../xxx`)
3. User pastes the webhook URL into the ChapsMind stream form
4. Stream service POSTs Block Kit messages to the webhook URL

**User setup:** Slack App → Incoming Webhooks → Activate → Add New Webhook to Workspace → Copy URL → Paste in ChapsMind.

**Message format:** Slack Block Kit (same as bot token approach).

```json
{
  "text": "Nouvelle fiche entreprise : Anthropic",
  "blocks": [
    {
      "type": "section",
      "text": {
        "type": "mrkdwn",
        "text": "*Nouvelle fiche entreprise*\nAnthropic — créée le 28 mars 2026"
      }
    },
    {
      "type": "actions",
      "elements": [
        {
          "type": "button",
          "text": { "type": "plain_text", "text": "Voir dans ChapsMind" },
          "url": "https://..."
        }
      ]
    }
  ]
}
```

**Advantages:**

- **Zero infrastructure**: no OAuth flow, no token storage, no Slack App registration on our side
- **Instant setup**: user pastes a URL, done
- **Same message quality**: Block Kit works identically via webhooks
- **Simple adapter**: same pattern as Generic Webhook, just different payload format

**Limitations (addressed in future Slack App upgrade):**

- One webhook URL = one channel (user needs a new URL per channel)
- No channel picker dropdown (user must create webhook manually in Slack)
- No dynamic channel targeting
- Slack legacy custom integration webhooks die November 2026 — but **Slack App incoming webhooks** (the approach we use) remain fully supported

### Generic Webhook

Standard HTTP webhook for custom integrations (Zapier, n8n, internal services, etc.).

- User provides a URL + HTTP method (POST/PUT) + optional custom headers
- Stream service POSTs a JSON payload with the event data
- No authentication managed by us — the URL itself is the secret (similar to Stripe webhooks)

When a `secret` is configured, the webhook adapter signs the payload body using HMAC-SHA256 and includes the signature in the `X-Signature-256` header (format: `sha256=<hex>`). This allows the receiver to verify payload integrity.

### Summary

| Channel     | Integration                    | Auth                   | User Setup                          |
| ----------- | ------------------------------ | ---------------------- | ----------------------------------- |
| **Teams**   | Power Automate Workflow URL    | URL contains signature | Create workflow in Teams, paste URL |
| **Slack**   | Incoming Webhook URL (Phase 1) | URL is secret          | Create Slack App webhook, paste URL |
| **Webhook** | Generic HTTP POST              | None (URL is secret)   | Paste URL                           |

> **Note:** Slack will be upgraded to full OAuth V2 bot token in a future phase for dynamic channel targeting and channel picker.

---

## Data Model

### Table: `streams`

Distribution channel configuration per folder.

| Column              | Type           | Description                                                            |
| ------------------- | -------------- | ---------------------------------------------------------------------- |
| `id`                | `SERIAL PK`    |                                                                        |
| `folder_id`         | `VARCHAR(36)`  | Folder UUID from Global Service                                        |
| `organization_id`   | `VARCHAR(36)`  | Keycloak organization ID                                               |
| `name`              | `VARCHAR(255)` | User-defined stream name                                               |
| `description`       | `TEXT`         | Optional user-provided description                                     |
| `channel_type`      | `ENUM`         | `teams`, `slack`, `webhook` (email added in Phase 4)                   |
| `mode`              | `ENUM`         | `live`, `recurrence`                                                   |
| `status`            | `ENUM`         | `draft`, `active`, `paused`, `archived`                                |
| `cron_expression`   | `VARCHAR(100)` | Cron schedule (recurrence mode only)                                   |
| `timezone`          | `VARCHAR(50)`  | e.g. `Europe/Paris`                                                    |
| `digest_goal`       | `TEXT`         | AI instruction for digest generation (Phase 4, nullable)               |
| `digest_language`   | `VARCHAR(10)`  | e.g. `fr`, `en` (Phase 4, nullable)                                    |
| `subscribed_events` | `JSONB`        | Array of event types to listen for (e.g. `["screen.company.created"]`) |
| `channel_config`    | `JSONB`        | Channel-specific config (see below)                                    |
| `owner_id`          | `VARCHAR(36)`  | Keycloak user ID                                                       |
| `owner_username`    | `VARCHAR(255)` | Denormalized for display                                               |
| `created_at`        | `TIMESTAMP`    |                                                                        |
| `updated_at`        | `TIMESTAMP`    |                                                                        |

**`channel_config` JSONB examples per channel type:**

```json
// Teams (Power Automate Workflow URL)
{ "workflow_url": "https://default....powerplatform.com/.../invoke?..." }

// Slack (Incoming Webhook URL — Phase 1)
{ "webhook_url": "https://hooks.slack.com/services/T.../B.../xxx" }

// Webhook (generic HTTP)
{ "url": "https://hooks.example.com/stream", "method": "POST", "headers": { "X-Api-Key": "..." }, "secret": "whsec_..." }
```

> **Phase 1 Slack:** Uses Incoming Webhook URL stored directly in `channel_config`, same pattern as Generic Webhook but with Slack Block Kit formatting. Future upgrade to OAuth V2 bot token will add the `slack_installations` table.

### Table: `slack_installations` (Deferred — Future Slack App upgrade)

> **Phase 1 does not use this table.** Slack integration uses simple Incoming Webhook URLs stored in `streams.channel_config`, identical to the Generic Webhook pattern. This table will be introduced when upgrading to full Slack App with OAuth V2 bot token.

| Column            | Type           | Description                      |
| ----------------- | -------------- | -------------------------------- |
| `id`              | `SERIAL PK`    |                                  |
| `organization_id` | `VARCHAR(36)`  | Keycloak organization ID         |
| `team_id`         | `VARCHAR(50)`  | Slack workspace ID               |
| `team_name`       | `VARCHAR(255)` | Slack workspace name             |
| `bot_token`       | `TEXT`         | Encrypted bot token (`xoxb-...`) |
| `bot_user_id`     | `VARCHAR(50)`  | Bot's Slack user ID              |
| `installed_by`    | `VARCHAR(36)`  | Keycloak user ID who authorized  |
| `scopes`          | `VARCHAR(500)` | Granted OAuth scopes             |
| `created_at`      | `TIMESTAMP`    |                                  |
| `updated_at`      | `TIMESTAMP`    |                                  |

### Table: `stream_events`

Events pushed by other services (Screen, Target, Explore).

| Column            | Type           | Description                                                |
| ----------------- | -------------- | ---------------------------------------------------------- |
| `id`              | `SERIAL PK`    |                                                            |
| `folder_id`       | `VARCHAR(36)`  | Folder UUID                                                |
| `organization_id` | `VARCHAR(36)`  | Keycloak organization ID (for multi-tenancy isolation)     |
| `event_type`      | `VARCHAR(100)` | e.g. `screen.company.created`, `target.document.published` |
| `source`          | `VARCHAR(100)` | `screen`, `target`, `explore`                              |
| `entity_id`       | `VARCHAR(255)` | ID of the source object                                    |
| `entity_type`     | `VARCHAR(100)` | Type of the referenced entity                              |
| `summary`         | `TEXT`         | Human-readable event summary                               |
| `payload`         | `JSONB`        | Event data (company name, alert details, etc.)             |
| `is_deleted`      | `BOOLEAN`      | Soft-delete when source objects are removed                |
| `created_at`      | `TIMESTAMP`    |                                                            |

### Table: `stream_deliveries`

Delivery tracking per event-stream pair.

| Column              | Type                 | Description                                 |
| ------------------- | -------------------- | ------------------------------------------- |
| `id`                | `SERIAL PK`          |                                             |
| `stream_id`         | `FK → streams`       |                                             |
| `event_id`          | `FK → stream_events` |                                             |
| `status`            | `ENUM`               | `pending`, `delivered`, `skipped`, `failed` |
| `batch_id`          | `VARCHAR(36)`        | Groups deliveries for recurrence dispatch   |
| `error_message`     | `TEXT`               | Error message on failure                    |
| `delivered_at`      | `TIMESTAMP`          |                                             |
| `attempt_count`     | `INT`                | Number of delivery attempts                 |
| `next_retry_at`     | `TIMESTAMP`          | Scheduled time for next retry attempt       |
| `response_metadata` | `JSONB`              | Response data from channel adapter          |
| `created_at`        | `TIMESTAMP`          |                                             |

### Table: `stream_recipients` (Future — Newsletter iteration)

Newsletter distribution list per stream. Deferred until email/newsletter channel is implemented.

| Column       | Type           | Description             |
| ------------ | -------------- | ----------------------- |
| `id`         | `SERIAL PK`    |                         |
| `stream_id`  | `FK → streams` |                         |
| `email`      | `VARCHAR(255)` | Recipient email         |
| `name`       | `VARCHAR(255)` | Display name (optional) |
| `created_at` | `TIMESTAMP`    |                         |

---

## API Surface

### Public Endpoints (via Global Service proxy at `/api/stream/`)

**Event Catalog**:

- `GET /api/stream/event-types` — List available event types (hardcoded catalog)

**Stream CRUD**:

- `GET /api/stream/folders/{folder_id}/streams` — List streams for a folder
- `POST /api/stream/folders/{folder_id}/streams` — Create a stream
- `GET /api/stream/streams/{id}` — Get stream details
- `PUT /api/stream/streams/{id}` — Update stream config
- `DELETE /api/stream/streams/{id}` — Delete stream
- `PATCH /api/stream/streams/{id}/status` — Change status (draft → active, active → paused)

**Dispatch & Testing**:

- `POST /api/stream/streams/{id}/dispatch` — Manual dispatch (send now)
- `POST /api/stream/streams/{id}/test` — Send test delivery (0 credits) _(Phase 2)_
- `POST /api/stream/test-connection` — Test channel connection (Teams URL / Slack / Webhook)

**Slack OAuth (Deferred — Future Phase)**:

> Phase 1 uses Incoming Webhook URLs for Slack (no OAuth). The following endpoints will be added when upgrading to full Slack App:
>
> - `GET /api/stream/slack/authorize` — Initiate Slack OAuth flow
> - `GET /api/stream/slack/callback` — OAuth callback
> - `GET /api/stream/slack/channels` — List available channels
> - `DELETE /api/stream/slack/installation` — Disconnect workspace

**Delivery History**:

- `GET /api/stream/streams/{id}/deliveries` — Delivery history with status

### Internal Endpoints (service-to-service, internal JWT)

- `POST /api/internal/events/ingest` — Push new event from Screen/Target/Explore
- `DELETE /api/internal/events/cleanup` — Remove events for deleted source objects

---

## Credit System Integration

Stream operations consume credits from the organization's global token balance (managed by Global Service). Credit costs vary by channel type and stream mode.

| Operation           | Channel             | Estimated Cost   | Rationale                        |
| ------------------- | ------------------- | ---------------- | -------------------------------- |
| Stream creation     | All                 | 25 credits       | Base setup cost                  |
| Live dispatch       | Teams/Slack         | 5 credits/event  | Simple message formatting        |
| Live dispatch       | Webhook             | 2 credits/event  | Raw payload, minimal processing  |
| Recurrence dispatch | Teams/Slack/Webhook | 10 credits/batch | Template-based digest generation |
| Test delivery       | All                 | 0 credits        | Free for testing                 |

Credit consumption is tracked via the internal token API (`POST /api/internal/tokens/consume`) on Global Service. If an organization has insufficient credits, dispatch is skipped and the delivery is marked as `skipped`.

> **Note**: Exact credit costs will be finalized during implementation based on actual LLM token usage per channel type.
>
> **Implementation note**: Credit costs are implemented as configurable settings (`STREAM_COST_TEAMS`, `STREAM_COST_SLACK`, `STREAM_COST_WEBHOOK`) that can be adjusted via environment variables without code changes.

---

## Gateway Integration: ModuleRegistry Auto-Discovery

Global Service v2 (MRs !70–!77) replaces the former single-backend proxy with a **ModuleRegistry** that provides config-driven multi-backend routing, aggregated health checks, OpenAPI merge, and correlation ID propagation. Stream is the first new module to leverage this infrastructure alongside the existing Screen default backend.

### What Global Service v2 Provides

| Capability                    | MR                  | Impact on Stream                                                                                 |
| ----------------------------- | ------------------- | ------------------------------------------------------------------------------------------------ |
| **ModuleRegistry + config**   | !76 (TAR-1378/1379) | Stream registers as a module via config; no proxy code changes needed                            |
| **Multi-backend proxy**       | !77 (TAR-1380)      | `/api/stream/*` automatically routed to Stream service by registry path-prefix matching          |
| **Correlation ID middleware** | !70 (TAR-1375)      | `X-Correlation-ID` propagated on all proxied requests — Stream gets distributed tracing for free |
| **Aggregated health/ready**   | !72 (TAR-1377)      | Gateway health checks ping all registered backends; Stream health is included automatically      |
| **OpenAPI merge**             | !71 (TAR-1376)      | Stream's OpenAPI schema is merged into the gateway's `/docs` — no manual aggregation needed      |

### Stream Registration

Stream registers in the ModuleRegistry config. The gateway handles everything else:

```text
/api/stream/*  → STREAM_BASE_URL (new Stream service, via registry)
/api/*         → SCREEN_BASE_URL (existing Screen service, default fallback)
```

No modifications to `proxy/routes.py`, `proxy/client.py`, or `core/config.py` are required — the registry-based routing resolves the target backend from the request path prefix automatically.

### Cross-Cutting Concerns (Free via Gateway v2)

- **Correlation ID**: Every request proxied to Stream includes `X-Correlation-ID` — Stream should log and propagate it on outbound calls (relay → ingest, channel adapter → external webhook)
- **Health aggregation**: Stream exposes `/health/ready` and `/health/live`; the gateway aggregates these into its own readiness probe
- **OpenAPI merge**: Stream's Swagger schema is automatically included in the gateway's combined docs at `/docs`
- **Internal JWT**: Stream receives the same `Internal <token>` header as Screen — standard `verify_internal_token()` dependency works unchanged

---

## Frontend Integration (Minimal)

The frontend for this iteration is intentionally minimal — just enough UI to create, manage, and test streams. Rich editing (split-view editor, chat agent, newsletter preview) is deferred to the agent iteration.

### "Nouveau" Dropdown

The existing "Nouveau" button dropdown (in `FoldersHeader.vue`) gains a "Stream" option:

```text
+ Nouveau ▼
├── Veille          (1000 crédits)
├── Fiche Entreprise (50 crédits)
├── Cartographie    (150 crédits)
└── Stream          (25 crédits)    ← NEW
```

Feature-flagged: only visible when `STREAM` flag is enabled for the organization.

### Folder View: Stream List

A dedicated **"Streams" tab** alongside object type tabs (Veille, Cartographie, Fiche Entreprise) in the folder view.

| Column             | Description                                         |
| ------------------ | --------------------------------------------------- |
| Nom du stream      | Stream name (link to edit page)                     |
| Canal              | Channel type icon + label (Teams / Slack / Webhook) |
| Mode               | Live / Récurrence                                   |
| Statut             | Badge: Brouillon / Actif / En pause                 |
| Dernière diffusion | Date of last dispatch (or "—")                      |

### Stream Form Page

Single-page form for creating and editing streams (see [Stream Creation Form](#stream-creation-form) above). On edit, the same form is pre-filled with existing values, plus:

- **Status toggle**: Brouillon → Actif / Actif → En pause
- **Delivery history section**: table at the bottom showing recent deliveries (Date, Statut, Détails)
- **"Envoyer un test"** button: sends a test payload to the configured channel (0 credits)

---

## Phased Delivery

| Phase                         | Scope                                                                                                                                                                                                                                                                                                                                   |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1 — Foundation (this ADR)** | Service skeleton + proxy routing + Channel adapters (Teams via Power Automate Workflow, Slack via OAuth bot token, generic Webhook) in live mode only + Event type subscription (hardcoded catalog) + Feature flag + Minimal frontend (form page, stream list in folder) + Screen outbox integration (push events on analysis complete) |
| **2 — Recurrence**            | APScheduler + recurrence mode for all channels + template-based digest generation + delivery history UI                                                                                                                                                                                                                                 |
| **3 — Event Auto-Discovery**  | Dynamic event catalog registration from producer services + multi-source producer support (Target)                                                                                                                                                                                                                                      |
| **4 — Agent & Newsletter**    | LangGraph content generation agent + split-view editor page with chat panel + Email/Newsletter channel + distribution list management (recipients, CSV import) + AI-generated digest summaries                                                                                                                                          |
| **5 — Observability**         | Stats dashboard (delivery rates, event volume, credit consumption) + admin tooling                                                                                                                                                                                                                                                      |

---

## Options Considered

### Option 1: Standalone Stream Service (Chosen)

**Description:** New FastAPI service at `apps/stream/` with dedicated database, following ADR-0009 gateway pattern.

**Pros:**

- Clean domain boundary (event queues, scheduling, channel integrations)
- Independent deployment and scaling
- Multiple services can push events (Screen now, Target/Explore later)
- Keeps Global Service focused on gateway + shared services
- Team can work independently on Stream features

**Cons:**

- Additional service to deploy and monitor
- New database to manage

### Option 2: Embed in Screen Service

**Description:** Add stream functionality directly to the existing Screen service.

**Pros:**

- No new service to deploy
- Direct access to Screen's database and models
- Simpler initial implementation

**Cons:**

- Screen service becomes bloated with unrelated domain logic
- Cannot receive events from Target/Explore without coupling
- Scaling Stream means scaling all of Screen
- **Rejected**: Violates separation of concerns, limits future extensibility

### Option 3: Embed in Global Service

**Description:** Add stream as a shared service within Global Service.

**Pros:**

- No new service infrastructure
- Direct access to folder and token systems

**Cons:**

- Global Service is a gateway, not a domain service
- Adds significant complexity (scheduling, channel adapters, AI agents) to the gateway
- APScheduler + LangGraph in the gateway increases failure blast radius
- **Rejected**: Over-burdens the gateway, violates its architectural role

### Option 4: Extend Chaps-e for Stream Creation (Future consideration)

**Description:** Reuse the existing Chaps-e chat in Screen service for stream creation, adding tool calling.

**Status:** Not applicable for this iteration (form-based UI). May be revisited when adding the LangGraph agent in Phase 4, but as a **dedicated Stream agent** rather than extending Chaps-e — see Phase 4 in delivery plan.

**Rejected rationale:** Wrong service boundary (Chaps-e is Screen-scoped), wrong abstraction level (mixes company research with stream config)

---

## Consequences

### Positive

- **Push distribution**: Users get intelligence delivered to their preferred channels without visiting the app
- **Clean architecture**: Stream is a focused service with clear boundaries
- **Extensible event system**: Any ChapsMind service can push events to Stream
- **Leverages Gateway v2**: Multi-backend routing, health aggregation, OpenAPI merge, and correlation ID propagation are already built — Stream benefits immediately
- **Iterative approach**: Minimal frontend validates the core pipeline before investing in AI agent and rich editor
- **Flexible pricing**: Credit costs per channel type align with actual resource consumption

### Negative

- **New infrastructure**: Additional service, database, and deployment pipeline
- **External dependencies**: Teams/Slack webhook APIs can be unreliable; need retry logic in adapters

### Neutral

- Stream is feature-flagged, so organizations opt in when ready
- Credit costs may need tuning based on real-world usage patterns
- Template-based digests are functional but lack the polish of AI-generated summaries (addressed in Phase 4)

---

## Files to Modify

| File                                                  | Change                                                                                                                                                                            |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `apps/global-service/app/models/organization.py`      | Add `STREAM = "stream"` to `FeatureFlag` enum                                                                                                                                     |
| `apps/screen/app/models/organization.py`              | Add `STREAM = "stream"` to `FeatureFlag` enum                                                                                                                                     |
| `apps/global-service/` ModuleRegistry config          | Register `stream` module with `STREAM_BASE_URL` — no proxy code changes needed (handled by registry auto-discovery from v2)                                                       |
| `apps/screen/app/agents/runner.py`                    | Write event to outbox table after analysis completion (same transaction)                                                                                                          |
| `apps/front/src/components/folders/FoldersHeader.vue` | Add "Stream" to "Nouveau" dropdown (feature-flagged)                                                                                                                              |
| `apps/front/src/pages/`                               | Add stream form page + stream list in folder view                                                                                                                                 |
| `apps/front/src/types/feature-flags.ts`               | Add `'stream'` to `FeatureFlagName` type                                                                                                                                          |
| `infra/compose.yaml`                                  | Add `stream` service + `stream_db` database + `STREAM_BASE_URL` env var for global-service + `SLACK_CLIENT_ID`, `SLACK_CLIENT_SECRET`, `SLACK_SIGNING_SECRET` env vars for stream |
| `infra/compose.local.yaml`                            | Add `stream` local dev service with volume mounts                                                                                                                                 |
| `Taskfile.yml`                                        | Add `stream:` include                                                                                                                                                             |

## New Files

```text
apps/stream/
├── app/
│   ├── adapters/                  # Channel adapters
│   │   ├── __init__.py
│   │   ├── base.py                # Abstract base adapter (ABC)
│   │   ├── teams.py               # Teams adapter (Power Automate Workflow URL)
│   │   ├── slack.py               # Slack adapter (Bot token + chat.postMessage)
│   │   └── webhook.py             # Generic HTTP webhook adapter
│   ├── api/
│   │   └── endpoints/
│   │       ├── streams.py         # CRUD endpoints
│   │       ├── event_types.py     # Event catalog endpoint
│   │       ├── slack_oauth.py     # Slack OAuth flow (future — deferred to Slack App upgrade)
│   │       ├── dispatch.py        # Dispatch, test delivery, test connection
│   │       ├── deliveries.py      # Delivery history
│   │       └── internal.py        # Event ingestion (internal JWT)
│   ├── core/
│   │   ├── config.py              # Settings (env vars)
│   │   ├── logging_config.py      # Logging setup
│   │   ├── internal_auth.py       # Internal JWT verification
│   │   └── event_catalog.py       # Hardcoded event type catalog
│   ├── models/
│   │   ├── stream.py              # Stream SQLAlchemy model
│   │   ├── event.py               # StreamEvent model
│   │   ├── delivery.py            # StreamDelivery model
│   │   └── slack_installation.py  # SlackInstallation model
│   ├── schemas/
│   │   ├── stream.py              # Pydantic schemas for streams
│   │   ├── event.py               # Event schemas
│   │   └── delivery.py            # Delivery schemas
│   ├── services/
│   │   ├── stream_service.py      # Stream CRUD business logic
│   │   ├── event_service.py       # Event ingestion and matching
│   │   ├── dispatch_service.py    # Dispatch orchestration
│   │   └── slack_service.py       # Slack webhook formatting (Phase 1), OAuth in future
│   ├── database.py                # Database engine and session
│   └── main.py                    # FastAPI app entry point
├── alembic/                       # Stream-specific migrations
│   ├── env.py
│   └── versions/
├── tests/
├── Dockerfile
├── Taskfile.yaml
├── pyproject.toml
└── run.py
```

---

## End-to-End Example: Target Document → Slack Notification

This example traces a single event from a Target watchfile alert to a Slack message, showing every system involved.

### Scenario

A user has a folder "Concurrents IA" with an active **live Slack stream** configured to post to `#veille-ia`. Target detects a new document matching a watchfile in that folder.

### Step-by-step flow

```text
 ① Target Service                ② target_db                  ③ Target Relay
 ┌─────────────────┐            ┌─────────────────┐          ┌──────────────────┐
 │ Watchfile crawl  │──INSERT───►│ documents table  │          │ Poll outbox      │
 │ finds new doc    │   (same   │ + outbox table   │◄─────────│ every 2-5s       │
 │                  │    tx)    │                  │          │                  │
 └─────────────────┘            └─────────────────┘          └────────┬─────────┘
                                                                      │ POST
                                                                      ▼
 ④ Stream Service (ingest)       ⑤ stream_db                  ⑥ Stream Service (dispatch)
 ┌─────────────────┐            ┌─────────────────┐          ┌──────────────────┐
 │ POST /internal/  │──INSERT───►│ stream_events    │          │ Match event to   │
 │ events/ingest    │           │                  │          │ active streams   │
 │                  │           │ stream_deliveries│◄─────────│ on folder        │
 └─────────────────┘            └─────────────────┘          └────────┬─────────┘
                                                                      │
                                                                      ▼
                                                              ⑦ Slack Adapter
                                                              ┌──────────────────┐
                                                              │ chat.postMessage │
                                                              │ → #veille-ia     │
                                                              └──────────────────┘
```

### Detailed steps

| Step  | System              | Action                                                                                                                                                                    | Data                                                                                                                                       |
| ----- | ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| **①** | **Target Service**  | Watchfile crawl detects a new document matching folder "Concurrents IA"                                                                                                   | Document: `{title: "OpenAI lance GPT-5", source: "Les Echos", url: "..."}`                                                                 |
| **②** | **target_db**       | Within the **same transaction**: insert the document row AND insert an outbox row                                                                                         | Outbox row: `{event_type: "watchfile_alert", folder_id: "abc-123", source_service: "target", payload: {title, source, url, watchfile_id}}` |
| **③** | **Target Relay**    | Polls `target_db.outbox` every 2-5s, picks up the new row, marks it as `processing`                                                                                       | Reads outbox row, builds HTTP payload                                                                                                      |
| **④** | **Stream Service**  | Receives `POST /internal/events/ingest` with internal JWT auth, validates payload                                                                                         | Inserts into `stream_events`: `{folder_id: "abc-123", event_type: "watchfile_alert", source_service: "target", payload: {...}}`            |
| **⑤** | **stream_db**       | Stream queries all **active streams** on folder `abc-123` whose `subscribed_events` include `target.document.published`                                                   | Finds 1 match: stream `id=42`, `channel_type=slack`, `mode=live`, `status=active`                                                          |
| **⑥** | **Stream Dispatch** | Since mode is `live`, dispatch immediately. Creates a `stream_deliveries` row with `status=pending`, consumes 5 credits via Global Service token API                      | Delivery row: `{stream_id: 42, event_id: 7, status: "pending"}`                                                                            |
| **⑦** | **Slack Adapter**   | Formats payload into a Slack Block Kit message, calls `chat.postMessage` using the organization's bot token to the channel stored in `streams.channel_config`             | Slack message: **"📄 Nouveau document — OpenAI lance GPT-5"** with source, link, and folder context                                        |
| **⑧** | **Stream Dispatch** | On 200 OK from Slack, updates delivery row to `status=delivered`, `delivered_at=now()`. On failure, retries with backoff, then marks `status=failed` with `error_message` | Relay marks outbox row as `delivered`                                                                                                      |

### What the user sees in Slack

```text
┌─────────────────────────────────────────────────┐
│ 📄  Nouveau document dans "Concurrents IA"      │
│                                                 │
│ OpenAI lance GPT-5                              │
│ Source : Les Echos                              │
│ 🔗 Voir le document                             │
│                                                 │
│ 🕐 il y a 3 secondes  •  via ChapsMind Stream  │
└─────────────────────────────────────────────────┘
```

### Recurrence mode variant

If the stream were configured as **recurrence** (e.g., weekly digest every Monday 9:00 CET) instead of live:

- Steps ①–⑤ are identical — the event is still ingested and stored
- Step ⑥ differs: instead of immediate dispatch, the delivery row is created with `status=pending` and a `batch_id`
- APScheduler fires the cron job on Monday 9:00 CET, collects all pending events for the stream since the last batch
- In Phase 2 (template-based), the digest is a simple aggregated list of events. In Phase 4 (agent), the LangGraph agent generates a rich summary using the stream's `digest_goal` and `digest_language`
- The Slack Adapter posts the digest as a single rich message (costs 10 credits/batch instead of 5/event)

### Key guarantees illustrated

- **At-least-once delivery**: the outbox write in step ② is atomic with the document insert — if Target commits the document, the event is guaranteed to exist
- **Decoupled services**: Target never calls Stream directly; the relay bridges the two via REST
- **Idempotent ingest**: Stream deduplicates on `(source, entity_id, organization_id)` unique constraint to handle relay retries safely
- **Credit control**: if the organization has insufficient credits at step ⑥, the delivery is marked `skipped` and no Slack message is sent

---

## Event Type Subscription

Inspired by Stripe's webhook event filtering, each stream subscribes to **specific event types** instead of receiving all events from a folder. This gives users granular control over what triggers their notifications from day one.

### Hardcoded Event Catalog (Phase 1)

The event catalog is **hardcoded in the Stream service** as a Python constant. The frontend fetches it via a simple endpoint. Auto-discovery from producer services comes in Phase 3.

```http
GET /api/stream/event-types

[
  {
    "source": "screen",
    "label": "Screen",
    "available": true,
    "events": [
      { "type": "screen.company.created",   "label": "Fiche entreprise créée" },
      { "type": "screen.company.updated",    "label": "Fiche entreprise actualisée" },
      { "type": "screen.task.completed",     "label": "Tâche de collecte terminée" }
    ]
  },
  {
    "source": "target",
    "label": "Target",
    "available": false,
    "events": [
      { "type": "target.watchfile.created",  "label": "Dossier de veille créé" },
      { "type": "target.watchfile.updated",  "label": "Dossier de veille actualisé" },
      { "type": "target.alert.triggered",    "label": "Alerte veille déclenchée" }
    ]
  }
]
```

The `available: false` flag lets the frontend display Target events as greyed-out / "bientôt disponible" — users see what's coming without being able to select them yet.

### Event Type Naming Convention

Pattern: `{source}.{resource}.{action}`

- **Source** = producing service (`screen`, `target`, `explore`)
- **Resource** = domain entity (`company`, `document`, `actor`, `watchfile`)
- **Action** = what happened (`created`, `refreshed`, `published`, `detected`, `alert`)

### Data Model

JSONB array `subscribed_events` on the `streams` table (see [Data Model](#data-model)):

```python
# Example stored value
subscribed_events = ["screen.company.created", "screen.company.refreshed"]
```

**Validation rule:** at least one event type must be selected when creating a stream.

### Event Matching Logic

```python
active_streams = [
    s for s in get_active_streams_for_folder(event.folder_id)
    if event.event_type in s.subscribed_events
]
```

### Future: Auto-Discovery (Phase 3)

Replace the hardcoded catalog with dynamic registration — each producer service declares its event types at startup (via config or registration endpoint), and Stream aggregates them. The API response format stays the same, so no frontend changes are needed.

---

## References

**Internal ADRs:**

- [ADR-0009 — Global Service Architecture](./0009-global-service-architecture.md)
- [ADR-0012 — LangGraph Agent System](./0012-langgraph-agent-system.md)
- [ADR-0013 — Chat Service Replacement](./0013-chat-service-replacement.md)
- [ADR-0015 — Multi-Module API Gateway](./0015-multi-module-gateway.md)

**Teams Integration:**

- [Retirement of Office 365 Connectors in Teams](https://devblogs.microsoft.com/microsoft365dev/retirement-of-office-365-connectors-within-microsoft-teams/) — legacy webhooks die April 30, 2026
- [Create Incoming Webhooks with Workflows](https://support.microsoft.com/en-us/office/create-incoming-webhooks-with-workflows-for-microsoft-teams-8ae491c7-0394-4861-ba59-055e33f75498) — Power Automate replacement
- [Adaptive Cards Documentation](https://adaptivecards.io/)

**Slack Integration:**

- [Slack OAuth V2 — Installing with OAuth](https://docs.slack.dev/authentication/installing-with-oauth/)
- [Slack chat.postMessage API](https://docs.slack.dev/reference/methods/chat.postMessage/)
- [Slack Block Kit Builder](https://app.slack.com/block-kit-builder/)
- [Classic Apps Deprecation (November 2026)](https://docs.slack.dev/changelog/2024-09-legacy-custom-bots-classic-apps-deprecation/)

**Other:**

- [APScheduler Documentation](https://apscheduler.readthedocs.io/)
