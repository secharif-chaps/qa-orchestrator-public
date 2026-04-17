"""Admin endpoints requiring admin role.

This module contains all admin-only endpoints that require specific admin roles.
Uses fastapi-keycloak for automatic role-based access control via dependency injection.
"""

from datetime import date, datetime

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.database import get_db
from app.models.company import Company
from app.models.task import Task, TaskStatus
from app.schemas.admin_tasks import FailStuckTasksResponse
from app.schemas.admin_usage import (
    OrganizationBreakdown,
    TimeSeriesDataPoint,
    UsageStatsResponse,
)
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/admin", tags=["admin"])

logger = get_logger(__name__)


# Task Management Endpoints


@router.post(
    "/tasks/fail-stuck",
    response_model=FailStuckTasksResponse,
    openapi_extra={"x-permissions": ["admin.organizations"]},
)
async def fail_stuck_tasks(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Fail all pending and running tasks.

    This is used to unlock the system when tasks get stuck.
    Requires admin.organizations permission.
    """
    stuck_tasks = db.query(Task).filter(Task.status.in_([TaskStatus.PENDING, TaskStatus.RUNNING])).all()

    failed_count = 0
    for task in stuck_tasks:
        task.status = TaskStatus.ERROR
        task.error = "Task failed by admin to unlock stuck queue"
        task.updated_at = datetime.utcnow()
        failed_count += 1

    db.commit()

    return {
        "success": True,
        "tasks_failed": failed_count,
        "message": f"Failed {failed_count} stuck tasks",
    }


# Usage Statistics Endpoints


def _get_companies_count(db: Session, start_date: date, end_date: date) -> int:
    """Query total count of companies created in the date range."""
    count = (
        db.query(func.count(Company.id))
        .filter(
            Company.is_deleted.is_(False),
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .scalar()
    )
    return count or 0


def _get_task_success_rate(db: Session, start_date: date, end_date: date) -> float | None:
    """Calculate task success rate for completed tasks in the date range."""
    succeeded_count = (
        db.query(func.count(Task.id))
        .filter(
            Task.status == TaskStatus.SUCCEEDED,
            func.date(Task.created_at) >= start_date,
            func.date(Task.created_at) <= end_date,
        )
        .scalar()
        or 0
    )

    total_completed = (
        db.query(func.count(Task.id))
        .filter(
            Task.status.in_([TaskStatus.SUCCEEDED, TaskStatus.ERROR]),
            func.date(Task.created_at) >= start_date,
            func.date(Task.created_at) <= end_date,
        )
        .scalar()
        or 0
    )

    if total_completed == 0:
        return None

    return round((succeeded_count / total_completed) * 100, 1)


def _get_active_users_count(db: Session, start_date: date, end_date: date) -> int:
    """Count unique users who created companies in the date range."""
    count = (
        db.query(func.count(func.distinct(Company.owner_id)))
        .filter(
            Company.is_deleted.is_(False),
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .scalar()
    )
    return count or 0


def _get_companies_over_time(db: Session, start_date: date, end_date: date) -> list[TimeSeriesDataPoint]:
    """Aggregate companies by time period for the line chart."""
    range_days = (end_date - start_date).days

    if range_days <= 30:
        trunc_unit = "day"
    elif range_days <= 90:
        trunc_unit = "week"
    else:
        trunc_unit = "month"

    results = (
        db.query(
            func.date_trunc(trunc_unit, Company.created_at).label("period"),
            func.count(Company.id).label("count"),
        )
        .filter(
            Company.is_deleted.is_(False),
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .group_by(func.date_trunc(trunc_unit, Company.created_at))
        .order_by(func.date_trunc(trunc_unit, Company.created_at))
        .all()
    )

    return [TimeSeriesDataPoint(period=row.period.isoformat(), count=row.count) for row in results]


async def _get_companies_by_organization(db: Session, start_date: date, end_date: date) -> list[OrganizationBreakdown]:
    """Aggregate companies by organization with percentage calculation."""
    results = (
        db.query(
            Company.organization_id,
            func.count(Company.id).label("count"),
        )
        .filter(
            Company.is_deleted.is_(False),
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .group_by(Company.organization_id)
        .order_by(func.count(Company.id).desc())
        .all()
    )

    if not results:
        return []

    total_count = sum(row.count for row in results)

    org_name_map: dict[str, str] = {}
    try:
        all_orgs = await keycloak_admin_service.get_organizations()
        org_name_map = {org["id"]: org.get("name", "Unknown") for org in all_orgs}
    except Exception as e:
        logger.warning(
            "Failed to fetch organizations from Keycloak, using IDs as names",
            extra={"error": str(e)},
        )

    breakdown: list[OrganizationBreakdown] = []
    other_count = 0

    for i, row in enumerate(results):
        if i < 10:
            org_name = org_name_map.get(row.organization_id, row.organization_id or "Unknown")
            percentage = round((row.count / total_count) * 100, 1) if total_count > 0 else 0.0
            breakdown.append(
                OrganizationBreakdown(
                    organization_id=row.organization_id or "unknown",
                    organization_name=org_name,
                    companies_count=row.count,
                    percentage=percentage,
                )
            )
        else:
            other_count += row.count

    if other_count > 0:
        other_percentage = round((other_count / total_count) * 100, 1) if total_count > 0 else 0.0
        breakdown.append(
            OrganizationBreakdown(
                organization_id="other",
                organization_name="Other",
                companies_count=other_count,
                percentage=other_percentage,
            )
        )

    return breakdown


@router.get(
    "/usage-stats",
    response_model=UsageStatsResponse,
    openapi_extra={"x-permissions": ["admin.organizations"]},
)
async def get_usage_stats(
    start_date: str = Query(..., description="Start date in ISO format (YYYY-MM-DD)", examples=["2025-01-01"]),
    end_date: str = Query(..., description="End date in ISO format (YYYY-MM-DD)", examples=["2025-01-07"]),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Get usage statistics for the admin dashboard.

    Requires admin.organizations role for access.
    """
    logger.info(
        "Fetching usage statistics",
        extra={
            "admin_user": user.preferred_username,
            "start_date": start_date,
            "end_date": end_date,
        },
    )

    try:
        start = date.fromisoformat(start_date)
        end = date.fromisoformat(end_date)
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid date format. Use YYYY-MM-DD. Error: {str(e)}",
        )

    if start > end:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="start_date must be before or equal to end_date",
        )

    companies_count = _get_companies_count(db, start, end)
    task_success_rate = _get_task_success_rate(db, start, end)
    active_users_count = _get_active_users_count(db, start, end)
    companies_over_time = _get_companies_over_time(db, start, end)
    companies_by_organization = await _get_companies_by_organization(db, start, end)

    return UsageStatsResponse(
        companies_count=companies_count,
        task_success_rate=task_success_rate,
        active_users_count=active_users_count,
        companies_over_time=companies_over_time,
        companies_by_organization=companies_by_organization,
    )
