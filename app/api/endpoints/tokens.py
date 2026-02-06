"""Token management API endpoints.

Provides REST endpoints for managing organization token balances:
- GET /{org_id}/tokens - Get current balance
- POST /{org_id}/tokens - Add tokens (admin only)
- GET /{org_id}/tokens/history - Get transaction history
"""

from datetime import datetime
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session

from app.core.keycloak import idp, OIDCUser
from app.core.logging_config import get_logger
from app.core.organization import get_user_organization, OrganizationContext
from app.database import get_global_db
from app.models.organization import ReferenceType, TransactionType
from app.schemas.token import (
    AddTokensRequest,
    PaginatedTokenTransactionResponse,
    TokenBalanceResponse,
    TokenTransactionRead,
)
from app.services.token_manager import TokenManager

logger = get_logger(__name__)

router = APIRouter(prefix="/organizations", tags=["tokens"])


def get_token_manager(db: Session = Depends(get_global_db)) -> TokenManager:
    """FastAPI dependency to get TokenManager instance.

    Args:
        db: SQLAlchemy session for global_schema database.

    Returns:
        TokenManager instance configured with the database session.
    """
    return TokenManager(db=db)


def _verify_org_access(
    path_org_id: str,
    org_context: OrganizationContext,
    user: OIDCUser,
) -> None:
    """Verify user has access to the specified organization.

    Users can access their own organization's tokens, or admins with
    admin.organizations role can access any organization.

    Args:
        path_org_id: Organization ID from URL path
        org_context: User's organization context from JWT
        user: Current authenticated user

    Raises:
        HTTPException: 403 if user cannot access this organization
    """
    is_own_org = org_context.organization_id == path_org_id
    is_admin = "admin.organizations" in (user.roles or [])

    if not is_own_org and not is_admin:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied to this organization's tokens",
        )


@router.get(
    "/{org_id}/tokens",
    response_model=TokenBalanceResponse,
)
def get_organization_token_balance(
    org_id: str,
    org_context: OrganizationContext = Depends(get_user_organization),
    user: OIDCUser = Depends(idp.get_current_user()),
    token_manager: TokenManager = Depends(get_token_manager),
) -> TokenBalanceResponse:
    """Get current token balance for an organization.

    Users can view their own organization's balance. Admins with
    admin.organizations role can view any organization's balance.

    Args:
        org_id: Keycloak organization UUID from URL path.
        org_context: User's organization context extracted from JWT.
        user: Current authenticated user from Keycloak.
        token_manager: TokenManager service instance.

    Returns:
        TokenBalanceResponse with organization_id and current balance.

    Raises:
        HTTPException: 403 if user cannot access this organization's tokens.
    """
    _verify_org_access(org_id, org_context, user)

    balance = token_manager.get_balance(org_id)

    logger.info(
        f"Token balance queried for organization {org_id}",
        extra={
            "organization_id": org_id,
            "balance": balance,
            "user_id": user.sub,
        },
    )

    return TokenBalanceResponse(organization_id=org_id, balance=balance)


@router.post(
    "/{org_id}/tokens",
    response_model=TokenBalanceResponse,
    status_code=status.HTTP_201_CREATED,
)
def add_organization_tokens(
    org_id: str,
    request: AddTokensRequest,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
    token_manager: TokenManager = Depends(get_token_manager),
) -> TokenBalanceResponse:
    """Add tokens to an organization's balance.

    Requires admin.organizations role. Creates a transaction record
    for audit purposes.

    Args:
        org_id: Keycloak organization UUID from URL path.
        request: AddTokensRequest containing the amount to add.
        user: Current authenticated admin user from Keycloak.
        token_manager: TokenManager service instance.

    Returns:
        TokenBalanceResponse with organization_id and new balance.

    Raises:
        HTTPException: 403 if user doesn't have admin.organizations role.
    """
    org = token_manager.add_tokens(
        org_id=org_id,
        amount=request.amount,
        user_id=user.sub,
    )

    logger.info(
        f"Added {request.amount} tokens to organization {org_id}",
        extra={
            "organization_id": org_id,
            "amount": request.amount,
            "new_balance": org.token_balance,
            "admin_user_id": user.sub,
        },
    )

    return TokenBalanceResponse(
        organization_id=org_id,
        balance=org.token_balance,
    )


@router.get(
    "/{org_id}/tokens/history", response_model=PaginatedTokenTransactionResponse
)
def get_transaction_history(
    org_id: str,
    transaction_type: Optional[TransactionType] = Query(
        None, description="Filter by transaction type"
    ),
    reference_type: Optional[ReferenceType] = Query(
        None, description="Filter by reference type"
    ),
    date_from: Optional[datetime] = Query(
        None, description="Filter transactions after this date"
    ),
    date_to: Optional[datetime] = Query(
        None, description="Filter transactions before this date"
    ),
    page: int = Query(1, ge=1, description="Page number"),
    size: int = Query(50, ge=1, le=100, description="Page size"),
    org_context: OrganizationContext = Depends(get_user_organization),
    user: OIDCUser = Depends(idp.get_current_user()),
    token_manager: TokenManager = Depends(get_token_manager),
) -> PaginatedTokenTransactionResponse:
    """Get paginated token transaction history for an organization.

    Users can view their own organization's history. Admins with
    admin.organizations role can view any organization's history.

    Args:
        org_id: Keycloak organization UUID from URL path.
        transaction_type: Optional filter by transaction type (add, consume, adjustment).
        reference_type: Optional filter by reference type (company, csv_import, etc.).
        date_from: Optional filter for transactions after this datetime.
        date_to: Optional filter for transactions before this datetime.
        page: Page number for pagination (default: 1).
        size: Number of items per page (default: 50, max: 100).
        org_context: User's organization context extracted from JWT.
        user: Current authenticated user from Keycloak.
        token_manager: TokenManager service instance.

    Returns:
        PaginatedTokenTransactionResponse with items, total, page, size, and pages.

    Raises:
        HTTPException: 403 if user cannot access this organization's tokens.
    """
    _verify_org_access(org_id, org_context, user)

    # Get transactions
    transactions = token_manager.get_transaction_history(
        org_id=org_id,
        transaction_type=transaction_type,
        reference_type=reference_type,
        date_from=date_from,
        date_to=date_to,
        page=page,
        size=size,
    )

    # Get total count for pagination
    total = token_manager.get_transaction_count(
        org_id=org_id,
        transaction_type=transaction_type,
        reference_type=reference_type,
        date_from=date_from,
        date_to=date_to,
    )

    # Calculate total pages
    pages = (total + size - 1) // size if total > 0 else 0

    logger.debug(
        f"Transaction history queried for organization {org_id}",
        extra={
            "organization_id": org_id,
            "page": page,
            "size": size,
            "total": total,
            "user_id": user.sub,
        },
    )

    return PaginatedTokenTransactionResponse(
        items=[TokenTransactionRead.model_validate(t) for t in transactions],
        total=total,
        page=page,
        size=size,
        pages=pages,
    )
