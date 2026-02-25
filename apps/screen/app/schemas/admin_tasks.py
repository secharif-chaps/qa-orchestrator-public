"""Pydantic schemas for admin task monitoring endpoints."""

from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field

from app.models.task import TaskStatus, TaskType


class AdminTaskResponse(BaseModel):
    """Response schema for a task in admin monitoring view."""

    id: int
    company_id: int
    company_name: str
    organization_id: str | None
    type: TaskType
    status: TaskStatus
    error: str | None = None
    is_prerequisite: bool = False
    created_at: datetime | None = None
    updated_at: datetime | None = None

    model_config = ConfigDict(from_attributes=True)


class AdminTasksListResponse(BaseModel):
    """Paginated response for admin tasks list."""

    items: list[AdminTaskResponse]
    total: int
    page: int
    size: int
    pages: int


class AdminTaskStatsResponse(BaseModel):
    """Aggregated task statistics for dashboard summary cards."""

    total_tasks: int
    running: int
    pending: int
    blocked: int
    succeeded: int
    error: int
    success_rate: float
    stuck_count: int
    time_range_hours: int


class BulkRestartRequest(BaseModel):
    """Request schema for bulk task restart."""

    task_ids: list[int] = Field(..., min_length=1, max_length=50)


class BulkRestartResponse(BaseModel):
    """Response schema for bulk task restart."""

    restarted: list[int]
    skipped: list[int]
    skipped_reasons: dict[str, str]


class OrganizationResponse(BaseModel):
    """Response schema for organization in admin view."""

    id: str
    name: str
    is_internal: bool = False


class OrganizationsListResponse(BaseModel):
    """Response schema for organizations list."""

    organizations: list[OrganizationResponse]
