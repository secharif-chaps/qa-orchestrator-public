from pydantic import BaseModel, ConfigDict
from datetime import datetime
from app.models.task import TaskStatus, TaskType

class TaskCreate(BaseModel):
    company_id: int
    type: TaskType

class TaskResponse(BaseModel):
    id: int
    company_id: int
    type: TaskType
    status: TaskStatus
    error: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True) 