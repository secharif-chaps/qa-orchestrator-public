"""Configuration for Dify error handling."""
from typing import Dict, Set
from app.schemas.dify_errors import DifyErrorType


# ============================================================================
# ERROR WHITELIST
# ============================================================================
# Errors in this whitelist receive special handling (custom messages, retry logic, etc.)
# Errors NOT in the whitelist are still logged but handled generically

WHITELISTED_ERROR_TYPES: Set[str] = {
    # Rate limiting - most common, needs special handling
    "rate_limit_llm",
    "rate_limit_api",
    "rate_limit",

    # Authentication issues - need immediate attention
    "auth_invalid_key",
    "auth_expired",
    "authentication_error",

    # Workflow timeouts - can be retried
    "workflow_timeout",
    "timeout",

    # External service issues - transient, can retry
    "external_service_unavailable",
    "external_api_error",
    "service_unavailable",

    # Network errors - transient
    "network_timeout",
    "connection_error",
    "network_error",

    # Input validation - needs user action
    "data_invalid_input",
    "validation_error",
    "invalid_input",
}


# ============================================================================
# ERROR TYPE MAPPING
# ============================================================================
# Maps Dify error types to our internal categorized types

ERROR_TYPE_MAPPING: Dict[str, DifyErrorType] = {
    # Rate limiting
    "rate_limit_llm": DifyErrorType.RATE_LIMIT_LLM,
    "rate_limit_api": DifyErrorType.RATE_LIMIT_API,
    "rate_limit": DifyErrorType.RATE_LIMIT_API,
    "rate_limited": DifyErrorType.RATE_LIMIT_API,

    # Authentication
    "auth_invalid_key": DifyErrorType.AUTH_INVALID_KEY,
    "auth_expired": DifyErrorType.AUTH_EXPIRED,
    "authentication_error": DifyErrorType.AUTH_INVALID_KEY,
    "unauthorized": DifyErrorType.AUTH_INVALID_KEY,

    # Workflow errors
    "workflow_timeout": DifyErrorType.WORKFLOW_TIMEOUT,
    "timeout": DifyErrorType.WORKFLOW_TIMEOUT,
    "workflow_node_error": DifyErrorType.WORKFLOW_NODE_ERROR,
    "node_error": DifyErrorType.WORKFLOW_NODE_ERROR,
    "workflow_validation_error": DifyErrorType.WORKFLOW_VALIDATION_ERROR,
    "validation_error": DifyErrorType.WORKFLOW_VALIDATION_ERROR,

    # Data errors
    "data_invalid_input": DifyErrorType.DATA_INVALID_INPUT,
    "invalid_input": DifyErrorType.DATA_INVALID_INPUT,
    "data_missing_field": DifyErrorType.DATA_MISSING_FIELD,
    "missing_field": DifyErrorType.DATA_MISSING_FIELD,

    # External services
    "external_service_unavailable": DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    "service_unavailable": DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    "external_api_error": DifyErrorType.EXTERNAL_API_ERROR,
    "api_error": DifyErrorType.EXTERNAL_API_ERROR,

    # Network
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

ERROR_PRIORITY: Dict[DifyErrorType, int] = {
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

RECOVERABLE_ERROR_TYPES: Set[DifyErrorType] = {
    DifyErrorType.RATE_LIMIT_LLM,
    DifyErrorType.RATE_LIMIT_API,
    DifyErrorType.WORKFLOW_TIMEOUT,
    DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE,
    DifyErrorType.NETWORK_TIMEOUT,
    DifyErrorType.NETWORK_CONNECTION_ERROR,
}
