# ADR-0021: Stream Newsletter Channel

## Status

**Status:** Proposed — **amended 2026-04-30 (spec audit) + 2026-05-04 (live workflow validated, segment dropped)**

**Date:** 2026-04-23 (initial), 2026-04-30 (NP6 audit refonte), 2026-05-04 (segment-drop after live E2E)

**Decision Makers:** ChapsMind Engineering Team

**Tags:** backend, frontend, stream, newsletter, email, mjml, np6

**Prerequisite:** This ADR assumes **Phase 2 of [ADR-0020](./0020-stream-module-architecture.md)** — APScheduler + recurrence mode + batch dispatch — is merged. Newsletter is a recurrence-native channel: it does not make sense in live mode, and the scheduling infrastructure built for Teams/Slack/Webhook digests is a hard dependency here.

**Related:**

- [ADR-0009 — Global Service Architecture](./0009-global-service-architecture.md)
- [ADR-0012 — LangGraph Agent System](./0012-langgraph-agent-system.md) (Phase 4 AI copilote)
- [ADR-0015 — Multi-Module API Gateway](./0015-multi-module-gateway.md)
- [ADR-0020 — Stream Module Architecture](./0020-stream-module-architecture.md) (the parent Stream ADR)

> ⚠️ **Phase 1a refonte notice (2026-04-30 + 2026-05-04)** — the NP6 integration described in §"NP6 Integration — Isolated Module Inside Stream" was implemented speculatively against an HTML-rendered version of the NP6 docs that did not expose the OpenAPI security/payload schemas. A follow-up audit against `https://documentation.np6.com/_bundle/api.yaml` revealed five major divergences from the real API (auth header, action body shape, send endpoint, MIME type, and the absence of any push-webhook mechanism). A second amendment on 2026-05-04 — after live workflow validation with a NP6 contact — dropped the segment-per-stream design (NP6 confirmed segments aren't used for `mailMessage` action dispatch) and clarified the **two-phase validation** (`{fortest:true, testSegments:[id]}` + `{fortest:false}`) required to advance an action from state 20 → 38 → 50 before it can be executed. The corrected design is captured in **§"NP6 API Contract — Verified Against Spec"**, **§"Mapping Newsletter Concepts to NP6 Primitives"** and **§"Pull-Based Events"** below. Sections describing the original speculative shape are kept for historical context but flagged with `[VOIDED]` callouts. The Phased Delivery table marks Phase 1a as **rewritten in-place** rather than re-numbered, since no production traffic ever ran against the speculative implementation. See [docs/np6/README.md](../../np6/README.md) for the up-to-date integration guide and `x-request-id` debug recipe.

---

## Context

Stream (ADR-0020) ships with three push channels — **Teams, Slack, Webhook** — that deliver a single event as a single message, live. The Figma design and customer requests introduce a fourth channel, **Newsletter**, whose shape is fundamentally different:

| Dimension   | Teams / Slack / Webhook     | Newsletter                                         |
| ----------- | --------------------------- | -------------------------------------------------- |
| Trigger     | Per event (live)            | Scheduled (weekly / monthly)                       |
| Cardinality | 1 event → 1 message         | N events → 1 digest                                |
| Artifact    | Chat message / HTTP payload | Editorial HTML email with subject, theme, sections |
| Destination | URL                         | Recipient list (emails)                            |
| Authoring   | Config form                 | Template composition (sections, theme, subject)    |
| Delivery    | HTTP POST                   | SMTP via NP6                                       |

Treating Newsletter as "another adapter like the others" would miss the point — it has its own authoring workflow, its own scheduling semantics (recurrence-only), its own rendering pipeline (HTML email with client compatibility concerns), and its own delivery backend (NP6 in-house email platform). It also has its own per-recipient cost model.

This ADR designs Newsletter in a way that reuses everything reusable from ADR-0020 (event ingestion, outbox relay, stream lifecycle, credit system, correlation IDs, gateway routing) while cleanly extending what needs extending (adapter contract, data model, frontend form).

### Scope boundaries

**In scope (this ADR):**

- The `newsletter` channel type, its `channel_config` shape, and the `NewsletterAdapter`
- Email template library choice and rationale
- Section-based template assembly model (events → sections → theme → HTML)
- NP6 integration boundary (isolated integration module inside Stream)
- Data model additions (`newsletter_themes`, `section_templates`, `stream_recipients`, `recipient_suppressions`)
- Minimal frontend authoring page (section picker + theme picker + recipients + preview)
- **Security model**: preview API hardening, sandboxed substitution engine, sender authentication policy, signed unsubscribe tokens
- **Unsubscribe & compliance**: one-click unsubscribe endpoint, suppression list, RFC 8058 `List-Unsubscribe` headers, NP6 bounce/complaint sync into suppression list
- **Operational constraints**: email size caps, events-per-batch cap, asset hosting policy, pre-send weight check
- Phased delivery plan with a sub-phased Phase 1 (1a–1d)

**Out of scope:**

- Scheduling infrastructure — owned by ADR-0020 Phase 2
- NP6 SMTP plumbing / deliverability tuning / per-IP reputation management — in-house NP6 ops capability (we just consume the API and respect its constraints)
- Per-organization custom From addresses (deferred until SPF/DKIM/DMARC org-by-org provisioning workflow exists; documented as a future path)
- Chaps-e LangGraph copilote shown in the Figma — deferred to Phase 4 (separate ADR)
- Visual drag-drop email editor (Unlayer / GrapeJS) — deferred to Phase 5 evaluation
- Email analytics dashboard (open rate, click rate visualization) — deferred to Phase 5; raw NP6 delivery events (pulled by the event poller) are stored in `stream_deliveries.response_metadata` from Phase 1c so the data is available when the dashboard ships

### Requirements

1. **Section-based authoring**: each event type declares one or more section templates; user assembles a newsletter by picking ordered sections + a theme
2. **Recipient management**: manual list + CSV import; optional "all folder members" / "org users" deferred
3. **Recurrence**: inherits ADR-0020 Phase 2 cron (daily / weekly / monthly / custom)
4. **Preview**: live HTML preview in the authoring UI with sample or real event data
5. **Test send**: dispatch to a single email for validation (0 credits)
6. **NP6 boundary**: NP6 specifics must not leak into business logic (adapter, dispatch, templating); an isolated integration module owns them
7. **No new deployable service**: email delivery is folded into the Stream service — the team has explicitly ruled out standing up a separate email gateway to avoid the ops / infra cost
8. **Sender authentication**: the From address is provider-managed (single fixed `NP6_FROM_EMAIL` for all orgs in Phase 1) — the sending domain MUST be SPF / DKIM / DMARC aligned with NP6 to avoid spoofing flags and protect the shared NP6 IP reputation. No per-stream or per-org From override in Phase 1.
9. **Unsubscribe & compliance**: every newsletter dispatch carries a one-click unsubscribe link (footer + RFC 8058 `List-Unsubscribe` header); a dedicated suppression list filters recipients before each dispatch; hard bounces and complaints pulled from NP6 by the event poller auto-populate the suppression list (RGPD / CAN-SPAM compliance is a Phase 1 hard requirement, not deferred)
10. **Email size constraints**: Phase 1 enforces a configurable cap on events per batch (default 50) to avoid Gmail's 102 KB clip threshold; rendered HTML > 200 KB aborts the dispatch with a clear failure status
11. **Template injection safety**: payloads from event ingestion or the preview API are treated strictly as data; the substitution engine (sandboxed Jinja) never re-evaluates payload values as templates, eliminating SSTI as an attack vector
12. **Feature-flagged**: the existing `STREAM` flag gates the whole module; newsletter ships under it

---

## Decision

Build Newsletter as a **fourth channel type within the existing Stream service**, not a new service. Reuse the `ChannelAdapter` pattern with a minor extension for batch dispatch. Render emails with **MJML** via `mjml-python` (Rust-backed, Python-native, no Node runtime). Isolate NP6 behind a dedicated **integration module inside Stream** (`apps/stream/app/integrations/email/`) — no new deployable service, but a clear architectural seam so the NP6-specific code can be replaced or extracted later without touching the adapter, dispatch, or templating layers.

Template authoring in Phase 1 is a **section picker + theme picker** — no visual drag-drop editor, no AI copilote. The Figma copilote goal is a future Phase 4, gated by user validation of the section-picker foundation.

### Architecture overview

```text
             ┌─────────────────────────────────────────────────┐
             │                  Frontend SPA                   │
             │      (Newsletter form, Section picker,          │
             │       Theme picker, Live preview iframe)        │
             └────────────────────────┬────────────────────────┘
                                      │ REST
                                      ▼
             ┌─────────────────────────────────────────────────┐
             │           Global Service (Gateway v2)           │
             │         /api/stream/* → Stream Service          │
             └────────────────────────┬────────────────────────┘
                                      │
                                      ▼
             ┌─────────────────────────────────────────────────┐
             │                 Stream Service                  │
             │                                                 │
             │  ┌───────────────────────────────────────────┐  │
             │  │  NewsletterAdapter (BatchChannelAdapter)  │  │
             │  │  ├─ assemble sections                     │  │
             │  │  ├─ render MJML → HTML (mjml-python)      │  │
             │  │  └─ call EmailProvider.send()             │  │
             │  └──────────────────┬────────────────────────┘  │
             │                     │ Python call               │
             │                     ▼                           │
             │  ┌───────────────────────────────────────────┐  │
             │  │  integrations/email/  (NP6 isolation)     │  │
             │  │  ├─ EmailProvider ABC  (generic shape)    │  │
             │  │  ├─ NP6EmailProvider   (NP6 specifics)    │  │
             │  │  ├─ event poller       (pulls NP6 events)│  │
             │  │  └─ retry / normalization                 │  │
             │  └──────────────────┬────────────────────────┘  │
             │                     │ HTTPS                     │
             └─────────────────────┼───────────────────────────┘
                                   │
                          ┌────────┴─────────┐
                          │   NP6 Platform   │
                          │   (ChapsVision)  │
                          └──────────────────┘

      (Delivery feedback is pulled — APScheduler tick calls
       GET /actions/events?start=...; bounces/complaints feed
       the suppression list and stream_deliveries updates)
```

### Event-to-newsletter flow

```text
Recurrence scheduler fires (ADR-0020 Phase 2)
        │
        ▼
Newsletter streams due at this cron tick
        │
        ▼
For each stream:
  1. Collect stream_events since last batch (batch_id)
  2. Group events by event_type
  3. For each configured section in channel_config.sections[]:
       render MJML snippet(event_type) with matching events as context
  4. Wrap sections in the theme's MJML header/footer
  5. Compile MJML → inlined HTML with mjml-python
  6. Render subject_template with {folder_name}, {period}, counts
  7. POST { subject, html, recipients[], from } to email-gateway
  8. Mark stream_deliveries rows as delivered/failed
```

---

## Section-Based Template Model

### Concept

The user sketched it this way: _"every event that the user can subscribe to comes with sections that the user can add to his newsletter"_. This maps directly to MJML component reuse.

- An **event type** (e.g. `screen.company.created`) declares one or more **section templates** — MJML snippets with placeholders mapped to the event payload.
- A **theme** is a global MJML wrapper: header (logo, period title), footer (unsubscribe, branding), brand colors, typography. Themes do not depend on event types.
- A **newsletter stream's `channel_config`** stores the ordered assembly: which sections from which events, in what order, wrapped in which theme.

### Example

For event type `screen.company.created`, a section template `company_card_v1` might be:

```mjml
<mj-section background-color="#ffffff" padding="16px">
  <mj-column>
    <mj-text font-size="18px" font-weight="bold">{{ company.name }}</mj-text>
    <mj-text font-size="14px" color="#666">{{ company.industry }}</mj-text>
    <mj-text>{{ event.summary }}</mj-text>
    <mj-button href="{{ chapsmind_url }}">Voir la fiche</mj-button>
  </mj-column>
</mj-section>
```

A stream's `channel_config` composing this section into a newsletter:

```json
{
  "theme_id": "theme_minimal_v1",
  "subject_template": "Digest hebdo — {folder_name} ({period})",
  "sections": [
    {
      "event_type": "screen.company.created",
      "section_template_id": "company_card_v1",
      "order": 1
    },
    { "event_type": "screen.company.updated", "section_template_id": "company_diff_v1", "order": 2 }
  ],
  "recipients_source": "list"
}
```

> **Note on `from` / `reply_to`:** intentionally absent from `channel_config`. The sending address is provider-managed (single fixed `NP6_FROM_EMAIL` for all dispatches in Phase 1) to guarantee SPF / DKIM / DMARC alignment with NP6's authenticated sending domains. See **Sender Authentication** below.

At dispatch time, the adapter iterates the matched events, groups them by `event_type`, and renders each configured section once per event (or once aggregating all events for that type — declared by the section template itself, see `rendering_mode` below).

### Section template schema

Each section template declares:

| Field                   | Purpose                                                                                  |
| ----------------------- | ---------------------------------------------------------------------------------------- |
| `id`                    | Stable identifier (e.g. `company_card_v1`)                                               |
| `event_type`            | The event type this section renders (e.g. `screen.company.created`)                      |
| `name`                  | Internal name                                                                            |
| `label_fr` / `label_en` | Human labels for the picker UI                                                           |
| `mjml_snippet`          | The MJML template body (per locale)                                                      |
| `required_fields`       | JSONB array of payload fields the template needs (for validation)                        |
| `rendering_mode`        | `per_event` (one render per event) or `aggregated` (single render, all events as a list) |
| `preview_image_url`     | Thumbnail shown in the section picker                                                    |
| `is_default`            | Whether this section is pre-selected for its event type                                  |

---

## Email Template Library — Options Considered

### Option A: MJML via `mjml-python` (Recommended)

**Description:** MJML is a mature declarative email framework that compiles to table-based HTML with inlined CSS and MSO conditionals for Outlook. `mjml-python` wraps MRML — a Rust port of the MJML engine — for in-process compilation, no Node runtime required.

**Pros:**

- **No Node dependency**: Stream is Python/FastAPI; `pip install mjml-python` is the only add
- **Battle-tested client compatibility**: Outlook (desktop + web), Gmail, Apple Mail, mobile clients all covered
- **Responsive by default**: `<mj-section>` / `<mj-column>` auto-stack on mobile, no media query management
- **Performance**: MRML uses <3MB RAM under load, sub-ms render times (per MRML benchmarks)
- **Section templates are plain MJML fragments**: designers can edit them without touching Python
- **Stable declarative syntax**: no framework churn, no build step

**Cons:**

- Declarative only (no programmatic JSX/TSX composition) — mitigated by the fact that our composition logic lives in Python anyway
- MRML tracks the official MJML spec but occasionally lags on the newest `<mj-*>` components — acceptable, we use the stable subset

**Fit:** Perfect for our stack and our Phase 1 scope.

### Option B: React Email

**Description:** JSX components for email, compiled to HTML at render time.

**Pros:**

- Excellent DX for JS/TS teams
- Componentization via React composition

**Cons:**

- Requires **Node.js runtime inside the Stream service** — adds a second language and build toolchain for no DX benefit (Stream is Python)
- Forces us to either spawn a Node subprocess per render (slow, fragile) or run a sidecar service just for rendering

**Rejected:** wrong language ecosystem for our backend.

### Option C: Maizzle

**Description:** Tailwind-CSS-for-email framework, CLI-based.

**Pros:**

- Familiar for Tailwind developers
- Full HTML control

**Cons:**

- **Build-time tool, not a runtime renderer** — Maizzle produces static HTML files during a CLI build. Our use case requires rendering at dispatch time with dynamic event data, which Maizzle doesn't target.

**Rejected:** wrong shape for dynamic server-side rendering.

### Option D: Hand-rolled HTML + Jinja2

**Description:** Write table-based HTML directly, use Jinja2 for templating.

**Pros:**

- Zero new dependencies (we already have Jinja2 via FastAPI)
- Full control

**Cons:**

- **Reimplementing MJML badly**: Outlook MSO conditionals, Gmail clipping at 102KB, dark mode inversions, mobile breakpoints, CSS inlining — all these are months of accumulated edge-case engineering that MJML solves for us
- Every new template requires a cross-client QA pass we can't afford

**Rejected:** reinventing a solved problem.

### Option E: Unlayer / GrapeJS (visual drag-drop editors)

**Description:** Embed a visual email builder in the frontend so users design HTML freely.

**Pros:**

- Most flexible UX
- Matches what non-technical users expect

**Cons:**

- Unlayer free tier ships a "Powered by Unlayer" footer; white-label requires a paid plan
- GrapeJS needs significant integration to bolt onto our component library and design system
- Both produce HTML (not MJML), which sidesteps the section-picker composition model central to this ADR
- Mismatched with Phase 1 scope (section-picker-first)

**Deferred:** revisit in Phase 5 if customer demand justifies.

### Recommendation

**MJML via `mjml-python`** (Option A). Python-native, Rust-backed, no Node runtime, battle-tested client compatibility, and section templates are plain MJML fragments that designers can iterate on independently.

---

## NP6 API Contract — Verified Against Spec

> Added 2026-04-30. Source of truth: `https://documentation.np6.com/_bundle/api.yaml` (NP6 8.1.0, fetched 2026-04-30). HTML view at `https://documentation.np6.com/api` does not expose `securitySchemes` or schema discriminators — always cross-reference against the YAML bundle.

### Auth

- **Header:** `X-Key: <api_key>` (custom API-key scheme, NOT `Authorization: Bearer`)
- **Required on every endpoint** (the bundle declares it as a required `header` parameter on each operation, not as a global security scheme — implementations must stamp it explicitly)

### Endpoints we actually use

| Operation                        | Verb + path                                                      | Content-Type                   | Body shape                                                                                                                                   | Response                                                                                                                                                                           |
| -------------------------------- | ---------------------------------------------------------------- | ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Create email action (template)   | `POST /actions`                                                  | `application/json`             | `SubstitutionAction` discriminated by `type` — for emails: `{type: "mailMessage", name, settings: {...}, content: {...}}`                    | `SubstitutionAction#Discriminable` with `id`                                                                                                                                       |
| Add contacts to a static segment | `PUT /segments/{id}/targets`                                     | `application/json`             | array of target identifiers, **max 50 per request**                                                                                          | (per spec)                                                                                                                                                                         |
| Provision contact                | `POST /targets`                                                  | `application/json`             | `Document` with field-mapped values; minimum one mapped field that holds the email                                                           | created target with `id`                                                                                                                                                           |
| Lookup contact                   | `GET /targets?...`                                               | n/a                            | query params                                                                                                                                 | array of targets                                                                                                                                                                   |
| Create static segment            | `POST /segments`                                                 | `application/json`             | `{type: "static", name, description, isTest}`                                                                                                | segment with `id`                                                                                                                                                                  |
| **Send to recipients**           | `POST /actions/{id}/executions` (batch) or `/execution` (single) | `application/vnd.np6.cm.email` | array of `ExecutionEmailRequest`: `{recipient: {type: id\|unicity\|hash\|footprint, value: ...}, copies?, subject?, content?, attachments?}` | array of `ExecutionResult#Discriminable` with `type: success\|error`, `id`, `recipient: {type: footprint, id, unicity, hash}`                                                      |
| **Pull delivery events**         | `GET /actions/events?start=<ISO\|ts>&end=<ISO\|ts>&sort=...`     | n/a                            | query params (cursor)                                                                                                                        | array of `BaseEvent#Discriminable` — types: `bounce` (with `analysis.verdict: hard\|soft`), `hit` (with `link.type: redirection\|open\|unsubscribe\|...`), `complaint`, `delivery` |

### What does NOT exist in NP6

- **No push webhook.** The strings `webhook`, `callback`, `signature` do not appear anywhere in the OpenAPI bundle. Delivery feedback is **pull-based** via `GET /actions/events`. The `verify_webhook()` ABC method and the `/api/stream/email/webhook` endpoint described in earlier drafts of this ADR were a misreading of the API surface and are removed.
- **No anonymous send.** `recipient.type` accepts only `id`, `unicity`, `hash`, or `footprint` — all references to a contact that already exists as a NP6 `target`. Sending to an arbitrary email without provisioning is not supported. We accept this constraint and pre-provision every recipient via `upsert_contact` when they are added to a stream's distribution list (see §"Mapping Newsletter Concepts to NP6 Primitives"); `unicity` and `hash` are never used as auto-provisioning shortcuts.
- **No recurring scheduler at the action level.** `ActionSchedulerModel` discriminator types are `asap`, `scheduled`, `timeZoneSchedule` only. Recurrence (cron) lives in NP6's Scripting / Workflows surface, which Stream does not use — Stream owns its own APScheduler from ADR-0020 Phase 2.

### Rendering vs sending

`POST /actions/{id}/renders` is a **preview** endpoint (returns `RenderMailResult`) — it does NOT trigger delivery. The earlier two-step flow described in this ADR (`POST /actions` then `POST /actions/{id}/renders` to dispatch) was incorrect. The real send endpoint is `/executions`.

### Sender shape

`content.headers.from` is an object: `{prefix: <local-part>, domain: <domain>, label: <display name>}`. Our `NP6_FROM_EMAIL` env var still holds a single email string (`noreply@chapsmind.com`); the provider parses it into `{prefix, domain}` and stamps a static `label` (configurable via a follow-up `NP6_FROM_LABEL` env var).

---

## Mapping Newsletter Concepts to NP6 Primitives

> Added 2026-04-30. **Amended 2026-05-04 after live workflow validation with NP6** — segment dropped from the design (NP6 confirmed segments aren't used for `mailMessage` action dispatch), validation phase clarified as 2-step.

The user-visible mental model is "one newsletter = one distribution list managed in ChapsMind". NP6's `/executions` endpoint takes the recipient list **directly in the body** for `mailMessage` actions — no segment indirection needed. We adopt a **1 NP6 action ↔ 1 ChapsMind stream** mapping, with recipients passed explicitly at dispatch time.

| ChapsMind concept                               | NP6 primitive                                                                                                | Persistence                                                                      |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| Newsletter stream                               | 1 NP6 `action` (`type: mailMessage`) — created once, updated on each dispatch                                | `streams.np6_action_id` column (nullable)                                        |
| `stream_recipient.email`                        | NP6 `target` (contact) — created once, idempotent (POST returns 409 "already exists")                        | **No persistent ID needed** — addressed by `unicity` (= email) at execution time |
| Section + theme rendering output                | `content.html` of the action (single rendered HTML, optional NP6 substitutions for per-recipient values)     | unchanged                                                                        |
| Scheduled dispatch                              | `POST /actions/{id}/executions` with body = explicit array of `{recipient: {type:"unicity", value:[email]}}` | unchanged                                                                        |
| Bounce / complaint / open / click / unsubscribe | `GET /actions/events` polled by Stream APScheduler with a persistent cursor                                  | `np6_event_cursors` table                                                        |
| Per-recipient unsubscribe URL                   | NP6 substitution placeholder in `content.html` — **syntax still TBC with NP6** (Open Question §10)           | flagged as Open Question                                                         |

### Two-phase validation (mandatory before first dispatch)

> **Live confirmation 2026-05-04** : on a passé du temps à comprendre pourquoi `/executions` retournait 409 sur action en state 38. NP6 a tracé l'erreur backend : **la phase de test a un sous-statut "completed/uncompleted"** que seule la phase prod (`{fortest:false}`) clôt. Sans la phase 2, l'action reste bloquée.

```text
state 20 ──POST /validation──► state 38 ──POST /validation──► state 50 ──POST /executions──► sent
            {fortest: true,                 {fortest: false}                    │
             testSegments:                  (production                          │
             [BAT_TEST_ID]}                  validation)                         ▼
                                                                          200 + message_id
```

Practical: each newsletter stream's action goes through this 2-phase validation **once**, the first time we dispatch. Subsequent dispatches reuse the action in state 50 (no re-validation needed unless content changes substantially or NP6 dictates otherwise — tbd).

### Idempotency contract (provider-level invariants)

- `ensure_action(stream, html, subject)` — creates the NP6 action on first call, updates `content.html` / `subject` on subsequent calls (hash compare to skip no-op). Persists `np6_action_id` on the stream row.
- `validate_action(action_id)` — if action is state 20, runs the 2-phase validation: `{fortest:true, testSegments:[BAT_TEST_ID]}` → state 38, then `{fortest:false}` → state 50. Idempotent (returns 409 if already validated, ignored).
- `upsert_target(email)` — POST to `/targets` with `{"Email": email}` (note: field NAME at top-level, not `fields:{id:val}`). Returns 200 if created, 409 if already exists; both are success for our purpose.
- `dispatch(action_id, emails)` — single API call to `/actions/{id}/executions` with body = `[{recipient: {type:"unicity", value:[email]}} for email in emails]`. Returns the list of `ExecutionResult` (one per recipient with `success|error` discriminator).

### Test-send (`POST /api/stream/streams/{id}/test-send`)

Reuses the same flow with a single-recipient body: ensures action+validation, upserts the test target, runs `/execution` (singular) for the one recipient. No segment involved.

### What we **don't** do anymore

- ❌ Create a NP6 segment per stream (`ensure_segment`) — confirmed unnecessary
- ❌ Maintain `np6_segment_id` on the stream — column dropped from migration 004
- ❌ Maintain `np6_target_id` on stream_recipients — addressed by email/unicity at runtime
- ❌ `add_to_segment` calls — replaced by passing recipients directly to `/executions`

---

## Pull-Based Events (replaces "Webhook endpoint")

> Added 2026-04-30. Supersedes the `POST /api/stream/email/webhook` design — see VOIDED block below.

NP6 exposes delivery events via a polling endpoint:

```text
GET /actions/events?start=<unix_ms_or_ISO>&end=<unix_ms_or_ISO>&sort=asc
X-Key: <NP6_API_KEY>
```

Response is an array of `BaseEvent#Discriminable`. The relevant types for Stream are:

| NP6 event                               | Discriminator                                                 | Our normalised type                  |
| --------------------------------------- | ------------------------------------------------------------- | ------------------------------------ |
| `bounce` with `analysis.verdict = hard` | `type: bounce`, `source.detail.bounce.analysis.verdict: hard` | `bounce_hard`                        |
| `bounce` with `analysis.verdict = soft` | same with `soft`                                              | `bounce_soft`                        |
| `complaint`                             | `type: complaint`                                             | `complaint`                          |
| `delivery`                              | `type: delivery` (or `delivered`)                             | `delivered`                          |
| `hit` with `link.type: open`            | `type: hit`, `source.detail.link.type: open`                  | `open`                               |
| `hit` with `link.type: redirection`     | same with `redirection`                                       | `click`                              |
| `hit` with `link.type: unsubscribe`     | same with `unsubscribe`                                       | `unsubscribe` (triggers suppression) |

### Poller design

- **Service:** `apps/stream/app/services/np6_event_poller.py`
- **Trigger:** APScheduler job (already provisioned in ADR-0020 Phase 2), default cadence `*/5 * * * *` (every 5 min — env-tunable via `STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS`)
- **Cursor table:** `np6_event_cursors (organization_id PK, last_pulled_at TIMESTAMP, last_event_id TEXT, last_run_at TIMESTAMP)` — one row per organization (events are scoped by NP6 customer/agency identity, which we map 1:1 to ChapsMind organization)
- **Pull window:** `start = last_pulled_at`, `end = now() - 30s` (30s safety buffer to avoid racing NP6's event commit)
- **Idempotency:** event-level dedup by `event.id` (NP6 events carry stable UUIDs); skip if seen recently (in-memory LRU per run)
- **Dispatch path:** poller normalises into `EmailDeliveryEvent` (existing schema), then calls
  - `suppression_service.handle_event(event)` for `bounce_hard`, `complaint`, `unsubscribe` → inserts into `recipient_suppressions`
  - `delivery_service.handle_event(event)` for `delivered`, `open`, `click`, `bounce_soft` → updates `stream_deliveries.response_metadata`
- **Error handling:** if the GET fails, the poller does NOT advance `last_pulled_at`, so events are retried on the next tick. Repeated failures emit a `STREAM_NP6_POLLER_FAILING` log + metric.

### Webhook endpoint removal

The endpoint `POST /api/stream/email/webhook` is removed in the refonte. `verify_webhook()` and `normalize_event()` move out of `EmailProvider`'s ABC into the poller service (only one caller). Tests `test_email_webhook.py` and `test_email_webhook_suppression.py` are deleted.

---

## NP6 Integration — Isolated Module Inside Stream

### Why isolate (but not extract into a new service)

NP6 is ChapsVision's in-house marketing automation platform. Its API ([documentation.np6.com/api](https://documentation.np6.com/api)) exposes a campaign-oriented surface (`/actions`, `/segments`, `/targets`, `/executions`, `/events`). Stream only needs a narrow "send this HTML to this distribution list, then poll for delivery events" capability.

If NP6 specifics were scattered through the `NewsletterAdapter`, dispatch service, and delivery status handling:

- A second email provider (future fallback, A/B testing, regional routing) would require rewriting the adapter
- Other ChapsMind modules that eventually need email (password reset, invitations, admin notifications) would each reinvent NP6 integration
- NP6 auth, rate limiting, and event-poller cursor management would leak into business logic

The obvious answer would be a separate email-gateway service (classic hexagonal ports/adapters layout). **We explicitly reject that** for this iteration: standing up a new deployable service, DB config, CI pipeline, monitoring surface, and ModuleRegistry wiring is disproportionate to the payoff. A well-isolated **module inside Stream** gives us the same substitutability seam without the ops cost. If email volume, blast radius concerns, or a second consumer later justifies extraction, the module's shape is a drop-in starting point for a standalone service — same interface, same DTOs, same event poller.

### Module shape (refonte, 2026-04-30)

**Location:** `apps/stream/app/integrations/email/`

**Structure:**

```text
apps/stream/app/integrations/email/
├── __init__.py
├── provider.py       # EmailProvider ABC — high-level newsletter contract
├── np6_client.py     # NEW — low-level wrapper, 1 method ↔ 1 NP6 endpoint
├── np6.py            # NP6EmailProvider — orchestrates np6_client around the ABC
├── schemas.py        # EmailSendRequest / EmailSendResult / EmailDeliveryEvent DTOs
└── factory.py        # get_email_provider() DI helper
```

**Generic contract (`provider.py`)** — amended 2026-05-04 (segment dropped):

```python
class EmailProvider(ABC):
    @abstractmethod
    async def ensure_action(self, stream: Stream, html: str, subject: str) -> str:
        """Idempotent. Creates or updates the provider-side template, returns its id."""

    @abstractmethod
    async def validate_action(self, action_id: str) -> None:
        """Run the 2-phase validation if action is in state 20: {fortest:true, testSegments:[id]}
        then {fortest:false}. No-op if already in state 50. Idempotent."""

    @abstractmethod
    async def upsert_target(self, email: str) -> None:
        """Provision a contact via POST /targets {Email: email}. Treats 409 'already exists'
        as success. No id is returned because we address contacts by unicity (= email) at
        execution time."""

    @abstractmethod
    async def dispatch(self, action_id: str, emails: list[str]) -> list[ExecutionResult]:
        """POST /actions/{id}/executions with body = [{recipient: {type:'unicity', value:[email]}} ...].
        Returns per-recipient results."""

    @abstractmethod
    async def send(self, request: EmailSendRequest) -> EmailSendResult:
        """Single-recipient ad-hoc send (used by the test-send endpoint and the CLI)."""

    @abstractmethod
    async def pull_events(self, since: datetime) -> list[EmailDeliveryEvent]:
        """Pull-based delivery feedback. Caller persists the cursor."""
```

`verify_webhook()` and `normalize_event()` are removed from the ABC — only the poller calls the latter, and the former no longer applies (no NP6 webhook).

**NP6 implementation (`np6.py` + `np6_client.py`):**

- Reads `NP6_BASE_URL`, `NP6_API_KEY`, `NP6_FROM_EMAIL`, optionally `NP6_FROM_LABEL` from Stream settings (note: `NP6_WEBHOOK_SECRET` removed in the refonte)
- `np6_client.py` exposes one async method per NP6 endpoint we use, with `httpx.AsyncClient` (header `X-Key`, retries on 5xx + transport, MIME `application/vnd.np6.cm.email` on `/executions`)
- `np6.py` orchestrates: parses `NP6_FROM_EMAIL` into `{prefix, domain}`, persists the NP6 ids back onto `streams` / `stream_recipients` for idempotency, normalises events
- Sender invariant preserved: every action created by `ensure_action` stamps `content.headers.from` from settings — callers cannot override

**How the adapter consumes it:**

```python
# apps/stream/app/adapters/newsletter.py
from app.integrations.email import EmailProvider, get_email_provider

class NewsletterAdapter(BatchChannelAdapter):
    def __init__(self, email_provider: EmailProvider = Depends(get_email_provider)):
        self._email = email_provider

    async def send_batch(self, events, stream) -> DispatchResult:
        rendered = self._render(events, stream)  # MJML → HTML, payload sandboxed
        eligible = self._suppression.filter_recipients(stream.id, stream.organization_id)
        emails = [r.email for r in eligible]

        action_id = await self._email.ensure_action(stream, html=rendered.html, subject=rendered.subject)
        await self._email.validate_action(action_id)            # 2-phase, idempotent (no-op if already validated)
        for email in emails:
            await self._email.upsert_target(email)              # 409 ignored
        execution_results = await self._email.dispatch(action_id, emails)

        # Persist one stream_delivery per recipient, mapping NP6 execution result → success/error
        return DispatchResult.from_execution_results(execution_results)
```

**Pull-based delivery feedback:** see §"Pull-Based Events" above. No webhook endpoint. The poller service (`np6_event_poller.py`) is APScheduler-driven and lives under `apps/stream/app/services/`, separately from the integration module.

> ⚠️ `[VOIDED]` — Earlier drafts described a `POST /api/stream/email/webhook` endpoint with `X-NP6-Signature` HMAC verification. NP6 has no push-webhook surface; the endpoint and the signature scheme are removed. The text below is preserved for historical context only.
>
> ```text
> POST /api/stream/email/webhook       (public, verified by NP6 signature)
> X-NP6-Signature: ...
> → Stream's webhook endpoint calls email_provider.verify_webhook()
> → If valid, calls email_provider.normalize_event(payload)
> → Updates matching stream_deliveries row (by message_id stored in response_metadata)
> ```

### Responsibilities (refonte)

| Concern                                                                                             | Owned by                                                                                                                |
| --------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| NP6 auth (`X-Key`) + API keys                                                                       | `integrations/email/np6_client.py`                                                                                      |
| Wire-level mapping for `/actions`, `/actions/{id}/validation`, `/targets`, `/executions`, `/events` | `integrations/email/np6_client.py`                                                                                      |
| Retries, rate limiting, backoff, circuit-breaking                                                   | `integrations/email/np6_client.py`                                                                                      |
| Idempotent provisioning (`ensure_action`, `validate_action`, `upsert_target`)                       | `integrations/email/np6.py`                                                                                             |
| Sender invariant (parsing `NP6_FROM_EMAIL` into `{prefix, domain, label}`)                          | `integrations/email/np6.py`                                                                                             |
| Bounce/complaint/hit event normalisation                                                            | `integrations/email/np6.py` (`pull_events`)                                                                             |
| Polling cadence + cursor persistence                                                                | `services/np6_event_poller.py`                                                                                          |
| MJML rendering, template composition                                                                | `services/newsletter_service.py` (unchanged — no NP6 leak)                                                              |
| Delivery status persistence                                                                         | `services/dispatch_service.py` + `services/delivery_service.py` (consume normalised events, update `stream_deliveries`) |
| Suppression updates from poller events                                                              | `services/suppression_service.py`                                                                                       |
| Recipient list management                                                                           | `services/recipient_service.py` (no NP6 concern)                                                                        |

### Deployment footprint

- **No new service** — code lives in Stream
- **No new database** — delivery history stays in Stream's `stream_deliveries`; NP6 owns long-term campaign history
- **New env vars on Stream (refonte):** `NP6_BASE_URL`, `NP6_API_KEY`, `NP6_FROM_EMAIL`, optional `NP6_FROM_LABEL`, `STREAM_UNSUBSCRIBE_SECRET`, optional `STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS` (default 300). `NP6_WEBHOOK_SECRET` is **removed** (no webhook).
- **New tables (refonte):** `np6_event_cursors` (one row per organization, persists the poller cursor). New column on `streams` (`np6_action_id` only — `np6_segment_id` and `stream_recipients.np6_target_id` dropped after the 2026-05-04 workflow validation).
- **No ModuleRegistry changes** — the poller is internal (no public route); the dispatch endpoints already live under `/api/stream/*`
- **Future extraction path:** if email volume or blast radius forces it, `np6_client.py` is the natural seam to lift into a standalone `apps/email-gateway/` service. The high-level `EmailProvider` ABC stays inside Stream and becomes an HTTP client to the gateway.

### Sender authentication

This is a **shared-tenant constraint** for the NP6 platform: every email ChapsMind sends rides on NP6's IP pool, which is shared with all NP6 clients. Sending from a domain that NP6 is not authorized to send on behalf of (no SPF / DKIM / DMARC alignment) is **spoofing as far as receiving MTAs are concerned**, which can:

1. Trigger spam-bin or block at major receivers (Gmail, Outlook, ProofPoint, etc.)
2. Damage the reputation of NP6 IPs across **all NP6 clients**, not just ours
3. Cause domain-level blacklisting that's expensive to recover from

**Phase 1 policy:**

- **Single fixed sender** — `NP6_FROM_EMAIL` env var, set by ops once after NP6 has provisioned SPF + DKIM + DMARC for the chosen domain (e.g. `noreply@chapsmind.fr`)
- **No per-stream override** — `from` is intentionally absent from `channel_config` and `NewsletterConfig` (Pydantic schema rejects it)
- **No per-organization override** — out of scope for Phase 1
- **Provider injects, never the caller** — the `NP6EmailProvider.send()` method reads `NP6_FROM_EMAIL` from settings and stamps it on every outbound message; the adapter has no way to influence it

**Phase 3+ path** (documented for future work): per-org custom From addresses become possible after building an `organization_email_senders` allowlist table populated only after manual SPF / DKIM / DMARC validation with the NP6 ops team. Until that workflow exists, single-sender is the only safe option.

---

## Security Model

Email rendering is a known-dangerous surface. MJML / `mjml-python` have a history of XSS and RCE-class issues via untrusted attribute parsing, and any templating engine that evaluates user-supplied input is an SSTI vulnerability waiting to happen. This section lays out the explicit defenses for Phase 1.

### Templates are code, payloads are data — never confuse the two

| Artifact                                                     | Trust                                              | Source                            | Lifecycle                                      |
| ------------------------------------------------------------ | -------------------------------------------------- | --------------------------------- | ---------------------------------------------- |
| Theme MJML (`newsletter_themes.mjml_header` / `mjml_footer`) | **Trusted** (server-controlled)                    | Alembic migration, admin UI later | Reviewed at PR / migration time                |
| Section MJML (`section_templates.mjml_snippet_*`)            | **Trusted** (server-controlled)                    | Alembic migration, admin UI later | Reviewed at PR / migration time                |
| Event payload (`stream_events.payload`)                      | **Untrusted** (caller-supplied)                    | Producer service via outbox relay | Stored as-is; rendered as data                 |
| Preview API request body                                     | **Untrusted** (caller-supplied)                    | Frontend, possibly attacker       | Validated, sandboxed                           |
| `subject_template` (`channel_config.subject_template`)       | **Trusted** (set by an authenticated stream owner) | Stream form                       | Validated against an allowlist of placeholders |

The substitution engine **only ever interprets templates from the trusted column**. Untrusted values reach the template render exclusively as Jinja context variables, which means a payload containing `{{ config.SECRET_KEY }}` is escaped and rendered as the literal string — never re-evaluated.

### Sandboxed substitution engine

- **Engine:** `jinja2.sandbox.SandboxedEnvironment` with `autoescape=True` (HTML-escapes by default)
- **Globals:** stripped — no access to `config`, `app`, `request`, `os`, `__builtins__`
- **Filters:** restricted to a safe whitelist (`upper`, `lower`, `length`, `default`, `truncate`, `e` for explicit escape, date / number formatters)
- **Variables exposed:** only `event`, `payload`, `folder`, `period`, `stream` — all other names raise `UndefinedError`
- **`SandboxedEnvironment`** blocks attribute pivots used in classic SSTI exploits: `__class__.__mro__`, `__subclasses__()`, `__globals__`, `__base__`, etc.
- **Render boundary:** templates are loaded from DB strings (not `FileSystemLoader`), so file system access via `{% include %}` / `{% extends %}` is impossible

### Preview API hardening

The preview endpoint (`POST /api/stream/newsletters/preview`) is the highest-risk surface — it accepts caller input and renders. Phase 1 contract:

- **Input:** `{ section_template_id: string, payload: object }` — strict Pydantic schema
- **`section_template_id`:** must reference an existing row in `section_templates`; 404 if unknown
- **`payload`:** dict of arbitrary depth, but **values are treated as data only**
- **Rejected at schema level:** any top-level field named `mjml`, `mjml_*`, `template`, `template_*`, `html`, `html_*`, `from`, `sender`, `reply_to`, `to` — defense-in-depth against future feature creep
- **No raw MJML accepted ever** — there is no API path that takes MJML as input. Adding one in the future requires an explicit security review and is out of scope for this ADR.

### Unsubscribe token signing

- **Algorithm:** HMAC-SHA256 with `STREAM_UNSUBSCRIBE_SECRET` (rotatable env var)
- **Payload:** `{recipient_email, organization_id, stream_id, expires_at}`
- **Encoding:** URL-safe base64 of `{payload_json, signature}`
- **Expiry:** 90 days (long enough for newsletters lingering in inboxes, short enough to revoke if secret rotates)
- **Verification:** constant-time comparison of HMAC; expired tokens get a "lien expiré, demandez un nouveau mail à votre administrateur" page rather than failing silently

### CI security tests

A non-skippable test suite at `apps/stream/tests/security/` runs on every PR:

- **`test_template_injection.py`:** 10+ canonical SSTI payloads (`{{ config }}`, `{{ self.__class__ }}`, `{{ ''.__class__.__mro__[1].__subclasses__() }}`, `{{ cycler.__init__.__globals__.os.popen('id').read() }}`, etc.) — every one must render as escaped literal text, never execute
- **`test_preview_schema.py`:** every blocked field name returns 400; only `section_template_id` + `payload` is accepted
- **`test_unsubscribe_token.py`:** tampered tokens rejected; expired tokens rejected; replay across orgs rejected
- **`test_sender_invariant.py`:** asserts `NP6EmailProvider.ensure_action()` always stamps `content.headers.from` from `NP6_FROM_EMAIL` regardless of caller input, and `provider.send()` (single-recipient ad-hoc) does the same

---

## Operational Constraints

### Email size limits

Email clients enforce hard and soft size limits that the dispatch path must respect:

| Limit            | Source                                          | Behavior                                                                                        |
| ---------------- | ----------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| **102 KB**       | Gmail (web + mobile)                            | Truncates with `[Message clipped] View entire message` link — destroys the editorial experience |
| **102 KB**       | Yahoo Mail                                      | Same clipping behavior                                                                          |
| **25 MB**        | Most receivers (hard cap including attachments) | Bounce                                                                                          |
| **NP6-specific** | TBD with NP6 ops during Phase 1a                | Document during implementation                                                                  |

### Batch cap and truncation strategy

- **Setting:** `STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH` (default `50`, configurable per env)
- **At dispatch time:** if more than the cap of events match the stream since the last batch, render the first N (sorted by `created_at DESC`) and append a CTA section _"Voir les {overflow_count}+ autres événements dans ChapsMind"_ linking to the folder
- **Rationale:** 50 events × ~1.5 KB rendered MJML ≈ 75 KB before theme — leaves headroom under the 102 KB Gmail clip threshold

### Pre-send weight check

After MJML compilation, before calling the provider:

| Rendered HTML size | Action                                                                                                                                                                        |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `< 95 KB`          | OK, dispatch                                                                                                                                                                  |
| `95 – 200 KB`      | Log `WARNING` with `stream_id` + `batch_id` + size; dispatch anyway (some clients tolerate it)                                                                                |
| `> 200 KB`         | Abort: `delivery.status = failed`, `error_message = "rendered HTML exceeds 200 KB safety limit"`. Operator investigates (likely a runaway template or excessive payload size) |

### Asset hosting policy

- **No base64-inlined images** — every image referenced by a theme or section template must be a public URL hosted on our CDN (TBD: pick a host in Phase 1b)
- Exception: spacer GIFs and tracking pixels handled by NP6 directly
- Rationale: base64 inlining typically adds 20–30% to email weight; a single 50 KB hero image inlined would alone blow past the Gmail clip threshold

### HTML minification

`mjml-python` is invoked with `keep_comments=False` and a post-render whitespace minifier; expected savings: ~10–15% of rendered HTML weight.

---

## Unsubscribe & Compliance

Unsubscribe is a **first-class concern in Phase 1**, not deferred. The legal basis (RGPD, CAN-SPAM) and the engineering implications (data model, dispatch flow, NP6 sync) are too central to the channel to address later.

### Default scope: per-stream

When a recipient clicks the unsubscribe link in a newsletter, **they are unsubscribed from that newsletter only** — not from all newsletters of the same organization. Rationale:

- Granularity matches user expectation (they unsubscribed from this specific digest, not from the company)
- RGPD compliance is satisfied: the consent withdrawal is explicit and proportionate to what they received
- Reduces "I wanted to unsubscribe from one mailing, now I receive nothing" support tickets

A future iteration may add a confirmation page offering "Se désabonner de toutes les communications de [Org]" as a secondary action.

### Unsubscribe paths into the suppression list

| Source                                                        | Trigger                                                  | `reason`               | Reversible?                 |
| ------------------------------------------------------------- | -------------------------------------------------------- | ---------------------- | --------------------------- |
| Footer link (`GET /email/unsubscribe?token=...`)              | User click                                               | `user_request`         | Yes (re-subscribe endpoint) |
| List-Unsubscribe header (`POST /email/unsubscribe` one-click) | Gmail/Outlook unsubscribe button                         | `user_request`         | Yes                         |
| NP6 hard bounce event (poller)                                | Receiver permanent failure (mailbox doesn't exist, etc.) | `bounce_hard`          | No (admin action only)      |
| NP6 complaint event (poller)                                  | Recipient flagged as spam                                | `complaint`            | No (admin action only)      |
| Internal soft-bounce counter ≥ 3 consecutive                  | Stream-side counter on `stream_deliveries`               | `bounce_soft_repeated` | Yes (admin action)          |
| Admin manual action                                           | Future admin UI                                          | `admin_action`         | Yes                         |

### Mandatory dispatch-time elements

Every newsletter dispatch must include:

1. **Footer link** — rendered by the theme MJML, e.g.:

   ```mjml
   <mj-text font-size="11px" color="#999999" align="center">
     Vous recevez cet email parce que vous êtes abonné·e à la newsletter <em>{{ stream.name }}</em
     >.<br />
     <a href="{{ unsubscribe_url }}">Se désabonner</a>
   </mj-text>
   ```

2. **`List-Unsubscribe` header** (RFC 2369):

   ```text
   List-Unsubscribe: <https://chapsmind.fr/api/stream/email/unsubscribe?token=...>, <mailto:unsubscribe+TOKEN@chapsmind.fr>
   ```

3. **`List-Unsubscribe-Post` header** (RFC 8058 one-click):

   ```text
   List-Unsubscribe-Post: List-Unsubscribe=One-Click
   ```

The `unsubscribe_url` is generated **per recipient per dispatch** — the token embeds `(email, organization_id, stream_id)` so each recipient gets a unique link. This is not optional; sending the same link to multiple recipients leaks the ability to unsubscribe others.

### NP6 coordination

NP6 also maintains its own unsubscribe / suppression list. **ChapsMind's `recipient_suppressions` table is the source of truth.** We sync into it from the **NP6 event poller** (bounces, complaints) — `verify_webhook()` is no longer the entry point (see §"Pull-Based Events" for the corrected mechanism). Filtering happens entirely on our side before the `POST /actions/{id}/executions` call.

Open question (flagged below): do we _push_ our suppression list to NP6 (mirror) so they don't try to deliver to known-bad addresses on our behalf? TBD with NP6 ops in Phase 1c.

### Hard-bounce vs soft-bounce

- **Hard bounce** (5xx SMTP, "550 mailbox does not exist"): NP6 event poller → `recipient_suppressions` row on next tick
- **Soft bounce** (4xx SMTP, "452 mailbox full", greylisting): NP6 retries internally; we count the soft bounces per `(email, stream_id)` in `stream_deliveries`. After 3 consecutive soft bounces, we suppress with `reason=bounce_soft_repeated` (Phase 1c initial threshold; tunable)

### Re-subscribe semantics

- `user_request` → reversible via the resubscribe endpoint, no admin involvement
- `bounce_hard`, `complaint` → only an admin can clear (the email genuinely doesn't work, or the user explicitly flagged us as spam — re-engaging without confirming risks more complaints)
- Phase 1 admin clearing is a SQL operation; admin UI for suppression management is Phase 5

### Audit trail

Every row in `recipient_suppressions` is immutable once written; un-suppression is a DELETE, but the action is logged in an `audit_log` (out of this ADR's scope, but flagged for Phase 1c so we can demonstrate RGPD compliance during audits).

---

## Data Model Additions

### Table: `newsletter_themes`

Global MJML wrappers. Seeded with 2–3 presets; organization-specific themes possible later.

| Column              | Type             | Description                                   |
| ------------------- | ---------------- | --------------------------------------------- |
| `id`                | `VARCHAR(50) PK` | e.g. `theme_minimal_v1`                       |
| `name`              | `VARCHAR(255)`   | Display name                                  |
| `description`       | `TEXT`           | Short description                             |
| `mjml_header`       | `TEXT`           | MJML for the top wrapper (logo, period title) |
| `mjml_footer`       | `TEXT`           | MJML for the footer (unsubscribe, branding)   |
| `preview_image_url` | `VARCHAR(500)`   | Thumbnail URL for the picker                  |
| `organization_id`   | `VARCHAR(36)`    | Nullable — null means global preset           |
| `created_at`        | `TIMESTAMP`      |                                               |
| `updated_at`        | `TIMESTAMP`      |                                               |

### Table: `section_templates`

Per event-type MJML snippets. Seeded from committed `.mjml` files in the migration; editable via admin UI later.

| Column              | Type             | Description                                     |
| ------------------- | ---------------- | ----------------------------------------------- |
| `id`                | `VARCHAR(50) PK` | e.g. `company_card_v1`                          |
| `event_type`        | `VARCHAR(100)`   | e.g. `screen.company.created`                   |
| `name`              | `VARCHAR(255)`   | Internal name                                   |
| `label_fr`          | `VARCHAR(255)`   | French UI label                                 |
| `label_en`          | `VARCHAR(255)`   | English UI label                                |
| `mjml_snippet_fr`   | `TEXT`           | MJML body (French locale)                       |
| `mjml_snippet_en`   | `TEXT`           | MJML body (English locale)                      |
| `required_fields`   | `JSONB`          | Array of payload fields the template references |
| `rendering_mode`    | `ENUM`           | `per_event` or `aggregated`                     |
| `preview_image_url` | `VARCHAR(500)`   | Thumbnail URL                                   |
| `is_default`        | `BOOLEAN`        | Pre-selected for its event type                 |
| `created_at`        | `TIMESTAMP`      |                                                 |

### Table: `stream_recipients`

Newsletter distribution list. Reserved in ADR-0020; specced here.

| Column         | Type           | Description                         |
| -------------- | -------------- | ----------------------------------- |
| `id`           | `SERIAL PK`    |                                     |
| `stream_id`    | `FK → streams` | Cascade delete                      |
| `email`        | `VARCHAR(255)` | Indexed `(stream_id, email)` unique |
| `display_name` | `VARCHAR(255)` | Optional                            |
| `created_at`   | `TIMESTAMP`    |                                     |

### Table: `recipient_suppressions`

Per-recipient suppression list. Filters dispatch before any NP6 call. **This is the source of truth for unsubscribe state in ChapsMind**, even if NP6 maintains its own list.

| Column            | Type                      | Description                                                                                         |
| ----------------- | ------------------------- | --------------------------------------------------------------------------------------------------- |
| `id`              | `SERIAL PK`               |                                                                                                     |
| `email`           | `VARCHAR(255)`            | Indexed                                                                                             |
| `organization_id` | `VARCHAR(36)`             | Org-scoped suppression (an unsubscribe in org A doesn't affect org B)                               |
| `stream_id`       | `FK → streams` (nullable) | NULL = global suppression for the org; non-null = stream-specific. Phase 1 default: stream-specific |
| `unsubscribed_at` | `TIMESTAMP`               | When the suppression was created                                                                    |
| `reason`          | `ENUM`                    | `user_request`, `bounce_hard`, `bounce_soft_repeated`, `complaint`, `admin_action`                  |
| `source`          | `ENUM`                    | `unsubscribe_link`, `list_unsubscribe_header`, `np6_event`, `manual`                                |
| `notes`           | `TEXT`                    | Free-form (e.g. NP6 bounce code, admin comment)                                                     |

**Unique index:** `(email, organization_id, stream_id)` — `stream_id NULL` is treated as a distinct value (PostgreSQL semantics, explicit partial index handles this).

**Lookup at dispatch time:** before each newsletter dispatch, the dispatch service runs:

```sql
SELECT email FROM stream_recipients
WHERE stream_id = $1
AND email NOT IN (
  SELECT email FROM recipient_suppressions
  WHERE organization_id = $2
  AND (stream_id = $1 OR stream_id IS NULL)
);
```

If the resulting list is empty, the delivery is marked `skipped` with `error_message = "all recipients suppressed"`.

### Changes to existing tables

**`streams.channel_type` enum** — add `newsletter` value (Alembic migration with explicit `ALTER TYPE ... ADD VALUE`).

**`streams.channel_config` JSONB shape for newsletter:**

```json
{
  "theme_id": "theme_minimal_v1",
  "subject_template": "Digest hebdo — {folder_name} ({period})",
  "sections": [
    {
      "event_type": "screen.company.created",
      "section_template_id": "company_card_v1",
      "order": 1
    },
    { "event_type": "screen.company.updated", "section_template_id": "company_diff_v1", "order": 2 }
  ],
  "recipients_source": "list"
}
```

The Pydantic schema for `NewsletterConfig` rejects any `from` / `reply_to` / `sender` / `mjml` / `template` / `html_*` field at the top level — defense in depth against a future caller trying to override server-controlled identity or inject raw template content.

**`stream_deliveries`** — no schema changes. `batch_id` (already in ADR-0020 Phase 2) groups per-recipient deliveries for a single newsletter batch. `response_metadata` stores NP6 `message_id` and bounce events.

---

## Channel Adapter

### Batch adapter contract

Live adapters (`TeamsAdapter`, `SlackAdapter`, `WebhookAdapter`) implement:

```python
class ChannelAdapter(ABC):
    @abstractmethod
    def format_payload(self, event: StreamEvent, stream: Stream) -> dict: ...
    @abstractmethod
    async def send(self, event: StreamEvent, stream: Stream) -> DispatchResult: ...
```

Newsletter renders **batches of events**, not single events. Two options:

**Option 1: Extend `ChannelAdapter` with an optional `format_batch()`**

- Pros: one ABC, less class hierarchy
- Cons: live adapters have to implement or `raise NotImplementedError` for batch, violates ISP

**Option 2 (Recommended): Introduce a sibling `BatchChannelAdapter` ABC**

```python
class BatchChannelAdapter(ABC):
    @abstractmethod
    def format_batch(self, events: list[StreamEvent], stream: Stream) -> dict: ...
    @abstractmethod
    async def send_batch(self, events: list[StreamEvent], stream: Stream) -> DispatchResult: ...
```

- `NewsletterAdapter` implements `BatchChannelAdapter`
- Live adapters stay on `ChannelAdapter` unchanged
- Factory returns either kind; dispatch service routes to `send()` for live or `send_batch()` for recurrence
- Cleaner Liskov, cleaner LSP compliance

**Decision:** Option 2 — sibling ABC.

### `NewsletterAdapter` responsibilities

1. `format_batch(events, stream)`:
   - Group events by `event_type`
   - For each entry in `channel_config.sections[]` (ordered):
     - Load section template from `section_templates` table
     - Filter events matching its `event_type`
     - Render MJML snippet (per-event or aggregated based on `rendering_mode`) via `mjml-python`
   - Load theme MJML header/footer
   - Assemble `header + sections + footer` into a single MJML document
   - Compile to inlined HTML
   - Render `subject_template` with `{folder_name}`, `{period}`, `{event_count}` (sandboxed substitution — see Security Model below)
   - Filter `stream.recipients` against the suppression list (`recipient_suppressions`) before returning
   - Return `{ subject, html, recipients: filtered_recipients, batch_id }` — `from` is injected by the provider, not carried in the dict

2. `send_batch(events, stream)`:
   - Call `format_batch()` to produce the rendered HTML + subject
   - Call `email_provider.send(EmailSendRequest(...))` — in-process Python call to the `integrations/email/` module
   - Update `stream_deliveries` rows (one per recipient) based on the provider result
   - Asynchronous delivery events (bounce / complaint / open / click) are pulled later by the NP6 event poller (`GET /actions/events`) and update the same rows

### Factory registration

`apps/stream/app/adapters/factory.py` gains:

```python
_ADAPTER_MAP[ChannelType.NEWSLETTER] = NewsletterAdapter
```

Dispatch service checks `isinstance(adapter, BatchChannelAdapter)` to route to `send_batch`.

---

## API Surface

### New public endpoints (via Global Service proxy)

**Newsletter themes:**

- `GET /api/stream/newsletters/themes` — list available themes (org presets + globals)

**Section templates:**

- `GET /api/stream/newsletters/sections?event_types=...` — list sections for given event types (used by the picker)

**Preview:**

- `POST /api/stream/newsletters/preview` — render a preview given a `channel_config` + sample event payloads or real recent events; returns inlined HTML for iframe rendering

**Recipients (nested under stream):**

- `GET /api/stream/streams/{id}/recipients` — list recipients
- `POST /api/stream/streams/{id}/recipients` — add recipient(s)
- `POST /api/stream/streams/{id}/recipients/import` — CSV import (multipart)
- `DELETE /api/stream/streams/{id}/recipients/{recipient_id}` — remove

**Test send:**

- `POST /api/stream/streams/{id}/test-send` — send to a single email address (0 credits, body: `{ "email": "..." }`)

### NP6 delivery events (refonte: pull-based, not webhook)

**NP6 has no push-webhook surface.** The earlier `POST /api/stream/email/webhook` endpoint and the `X-NP6-Signature` HMAC scheme are removed (see §"Pull-Based Events" and §"NP6 API Contract — Verified Against Spec").

The poller service `apps/stream/app/services/np6_event_poller.py` is invoked by APScheduler on the cadence defined by `STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS` (default 300s). Each tick calls `EmailProvider.pull_events(since=cursor)`, which wraps `GET /actions/events?start=...&end=...&sort=asc`. Events are normalised into `EmailDeliveryEvent`, then dispatched to:

- `suppression_service.handle_event()` for `bounce_hard`, `complaint`, `unsubscribe` → inserts a `recipient_suppressions` row with `source=np6_poller`
- `delivery_service.handle_event()` for `delivered`, `open`, `click`, `bounce_soft` → updates `stream_deliveries.response_metadata`

The cursor `(organization_id, last_pulled_at, last_event_id)` lives in the new `np6_event_cursors` table; `last_pulled_at` only advances on a successful pull, so transient NP6 outages cause re-pull, not data loss.

### Public unsubscribe endpoints

These are intentionally unauthenticated — RFC 8058 one-click unsubscribe must work without login. Auth is provided by the signed token.

- `GET /api/stream/email/unsubscribe?token=...` — landing page (HTML)
  - Verifies token signature (HMAC-SHA256) and expiry
  - Renders a confirmation page: _"Vous êtes désabonné·e de la newsletter [name]. Cliquer ici pour annuler."_
  - On valid token: inserts row in `recipient_suppressions` (idempotent — duplicate clicks are no-op)
  - On invalid / expired token: friendly error page with admin contact

- `POST /api/stream/email/unsubscribe` — RFC 8058 one-click endpoint
  - Body: `List-Unsubscribe=One-Click` (form-encoded, per spec)
  - Headers: `X-Token: ...` (or token in body)
  - Returns 200 on success, no HTML page (machine-driven by Gmail/Outlook unsubscribe button)
  - Same suppression-insert side effect as GET

- `POST /api/stream/email/resubscribe` — re-subscribe after accidental unsubscribe (linked from the confirmation page)
  - Same token verification
  - Removes the matching row from `recipient_suppressions` if `reason=user_request` (does NOT remove `bounce_hard` or `complaint` — those require admin action)

### Internal endpoints (service-to-service)

No new internal endpoints. The adapter ↔ email provider interaction is an in-process Python call inside Stream, not a network hop.

---

## Frontend

### Form page

Dedicated route: `/folders/:folderId/streams/new?type=newsletter` (and `/folders/:folderId/streams/:id/edit` for existing).

**Phase 1 layout** (no Chaps-e copilote, no split-view):

- Header: "Nouveau Stream — Newsletter"
- **Section "Canal"** — channel type chip = Newsletter, read-only
- **Section "Événements"** — reuse existing event subscription picker
- **Section "Contenu"** — for each subscribed event:
  - List of available section templates (thumbnail + label)
  - Checkbox to include, drag handle to reorder
  - "Aperçu" link opens the thumbnail large
- **Section "Thème"** — dropdown with thumbnail preview
- **Section "Sujet"** — text input with placeholder hints (`{folder_name}`, `{period}`)
- **Section "Destinataires"** — list view + "Ajouter un utilisateur" + "Import CSV"
- **Section "Planification"** — from ADR-0020 Phase 2 (récurrence, jour, heure, fuseau)
- **Section "Aperçu"** — live iframe rendering the compiled HTML (uses `/api/stream/newsletters/preview` with recent folder events as sample data)
- Footer: `[Annuler]` `[Envoyer un test]` `[Créer le stream]`

### Folder view integration

The existing Diffusions tab (ADR-0020) already lists streams. Newsletter streams display with their icon and channel label; no new tab required.

### Future: 3-tab edit page (Phase 2+)

Matches the Figma:

- **Prévisualisation** — full HTML preview + "Faire un test d'envoi"
- **Plan de communication** — temporalité + liste de diffusion (CSV import)
- **Historique** — delivery table with status (En cours, Erreur, Envoyé), "Voir les erreurs", "Consulter"

### Components

New Vue components (locations per existing frontend conventions):

- `NewsletterForm.vue` — the main page
- `SectionPicker.vue` — grouped by event type, checkbox + drag reorder
- `ThemePicker.vue` — dropdown with preview thumbnail
- `RecipientList.vue` — list + add + CSV import
- `NewsletterPreview.vue` — iframe + debounced preview refresh
- `SubjectTemplateInput.vue` — text input with placeholder chips

### i18n

New keys under `stream.channels.newsletter.*`:

- `stream.channels.newsletter.label` — "Newsletter"
- `stream.channels.newsletter.description` — "Digest éditorial envoyé par email"
- `stream.channels.newsletter.sections.title` — "Sections du contenu"
- `stream.channels.newsletter.theme.title` — "Thème visuel"
- `stream.channels.newsletter.recipients.title` — "Liste de diffusion"
- (etc., per ICU MessageFormat conventions)

All keys added to every locale file (ADR-0014 enforcement).

### TypeScript types

Extend the discriminated union in `apps/front/src/types/stream.ts`:

```ts
export interface NewsletterConfig {
  theme_id: string
  subject_template: string
  // Note: no `from` or `reply_to` here — server-controlled (NP6_FROM_EMAIL).
  sections: Array<{
    event_type: string
    section_template_id: string
    order: number
  }>
  recipients_source: 'list'
}

// Extended union
export type StreamChannel =
  | { channel_type: 'teams'; channel_config: TeamsConfig }
  | { channel_type: 'slack_webhook'; channel_config: SlackWebhookConfig }
  | { channel_type: 'webhook'; channel_config: WebhookConfig }
  | { channel_type: 'newsletter'; channel_config: NewsletterConfig }
```

`buildStreamCreate()` helper narrows on `channel_type` as today.

---

## Credit Model

Stream operations consume credits from the organization's global token balance (managed by Global Service, same API as Teams/Slack/Webhook).

| Operation                       | Cost                            | Rationale                                                   |
| ------------------------------- | ------------------------------- | ----------------------------------------------------------- |
| Newsletter stream creation      | 50 credits                      | Heavier than Teams/Slack (25) — template + recipients setup |
| Newsletter dispatch (per batch) | 15 credits + 1 credit/recipient | MJML render + NP6 fan-out cost                              |
| Test send                       | 0 credits                       | Free for validation                                         |

Credits are consumed **after** successful delivery (same pattern as existing adapters in `apps/stream/app/services/dispatch_service.py`). Insufficient credits → `stream_deliveries.status = skipped`, no email sent.

Settings: `STREAM_COST_NEWSLETTER_BASE`, `STREAM_COST_NEWSLETTER_PER_RECIPIENT` env vars.

---

## Phased Delivery

Phase 1 is split into four sequential-but-independently-mergeable sub-phases. Each has its own MR / Jira ticket and is end-to-end testable on its own.

| Phase                                                                                     | Scope                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Acceptance criterion                                                                                                                                                                                                                                                                                                                                  | Depends on                                     |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| **1a — NP6 plumbing** _(rewritten 2026-04-30 + 2026-05-04 segment-drop)_                  | `apps/stream/app/integrations/email/`: `np6_client.py` (one method per NP6 endpoint, header `X-Key`, MIME `application/vnd.np6.cm.email`), `np6.py` orchestrator (`ensure_action`, `validate_action` 2-phase, `upsert_target`, `dispatch`, `pull_events`, `send`), extended `EmailProvider` ABC, schemas. New env vars (`NP6_*`, `NP6_BAT_TEST_SEGMENT_ID`, `STREAM_UNSUBSCRIBE_SECRET`; `STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS` lands with the poller in TAR-1629, not in the settings story TAR-1626). Alembic migration adds `streams.np6_action_id` and `np6_event_cursors` table. CI security tests skeleton. **No webhook endpoint** (refonte). **No segment, no `np6_target_id`** (post-2026-05-04). | `task stream:send-test-email -- --to alice@example.com` provisions a NP6 contact, validates the action 2-phase, dispatches via `/executions`, a real email arrives in alice's inbox; the message_id from `ExecutionResult` is logged. The poller pulls a synthetic bounce event via `GET /actions/events` and inserts a `recipient_suppressions` row. | None                                           |
| **1b — Template engine & catalog**                                                        | `newsletter_themes` + `section_templates` tables + Alembic seeds (3 themes, 1 default section per Screen event). Sandboxed Jinja substitution (`apps/stream/app/integrations/email/sandbox.py`). MJML renderer service. Preview endpoint `POST /api/stream/newsletters/preview` (backend-only, returns inlined HTML). All security tests passing. Pre-send weight check.                                                                                                                                                                                                                                                                                                                                     | A curl call to `/preview` with a `section_template_id` and a sample payload returns valid HTML; SSTI test suite passes.                                                                                                                                                                                                                               | 1a (uses `EmailSendRequest` shape)             |
| **1c — Adapter, dispatch & unsubscribe** _(refonte 2026-05-04 — direct-list, no segment)_ | `NewsletterAdapter` (`ensure_action` → `validate_action` 2-phase → `upsert_target` per recipient → `dispatch` with explicit recipient list) + `BatchChannelAdapter` ABC + dispatch service routing. `stream_recipients` + `recipient_suppressions` tables. Suppression filter at dispatch. Unsubscribe endpoints (`GET/POST /api/stream/email/unsubscribe`, `POST /resubscribe`). Token signing (`STREAM_UNSUBSCRIBE_SECRET`). `List-Unsubscribe` headers in dispatch. **Poller** (`np6_event_poller.py`) → suppression sync (hard bounce + complaint) and delivery status updates. Manual dispatch endpoint. Test send (0 credits, single-recipient `provider.send()` path).                                | A stream is created via SQL/seed, manual dispatch endpoint is hit, an actual email arrives in a tester's inbox with a working unsubscribe link; the poller picks up the synthetic bounce / open events from NP6 and updates `recipient_suppressions` / `stream_deliveries`.                                                                           | 1a, 1b                                         |
| **1d — Frontend authoring**                                                               | `NewsletterForm.vue` page + `SectionPicker.vue` + `ThemePicker.vue` + `RecipientList.vue` (with CSV import) + `NewsletterPreview.vue` iframe + `SubjectTemplateInput.vue`. i18n keys. TypeScript discriminated union extension. Pinia Colada queries / mutations.                                                                                                                                                                                                                                                                                                                                                                                                                                            | A non-technical user can create a newsletter stream end-to-end from the UI, hit "Envoyer un test", and receive the email.                                                                                                                                                                                                                             | 1c (uses APIs from 1c)                         |
| **2 — Scheduled delivery**                                                                | Wire `NewsletterAdapter` into APScheduler from ADR-0020 Phase 2 + recurrence UI + batch event collection logic + soft-bounce-counter suppression                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Newsletter dispatched on cron schedule with the configured cadence                                                                                                                                                                                                                                                                                    | ADR-0020 Phase 2 (APScheduler + recurrence)    |
| **3 — Section catalog expansion**                                                         | More section variants per event (compact / detailed / quote), Target events sections when Target goes live, admin UI for section template editing                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | New section variants visible in the picker without a backend deploy                                                                                                                                                                                                                                                                                   | ADR-0020 Phase 3 (Target producer integration) |
| **4 — AI copilote**                                                                       | Chaps-e LangGraph agent that composes newsletters conversationally (Figma goal) + AI-generated digest summaries + split-view editor                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Conversational newsletter creation matches the Figma flow                                                                                                                                                                                                                                                                                             | ADR-0020 Phase 4 (agent & newsletter)          |
| **5 — Visual editor & analytics**                                                         | Evaluate Unlayer / GrapeJS for free-form editing if customer demand justifies; admin suppression management UI; per-org email analytics dashboard (open rate, click rate from `stream_deliveries.response_metadata`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | TBD on customer validation                                                                                                                                                                                                                                                                                                                            | Customer validation                            |

**Sub-phase strategy:** 1a and 1b can be developed in parallel by two engineers (1b depends only on the `EmailSendRequest` shape, which is defined upfront in 1a's interface stubs). 1c blocks on both. 1d is fully serial after 1c.

**MVP shipping path:** Phase 1c is the minimum useful release — operators can create newsletters via SQL and trigger dispatches. Phase 1d makes it self-serve for users.

---

## Gateway Integration

Following ADR-0015 (ModuleRegistry auto-discovery) and ADR-0020's gateway integration pattern:

- **Stream** routes unchanged — `/api/stream/*` → Stream service (already registered)
- **No new module registration** — email delivery lives inside Stream; the public unsubscribe endpoints (`GET/POST /api/stream/email/unsubscribe`, `POST /resubscribe`) ride on the existing Stream route prefix. Delivery feedback is pull-based (refonte 2026-04-30) — no inbound NP6 endpoint exists.
- **Correlation ID**: Stream → NP6 outbound requests propagate `X-Correlation-ID` so NP6-side logs can be joined with ours; on the inbound side, the NP6 event poller stamps a fresh correlation id on each tick so events ingested into `stream_deliveries` are traceable

No modifications to `proxy/routes.py`, `core/config.py`, or ModuleRegistry config required.

---

## Consequences

### Positive

- **Editorial push channel**: users get curated digests delivered to recipient inboxes, not just reactive event pings
- **Clean NP6 boundary without new infra**: the `EmailProvider` seam keeps business logic provider-agnostic while avoiding the cost of standing up, deploying, and monitoring a separate email-gateway service
- **Straightforward future extraction**: the module's file boundaries, DTOs, and ABC transplant wholesale into a standalone service if volume or blast radius ever justifies it
- **Reusable section catalog**: same sections feed newsletter Phase 1 and the AI copilote in Phase 4
- **Leverages ADR-0020 infrastructure**: ingest pipeline, stream lifecycle, credit system, correlation IDs — all reused
- **Python-native rendering**: no Node.js added to the backend stack
- **RGPD/CAN-SPAM compliance built-in from day one**: one-click unsubscribe, signed tokens, suppression list as source of truth, RFC 8058 headers — not bolted on later under audit pressure
- **Defense-in-depth security model**: sandboxed substitution, preview API hardening, server-controlled sender, CI-blocking SSTI tests — protects against known classes of attack on email-templating systems
- **Sub-phased Phase 1**: 1a/1b can run in parallel; 1c is the MVP shipping point; reviewers see narrow MRs, not a giant blob
- **Iterative approach**: section picker validates the model before investing in the Figma copilote experience

### Negative

- **Shared failure domain**: because NP6 integration lives in-process, a runaway NP6 call (slow response, high volume, library bug) can contend for Stream's CPU / event loop / connection pool — mitigated by async HTTP with timeouts and by the provider's retry/backoff being isolated, but not eliminated
- **Shared deployment cadence**: email-provider changes ship on the Stream release train; if a second consumer (password reset, invitations) later needs email and evolves at a different pace, extraction to a standalone service becomes justified
- **NP6 coupling is a runtime dependency**: NP6 availability and latency directly affect every newsletter dispatch (same as it would be with a wrapper service — just now co-located)
- **Template editing is code-adjacent**: in Phase 1, updating section MJML requires a migration; non-engineers can't self-serve yet
- **Two adapter contracts** (`ChannelAdapter` vs `BatchChannelAdapter`) add minor cognitive load in dispatch logic
- **No per-org sender flexibility in Phase 1**: orgs that want to send from their own branded domain must wait for Phase 3+ (DNS-validation workflow with NP6 ops)
- **Suppression list is a new source of truth**: must stay in sync with NP6's view of bounces/complaints — divergence (e.g., poller cursor lag, NP6 event retention dropping older entries before we ingest them) is a silent compliance risk; mitigated by reconciliation tooling planned in Phase 5

### Neutral

- Recurrence-only makes sense for newsletter — live newsletter would be spam. This is a constraint, not a limitation.
- Per-recipient credit cost will need tuning based on real NP6 usage patterns
- Section template localization (per-locale MJML) doubles storage but keeps designer workflow simple

---

## Open Questions

The following decisions are flagged for team discussion before or during implementation:

1. **Section template storage**: DB rows (editable via admin UI later) vs versioned filesystem files (git-tracked, migration-seeded). **Recommendation:** DB rows, seeded from committed `.mjml` files during Alembic migration — best of both worlds.

2. **Localization of section templates**: per-locale MJML columns vs single MJML with ICU placeholders. **Recommendation:** per-locale columns — simpler for designers, matches our ADR-0014 i18n model, no runtime locale-resolution edge cases.

3. **Batch adapter contract**: extend `ChannelAdapter` with optional `format_batch()` vs sibling `BatchChannelAdapter` ABC. **Recommendation:** sibling ABC — cleaner ISP/LSP, no `NotImplementedError` traps.

4. **Recipients source**: manual list only in Phase 1, or also "all folder members" / "all org users"? **Recommendation:** manual + CSV in Phase 1; member-based sources in Phase 2, gated by Keycloak group membership queries (ADR-0005 / ADR-0007 territory).

5. **Preview rendering**: backend SSR via `mjml-python` endpoint vs push MJML to frontend to render. **Recommendation:** backend SSR — single source of truth, no MJML compiler in the browser, consistent with production render.

6. **Extraction threshold for `integrations/email/`**: at what signal do we lift it into a standalone `apps/email-gateway/` service? **Recommendation:** flag for extraction when any of the following hits — (a) a second consumer module starts calling the provider, (b) sustained >1000 emails/hour make shared-process contention measurable, (c) email-provider outages are observed to degrade Stream's event ingestion latency. Until then the in-module design stands.

7. **Bounce / complaint feedback loop**: exact NP6 event payload (from `GET /actions/events`) → Stream delivery status mapping. **To be specced during Phase 1a/1c implementation**; at minimum: `delivered`, `bounced_hard`, `bounced_soft`, `complaint`, `opened`, `clicked`.

8. **NP6 suppression sync direction**: do we _push_ our `recipient_suppressions` to NP6 (mirror) so they don't attempt delivery to known-bad addresses on our behalf, or do we rely on filtering entirely on our side? **Recommendation:** filter on our side only in Phase 1c (simpler, single source of truth); revisit if NP6 ops asks us to sync to reduce their attempted-delivery volume.

9. **Soft-bounce suppression threshold**: 3 consecutive soft bounces is the proposed default before suppressing with `bounce_soft_repeated`. Configurable via `STREAM_SOFT_BOUNCE_SUPPRESSION_THRESHOLD` env var. **To validate** with NP6 ops — they may have a recommended value based on their own data.

### Open questions raised by the 2026-04-30 NP6 spec audit

1. **Per-recipient unsubscribe URL substitution**: NP6's templating engine supports server-side substitution placeholders inside `content.html`, but the OpenAPI bundle doesn't expose the placeholder grammar. The plan assumes a syntax like `[[CONTACT_PROPERTY:unsubscribe_token]]` that resolves per recipient at execution time. **To confirm with NP6**: the exact substitution syntax, whether arbitrary `target` properties can hold our signed token, and whether the substitution runs _after_ delivery so a per-recipient signed URL is computable. If unsupported, fallback is N executions (one per recipient) with `content.html` overridden per-call — costlier but functional.

> Resolved post-audit (2026-04-30):
>
> - **`unicity` semantics with non-pre-provisioned email** — moot. Our flow always pre-provisions a `target` via `upsert_contact` when a recipient is added to a stream's distribution list, so by the time we execute, every email is already a known contact. We never need `unicity` to auto-provision.
> - **NP6 sandbox / staging URL** — confirmed with NP6 ops: there is no sandbox. Phase 1a smoke tests run against the production NP6 base URL with a dedicated test sender domain and recipients restricted to internal mailboxes.

---

## Stream Lifecycle for Newsletter (deferred)

> Added 2026-04-30 as design context. **Not implemented in Phase 1a/1b/1c**; flagged here so the data model and API surface stay forward-compatible.

The current `streams.status` enum (`draft`, `active`, `paused`, `archived`) and its transitions (`active ↔ paused`, both → `archived`) work for Teams / Slack / Webhook channels where activation is immediate after creation. Newsletter is more configuration-heavy: a user creates the stream, then iterates on theme + sections + recipients + schedule before being ready to send.

**Future intended flow** (target Phase 1d+ or a follow-up ticket):

1. User submits the newsletter form → stream created with `status = paused` _(today: defaults to `active`)_
2. User configures sections via the section picker
3. User uploads / edits the recipient list
4. User picks the dispatch schedule (cron preset)
5. User clicks "Activate" → server validates that the stream has at least one section, at least one recipient, and a valid schedule before allowing `paused → active`. The validation lives in `StreamService.update_status` and emits a structured 422 with the missing prerequisites listed.

**Implication for the refonte:** `ensure_action`, `validate_action`, `upsert_target` are all idempotent and callable from either `paused` or `active` state, because the user may want a "preview send" while the stream is still in `paused`. The test-send endpoint already works this way.

This change is intentionally out of scope for the NP6 refonte branch stack (TAR-1626 → 1635) to keep the rebase blast radius bounded. It is filed as a follow-up ticket in the next epic refresh.

1. **Token expiry duration for unsubscribe links**: 90 days proposed. Long enough for newsletters that linger in inboxes, short enough that secret rotation is meaningful. **Open** to feedback from compliance / legal.

2. **Audit log infrastructure**: `recipient_suppressions` mutations should be logged for RGPD audit. Stream doesn't have a generic audit log today. **Recommendation:** add a minimal `audit_log` table in Phase 1c scoped to suppression actions; broader audit logging is a separate ADR.

3. **Unsubscribe page i18n**: the public landing page is rendered without user authentication, so we don't know the recipient's locale. **Recommendation:** read `Accept-Language` header with French fallback; or embed locale in the signed token (slight token-size increase). TBD in Phase 1c.

---

## Files to Modify

| File                                                       | Change                                                                                                                                                                                                                                                         |
| ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `apps/stream/app/adapters/base.py`                         | Add `BatchChannelAdapter` sibling ABC                                                                                                                                                                                                                          |
| `apps/stream/app/adapters/newsletter.py`                   | **New** — `NewsletterAdapter` implementing `BatchChannelAdapter`                                                                                                                                                                                               |
| `apps/stream/app/adapters/factory.py`                      | Register `ChannelType.NEWSLETTER` → `NewsletterAdapter`                                                                                                                                                                                                        |
| `apps/stream/app/models/stream.py`                         | Add `newsletter` to `ChannelType` enum                                                                                                                                                                                                                         |
| `apps/stream/app/models/newsletter_theme.py`               | **New** — `NewsletterTheme` SQLAlchemy model                                                                                                                                                                                                                   |
| `apps/stream/app/models/section_template.py`               | **New** — `SectionTemplate` SQLAlchemy model                                                                                                                                                                                                                   |
| `apps/stream/app/models/recipient.py`                      | **New** — `StreamRecipient` SQLAlchemy model                                                                                                                                                                                                                   |
| `apps/stream/app/models/suppression.py`                    | **New** — `RecipientSuppression` SQLAlchemy model                                                                                                                                                                                                              |
| `apps/stream/app/schemas/stream.py`                        | Add `NewsletterConfig` Pydantic model; extend channel config union                                                                                                                                                                                             |
| `apps/stream/app/schemas/newsletter.py`                    | **New** — theme/section/recipient schemas                                                                                                                                                                                                                      |
| `apps/stream/app/services/dispatch_service.py`             | Route to `send_batch()` when adapter is `BatchChannelAdapter`                                                                                                                                                                                                  |
| `apps/stream/app/services/newsletter_service.py`           | **New** — MJML composition + render (uses sandboxed Jinja)                                                                                                                                                                                                     |
| `apps/stream/app/services/suppression_service.py`          | **New** — suppression list lookups, insertions (from unsubscribe link, NP6 event poller, soft-bounce counter), token signing/verification                                                                                                                      |
| `apps/stream/app/api/endpoints/newsletters.py`             | **New** — themes, sections, preview endpoints (preview accepts `section_template_id` only, never raw MJML)                                                                                                                                                     |
| `apps/stream/app/api/endpoints/recipients.py`              | **New** — recipients CRUD + CSV import                                                                                                                                                                                                                         |
| `apps/stream/app/api/endpoints/unsubscribe.py`             | **New** — public `GET/POST /api/stream/email/unsubscribe`, `POST /resubscribe` (no JWT auth, signed-token auth)                                                                                                                                                |
| `apps/stream/alembic/versions/`                            | **New migration** — `newsletter_themes`, `section_templates`, `stream_recipients`, `recipient_suppressions` tables + `newsletter` enum value + seed data (3 themes + 1 default section per Screen event)                                                       |
| `apps/stream/pyproject.toml`                               | Add `mjml-python` dependency                                                                                                                                                                                                                                   |
| `apps/stream/app/integrations/email/`                      | **New module** — `provider.py` (ABC), `np6.py` (NP6 impl), `np6_client.py` (one method per NP6 endpoint), `schemas.py` (DTOs), `sandbox.py` (Jinja2 SandboxedEnvironment setup), `factory.py` (DI)                                                             |
| `apps/stream/app/services/np6_event_poller.py`             | **New** — APScheduler-driven poller that calls `GET /actions/events`, normalizes payloads, and writes hard-bounce/complaint suppressions + `stream_deliveries` updates (refonte: replaces the speculative webhook endpoint)                                    |
| `apps/stream/tests/security/`                              | **New** — SSTI test suite, preview schema test, unsubscribe token test, sender invariant test (CI-blocking)                                                                                                                                                    |
| `apps/front/src/types/stream.ts`                           | Extend discriminated union with `NewsletterConfig`                                                                                                                                                                                                             |
| `apps/front/src/api/newsletters.ts`                        | **New** — API functions for themes, sections, preview, recipients                                                                                                                                                                                              |
| `apps/front/src/queries/newsletters.ts`                    | **New** — Pinia Colada queries                                                                                                                                                                                                                                 |
| `apps/front/src/components/`                               | **New components** — `NewsletterForm.vue`, `SectionPicker.vue`, `ThemePicker.vue`, `RecipientList.vue`, `NewsletterPreview.vue`, `SubjectTemplateInput.vue`                                                                                                    |
| `apps/front/src/i18n/locales/fr-FR.json` / `en-US.json`    | Add `stream.channels.newsletter.*` keys                                                                                                                                                                                                                        |
| `infra/compose.yaml`                                       | Add `NP6_BASE_URL`, `NP6_API_KEY`, `NP6_FROM_EMAIL`, optional `NP6_FROM_LABEL`, optional `STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS` env vars to the existing `stream` service — no new Docker service. (`NP6_WEBHOOK_SECRET` removed in the 2026-04-30 refonte.) |
| `infra/compose.local.yaml`                                 | No changes (Stream service already defined; env vars picked up from `.env`)                                                                                                                                                                                    |
| `docs/architecture/adr/README.md`                          | Index this ADR (0018)                                                                                                                                                                                                                                          |
| `docs/architecture/adr/0020-stream-module-architecture.md` | Add 0018 to "Related" section                                                                                                                                                                                                                                  |

---

## References

**Internal ADRs:**

- [ADR-0009 — Global Service Architecture](./0009-global-service-architecture.md)
- [ADR-0012 — LangGraph Agent System](./0012-langgraph-agent-system.md)
- [ADR-0014 — Frontend Translation Management Strategy](./0014-frontend-translation-management-strategy.md)
- [ADR-0015 — Multi-Module API Gateway](./0015-multi-module-gateway.md)
- [ADR-0020 — Stream Module Architecture](./0020-stream-module-architecture.md)

**MJML / email rendering:**

- [MJML — The Responsive Email Framework](https://mjml.io/)
- [mjml-python on PyPI](https://pypi.org/project/mjml-python/)
- [MRML — Rust port of MJML](https://github.com/jdrouet/mrml)
- [MJML Components Reference](https://documentation.mjml.io/)

**Email client compatibility:**

- [Email on Acid — Best Email Frameworks](https://www.emailonacid.com/blog/article/email-development/best-email-frameworks/)
- [Awesome Emails — curated resources](https://github.com/jonathandion/awesome-emails)
- [Gmail message clipping (102 KB threshold)](https://support.google.com/mail/answer/6558)

**Security & compliance:**

- [Jinja2 SandboxedEnvironment documentation](https://jinja.palletsprojects.com/en/stable/sandbox/)
- [Server-Side Template Injection (PortSwigger Web Security Academy)](https://portswigger.net/web-security/server-side-template-injection)
- [RFC 2369 — `List-Unsubscribe` header](https://datatracker.ietf.org/doc/html/rfc2369)
- [RFC 8058 — One-Click Unsubscribe](https://datatracker.ietf.org/doc/html/rfc8058)
- [RGPD — droit d'opposition (CNIL)](https://www.cnil.fr/fr/le-droit-dopposition)
- [SPF, DKIM, DMARC overview (Google Postmaster Tools)](https://support.google.com/mail/answer/81126)

**Alternatives evaluated:**

- [React Email](https://react.email/) (rejected — Node runtime)
- [Maizzle](https://maizzle.com/) (rejected — build-time only)
- [Unlayer Vue Email Editor](https://github.com/unlayer/vue-email-editor) (deferred to Phase 5)
- [GrapeJS](https://grapesjs.com/) (deferred to Phase 5)

**NP6 (email delivery provider):**

- [NP6 API Documentation (HTML)](https://documentation.np6.com/api)
- [NP6 OpenAPI Bundle (YAML — source of truth)](https://documentation.np6.com/_bundle/api.yaml)
- [NP6 Email Tracking](https://documentation.np6.com/api/section/email-tracking)
- [NP6 by ChapsVision overview](https://www.chapsvision.com/marketing-automation/)
