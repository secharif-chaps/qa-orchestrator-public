"""Shared error response schemas for OpenAPI documentation.

These schemas document the error response format used across all endpoints.
They do NOT change endpoint behavior — they only enrich the OpenAPI spec.
"""

from pydantic import BaseModel, ConfigDict, Field


class ErrorDetail(BaseModel):
    """Standard error response returned by all endpoints on failure."""

    detail: str = Field(..., description="Human-readable error message")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [{"detail": "Not authenticated"}],
        },
    )


# ---------------------------------------------------------------------------
# Reusable response dicts — spread into endpoint decorators:
#   @router.get("/...", responses={**COMMON_RESPONSES})
# ---------------------------------------------------------------------------

COMMON_RESPONSES: dict[int, dict] = {
    401: {
        "description": "Not authenticated — missing or invalid Bearer token",
        "model": ErrorDetail,
    },
    403: {
        "description": "Insufficient permissions for this operation",
        "model": ErrorDetail,
    },
    422: {
        "description": "Validation error — invalid request parameters or body",
    },
}

NOT_FOUND_RESPONSE: dict[int, dict] = {
    404: {
        "description": "Resource not found",
        "model": ErrorDetail,
    },
}

RATE_LIMIT_RESPONSE: dict[int, dict] = {
    429: {
        "description": "Too many requests — rate limit exceeded",
        "model": ErrorDetail,
    },
}

INSUFFICIENT_TOKENS_RESPONSE: dict[int, dict] = {
    402: {
        "description": "Insufficient tokens to perform this operation",
        "model": ErrorDetail,
    },
}

LOCK_NOT_FOUND_RESPONSE: dict[int, dict] = {
    404: {
        "description": "Token lock not found for this organization",
        "model": ErrorDetail,
    },
}

LOCK_CONFLICT_RESPONSE: dict[int, dict] = {
    409: {
        "description": ("Token lock cannot be modified (already confirmed/released, or expired)"),
        "model": ErrorDetail,
    },
}

# For internal-only endpoints (internal JWT, not user-facing Keycloak JWT)
INTERNAL_RESPONSES: dict[int, dict] = {
    401: {
        "description": "Invalid or missing internal JWT token",
        "model": ErrorDetail,
    },
    403: {
        "description": "Forbidden — organization mismatch or module not enabled",
        "model": ErrorDetail,
    },
}
