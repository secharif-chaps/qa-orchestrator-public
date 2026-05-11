# NP6 CM — ChapsMind integration guide

> Third-party platform from the ChapsVision group. Used by the **Stream** module for the newsletter channel (marketing email delivery).
> Reference ADR: [ADR-0021 — Stream Newsletter Channel](../architecture/adr/0021-stream-newsletter-channel.md)
> Date of last live audit: 2026-05-04 (E2E workflow validated: action → target → 2-phase validation → execution → 200)

## Table of contents

- [What is NP6?](#what-is-np6)
- [Where to find NP6 documentation](#where-to-find-np6-documentation)
- [Authentication](#authentication)
- [Action lifecycle — E2E-validated workflow](#action-lifecycle-mail--e2e-validated-workflow-2026-05-04)
  - [State machine](#confirmed-state-machine)
  - [Recommended programmatic workflow](#recommended-programmatic-workflow)
  - [Endpoints used](#endpoints-used)
  - [`/executions` body shape](#executions-body-shape-mailmessage)
- [HTTP codes](#http-codes-what-we-actually-observed)
- [ChapsMind-side configuration](#chapsmind-side-configuration)
- [Known gotchas (10)](#known-gotchas)
- [How to request something from NP6](#how-to-request-something-from-np6)

## What is NP6?

**NP6 CM** (Customer Marketing) is ChapsVision's marketing-automation platform. It sends marketing and transactional emails and SMS, manages contacts, segments, campaigns, and tracking (opens/clicks/bounces). ChapsMind uses its REST API for the **newsletter channel** of the Stream module — we never touch the web UI, everything goes through the HTTP endpoints.

Proprietary vocabulary:

| NP6 term        | ChapsMind equivalent                                       | Definition                                                                                                                                                                                                                                                                              |
| --------------- | ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Action**      | email template + envelope (1 per stream)                   | object holding the subject + HTML + send parameters. An action has a state (10/20/38/50/100) that governs what it can be used for.                                                                                                                                                      |
| **Target**      | contact / `stream_recipient`                               | a contact record (email + metadata). Addressed by `id`, `unicity` (= value of the unicity field, the email in our case), `hash`, or `footprint`.                                                                                                                                        |
| **Segment**     | (optional for our case)                                    | static or dynamic group of targets. **Confirmed by NP6 (2026-05-04): not useful for `mailMessage`** — we pass the recipient list explicitly to `/executions`. Segments are mostly used for test mode (`testSegments`) and as optional NP6-side storage when we don't have our own list. |
| **Execution**   | dispatch                                                   | actual delivery of an action to 1 or N targets. The `/executions` endpoint accepts an array of `{recipient: {type, value}}`, **not** a segment reference.                                                                                                                               |
| **Validation**  | mandatory step before sending                              | flips an action into a "sendable" state. Done in **2 phases**: `{fortest:true, testSegments:[id]}` (state 20→38) then `{fortest:false}` (state 38→50). Without phase 2, the action stays in state 38 and `/execution` returns 409 with no body.                                         |
| **SendMessage** | (legacy API / marketing name)                              | commercial name of the transactional send endpoint. Technically = `POST /actions/{id}/execution`.                                                                                                                                                                                       |
| **MP-Remote**   | (advanced)                                                 | "Marketing Pressure Remote" — marketing-pressure arbitration. Out of scope for newsletter.                                                                                                                                                                                              |
| **BAT**         | "Bon À Tirer" (French marketing term for "sign-off proof") | terminology for "test/proof before prod send". Segment `1319 BAT TEST` on tenant CHAP/02C holds the contacts used for the test phase of validation.                                                                                                                                     |

## Where to find NP6 documentation

Three sources, in order of reliability:

1. **OpenAPI spec (source of truth)** — `https://documentation.np6.com/_bundle/api.yaml`
   - Raw YAML, greppable. Contains schemas, parameters, expected HTTP codes.
   - **Always consult first** before coding an endpoint. Do not rely on the rendered HTML doc, which omits the `securitySchemes` section and discriminated schemas.
   - See [feedback memory: verify external API specs](#) — the first iteration of the NP6 integration was coded against the HTML doc, which forced a complete refactor after the audit.

2. **Rendered API doc (HTML)** — `https://documentation.np6.com/api`
   - More readable but incomplete on security and schemas.
   - Good for discovering endpoints, bad for validating body details.

3. **Functional documentation (help centre)** — `https://np6.supporthero.io`
   - French-language articles oriented at web-UI users. No HTTP signatures, but plenty of context on concepts (states, validation, campaigns, etc.).
   - Key sections for us:
     - [API-CM F.A.Q.](https://np6.supporthero.io/container/show/vous-avez-des-questions-sur-les-api-cm) — auth, permissions, common errors
     - [NP6 glossary](https://np6.supporthero.io/container/show/glossaire-np6) — vocabulary
     - [Activation > Emails](https://np6.supporthero.io/container/show/emails-sms-formulaires) — lifecycle on the UI side
     - [Administration > Permissions](https://np6.supporthero.io/container/show/permissions) — permission model
   - Notable: only 6 error codes are documented (415, 403, 401, 404, +). **409 is not in the list** — expect under-documentation when you hit it.

## Authentication

- **Header**: `X-Key: <key>` (NP6 custom — **not** `Authorization: Bearer`)
- **How to obtain a key**: ask your NP6 project manager (no self-service).
- **Important — permission rule** (translated from French original): _"Authentication via your key works exactly like your authentication on the UI. You will therefore have the same rights and the same restrictions as when browsing."_ (source: [NP6 F.A.Q.](https://np6.supporthero.io/article/show/34709-ai-je-les-memes-droits-avec-les-api-que-sur-linterface)). Concretely: the key inherits the rights of the user account it was generated under. If that user cannot **validate** or **send** an action on the UI, the API returns **409 with no body** on the corresponding endpoints.

On the ChapsMind side, the key lives in `NP6_API_KEY` (env var, never committed in code or in Git — see `apps/stream/app/core/config.py`).

## Action lifecycle (mail) — E2E-validated workflow (2026-05-04)

### Confirmed state machine

```
              POST /actions                     POST /validation                POST /validation              POST /execution(s)
              (with content)                    {fortest:true,                  {fortest:false}               (sends the mail)
                  │                              testSegments:[id]}                  │                            │
                  ▼                                  ▼                                ▼                            ▼
   ╔═════════╗   creates    ╔════════════╗  test phase  ╔══════════════╗   prod phase  ╔═══════════╗   send       ╔══════════╗
   ║ state 10║──────────────║ state 20   ║──────────────║ state 38     ║───────────────║ state 50  ║──────────────║ state 50 ║
   ╚═════════╝              ╚════════════╝              ╚══════════════╝               ╚═══════════╝              ╚══════════╝
   minimal                  draft (content)             test-validated                  prod-validated             execution-ready
                                                        (send allowed                    (action ready to
                                                        on testSegments                  serve in production)
                                                        only)
                                                                                                                  │
                                                                                              ╔═══════════════╗   │
                                                                                              ║ state 100      ║◄─┘ (after archiving)
                                                                                              ╚═══════════════╝
                                                                                                  archived
```

| State   | Meaning                                                  | What you can do                                                                    |
| ------- | -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| **10**  | initial draft (empty or minimal content)                 | `PUT` to fill content, `DELETE`                                                    |
| **20**  | draft with content                                       | `PUT`, `POST /validation {fortest:true, testSegments:[id]}`                        |
| **38**  | **test-validated** — phase 1 OK, prod validation pending | `POST /validation {fortest:false}` to move to prod, `DELETE /validation` to revert |
| **50**  | **prod-validated, ready to send** — can now execute      | `POST /execution(s)`, `DELETE /validation` (re-test)                               |
| **100** | archived                                                 | read-only                                                                          |

> **Key discovery (2026-05-04)**: 2-phase validation is **mandatory**. Calling only `{fortest:true, testSegments:[id]}` leaves the action in state 38 — `/execution` returns **409 with no body** in that state. A second `POST /validation {fortest:false}` is required to move state 38 → 50, and **only then** does sending work. Confirmed by NP6 (translated from French): "the test phase ran into an issue, so you're still pending validation".

> **Observed on tenant CHAP/02C**: 144 out of 214 actions are in state 50 — most have been properly prod-validated and have already been used.

### Recommended programmatic workflow

```python
# 1. Create the action (template) — once, reusable
action_id = post(/actions, mailMessage_payload)  # → state 20

# 2. Provision recipients as targets — idempotent (409 if it already exists)
for email in recipients:
    try:
        post(/targets, {"Email": email})  # field NAME, not its id
    except HTTP409:  # "target already exists" — OK
        pass

# 3. 2-phase validation — mandatory before the first send
post(/actions/{id}/validation, {"fortest": True, "testSegments": [BAT_TEST_ID]})  # → state 38
post(/actions/{id}/validation, {"fortest": False})                                 # → state 50

# 4. Send — body = explicit recipient list (no segment reference)
results = post(/actions/{id}/executions,
               body=[{"recipient": {"type": "unicity", "value": [email]}} for email in recipients],
               content_type="application/vnd.np6.cm.email")
# → 200 + array of ExecutionResult per recipient
```

### Endpoints used

| Endpoint                   | Verb      | MIME                           | Purpose                                                                            |
| -------------------------- | --------- | ------------------------------ | ---------------------------------------------------------------------------------- |
| `/actions`                 | POST      | `application/json`             | Create an action (template + envelope)                                             |
| `/actions/{id}`            | GET / PUT | `application/json`             | Read / update                                                                      |
| `/actions/{id}/validation` | POST      | `application/json`             | **2 mandatory phases**: `{fortest:true, testSegments:[id]}` then `{fortest:false}` |
| `/actions/{id}/validation` | DELETE    | `application/json`             | Un-validate (state 50/38 → 20). Body `{}` required.                                |
| `/actions/{id}/execution`  | POST      | `application/vnd.np6.cm.email` | Single-recipient send (body = `{recipient: {...}}`)                                |
| `/actions/{id}/executions` | POST      | `application/vnd.np6.cm.email` | Batch send (body = `[{recipient: {...}}, ...]`)                                    |
| `/actions/events`          | GET       | `application/json`             | Pull delivery events (bounce/hit/complaint)                                        |
| `/targets`                 | POST      | `application/json`             | Create a contact (body: **field names at top-level**, not `fields:{id:val}`)       |
| `/targets`                 | GET       | `application/json`             | Search by `?unicity=<value>` or `?hash=<value>`                                    |
| `/segments`                | POST      | `application/json`             | Create a segment (useful for test segments + optional list storage)                |
| `/segments/{id}/targets`   | PUT/GET   | `application/json`             | Read/write segment members (max 50/call)                                           |

### `/executions` body shape (mailMessage)

NP6 confirmed: for a `mailMessage`, **`/executions` does not accept a segment_id as a reference** — you pass the recipient list directly:

```json
[
  { "recipient": { "type": "unicity", "value": ["alice@example.com"] } },
  { "recipient": { "type": "unicity", "value": ["bob@example.com"] } },
  { "recipient": { "type": "id", "value": "000PIZ76" } }
]
```

`recipient.type` values (from the spec):

- `id` — `value` = string (NP6 target id, e.g. `000PIZ76`)
- `unicity` — `value` = **array** of strings (the value of the unicity field; for us: email)
- `hash` — `value` = string (MD5 hash of the target)
- `footprint` — `id` + `unicity` + `hash` at the recipient level (not under `value`)

> If you want to fetch a list from an NP6 segment rather than maintain it on the ChapsMind side: `GET /segments/{id}/targets` returns an array of IDs, which you forward to `/executions` by building the body. But for our use case (newsletter with a list provided by the ChapsMind user), we pass the list **directly** without creating an NP6 segment.

### `/executions` response

Status 200 with an array body (1 element per sent recipient):

```json
[
  {
    "type": "success",
    "id": "f5a0982e-59e1-43bb-afc5-b40aecbb723c", // NP6 message_id
    "recipient": {
      "type": "footprint",
      "id": "000PIZ76",
      "unicity": "nmercier@chapsvision.com",
      "hash": "974d82733bd630e8f23671dcfc322a7e"
    }
  }
]
```

On partial failure: `{type:"error", value:{type:"recipient not found", recipient:{...}}}` for the failing recipient; the rest of the batch can still succeed.

### Send mode: 2 ways (NP6 doc article)

> _(translated from French original)_ "Without parameters: if no JSON is provided, the email is sent to the target as configured via the UI (personalization will apply, as will tracking)."
>
> _(translated from French original)_ "With parameters: if content is sent in the JSON, it replaces the entirety of the previously configured content. It is not interpreted, which means no personalization and no tracking will be applied to it. The email will be sent as-is."
>
> ([source](https://np6.supporthero.io/article/show/34729-quand-doit-on-utiliser-lapi-sendmessage))

ChapsMind uses **mode 1 (no override)**: we configure everything in the action (subject + HTML + headers.from), then execute it by passing only the recipient. The "override per-recipient" mode is our fallback if server-side substitution does not cover the per-recipient unsubscribe URL.

### Per-recipient substitution (unsubscribe URL — still open)

> _(translated from French original)_ "Content override via API is only possible with messages in V4.1. If your action's content is not in that version you will get a 409 in response."
>
> ([source](https://np6.supporthero.io/article/show/167980-je-veux-surcharger-le-contenu-de-mon-email-declenche))

**Practical implication**: our actions ship with `settings.templating.version = "4.1"` (our code does this by default). The exact substitution syntax (placeholders in the HTML for the per-recipient unsubscribe URL) remains **to be confirmed with NP6** — Open Question §10 of ADR-0021.

## HTTP codes: what we actually observed

NP6 returns codes that are sometimes misleading and ship with an empty body. Things to keep in mind:

| Code    | Observed case                                                                                                                                                                                               | Interpretation                                                                                                                |
| ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **200** | `POST /actions` with minimal body; `POST /executions` after the complete 2-phase validation                                                                                                                 | Creation OK / send accepted                                                                                                   |
| **204** | `DELETE /actions/{id}/validation` on a state-50 action; `POST /validation` (both phases)                                                                                                                    | OK, no body                                                                                                                   |
| **400** | malformed JSON body, missing field, or wrong shape (e.g. `from` as a string instead of an object, `target cannot be identified - unicity is null` when `POST /targets` is sent without the right top-level) | Genuine payload error                                                                                                         |
| **403** | (not observed for us)                                                                                                                                                                                       | Forbidden                                                                                                                     |
| **404** | `settings.field=1` on a tenant that expects `field=728`; malformed URL                                                                                                                                      | **Misleading** — can be an invalid value for a valid field, not a missing endpoint                                            |
| **409** | `POST /execution` on a state-38 action (test-validated, not yet prod-validated); `POST /targets` on an existing target; `DELETE /validation` on an already-unvalidated action                               | **State machine blocked** or conflict (resource already exists). **Body systematically empty** — use `x-request-id` to debug. |
| **411** | POST without Content-Length                                                                                                                                                                                 | Missing length header                                                                                                         |
| **415** | wrong Content-Type (e.g. json instead of `application/vnd.np6.cm.email`)                                                                                                                                    | MIME type rejected                                                                                                            |

> **Debug rule**: a 4xx with no body and only an `x-request-id` in the headers → share it with NP6 ops, they can trace on the backend. Cf. example `0d8c24af-93c9-4eb0-81eb-eb2248b5ff9f` which let NP6 diagnose the missing phase-2 validation.

## ChapsMind-side configuration

Env vars of the `stream` service (cf. `apps/stream/app/core/config.py`):

```
NP6_BASE_URL=https://api-cm.np6.com
NP6_API_KEY=<key provided by NP6>
NP6_FROM_EMAIL=noreply@<subdomain>.chapsmind.com       # subdomain to be provisioned with NP6 ops
NP6_FROM_LABEL=ChapsMind                               # sender display name, optional
NP6_BAT_TEST_SEGMENT_ID=1319                           # id of the "BAT TEST" segment for validation phase 1
STREAM_NP6_EVENT_POLL_INTERVAL_SECONDS=300             # event poller cadence (default 5min)
```

**Alembic migration 004** (TAR-1628) — column on `streams`:

- `np6_action_id` (varchar, nullable) — NP6 id of the action template reused for every dispatch

> No `np6_segment_id` column (segment dropped after the 2026-05-04 audit). No `np6_target_id` column on `stream_recipients` (we address contacts by their email = unicity, no need to store the NP6 id).

**Mandatory sender — "single fixed sender" model**: all emails leave from `NP6_FROM_EMAIL`, parsed by the provider into `{prefix, domain, label}`. The domain must be pre-validated at NP6 (SPF/DKIM/DMARC aligned with their sending IPs). For ChapsMind we plan a dedicated subdomain such as `news.chapsmind.com` (to confirm with ops). No per-org override possible in Phase 1.

## Known gotchas

### 1. `field` is tenant-specific

The `settings.field` value (id of the contact field that holds the email) **depends on the tenant**. On CHAP/02C it is `728`. On another tenant it will be different. Our code **no longer** sends this field on action creation — NP6 picks the tenant default itself. `GET /fields` lets you dynamically find the field that has `isUnicity:true`.

### 2. Validation = 2 mandatory phases

Without the prod phase (`{fortest:false}`), the action stays in **state 38** and `/execution(s)` returns 409 with no body. The trap: `{fortest:true}` returns 204 → you think it's validated, but one step is still missing. See §"Action lifecycle" for the full workflow.

### 3. POST /targets — asymmetric body vs GET /targets

**The POST body shape differs from the read shape**:

```jsonc
// Creation — field names at top-level:
// ✅ {"Email": "alice@..."} → 200, target created
// ❌ {"fields":{"728":"alice@..."}} → 400 "target cannot be identified - unicity is null"
// Read (GET /targets/{id}) — field ids under "fields":
// {"id":"...","hash":"...","fields":{"728":"alice@...","729":"Doe"}}
```

This asymmetry is documented nowhere in the OpenAPI spec. A `POST /targets` on an already-existing email returns **409 "target already exists"** — useful to build an idempotent upsert (catch 409, ignore).

### 4. No segment for `mailMessage` — pass the list directly

Confirmed by NP6 (2026-05-04): `/executions` **does not** accept a segment reference in the body for `mailMessage` actions. We pass the recipient array explicitly. The NP6 segment is only used:

- As `testSegments:[id]` during phase-1 validation
- As **optional** storage of a list when we don't have our own on the ChapsMind side (then fetched via `GET /segments/{id}/targets`)

### 5. `unicity.value` must be an array

Not a string. `"unicity": "alice@..."` → 400. `"unicity": ["alice@..."]` → OK. For the other types (`id`, `hash`), `value` is a plain string.

### 6. The `from` sender is an object, not a string

```jsonc
// ❌ "from": "noreply@news.chapsmind.com"  → 400
// ✅ "from": { "prefix": "noreply", "domain": "news.chapsmind.com", "label": "ChapsMind" }
```

### 7. Custom MIME type mandatory for `/executions` and `/render(s)`

`Content-Type: application/vnd.np6.cm.email` — not `application/json`. Everything else (actions, validation, targets, segments) uses standard JSON.

### 8. No NP6 webhook

NP6 **does not push** events to our endpoints. Delivery feedback (bounces, complaints, opens, clicks, unsubscribes) is retrieved by **polling** `GET /actions/events?start=<ts>&end=<ts>&sort=asc`. See `apps/stream/app/services/np6_event_poller.py`.

### 9. The word "webhook" appears nowhere in the spec

For confirmation: the OpenAPI `api.yaml` bundle contains neither `webhook`, `callback`, nor `signature`. If anyone on the team mentions an NP6 webhook, ask for the source — it's most likely a confusion with another system.

### 10. NP6 and its documentation — golden rule

When observed behaviour does not match the spec, contact NP6 with an `x-request-id` instead of guessing. Our example: we spent time testing 5 body formats before realising the issue was the state machine (2-phase validation). NP6 traced the request-id and told us in 2 messages "the test phase had an issue". **Faster to just ask.**

## How to request something from NP6

1. **First reflex: grep the OpenAPI spec** — `https://documentation.np6.com/_bundle/api.yaml` answers 80% of structural questions.
2. **If the spec is silent**: help centre `https://np6.supporthero.io` (API-CM F.A.Q. sections first).
3. **Otherwise, contact**:
   - Our **NP6 project manager** (for API keys, permissions, subdomains, points specific to tenant CHAP/02C). Ask NP6/ops for the current point of contact.
   - General support: `https://support.chapsvision.com`
4. **Always provide the `x-request-id`** returned in the 4xx/5xx response headers — it lets NP6 trace the call on the backend.

## Useful links

- **Our integration**: `apps/stream/app/integrations/email/` (NP6Client + NP6EmailProvider)
- **OpenAPI spec**: https://documentation.np6.com/_bundle/api.yaml
- **HTML doc**: https://documentation.np6.com/api
- **Help centre**: https://np6.supporthero.io
- **API F.A.Q.**: https://np6.supporthero.io/container/show/vous-avez-des-questions-sur-les-api-cm
- **ADR-0021**: [docs/architecture/adr/0021-stream-newsletter-channel.md](../architecture/adr/0021-stream-newsletter-channel.md)
