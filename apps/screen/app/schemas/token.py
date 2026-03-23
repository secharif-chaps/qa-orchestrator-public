"""Pydantic schemas for global token system.

This module contains schemas for:
- Organization token balance management
- Token transactions (history/audit)
- Token operations (add, consume)
"""

from datetime import datetime
from enum import StrEnum

from pydantic import BaseModel, ConfigDict, Field


# Re-export enums for API use
class TransactionType(StrEnum):
    """Token transaction types."""

    add = "add"
    consume = "consume"
    adjustment = "adjustment"


class ReferenceType(StrEnum):
    """Reference types for token transactions."""

    company = "company"
    csv_import = "csv_import"
    refresh = "refresh"
    manual = "manual"
    system = "system"


# Organization schemas
class OrganizationCreate(BaseModel):
    """Schema for creating an organization record.

    Note: Organization records are typically auto-created during token
    operations (lazy initialization), but this schema supports explicit creation.
    """

    organization_id: str = Field(..., description="Keycloak organization UUID")
    token_balance: int = Field(default=0, ge=0, description="Initial token balance")


class OrganizationRead(BaseModel):
    """Schema for reading organization data."""

    organization_id: str = Field(..., description="Keycloak organization UUID")
    token_balance: int = Field(..., ge=0, description="Current token balance")
    created_at: datetime = Field(..., description="When record was created")
    updated_at: datetime | None = Field(None, description="Last update timestamp")

    model_config = ConfigDict(from_attributes=True)


class OrganizationUpdate(BaseModel):
    """Schema for updating organization data.

    Note: Token balance updates should go through TokenManager service
    to maintain transaction audit trail.
    """

    token_balance: int | None = Field(None, ge=0, description="New token balance")


# Token balance schemas
class TokenBalanceResponse(BaseModel):
    """Response schema for token balance queries.

    Simple response with just the balance and organization ID.
    """

    organization_id: str = Field(..., description="Keycloak organization UUID")
    balance: int = Field(..., ge=0, description="Current token balance")


class AddTokensRequest(BaseModel):
    """Request schema for adding tokens to an organization.

    Used by admin endpoints to add tokens to organization balance.
    """

    amount: int = Field(..., gt=0, description="Number of tokens to add (must be positive)")


# Token transaction schemas
class TokenTransactionCreate(BaseModel):
    """Schema for creating a token transaction record.

    Used internally by TokenManager service.
    """

    organization_id: str = Field(..., description="Keycloak organization UUID")
    amount: int = Field(..., description="Token amount (+/- for add/consume)")
    balance_after: int = Field(..., ge=0, description="Balance after transaction")
    transaction_type: TransactionType = Field(..., description="Type of transaction")
    reference_type: ReferenceType = Field(..., description="What triggered the transaction")
    reference_id: str | None = Field(None, description="ID of referenced entity")
    created_by: str = Field(..., description="Keycloak user ID who initiated")


class TokenTransactionRead(BaseModel):
    """Schema for reading token transaction data.

    Used in transaction history responses.
    """

    id: int = Field(..., description="Transaction ID")
    organization_id: str = Field(..., description="Keycloak organization UUID")
    amount: int = Field(..., description="Token amount (+/- for add/consume)")
    balance_after: int = Field(..., ge=0, description="Balance after transaction")
    transaction_type: TransactionType = Field(..., description="Type of transaction")
    reference_type: ReferenceType = Field(..., description="What triggered the transaction")
    reference_id: str | None = Field(None, description="ID of referenced entity")
    created_at: datetime = Field(..., description="When transaction occurred")
    created_by: str = Field(..., description="Keycloak user ID who initiated")

    model_config = ConfigDict(from_attributes=True)


# History filter schemas
class TokenHistoryFilters(BaseModel):
    """Query parameters for filtering token history.

    All filters are optional. Results are paginated.
    """

    transaction_type: TransactionType | None = Field(None, description="Filter by transaction type")
    reference_type: ReferenceType | None = Field(None, description="Filter by reference type")
    date_from: datetime | None = Field(None, description="Filter transactions from this date")
    date_to: datetime | None = Field(None, description="Filter transactions until this date")


# Error response schemas
class TokenError(BaseModel):
    """Error response for token operations."""

    error: str = Field(default="insufficient_tokens", description="Error code")
    message: str = Field(..., description="Human-readable error message")
    current_balance: int = Field(..., ge=0, description="Current token balance")
    required_tokens: int = Field(..., ge=0, description="Tokens required for operation")
