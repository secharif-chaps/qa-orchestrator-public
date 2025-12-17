"""Token management endpoints for organization global token balance.

This module provides API endpoints for:
- GET /organizations/{id}/tokens - Get current token balance
- POST /organizations/{id}/tokens - Add tokens (admin only)
- GET /organizations/{id}/tokens/history - Get transaction history with filters
"""

from datetime import datetime
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser

from app.core.dependencies import get_token_manager
from app.core.keycloak import idp
from app.core.organization import OrganizationContext, get_user_organization
from app.models.organization import ReferenceType, TransactionType
from app.schemas.pagination import PaginatedResponse, create_pagination_meta
from app.schemas.token import (
    AddTokensRequest,
    TokenBalanceResponse,
    TokenTransactionRead,
)
from app.services.token_manager import TokenManager

router = APIRouter(
    prefix="/organizations",
    tags=["tokens"],
)


@router.get("/{organization_id}/tokens", response_model=TokenBalanceResponse)
async def get_organization_tokens(
    organization_id: str,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get current token balance for an organization.

    Organization members can view their own organization balance.
    Users with admin.organizations role can view any organization balance.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        TokenBalanceResponse with current balance

    Raises:
        403: If user lacks access to the organization
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = (
        hasattr(user, "roles") and user.roles and "admin.organizations" in user.roles
    )
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization",
        )

    balance = token_manager.get_balance(organization_id)

    return TokenBalanceResponse(
        organization_id=organization_id,
        balance=balance,
    )


@router.post("/{organization_id}/tokens", response_model=TokenBalanceResponse)
async def add_organization_tokens(
    organization_id: str,
    request: AddTokensRequest,
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Add tokens to an organization's balance.

    Requires admin.organizations role for access.
    Creates a transaction record for audit trail.

    Args:
        organization_id: Keycloak organization UUID
        request: AddTokensRequest with amount to add

    Returns:
        TokenBalanceResponse with updated balance
    """
    updated_org = token_manager.add_tokens(
        org_id=organization_id,
        amount=request.amount,
        user_id=user.sub,
    )

    return TokenBalanceResponse(
        organization_id=organization_id,
        balance=updated_org.token_balance,
    )


@router.get(
    "/{organization_id}/tokens/history",
    response_model=PaginatedResponse[TokenTransactionRead],
)
async def get_token_history(
    organization_id: str,
    transaction_type: Optional[TransactionType] = Query(
        None, description="Filter by transaction type"
    ),
    reference_type: Optional[ReferenceType] = Query(
        None, description="Filter by reference type"
    ),
    date_from: Optional[datetime] = Query(
        None, description="Filter transactions from this date"
    ),
    date_to: Optional[datetime] = Query(
        None, description="Filter transactions until this date"
    ),
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    size: int = Query(10, ge=1, le=100, description="Items per page (max 100)"),
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get token transaction history for an organization.

    Organization members can view their own organization history.
    Users with admin.organizations role can view any organization history.

    Supports filtering by:
    - transaction_type: add, consume, adjustment
    - reference_type: company, csv_import, manual, system
    - date_from/date_to: date range

    Results are paginated and ordered by most recent first.

    Args:
        organization_id: Keycloak organization UUID
        transaction_type: Optional filter by transaction type
        reference_type: Optional filter by reference type
        date_from: Optional filter for transactions from this date
        date_to: Optional filter for transactions until this date
        page: Page number (1-indexed)
        size: Number of items per page

    Returns:
        PaginatedResponse with TokenTransactionRead items in data field
    """
    # Check if user has admin.organizations role or belongs to the organization
    is_org_admin = (
        hasattr(user, "roles") and user.roles and "admin.organizations" in user.roles
    )
    is_org_member = org_context.organization_id == organization_id

    if not (is_org_admin or is_org_member):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization",
        )

    # Get transactions with filters
    transactions = token_manager.get_transaction_history(
        org_id=organization_id,
        transaction_type=transaction_type,
        reference_type=reference_type,
        date_from=date_from,
        date_to=date_to,
        page=page,
        size=size,
    )

    # Get total count for pagination
    total = token_manager.get_transaction_count(
        org_id=organization_id,
        transaction_type=transaction_type,
        reference_type=reference_type,
        date_from=date_from,
        date_to=date_to,
    )

    # Create pagination metadata using existing helper
    meta = create_pagination_meta(total=total, page=page, per_page=size)

    # Convert transactions to response schema
    transaction_data = [TokenTransactionRead.model_validate(t) for t in transactions]

    return PaginatedResponse(data=transaction_data, meta=meta)
