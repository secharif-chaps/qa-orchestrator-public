"""Custom exceptions for global-service.

This module defines HTTP exceptions with specific status codes and
structured error responses for token and module operations.

It also defines plain Python exceptions for the token lock lifecycle
(lock/confirm/release). Those are intentionally NOT HTTPException
subclasses — the endpoint layer maps them to the appropriate HTTP
status codes so the service layer stays transport-agnostic.
"""

from fastapi import HTTPException, status
from pydantic import BaseModel, Field

from app.models.organization import ModuleName


class TokenError(BaseModel):
    """Error response for insufficient tokens."""

    error: str = Field(default="insufficient_tokens")
    message: str = Field(..., description="Human-readable error message")
    current_balance: int = Field(..., ge=0)
    required_tokens: int = Field(..., ge=0)


class InsufficientTokensException(HTTPException):
    """Exception raised when organization has insufficient tokens.

    Returns HTTP 402 Payment Required with token balance details.
    """

    def __init__(self, current_balance: int, required_tokens: int):
        # Keep attributes accessible for callers that want to re-format the
        # response detail (e.g. the lock/confirm/release endpoints).
        self.current_balance = current_balance
        self.required_tokens = required_tokens
        error_detail = TokenError(
            message=(f"Insufficient tokens. Current balance: {current_balance}, required: {required_tokens}"),
            current_balance=current_balance,
            required_tokens=required_tokens,
        )
        super().__init__(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=error_detail.model_dump(),
        )


class ModuleNotEnabledException(HTTPException):
    """Exception raised when trying to use a disabled module.

    Returns HTTP 403 Forbidden with module name.
    """

    def __init__(self, module_name: ModuleName):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Module '{module_name.value}' is not enabled for this organization",
        )


class PreferencesUpdateException(HTTPException):
    """Exception raised when user preferences update fails after retry.

    Returns HTTP 500 with a generic message. The user_id is only
    logged server-side for debugging, never exposed in the response.
    """

    def __init__(self):
        super().__init__(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to update preferences. Please try again.",
        )


# ---------------------------------------------------------------------------
# Token lock lifecycle exceptions (plain Python, mapped in the endpoint layer)
# ---------------------------------------------------------------------------


class LockNotFoundException(Exception):
    """Raised when a token lock cannot be found for the given org/lock_id pair."""

    def __init__(self, lock_id: str):
        self.lock_id = lock_id
        super().__init__(f"Token lock {lock_id} not found")


class LockExpiredException(Exception):
    """Raised when a token lock has expired and can no longer be confirmed/released.

    The service layer is expected to mark the lock as ``expired`` before
    raising this exception so callers see a consistent terminal state.
    """

    def __init__(self, lock_id: str):
        self.lock_id = lock_id
        super().__init__(f"Token lock {lock_id} has expired")


class LockNotInLockedStateException(Exception):
    """Raised when trying to confirm/release a lock that is not in the 'locked' state.

    Attributes:
        lock_id: The lock identifier that was operated on.
        current_status: The lock's current status (e.g. ``confirmed``, ``released``).
    """

    def __init__(self, lock_id: str, current_status: str):
        self.lock_id = lock_id
        self.current_status = current_status
        super().__init__(f"Token lock {lock_id} is in state '{current_status}', not 'locked'")
