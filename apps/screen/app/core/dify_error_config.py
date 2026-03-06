"""Configuration for Dify error handling."""

from app.schemas.dify_errors import DifyErrorType

# ============================================================================
# ERROR WHITELIST
# ============================================================================
# Errors in this whitelist receive special handling (custom messages, retry logic, etc.)
# Errors NOT in the whitelist are still logged but handled generically
#
# ⚠️ NOTE ON ERROR TYPES:
# - ✅ CONFIRMED: Error types that have been observed in production
# - ⚠️ PLACEHOLDER: Error types that are anticipated but not yet observed
# - Placeholders will be updated as real errors are detected

WHITELISTED_ERROR_TYPES: set[str] = {
    # ============================================================================
    # ✅ CONFIRMED ERRORS (detected in production)
    # ============================================================================

    # Rate limiting - CONFIRMED: InvokeRateLimitError from Azure OpenAI
    "invokeratelimiterror",  # Real Dify error type (case-insensitive)
    "rate_limit_llm",  # Legacy/compatibility

    # ============================================================================
    # ⚠️ PLACEHOLDER ERRORS (not yet detected - will be updated)
    # ============================================================================

    # Rate limiting - PLACEHOLDER
    "rate_limit_api",
    "rate_limit",

    # Authentication issues - PLACEHOLDER
    "auth_invalid_key",
    "auth_expired",
    "authentication_error",

    # Workflow timeouts - PLACEHOLDER
    "workflow_timeout",
    "timeout",

    # External service issues - PLACEHOLDER
    "external_service_unavailable",
    "external_api_error",
    "service_unavailable",

    # Network errors - PLACEHOLDER
    "network_timeout",
    "connection_error",
    "network_error",

    # Input validation - PLACEHOLDER
    "data_invalid_input",
    "validation_error",
    "invalid_input",
}


# ============================================================================
# ERROR TYPE MAPPING
# ============================================================================
# Maps Dify error types to our internal categorized types
#
# ⚠️ NOTE: Error types are matched case-insensitively
# ✅ CONFIRMED mappings are based on observed production errors
# ⚠️ PLACEHOLDER mappings are anticipated but not yet confirmed

ERROR_TYPE_MAPPING: dict[str, DifyErrorType] = {
    # ============================================================================
    # ✅ CONFIRMED MAPPINGS
    # ============================================================================

    # Rate limiting - CONFIRMED: InvokeRateLimitError from Dify
    "invokeratelimiterror": DifyErrorType.RATE_LIMIT_LLM,  # Real Dify error type

    # ============================================================================
    # ⚠️ PLACEHOLDER MAPPINGS (compatibility/future)
    # ============================================================================

    # Rate limiting - PLACEHOLDER
    "rate_limit_llm": DifyErrorType.RATE_LIMIT_LLM,
    "rate_limit_api": DifyErrorType.RATE_LIMIT_API,
    "rate_limit": DifyErrorType.RATE_LIMIT_API,
    "rate_limited": DifyErrorType.RATE_LIMIT_API,

    # Authentication - PLACEHOLDER
    "auth_invalid_key": DifyErrorType.AUTH_INVALID_KEY,
    "auth_expired": DifyErrorType.AUTH_EXPIRED,
    "authentication_error": DifyErrorType.AUTH_INVALID_KEY,
    "unauthorized": DifyErrorType.AUTH_INVALID_KEY,

    # Workflow errors - PLACEHOLDER
    "workflow_timeout": DifyErrorType.WORKFLOW_TIMEOUT,
    "timeout": DifyErrorType.WORKFLOW_TIMEOUT,
    "workflow_node_error": DifyErrorType.WORKFLOW_NODE_ERROR,
    "node_error": DifyErrorType.WORKFLOW_NODE_ERROR,
    "workflow_validation_error": DifyErrorType.WORKFLOW_VALIDATION_ERROR,
    "validation_error": DifyErrorType.WORKFLOW_VALIDATION_ERROR,

    # Data errors - PLACEHOLDER
    "data_invalid_input": DifyErrorType.DATA_INVALID_INPUT,
    "invalid_input": DifyErrorType.DATA_INVALID_INPUT,
    "data_missing_field": DifyErrorType.DATA_MISSING_FIELD,
    "missing_field": DifyErrorType.DATA_MISSING_FIELD,

    # External services - PLACEHOLDER
    "external_service_unavailable": DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    "service_unavailable": DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    "external_api_error": DifyErrorType.EXTERNAL_API_ERROR,
    "api_error": DifyErrorType.EXTERNAL_API_ERROR,

    # Network - PLACEHOLDER
    "network_timeout": DifyErrorType.NETWORK_TIMEOUT,
    "connection_timeout": DifyErrorType.NETWORK_TIMEOUT,
    "network_connection_error": DifyErrorType.NETWORK_CONNECTION_ERROR,
    "connection_error": DifyErrorType.NETWORK_CONNECTION_ERROR,
    "network_error": DifyErrorType.NETWORK_CONNECTION_ERROR,
}


# ============================================================================
# USER MESSAGE TEMPLATES
# ============================================================================
# NOTE: Message templates have been moved to dify_error_i18n.py for i18n support
# Import from there: from app.core.dify_error_i18n import get_message_template


# ============================================================================
# ERROR PRIORITY
# ============================================================================
# Priority order for determining which error to show as primary
# Higher number = higher priority

ERROR_PRIORITY: dict[DifyErrorType, int] = {
    # Auth errors are most critical - user can't proceed
    DifyErrorType.AUTH_INVALID_KEY: 100,
    DifyErrorType.AUTH_EXPIRED: 90,

    # Rate limits are next - user needs to wait
    DifyErrorType.RATE_LIMIT_LLM: 80,
    DifyErrorType.RATE_LIMIT_API: 75,

    # Validation errors - user needs to fix input
    DifyErrorType.WORKFLOW_VALIDATION_ERROR: 70,
    DifyErrorType.DATA_INVALID_INPUT: 65,
    DifyErrorType.DATA_MISSING_FIELD: 60,

    # Service errors - transient, can retry
    DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE: 50,
    DifyErrorType.EXTERNAL_API_ERROR: 45,

    # Workflow errors
    DifyErrorType.WORKFLOW_TIMEOUT: 40,
    DifyErrorType.WORKFLOW_NODE_ERROR: 35,

    # Network errors - usually transient
    DifyErrorType.NETWORK_TIMEOUT: 30,
    DifyErrorType.NETWORK_CONNECTION_ERROR: 25,

    # Unknown - lowest priority
    DifyErrorType.UNKNOWN: 10,
}


# ============================================================================
# RECOVERABLE ERRORS
# ============================================================================
# Errors that can potentially be retried automatically

RECOVERABLE_ERROR_TYPES: set[DifyErrorType] = {
    DifyErrorType.RATE_LIMIT_LLM,
    DifyErrorType.RATE_LIMIT_API,
    DifyErrorType.WORKFLOW_TIMEOUT,
    DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    DifyErrorType.NETWORK_TIMEOUT,
    DifyErrorType.NETWORK_CONNECTION_ERROR,
}
