from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict

from app.models.task import TaskStatus, TaskType


class TaskCreate(BaseModel):
    company_id: int
    type: TaskType


class TaskTokenUpdate(BaseModel):
    input_tokens: int | None = None
    output_tokens: int | None = None
    total_cost: float | None = None


class TaskResponse(BaseModel):
    id: int
    company_id: int
    organization_id: str | None = None
    type: TaskType
    status: TaskStatus
    error: str | None = None
    error_details: dict[str, Any] | None = None
    created_at: datetime
    updated_at: datetime
    input_tokens: int | None = None
    output_tokens: int | None = None
    total_cost: float | None = None

    model_config = ConfigDict(from_attributes=True)
