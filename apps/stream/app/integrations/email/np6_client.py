"""Low-level wrapper around the NP6 REST API.

One method per NP6 endpoint we use. Authentication via the custom `X-Key`
header (NP6 OpenAPI bundle, audit 2026-04-30, live re-verified 2026-05-04).
Two distinct content types: `application/json` for action / target /
validation CRUD, and `application/vnd.np6.cm.email` for `/execution(s)`
calls.

Retry policy lives here so the orchestrator (`np6.py`) doesn't have to
reason about transient NP6 errors. 5xx responses, network errors, and
request timeouts are retried with exponential backoff (1s / 2s / 4s);
4xx surfaces immediately (client errors are not transient — in particular
409 "target already exists" is signal the orchestrator must handle).

This file is intentionally a thin transport layer — schema validation,
idempotency, and persistence of NP6 ids belong to `np6.py`.
"""

from __future__ import annotations

import asyncio
import logging
from collections.abc import Mapping
from typing import Any

import httpx

logger = logging.getLogger(__name__)

# Retry policy for transient NP6 failures (ADR-0020 §"NP6 API Contract").
_RETRY_ATTEMPTS = 3
_RETRY_BACKOFF_SECONDS = (1.0, 2.0, 4.0)
_CONNECT_TIMEOUT = 10.0
_READ_TIMEOUT = 30.0

# Custom MIME type required by NP6 for /execution(s) and /render(s) endpoints.
_NP6_EMAIL_MIME = "application/vnd.np6.cm.email"


class NP6Client:
    """Low-level NP6 transport. One method ↔ one HTTP call to NP6."""

    def __init__(
        self,
        *,
        base_url: str,
        api_key: str,
        client_factory: type[httpx.AsyncClient] = httpx.AsyncClient,
    ) -> None:
        if not base_url:
            raise ValueError("NP6Client requires a non-empty base_url")
        if not api_key:
            raise ValueError("NP6Client requires a non-empty api_key")
        self._base_url = base_url
        self._api_key = api_key
        self._client_factory = client_factory

    # ----------------------------------------------------------------- transport

    def _client(self, *, content_type: str = "application/json") -> httpx.AsyncClient:
        """Build an httpx client with the right defaults for an NP6 call.

        Tests inject a `MockTransport` by replacing `client_factory`.
        """
        return self._client_factory(
            base_url=self._base_url,
            timeout=httpx.Timeout(
                connect=_CONNECT_TIMEOUT,
                read=_READ_TIMEOUT,
                write=_READ_TIMEOUT,
                pool=_READ_TIMEOUT,
            ),
            headers={
                "X-Key": self._api_key,
                "Content-Type": content_type,
                "Accept": "application/json",
            },
        )

    async def _request(
        self,
        method: str,
        path: str,
        *,
        json_body: Any = None,
        params: Mapping[str, Any] | None = None,
        content_type: str = "application/json",
    ) -> dict[str, Any] | list[Any]:
        """Issue an NP6 request with retry-on-5xx + transport-error semantics.

        Returns the decoded JSON body on success. Raises `httpx.HTTPError`
        (or a subclass) on terminal failure. 4xx (including 409) is raised
        immediately so the caller can branch on idempotency signals.
        """
        last_exc: Exception | None = None
        for attempt in range(_RETRY_ATTEMPTS):
            try:
                async with self._client(content_type=content_type) as client:
                    response = await client.request(
                        method,
                        path,
                        json=json_body,
                        params=params,
                    )
            except (httpx.TransportError, httpx.TimeoutException) as exc:
                last_exc = exc
                logger.info(
                    "NP6 transient transport error, will retry",
                    extra={"path": path, "attempt": attempt + 1, "error": str(exc)},
                )
            else:
                if response.status_code < 500:
                    response.raise_for_status()
                    return _safe_json(response)
                last_exc = httpx.HTTPStatusError(
                    f"NP6 {method} {path} returned {response.status_code}",
                    request=response.request,
                    response=response,
                )
                logger.info(
                    "NP6 5xx, will retry",
                    extra={
                        "path": path,
                        "attempt": attempt + 1,
                        "status": response.status_code,
                    },
                )

            if attempt + 1 < _RETRY_ATTEMPTS:
                await asyncio.sleep(_RETRY_BACKOFF_SECONDS[attempt])

        # The retry loop above always either returns or assigns last_exc on
        # every failure path; if we reach here without one, the loop logic is
        # broken — raise loudly rather than relying on `assert` (stripped at -O).
        if last_exc is None:
            raise RuntimeError("NP6 retry loop exited without recording an error")
        raise last_exc

    # -------------------------------------------------------------------- actions

    async def create_action(self, payload: dict[str, Any]) -> dict[str, Any]:
        """`POST /actions` — body must be a `SubstitutionAction#Discriminable`.

        For email use: `{"type": "mailMessage", "name": ..., "settings": {...},
        "content": {"headers": {"from": {"prefix", "domain", "label"}}, "subject", "html"}}`.
        """
        result = await self._request("POST", "/actions", json_body=payload)
        if not isinstance(result, dict):
            raise ValueError(f"NP6 /actions returned non-dict body: {result!r}")
        return result

    async def update_action(self, action_id: str, payload: dict[str, Any]) -> dict[str, Any]:
        """`PUT /actions/{id}` — full replacement of the action body."""
        result = await self._request("PUT", f"/actions/{action_id}", json_body=payload)
        if not isinstance(result, dict):
            raise ValueError(f"NP6 PUT /actions/{action_id} returned non-dict body: {result!r}")
        return result

    async def get_action(self, action_id: str) -> dict[str, Any]:
        """`GET /actions/{id}` — fetch current action document (state included)."""
        result = await self._request("GET", f"/actions/{action_id}")
        if not isinstance(result, dict):
            raise ValueError(f"NP6 GET /actions/{action_id} returned non-dict body: {result!r}")
        return result

    # ----------------------------------------------------------------- validation

    async def validate_action(self, action_id: str, body: dict[str, Any]) -> dict[str, Any] | list[Any]:
        """`POST /actions/{id}/validation` — body is a ValidationSettingsModel.

        Phase 1 (BAT test): `{"fortest": true, "testSegments": [<segment_id>]}`
        Phase 2 (production): `{"fortest": false}`

        On 409 the caller treats the action as already in the target state
        (idempotent re-entry).
        """
        return await self._request(
            "POST",
            f"/actions/{action_id}/validation",
            json_body=body,
        )

    async def unvalidate_action(self, action_id: str) -> dict[str, Any] | list[Any]:
        """`DELETE /actions/{id}/validation` — revert to draft state."""
        return await self._request(
            "DELETE",
            f"/actions/{action_id}/validation",
            json_body={},
        )

    # -------------------------------------------------------------------- targets

    async def create_target(self, payload: dict[str, Any]) -> dict[str, Any]:
        """`POST /targets` — provision a contact.

        IMPORTANT: NP6 `POST /targets` body uses field NAMES at the top
        level (e.g. `{"Email": "alice@…"}`), NOT the nested `{"fields":
        {"<id>": "value"}}` shape returned by GET. Asymmetric on purpose;
        confirmed live 2026-05-04.

        Returns the created target document. Raises `httpx.HTTPStatusError`
        on 409 — caller decides whether 409 = "already exists" idempotent
        success or a real conflict.
        """
        result = await self._request("POST", "/targets", json_body=payload)
        if not isinstance(result, dict):
            raise ValueError(f"NP6 /targets returned non-dict body: {result!r}")
        return result

    async def find_target_by_unicity(self, unicity: str) -> dict[str, Any] | None:
        """`GET /targets?unicity=<value>` — best-effort lookup by primary key.

        Returns the first matching target or None. NP6 enforces uniqueness
        on the unicity field (the field flagged `isUnicity: true`, by tenant
        config the email field). One result expected when present.
        """
        result = await self._request("GET", "/targets", params={"unicity": unicity})
        if isinstance(result, list) and result:
            first = result[0]
            if isinstance(first, dict):
                return first
        if isinstance(result, dict) and result.get("id"):
            return result
        return None

    # ----------------------------------------------------------------- executions

    async def execute_recipient(
        self,
        action_id: str,
        body: dict[str, Any],
    ) -> dict[str, Any]:
        """`POST /actions/{id}/execution` (singular).

        `body` follows the `ExecutionEmailRequest` shape:
        `{"recipient": {"type": "unicity"|"id"|"hash"|"footprint", "value": ...}}`.
        For `unicity` the value MUST be a string array.
        """
        result = await self._request(
            "POST",
            f"/actions/{action_id}/execution",
            json_body=body,
            content_type=_NP6_EMAIL_MIME,
        )
        if not isinstance(result, dict):
            raise ValueError(f"NP6 /actions/{action_id}/execution returned non-dict body: {result!r}")
        return result

    async def execute_recipients(
        self,
        action_id: str,
        body: list[dict[str, Any]],
    ) -> list[dict[str, Any]]:
        """`POST /actions/{id}/executions` (plural, batch).

        `body` is an array of `ExecutionEmailRequest` items; the response is
        an array of `ExecutionResult` items in the same order. NP6 imposes
        no documented per-call cap; callers chunk as they see fit.
        """
        result = await self._request(
            "POST",
            f"/actions/{action_id}/executions",
            json_body=body,
            content_type=_NP6_EMAIL_MIME,
        )
        if isinstance(result, list):
            return [r for r in result if isinstance(r, dict)]
        raise ValueError(f"NP6 /actions/{action_id}/executions returned non-list body: {result!r}")

    # --------------------------------------------------------------------- events

    async def list_events(
        self,
        *,
        start: str,
        end: str,
        sort: str = "asc",
    ) -> list[dict[str, Any]]:
        """`GET /actions/events?start=...&end=...&sort=asc` — pull-based feedback.

        `start` and `end` are passed verbatim; the spec accepts either ISO
        dates or unix-ms timestamps. Returns the raw event list — the
        orchestrator normalizes each entry into `EmailDeliveryEvent`.
        """
        result = await self._request(
            "GET",
            "/actions/events",
            params={"start": start, "end": end, "sort": sort},
        )
        if isinstance(result, list):
            return [r for r in result if isinstance(r, dict)]
        raise ValueError(f"NP6 /actions/events returned non-list body: {result!r}")


def _safe_json(response: httpx.Response) -> dict[str, Any] | list[Any]:
    """Decode JSON body, returning an empty dict on empty 2xx responses."""
    if not response.content:
        return {}
    try:
        decoded: Any = response.json()
    except ValueError:
        return {}
    if isinstance(decoded, dict | list):
        return decoded
    return {}
