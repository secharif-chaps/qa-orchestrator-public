"""WorldCheck One API client with HMAC-SHA256 authentication.

Provides async HTTP methods for screening entities against WorldCheck One
(Refinitiv/LSEG) sanctions, PEP, and adverse media databases.

The client handles:
- HMAC-SHA256 signature computation for every request
- Exponential backoff retry on HTTP 429 (rate limit)
- Structured error handling with custom exceptions
- Secure credential handling (secrets never logged)

Usage:
    client = WorldCheckClient(api_key="...", api_secret="...")
    response = await client.screen_entity("Acme Corp", EntityType.ORGANISATION, group_id)
"""

import asyncio

import httpx

from app.core.logging_config import get_logger

from .auth import build_auth_headers
from .exceptions import (
    WorldCheckAPIError,
    WorldCheckAuthError,
    WorldCheckNotFoundError,
    WorldCheckRateLimitError,
)
from .schemas import (
    CaseResult,
    EntityType,
    ScreeningRequest,
    ScreeningResponse,
    ScreeningResult,
)

logger = get_logger(__name__)

# Retry configuration for 429 rate limit
MAX_RETRIES = 3
BASE_DELAY_SECONDS = 1.0
MAX_DELAY_SECONDS = 8.0

# Default request timeout
DEFAULT_TIMEOUT_SECONDS = 30.0

# WorldCheck One API base URL
BASE_URL = "https://api.risk.lseg.com"
API_HOST = "api.risk.lseg.com"

# API base path — change here to switch all endpoints (e.g., /screening/v2 → /screening/v3)
API_BASE_PATH = "/screening/v2"


class WorldCheckClient:
    """Async HTTP client for WorldCheck One API.

    Attributes:
        api_key: WorldCheck API key (used as keyId in signatures)
        timeout: Request timeout in seconds
    """

    def __init__(self, api_key: str, api_secret: str, timeout: float = DEFAULT_TIMEOUT_SECONDS):
        """Initialize the WorldCheck client.

        Args:
            api_key: WorldCheck API key
            api_secret: WorldCheck API secret (used for HMAC, never transmitted)
            timeout: Request timeout in seconds
        """
        self._api_key = api_key
        self._api_secret = api_secret
        self.timeout = timeout

    async def _request(
        self,
        method: str,
        path: str,
        body: str = "",
    ) -> httpx.Response:
        """Make an authenticated request to the WorldCheck API with retry.

        Handles HMAC-SHA256 signing, rate limit retries, and error mapping.

        Args:
            method: HTTP method (GET, POST, etc.)
            path: API path (e.g., '/screening/v2/cases/screeningRequest')
            body: Request body as JSON string (empty for GET)

        Returns:
            httpx.Response on success

        Raises:
            WorldCheckAuthError: On HTTP 401
            WorldCheckNotFoundError: On HTTP 404
            WorldCheckRateLimitError: After retries exhausted on HTTP 429
            WorldCheckAPIError: On other non-success responses
        """
        last_exception: Exception | None = None

        for attempt in range(MAX_RETRIES):
            headers = build_auth_headers(
                method=method,
                path=path,
                api_key=self._api_key,
                api_secret=self._api_secret,
                host=API_HOST,
                body=body,
            )

            url = f"{BASE_URL}{path}"

            try:
                async with httpx.AsyncClient(timeout=self.timeout) as client:
                    if method.upper() in ("POST", "PUT", "PATCH"):
                        response = await client.request(
                            method=method,
                            url=url,
                            content=body.encode("utf-8"),
                            headers=headers,
                        )
                    else:
                        response = await client.request(
                            method=method,
                            url=url,
                            headers=headers,
                        )

                # Handle success
                if 200 <= response.status_code < 300:
                    return response

                # Handle specific error codes
                if response.status_code == 401:
                    logger.error(
                        "WorldCheck authentication failed",
                        extra={"status_code": 401, "path": path},
                    )
                    raise WorldCheckAuthError(response_body=response.text)

                if response.status_code == 404:
                    logger.warning(
                        "WorldCheck resource not found",
                        extra={"status_code": 404, "path": path},
                    )
                    raise WorldCheckNotFoundError(response_body=response.text)

                if response.status_code == 429:
                    retry_after = response.headers.get("Retry-After")
                    delay = (
                        float(retry_after) if retry_after else min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                    )
                    logger.warning(
                        f"WorldCheck rate limit hit, retrying in {delay}s",
                        extra={
                            "attempt": attempt + 1,
                            "max_retries": MAX_RETRIES,
                            "delay": delay,
                            "path": path,
                        },
                    )
                    last_exception = WorldCheckRateLimitError(
                        retry_after=int(delay),
                        response_body=response.text,
                    )
                    if attempt < MAX_RETRIES - 1:
                        await asyncio.sleep(delay)
                        continue
                    raise last_exception

                # Other errors - no retry
                logger.error(
                    "WorldCheck API error",
                    extra={
                        "status_code": response.status_code,
                        "path": path,
                        "response_body": response.text[:500],
                    },
                )
                raise WorldCheckAPIError(
                    message=f"WorldCheck API returned {response.status_code}",
                    status_code=response.status_code,
                    response_body=response.text,
                )

            except (WorldCheckAuthError, WorldCheckNotFoundError, WorldCheckAPIError):
                raise
            except WorldCheckRateLimitError:
                if attempt >= MAX_RETRIES - 1:
                    raise
            except httpx.TimeoutException as e:
                delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                logger.warning(
                    f"WorldCheck request timeout, retrying in {delay}s",
                    extra={"attempt": attempt + 1, "path": path, "delay": delay},
                )
                last_exception = e
                if attempt < MAX_RETRIES - 1:
                    await asyncio.sleep(delay)
                    continue
                raise WorldCheckAPIError(
                    message=f"WorldCheck request timed out after {MAX_RETRIES} attempts",
                    status_code=None,
                ) from e
            except httpx.HTTPError as e:
                logger.error(
                    "WorldCheck HTTP error",
                    extra={"path": path, "error": str(e)},
                )
                raise WorldCheckAPIError(
                    message=f"WorldCheck HTTP error: {e}",
                    status_code=None,
                ) from e

        # Should not reach here, but safety fallback
        if last_exception:
            raise last_exception
        raise WorldCheckAPIError(message="WorldCheck request failed after retries")

    async def screen_entity(
        self,
        name: str,
        entity_type: EntityType,
        group_id: str,
        secondary_fields: list[dict] | None = None,
    ) -> ScreeningResponse:
        """Screen an entity synchronously (create + screen + results in 1 call).

        Args:
            name: Entity name to screen
            entity_type: INDIVIDUAL or ORGANISATION
            group_id: WorldCheck group ID for the screening
            secondary_fields: Optional additional fields (DOB, nationality, etc.)

        Returns:
            ScreeningResponse with case info and match results
        """
        request = ScreeningRequest(
            groupId=group_id,
            entityType=entity_type,
            name=name,
            secondaryFields=secondary_fields,
        )

        body = request.model_dump_json(exclude_none=True)
        path = f"{API_BASE_PATH}/cases/screeningRequest"

        logger.info(
            "Screening entity via WorldCheck",
            extra={
                "entity_type": entity_type.value,
                "group_id": group_id,
                "path": path,
            },
        )

        response = await self._request("POST", path, body=body)
        data = response.json()

        # Parse the response - the API returns case + results
        results = []
        for result_data in data.get("results", []):
            results.append(
                ScreeningResult(
                    referenceId=result_data.get("referenceId", ""),
                    matchStrength=result_data.get("matchStrength", "WEAK"),
                    matchedTerm=result_data.get("matchedTerm"),
                    submittedTerm=result_data.get("submittedTerm"),
                    matchedNameType=result_data.get("matchedNameType"),
                    categories=[
                        cat.get("name", "") for cat in result_data.get("categories", []) if isinstance(cat, dict)
                    ]
                    if isinstance(result_data.get("categories"), list)
                    else result_data.get("categories", []),
                    sources=[src.get("name", "") for src in result_data.get("sources", []) if isinstance(src, dict)]
                    if isinstance(result_data.get("sources"), list)
                    else result_data.get("sources", []),
                    primaryName=result_data.get("primaryName"),
                    gender=result_data.get("gender"),
                    events=result_data.get("events"),
                )
            )

        screening_response = ScreeningResponse(
            caseSystemId=data.get("caseSystemId", ""),
            caseId=data.get("caseId"),
            name=data.get("name"),
            results=results,
            resultCount=len(results),
        )

        logger.info(
            "WorldCheck screening completed",
            extra={
                "case_system_id": screening_response.caseSystemId,
                "result_count": screening_response.resultCount,
            },
        )

        return screening_response

    @staticmethod
    def _validate_path_param(value: str, name: str) -> None:
        """Validate a path parameter contains only safe characters.

        Args:
            value: The parameter value to validate
            name: Parameter name for error messages

        Raises:
            ValueError: If the value contains path traversal or unsafe characters
        """
        if not value or not value.replace("-", "").replace("_", "").isalnum():
            raise ValueError(f"Invalid {name}: must be alphanumeric (with hyphens/underscores)")

    async def get_case_results(self, case_system_id: str) -> list[CaseResult]:
        """Get results for an existing case.

        Args:
            case_system_id: System-generated case ID

        Returns:
            List of CaseResult entries

        Raises:
            ValueError: If case_system_id contains invalid characters
        """
        self._validate_path_param(case_system_id, "case_system_id")
        path = f"{API_BASE_PATH}/cases/{case_system_id}/results"

        logger.info(
            "Fetching WorldCheck case results",
            extra={"case_system_id": case_system_id},
        )

        response = await self._request("GET", path)
        data = response.json()

        results = []
        for result_data in data.get("results", data if isinstance(data, list) else []):
            results.append(
                CaseResult(
                    resultId=result_data.get("resultId", ""),
                    referenceId=result_data.get("referenceId", ""),
                    matchStrength=result_data.get("matchStrength", "WEAK"),
                    matchedTerm=result_data.get("matchedTerm"),
                    submittedTerm=result_data.get("submittedTerm"),
                    categories=result_data.get("categories", []),
                    primaryName=result_data.get("primaryName"),
                )
            )

        return results

    async def get_groups(self) -> list[dict]:
        """Get available screening groups from WorldCheck.

        Returns:
            List of group dicts with keys like id, name, parentId, etc.
        """
        path = "/screening/v2/groups"

        logger.info("Fetching WorldCheck screening groups")

        response = await self._request("GET", path)
        return response.json()

    async def get_reference_profile(self, reference_id: str) -> dict:
        """Get detailed profile for a matched reference.

        Args:
            reference_id: Reference ID from a screening result

        Returns:
            Raw profile data as dict

        Raises:
            ValueError: If reference_id contains invalid characters
        """
        self._validate_path_param(reference_id, "reference_id")
        path = f"{API_BASE_PATH}/cases/references/{reference_id}"

        logger.info(
            "Fetching WorldCheck reference profile",
            extra={"reference_id": reference_id},
        )

        response = await self._request("GET", path)
        return response.json()
