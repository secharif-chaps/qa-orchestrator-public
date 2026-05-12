"""NP6 implementation of `EmailProvider` (ADR-0020 §"NP6 API Contract — Verified").

`NP6EmailProvider` orchestrates `NP6Client` (the bare-metal HTTP wrapper)
into the high-level newsletter primitives the adapter depends on:
create / update an action, validate it (2-phase, idempotent), provision
contacts as targets, dispatch by `unicity`, and pull events.

Workflow (audited live 2026-05-04, see `docs/np6/README.md`)
------------------------------------------------------------
1. `create_action` → state 10/20 (POST `/actions`, content embedded).
2. `update_action` (optional, on subsequent dispatches) → state 20.
3. `validate_action` → state 38 then 50 (idempotent: GET first, skip if
   already 50; catch 409 as already-validated).
4. `upsert_target` (per recipient) → POST `/targets {Email: ...}`, 409 = ok.
5. `execute_to_recipients` → POST `/actions/{id}/executions` with array of
   `{recipient: {type: 'unicity', value: [email]}}`.

Sender invariant
----------------
Every action's `content.headers.from` is stamped from `NP6_FROM_EMAIL`
(parsed into `{prefix, domain, label}`) — callers cannot influence it.
This is the second line of defense behind `EmailSendRequest`'s
`extra="forbid"` and is asserted by the security tests.
"""

from __future__ import annotations

import logging
from datetime import UTC, datetime
from typing import Any

import httpx

from app.core.config import Settings
from app.core.config import settings as default_settings

from .np6_client import NP6Client
from .provider import EmailProvider
from .schemas import (
    EmailDeliveryEvent,
    EmailDeliveryEventType,
    EmailSendRequest,
    EmailSendResult,
    ExecutionResult,
)

logger = logging.getLogger(__name__)

# NP6 templating version (`EmailTemplating` discriminator).
# 4.1 is the only documented version in the spec we audited.
_NP6_TEMPLATING_VERSION = "4.1"

# NP6 action `editionMode`: 0=design, 1=html, 2=text. We always send pre-rendered HTML.
_NP6_EDITION_MODE_HTML = 1

# Action state at which an action is fully validated and can dispatch.
# State machine: 10 → 20 (content present) → 38 (BAT validated) → 50 (prod
# validated, sendable) → 100 (archived). See docs/np6/README.md.
_NP6_STATE_VALIDATED = 50

# Conservative batch size for `/actions/{id}/executions`. NP6 does not document
# a hard cap, but oversized batches risk request timeouts and partial
# server-side processing. We chunk internally so callers never have to think
# about it.
_NP6_RECIPIENTS_PER_BATCH = 500


class NP6EmailProvider(EmailProvider):
    """High-level newsletter operations against NP6."""

    def __init__(
        self,
        settings: Settings | None = None,
        *,
        client: NP6Client | None = None,
    ) -> None:
        self._settings = settings or default_settings
        if not self._settings.NP6_BASE_URL:
            raise ValueError("NP6_BASE_URL is required for NP6EmailProvider")
        if not self._settings.NP6_API_KEY:
            raise ValueError("NP6_API_KEY is required for NP6EmailProvider")
        if not self._settings.NP6_FROM_EMAIL:
            raise ValueError("NP6_FROM_EMAIL is required for NP6EmailProvider")
        self._client = client or NP6Client(
            base_url=self._settings.NP6_BASE_URL,
            api_key=self._settings.NP6_API_KEY,
        )

    # ----------------------------------------------------------- sender helpers

    def _from_components(self) -> dict[str, str]:
        """Parse `NP6_FROM_EMAIL` into the `{prefix, domain, label}` shape NP6 expects."""
        email = self._settings.NP6_FROM_EMAIL
        if "@" not in email:
            raise ValueError(f"NP6_FROM_EMAIL must be a full email address (got {email!r})")
        prefix, domain = email.split("@", 1)
        label = getattr(self._settings, "NP6_FROM_LABEL", "") or "ChapsMind"
        return {"prefix": prefix, "domain": domain, "label": label}

    def _action_payload(self, *, name: str, subject: str, html: str) -> dict[str, Any]:
        """Assemble the `mailMessage` action body — sender stamped here, not by callers.

        We deliberately omit `settings.field` and `settings.contentFormat`:
        NP6 fills both with tenant-specific defaults (the email field id is
        per-customer, e.g. 728 for our agency CHAP/customer 02C). Setting
        `field=1` triggers a 404 instead of a clean 400 (NP6 quirk on
        unknown field ids). See live verification 2026-05-01.
        """
        return {
            "type": "mailMessage",
            "name": name,
            "settings": {
                "templating": {"version": _NP6_TEMPLATING_VERSION},
                "editionMode": _NP6_EDITION_MODE_HTML,
            },
            "content": {
                "headers": {"from": self._from_components()},
                "subject": subject,
                "html": html,
            },
        }

    # ------------------------------------------------------------------- actions

    async def create_action(self, *, name: str, subject: str, html: str) -> str:
        payload = self._action_payload(name=name, subject=subject, html=html)
        response = await self._client.create_action(payload)
        action_id = response.get("id")
        if not action_id:
            raise ValueError(f"NP6 /actions response missing id: {response!r}")
        return str(action_id)

    async def update_action(self, action_id: str, *, subject: str, html: str) -> None:
        payload = self._action_payload(
            name=f"action-{action_id}",
            subject=subject,
            html=html,
        )
        await self._client.update_action(action_id, payload)

    # ---------------------------------------------------------------- validation

    async def validate_action(self, action_id: str, *, bat_test_segment_id: int) -> None:
        """2-phase validation, idempotent.

        State machine:
            10/20 → POST validation `{fortest:true, testSegments:[bat]}` → 38
            38    → POST validation `{fortest:false}`                    → 50
            50    → no-op (already sendable)

        We GET first to skip the work when the action is already at state 50;
        we also catch 409 from each phase as "already past this state" so a
        concurrent caller (or a previous failed attempt) cannot wedge us.
        """
        try:
            current = await self._client.get_action(action_id)
        except httpx.HTTPStatusError as exc:
            # If GET fails, fall through to validation attempts — they will
            # surface a more meaningful error if the action truly doesn't exist.
            logger.info(
                "NP6 GET /actions failed during validate_action precheck",
                extra={"action_id": action_id, "status": exc.response.status_code},
            )
        else:
            if int(current.get("state", 0)) >= _NP6_STATE_VALIDATED:
                logger.debug(
                    "NP6 action already validated; skipping validation calls",
                    extra={"action_id": action_id, "state": current.get("state")},
                )
                return

        await self._validate_phase(
            action_id,
            {"fortest": True, "testSegments": [bat_test_segment_id]},
            phase="bat",
        )
        await self._validate_phase(
            action_id,
            {"fortest": False},
            phase="prod",
        )

    async def _validate_phase(self, action_id: str, body: dict[str, Any], *, phase: str) -> None:
        try:
            await self._client.validate_action(action_id, body)
        except httpx.HTTPStatusError as exc:
            if exc.response.status_code == 409:
                # Already at this validation state — concurrent caller raced
                # ahead, or we're re-running after a partial success. Either
                # way, the post-condition holds.
                logger.info(
                    "NP6 validate_action returned 409; treating as already-validated",
                    extra={"action_id": action_id, "phase": phase},
                )
                return
            raise

    # ------------------------------------------------------------------ targets

    async def upsert_target(self, email: str) -> bool:
        """POST `/targets` `{Email: <email>}`; 409 "already exists" is success.

        Returns `True` when a new target was created, `False` when NP6
        signalled an existing one via 409. Field NAMES (not ids) at the top
        level — asymmetric vs GET responses. See `docs/np6/README.md`
        §"Pièges connus" entry 3.
        """
        try:
            await self._client.create_target({"Email": email})
        except httpx.HTTPStatusError as exc:
            if exc.response.status_code == 409:
                logger.debug(
                    "NP6 target already exists; idempotent no-op",
                    extra={"email": email},
                )
                return False
            raise
        return True

    # ------------------------------------------------------------------ dispatch

    async def execute_to_recipients(
        self,
        action_id: str,
        emails: list[str],
    ) -> list[ExecutionResult]:
        """Dispatch `action_id` to each email by `unicity`.

        Returns one `ExecutionResult` per input email, in the order the NP6
        response contains them. NP6 spec confirms responses are in request
        order; if the count diverges we still return what NP6 sent us — the
        adapter decides how to reconcile.

        Recipients are chunked into batches of `_NP6_RECIPIENTS_PER_BATCH`
        to keep individual `/executions` calls within safe latency bounds.
        """
        if not emails:
            return []
        results: list[ExecutionResult] = []
        for start in range(0, len(emails), _NP6_RECIPIENTS_PER_BATCH):
            chunk = emails[start : start + _NP6_RECIPIENTS_PER_BATCH]
            body = [{"recipient": {"type": "unicity", "value": [email]}} for email in chunk]
            raw_results = await self._client.execute_recipients(action_id, body)
            results.extend(_normalize_execution_result(r) for r in raw_results)
        return results

    # --------------------------------------------------------------- send (ad-hoc)

    async def send(self, request: EmailSendRequest) -> EmailSendResult:
        """Single-shot ad-hoc send used by the smoke-test CLI and test-send.

        Composes the full validated workflow in one call:
        create_action → validate_action → upsert_target (per recipient) →
        execute_to_recipients. Returns the first successful message id, or
        the first error encountered.
        """
        try:
            action_id = await self.create_action(
                name=f"adhoc-{datetime.now(UTC).isoformat()}",
                subject=request.subject,
                html=request.html,
            )
            await self.validate_action(
                action_id,
                bat_test_segment_id=self._settings.NP6_BAT_TEST_SEGMENT_ID,
            )
            emails = [str(r) for r in request.recipients]
            for email in emails:
                await self.upsert_target(email)
            results = await self.execute_to_recipients(action_id, emails)

            failed = next((r for r in results if not r.success), None)
            if failed is not None:
                return EmailSendResult(
                    success=False,
                    error=failed.error_type or "execution failed",
                    provider_response=failed.raw,
                )
            first = results[0] if results else None
            return EmailSendResult(
                success=True,
                message_id=first.message_id if first else None,
                provider_response={"action_id": action_id},
            )
        except httpx.HTTPError as exc:
            logger.warning(
                "NP6 ad-hoc send failed",
                extra={"error": str(exc), "tags": dict(request.tags)},
            )
            return EmailSendResult(success=False, error=str(exc))

    # -------------------------------------------------------------------- events

    async def pull_events(self, *, since: datetime, until: datetime) -> list[EmailDeliveryEvent]:
        raw_events = await self._client.list_events(
            start=_to_np6_timestamp(since),
            end=_to_np6_timestamp(until),
            sort="asc",
        )
        normalized: list[EmailDeliveryEvent] = []
        for raw in raw_events:
            try:
                normalized.append(_normalize_event(raw))
            except ValueError as exc:
                logger.info(
                    "NP6 event skipped (unknown shape)",
                    extra={"error": str(exc), "raw_id": raw.get("id")},
                )
        return normalized


# ============================================================================
# Normalization helpers — pure functions, easy to unit-test in isolation.
# ============================================================================


def _normalize_execution_result(raw: dict[str, Any]) -> ExecutionResult:
    """Map NP6 `ExecutionResult#Discriminable` to our generic shape."""
    status_type = (raw.get("type") or "").lower()
    success = status_type == "success"
    recipient = raw.get("recipient") or {}
    return ExecutionResult(
        success=success,
        message_id=str(raw["id"]) if success and raw.get("id") else None,
        target_id=str(recipient.get("id")) if recipient.get("id") else None,
        unicity=str(recipient.get("unicity")) if recipient.get("unicity") else None,
        error_type=(raw.get("value") or {}).get("type") if not success else None,
        raw=raw,
    )


def _normalize_event(raw: dict[str, Any]) -> EmailDeliveryEvent:
    """Map a NP6 `BaseEvent#Discriminable` to our `EmailDeliveryEvent`.

    Discriminator on `type`:
    - `bounce` with `analysis.verdict in {hard, soft}` → bounce_hard / bounce_soft
    - `complaint` → complaint
    - `delivery` → delivered
    - `hit` with `link.type in {open, redirection, unsubscribe}` → open / click / unsubscribe
    """
    event_type_raw = (raw.get("type") or "").lower()
    source_detail = (raw.get("source") or {}).get("detail") or {}

    normalized_type: EmailDeliveryEventType
    if event_type_raw == "bounce":
        verdict = (source_detail.get("bounce") or {}).get("analysis", {}).get("verdict", "").lower()
        if verdict == "hard":
            normalized_type = "bounce_hard"
        elif verdict == "soft":
            normalized_type = "bounce_soft"
        else:
            raise ValueError(f"NP6 bounce with unknown verdict: {verdict!r}")
    elif event_type_raw == "complaint":
        normalized_type = "complaint"
    elif event_type_raw in {"delivery", "delivered"}:
        normalized_type = "delivered"
    elif event_type_raw == "hit":
        link_type = (source_detail.get("link") or {}).get("type", "").lower()
        if link_type == "open":
            normalized_type = "open"
        elif link_type == "redirection":
            normalized_type = "click"
        elif link_type == "unsubscribe":
            normalized_type = "unsubscribe"
        else:
            raise ValueError(f"NP6 hit with unknown link type: {link_type!r}")
    else:
        raise ValueError(f"Unknown NP6 event type: {event_type_raw!r}")

    activation = source_detail.get("activation") or {}
    receiver = activation.get("receiver") or {}
    stamp = activation.get("stamp") or {}

    email = activation.get("recipient") or receiver.get("unicity")
    if not email:
        raise ValueError(f"NP6 event {raw.get('id')} missing recipient email (activation.recipient / receiver.unicity)")

    event_id = raw.get("id")
    if not event_id:
        raise ValueError(f"NP6 event missing id: {raw!r}")

    return EmailDeliveryEvent(
        event_id=str(event_id),
        message_id=str(stamp.get("id")) if stamp.get("id") else None,
        event_type=normalized_type,
        email=email,
        timestamp=_parse_np6_timestamp(raw.get("timestamp")),
        metadata={k: v for k, v in raw.items() if k not in {"id", "type", "timestamp", "source"}},
    )


def _parse_np6_timestamp(value: Any) -> datetime:
    """Parse an NP6 event timestamp (unix-ms per spec examples; ISO accepted).

    Raises `ValueError` on missing or unparseable input — the caller
    (`pull_events`) catches it and skips the event. A silent `datetime.now()`
    fallback here would break poller idempotency: the same malformed event
    would land with a new synthetic timestamp on each tick.
    """
    if value is None:
        raise ValueError("NP6 event missing timestamp")
    if isinstance(value, int | float):
        # > 1e11 means milliseconds since epoch (year 5138 in seconds).
        if value > 1e11:
            return datetime.fromtimestamp(value / 1000, tz=UTC)
        return datetime.fromtimestamp(value, tz=UTC)
    if isinstance(value, str):
        return datetime.fromisoformat(value.replace("Z", "+00:00"))
    raise ValueError(f"NP6 event timestamp has unsupported type {type(value).__name__}: {value!r}")


def _to_np6_timestamp(dt: datetime) -> str:
    """NP6 accepts unix-ms or ISO; we use ISO for human-readable logs."""
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=UTC)
    return dt.isoformat()
