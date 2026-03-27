"""Pappers API async HTTP client.

Provides methods for searching and retrieving French company data from
the Pappers API (https://api.pappers.fr/v2).

Usage:
    client = PappersClient(api_key="...")
    results = await client.search_company("Michelin")
    if results:
        company = await client.get_company(results[0].siren)
"""

import asyncio

import httpx

from app.core.logging_config import get_logger

from .exceptions import (
    PappersAuthError,
    PappersError,
    PappersNotFoundError,
    PappersRateLimitError,
)
from .schemas import PappersCompanyData, PappersSearchResult

logger = get_logger(__name__)

BASE_URL = "https://api.pappers.fr/v2"
DEFAULT_TIMEOUT_SECONDS = 30.0
MAX_RETRIES = 3
BASE_DELAY_SECONDS = 1.0
MAX_DELAY_SECONDS = 8.0


class PappersClient:
    """Async HTTP client for Pappers French business registry API."""

    def __init__(self, api_key: str, timeout: float = DEFAULT_TIMEOUT_SECONDS):
        self._api_key = api_key
        self._timeout = timeout

    async def _request(self, method: str, path: str, params: dict | None = None) -> dict:
        """Execute an HTTP request with retry on rate limit.

        Args:
            method: HTTP method (GET)
            path: API path (e.g., "/recherche")
            params: Query parameters

        Returns:
            Parsed JSON response

        Raises:
            PappersAuthError: On 401/403
            PappersNotFoundError: On 404
            PappersRateLimitError: On 429 after retries exhausted
            PappersError: On other HTTP errors
        """
        url = f"{BASE_URL}{path}"
        params = params or {}
        params["api_token"] = self._api_key

        for attempt in range(MAX_RETRIES + 1):
            try:
                async with httpx.AsyncClient(timeout=self._timeout) as client:
                    response = await client.request(method, url, params=params)

                if response.status_code == 200:
                    return response.json()

                body = response.text
                status = response.status_code

                if status in (401, 403):
                    raise PappersAuthError(
                        f"Authentication failed: {status}",
                        status_code=status,
                        response_body=body,
                    )
                if status == 404:
                    raise PappersNotFoundError(
                        "Entity not found",
                        status_code=status,
                        response_body=body,
                    )
                if status == 429:
                    if attempt == MAX_RETRIES:
                        raise PappersRateLimitError(
                            "Rate limit exceeded after retries",
                            status_code=status,
                            response_body=body,
                        )
                    delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                    logger.warning(
                        f"Pappers rate limited, retrying in {delay:.1f}s (attempt {attempt + 1}/{MAX_RETRIES})"
                    )
                    await asyncio.sleep(delay)
                    continue

                raise PappersError(
                    f"Pappers API error: {status}",
                    status_code=status,
                    response_body=body,
                )

            except (httpx.TimeoutException, httpx.ConnectError) as e:
                if attempt == MAX_RETRIES:
                    raise PappersError(f"Pappers request failed after retries: {e}") from e
                delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                logger.warning(f"Pappers request error, retrying in {delay:.1f}s: {e}")
                await asyncio.sleep(delay)

        # Should not reach here, but satisfy type checker
        raise PappersError("Unexpected retry exhaustion")

    async def search_company(self, name: str, per_page: int = 5) -> list[PappersSearchResult]:
        """Search companies by name.

        Args:
            name: Company name to search
            per_page: Number of results per page (default 5)

        Returns:
            List of matching companies with SIREN numbers
        """
        data = await self._request("GET", "/recherche", params={"q": name, "par_page": per_page})
        raw_results = data.get("resultats", [])
        return [PappersSearchResult.model_validate(r) for r in raw_results]

    async def get_company(self, siren: str) -> PappersCompanyData:
        """Get full company data by SIREN number.

        Args:
            siren: 9-digit SIREN number

        Returns:
            Complete company data including officers and financials
        """
        data = await self._request("GET", "/entreprise", params={"siren": siren})
        return PappersCompanyData.model_validate(data)
