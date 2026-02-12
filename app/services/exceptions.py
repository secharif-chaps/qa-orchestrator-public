"""Custom exceptions for global-service.

This module defines HTTP exceptions with specific status codes and
structured error responses for token and module operations.
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
        error_detail = TokenError(
            message=(
                f"Insufficient tokens. "
                f"Current balance: {current_balance}, required: {required_tokens}"
            ),
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
