"""Async HTTP client for consuming tokens via the Global Service internal API.

Calls POST /internal/organizations/{org_id}/tokens/consume on the gateway.
Handles 200 (success), 402 (insufficient tokens), 403 (module not enabled),
and network/timeout errors.
"""

from __future__ import annotations

from dataclasses import dataclass
from enum import StrEnum

import httpx

from app.core.config import settings
from app.core.internal_jwt import InternalJWTError, create_internal_token
from app.core.logging_config import get_logger
from app.models.stream import ChannelType

logger = get_logger(__name__)

# HTTP timeout for token API calls (seconds)
_TOKEN_API_TIMEOUT = 10


class TokenErrorCode(StrEnum):
    """Classifies why a token consumption failed."""

    INSUFFICIENT_TOKENS = "insufficient_tokens"
    MODULE_NOT_ENABLED = "module_not_enabled"
    NETWORK_ERROR = "network_error"
    AUTH_ERROR = "auth_error"
    UNEXPECTED_ERROR = "unexpected_error"


@dataclass(frozen=True, slots=True)
class TokenConsumeResult:
    """Result of a token consumption attempt.

    Attributes:
        success: Whether tokens were consumed successfully.
        balance: Remaining balance after consumption (None on error).
        error: Human-readable error description (None on success).
        error_code: Structured error code for branching logic.
    """

    success: bool
    balance: int | None = None
    error: str | None = None
    error_code: TokenErrorCode | None = None


def get_credit_cost(channel_type: ChannelType) -> int:
    """Return the credit cost for a dispatch on the given channel type.

    Costs are configured via environment variables with ADR-0020 defaults:
    - Teams / Slack: 5 credits
    - Webhook: 2 credits
    """
    cost_map: dict[ChannelType, int] = {
        ChannelType.TEAMS: settings.STREAM_COST_TEAMS,
        ChannelType.SLACK_WEBHOOK: settings.STREAM_COST_SLACK,
        ChannelType.WEBHOOK: settings.STREAM_COST_WEBHOOK,
    }
    return cost_map.get(channel_type, settings.STREAM_COST_WEBHOOK)


class TokenClient:
    """Consumes tokens from the organization balance via the Global Service.

    Thread-safe: each call creates its own ``httpx.AsyncClient``.
    """

    def __init__(self, base_url: str) -> None:
        self._base_url = base_url.rstrip("/")

    async def consume(
        self,
        *,
        org_id: str,
        amount: int,
        reference_id: str,
        user_id: str,
        username: str,
    ) -> TokenConsumeResult:
        """Ask the Global Service to deduct *amount* credits.

        Args:
            org_id: Keycloak organization UUID.
            amount: Number of credits to consume (must be > 0).
            reference_id: Delivery or event ID for the audit trail.
            user_id: Keycloak user UUID that owns the stream.
            username: Username for the internal JWT.

        Returns:
            A ``TokenConsumeResult`` indicating success or categorised failure.
        """
        url = f"{self._base_url}/internal/organizations/{org_id}/tokens/consume"

        try:
            token = create_internal_token(
                user_id=user_id,
                username=username,
                org_id=org_id,
            )
        except InternalJWTError as exc:
            logger.error("Cannot create internal JWT for token consumption", extra={"error": str(exc)})
            return TokenConsumeResult(
                success=False,
                error=f"JWT creation failed: {exc}",
                error_code=TokenErrorCode.AUTH_ERROR,
            )

        payload = {
            "amount": amount,
            "module_name": "stream",
            "reference_type": "dispatch",
            "reference_id": reference_id,
            "created_by": user_id,
        }

        try:
            async with httpx.AsyncClient(timeout=_TOKEN_API_TIMEOUT) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers={"Authorization": f"Internal {token}"},
                )
        except httpx.TimeoutException:
            logger.warning("Token API call timed out", extra={"org_id": org_id, "amount": amount})
            return TokenConsumeResult(
                success=False,
                error="Token API request timed out",
                error_code=TokenErrorCode.NETWORK_ERROR,
            )
        except httpx.HTTPError as exc:
            logger.warning(
                "Token API network error",
                extra={"org_id": org_id, "amount": amount, "error": str(exc)},
            )
            return TokenConsumeResult(
                success=False,
                error=f"Token API network error: {exc}",
                error_code=TokenErrorCode.NETWORK_ERROR,
            )

        if response.status_code == 200:
            body = response.json()
            logger.info(
                "Tokens consumed successfully",
                extra={"org_id": org_id, "amount": amount, "balance": body.get("balance")},
            )
            return TokenConsumeResult(success=True, balance=body.get("balance"))

        if response.status_code == 402:
            body = response.json()
            detail = body.get("detail", {})
            logger.warning(
                "Insufficient tokens for dispatch",
                extra={
                    "org_id": org_id,
                    "required": amount,
                    "current_balance": detail.get("current_balance"),
                },
            )
            return TokenConsumeResult(
                success=False,
                balance=detail.get("current_balance"),
                error=detail.get("message", "Insufficient tokens"),
                error_code=TokenErrorCode.INSUFFICIENT_TOKENS,
            )

        if response.status_code == 403:
            body = response.json()
            detail = body.get("detail", {})
            message = detail.get("message", detail) if isinstance(detail, dict) else str(detail)
            logger.warning(
                "Module not enabled for token consumption",
                extra={"org_id": org_id, "detail": message},
            )
            return TokenConsumeResult(
                success=False,
                error=f"Module not enabled: {message}",
                error_code=TokenErrorCode.MODULE_NOT_ENABLED,
            )

        # Unexpected status code
        logger.error(
            "Unexpected response from token API",
            extra={"org_id": org_id, "status_code": response.status_code, "body": response.text[:500]},
        )
        return TokenConsumeResult(
            success=False,
            error=f"Unexpected status {response.status_code}",
            error_code=TokenErrorCode.UNEXPECTED_ERROR,
        )


def create_token_client() -> TokenClient | None:
    """Factory: return a ``TokenClient`` if ``GLOBAL_SERVICE_URL`` is configured, else ``None``."""
    url = settings.GLOBAL_SERVICE_URL
    if not url:
        logger.info("GLOBAL_SERVICE_URL not set — token consumption disabled")
        return None
    return TokenClient(base_url=url)
