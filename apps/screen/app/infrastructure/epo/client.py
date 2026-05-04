"""EPO OPS (Open Patent Services) async HTTP client.

Authenticates via OAuth2 `client_credentials` grant against
`https://ops.epo.org/3.2/auth/accesstoken`, caches the bearer token
in memory with a 60-second safety window before expiry, and retries
requests that return HTTP 403 with an EPO quota-exceeded rejection
header using exponential backoff.

The client never logs Consumer Key, Consumer Secret, the
`Authorization` header, nor the bearer token — only boolean presence
markers and HTTP status codes.

Usage:
    client = EpoClient(consumer_key="...", consumer_secret="...")
    result = await client.search_patents("Acme Corp")
"""

from __future__ import annotations

import asyncio
import base64
import time
from urllib.parse import quote

import httpx

from app.core.logging_config import get_logger

from .exceptions import (
    EpoAuthError,
    EpoError,
    EpoNotFoundError,
    EpoQuotaExceededError,
)
from .schemas import (
    PatentAbstract,
    PatentBiblio,
    PatentFamily,
    PatentLegalStatus,
    PatentSearchResult,
    parse_abstract_response,
    parse_biblio_response,
    parse_family_response,
    parse_legal_response,
    parse_search_response,
)

logger = get_logger(__name__)

BASE_URL = "https://ops.epo.org/3.2"
AUTH_URL = "https://ops.epo.org/3.2/auth/accesstoken"

MAX_RETRIES = 3
BASE_DELAY_SECONDS = 1.0
MAX_DELAY_SECONDS = 8.0
DEFAULT_TIMEOUT_SECONDS = 30.0
TOKEN_SAFETY_WINDOW_SECONDS = 60

# EPO OPS caps `published-data/search` responses at 100 hits per call. For
# large applicants (AIRBUS, SIEMENS…) the default open-ended response
# triggers HTTP 413 Payload Too Large. Sending an explicit `X-OPS-Range`
# keeps us under the cap and lets us rely on sort-by-publication-desc
# upstream to retain the most relevant page.
SEARCH_RESULT_RANGE = "1-100"

_QUOTA_REJECTION_REASONS = {
    "IndividualQuotaPerHour",
    "IndividualQuotaPerWeek",
    "RegisteredQuotaPerWeek",
}


class EpoClient:
    """Async OAuth2 client for the EPO OPS API v3.2.

    Attributes:
        timeout: Per-request timeout in seconds.
    """

    def __init__(
        self,
        consumer_key: str,
        consumer_secret: str,
        timeout: float = DEFAULT_TIMEOUT_SECONDS,
    ):
        if not consumer_key or not consumer_secret:
            raise EpoAuthError(
                message="EPO consumer_key and consumer_secret are required",
                status_code=None,
            )
        self._consumer_key = consumer_key
        self._consumer_secret = consumer_secret
        self.timeout = timeout

        self._token: str | None = None
        self._token_expires_at: float = 0.0
        self._token_lock = asyncio.Lock()

    async def _get_token(self, force_refresh: bool = False) -> str:
        """Return a valid OAuth2 bearer token, refreshing if needed.

        Uses double-checked locking so that many concurrent requests with
        an expired token only trigger a single auth round-trip.
        """
        now = time.monotonic()
        if not force_refresh and self._token is not None and now < self._token_expires_at - TOKEN_SAFETY_WINDOW_SECONDS:
            return self._token

        async with self._token_lock:
            now = time.monotonic()
            if (
                not force_refresh
                and self._token is not None
                and now < self._token_expires_at - TOKEN_SAFETY_WINDOW_SECONDS
            ):
                return self._token

            self._token = None
            token, expires_in = await self._fetch_token()
            self._token = token
            self._token_expires_at = time.monotonic() + expires_in
            logger.info(
                "EPO OAuth2 token acquired",
                extra={"has_token": True, "expires_in": expires_in},
            )
            return token

    async def _fetch_token(self) -> tuple[str, int]:
        """POST to `/auth/accesstoken` and return (token, expires_in)."""
        credentials = f"{self._consumer_key}:{self._consumer_secret}".encode()
        basic = base64.b64encode(credentials).decode()
        headers = {
            "Authorization": f"Basic {basic}",
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json",
        }

        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.post(
                    AUTH_URL,
                    headers=headers,
                    content=b"grant_type=client_credentials",
                )
        except httpx.HTTPError as exc:
            logger.error("EPO auth transport error", extra={"error": str(exc)})
            raise EpoAuthError(
                message=f"EPO auth transport error: {exc}",
                status_code=None,
            ) from exc

        if response.status_code != 200:
            logger.error(
                "EPO auth failed",
                extra={"status_code": response.status_code},
            )
            raise EpoAuthError(
                message=f"EPO auth returned HTTP {response.status_code}",
                status_code=response.status_code,
                response_body=response.text,
            )

        try:
            payload = response.json()
            token = payload["access_token"]
            expires_in = int(payload.get("expires_in", 1200))
        except (ValueError, KeyError, TypeError) as exc:
            raise EpoAuthError(
                message=f"EPO auth response missing access_token: {exc}",
                status_code=response.status_code,
            ) from exc

        return token, expires_in

    async def _request(
        self,
        method: str,
        path: str,
        params: dict[str, str] | None = None,
        extra_headers: dict[str, str] | None = None,
    ) -> httpx.Response:
        """Make an authenticated request with quota-retry and 401 recovery."""
        url = f"{BASE_URL}{path}"
        last_quota_error: EpoQuotaExceededError | None = None
        auth_retry_used = False

        for attempt in range(MAX_RETRIES):
            token = await self._get_token()
            headers = {
                "Authorization": f"Bearer {token}",
                "Accept": "application/xml",
            }
            if extra_headers:
                headers.update(extra_headers)

            try:
                async with httpx.AsyncClient(timeout=self.timeout) as client:
                    response = await client.request(
                        method=method,
                        url=url,
                        params=params,
                        headers=headers,
                    )
            except httpx.TimeoutException as exc:
                delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                logger.warning(
                    "EPO request timeout",
                    extra={"attempt": attempt + 1, "path": path, "delay": delay},
                )
                if attempt < MAX_RETRIES - 1:
                    await asyncio.sleep(delay)
                    continue
                raise EpoError(
                    message=f"EPO request timed out after {MAX_RETRIES} attempts",
                    status_code=None,
                ) from exc
            except httpx.HTTPError as exc:
                logger.error(
                    "EPO HTTP transport error",
                    extra={"path": path, "error": str(exc)},
                )
                raise EpoError(
                    message=f"EPO HTTP error: {exc}",
                    status_code=None,
                ) from exc

            status = response.status_code

            if 200 <= status < 300:
                return response

            if status == 401:
                if auth_retry_used:
                    raise EpoAuthError(
                        message="EPO rejected the bearer token after refresh",
                        status_code=401,
                        response_body=response.text,
                    )
                auth_retry_used = True
                logger.warning(
                    "EPO 401 received, invalidating cached token and retrying once",
                    extra={"path": path},
                )
                self._token = None
                self._token_expires_at = 0.0
                continue

            if status == 404:
                raise EpoNotFoundError(response_body=response.text)

            if status == 403:
                rejection = response.headers.get("X-Rejection-Reason")
                if rejection in _QUOTA_REJECTION_REASONS:
                    retry_after_raw = response.headers.get("Retry-After")
                    delay = (
                        float(retry_after_raw)
                        if retry_after_raw
                        else min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                    )
                    logger.warning(
                        "EPO quota exceeded, retrying after backoff",
                        extra={
                            "attempt": attempt + 1,
                            "path": path,
                            "delay": delay,
                            "rejection_reason": rejection,
                        },
                    )
                    last_quota_error = EpoQuotaExceededError(
                        retry_after=int(delay),
                        response_body=response.text,
                    )
                    if attempt < MAX_RETRIES - 1:
                        await asyncio.sleep(delay)
                        continue
                    raise last_quota_error

                logger.error(
                    "EPO 403 without quota header",
                    extra={"path": path, "rejection_reason": rejection},
                )
                raise EpoAuthError(
                    message="EPO denied the request (non-quota 403)",
                    status_code=403,
                    response_body=response.text,
                )

            logger.error(
                "EPO API error",
                extra={"status_code": status, "path": path},
            )
            raise EpoError(
                message=f"EPO API returned HTTP {status}",
                status_code=status,
                response_body=response.text,
            )

        if last_quota_error is not None:
            raise last_quota_error
        raise EpoError(message="EPO request failed after retries")

    @staticmethod
    def _validate_doc_id(doc_id: str) -> None:
        """Reject path-traversal attempts and malformed doc_ids.

        EPO doc_ids look like `EP.1000000.A1` or `US.2023123456.A1`;
        we accept letters, digits, dot, dash and underscore only.
        """
        if not doc_id:
            raise ValueError("doc_id must be a non-empty string")
        allowed = set("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_")
        if not set(doc_id).issubset(allowed):
            raise ValueError("doc_id contains invalid characters (allowed: letters, digits, dot, dash, underscore)")

    async def search_patents(self, applicant_name: str) -> PatentSearchResult:
        """Search published patents whose applicant name matches `applicant_name`.

        Caps the result page with the ``X-OPS-Range`` header (100 hits max —
        EPO OPS's per-call limit) and treats a 404 as "no matches", which
        EPO returns when the CQL query yields zero hits; both cases return
        an empty ``PatentSearchResult`` so the enrichment pipeline can
        persist a clean "searched, nothing found" record instead of an
        error.
        """
        if not applicant_name or not applicant_name.strip():
            raise ValueError("applicant_name must be a non-empty string")
        cql = f'pa="{applicant_name}"'
        path = f"/rest-services/published-data/search?q={quote(cql)}"
        logger.info("EPO search_patents", extra={"path": "/rest-services/published-data/search"})
        try:
            response = await self._request(
                "GET",
                path,
                extra_headers={"X-OPS-Range": SEARCH_RESULT_RANGE},
            )
        except EpoNotFoundError:
            logger.info(
                "EPO search returned 404 (no matching applicants)",
                extra={"applicant_name": applicant_name},
            )
            return PatentSearchResult(total_results=0, entries=[])
        return parse_search_response(response.content)

    async def get_biblio(self, doc_id: str) -> PatentBiblio:
        """Fetch bibliographic data for a single publication."""
        self._validate_doc_id(doc_id)
        path = f"/rest-services/published-data/publication/docdb/{doc_id}/biblio"
        logger.info("EPO get_biblio", extra={"doc_id": doc_id})
        response = await self._request("GET", path)
        return parse_biblio_response(response.content)

    async def get_abstract(self, doc_id: str) -> PatentAbstract:
        """Fetch the abstract text for a single publication."""
        self._validate_doc_id(doc_id)
        path = f"/rest-services/published-data/publication/docdb/{doc_id}/abstract"
        logger.info("EPO get_abstract", extra={"doc_id": doc_id})
        response = await self._request("GET", path)
        return parse_abstract_response(response.content)

    async def get_family(self, doc_id: str) -> PatentFamily:
        """Fetch the patent family (with biblio) for a single publication.

        The ``/biblio`` suffix asks EPO to return bibliographic data for
        every family member in the same payload, which is required to
        collect CPC classifications alongside the geographic coverage.
        """
        self._validate_doc_id(doc_id)
        path = f"/rest-services/family/publication/docdb/{doc_id}/biblio"
        logger.info("EPO get_family", extra={"doc_id": doc_id})
        response = await self._request("GET", path)
        return parse_family_response(response.content)

    async def get_legal(self, doc_id: str) -> PatentLegalStatus:
        """Fetch the legal-status history for a single publication."""
        self._validate_doc_id(doc_id)
        path = f"/rest-services/legal/publication/docdb/{doc_id}"
        logger.info("EPO get_legal", extra={"doc_id": doc_id})
        response = await self._request("GET", path)
        return parse_legal_response(response.content)
