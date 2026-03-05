"""Pydantic schemas for token operations."""

from datetime import datetime

from pydantic import BaseModel, Field

from app.models.organization import ReferenceType, TransactionType


class TokenBalanceResponse(BaseModel):
    """Response schema for token balance queries."""

    organization_id: str = Field(..., description="Keycloak organization UUID")
    balance: int = Field(..., ge=0, description="Current token balance")

    model_config = {"from_attributes": True}


class AddTokensRequest(BaseModel):
    """Request schema for adding tokens to an organization."""

    amount: int = Field(
        ..., gt=0, description="Number of tokens to add (must be positive)"
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
    created_by: str = Field(
        ..., description="Keycloak user ID who created the transaction"
    )

    model_config = {"from_attributes": True}


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


class ConsumeTokensResponse(BaseModel):
    """Response schema for token consumption (internal API)."""

    success: bool = Field(True, description="Always true for successful operations")
    balance: int = Field(..., ge=0, description="Remaining token balance after consumption")
    transaction: TokenTransactionRead | None = Field(None, description="Created transaction record")
