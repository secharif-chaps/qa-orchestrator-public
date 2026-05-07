"""Pydantic schemas for token operations."""

from datetime import datetime
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field

from app.models.organization import ReferenceType, TokenLockStatus, TransactionType


class TokenConfigResponse(BaseModel):
    """Response schema for token configuration."""

    tokens_per_company: int = Field(..., gt=0, description="Number of tokens consumed per company creation")


class TokenBalanceResponse(BaseModel):
    """Response schema for token balance queries."""

    organization_id: str = Field(..., description="Keycloak organization UUID")
    balance: int = Field(..., ge=0, description="Current token balance")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "balance": 150,
                },
            ],
        },
    )


class AddTokensRequest(BaseModel):
    """Request schema for adding tokens to an organization."""

    amount: int = Field(..., gt=0, description="Number of tokens to add (must be positive)")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "amount": 500,
                },
            ],
        },
    )


class TokenTransactionRead(BaseModel):
    """Response schema for token transaction records."""

    id: int = Field(..., description="Transaction ID")
    organization_id: str = Field(..., description="Keycloak organization UUID")
    amount: int = Field(..., description="Token amount (negative for consumption)")
    balance_after: int = Field(..., ge=0, description="Balance after transaction")
    transaction_type: TransactionType = Field(..., description="Type of transaction")
    reference_type: ReferenceType = Field(..., description="Reference type")
    reference_id: str | None = Field(None, description="Reference entity ID")
    created_at: datetime = Field(..., description="Transaction timestamp")
    created_by: str = Field(..., description="Keycloak user ID who created the transaction")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "id": 42,
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "amount": -3,
                    "balance_after": 147,
                    "transaction_type": "consume",
                    "reference_type": "company",
                    "reference_id": "d4e5f6a7-b8c9-0123-def0-456789abcdef",
                    "created_at": "2025-03-15T10:30:00Z",
                    "created_by": "550e8400-e29b-41d4-a716-446655440000",
                },
            ],
        },
    )


class PaginatedTokenTransactionResponse(BaseModel):
    """Paginated response for token transaction history."""

    items: list[TokenTransactionRead] = Field(..., description="List of transactions")
    total: int = Field(..., ge=0, description="Total number of matching transactions")
    page: int = Field(..., ge=1, description="Current page number")
    size: int = Field(..., ge=1, description="Page size")
    pages: int = Field(..., ge=0, description="Total number of pages")


class ConsumeTokensRequest(BaseModel):
    """Request schema for consuming tokens (internal API)."""

    amount: int = Field(..., gt=0, description="Number of tokens to consume (must be positive)")
    module_name: str = Field(..., description="Module consuming the tokens (must be enabled)")
    reference_type: str = Field(..., description="Type of operation consuming tokens")
    reference_id: str | None = Field(None, description="Optional ID of the referenced entity")
    created_by: str = Field(..., description="Keycloak user ID performing the operation")
    description: str | None = Field(None, description="Optional transaction description")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "amount": 3,
                    "module_name": "screen",
                    "reference_type": "company",
                    "reference_id": "d4e5f6a7-b8c9-0123-def0-456789abcdef",
                    "created_by": "550e8400-e29b-41d4-a716-446655440000",
                    "description": "Company creation: Acme Corp",
                },
            ],
        },
    )


class ConsumeTokensResponse(BaseModel):
    """Response schema for token consumption (internal API)."""

    success: bool = Field(True, description="Always true for successful operations")
    balance: int = Field(..., ge=0, description="Remaining token balance after consumption")
    transaction: TokenTransactionRead | None = Field(None, description="Created transaction record")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "success": True,
                    "balance": 147,
                    "transaction": None,
                },
            ],
        },
    )


# ---------------------------------------------------------------------------
# Token lock lifecycle (TAR-1569): lock → confirm | release
# ---------------------------------------------------------------------------


class LockTokensRequest(BaseModel):
    """Request schema for reserving (locking) tokens (internal API)."""

    amount: int = Field(..., gt=0, description="Number of tokens to reserve")
    module_name: str = Field(..., description="Module reserving the tokens")
    correlation_id: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Unique idempotency key for this reservation",
    )
    reference_id: str | None = Field(
        None,
        description="Optional reference (e.g., delivery_id, company_id)",
    )

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "amount": 5,
                    "module_name": "screen",
                    "correlation_id": "req-7c9e6679-7425",
                    "reference_id": "company-42",
                },
            ],
        },
    )


class LockTokensResponse(BaseModel):
    """Response schema for token reservation."""

    lock_id: UUID = Field(..., description="Unique lock identifier")
    organization_id: str = Field(..., description="Organization UUID")
    amount: int = Field(..., gt=0, description="Reserved amount")
    expires_at: datetime = Field(
        ...,
        description="When the lock expires if not confirmed/released",
    )
    available_balance: int = Field(
        ...,
        ge=0,
        description=("Available balance after this reservation (= balance - sum of active locks)"),
    )
    status: TokenLockStatus = Field(..., description="Lock status")

    model_config = ConfigDict(
        from_attributes=True,
        json_schema_extra={
            "examples": [
                {
                    "lock_id": "550e8400-e29b-41d4-a716-446655440000",
                    "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                    "amount": 5,
                    "expires_at": "2026-03-04T12:00:30Z",
                    "available_balance": 95,
                    "status": "locked",
                },
            ],
        },
    )


class ConfirmLockRequest(BaseModel):
    """Request schema for confirming a token lock."""

    reference_type: str = Field(
        ...,
        description="Type of operation that consumed the tokens",
    )

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {"reference_type": "company"},
            ],
        },
    )


class ConfirmLockResponse(BaseModel):
    """Response schema for lock confirmation."""

    success: bool = Field(True, description="Always true for successful confirmation")
    lock_id: UUID = Field(..., description="Lock identifier that was confirmed")
    balance: int = Field(..., ge=0, description="Remaining token balance after debit")
    transaction: TokenTransactionRead = Field(
        ...,
        description="Transaction record created by the debit",
    )

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "success": True,
                    "lock_id": "550e8400-e29b-41d4-a716-446655440000",
                    "balance": 95,
                    "transaction": {
                        "id": 42,
                        "organization_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
                        "amount": -5,
                        "balance_after": 95,
                        "transaction_type": "consume",
                        "reference_type": "company",
                        "reference_id": "company-42",
                        "created_at": "2026-03-04T12:00:00Z",
                        "created_by": "550e8400-e29b-41d4-a716-446655440000",
                    },
                },
            ],
        },
    )


class ReleaseLockResponse(BaseModel):
    """Response schema for lock release."""

    success: bool = Field(True, description="Always true for successful release")
    lock_id: UUID = Field(..., description="Lock identifier that was released")
    status: TokenLockStatus = Field(..., description="Final lock status")

    model_config = ConfigDict(
        json_schema_extra={
            "examples": [
                {
                    "success": True,
                    "lock_id": "550e8400-e29b-41d4-a716-446655440000",
                    "status": "released",
                },
            ],
        },
    )
