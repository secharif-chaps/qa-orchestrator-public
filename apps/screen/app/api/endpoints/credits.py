"""Credit statistics endpoints for organization credit usage.

This module provides API endpoints for:
- GET /organizations/{id}/credits/stats - Credit balance and usage breakdown
- GET /organizations/{id}/credits/top-users - Top credit consuming users
- GET /organizations/{id}/credits/daily-usage - Daily credit usage time series
"""

from datetime import datetime, timedelta
from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.dependencies import get_token_manager
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.modules import MODULE_CONFIG, get_all_module_names
from app.core.organization import OrganizationContext, get_user_organization
from app.database import get_db
from app.models.organization import (
    OrganizationModule,
    ReferenceType,
    TokenTransaction,
    TransactionType,
)
from app.schemas.credits import (
    CreditStatsResponse,
    DailyCreditUsageResponse,
    DailyUsageItem,
    ModuleForecastItem,
    ModuleUsageItem,
    TopCreditUser,
    TopCreditUsersResponse,
)
from app.services.keycloak_admin import keycloak_admin_service
from app.services.token_manager import TokenManager

logger = get_logger(__name__)

router = APIRouter(
    prefix="/organizations",
    tags=["credits"],
)


# Map reference types to module names
REFERENCE_TYPE_TO_MODULE = {
    "company": "screen",
    "csv_import": "screen",  # CSV imports are also screen module
    # Future mappings:
    # "watch": "target",
    # "mapping": "explore",
}

# Map module names to reference types (for filtering)
MODULE_TO_REFERENCE_TYPES = {
    "screen": [ReferenceType.company, ReferenceType.csv_import],
    # Future:
    # "target": [ReferenceType.watch],
    # "explore": [ReferenceType.mapping],
}


def _has_credits_access(user: OIDCUser, org_context: OrganizationContext, organization_id: str) -> bool:
    """Check if user has access to credits data for the organization.

    Args:
        user: Current authenticated user
        org_context: User's organization context
        organization_id: Target organization ID

    Returns:
        True if user has access, False otherwise
    """
    # Check if user has admin.organizations role
    is_org_admin = (
        hasattr(user, "roles") and user.roles and "admin.organizations" in user.roles
    )

    # Check if user has organization.manage role and belongs to the organization
    has_manage_role = (
        hasattr(user, "roles") and user.roles and "organization.manage" in user.roles
    )
    is_org_member = org_context.organization_id == organization_id

    return is_org_admin or (has_manage_role and is_org_member)


def _get_date_range(period: str, start_date: Optional[datetime], end_date: Optional[datetime]) -> tuple[datetime, datetime]:
    """Calculate date range from period preset or custom dates.

    Args:
        period: Period preset (7d, 30d, 90d, custom)
        start_date: Custom start date (used when period is 'custom')
        end_date: Custom end date (used when period is 'custom')

    Returns:
        Tuple of (start_date, end_date)
    """
    now = datetime.utcnow()

    if period == "custom" and start_date and end_date:
        return start_date, end_date

    period_days = {
        "7d": 7,
        "30d": 30,
        "90d": 90,
    }

    days = period_days.get(period, 30)  # Default to 30 days
    return now - timedelta(days=days), now


def _get_module_enabled_status(db: Session, organization_id: str) -> dict[str, bool]:
    """Get enabled status for all modules in an organization.

    Args:
        db: Database session
        organization_id: Keycloak organization UUID

    Returns:
        Dict mapping module name to enabled status
    """
    modules = (
        db.query(OrganizationModule)
        .filter(OrganizationModule.organization_id == organization_id)
        .all()
    )

    module_status = {name: False for name in get_all_module_names()}
    for module in modules:
        if module.module_name.value in module_status:
            module_status[module.module_name.value] = module.enabled

    return module_status


@router.get("/{organization_id}/credits/stats", response_model=CreditStatsResponse)
async def get_credit_stats(
    organization_id: str,
    db: Session = Depends(get_db),
    token_manager: TokenManager = Depends(get_token_manager),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get credit statistics for an organization.

    Returns current balance, usage breakdown by module, and remaining capacity.

    Requires organization.manage role for own organization or admin.organizations for any.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        CreditStatsResponse with balance, usage breakdown, and capacity forecast
    """
    if not _has_credits_access(user, org_context, organization_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied. Requires organization.manage or admin.organizations role.",
        )

    # Get current balance
    balance = token_manager.get_balance(organization_id)

    # Get module enabled status
    module_status = _get_module_enabled_status(db, organization_id)

    # Calculate usage by module (consumption transactions only)
    # Group by reference_type to determine module usage
    consumption_query = (
        db.query(
            TokenTransaction.reference_type,
            func.sum(func.abs(TokenTransaction.amount)).label("total_consumed"),
        )
        .filter(
            TokenTransaction.organization_id == organization_id,
            TokenTransaction.transaction_type == TransactionType.consume,
        )
        .group_by(TokenTransaction.reference_type)
        .all()
    )

    # Build usage by module
    module_consumption: dict[str, int] = {name: 0 for name in get_all_module_names()}

    for ref_type, total in consumption_query:
        if ref_type:
            module_name = REFERENCE_TYPE_TO_MODULE.get(ref_type.value)
            if module_name:
                module_consumption[module_name] += total or 0

    # Calculate total consumption for percentage calculation
    total_consumption = sum(module_consumption.values())

    # Build usage_by_module response
    usage_by_module = []
    for module_name in get_all_module_names():
        config = MODULE_CONFIG.get(module_name, {})
        consumed = module_consumption.get(module_name, 0)
        percentage = (consumed / total_consumption * 100) if total_consumption > 0 else 0

        usage_by_module.append(
            ModuleUsageItem(
                module=module_name,
                label=config.get("label", module_name),
                credits_consumed=consumed,
                percentage=round(percentage, 1),
            )
        )

    # Build remaining_capacity response
    remaining_capacity = []
    for module_name in get_all_module_names():
        config = MODULE_CONFIG.get(module_name, {})
        enabled = module_status.get(module_name, False)
        cost = config.get("cost")

        remaining_count = None
        if enabled and cost and cost > 0:
            remaining_count = balance // cost

        remaining_capacity.append(
            ModuleForecastItem(
                module=module_name,
                label=config.get("label", module_name),
                icon=config.get("icon", "fa-solid fa-cube"),
                cost=cost if enabled else None,
                remaining_count=remaining_count,
                enabled=enabled,
                item_label=config.get("item_label", module_name),
                item_label_plural=config.get("item_label_plural", f"{module_name}s"),
            )
        )

    return CreditStatsResponse(
        balance=balance,
        usage_by_module=usage_by_module,
        remaining_capacity=remaining_capacity,
    )


@router.get("/{organization_id}/credits/top-users", response_model=TopCreditUsersResponse)
async def get_top_credit_users(
    organization_id: str,
    module: Optional[str] = Query(None, description="Filter by module (screen, target, explore)"),
    start_date: Optional[datetime] = Query(None, description="Filter from date"),
    end_date: Optional[datetime] = Query(None, description="Filter until date"),
    period: str = Query("30d", description="Period preset (7d, 30d, 90d, custom)"),
    search: Optional[str] = Query(None, description="Search by username or name"),
    page: int = Query(1, ge=1, description="Page number (1-indexed)"),
    size: int = Query(10, ge=1, le=100, description="Items per page"),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get top credit-consuming users in the organization.

    Requires organization.manage role for own organization or admin.organizations for any.

    Args:
        organization_id: Keycloak organization UUID
        module: Optional module filter
        start_date: Filter from date
        end_date: Filter until date
        period: Period preset (7d, 30d, 90d, custom)
        search: Search by username
        page: Page number
        size: Items per page

    Returns:
        TopCreditUsersResponse with paginated user list
    """
    if not _has_credits_access(user, org_context, organization_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied. Requires organization.manage or admin.organizations role.",
        )

    # Calculate date range
    date_from, date_to = _get_date_range(period, start_date, end_date)

    # Build query for user consumption
    query = (
        db.query(
            TokenTransaction.created_by,
            func.sum(func.abs(TokenTransaction.amount)).label("total_consumed"),
        )
        .filter(
            TokenTransaction.organization_id == organization_id,
            TokenTransaction.transaction_type == TransactionType.consume,
            TokenTransaction.created_at >= date_from,
            TokenTransaction.created_at <= date_to,
        )
    )

    # Apply module filter
    if module and module != "all":
        reference_types = MODULE_TO_REFERENCE_TYPES.get(module, [])
        if reference_types:
            query = query.filter(TokenTransaction.reference_type.in_(reference_types))

    # Group and order by consumption
    query = (
        query
        .group_by(TokenTransaction.created_by)
        .order_by(func.sum(func.abs(TokenTransaction.amount)).desc())
    )

    # Get total count before pagination
    all_results = query.all()
    total = len(all_results)

    # Apply pagination
    offset = (page - 1) * size
    user_consumption = all_results[offset:offset + size]

    # Build response items by fetching user details from Keycloak
    items = []
    rank = offset + 1

    for user_id, credits_consumed in user_consumption:
        try:
            # Fetch user details from Keycloak
            user_details = await keycloak_admin_service.get_user(user_id)

            if user_details:
                first_name = user_details.get("firstName", "")
                last_name = user_details.get("lastName", "")
                full_name = f"{first_name} {last_name}".strip() or user_details.get("username", "Unknown")

                # Generate initials
                initials = ""
                if first_name:
                    initials += first_name[0].upper()
                if last_name:
                    initials += last_name[0].upper()
                if not initials:
                    initials = full_name[0].upper() if full_name else "?"

                # Apply search filter if provided
                if search:
                    search_lower = search.lower()
                    username = user_details.get("username", "").lower()
                    email = user_details.get("email", "").lower()
                    name_lower = full_name.lower()

                    if search_lower not in username and search_lower not in email and search_lower not in name_lower:
                        continue

                items.append(
                    TopCreditUser(
                        rank=rank,
                        user_id=user_id,
                        username=user_details.get("username", ""),
                        full_name=full_name,
                        email=user_details.get("email", ""),
                        initials=initials,
                        credits_consumed=credits_consumed or 0,
                    )
                )
                rank += 1
            else:
                # User not found in Keycloak, still include with limited data
                items.append(
                    TopCreditUser(
                        rank=rank,
                        user_id=user_id,
                        username="Unknown",
                        full_name="Unknown User",
                        email="",
                        initials="?",
                        credits_consumed=credits_consumed or 0,
                    )
                )
                rank += 1

        except Exception as e:
            logger.warning(
                f"Failed to fetch user details for {user_id}: {e}",
                extra={"user_id": user_id, "error": str(e)}
            )
            # Include with limited data on error
            items.append(
                TopCreditUser(
                    rank=rank,
                    user_id=user_id,
                    username="Unknown",
                    full_name="Unknown User",
                    email="",
                    initials="?",
                    credits_consumed=credits_consumed or 0,
                )
            )
            rank += 1

    return TopCreditUsersResponse(
        items=items,
        total=total,
        page=page,
        size=size,
    )


@router.get("/{organization_id}/credits/daily-usage", response_model=DailyCreditUsageResponse)
async def get_daily_credit_usage(
    organization_id: str,
    module: Optional[str] = Query(None, description="Filter by module (screen, target, explore)"),
    start_date: Optional[datetime] = Query(None, description="Filter from date"),
    end_date: Optional[datetime] = Query(None, description="Filter until date"),
    period: str = Query("30d", description="Period preset (7d, 30d, 90d, custom)"),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get daily credit usage for an organization.

    Requires organization.manage role for own organization or admin.organizations for any.

    Args:
        organization_id: Keycloak organization UUID
        module: Optional module filter
        start_date: Filter from date
        end_date: Filter until date
        period: Period preset (7d, 30d, 90d, custom)

    Returns:
        DailyCreditUsageResponse with daily usage time series
    """
    if not _has_credits_access(user, org_context, organization_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Access denied. Requires organization.manage or admin.organizations role.",
        )

    # Calculate date range
    date_from, date_to = _get_date_range(period, start_date, end_date)

    # Build query for daily usage
    query = (
        db.query(
            func.date(TokenTransaction.created_at).label("date"),
            func.sum(func.abs(TokenTransaction.amount)).label("daily_total"),
        )
        .filter(
            TokenTransaction.organization_id == organization_id,
            TokenTransaction.transaction_type == TransactionType.consume,
            TokenTransaction.created_at >= date_from,
            TokenTransaction.created_at <= date_to,
        )
    )

    # Apply module filter
    if module and module != "all":
        reference_types = MODULE_TO_REFERENCE_TYPES.get(module, [])
        if reference_types:
            query = query.filter(TokenTransaction.reference_type.in_(reference_types))

    # Group by date and order chronologically
    query = (
        query
        .group_by(func.date(TokenTransaction.created_at))
        .order_by(func.date(TokenTransaction.created_at))
    )

    results = query.all()

    # Build response
    daily_usage = [
        DailyUsageItem(
            date=str(date),
            credits_consumed=total or 0,
        )
        for date, total in results
    ]

    return DailyCreditUsageResponse(daily_usage=daily_usage)
