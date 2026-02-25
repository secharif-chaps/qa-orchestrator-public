from pydantic import BaseModel, ConfigDict
from datetime import datetime
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
    type: TaskType
    status: TaskStatus
    error: str | None = None
    is_prerequisite: bool = False
    created_at: datetime
    updated_at: datetime
    input_tokens: int | None = None
    output_tokens: int | None = None
    total_cost: float | None = None

    model_config = ConfigDict(from_attributes=True) 