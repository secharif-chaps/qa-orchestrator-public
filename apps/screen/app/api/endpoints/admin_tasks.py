"""Admin task monitoring endpoints.

This module provides endpoints for admin users to monitor and manage tasks
across all organizations. Requires admin.tasks role for access.
"""

from datetime import UTC, datetime, timedelta

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from sqlalchemy import func
from sqlalchemy.orm import Session, joinedload

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.database import get_db
from app.models.company import Company
from app.models.task import Task, TaskStatus, TaskType
from app.schemas.admin_tasks import (
    AdminTaskResponse,
    AdminTasksListResponse,
    AdminTaskStatsResponse,
    BulkRestartRequest,
    BulkRestartResponse,
    OrganizationResponse,
    OrganizationsListResponse,
)
from app.services.company import CompanyService
from app.services.keycloak_admin import keycloak_admin_service
from app.services.task_service import TaskService

logger = get_logger(__name__)

router = APIRouter(prefix="/admin/tasks", tags=["admin-tasks"])

# Internal organization identifier - organizations with this in the name are internal
INTERNAL_ORG_IDENTIFIER = "chapsvision"


@router.get(
    "",
    response_model=AdminTasksListResponse,
    openapi_extra={"x-permissions": ["admin.tasks"]},
)
async def get_admin_tasks(
    status_filter: list[str] | None = Query(None, alias="status"),
    type_filter: list[str] | None = Query(None, alias="type"),
    organization_id: str | None = Query(None),
    page: int = Query(1, ge=1),
    size: int = Query(20, ge=1, le=100),
    sort_by: str = Query("created_at"),
    sort_order: str = Query("desc"),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.tasks"])),
):
    """Get all tasks across all organizations with filtering and pagination.

    Performs lazy cleanup of all stale tasks (running > 5 minutes) before
    returning results to ensure accurate task statuses.

    Requires admin.tasks role for access.

    Args:
        status_filter: Filter by status(es): pending, running, succeeded, error
        type_filter: Filter by task type(s)
        organization_id: Filter by specific organization UUID
        page: Page number for pagination
        size: Items per page (max 100, default 20)
        sort_by: Sort field: created_at, updated_at, status, type
        sort_order: Sort direction: asc, desc
    """
    logger.info(
        "Admin fetching tasks",
        extra={
            "user": user.preferred_username,
            "filters": {
                "status": status_filter,
                "type": type_filter,
                "organization_id": organization_id,
            },
            "pagination": {"page": page, "size": size},
        },
    )

    # Lazy cleanup of all stale tasks before fetching
    task_service = TaskService(db)
    cleaned_count = task_service.cleanup_all_stale_tasks()
    if cleaned_count > 0:
        logger.info(
            f"Admin tasks endpoint cleaned up {cleaned_count} stale tasks",
            extra={"user": user.preferred_username, "cleaned_count": cleaned_count},
        )

    # Build base query with company join for company_name
    query = db.query(Task).join(Company)

    # Apply filters
    if status_filter:
        # Convert string values to TaskStatus enum
        status_enums = []
        for s in status_filter:
            try:
                status_enums.append(TaskStatus(s.lower()))
            except ValueError:
                pass  # Ignore invalid status values
        if status_enums:
            query = query.filter(Task.status.in_(status_enums))

    if type_filter:
        # Convert string values to TaskType enum
        type_enums = []
        for t in type_filter:
            try:
                type_enums.append(TaskType(t.lower()))
            except ValueError:
                pass  # Ignore invalid type values
        if type_enums:
            query = query.filter(Task.type.in_(type_enums))

    if organization_id:
        query = query.filter(Task.organization_id == organization_id)

    # Get total count before pagination
    total = query.count()

    # Apply sorting
    sort_column = getattr(Task, sort_by, Task.created_at)
    query = query.order_by(sort_column.asc()) if sort_order.lower() == "asc" else query.order_by(sort_column.desc())

    # Apply pagination
    offset = (page - 1) * size
    tasks = query.options(joinedload(Task.company)).offset(offset).limit(size).all()

    # Calculate total pages
    pages = (total + size - 1) // size if total > 0 else 1

    # Transform to response format
    items = [
        AdminTaskResponse(
            id=task.id,
            company_id=task.company_id,
            company_name=task.company.name if task.company else "Unknown",
            organization_id=task.organization_id,
            type=task.type,
            status=task.status,
            error=task.error,
            created_at=task.created_at,
            updated_at=task.updated_at,
        )
        for task in tasks
    ]

    logger.info("Admin tasks query completed", extra={"total": total, "page": page, "returned": len(items)})

    return AdminTasksListResponse(items=items, total=total, page=page, size=size, pages=pages)


@router.get(
    "/stats",
    response_model=AdminTaskStatsResponse,
    openapi_extra={"x-permissions": ["admin.tasks"]},
)
async def get_admin_task_stats(
    hours: int = Query(24, ge=1, le=168),
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.tasks"])),
):
    """Get aggregated task statistics for the summary cards.

    Requires admin.tasks role for access.

    Args:
        hours: Time range in hours for calculations
    """
    logger.info("Admin fetching task stats", extra={"user": user.preferred_username, "hours": hours})

    # Calculate time threshold
    time_threshold = datetime.now(UTC) - timedelta(hours=hours)

    # Get counts by status
    status_counts = (
        db.query(Task.status, func.count(Task.id)).filter(Task.created_at >= time_threshold).group_by(Task.status).all()
    )

    # Initialize counts
    counts = {"running": 0, "pending": 0, "succeeded": 0, "error": 0}

    for task_status, count in status_counts:
        counts[task_status.value] = count

    total_tasks = sum(counts.values())

    # Calculate success rate (succeeded / (succeeded + error))
    completed = counts["succeeded"] + counts["error"]
    success_rate = (counts["succeeded"] / completed * 100) if completed > 0 else 0.0

    # Count stuck tasks (running > 3 minutes)
    stuck_threshold = datetime.now(UTC) - timedelta(minutes=3)
    stuck_count = (
        db.query(func.count(Task.id))
        .filter(Task.status == TaskStatus.RUNNING, Task.created_at < stuck_threshold, Task.created_at >= time_threshold)
        .scalar()
    ) or 0

    logger.info(
        "Admin task stats calculated",
        extra={
            "total": total_tasks,
            "running": counts["running"],
            "pending": counts["pending"],
            "stuck_count": stuck_count,
            "success_rate": round(success_rate, 1),
        },
    )

    return AdminTaskStatsResponse(
        total_tasks=total_tasks,
        running=counts["running"],
        pending=counts["pending"],
        succeeded=counts["succeeded"],
        error=counts["error"],
        success_rate=round(success_rate, 1),
        stuck_count=stuck_count,
        time_range_hours=hours,
    )


@router.post(
    "/restart",
    response_model=BulkRestartResponse,
    openapi_extra={"x-permissions": ["admin.tasks"]},
)
async def bulk_restart_tasks(
    request: BulkRestartRequest,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.tasks"])),
):
    """Restart multiple tasks in bulk.

    Requires admin.tasks role for access.

    Only tasks with status 'running' or 'error' can be restarted.
    Tasks with status 'pending' or 'succeeded' will be skipped.
    """
    logger.info(
        "Admin bulk restart tasks",
        extra={"user": user.preferred_username, "task_count": len(request.task_ids), "task_ids": request.task_ids},
    )

    restarted: list[int] = []
    skipped: list[int] = []
    skipped_reasons: dict[str, str] = {}

    # Create company service for restart functionality
    company_service = CompanyService(db)

    for task_id in request.task_ids:
        task = db.query(Task).filter(Task.id == task_id).first()

        if not task:
            skipped.append(task_id)
            skipped_reasons[str(task_id)] = "Task not found"
            continue

        # Only restart running or error tasks
        if task.status not in [TaskStatus.RUNNING, TaskStatus.ERROR]:
            skipped.append(task_id)
            skipped_reasons[str(task_id)] = (
                f"Task status is '{task.status.value}', only 'running' or 'error' tasks can be restarted"
            )
            continue

        try:
            # Restart the task using the company service (fire-and-forget via LangGraph)
            company_service.restart_task(task_id)
            restarted.append(task_id)
            logger.info(f"Task {task_id} restarted successfully by admin")
        except Exception as e:
            skipped.append(task_id)
            skipped_reasons[str(task_id)] = f"Failed to restart: {str(e)}"
            logger.error(
                f"Failed to restart task {task_id}", exc_info=True, extra={"task_id": task_id, "error": str(e)}
            )

    logger.info(
        "Bulk restart completed",
        extra={
            "restarted_count": len(restarted),
            "skipped_count": len(skipped),
            "restarted": restarted,
            "skipped": skipped,
        },
    )

    return BulkRestartResponse(restarted=restarted, skipped=skipped, skipped_reasons=skipped_reasons)


# Organizations endpoint in a separate router to avoid prefix issues
org_router = APIRouter(prefix="/admin/organizations", tags=["admin-tasks"])


@org_router.get(
    "",
    response_model=OrganizationsListResponse,
    openapi_extra={"x-permissions": ["admin.tasks"]},
)
async def get_admin_organizations(user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.tasks"]))):
    """Fetch all organizations for the organization filter dropdown and name mapping.

    Requires admin.tasks role for access.

    Returns organizations from Keycloak with is_internal flag set for
    internal organizations (those containing 'chapsvision' in their name).
    """
    logger.info("Admin fetching organizations", extra={"user": user.preferred_username})

    try:
        # Fetch organizations from Keycloak
        keycloak_orgs = await keycloak_admin_service.get_organizations()

        organizations = [
            OrganizationResponse(
                id=org.get("id", ""),
                name=org.get("name", "Unknown"),
                is_internal=INTERNAL_ORG_IDENTIFIER in org.get("name", "").lower(),
            )
            for org in keycloak_orgs
            if org.get("id")  # Only include orgs with valid IDs
        ]

        logger.info("Organizations fetched successfully", extra={"count": len(organizations)})

        return OrganizationsListResponse(organizations=organizations)

    except Exception as e:
        logger.error("Failed to fetch organizations from Keycloak", exc_info=True, extra={"error": str(e)})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to fetch organizations from Keycloak"
        )
