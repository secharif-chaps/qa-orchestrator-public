# ADR-0019: Security Audit Trail

## Status

**Status:** Proposed

**Date:** 2026-04-21

**Decision Makers:** ChapsMind Engineering Team

**Tags:** security, audit, compliance, iso27001, observability

---

## Context

### Objective

Define the **ChapsMind security audit trail**: list of traced events, storage schema, write mechanism, read access, retention, non-repudiation, and integration with Keycloak and observability.

### Current situation (factual inventory)

The audit trail is today **fragmented, unorchestrated, partially covering**. Piece by piece:

#### What exists

1. **Python standard `logging`** — `apps/global-service/app/core/logging_config.py` and equivalents on screen/stream.
   - **Plain text format**: `"%(asctime)s - %(name)s - %(levelname)s - %(message)s"`.
   - **Destination stdout only** (StreamHandler).
   - Helper `get_logger()` used everywhere; an `extra={...}` dict pattern is in place but not exploited as a structured field.
2. **Ad-hoc security event logs** in admin endpoints:
   - `auth_middleware.py`: Valid / expired / invalid JWT, Keycloak key fetch failure.
   - `authorization.py`: Organization access denied, role denied (with `user`, `org_id`, `resource` in `extra`).
   - `endpoints/team.py`, `endpoints/organizations.py`: Invitation, deletion, permission change, password reset logged at INFO level.
3. **`token_transactions` table** (`global.token_transactions`) — **financial** audit of consumption/addition/adjustment of AI tokens. Fields: `transaction_type`, `reference_type` (company/csv_import/refresh/dispatch/manual/system), `reference_id`, `created_by`, `balance_after`, `created_at`. Index `org + created_at`.
4. **`screen.outbox` table** — outbox pattern for business events (type `screen.company.created` etc.), relayed to `stream`. Not currently intended for security audit.
5. **`stream.events` table** — storage of multi-channel events relayed.
6. **`SourceActivity`, `WatchFileActivity` tables** (target Symfony) — business history per resource, exposed via `GET /sources/{id}/history`.
7. **`TenantContext` Symfony** (target) — decodes the `impersonator` claim from JWT, exposes `getImpersonator()`, `isImpersonating()`, `getAuditUser()`. **No persistence** of impersonation in a dedicated table.

#### What is missing

1. **No dedicated `audit_event` table** at the application level.
2. **Unstructured logs** (plain text), requiring parser ingestion, no typed queryable fields.
3. **No centralized sink**: No Loki, ELK, Splunk, or Datadog in `infra/compose.yaml`. Stdout logs are rotated by Docker with no post-crash guarantee.
4. **Keycloak Events disabled**: `infra/files/realm-chapsmind.json` contains neither `eventsEnabled` nor `adminEventsEnabled`. OIDC events (login, logout, password change, token refresh, session revoke) **are not traced**.
5. **Client IP and User-Agent not logged**: Lost behind the reverse proxy. `X-Forwarded-For` stripped in gateway security — the real source IP is nowhere.
6. **No generic audit hook**: Each endpoint decides ad-hoc what to log. Predictable drift — a new dev forgets to emit the log on a sensitive endpoint.
7. **No automated tests** validating audit event emission on sensitive actions.
8. **No documented retention policy** or long-term archiving.
9. **No alerting**: No automatic anomaly detection (brute force, 403 enumeration, escalation).

### External Constraints

- **ISO 27001 A.12.4 (Logging and monitoring)**: Estimated compliance **40-50%**. Missing: monitoring/alerting, documented retention, log protection (encryption), IP/user-agent.
- **ADR-0018** (Role and Permission Model) introduces sensitive actions (`admin` elevation, cross-tenant bypass, folder access without ACL) **that must imperatively be traced**. Without this ADR, ADR-0018 security remains theoretical.

### No Production Data

As with ADR-0018, there is no production data yet. Implementation can be **direct**, without dual-run or complex migration.

---

## Decision

Implement a **unified security audit trail** articulated in three complementary pillars:

### 1. Primary Storage: `global.audit_events` table (source of truth)

Append-only table, sole repository of security audit. Owned by `global-service` (single source of truth, like the rest of the permission model).

**Schema**:

| Colonne                 | Type         | Description                                                                                                                                                                                                     |
| ----------------------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`                    | UUID         | Primary key (generated applicatively to allow idempotence of writes)                                                                                                                                            |
| `event_type`            | VARCHAR(100) | String `<category>.<action>` (e.g. `admin.role_elevated`, `folder.acl_added`)                                                                                                                                   |
| `event_category`        | VARCHAR(30)  | `auth` \| `authz` \| `admin` \| `admin_cross_tenant` \| `folder` \| `resource` \| `token` \| `security` \| `data_access`                                                                                        |
| `severity`              | VARCHAR(10)  | `info` \| `warning` \| `critical` (alerting + differential retention)                                                                                                                                           |
| `actor_id`              | VARCHAR(100) | Keycloak `sub` of the action initiator (NULL if system event)                                                                                                                                                   |
| `actor_username`        | VARCHAR(255) | Denormalized for reading without Keycloak                                                                                                                                                                       |
| `actor_email`           | VARCHAR(255) | Denormalized                                                                                                                                                                                                    |
| `actor_roles`           | TEXT[]       | Keycloak roles at the time of the event (snapshot)                                                                                                                                                              |
| `impersonator_id`       | VARCHAR(100) | If the action is performed under impersonation, the real ChapsVision admin                                                                                                                                      |
| `impersonator_username` | VARCHAR(255) | Denormalized                                                                                                                                                                                                    |
| `target_type`           | VARCHAR(50)  | Nature of the target resource (`user`, `folder`, `company`, `organization`, `watchfile`, etc.)                                                                                                                  |
| `target_id`             | VARCHAR(100) | ID of the target resource                                                                                                                                                                                       |
| `target_label`          | VARCHAR(500) | Readable denormalization (name, title)                                                                                                                                                                          |
| `organization_id`       | VARCHAR(100) | Organization context (NULL for global events)                                                                                                                                                                   |
| `reason_code`           | VARCHAR(50)  | Normalized reason code (e.g. `admin_bypass`, `acl_editor`, `permission_denied`, `ip_allowlist_violation`)                                                                                                       |
| `outcome`               | VARCHAR(20)  | `success` \| `denied` \| `error`                                                                                                                                                                                |
| `client_ip`             | INET         | Source IP (propagated by gateway via a dedicated internal header)                                                                                                                                               |
| `user_agent`            | VARCHAR(500) | User-Agent of the request                                                                                                                                                                                       |
| `correlation_id`        | UUID         | `X-Correlation-ID` propagated by existing middleware (`apps/global-service/app/core/correlation.py`). Allows cross-referencing with Splunk logs and traces across the entire path gateway → backend → Keycloak. |
| `metadata`              | JSONB        | Free contextual fields (permission diffs, ACL before/after, etc.)                                                                                                                                               |
| `created_at`            | TIMESTAMPTZ  | Server — temporal source of truth                                                                                                                                                                               |

**Indexes**:

- `(organization_id, created_at DESC)` — query by organization
- `(actor_id, created_at DESC)` — "what did this user do?"
- `(target_type, target_id, created_at DESC)` — "who touched this resource?"
- `(event_category, severity, created_at DESC)` — alerting
- Partial GIN on `metadata` for full-text search on large incidents

**Partitioning**: The table is partitioned by **month** via `pg_partman` (or equivalent native PG 15+). Allows efficient archiving/drop without impacting writes.

**Strict append-only**: A dedicated Postgres role `audit_writer` has `INSERT` only (no `UPDATE`, no `DELETE`). The `audit_reader` role has `SELECT` only. Application roles **never** have `UPDATE`/`DELETE` on this table. An erroneous audit entry is not corrected: a corrective entry is added.

### 2. Structured JSON Logs → Splunk (debug channel + production SIEM)

Migration of current Python logs to a **structured JSON format** via `python-json-logger` (or equivalent lightweight). Each log contains:

- `timestamp`, `level`, `logger`, `message`
- `correlation_id`, `organization_id`, `user_id` (propagated via `contextvars` and aligned with existing `CorrelationIdMiddleware` middleware)
- `event_type` when the emitter is a security event
- `event_category`, `severity`, `actor_id`, `outcome`, `reason_code` when applicable

**In production, these logs are ingested by Splunk** — the organization's official SIEM sink. Splunk covers:

- Ingestion via agent (Universal Forwarder on k8s workers, or HEC HTTP Event Collector from the application).
- Retention and long-term search (out of scope ADR, managed by the secops team).
- Alerting on security patterns (brute force, access anomalies) via Splunk searches/alerts.

In **dev**, logs remain human-readable stdout (pretty JSON or `rich`). No local Splunk needed — the `audit_events` table is sufficient for devs.

Relationship between the two channels:

- **`audit_events` (Postgres)** = application source of truth, append-only, queryable from the UI, used by admin/org endpoints (§6), guarantees local non-repudiation.
- **Logs JSON → Splunk** = transverse SIEM view (application audit + Keycloak + infra), alerting, cross-service investigation.

Any write to `audit_events` also emits **a JSON log** (dual channel). The reverse is not true: operational debug logs do not create audit entries.

### 3. Keycloak Events — Direct Ingestion by Splunk

In production, `eventsEnabled: true` + `adminEventsEnabled: true` are enabled in the Keycloak realm, and events (User Events + Admin Events) are:

- **Emitted as JSON to Keycloak stdout** via the `jboss-logging` listener configured in JSON format.
- **Ingested directly by Splunk** via the Universal Forwarder deployed on Keycloak workers.

**The application therefore does not do polling or webhooks to Keycloak.** Splunk is the source of truth for auth/OIDC events; the application consults Splunk (SPL search via API if necessary) or waits for an admin to go directly into the Splunk console for investigation.

**Realm configuration** (to be added in `infra/files/realm-chapsmind.json`):

```json
"eventsEnabled": true,
"eventsListeners": ["jboss-logging"],
"adminEventsEnabled": true,
"adminEventsDetailsEnabled": true,
"enabledEventTypes": [
  "LOGIN", "LOGIN_ERROR", "LOGOUT", "LOGOUT_ERROR",
  "CODE_TO_TOKEN", "CODE_TO_TOKEN_ERROR",
  "REFRESH_TOKEN", "REFRESH_TOKEN_ERROR",
  "REVOKE_GRANT", "UPDATE_PASSWORD", "UPDATE_PASSWORD_ERROR",
  "RESET_PASSWORD", "SEND_RESET_PASSWORD",
  "IDENTITY_PROVIDER_LINK_ACCOUNT", "IMPERSONATE"
]
```

**Propagation of `correlation_id` to Keycloak**: The frontend and gateway include `X-Correlation-ID` in Keycloak auth calls. Keycloak 24+ supports Request Logging MDC — the header is copied into the Keycloak log context and appears in events. Splunk can then join application and Keycloak events on the `correlation_id` key, reconstructing the complete trace of an incident.

**In dev**, Keycloak Events remain visible in the Keycloak container Docker stdout (`task logs:service -- keycloak`). No Splunk required for DX.

**`audit_events` table and Keycloak**: The table does **not** duplicate Keycloak Events. Only application events (sensitive business actions, voter decisions, ACL modifications, admin elevation…) are stored there. For complete audit (auth + application), the investigation tool is Splunk which aggregates both channels via the `correlation_id`.

### 4. Writing — Mechanism and Atomicity

**Principle**: The write of an audit event must be **guaranteed** when the action it traces succeeds. Two cases:

**4.1 Events from an application transaction** (folder creation, role change, resource deletion…):

- Writing to `audit_events` **in the same DB transaction** as the business action. If the transaction rolls back, the audit rolls back too — this is the expected behavior (we don't log a business failure as an audit success).
- No external double-write: writing to a Postgres table local to the gateway is atomic.

**4.2 System or asynchronous events** (stream dispatch, Keycloak events, webhook hook):

- Direct write to `audit_events` via standard Postgres connection, best-effort. A write failure surfaces a critical alert (Prometheus counter `audit_events_write_failures_total`).

**4.3 Generic hook to avoid omission — complete example of the Python decorator**

A FastAPI `@audited(...)` decorator is provided and **mandatory** on all sensitive mutant endpoints of `global-service`. It captures the request context, executes the business logic, then writes the audit entry in the same transaction on success.

**Service structure** (`apps/global-service/app/services/audit_service.py`, new):

```python
# apps/global-service/app/core/audit_context.py
from contextvars import ContextVar
from dataclasses import dataclass

@dataclass(frozen=True)
class AuditContext:
    correlation_id: str
    actor_id: str | None
    actor_username: str | None
    actor_email: str | None
    actor_roles: list[str]
    impersonator_id: str | None
    impersonator_username: str | None
    client_ip: str | None
    user_agent: str | None
    organization_id: str | None

audit_context: ContextVar[AuditContext | None] = ContextVar("audit_context", default=None)
```

Le contexte est peuplé par un middleware FastAPI qui s'exécute après `CorrelationIdMiddleware` et la résolution de l'user. Il lit `request.state.correlation_id`, le user authentifié, les headers `X-Real-IP` / `User-Agent`, et expose l'objet via `contextvars` pour que toute la stack (endpoints, services) puisse le lire sans le passer en paramètre.

**Service d'écriture** :

```python
# apps/global-service/app/services/audit_service.py
from uuid import uuid4
from sqlalchemy.orm import Session
from app.models.audit import AuditEvent
from app.core.audit_context import audit_context
from app.core.logging_config import get_logger

logger = get_logger(__name__)

class AuditService:
    def __init__(self, db: Session):
        self.db = db

    def record(
        self,
        *,
        event_type: str,
        event_category: str,
        severity: str,
        target_type: str | None = None,
        target_id: str | None = None,
        target_label: str | None = None,
        outcome: str = "success",
        reason_code: str | None = None,
        metadata: dict | None = None,
    ) -> AuditEvent:
        ctx = audit_context.get()
        if ctx is None:
            # System event outside HTTP context (worker, scheduler). Caller must provide minimal context.
            raise RuntimeError("AuditService.record called outside audit context")

        event = AuditEvent(
            id=uuid4(),
            event_type=event_type,
            event_category=event_category,
            severity=severity,
            actor_id=ctx.actor_id,
            actor_username=ctx.actor_username,
            actor_email=ctx.actor_email,
            actor_roles=ctx.actor_roles,
            impersonator_id=ctx.impersonator_id,
            impersonator_username=ctx.impersonator_username,
            target_type=target_type,
            target_id=target_id,
            target_label=target_label,
            organization_id=ctx.organization_id,
            reason_code=reason_code,
            outcome=outcome,
            client_ip=ctx.client_ip,
            user_agent=ctx.user_agent,
            correlation_id=ctx.correlation_id,
            metadata=metadata or {},
        )
        self.db.add(event)
        self.db.flush()  # guarantees INSERT in current transaction, no commit here

        # Dual channel: structured JSON log for Splunk
        logger.info(
            event_type,
            extra={
                "event_category": event_category,
                "severity": severity,
                "actor_id": ctx.actor_id,
                "target_type": target_type,
                "target_id": target_id,
                "organization_id": ctx.organization_id,
                "outcome": outcome,
                "correlation_id": ctx.correlation_id,
                "reason_code": reason_code,
            },
        )
        return event
```

**Le décorateur** :

```python
# apps/global-service/app/core/audit_decorator.py
from functools import wraps
from typing import Callable, Any
from inspect import signature

def audited(
    *,
    event_type: str,
    event_category: str,
    severity: str = "info",
    target_type: str | None = None,
    extract_target_id: Callable[..., str] | None = None,
    extract_target_label: Callable[..., str] | None = None,
    extract_metadata: Callable[..., dict] | None = None,
):
    """Audit decorator for FastAPI endpoint.

    Writes an event to `audit_events` if business logic succeeds.
    If the function raises, no entry is created (except explicit error audit via record_failure()).
    """
    def decorator(func: Callable):
        @wraps(func)
        async def wrapper(*args: Any, **kwargs: Any) -> Any:
            # 1. Execute business logic
            result = await func(*args, **kwargs) if _is_coro(func) else func(*args, **kwargs)

            # 2. On success, write audit — same transaction as business logic
            #    since AuditService shares the SQLAlchemy session injected in kwargs["db"]
            db = kwargs.get("db")
            if db is None:
                raise RuntimeError(f"@audited requires a 'db: Session' parameter in {func.__name__}")

            audit = AuditService(db=db)
            audit.record(
                event_type=event_type,
                event_category=event_category,
                severity=severity,
                target_type=target_type,
                target_id=extract_target_id(kwargs) if extract_target_id else None,
                target_label=extract_target_label(kwargs) if extract_target_label else None,
                metadata=extract_metadata(kwargs, result) if extract_metadata else None,
            )
            return result
        return wrapper
    return decorator
```

**Utilisation sur un endpoint critique** :

```python
# apps/global-service/app/api/endpoints/team.py

@router.post("/organizations/{org_id}/members/{user_id}/elevate-admin")
@audited(
    event_type="admin.role_elevated",
    event_category="admin",
    severity="critical",
    target_type="user",
    extract_target_id=lambda kw: kw["user_id"],
    extract_metadata=lambda kw, result: {
        "new_role": "admin",
        "target_username": result.username,
    },
)
async def elevate_to_admin(
    org_id: str,
    user_id: str,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin"])),
    db: Session = Depends(get_db),
) -> UserResponse:
    """Elevates a user to the admin composite role. Reserved for ChapsVision admins (ADR-0018 §7.4)."""
    # ... business logic: verify ChapsVision target, call Keycloak Admin API, commit
    return updated_user
```

**Audit of explicit failure**: When an endpoint rejects an action for security reasons (e.g., §5 ADR-0018: non-ChapsVision admin), we cannot rely on the decorator (the function raises). We then call `AuditService.record_failure(...)` explicitly before `raise HTTPException(403)`.

**CI policy**: A test `tests/policy/test_audited_coverage.py` parses the AST of all modules `apps/*/app/api/endpoints/` and verifies that every handler `POST`, `PUT`, `PATCH`, `DELETE` not explicitly listed in a whitelist `ENDPOINTS_EXEMPT_FROM_AUDIT` bears the `@audited` decorator. Rejection at CI otherwise.

**4.4 Client IP Propagation**

Currently the gateway strips `X-Forwarded-For`. To retrieve the real IP:

- The proxy (nginx / ingress) injects `X-Real-IP` and `X-Forwarded-For`.
- The gateway reads these headers, validates (whitelist of trusted upstream proxies), and copies them to an internal header `X-Client-IP` which it transmits to backends via the Internal JWT (claim `client_ip`).
- The original external headers remain stripped on the backends side to prevent spoofing.

**4.5 Audit Publishing from Sub-modules (screen, stream, target)**

The `audit_events` table lives in the `global-service` DB (schema `global`). Sub-modules (screen, stream, target) do not have direct DB access to it — this is intentional, to maintain schema separation and avoid coupling. They publish instead via an **internal HTTP endpoint** exposed by the gateway.

**Endpoint**:

```http
POST /api/internal/audit/events
Authorization: Internal <JWT>
Content-Type: application/json

{
  "client_event_id": "<uuid>",          // generated by caller, for idempotence
  "event_type": "folder.acl_added",
  "event_category": "folder",
  "severity": "info",
  "target_type": "folder",
  "target_id": "<uuid>",
  "target_label": "Acme Corp - Watch Q1",
  "outcome": "success",
  "reason_code": null,
  "metadata": {
    "shared_with_user_id": "...",
    "acl_role": "editor"
  }
}

→ 201 Created       (recorded)
→ 200 OK             (duplicate inferred from client_event_id, idempotent)
→ 403 Forbidden      (Invalid Internal JWT)
→ 422 Unprocessable  (Invalid payload)
```

The **`client_event_id`** (UUIDv4) guarantees idempotence: if the POST retries due to network, the second insert returns `200 OK` instead of creating a duplicate. UNIQUE constraint on `(client_event_id)` on DB side.

The **`actor_*`**, **`correlation_id`**, **`client_ip`**, **`organization_id`** fields are **not** sent by the client: the gateway reads them from the Internal JWT (for actor and org) and from context (for correlation_id/client_ip also relayed via the Internal JWT). Impossible for a backend to falsify the actor's identity.

**Backend write pattern (screen/stream Python example)**:

```python
# apps/screen/app/services/audit_client.py
from uuid import uuid4
import httpx
from app.core.internal_token import mint_internal_jwt
from app.core.logging_config import get_logger

logger = get_logger(__name__)

class AuditClient:
    def __init__(self, gateway_url: str):
        self.url = f"{gateway_url}/api/internal/audit/events"

    async def record(self, **fields) -> None:
        payload = {"client_event_id": str(uuid4()), **fields}
        try:
            async with httpx.AsyncClient(timeout=2.0) as client:
                resp = await client.post(
                    self.url,
                    json=payload,
                    headers={"Authorization": f"Internal {mint_internal_jwt()}"},
                )
                resp.raise_for_status()
        except (httpx.RequestError, httpx.HTTPStatusError) as exc:
            # Best-effort failure: write to local outbox (see below)
            logger.warning("audit_post_failed, falling back to outbox", extra={"error": str(exc), "event": payload})
            AuditOutboxService(db=get_db()).record(payload)
```

**Delivery guarantee via local audit outbox**: If the HTTP POST fails (gateway timeout, 5xx, maintenance), the event is **not lost**. Each backend has a **dedicated audit outbox table**, distinct from the existing business outbox.

#### Two distinct outboxes, identical pattern

| Outbox                            | Scope                 | Consumer                                               | Retention                  | Example                                                       |
| --------------------------------- | --------------------- | ------------------------------------------------------ | -------------------------- | ------------------------------------------------------------- |
| **`<schema>.outbox`** (existing)  | Business events       | `stream` (multi-channel distribution to final clients) | Short (purge once relayed) | `screen.company.created` relayed to Teams/Slack/webhook       |
| **`<schema>.audit_outbox`** (new) | Security/audit events | `global-service` (table `audit_events`)                | Short (purge once relayed) | `folder.acl_added`, `resource.deleted`, `admin.role_elevated` |

They share the **same technical pattern** (atomic insert with business transaction, periodic relay worker, `published_at` to mark delivered) but are **physically separated**:

- Consumers are different (stream vs gateway audit).
- Payload contracts are different.
- Operational criticality is different: audit outbox not relayed for > 5 min must surface a PagerDuty alert (potential loss of evidence), business outbox can tolerate a few minutes of delay without alerting.
- The separation makes debugging clearer: a stuck audit does not pollute the view of business events and vice versa.

#### `audit_outbox` Schema (identical in each backend)

```python
# apps/screen/app/models/audit_outbox.py (and equivalents stream/target)
class AuditOutboxEvent(Base):
    __tablename__ = "audit_outbox"
    __table_args__ = {"schema": SCREEN_SCHEMA}   # or STREAM_SCHEMA, etc.

    id = Column(Integer, primary_key=True, autoincrement=True)
    client_event_id = Column(UUID, nullable=False, unique=True)     # idempotence on gateway side
    payload = Column(JSONB, nullable=False)                          # complete JSON to POST
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    published_at = Column(DateTime(timezone=True), nullable=True)    # NULL = pending
    retry_count = Column(Integer, nullable=False, server_default="0")
    last_error = Column(Text, nullable=True)
```

#### Detailed Flow

1. **Business logic**: The backend executes its action (e.g., delete watchfile) in its local DB transaction.
2. **In the same transaction**, insert into `audit_outbox` with the complete pre-built payload (including generated `client_event_id`). If the transaction rolls back, the outbox rolls back too — **no audit evidence for an action that didn't occur**.
3. **Hot path**: After commit, a call to `AuditClient.record()` attempts the HTTP POST to `/api/internal/audit/events` **immediately** (best-effort, short 2s timeout).
   - Success → mark `published_at = now()` on the outbox row. Entry in `audit_events` created on gateway side.
   - Failure → leave the row pending. No user response blocking.
4. **Relay worker** (`AuditOutboxRelay`, periodic Celery/Symfony Messenger task, 30s interval):
   - Select rows `published_at IS NULL` ordered by `created_at`.
   - POST each to gateway. Success → `published_at = now()`. Failure → `retry_count += 1`, `last_error = ...`.
   - Targeted exponential backoff (max 5 min between attempts on same row).
   - After 100 retries without success (~ a few hours of gateway downtime), event is escalated to critical alert (Prometheus counter `audit_outbox_stuck_events_total`).
5. **Idempotence**: Thanks to `client_event_id`, a replayed POST does not cause a duplicate on `audit_events` — gateway returns `200 OK` on duplicate.
6. **Purge**: Once `published_at` is set, rows are deleted after 7 days (security retention in case of need for manual replay post-incident).

#### Rationale

This mechanism guarantees the **"zero loss"** property of audit events over time, **without** penalizing user request latency (no blocking synchronous POST, no 2PC between backend DB and gateway DB). It's the classic "eventual consistency with local transactional durability" tradeoff, adapted to a context where cross-DB atomicity is not available.

**Pattern on target side (Symfony/PHP)**:

```php
// apps/target/src/Infrastructure/Audit/AuditClient.php
#[AsEventSubscriber]
final class AuditClient
{
    public function record(
        string $eventType,
        string $eventCategory,
        string $severity = 'info',
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
    ): void {
        $payload = [
            'client_event_id' => (string) Uuid::v4(),
            'event_type' => $eventType,
            'event_category' => $eventCategory,
            'severity' => $severity,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
            'outcome' => 'success',
        ];

        try {
            $this->httpClient->request('POST', $this->gatewayUrl.'/api/internal/audit/events', [
                'headers' => ['Authorization' => 'Internal '.$this->internalJwtMinter->mint()],
                'json' => $payload,
                'timeout' => 2.0,
            ]);
        } catch (TransportExceptionInterface $e) {
            // Local outbox via Messenger (Symfony) for retry
            $this->messageBus->dispatch(new RelayAuditEventMessage($payload));
            $this->logger->warning('audit_post_failed, enqueued for retry', ['error' => $e->getMessage()]);
        }
    }
}
```

**PHP 8 attribute** optional (equivalent to Python decorator for target controllers):

```php
#[Audited(
    eventType: 'resource.deleted',
    eventCategory: 'resource',
    severity: 'warning',
    targetType: 'watchfile',
    targetIdProperty: 'watchfileId',
)]
#[Route('/watchfiles/{watchfileId}', methods: ['DELETE'])]
public function delete(string $watchfileId): Response { ... }
```

A `KernelEvents::VIEW` subscriber reads the attribute, waits for the request to finish without exception, then calls `AuditClient::record()`.

**Summary of 3 writing modes**:

| Source                           | Mechanism                                           | Atomicity                               | Example                                  |
| -------------------------------- | --------------------------------------------------- | --------------------------------------- | ---------------------------------------- |
| `global-service` endpoint        | `@audited` decorator + same DB session              | **Strong** (transactional)              | Admin elevation, folder share, org CRUD  |
| Python sub-module                | `AuditClient.record()` via HTTP + local outbox      | **Eventual consistency** (outbox retry) | Screen company deletion, stream dispatch |
| PHP sub-module                   | `#[Audited]` or `AuditClient::record()` + Messenger | **Eventual consistency**                | Watchfile archival, actor modification   |
| System event (Celery, scheduler) | `AuditService.record()` direct with forged context  | **Strong** if worker shares gateway DB  | Keycloak sync cron, token cleanup        |

### 5. Catalog of Events to Trace

Exhaustive list (not extensible without ADR update):

#### `auth.*` (source: Keycloak Events + gateway)

- `auth.login_success`, `auth.login_failure`
- `auth.logout`, `auth.session_revoked`
- `auth.token_refresh`, `auth.token_refresh_failed`
- `auth.password_reset_initiated`, `auth.password_reset_completed`
- `auth.mfa_enrolled`, `auth.mfa_disabled`

#### `authz.*` (source: gateway + voter §9 ADR-0018)

- `authz.permission_denied` — severity `warning`, with `reason_code` from voter
- `authz.admin_role_denied` — severity `critical` (cf. ADR-0018 §5: user with admin role but non-ChapsVision)
- `authz.cross_org_access_denied` — severity `warning`
- `authz.folder_access_denied` — severity `info` (folder access rejection, cf. ADR-0018 §9.2)

#### `admin.*` (source: organizational admin endpoints)

- `admin.role_assigned`, `admin.role_revoked` (composite role change)
- `admin.role_elevated` — severity `critical` (cf. ADR-0018 §7.5, elevation to manager/admin)
- `admin.user_invited`, `admin.user_removed`
- `admin.user_disabled`, `admin.user_enabled`
- `admin.organization_created`, `admin.organization_deleted`
- `admin.organization_settings_changed`
- `admin.organization_tokens_added` (manual top-up)

#### `admin_cross_tenant.*` (source: ChapsVision global admin endpoints)

- `admin_cross_tenant.data_accessed` — read of a client org's data by a ChapsVision admin. `target_type = 'organization'`, `metadata` contains the path accessed and effective filter.
- `admin_cross_tenant.data_modified` — write
- `admin_cross_tenant.impersonation_started`, `admin_cross_tenant.impersonation_ended`

#### `folder.*` (source: folder service)

- `folder.created`, `folder.archived`, `folder.restored`, `folder.deleted`
- `folder.acl_added`, `folder.acl_removed`, `folder.acl_role_changed`
- `folder.owner_cascade` (automatic cascade §8.3 ADR-0018)

#### `resource.*` (source: screen/target/stream services via their `delete` endpoints)

- `resource.deleted` — hard deletion of a company / watchfile / stream / document
- `resource.archived`, `resource.restored`
- `resource.exported` — export CSV/PDF

#### `token.*` (source: AI token service)

- `token.consumed`, `token.reserved`, `token.released`
- `token.added`, `token.adjusted`

#### `security.*` (source: global-service middleware + detection cron)

- `security.rate_limit_triggered`
- `security.suspicious_activity_detected` (heuristic pattern, alerting)
- `security.webhook_rejected` (invalid webhook token, IP not allowed)
- `security.internal_jwt_forged` (invalid signature on Internal JWT)

#### `data_access.*` (optional, phase 2 if required by client with contractual clause)

- `data_access.sensitive_viewed` — consultation of data explicitly marked sensitive (PII, financial)

### 6. Reading and Exposure

**Endpoints**:

- `GET /api/admin/audit/events` — reserved for users with composite role `admin` (ChapsVision). Filters: `event_type`, `event_category`, `severity`, `actor_id`, `target_type`, `target_id`, `organization_id`, `from`, `to`. Cursor-based pagination. CSV/JSON export.
- `GET /api/organizations/{org_id}/audit/events` — accessible to organization `manager` (and ChapsVision `admin`). Implicit filter on `organization_id`. No raw export by default (explicit request validated by global admin for external export).
- No **write** or **modification** endpoint. No deletion endpoint.

**Dedicated UI**: An `/admin/audit` page on the frontend (admin role reserved) and an "Activity Log" tab on the organization page (for managers). Filterable, sortable, exportable.

**API Platform / OpenAPI**: Both endpoints are documented with required permissions (cf. ADR-0018 §9.7 — `x-folder-action` is not applicable here, we use standard role pre-check).

### 7. Retention

| Tier               | Duration                               | Support                                      | Accessibility                                      |
| ------------------ | -------------------------------------- | -------------------------------------------- | -------------------------------------------------- |
| **Hot**            | 12 months                              | Partitioned Postgres                         | Queryable via endpoints §6                         |
| **Warm**           | 24 additional months (total 36 months) | Partitioned Postgres, reduced indexes        | Queryable but higher latency                       |
| **Cold (archive)** | Up to 7 years                          | S3 / GCS bucket (Parquet + zstd compression) | Async query with delay (48 h) by ChapsVision admin |
| **Drop**           | Beyond 7 years                         | —                                            | Automatic deletion (GDPR + ISO 27001)              |

For `severity=critical` events, retention extended to **7 years in hot/warm tier** (no archiving before 7 years). Justification: compliance + legal retention for audit.

### 8. Non-repudiation

- **Append-only DB** (Postgres role `audit_writer` without UPDATE/DELETE) is the first line.
- **No hash chain in V1** — overcomplication relative to current threat model (application DB compromise would already be a major incident).
- **Phase 2**: Daily export to S3 archive in **WORM bucket** (Write Once Read Many) with `s3:BucketObjectLockConfiguration` for cryptographic locking. Guarantees that a ChapsVision admin cannot rewrite history; only an infra operator with root S3 access could (which is already a strong separation).

### 9. Observability

Prometheus counters (already recommended by ADR-0018 §9.5) extended:

- `audit_events_total{event_category, severity, outcome}`
- `audit_events_write_failures_total`
- `audit_events_write_latency_seconds` (histogram)

Recommended alerts (to be defined in future observability ADR, not here):

- `authz.permission_denied` rate > 10× baseline over 5 min → possible enumeration.
- Single `authz.admin_role_denied` → immediate PagerDuty alert (escalation attempt).
- Audit write failure for > 5 min → critical alert (audit must never be lost).

### 10. Confidentiality and GDPR

- The `metadata` JSONB **never** contains raw sensitive data (passwords, tokens, message content). Sensitive values are replaced with hashes or IDs.
- GDPR right to be forgotten applies to the **external** identity (Keycloak profile) but **not** to the security audit log which remains under justified legal retention (system security, contractual obligation). Explicitly document in the privacy policy.
- On GDPR user deletion, audit entries are **anonymized** (actor_id → stable pseudonym, email → `removed@example.invalid`) but not deleted. A `data_access.pseudonymized` entry traces this operation.

---

## Options Considered

### Option 1: Dedicated `audit_events` table + JSON logs + Keycloak events (chosen)

**Description:** Triple channel — local append-only table as source of truth, JSON logs for debug/Loki ingestion, Keycloak events polling to cover the IdP.

**Pros:**

- Single source of truth, queryable via simple SQL.
- Strict append-only guaranteed by Postgres roles.
- No critical external dependency (even if Loki is down, audit is preserved).
- Consistent with existing architecture (everything already goes through `global-service`).
- Enables cross-cutting queries (who did what on which resource).

**Cons:**

- Significant storage in application DB (mitigated by partitioning + S3 archiving).
- Requires table creation + decorator + Keycloak config migration.
- Non-trivial application migration (adding decorator to ~30 endpoints).

### Option 2: Structured JSON logs alone → external sink (ELK/Loki)

**Description:** No dedicated table. Emit well-typed JSON logs, ingested by an external stack (Loki + Grafana, or Elastic + Kibana).

**Pros:**

- Zero DB schema to maintain.
- Powerful full-text indexing (Elastic).
- Standard tool for ops.

**Cons:**

- Strong dependency on observability infra (if collector is down, audit is lost).
- No strict append-only — logs can be deleted from an ELK stack.
- Elastic licensing costs or Loki operational burden.
- Less easy to query transactionally from the application (admin UI would query Loki via API).
- Non-compliance with ISO 27001 A.12.4.7 (log protection).

### Option 3: Extended outbox for audit (generalized `screen.outbox`)

**Description:** Reuse existing outbox pattern to also store audit events, with relay to an audit table or directly to external sink.

**Pros:**

- Reuse of existing code.
- Native transactional atomicity.

**Cons:**

- Strong coupling with current outbox business logic (relay to stream).
- Outbox is designed for "public" business events; mixing security audit makes flush riskier (relay bug loses audits).
- No strict append-only at source (outbox is in "emit-then-flush" mode).

### Option 4: Complete delegation to Keycloak Events + Sentry/Datadog

**Description:** Rely on Keycloak for OIDC audit, Sentry for server errors, and that's it.

**Pros:**

- Zero code to write.

**Cons:**

- Very partial coverage — business actions (folder CRUD, role elevation, sharing) don't go through Keycloak.
- Sentry is not an audit tool, it's an errors tool.
- Obvious ISO non-compliance.
- Rejected outright.

**Decision**: **Option 1**. It combines the rigor of a dedicated DB table (append-only, transactional, sovereign) with the operational visibility of JSON logs. Option 2 remains underlying as a non-critical secondary channel.

---

## Consequences

### Positive

- **ISO 27001 A.12.4 compliance** raised from ~40% to ~85% (remaining: alerting and long-term WORM archiving in phase 2).
- **Single source of truth** for security audit, queryable via standard SQL and exposable via API/UI.
- **Application non-repudiation** guaranteed by the append-only model.
- **Traceability of sensitive actions** from ADR-0018: admin elevation, cross-tenant access, folder ACL modifications.
- **Natural integration with folder voter** (ADR-0018 §9.2): each `denied` decision generates an `authz.*` event.
- **Facilitated ops debugging** via parallel structured JSON logs.

### Negative

- **DB storage** increased: estimated 100-500 MB/org/year depending on activity. Mitigated by partitioning + S3 archiving.
- **Initial development** non-trivial: table + `@audited` decorator + endpoint instrumentation + Keycloak polling + admin UI.
- **Cognitive load** on devs: forgetting the `@audited` decorator on a new sensitive endpoint creates an audit gap. Mitigated by CI test.
- **Keycloak Events cost**: Activation slightly increases Keycloak load. Negligible in practice.
- **GDPR policy to draft** to formalize audit log retention despite right to be forgotten.

### Neutral

- **SIEM export** (Splunk, Wazuh, etc.): not decided in this ADR. Architecture allows export via periodic job reading `audit_events` and pushing via webhook/API. To decide per client contract.
- **Admin UI**: The `/admin/audit` page is to be designed (out of scope for this ADR).
- **Hash chain / signature**: Rejected in V1, possible resumption in phase 2 if contractual requirement.
- **Load testing**: The `@audited` decorator adds one insert per mutant request. Measure before prod deployment (estimate: < 5 ms overhead P99).

---

## Implementation Notes

### Recommended Implementation Order

1. **Alembic migration** to create `global.audit_events` + Postgres roles `audit_writer` / `audit_reader`.
2. **`audit_service` module** in `global-service`: `AuditService.record(event_type, ...)`.
3. **`@audited` decorator** and `contextvars` to propagate `request_id`, `client_ip`, `user_agent`.
4. **Progressive endpoint instrumentation** (order by criticality: admin._ > folder._ > resource._ > token._).
5. **Activate Keycloak Events** in `realm-chapsmind.json`.
6. **Keycloak Admin API polling**: Celery task + writer to `audit_events`.
7. **Read endpoints** `/api/admin/audit/events` and `/api/organizations/{id}/audit/events`.
8. **Frontend UI** `/admin/audit` + org tab.
9. **Python logs to JSON migration** (can be done in parallel).
10. **pg_partman partitioning** and S3 archiving job (phase 2).

### Integration with ADR-0018

Strong articulation points:

- **§5 (ChapsVision detection)**: The 403 issued when a non-ChapsVision user carries the `admin` role triggers an `authz.admin_role_denied` event with `critical` severity.
- **§7.4 (Admin elevation with type-to-confirm)**: Success triggers `admin.role_elevated` + the entry in `audit_events` is the definitive archive (Keycloak Events is secondary).
- **§7.5 (Elevation notifications)**: Notification is triggered from `audit_events` write (guarantees 1:1 between log and notification).
- **§8.5 (Folder sharing notifications)**: Same, any `folder.acl_*` entry triggers notification, not vice versa.
- **§9.2 (Folder voter)**: Any `reason_code != allowed` records an `authz.folder_access_denied` event if access origin is UI, filtering at decorator level to avoid flooding audit with batch reads (only `write`/`manage` denied are traced).

### Tests

- **Unit**: `AuditService` + decorator + `contextvars` propagation.
- **Integration**: admin endpoint → verify DB entry + JSON log.
- **CI policy**: AST scan verifying presence of `@audited` on each POST/PUT/PATCH/DELETE endpoint not explicitly exempt (documented whitelist).
- **Load test**: decorator overhead on a critical endpoint.

### Dev vs Prod Configuration

- **Dev (`task init`)**: `audit_events` created, populated, queryable via UI. No Keycloak polling (disabled by flag, Keycloak stdout logs suffice).
- **Prod**: Keycloak polling enabled, S3 archiving configured, pg_partman retention enabled.

---

## References

- **ADR-0018** (Role and Permission Model) — primary reference, consumes events from this ADR.
- **ADR-0003** (Keycloak Authentication) — IdP context.
- **ADR-0009** (Global Service Architecture) — natural home for audit.
- [ISO 27001 Annex A.12.4 — Logging and monitoring](https://www.iso.org/standard/54534.html)
- [Keycloak Events and Event Listeners documentation](https://www.keycloak.org/docs/latest/server_admin/#admin-events)
- [pg_partman documentation](https://github.com/pgpartman/pg_partman)
