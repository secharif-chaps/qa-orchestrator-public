"""Admin endpoints requiring admin role.

This module contains all admin-only endpoints that require specific admin roles.
Uses fastapi-keycloak for automatic role-based access control via dependency injection.
"""

from datetime import datetime, date

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.database import get_db
from app.models.company import Company
from app.models.task import Task, TaskStatus
from app.schemas.admin_usage import (
    OrganizationBreakdown,
    TimeSeriesDataPoint,
    UsageStatsResponse,
)
from app.services.keycloak_admin import keycloak_admin_service
from app.services.workflow_config import (
    WorkflowConfigResponse,
    WorkflowConfigService,
    WorkflowConfigUpdate,
)

router = APIRouter(prefix="/admin", tags=["admin"])

logger = get_logger(__name__)


# Admin company routes removed - not used in frontend
# Companies are managed via /companies endpoints with proper permission checks

# Module management endpoints moved to /organizations/{id}/modules (no /admin/ prefix)
# See app/api/endpoints/modules.py


# Workflow Configuration Endpoints


@router.get("/workflows", response_model=list[WorkflowConfigResponse])
async def get_all_workflow_configs(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"])),
):
    """Get all workflow configurations with obfuscated API keys (workflow admin only).

    Requires admin.workflows role for access.
    """

    service = WorkflowConfigService(db)
    return service.get_all_configs()


@router.put("/workflows/{task_type}", response_model=WorkflowConfigResponse)
async def update_workflow_config(
    task_type: str,
    update_data: WorkflowConfigUpdate,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"])),
):
    """Update workflow configuration (workflow admin only).

    Requires admin.workflows role for access.
    """

    service = WorkflowConfigService(db)
    updated_config = service.update_config(task_type, update_data)

    if not updated_config:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workflow configuration for task type '{task_type}' not found",
        )

    # Return response with obfuscated API key
    return WorkflowConfigResponse(
        task_type=updated_config.task_type,
        title=updated_config.title,
        api_key_obfuscated=service.obfuscate_api_key(updated_config.api_key),
        has_api_key=bool(updated_config.api_key),
        llm=updated_config.llm,
    )


# User organization assignment moved to /users/{user_id}/organization
# See app/api/endpoints/users.py


# Task Management Endpoints


@router.post("/tasks/fail-stuck")
async def fail_stuck_tasks(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Fail all pending and running tasks and clear the task queue.

    This is used to unlock the system when tasks get stuck.
    Requires admin.organizations permission.
    """

    # Get all pending and running tasks
    stuck_tasks = (
        db.query(Task)
        .filter(Task.status.in_([TaskStatus.PENDING, TaskStatus.RUNNING]))
        .all()
    )

    failed_count = 0
    for task in stuck_tasks:
        task.status = TaskStatus.ERROR
        task.error = "Task failed by admin to unlock stuck queue"
        task.updated_at = datetime.utcnow()
        failed_count += 1

    # Commit the database changes
    db.commit()

    # Clear the Celery queue
    # Purge all messages from the dify_workflows queue
    try:
        # Get the queue with the same parameters as defined in celery_app
        from kombu import Connection, Queue as KombuQueue

        from app.core.celery_app import RABBITMQ_URL

        with Connection(RABBITMQ_URL) as conn:
            channel = conn.channel()
            # Declare the queue with the same arguments as in celery_app
            queue = KombuQueue(
                "dify_workflows",
                channel=channel,
                durable=True,
                queue_arguments={"x-max-priority": 10},  # Match the celery config
            )
            queue.declare()
            # Purge the queue
            purged_count = queue.purge()

        queue_cleared = True
        queue_message = f"Purged {purged_count} messages from queue"
    except Exception as e:
        queue_cleared = False
        queue_message = f"Failed to clear queue: {str(e)}"

    return {
        "success": True,
        "tasks_failed": failed_count,
        "queue_cleared": queue_cleared,
        "queue_status": queue_message,
        "message": f"Failed {failed_count} stuck tasks and {'successfully cleared' if queue_cleared else 'attempted to clear'} the queue",
    }


# Usage Statistics Endpoints


def _get_companies_count(db: Session, start_date: date, end_date: date) -> int:
    """Query total count of companies created in the date range.

    Args:
        db: Database session
        start_date: Start of date range (inclusive)
        end_date: End of date range (inclusive)

    Returns:
        Total count of non-deleted companies created in the range
    """
    count = (
        db.query(func.count(Company.id))
        .filter(
            Company.is_deleted == False,
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .scalar()
    )
    return count or 0


def _get_task_success_rate(
    db: Session, start_date: date, end_date: date
) -> float | None:
    """Calculate task success rate for completed tasks in the date range.

    Only counts tasks with 'succeeded' or 'error' status (completed tasks).
    Excludes pending, running, and blocked tasks.

    Args:
        db: Database session
        start_date: Start of date range (inclusive)
        end_date: End of date range (inclusive)

    Returns:
        Success rate as percentage (0-100) with one decimal precision,
        or None if no completed tasks exist
    """
    # Count succeeded tasks
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

    # Count total completed tasks (succeeded + error only)
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

    # Calculate percentage with one decimal precision
    return round((succeeded_count / total_completed) * 100, 1)


def _get_active_users_count(db: Session, start_date: date, end_date: date) -> int:
    """Count unique users who created companies in the date range.

    Args:
        db: Database session
        start_date: Start of date range (inclusive)
        end_date: End of date range (inclusive)

    Returns:
        Count of unique owner_ids from non-deleted companies
    """
    count = (
        db.query(func.count(func.distinct(Company.owner_id)))
        .filter(
            Company.is_deleted == False,
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .scalar()
    )
    return count or 0


def _get_companies_over_time(
    db: Session, start_date: date, end_date: date
) -> list[TimeSeriesDataPoint]:
    """Aggregate companies by time period for the line chart.

    Groups companies by day for date ranges up to 30 days,
    by week for ranges up to 90 days, and by month for longer ranges.

    Args:
        db: Database session
        start_date: Start of date range (inclusive)
        end_date: End of date range (inclusive)

    Returns:
        List of TimeSeriesDataPoint objects ordered by period ascending
    """
    # Determine grouping period based on date range
    range_days = (end_date - start_date).days

    if range_days <= 30:
        trunc_unit = "day"
    elif range_days <= 90:
        trunc_unit = "week"
    else:
        trunc_unit = "month"

    # Query companies grouped by period
    results = (
        db.query(
            func.date_trunc(trunc_unit, Company.created_at).label("period"),
            func.count(Company.id).label("count"),
        )
        .filter(
            Company.is_deleted == False,
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .group_by(func.date_trunc(trunc_unit, Company.created_at))
        .order_by(func.date_trunc(trunc_unit, Company.created_at))
        .all()
    )

    return [
        TimeSeriesDataPoint(period=row.period.isoformat(), count=row.count)
        for row in results
    ]


async def _get_companies_by_organization(
    db: Session, start_date: date, end_date: date
) -> list[OrganizationBreakdown]:
    """Aggregate companies by organization with percentage calculation.

    Fetches organization names from Keycloak for display.
    Limits to top 10 organizations and groups remainder as "Other".

    Args:
        db: Database session
        start_date: Start of date range (inclusive)
        end_date: End of date range (inclusive)

    Returns:
        List of OrganizationBreakdown objects sorted by count descending
    """
    # Query companies grouped by organization_id
    results = (
        db.query(
            Company.organization_id,
            func.count(Company.id).label("count"),
        )
        .filter(
            Company.is_deleted == False,
            func.date(Company.created_at) >= start_date,
            func.date(Company.created_at) <= end_date,
        )
        .group_by(Company.organization_id)
        .order_by(func.count(Company.id).desc())
        .all()
    )

    if not results:
        return []

    # Calculate total for percentage
    total_count = sum(row.count for row in results)

    # Fetch all organizations from Keycloak for name lookup
    org_name_map: dict[str, str] = {}
    try:
        all_orgs = await keycloak_admin_service.get_organizations()
        org_name_map = {org["id"]: org.get("name", "Unknown") for org in all_orgs}
    except Exception as e:
        logger.warning(
            "Failed to fetch organizations from Keycloak, using IDs as names",
            extra={"error": str(e)},
        )

    # Build breakdown list, limiting to top 10
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

    # Add "Other" category if there are more than 10 organizations
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


@router.get("/usage-stats", response_model=UsageStatsResponse)
async def get_usage_stats(
    start_date: str = Query(
        ..., description="Start date in ISO format (YYYY-MM-DD)", examples=["2025-01-01"]
    ),
    end_date: str = Query(
        ..., description="End date in ISO format (YYYY-MM-DD)", examples=["2025-01-07"]
    ),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Get usage statistics for the admin dashboard.

    Returns aggregated metrics for companies, tasks, and users within
    the specified date range. Data is used to populate KPI cards, charts,
    and organization breakdown table.

    Args:
        start_date: Start of date range in ISO format (YYYY-MM-DD)
        end_date: End of date range in ISO format (YYYY-MM-DD)
        db: Database session (injected)
        user: Authenticated user with admin.organizations role (injected)

    Returns:
        UsageStatsResponse with all aggregated metrics

    Raises:
        HTTPException 400: If date format is invalid
        HTTPException 403: If user lacks admin.organizations role

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

    # Parse date strings to date objects
    try:
        start = date.fromisoformat(start_date)
        end = date.fromisoformat(end_date)
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid date format. Use YYYY-MM-DD. Error: {str(e)}",
        )

    # Validate date range
    if start > end:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="start_date must be before or equal to end_date",
        )

    # Aggregate all metrics
    companies_count = _get_companies_count(db, start, end)
    task_success_rate = _get_task_success_rate(db, start, end)
    active_users_count = _get_active_users_count(db, start, end)
    companies_over_time = _get_companies_over_time(db, start, end)
    companies_by_organization = await _get_companies_by_organization(db, start, end)

    logger.info(
        "Usage statistics retrieved successfully",
        extra={
            "companies_count": companies_count,
            "task_success_rate": task_success_rate,
            "active_users_count": active_users_count,
            "time_series_points": len(companies_over_time),
            "organizations_count": len(companies_by_organization),
        },
    )

    return UsageStatsResponse(
        companies_count=companies_count,
        task_success_rate=task_success_rate,
        active_users_count=active_users_count,
        companies_over_time=companies_over_time,
        companies_by_organization=companies_by_organization,
    )
