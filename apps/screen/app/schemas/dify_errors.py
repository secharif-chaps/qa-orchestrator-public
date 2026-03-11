"""Schemas for Dify error callback handling.

⚠️ NOTE ON ERROR TYPES:
This module defines internal categorized error types for consistent handling.
Actual Dify error types are mapped to these categories in dify_error_config.py.

✅ CONFIRMED ERRORS (observed in production):
- InvokeRateLimitError → RATE_LIMIT_LLM

⚠️ PLACEHOLDER CATEGORIES (not yet observed in production):
- All other error types are anticipated but not yet confirmed
- These will be updated as real Dify errors are detected
"""
from enum import Enum
from typing import Any, Optional

from pydantic import BaseModel, ConfigDict, Field


class DifyErrorType(str, Enum):
    """Internal categorized error types.

    These are our internal categories. Actual Dify error types (like
    'InvokeRateLimitError') are mapped to these categories in ERROR_TYPE_MAPPING.
    """

    # Rate limiting errors
    # ✅ RATE_LIMIT_LLM is CONFIRMED (maps from InvokeRateLimitError)
    RATE_LIMIT_LLM = "rate_limit_llm"
    # ⚠️ RATE_LIMIT_API is PLACEHOLDER
    RATE_LIMIT_API = "rate_limit_api"

    # ⚠️ Authentication errors - PLACEHOLDER
    AUTH_INVALID_KEY = "auth_invalid_key"
    AUTH_EXPIRED = "auth_expired"

    # ⚠️ Workflow errors - PLACEHOLDER
    WORKFLOW_TIMEOUT = "workflow_timeout"
    WORKFLOW_NODE_ERROR = "workflow_node_error"
    WORKFLOW_VALIDATION_ERROR = "workflow_validation_error"

    # ⚠️ Data errors - PLACEHOLDER
    DATA_INVALID_INPUT = "data_invalid_input"
    DATA_MISSING_FIELD = "data_missing_field"

    # ⚠️ External service errors - PLACEHOLDER
    EXTERNAL_SERVICE_UNAVAILABLE = "external_service_unavailable"
    EXTERNAL_API_ERROR = "external_api_error"

    # ⚠️ Network errors - PLACEHOLDER
    NETWORK_TIMEOUT = "network_timeout"
    NETWORK_CONNECTION_ERROR = "network_connection_error"

    # Unknown/generic errors (fallback)
    UNKNOWN = "unknown"


class DifyErrorDetail(BaseModel):
    """Individual error detail from Dify callback message list."""

    error_type: str = Field(..., description="Type of error (e.g., 'rate_limit_llm')")
    error_message: str = Field(..., description="Human-readable error message")

    # Optional fields that might be present depending on error type
    retry_after: Optional[int] = Field(None, description="Seconds to wait before retry (for rate limits)")
    retry_after_ms: Optional[int] = Field(None, description="Milliseconds to wait before retry")
    node_id: Optional[str] = Field(None, description="Workflow node that failed")
    field_name: Optional[str] = Field(None, description="Field that caused validation error")
    service_name: Optional[str] = Field(None, description="External service name")
    http_status: Optional[int] = Field(None, description="HTTP status code if applicable")

    # Catch-all for any other attributes
    extra_data: dict[str, Any] = Field(default_factory=dict)

    model_config = ConfigDict(extra="allow")

    def __init__(self, **data):
        """Custom init to move unknown fields to extra_data."""
        known_fields = {
            "error_type", "error_message", "retry_after", "retry_after_ms",
            "node_id", "field_name", "service_name", "http_status", "extra_data"
        }
        extra = {k: v for k, v in data.items() if k not in known_fields}

        # Move extra fields to extra_data
        if extra:
            data["extra_data"] = {**extra, **data.get("extra_data", {})}
            for k in extra:
                data.pop(k, None)

        super().__init__(**data)


class DifyErrorResponse(BaseModel):
    """Full Dify error callback response format."""

    status: str = Field(..., description="Should be 'failed' for errors")
    message: list[DifyErrorDetail] = Field(..., description="List of error details")

    # Optional metadata
    workflow_run_id: Optional[str] = Field(None, description="Dify workflow run ID")
    task_id: Optional[str] = Field(None, description="Task ID from callback")
    timestamp: Optional[str] = Field(None, description="Error timestamp")


class ParsedError(BaseModel):
    """Processed error for internal use after parsing and categorization."""

    # Original error details
    original_type: str = Field(..., description="Original error_type from Dify")
    original_message: str = Field(..., description="Original error_message from Dify")

    # Categorized type
    categorized_type: DifyErrorType = Field(..., description="Categorized error type")

    # User-facing message (localized and contextualized)
    user_message: str = Field(..., description="User-friendly error message")

    # Technical details for logging
    technical_details: dict[str, Any] = Field(
        default_factory=dict,
        description="Technical details for debugging"
    )

    # Retry information if applicable
    retry_after_seconds: Optional[int] = Field(
        None,
        description="Seconds to wait before retry"
    )

    # Whether this error should be shown to the user
    should_display: bool = Field(
        True,
        description="Whether to display this error to the user"
    )

    # Whether this error is recoverable
    is_recoverable: bool = Field(
        False,
        description="Whether the task can be retried automatically"
    )


class ErrorHandlingResult(BaseModel):
    """Result of error handling process."""

    # Parsed errors
    errors: list[ParsedError] = Field(..., description="List of processed errors")

    # Primary error (most important one to show)
    primary_error: ParsedError = Field(..., description="Main error to display")

    # Summary for logging
    error_summary: str = Field(..., description="Brief summary of all errors")

    # Whether any error is in the whitelist
    has_whitelisted_error: bool = Field(
        False,
        description="True if any error is in the whitelist"
    )

    # Recommended action
    recommended_action: Optional[str] = Field(
        None,
        description="Recommended action for the user or system"
    )
