"""Pydantic schemas for token operations."""

from pydantic import BaseModel, Field


class TokenError(BaseModel):
    """Error response for token operations."""

    error: str = Field(default="insufficient_tokens", description="Error code")
    message: str = Field(..., description="Human-readable error message")
    current_balance: int = Field(..., ge=0, description="Current token balance")
    required_tokens: int = Field(..., ge=0, description="Tokens required for operation")
