"""Custom exception hierarchy for MINT backend.

This module defines domain-specific exceptions that provide better error handling
and debugging capabilities compared to generic Python exceptions.

Usage:
    from app.core.exceptions import AuthorizationError, ResourceNotFoundError

    if not user.has_permission('company.create'):
        raise AuthorizationError(
            "Missing required permission",
            details={"required": "company.create", "user": user.username}
        )
"""


class MintBaseException(Exception):
    """Base exception for all MINT-specific errors.

    All custom exceptions should inherit from this base class to enable
    consistent error handling and logging across the application.

    Attributes:
        message: Human-readable error message
        details: Optional dictionary with additional context for debugging
    """

    def __init__(self, message: str, details: dict | None = None):
        self.message = message
        self.details = details or {}
        super().__init__(self.message)


class AuthenticationError(MintBaseException):
    """Raised when authentication fails.

    Examples:
        - Invalid credentials
        - Expired token
        - Missing authentication header
    """

    pass


class AuthorizationError(MintBaseException):
    """Raised when user lacks required permissions.

    Examples:
        - User missing required role
        - User accessing resource outside their workspace
        - Permission denied for specific action
    """

    pass


class ValidationError(MintBaseException):
    """Raised when input validation fails.

    Examples:
        - Invalid email format
        - Required field missing
        - Value out of acceptable range

    Note: This should be used for business logic validation.
    Pydantic handles schema validation automatically.
    """

    pass


class ResourceNotFoundError(MintBaseException):
    """Raised when requested resource doesn't exist.

    Examples:
        - Company not found by ID
        - Workspace not found
        - User not found in Keycloak
    """

    pass


class DatabaseError(MintBaseException):
    """Raised when database operations fail.

    Examples:
        - Connection timeout
        - Constraint violation
        - Transaction rollback
    """

    pass


class ExternalServiceError(MintBaseException):
    """Raised when external service (Dify, n8n, Keycloak) fails.

    Examples:
        - Dify workflow execution timeout
        - Keycloak API unavailable
        - n8n webhook failure
    """

    pass
