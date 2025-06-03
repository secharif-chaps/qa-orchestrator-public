from typing import Dict, List, Optional, Any
from pydantic import BaseModel, Field, ConfigDict
from datetime import datetime
from app.domain.entities.task import TaskStatus, TaskType

# Base Pydantic models
class SourcedValue(BaseModel):
    value: Any
    source: str

class PendingState(BaseModel):
    pending: bool
    error: Optional[str] = None

# Base Company model
class CompanyBase(BaseModel):
    name: str
    website: str

# Company creation and update models
class CompanyCreate(CompanyBase):
    pass

class CompanyUpdate(BaseModel):
    name: Optional[str] = None
    website: Optional[str] = None
    profile: Optional[Dict[str, Any]] = None
    digital: Optional[Dict[str, Any]] = None
    timeline: Optional[Dict[str, Any]] = None
    products: Optional[Dict[str, Any]] = None
    jobs: Optional[Dict[str, Any]] = None
    csr: Optional[Dict[str, Any]] = None
    press: Optional[Dict[str, Any]] = None
    team: Optional[List[Dict[str, Any]]] = None

# Task models
class TaskBase(BaseModel):
    type: TaskType
    status: TaskStatus
    error: Optional[str] = None

class TaskCreate(TaskBase):
    company_id: int

class TaskResponse(TaskBase):
    id: int
    company_id: int
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)

# Full Company response model
class CompanyResponse(CompanyBase):
    id: int
    profile: Dict[str, Any] = Field(default_factory=dict)
    digital: Dict[str, Any] = Field(default_factory=dict)
    timeline: Dict[str, Any] = Field(default_factory=dict)
    products: Dict[str, Any] = Field(default_factory=dict)
    jobs: Dict[str, Any] = Field(default_factory=dict)
    csr: Dict[str, Any] = Field(default_factory=dict)
    press: Dict[str, Any] = Field(default_factory=dict)
    team: List[Dict[str, Any]] = Field(default_factory=list)
    error: Optional[str] = None
    created_at: datetime
    updated_at: datetime
    tasks: List[TaskResponse] = Field(default_factory=list)
    
    model_config = ConfigDict(from_attributes=True) 